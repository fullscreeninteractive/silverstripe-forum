<?php

/**
 * Forum represents a collection of forum threads. Each thread is a different topic on
 * the site. You can customize permissions on a per forum basis in the CMS.
 *
 * @todo Implement PermissionProvider for editing, creating forums.
 *
 * @package forum
 */

class Forum extends Page {

	private static $allowed_children = 'none';

	private static $icon = "forum/images/treeicons/user";

	/**
	 * Enable this to automatically notify moderators when a message is posted
	 * or edited on his forums.
	 */
	static $notify_moderators = false;

	private static $db = array(
		"Abstract" => "Text",
		"CanPostType" => "Enum('Inherit, Anyone, LoggedInUsers, OnlyTheseUsers, NoOne', 'Inherit')",
		"CanAttachFiles" => "Boolean",
	);

	private static $has_one = array(
		"Moderator" => "Member",
		"Category" => "ForumCategory"
	);

	private static $many_many = array(
		'Moderators' => 'Member',
		'PosterGroups' => 'Group'
	);

	private static $defaults = array(
		"ForumPosters" => "LoggedInUsers"
	);

	/**
	 * Number of posts to include in the thread view before pagination takes effect.
	 *
	 * @var int
	 */
	static $posts_per_page = 8;

	/**
	 * When migrating from older versions of the forum it used post ID as the url token
	 * as of forum 1.0 we now use ThreadID. If you want to enable 301 redirects from post to thread ID
	 * set this to true
	 *
	 * @var bool
	 */
	static $redirect_post_urls_to_thread = false;

	/**
	 * Check if the user can view the forum.
	 */
	function canView($member = null) {
		if(!$member) $member = Member::currentUser();
		return (parent::canView($member) || $this->canModerate($member));
	}

	/**
	 * Check if the user can post to the forum and edit his own posts.
	 */
	function canPost($member = null) {
		if(!$member) $member = Member::currentUser();

		if($this->CanPostType == "Inherit") {
			$holder = $this->getForumHolder();
			if ($holder) {
				return $holder->canPost($member);
			}

			return false;
		}

		if($this->CanPostType == "NoOne") return false;

		if($this->CanPostType == "Anyone" || $this->canEdit($member)) return true;

		if($member = Member::currentUser()) {
			if($member->IsSuspended()) return false;
			if($member->IsBanned()) return false;

			if($this->CanPostType == "LoggedInUsers") return true;

			if($groups = $this->PosterGroups()) {
				foreach($groups as $group) {
					if($member->inGroup($group)) return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if user has access to moderator panel and can delete posts and threads.
	 */
	function canModerate($member = null) {
		if(!$member) $member = Member::currentUser();

		if(!$member) return false;

		// Admins
		if (Permission::checkMember($member, 'ADMIN')) return true;

		// Moderators
		if ($member->isModeratingForum($this)) return true;

		return false;
	}

	/**
	 * Can we attach files to topics/posts inside this forum?
	 *
	 * @return bool Set to TRUE if the user is allowed to, to FALSE if they're
	 *              not
	 */
	function canAttach($member = null) {
		return $this->CanAttachFiles ? true : false;
	}

	function requireTable() {
		// Migrate permission columns
		if(DB::getConn()->hasTable('Forum')) {
			$fields = DB::getConn()->fieldList('Forum');
			if(in_array('ForumPosters', array_keys($fields)) && !in_array('CanPostType', array_keys($fields))) {
				DB::getConn()->renameField('Forum', 'ForumPosters', 'CanPostType');
				DB::alteration_message('Migrated forum permissions from "ForumPosters" to "CanPostType"', "created");
			}
		}

		parent::requireTable();
	}

	/**
	 * Add default records to database
	 *
	 * This function is called whenever the database is built, after the
	 * database tables have all been created.
 	 */
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();

		$code = "ACCESS_FORUM";
		if(!($forumGroup = Group::get()->filter('Code', 'forum-members')->first())) {
			$group = new Group();
			$group->Code = 'forum-members';
			$group->Title = "Forum Members";
			$group->write();

			Permission::grant( $group->ID, $code );
			DB::alteration_message(_t('Forum.GROUPCREATED','Forum Members group created'),'created');
		}
		else if(!Permission::get()->filter(array('GroupID' => $forumGroup->ID, 'Code' => $code))->exists()) {
			Permission::grant($forumGroup->ID, $code);
		}

		if(!($category = ForumCategory::get()->first())) {
			$category = new ForumCategory();
			$category->Title = _t('Forum.DEFAULTCATEGORY', 'General');
			$category->write();
		}

		if(!ForumHolder::get()->exists()) {
			$forumholder = new ForumHolder();
			$forumholder->Title = "Forums";
			$forumholder->URLSegment = "forums";
			$forumholder->Content = "<p>"._t('Forum.WELCOMEFORUMHOLDER','Welcome to SilverStripe Forum Module! This is the default ForumHolder page. You can now add forums.')."</p>";
			$forumholder->Status = "Published";
			$forumholder->write();
			$forumholder->publish("Stage", "Live");
			DB::alteration_message(_t('Forum.FORUMHOLDERCREATED','ForumHolder page created'),"created");

			$forum = new Forum();
			$forum->Title = _t('Forum.TITLE','General Discussion');
			$forum->URLSegment = "general-discussion";
			$forum->ParentID = $forumholder->ID;
			$forum->Content = "<p>"._t('Forum.WELCOMEFORUM','Welcome to SilverStripe Forum Module! This is the default Forum page. You can now add topics.')."</p>";
			$forum->Status = "Published";
			$forum->CategoryID = $category->ID;
			$forum->write();
			$forum->publish("Stage", "Live");

			DB::alteration_message(_t('Forum.FORUMCREATED','Forum page created'),"created");
		}
 	}

	/**
	 * Check if we can and should show forums in categories
	 */
	function getShowInCategories() {
		$holder = $this->getForumHolder();
		if ($holder) {
			return $holder->getShowInCategories();
		}
	}

	/**
	 * Returns a FieldList with which to create the CMS editing form
	 *
	 * @return FieldList The fields to be displayed in the CMS.
	 */
	function getCMSFields() {
		Requirements::javascript("forum/javascript/ForumAccess.js");
		Requirements::css("forum/css/Forum_CMS.css");

	  	$fields = parent::getCMSFields();

		$fields->addFieldToTab("Root.Access", new HeaderField(_t('Forum.ACCESSPOST','Who can post to the forum?'), 2));
		$fields->addFieldToTab("Root.Access", $optionSetField = new OptionsetField("CanPostType", "", array(
			"Inherit" => "Inherit",
		  	"Anyone" => _t('Forum.READANYONE', 'Anyone'),
		  	"LoggedInUsers" => _t('Forum.READLOGGEDIN', 'Logged-in users'),
		  	"OnlyTheseUsers" => _t('Forum.READLIST', 'Only these people (choose from list)'),
			"NoOne" => _t('Forum.READNOONE', 'Nobody. Make Forum Read Only')
		)));

		$optionSetField->addExtraClass('ForumCanPostTypeSelector');

		$fields->addFieldsToTab("Root.Access", array(
			new TreeMultiselectField("PosterGroups", _t('Forum.GROUPS',"Groups")),
			new OptionsetField("CanAttachFiles", _t('Forum.ACCESSATTACH','Can users attach files?'), array(
				"1" => _t('Forum.YES','Yes'),
				"0" => _t('Forum.NO','No')
			))
		));


		//Dropdown of forum category selection.
		$categories = ForumCategory::get()->map();

		$fields->addFieldsToTab(
			"Root.Main",
			DropdownField::create('CategoryID', _t('Forum.FORUMCATEGORY', 'Forum Category'), $categories),
			'Content'
		);

		//GridField Config - only need to attach or detach Moderators with existing Member accounts.
		$moderatorsConfig = GridFieldConfig::create()
			->addComponent(new GridFieldButtonRow('before'))
			->addComponent(new GridFieldAddExistingAutocompleter('buttons-before-right'))
			->addComponent(new GridFieldToolbarHeader())
			->addComponent($sort = new GridFieldSortableHeader())
			->addComponent($columns = new GridFieldDataColumns())
			->addComponent(new GridFieldDeleteAction(true))
			->addComponent(new GridFieldPageCount('toolbar-header-right'))
			->addComponent($pagination = new GridFieldPaginator());

		// Use GridField for Moderator management
		$moderators = GridField::create(
			'Moderators',
			_t('MODERATORS', 'Moderators for this forum'),
			$this->Moderators(),
			$moderatorsConfig
			);

		$columns->setDisplayFields(array(
			'Nickname' => 'Nickname',
			'FirstName' => 'First name',
			'Surname' => 'Surname',
			'Email'=> 'Email',
			'LastVisited.Long' => 'Last Visit'
		));

		$sort->setThrowExceptionOnBadDataType(false);
		$pagination->setThrowExceptionOnBadDataType(false);

		$fields->addFieldToTab('Root.Moderators', $moderators);
		
		$this->extend('updateCMSFieldsEnd', $fields);

		return $fields;
	}

	/**
	 * Create breadcrumbs
	 *
	 * @param int $maxDepth Maximal lenght of the breadcrumb navigation
	 * @param bool $unlinked Set to TRUE if the breadcrumb should consist of
	 *                       links, otherwise FALSE.
	 * @param bool $stopAtPageType Currently not used
	 * @param bool $showHidden Set to TRUE if also hidden pages should be
	 *                         displayed
	 * @return string HTML code to display breadcrumbs
	 */
	public function Breadcrumbs($maxDepth = null, $unlinked = false, $stopAtPageType = false, $showHidden = false) {
		$page = $this;
		$nonPageParts = array();
		$parts = array();

		$controller = Controller::curr();
		$params = $controller->getURLParams();

		$forumThreadID = $params['ID'];
		if(is_numeric($forumThreadID)) {
			if($topic = ForumThread::get()->byID($forumThreadID)) {
				$nonPageParts[] = Convert::raw2xml($topic->getTitle());
			}
		}

		while($page && (!$maxDepth || sizeof($parts) < $maxDepth)) {
			if($showHidden || $page->ShowInMenus || ($page->ID == $this->ID)) {
				if($page->URLSegment == 'home') $hasHome = true;

				if($nonPageParts) {
					$parts[] = '<a href="' . $page->Link() . '">' . Convert::raw2xml($page->Title) . '</a>';
				}
				else {
					$parts[] = (($page->ID == $this->ID) || $unlinked)
							? Convert::raw2xml($page->Title)
							: '<a href="' . $page->Link() . '">' . Convert::raw2xml($page->Title) . '</a>';
				}
			}

			$page = $page->Parent;
		}

		return implode(" &raquo; ", array_reverse(array_merge($nonPageParts,$parts)));
	}

	/**
	 * Helper Method from the template includes. Uses $ForumHolder so in order for it work
	 * it needs to be included on this page
	 *
	 * @return ForumHolder
	 */
	function getForumHolder() {
		$holder = $this->Parent();
		if ($holder->ClassName=='ForumHolder') return $holder;
	}

	/**
	 * Get the latest posting of the forum. For performance the forum ID is stored on the
	 * {@link Post} object as well as the {@link Forum} object
	 *
	 * @return Post
	 */
	function getLatestPost() {
		return Post::get()->filter('ForumID', $this->ID)->sort('"Post"."ID" DESC')->first();
	}

	/**
	 * Get the number of total topics (threads) in this Forum
	 *
	 * @return int Returns the number of topics (threads)
	 */
	function getNumTopics() {
		return DB::query(sprintf('SELECT COUNT("ID") FROM "ForumThread" WHERE "ForumID" = \'%s\'', $this->ID))->value();
	}

	/**
	 * Get the number of total posts
	 *
	 * @return int
	 */
	function getNumPosts() {
		return DB::query(sprintf('SELECT COUNT("ID") FROM "Post" WHERE "ForumID" = \'%s\'', $this->ID))->value();
	}

	/**
	 * Get the number of distinct Authors
	 *
	 * @return int
	 */
	function getNumAuthors() {
		return DB::query(sprintf('SELECT COUNT(DISTINCT "AuthorID") FROM "Post" WHERE "ForumID" = \'%s\'', $this->ID))->value();
	}

	/**
	 * Returns the Topics (the first Post of each Thread) for this Forum
	 * @return DataList
	 */
	function getTopics() {
		// Get a list of Posts
		$posts = Post::get();

		// Get the underlying query and change it to return the ThreadID and Max(Created) and Max(ID) for each thread
		// of those posts
		$postQuery = $posts->dataQuery()->query();

		$postQuery
			->setSelect(array())
			->selectField('MAX("Post"."Created")', 'PostCreatedMax')
			->selectField('MAX("Post"."ID")', 'PostIDMax')
			->selectField('"ThreadID"')
			->setGroupBy('"ThreadID"')
			->addWhere(sprintf('"ForumID" = \'%s\'', $this->ID))
			->setDistinct(false);

		// Get a list of forum threads inside this forum that aren't sticky
		$threads = ForumThread::get()->filter(array(
			'ForumID' => $this->ID,
			'IsGlobalSticky' => 0,
			'IsSticky' => 0
		));

		// Get the underlying query and change it to inner join on the posts list to just show threads that
		// have approved (and maybe awaiting) posts, and sort the threads by the most recent post
		$threadQuery = $threads->dataQuery()->query();
		$threadQuery
			->addSelect(array('"PostMax"."PostCreatedMax", "PostMax"."PostIDMax"'))
			->addFrom('INNER JOIN ('.$postQuery->sql().') AS "PostMax" ON ("PostMax"."ThreadID" = "ForumThread"."ID")')
			->addOrderBy(array('"PostMax"."PostCreatedMax" DESC', '"PostMax"."PostIDMax" DESC'))
			->setDistinct(false);

		// Alter the forum threads list to use the new query
		$threads = $threads->setDataQuery(new Forum_DataQuery('ForumThread', $threadQuery));

		// And return the results
		return $threads->exists() ? new PaginatedList($threads, $_GET) : null;
	}



	/*
	 * Returns the Sticky Threads
	 * @param boolean $include_global Include Global Sticky Threads in the results (default: true)
	 * @return DataList
	 */
	 function getStickyTopics($include_global = true) {
		// Get Threads that are sticky & in this forum
		$where = '("ForumThread"."ForumID" = '.$this->ID.' AND "ForumThread"."IsSticky" = 1)';
		// Get Threads that are globally sticky
		if ($include_global) $where .= ' OR ("ForumThread"."IsGlobalSticky" = 1)';

		// Get the underlying query
		$query = ForumThread::get()->where($where)->dataQuery()->query();

		// Sort by the latest Post in each thread's Created date
		$query
			->addSelect('"PostMax"."PostMax"')
			// TODO: Confirm this works in non-MySQL DBs
			->addFrom(sprintf(
				'LEFT JOIN (SELECT MAX("Created") AS "PostMax", "ThreadID" FROM "Post" WHERE "ForumID" = \'%s\' GROUP BY "ThreadID") AS "PostMax" ON ("PostMax"."ThreadID" = "ForumThread"."ID")',
				$this->ID
			))
			->addOrderBy('"PostMax"."PostMax" DESC')
			->setDistinct(false);

		// Build result as ArrayList
		$res = new ArrayList();
		$rows = $query->execute();
		if ($rows) foreach ($rows as $row) $res->push(new ForumThread($row));

		return $res;
	}
}

/**
 * The forum controller class
 *
 * @package forum
 */
class Forum_Controller extends Page_Controller {

	private static $allowed_actions = array(
		'AdminFormFeatures',
		'deleteattachment',
		'deletepost',
		'editpost',
		'markasspam',
		'PostMessageForm',
		'reply',
		'show',
		'starttopic',
		'subscribe',
		'unsubscribe',
		'rss',
		'ban',
		'ghost'
	);


	function init() {
		parent::init();
		if($this->redirectedTo()) return;

		Requirements::javascript(THIRDPARTY_DIR . "/jquery/jquery.js");
		Requirements::javascript("forum/javascript/Forum.js");
		Requirements::javascript("forum/javascript/jquery.MultiFile.js");

		Requirements::themedCSS('Forum','forum','all');

		RSSFeed::linkToFeed($this->Parent()->Link("rss/forum/$this->ID"), sprintf(_t('Forum.RSSFORUM',"Posts to the '%s' forum"),$this->Title));
	 	RSSFeed::linkToFeed($this->Parent()->Link("rss"), _t('Forum.RSSFORUMS','Posts to all forums'));

 	  	if(!$this->canView()) {
 		  	$messageSet = array(
				'default' => _t('Forum.LOGINDEFAULT','Enter your email address and password to view this forum.'),
				'alreadyLoggedIn' => _t('Forum.LOGINALREADY','I&rsquo;m sorry, but you can&rsquo;t access this forum until you&rsquo;ve logged in. If you want to log in as someone else, do so below'),
				'logInAgain' => _t('Forum.LOGINAGAIN','You have been logged out of the forums. If you would like to log in again, enter a username and password below.')
			);

			Security::permissionFailure($this, $messageSet);
			return;
 		}

		// Log this visit to the ForumMember if they exist
		$member = Member::currentUser();
		if($member && Config::inst()->get('ForumHolder', 'currently_online_enabled')) {
 			$member->LastViewed = date("Y-m-d H:i:s");
 			$member->write();
 		}

		// Set the back url
		if(isset($_SERVER['REQUEST_URI'])) {
			Session::set('BackURL', $_SERVER['REQUEST_URI']);
		} else {
			Session::set('BackURL', $this->Link());
		}
	}

	/**
	 * A convenience function which provides nice URLs for an rss feed on this forum.
	 */
	function rss() {
		$this->redirect($this->Parent()->Link("rss/forum/$this->ID"), 301);
	}

	/**
	 * Is OpenID support available?
	 *
	 * This method checks if the {@link OpenIDAuthenticator} is available and
	 * registered.
	 *
	 * @return bool Returns TRUE if OpenID is available, FALSE otherwise.
	 */
	function OpenIDAvailable() {
		return $this->Parent()->OpenIDAvailable();
	}

	/**
	 * Subscribe a user to a thread given by an ID.
	 *
	 * Designed to be called via AJAX so return true / false
	 *
	 * @return bool
	 */
	function subscribe(SS_HTTPRequest $request) {
		// Check CSRF
		if (!SecurityToken::inst()->checkRequest($request)) {
			return $this->httpError(400);
		}

		if(Member::currentUser() && !ForumThread_Subscription::already_subscribed($this->urlParams['ID'])) {
			$obj = new ForumThread_Subscription();
			$obj->ThreadID = (int) $this->urlParams['ID'];
			$obj->MemberID = Member::currentUserID();
			$obj->LastSent = date("Y-m-d H:i:s");
			$obj->write();

			die('1');
		}

		return false;
	}

	/**
	 * Unsubscribe a user from a thread by an ID
	 *
	 * Designed to be called via AJAX so return true / false
	 *
	 * @return bool
	 */
	function unsubscribe(SS_HTTPRequest $request) {
		$member = Member::currentUser();

		if(!$member) Security::permissionFailure($this, _t('LOGINTOUNSUBSCRIBE', 'To unsubscribe from that thread, please log in first.'));

		if(ForumThread_Subscription::already_subscribed($this->urlParams['ID'], $member->ID)) {

			DB::query("
				DELETE FROM \"ForumThread_Subscription\"
				WHERE \"ThreadID\" = '". Convert::raw2sql($this->urlParams['ID']) ."'
				AND \"MemberID\" = '$member->ID'");

			die('1');
		}

		return false;
	}

	/**
	 * Mark a post as spam. Deletes any posts or threads created by that user
	 * and removes their user account from the site
	 *
	 * Must be logged in and have the correct permissions to do marking
	 */
	function markasspam(SS_HTTPRequest $request) {
		$currentUser = Member::currentUser();
		if(!isset($this->urlParams['ID'])) return $this->httpError(400);
		if(!$this->canModerate()) return $this->httpError(403);

		// Check CSRF token
		if (!SecurityToken::inst()->checkRequest($request)) {
			return $this->httpError(400);
		}

		$post = Post::get()->byID($this->urlParams['ID']);
		if($post) {
			// post was the start of a thread, Delete the whole thing
			if($post->isFirstPost()) $post->Thread()->delete();

			// Delete the current post
			$post->delete();
			$post->extend('onAfterMarkAsSpam');

			// Log deletion event
			SS_Log::log(sprintf(
				'Marked post #%d as spam, by moderator %s (#%d)',
				$post->ID,
				$currentUser->Email,
				$currentUser->ID
			), SS_Log::NOTICE);

			// Suspend the member (rather than deleting him),
			// which gives him or a moderator the chance to revoke a decision.
			if($author = $post->Author()) {
				$author->SuspendedUntil = date('Y-m-d', strtotime('+99 years', SS_Datetime::now()->Format('U')));
				$author->write();
			}

			SS_Log::log(sprintf(
				'Suspended member %s (#%d) for spam activity, by moderator %s (#%d)',
				$author->Email,
				$author->ID,
				$currentUser->Email,
				$currentUser->ID
			), SS_Log::NOTICE);
		}

		return (Director::is_ajax()) ? true : $this->redirect($this->Link());
	}


	public function ban(SS_HTTPRequest $r) {
		if(!$r->param('ID')) return $this->httpError(404);
		if(!$this->canModerate()) return $this->httpError(403);

		$member = Member::get()->byID($r->param('ID'));
		if (!$member || !$member->exists()) return $this->httpError(404);

		$member->ForumStatus = 'Banned';
		$member->write();

		// Log event
		$currentUser = Member::currentUser();
		SS_Log::log(sprintf(
			'Banned member %s (#%d), by moderator %s (#%d)',
			$member->Email,
			$member->ID,
			$currentUser->Email,
			$currentUser->ID
		), SS_Log::NOTICE);

		return ($r->isAjax()) ? true : $this->redirectBack();
	}

	public function ghost(SS_HTTPRequest $r) {
		if(!$r->param('ID')) return $this->httpError(400);
		if(!$this->canModerate()) return $this->httpError(403);

		$member = Member::get()->byID($r->param('ID'));
		if (!$member || !$member->exists()) return $this->httpError(404);

		$member->ForumStatus = 'Ghost';
		$member->write();

		// Log event
		$currentUser = Member::currentUser();
		SS_Log::log(sprintf(
			'Ghosted member %s (#%d), by moderator %s (#%d)',
			$member->Email,
			$member->ID,
			$currentUser->Email,
			$currentUser->ID
		), SS_Log::NOTICE);

		return ($r->isAjax()) ? true : $this->redirectBack();
	}

	/**
	 * Get posts to display. This method assumes an URL parameter "ID" which contains the thread ID.
	 * @param string sortDirection The sort order direction, either ASC for ascending (default) or DESC for descending
	 * @return DataObjectSet Posts
	 */
	function Posts($sortDirection = "ASC") {
		$numPerPage = Forum::$posts_per_page;

		$posts = Post::get()
			->filter('ThreadID', $this->urlParams['ID'])
			->sort('Created', $sortDirection);

		if(isset($_GET['showPost']) && !isset($_GET['start'])) {
			$postIDList = clone $posts;
			$postIDList = $postIDList->select('ID')->toArray();

			if($postIDList->exists()) {
				$foundPos = array_search($_GET['showPost'], $postIDList);
				$_GET['start'] = floor($foundPos / $numPerPage) * $numPerPage;
			}
		}

		if(!isset($_GET['start'])) $_GET['start'] = 0;

		$member = Member::currentUser();

		/*
		 * Don't show posts of banned or ghost members, unless current Member
		 * is a ghost member and owner of current post
		 */

		$posts = $posts->exclude(array(
			'Author.ForumStatus' => 'Banned'
		));

		if($member) {
			$posts = $posts->exclude(array(
				'Author.ForumStatus' => 'Ghost',
				'Author.ID:not' => $member->ID
			));
		} else {
			$posts = $posts->exclude(array(
				'Author.ForumStatus' => 'Ghost'
			));
		}

		$paginated = new PaginatedList($posts, $_GET);
		$paginated->setPageLength(Forum::$posts_per_page);
		return $paginated;
	}

	/**
	 * Get the usable BB codes
	 *
	 * @return DataObjectSet Returns the usable BB codes
	 * @see BBCodeParser::usable_tags()
	 */
	function BBTags() {
		return BBCodeParser::usable_tags();
	}

	/**
	 * Section for dealing with reply / edit / create threads form
	 *
	 * @return Form Returns the post message form
	 */
	function PostMessageForm($addMode = false, $post = false) {
		$thread = false;

		if($post) {
			$thread = $post->Thread();
		} else if(isset($this->urlParams['ID']) && is_numeric($this->urlParams['ID'])) {
			$thread = DataObject::get_by_id('ForumThread', $this->urlParams['ID']);
		}

		// Check permissions
		$messageSet = array(
			'default' => _t('Forum.LOGINTOPOST','You\'ll need to login before you can post to that forum. Please do so below.'),
			'alreadyLoggedIn' => _t('Forum.LOGINTOPOSTLOGGEDIN',
				 'I\'m sorry, but you can\'t post to this forum until you\'ve logged in.'
			  	.'If you want to log in as someone else, do so below. If you\'re logged in and you still can\'t post, you don\'t have the correct permissions to post.'),
			'logInAgain' => _t('Forum.LOGINTOPOSTAGAIN','You have been logged out of the forums.  If you would like to log in again to post, enter a username and password below.'),
		);

		// Creating new thread
		if ($addMode && !$this->canPost()) {
 			Security::permissionFailure($this, $messageSet);
			return false;
		}

		// Replying to existing thread
		if (!$addMode && !$post && $thread && !$thread->canPost()) {
 			Security::permissionFailure($this, $messageSet);
			return false;
		}

		// Editing existing post
		if (!$addMode && $post && !$post->canEdit()) {
 			Security::permissionFailure($this, $messageSet);
			return false;
		}

		$forumBBCodeHint = $this->renderWith('Forum_BBCodeHint');

		$fields = new FieldList(
			($post && $post->isFirstPost() || !$thread) ? new TextField("Title", _t('Forum.FORUMTHREADTITLE', 'Title')) : new ReadonlyField('Title',  _t('Forum.FORUMTHREADTITLE', ''), 'Re:'. $thread->Title),
			new TextareaField("Content", _t('Forum.FORUMREPLYCONTENT', 'Content')),
			new LiteralField(
				"BBCodeHelper",
				"<div class=\"BBCodeHint\">[ <a href=\"#BBTagsHolder\" id=\"BBCodeHint\">" .
				_t('Forum.BBCODEHINT','View Formatting Help') .
				"</a> ]</div>" .
				$forumBBCodeHint
			),
			new CheckboxField("TopicSubscription",
				_t('Forum.SUBSCRIBETOPIC','Subscribe to this topic (Receive email notifications when a new reply is added)'),
				($thread) ? $thread->getHasSubscribed() : false)
		);

		if($thread) $fields->push(new HiddenField('ThreadID', 'ThreadID', $thread->ID));
		if($post) $fields->push(new HiddenField('ID', 'ID', $post->ID));

		// Check if we can attach files to this forum's posts
		if($this->canAttach()) {
			$fields->push(FileField::create("Attachment", _t('Forum.ATTACH', 'Attach file')));
		}

		// If this is an existing post check for current attachments and generate
		// a list of the uploaded attachments
		if($post && $attachmentList = $post->Attachments()) {
			if($attachmentList->exists()) {
				$attachments = "<div id=\"CurrentAttachments\"><h4>". _t('Forum.CURRENTATTACHMENTS', 'Current Attachments') ."</h4><ul>";
				$link = $this->Link();
				// An instance of the security token
				$token = SecurityToken::inst();

				foreach($attachmentList as $attachment) {
					// Generate a link properly, since it requires a security token
					$attachmentLink = Controller::join_links($link, 'deleteattachment', $attachment->ID);
					$attachmentLink = $token->addToUrl($attachmentLink);

					$attachments .= "<li class='attachment-$attachment->ID'>$attachment->Name [<a href='{$attachmentLink}' rel='$attachment->ID' class='deleteAttachment'>"
							. _t('Forum.REMOVE','remove') . "</a>]</li>";
				}
				$attachments .= "</ul></div>";

				$fields->push(new LiteralField('CurrentAttachments', $attachments));
			}
		}

		$actions = new FieldList(
			new FormAction("doPostMessageForm", _t('Forum.REPLYFORMPOST', 'Post'))
		);

		$required = $addMode === true ? new RequiredFields("Title", "Content") : new RequiredFields("Content");

		$form = new Form($this, 'PostMessageForm', $fields, $actions, $required);

		$this->extend('updatePostMessageForm', $form, $post);

		if($post) $form->loadDataFrom($post);

		return $form;
	}

	/**
	 * Wrapper for older templates. Previously the new, reply and edit forms were 3 separate
	 * forms, they have now been refactored into 1 form. But in order to not break existing
	 * themes too much just include this.
	 *
	 * @deprecated 0.5
	 * @return Form
	 */
	function ReplyForm() {
		user_error('Please Use $PostMessageForm in your template rather that $ReplyForm', E_USER_WARNING);

		return $this->PostMessageForm();
	}

	/**
	 * Post a message to the forum. This method is called whenever you want to make a
	 * new post or edit an existing post on the forum
	 *
	 * @param Array - Data
	 * @param Form - Submitted Form
	 */
	function doPostMessageForm($data, $form) {
		$member = Member::currentUser();
		$content = (isset($data['Content'])) ? $this->filterLanguage($data["Content"]) : "";
		$title = (isset($data['Title'])) ? $this->filterLanguage($data["Title"]) : false;

		// If a thread id is passed append the post to the thread. Otherwise create
		// a new thread
		$thread = false;
		if(isset($data['ThreadID'])) {
			$thread = DataObject::get_by_id('ForumThread', $data['ThreadID']);
		}

		// If this is a simple edit the post then handle it here. Look up the correct post,
		// make sure we have edit rights to it then update the post
		$post = false;
		if(isset($data['ID'])) {
			$post = DataObject::get_by_id('Post', $data['ID']);

			if($post && $post->isFirstPost()) {
				if($title) {
					$thread->Title = $title;
				}
			}
		}


		// Check permissions
		$messageSet = array(
			'default' => _t('Forum.LOGINTOPOST','You\'ll need to login before you can post to that forum. Please do so below.'),
			'alreadyLoggedIn' => _t('Forum.NOPOSTPERMISSION','I\'m sorry, but you do not have permission post to this forum.'),
			'logInAgain' => _t('Forum.LOGINTOPOSTAGAIN','You have been logged out of the forums.  If you would like to log in again to post, enter a username and password below.'),
		);

		// Creating new thread
		if (!$thread && !$this->canPost()) {
 			Security::permissionFailure($this, $messageSet);
			return false;
		}

		// Replying to existing thread
		if ($thread && !$post && !$thread->canPost()) {
 			Security::permissionFailure($this, $messageSet);
			return false;
		}

		// Editing existing post
		if ($thread && $post && !$post->canEdit()) {
 			Security::permissionFailure($this, $messageSet);
			return false;
		}

		if(!$thread) {
			$thread = new ForumThread();
			$thread->ForumID = $this->ID;
			if($title) $thread->Title = $title;
			$starting_thread = true;
		}

		// Upload and Save all files attached to the field
		// Attachment will always be blank, If they had an image it will be at least in Attachment-0
		//$attachments = new DataObjectSet();
		$attachments = new ArrayList();

		if(!empty($data['Attachment-0']) && !empty($data['Attachment-0']['tmp_name'])) {
			$id = 0;
			//
			// @todo this only supports ajax uploads. Needs to change the key (to simply Attachment).
			//
			while(isset($data['Attachment-' . $id])) {
				$image = $data['Attachment-' . $id];

				if($image && !empty($image['tmp_name'])) {
					$file = Post_Attachment::create();
					$file->OwnerID = Member::currentUserID();
					$folder = Config::inst()->get('ForumHolder','attachments_folder');

					try {
						$upload = Upload::create()->loadIntoFile($image, $file, $folder);
						$file->write();
						$attachments->push($file);
					}
					catch(ValidationException $e) {
						$message = _t('Forum.UPLOADVALIDATIONFAIL', 'Unallowed file uploaded. Please only upload files of the following: ');
						$message .= implode(', ', Config::inst()->get('File', 'allowed_extensions'));
						$form->addErrorMessage('Attachment', $message, 'bad');

						Session::set("FormInfo.Form_PostMessageForm.data", $data);

						return $this->redirectBack();
					}
				}

				$id++;
			}
		}

		// from now on the user has the correct permissions. save the current thread settings
		$thread->write();

		if(!$post || !$post->canEdit()) {
			$post = new Post();
			$post->AuthorID = ($member) ? $member->ID : 0;
			$post->ThreadID = $thread->ID;
		}

		$post->ForumID = $thread->ForumID;
		$post->Content = $content;
		$post->write();


		if($attachments) {
			foreach($attachments as $attachment) {
				$attachment->PostID = $post->ID;
				$attachment->write();
			}
		}

		// Add a topic subscription entry if required
		$isSubscribed = ForumThread_Subscription::already_subscribed($thread->ID);
		if(isset($data['TopicSubscription'])) {
			if(!$isSubscribed) {
				// Create a new topic subscription for this member
				$obj = new ForumThread_Subscription();
				$obj->ThreadID = $thread->ID;
				$obj->MemberID = Member::currentUserID();
				$obj->write();
			}
		} elseif($isSubscribed) {
			// See if the member wanted to remove themselves
			DB::query("DELETE FROM \"ForumThread_Subscription\" WHERE \"ThreadID\" = '$post->ThreadID' AND \"MemberID\" = '$member->ID'");
		}

		// Send any notifications that need to be sent
		ForumThread_Subscription::notify($post);

		// Send any notifications to moderators of the forum
		if (Forum::$notify_moderators) {
			if(isset($starting_thread) && $starting_thread) $this->notifyModerators($post, $thread, true);
			else $this->notifyModerators($post, $thread);
		}

		return $this->redirect($post->Link());
	}

	/**
	 * Send email to moderators notifying them the thread has been created or post added/edited.
	 */
	function notifyModerators($post, $thread, $starting_thread = false) {
		$moderators = $this->Moderators();
		if($moderators && $moderators->exists()) {
			foreach($moderators as $moderator){
				if($moderator->Email){
					$adminEmail = Config::inst()->get('Email', 'admin_email');

					$email = new Email();
					$email->setFrom($adminEmail);
					$email->setTo($moderator->Email);
					if($starting_thread){
						$email->setSubject('New thread "' . $thread->Title . '" in forum ['. $this->Title.']');
					}else{
						$email->setSubject('New post "' . $post->Title. '" in forum ['.$this->Title.']');
					}
					$email->setTemplate('ForumMember_NotifyModerator');
					$email->populateTemplate(new ArrayData(array(
						'NewThread' => $starting_thread,
						'Moderator' => $moderator,
						'Author' => $post->Author(),
						'Forum' => $this,
						'Post' => $post
					)));

					$email->send();
				}
			}
		}
	}

	/**
	 * Return the Forbidden Words in this Forum
	 *
	 * @return Text
	 */
	function getForbiddenWords() {
		return $this->Parent()->ForbiddenWords;
	}

	/**
	* This function filters $content by forbidden words, entered in forum holder.
	*
	* @param String $content (it can be Post Content or Post Title)
	* @return String $content (filtered string)
	*/
	function filterLanguage($content) {
		$words = $this->getForbiddenWords();
		if($words != ""){
			$words = explode(",",$words);
			foreach($words as $word){
				$content = str_ireplace(trim($word),"*",$content);
			}
		}

		return $content;
	}

	/**
	 * Get the link for the reply action
	 *
	 * @return string URL for the reply action
	 */
	function ReplyLink() {
		return $this->Link() . 'reply/' . $this->urlParams['ID'];
	}

	/**
	 * Show will get the selected thread to the user. Also increments the forums view count.
	 *
	 * If the thread does not exist it will pass the user to the 404 error page
	 *
	 * @return array|SS_HTTPResponse_Exception
	 */
 	function show() {
		$title = Convert::raw2xml($this->Title);

		if($thread = $this->getForumThread()) {

			//If there is not first post either the thread has been removed or thread if a banned spammer.
			if(!$thread->getFirstPost()){
				// don't hide the post for logged in admins or moderators
				$member = Member::currentUser();
				if(!$this->canModerate($member)) {
					return $this->httpError(404);
				}
			}

			$thread->incNumViews();

			$posts = sprintf(_t('Forum.POSTTOTOPIC',"Posts to the %s topic"), $thread->Title);

			RSSFeed::linkToFeed($this->Link("rss") . '/thread/' . (int) $this->urlParams['ID'], $posts);

			$title = Convert::raw2xml($thread->Title) . ' &raquo; ' . $title;
			$field = DBField::create_field('HTMLText', $title);

			return array(
				'Thread' => $thread,
				'Title' => $field
			);
		}
		else {
			// if redirecting post ids to thread id is enabled then we need
			// to check to see if this matches a post and if it does redirect
			if(Forum::$redirect_post_urls_to_thread && isset($this->urlParams['ID']) && is_numeric($this->urlParams['ID'])) {
				if($post = Post::get()->byID($this->urlParams['ID'])) {
					return $this->redirect($post->Link(), 301);
				}
			}
		}

		return $this->httpError(404);
	}

	/**
	 * Start topic action
	 *
	 * @return array Returns an array to render the start topic page
	 */
	function starttopic() {
		$topic = array(
			'Subtitle' => DBField::create_field('HTMLText', _t('Forum.NEWTOPIC','Start a new topic')),
			'Abstract' => DBField::create_field('HTMLText', DataObject::get_one("ForumHolder")->ForumAbstract)
		);
		return $topic;
	}

	/**
	 * Get the forum title
	 *
	 * @return string Returns the forum title
	 */
	function getHolderSubtitle() {
		return $this->dbObject('Title');
	}

	/**
	 * Get the currently viewed forum. Ensure that the user can access it
	 *
	 * @return ForumThread
	 */
	function getForumThread() {
		if(isset($this->urlParams['ID'])) {
			$SQL_id = Convert::raw2sql($this->urlParams['ID']);

			if(is_numeric($SQL_id)) {
				if($thread = DataObject::get_by_id('ForumThread', $SQL_id)) {
					if(!$thread->canView()) {
						Security::permissionFailure($this);

						return false;
					}

					return $thread;
				}
			}
		}

		return false;
	}

	/**
	 * Delete an Attachment
	 * Called from the EditPost method. Its Done via Ajax
	 *
	 * @return boolean
	 */
	function deleteattachment(SS_HTTPRequest $request) {
		// Check CSRF token
		if (!SecurityToken::inst()->checkRequest($request)) {
			return $this->httpError(400);
		}

		// check we were passed an id and member is logged in
		if(!isset($this->urlParams['ID'])) return false;

		$file = DataObject::get_by_id("Post_Attachment", (int) $this->urlParams['ID']);

		if($file && $file->canDelete()) {
			$file->delete();

			return (!Director::is_ajax()) ? $this->redirectBack() : true;
		}

		return false;
	}

	/**
	 * Edit post action
	 *
	 * @return array Returns an array to render the edit post page
	 */
	function editpost() {
		return array(
			'Subtitle' => _t('Forum.EDITPOST','Edit post')
		);
	}

	/**
	 * Get the post edit form if the user has the necessary permissions
	 *
	 * @return Form
	 */
	function EditForm() {
		$id = (isset($this->urlParams['ID'])) ? $this->urlParams['ID'] : null;
		$post = DataObject::get_by_id('Post', $id);

		return $this->PostMessageForm(false, $post);
	}


	/**
	 * Delete a post via the url.
	 *
	 * @return bool
	 */
	function deletepost(SS_HTTPRequest $request) {
		// Check CSRF token
		if (!SecurityToken::inst()->checkRequest($request)) {
			return $this->httpError(400);
		}

		if(isset($this->urlParams['ID'])) {
			if($post = DataObject::get_by_id('Post', (int) $this->urlParams['ID'])) {
				if($post->canDelete()) {
					// delete the whole thread if this is the first one. The delete action
					// on thread takes care of the posts.
					if($post->isFirstPost()) {
						$thread = DataObject::get_by_id("ForumThread", $post->ThreadID);
						$thread->delete();
					}
					else {
						// delete the post
						$post->delete();
					}
				}
			}
	  	}

		return (Director::is_ajax()) ? true : $this->redirect($this->Link());
	}

	/**
	 * Returns the Forum Message from Session. This
	 * is used for things like Moving thread messages
	 * @return String
	 */
	function ForumAdminMsg() {
		$message = Session::get('ForumAdminMsg');
		Session::clear('ForumAdminMsg');

		return $message;
	}


	/**
	 * Forum Admin Features form.
	 * Handles the dropdown to select the new forum category and the checkbox for stickyness
	 *
	 * @return Form
	 */
	function AdminFormFeatures() {
		if (!$this->canModerate()) return;

		$id = (isset($this->urlParams['ID'])) ? $this->urlParams['ID'] : false;

		$fields = new FieldList(
			new CheckboxField('IsSticky', _t('Forum.ISSTICKYTHREAD','Is this a Sticky Thread?')),
			new CheckboxField('IsGlobalSticky', _t('Forum.ISGLOBALSTICKY','Is this a Global Sticky (shown on all forums)')),
			new CheckboxField('IsReadOnly', _t('Forum.ISREADONLYTHREAD','Is this a Read only Thread?')),
			new HiddenField("ID", "Thread")
		);

		if(($forums = Forum::get()) && $forums->exists()) {
			$fields->push(new DropdownField("ForumID", _t('Forum.CHANGETHREADFORUM',"Change Thread Forum"), $forums->map('ID', 'Title', 'Select New Category:')), '', null, 'Select New Location:');
		}

		$actions = new FieldList(
			new FormAction('doAdminFormFeatures', _t('Forum.SAVE', 'Save'))
		);

		$form = new Form($this, 'AdminFormFeatures', $fields, $actions);

		// need this id wrapper since the form method is called on save as
		// well and needs to return a valid form object
		if($id) {
			$thread = ForumThread::get()->byID($id);
			$form->loadDataFrom($thread);
		}
		
		$this->extend('updateAdminFormFeatures', $form);
		
		return $form;
	}

	/**
	 * Process's the moving of a given topic. Has to check for admin privledges,
	 * passed an old topic id (post id in URL) and a new topic id
	 */
	function doAdminFormFeatures($data, $form) {
		if(isset($data['ID'])) {
			$thread = ForumThread::get()->byID($data['ID']);

			if($thread) {
				if (!$thread->canModerate()) {
					return Security::permissionFailure($this);
				}

				$form->saveInto($thread);
				$thread->write();
			}
		}

		return $this->redirect($this->Link());
	}
}

/**
 * This is a DataQuery that allows us to replace the underlying query. Hopefully this will
 * be a native ability in 3.1, but for now we need to.
 * TODO: Remove once API in core
 */
class Forum_DataQuery extends DataQuery {
	function __construct($dataClass, $query) {
		parent::__construct($dataClass);
		$this->query = $query;
	}
}
