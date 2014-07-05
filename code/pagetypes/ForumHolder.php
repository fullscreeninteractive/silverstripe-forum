<?php

/**
 * ForumHolder represents the top forum overview page. Its children
 * should be Forums. On this page you can also edit your global settings
 * for the entire forum.
 * 
 * @package forum
 */

class ForumHolder extends Page {

	private static $db = array(
		"HolderSubtitle" => "Varchar(200)",
		"ProfileSubtitle" => "Varchar(200)",
		"ForumSubtitle" => "Varchar(200)",
		"HolderAbstract" => "HTMLText", 
		"ProfileAbstract" => "HTMLText", 
		"ForumAbstract" => "HTMLText", 
		"ProfileModify" => "HTMLText", 
		"ProfileAdd" => "HTMLText",
		"DisplaySignatures" => "Boolean",
		"ShowInCategories" => "Boolean",
		"AllowGravatars" => "Boolean",
		"GravatarType" => "Varchar(10)",
		"ForbiddenWords" => "Text",
		"CanPostType" => "Enum('Anyone, LoggedInUsers, OnlyTheseUsers, NoOne', 'LoggedInUsers')",
	);
	
	private static $has_one = array();

	private static $has_many = array(
		"Categories" => "ForumCategory"
	);

	private static $allowed_children = array('Forum');
	
	private static $defaults = array(
		"HolderSubtitle" => "Welcome to our forum!",
		"ProfileSubtitle" => "Edit Your Profile",
		"ForumSubtitle" => "Start a new topic",
		"HolderAbstract" => "<p>If this is your first visit, you will need to <a class=\"broken\" title=\"Click here to register\" href=\"ForumMemberProfile/register\">register</a> before you can post. However, you can browse all messages below.</p>",
		"ProfileAbstract" => "<p>Please fill out the fields below. You can choose whether some are publically visible by using the checkbox for each one.</p>",
		"ForumAbstract" => "<p>From here you can start a new topic.</p>",
		"ProfileModify" => "<p>Thanks, your member profile has been modified.</p>",
		"ProfileAdd" => "<p>Thanks, you are now signed up to the forum.</p>",
	);
	
	/**
	 * If the user has spam protection enabled and setup then we can provide spam
	 * prevention for the forum. This stores whether we actually want the registration
	 * form to have such protection
	 * 
	 * @var bool
	 */
	public static $use_spamprotection_on_register = true;
	
	/**
	 * If the user has spam protection enabled and setup then we can provide spam
	 * prevention for the forum. This stores whether we actually want the posting 
	 * form (adding, replying) to have such protection
	 * 
	 * @var bool
	 */
	public static $use_spamprotection_on_posts = false;

	/**
	 * Add a hidden field to the form which should remain empty
	 * If its filled out, we can assume that a spam bot is auto-filling fields.
	 * 
	 * @var bool
	 */
	public static $use_honeypot_on_register = false;
	
	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldsToTab("Root.Messages", array(
			TextField::create("HolderSubtitle","Forum Holder Subtitle"),
			HTMLEditorField::create("HolderAbstract","Forum Holder Abstract"),
			TextField::create("ProfileSubtitle","Member Profile Subtitle"),
			HTMLEditorField::create("ProfileAbstract","Member Profile Abstract"),
			TextField::create("ForumSubtitle","Create topic Subtitle"),
			HTMLEditorField::create("ForumAbstract","Create topic Abstract"),
			HTMLEditorField::create("ProfileModify","Create message after modifing forum member"),
			HTMLEditorField::create("ProfileAdd","Create message after adding forum member")
		));
		$fields->addFieldsToTab("Root.Settings", array(
			CheckboxField::create("DisplaySignatures", "Display Member Signatures?"),
			CheckboxField::create("ShowInCategories", "Show Forums In Categories?"),
			CheckboxField::create("AllowGravatars", "Allow <a href='http://www.gravatar.com/' target='_blank'>Gravatars</a>?"),
			DropdownField::create("GravatarType", "Gravatar Type", array(
 		  		"standard" => _t('Forum.STANDARD','Standard'),
 		  		"identicon" => _t('Forum.IDENTICON','Identicon'),
		  		"wavatar" => _t('Forum.WAVATAR', 'Wavatar'),
				"monsterid" => _t('Forum.MONSTERID', 'Monsterid'),
				"retro" => _t('Forum.RETRO', 'Retro'),
 				"mm" => _t('Forum.MM', 'Mystery Man'),
 			))->setEmptyString('Use Forum Default')
		));

		// add a grid field to the category tab with all the categories
		$categoryConfig = GridFieldConfig::create()
			->addComponents(
				new GridFieldSortableHeader(),
				new GridFieldButtonRow(),
				new GridFieldDataColumns(),
				new GridFieldEditButton(),
				new GridFieldViewButton(),
				new GridFieldDeleteAction(),
				new GridFieldAddNewButton('buttons-before-left'),
				new GridFieldPaginator(),
				new GridFieldDetailForm()
			);

		$categories = GridField::create(
			'Category',
			_t('Forum.FORUMCATEGORY', 'Forum Category'),
			$this->Categories(),
			$categoryConfig
		);

		$fields->addFieldsToTab("Root.Categories", $categories);


		$fields->addFieldsToTab("Root.LanguageFilter", array(
			TextField::create("ForbiddenWords", "Forbidden words (comma separated)"),
			LiteralField::create("FWLabel","These words will be replaced by an asterisk")
		));
		
		$fields->addFieldToTab("Root.Access", HeaderField::create(_t('Forum.ACCESSPOST','Who can post to the forum?'), 2));
		$fields->addFieldToTab("Root.Access", OptionsetField::create("CanPostType", "", array(
		  	"Anyone" => _t('Forum.READANYONE', 'Anyone'),
		  	"LoggedInUsers" => _t('Forum.READLOGGEDIN', 'Logged-in users'),
			"NoOne" => _t('Forum.READNOONE', 'Nobody. Make Forum Read Only')
		)));

		return $fields;
	}

	function canPost($member = null) {
		if(!$member) $member = Member::currentUser();
		
		if($this->CanPostType == "NoOne") return false;
		
		if($this->CanPostType == "Anyone" || $this->canEdit($member)) return true;
		
		if($member) {
			if($member->IsSuspended()) return false;
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
	 * Ensure that any categories that exist with no forum holder are updated to be owned by the first forum holder
	 * if there is one. This is required now that multiple forum holds are allowed, and categories belong to holders.
	 *
	 * @see framework/core/model/DataObject#requireDefaultRecords()
	 */
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();

		$forumCategories = ForumCategory::get()->filter('ForumHolderID', 0);
		if(!$forumCategories->exists()) return;

		$forumHolder = ForumHolder::get()->first();
		if(!$forumHolder) return;

		foreach($forumCategories as $forumCategory) {
			$forumCategory->ForumHolderID = $forumHolder->ID;
			$forumCategory->write();
		}
	}
	
	/**
	 * If we're on the search action, we need to at least show
	 * a breadcrumb to get back to the ForumHolder page.
	 * @return string
	 */
	public function Breadcrumbs($maxDepth = 20, $unlinked = false, $stopAtPageType = false, $showHidden = false) {
		if(isset($this->urlParams['Action'])) {
			switch($this->urlParams['Action']) {
				case 'search':
					return '<a href="' . $this->Link() . '">' . $this->Title . '</a> &raquo; ' . _t('SEARCHBREADCRUMB', 'Search');
				case 'memberlist':
					return '<a href="' . $this->Link() . '">' . $this->Title . '</a> &raquo; ' . _t('MEMBERLIST', 'Member List');				
				case 'popularthreads':
					return '<a href="' . $this->Link() . '">' . $this->Title . '</a> &raquo; ' . _t('MOSTPOPULARTHREADS', 'Most popular threads');
			}
		}
	}
	
	
	/**
	 * Get the number of total posts
	 *
	 * @return int Returns the number of posts
	 */
	function getNumPosts() {
		return DB::query("
			SELECT COUNT(\"Post\".\"ID\") 
			FROM \"Post\" 
			JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"Post\".\"ForumID\" = \"ForumPage\".\"ID\"
			WHERE \"ForumPage\".\"ParentID\" = '" . $this->ID . "'")->value(); 
	}


	/**
	 * Get the number of total topics (threads)
	 *
	 * @return int Returns the number of topics (threads)
	 */
	function getNumTopics() {
		return DB::query("
			SELECT COUNT(\"ForumThread\".\"ID\") 
			FROM \"ForumThread\" 
			JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"ForumThread\".\"ForumID\" = \"ForumPage\".\"ID\"
			WHERE \"ForumPage\".\"ParentID\" = '" . $this->ID . "'")->value(); 
	}


	/**
	 * Get the number of distinct authors
	 *
	 * @return int Returns the number of distinct authors
	 */
	function getNumAuthors() {
		return DB::query("
			SELECT COUNT(DISTINCT \"Post\".\"AuthorID\") 
			FROM \"Post\" 
			JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"Post\".\"ForumID\"=\"ForumPage\".\"ID\"
			AND \"ForumPage\".\"ParentID\" = '" . $this->ID . "'")->value(); 		
	}
	
	/**
	 * Get a list of currently online users (last 15 minutes)
	 * that belong to the "forum-members" code {@link Group}.
	 * 
	 * @return DataList of {@link Member} objects
	 */
	function CurrentlyOnline() {
		$groupIDs = array();

		if($forumGroup = Group::get()->filter('Code', 'forum-members')->first()) {
			$groupIDs[] = $forumGroup->ID;
		}

		if($adminGroup = Group::get()->filter('Code', array('administrators', 'Administrators'))->first()) {
			$groupIDs[] = $adminGroup->ID;
		}

		return Member::get()
			->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
			->filter('GroupID', $groupIDs)
			->where('"Member"."LastViewed" > ' . DB::getConn()->datetimeIntervalClause('NOW', '-15 MINUTE'))
			->sort('"Member"."FirstName", "Member"."Surname"');
	}
	
	/**
	 * @deprecated 0.5
	 */
	function LatestMember($limit = 1) {
		user_error('Please use LatestMembers($limit) instead of LatestMember', E_USER_NOTICE);
		
		return $this->LatestMembers($limit);
	}
	
	/**
	 * Get the latest members
	 *
	 * @param int $limit Number of members to return
	 * @return ArrayList
	 */
	function getLatestMembers($limit = 1) {
		$groupIDs = array();
		
		if($forumGroup = Group::get()->filter('Code', 'forum-members')->first()) {
			$groupIDs[] = $forumGroup->ID;
		}
		if($adminGroup = Group::get()->filter('Code', array('administrators','Administrators'))->first()) {		
			$groupIDs[] = $adminGroup->ID;
		}
		
		$latestMembers = Member::get()
			->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
			->filter('GroupID', $groupIDs)
			->sort('"Member"."ID" DESC')
			->limit($limit);

		return $latestMembers;
	}

	/**
	 * Get a list of Forum Categories
	 * @return DataList
	 */
	function getShowInCategories() {
	 	$forumCategories = ForumCategory::get()->filter('ForumHolderID', $this->ID);
		$showInCategories = $this->getField('ShowInCategories');
		return $forumCategories->exists() && $showInCategories;
	}

	/**
	 * Get the forums. Actually its a bit more complex than that
	 * we need to group by the Forum Categories.
	 *
	 * @return ArrayList
	 */
	function Forums() {
		$categoryText = isset($_REQUEST['Category']) ? Convert::raw2xml($_REQUEST['Category']) : null;
		$holder = $this;

		if($this->getShowInCategories()) {
			return ForumCategory::get()
				->filter('ForumHolderID', $this->ID)
				->filterByCallback(function($category) use ($categoryText, $holder) {
					// Don't include if we've specified a Category, and it doesn't match this one
					if ($categoryText !== null && $category->Title != $categoryText) return false;

					// Get a list of forums that live under this holder & category
					$category->CategoryForums = Forum::get()
						->filter(array(
							'CategoryID' => $category->ID,
							'ParentID' => $holder->ID,
							'ShowInMenus' => 1
						))
						->filterByCallback(function($forum){
							return $forum->canView();
						});

					return $category->CategoryForums->exists();
				});
		}
		else {
			return Forum::get()
				->filter(array(
					'ParentID' => $this->ID,
					'ShowInMenus' => 1
				))
				->filterByCallback(function($forum){
					return $forum->canView();
				});
		}
	}

	/**
	 * A function that returns the correct base table to use for custom forum queries. It uses the getVar stage to determine
	 * what stage we are looking at, and determines whether to use SiteTree or SiteTree_Live (the general case). If the stage is
	 * not specified, live is assumed (general case). It is a static function so it can be used for both ForumHolder and Forum.
	 *
	 * @return String
	 */
	static function baseForumTable() {
		$stage = (Controller::curr()->getRequest()) ? Controller::curr()->getRequest()->getVar('stage') : false;
		if (!$stage) $stage = Versioned::get_live_stage();
		
		if((class_exists('SapphireTest', false) && SapphireTest::is_running_test()) || $stage == "Stage") {
			return "SiteTree";
		} else {
			return "SiteTree_Live";
		}
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
		if(class_exists('Authenticator') == false)
			return false;

		return Authenticator::is_registered("OpenIDAuthenticator");
	}
	
	
	/**
	 * Get the latest posts
	 *
	 * @param int $limit Number of posts to return
	 * @param int $forumID - Forum ID to limit it to
	 * @param int $threadID - Thread ID to limit it to
	 * @param int $lastVisit Optional: Unix timestamp of the last visit (GMT)
	 * @param int $lastPostID Optional: ID of the last read post
	 */
	function getRecentPosts($limit = 50, $forumID = null, $threadID = null, $lastVisit = null, $lastPostID = null) {
		$filter = array();
		
		if($lastVisit) $lastVisit = @date('Y-m-d H:i:s', $lastVisit);

		$lastPostID = (int) $lastPostID;
		
		// last post viewed
		if($lastPostID > 0) $filter[] = "\"Post\".\"ID\" > '". Convert::raw2sql($lastPostID) ."'";
		
		// last time visited
		if($lastVisit) $filter[] = "\"Post\".\"Created\" > '". Convert::raw2sql($lastVisit) ."'";

		// limit to a forum
		if($forumID) $filter[] = "\"Post\".\"ForumID\" = '". Convert::raw2sql($forumID) ."'";

		// limit to a thread
		if($threadID) $filter[] = "\"Post\".\"ThreadID\" = '". Convert::raw2sql($threadID) ."'";

		// limit to just this forum install
		$filter[] = "\"ForumPage\".\"ParentID\"='{$this->ID}'";

		$posts = Post::get()
			->leftJoin('ForumThread', '"Post"."ThreadID" = "ForumThread"."ID"')
			->leftJoin(ForumHolder::baseForumTable(), '"ForumPage"."ID" = "Post"."ForumID"', 'ForumPage')
			->limit($limit)
			->sort('"Post"."ID"', 'DESC')
			->where($filter);

		$recentPosts = new ArrayList();
		foreach ($posts as $post) {
			$recentPosts->push($post);
		}
		if ($recentPosts->count() > 0 ) {
			return $recentPosts;
		}
		return null;
	}


	/**
	 * Are new posts available?
	 *
	 * @param int $id
	 * @param array $data Optional: If an array is passed, the timestamp of
	 *                    the last created post and it's ID will be stored in
	 *                    it (keys: 'last_id', 'last_created')
	 * @param int $lastVisit Unix timestamp of the last visit (GMT)
	 * @param int $lastPostID ID of the last read post
	 * @param int $thread ID of the relevant topic (set to NULL for all
	 *                     topics)
	 * @return bool Returns TRUE if there are new posts available, otherwise
	 *              FALSE.
	 */
	public static function new_posts_available($id, &$data = array(), $lastVisit = null, $lastPostID = null, $forumID = null, $threadID = null) {
		$filter = array();
		
		// last post viewed
		$filter[] = "\"ForumPage\".\"ParentID\" = '". Convert::raw2sql($id) ."'";  
		if($lastPostID) $filter[] = "\"Post\".\"ID\" > '". Convert::raw2sql($lastPostID) ."'";
		if($lastVisit) $filter[] = "\"Post\".\"Created\" > '". Convert::raw2sql($lastVisit) ."'"; 
		if($forumID) $filter[] = "\"Post\".\"ForumID\" = '". Convert::raw2sql($forumID) ."'";
		if($threadID) $filter[] = "\"ThreadID\" = '". Convert::raw2sql($threadID) ."'";
		
		$filter = implode(" AND ", $filter);
		
		$version = DB::query("
			SELECT MAX(\"Post\".\"ID\") AS \"LastID\", MAX(\"Post\".\"Created\") AS \"LastCreated\" 
			FROM \"Post\"
			JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"Post\".\"ForumID\"=\"ForumPage\".\"ID\"
			WHERE $filter" )->first();  
		
		if($version == false) return false;

		if($data) {
			$data['last_id'] = (int)$version['LastID'];
			$data['last_created'] = strtotime($version['LastCreated']);
		}
	
		$lastVisit = (int) $lastVisit;
		
		if($lastVisit <= 0) $lastVisit = false;

		$lastPostID = (int)$lastPostID;
		if($lastPostID <= 0) $lastPostID = false;

		if(!$lastVisit && !$lastPostID) return true; 
		if($lastVisit && (strtotime($version['LastCreated']) > $lastVisit)) return true;

		if($lastPostID && ((int)$version['LastID'] > $lastPostID)) return true;

		return false;
	}
	
	/**
	 * Helper Method from the template includes. Uses $ForumHolder so in order for it work 
	 * it needs to be included on this page
	 *
	 * @return ForumHolder
	 */
	function getForumHolder() {
		return $this;
	}
}


class ForumHolder_Controller extends Page_Controller {

	private static $allowed_actions = array(
		'popularthreads',
		'login',
		'logout',
		'search',
		'rss',
	);

	public function init() {
		parent::init();

		Requirements::javascript(THIRDPARTY_DIR . "/jquery/jquery.js");
		Requirements::javascript("forum/javascript/jquery.MultiFile.js");
		Requirements::javascript("forum/javascript/forum.js");

		Requirements::themedCSS('Forum','forum','all');

		RSSFeed::linkToFeed($this->Link("rss"), _t('ForumHolder.POSTSTOALLFORUMS', "Posts to all forums"));

		// Set the back url
		if(isset($_SERVER['REQUEST_URI'])) {
			Session::set('BackURL', $_SERVER['REQUEST_URI']);
		}
		else {
			Session::set('BackURL', $this->Link());
		}
	}
	
	/** 
	 * Generate a complete list of all the members data. Return a 
	 * set of all these members sorted by a GET variable
	 *  
	 * @todo Sort via AJAX
	 * @return DataObjectSet A DataObjectSet of all the members which are signed up
	 */
	function memberlist() {
		return $this->httpError(404);

		$forumGroupID = (int) Group::get()->filter("Code","forum-members")->first()->ID;
		
		// If sort has been defined then save it as in the session
		$order = (isset($_GET['order'])) ? $_GET['order']: "";
		
		if(!isset($_GET['start']) || !is_numeric($_GET['start']) || (int) $_GET['start'] < 1) {
			$_GET['start'] = 0;
		}
		
		$SQL_start = (int) $_GET['start'];

		switch($order) {
			case "joined":
				$members = Member::get()
						->filter('Member.GroupID', $forumGroupID)
						->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
						->sort('"Member"."Created" ASC')
						->limit($SQL_start . ',100');
			break;
			case "name":
				$members = Member::get()
						->filter('Member.GroupID', $forumGroupID)
						->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
						->sort('"Member"."Nickname" ASC')
						->limit($SQL_start . ',100');
			break;
			case "country":
				$members = Member::get()
						->filter(array('Member.GroupID' => $forumGroupID, 'Member.CountryPublic' => TRUE))
						->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
						->sort('"Member"."Nickname" ASC')
						->limit($SQL_start . ',100');
			break;
			case "posts": 
				$query = singleton('Member')->extendedSQL('', "\"NumPosts\" DESC", "{$SQL_start},100");
				$query->select[] = "(SELECT COUNT(*) FROM \"Post\" WHERE \"Post\".\"AuthorID\" = \"Member\".\"ID\") AS \"NumPosts\"";
				$records = $query->execute();
				$members = singleton('Member')->buildDataObjectSet($records, 'DataObjectSet', $query, 'Member');
				$members->parseQueryLimit($query);
			break;
			default:
				$members = Member::get()
						->filter('Member.GroupID', $forumGroupID)
						->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
						->sort('"Member"."Created" DESC')
						->limit($SQL_start . ',100');
			break;
		}
		
		return array(
			'Subtitle' => _t('ForumHolder.MEMBERLIST', 'Forum member List'),
			'Abstract' => $this->MemberListAbstract,
			'Members' => $members,
			'Title' => _t('ForumHolder.MEMBERLIST', 'Forum member List')
		);
	}
	
	/**
	 * Show the 20 most popular threads across all {@link Forum} children.
	 * 
	 * Two configuration options are available:
	 * 1. "posts" - most popular threads by posts
	 * 2. "views" - most popular threads by views
	 * 
	 * e.g. mysite.com/forums/popularthreads?by=posts
	 *
	 * @return array
	 */
	function popularthreads() {
		$start = isset($_GET['start']) ? (int) $_GET['start'] : 0;
		$limit = 20;
		$method = isset($_GET['by']) ? $_GET['by'] : null;
		if(!$method) $method = 'posts';
		
		if($method == 'posts') {
			$threadsQuery = singleton('ForumThread')->buildSQL(
				"\"SiteTree\".\"ParentID\" = '" . $this->ID ."'",
				"\"PostCount\" DESC",
				"$start,$limit",
				"LEFT JOIN \"Post\" ON \"Post\".\"ThreadID\" = \"ForumThread\".\"ID\" LEFT JOIN \"SiteTree\" ON \"SiteTree\".\"ID\" = \"ForumThread\".\"ForumID\""
			);
			$threadsQuery->select[] = "COUNT(\"Post\".\"ID\") AS 'PostCount'";
			$threadsQuery->groupby[] = "\"ForumThread\".\"ID\"";
			$threads = singleton('ForumThread')->buildDataObjectSet($threadsQuery->execute());
			if($threads) $threads->setPageLimits($start, $limit, $threadsQuery->unlimitedRowCount());
			
		} elseif($method == 'views') {
			$threads = ForumThread::get()->sort("NumViews DESC")->limit($limit, $start);
		}
		
		return array(
			'Title' => _t('ForumHolder.POPULARTHREADS', 'Most popular forum threads'),
			'Subtitle' => _t('ForumHolder.POPULARTHREADS', 'Most popular forum threads'),
			'Method' => $method,
			'Threads' => $threads
		);
	}

	/**
	 * The login action
	 *
	 * It simple sets the return URL and forwards to the standard login form.
	 */
	function login() {
		Session::set('Security.Message.message',_t('Forum.CREDENTIALS'));
		Session::set('Security.Message.type', 'status');
		Session::set("BackURL", $this->Link());
		
		$this->redirect('Security/login');
	}
	

	function logout() {
		if($member = Member::currentUser()) $member->logOut();
		
		$this->redirect($this->Link());
	}
	
	/**
	 * The search action
	 *
	 * @return array Returns an array to render the search results.
	 */
	function search() {
		$keywords	= (isset($_REQUEST['Search'])) ? Convert::raw2xml($_REQUEST['Search']) : null;
		$order		= (isset($_REQUEST['order'])) ? Convert::raw2xml($_REQUEST['order']) : null;
		$start		= (isset($_REQUEST['start'])) ? (int) $_REQUEST['start'] : 0;

		$abstract = ($keywords) ? "<p>" . sprintf(_t('ForumHolder.SEARCHEDFOR',"You searched for '%s'."),$keywords) . "</p>": null;
		
		// get the results of the query from the current search engine
		$search = ForumSearch::get_search_engine();	
		
		if($search) {
			$engine = new $search();

			$results = $engine->getResults($this->ID, $keywords, $order, $start);
		}
		else {
			$results = false;
		}

		//Paginate the results
		$results = PaginatedList::create(
			$results,
			$this->request->getVars()
		);

		
		// if the user has requested this search as an RSS feed then output the contents as xml
		// rather than passing it to the template
		if(isset($_REQUEST['rss'])) {
			$rss = new RSSFeed($results, $this->Link(), _t('ForumHolder.SEARCHRESULTS','Search results'), "", "Title", "RSSContent", "RSSAuthor");
			
			return $rss->outputToBrowser();	
		}
		
		// attach a link to a RSS feed version of the search results
		$rssLink = $this->Link() ."search/?Search=".urlencode($keywords). "&amp;order=".urlencode($order)."&amp;rss";
		RSSFeed::linkToFeed($rssLink, _t('ForumHolder.SEARCHRESULTS','Search results'));
		
		return array(
			"Subtitle"		=> DBField::create_field('Text', _t('ForumHolder.SEARCHRESULTS','Search results')),
			"Abstract"		=> DBField::create_field('HTMLText', $abstract),
			"Query"			=> DBField::create_field('Text', $_REQUEST['Search']),
			"Order"			=> DBField::create_field('Text', ($order) ? $order : "relevance"),
			"RSSLink"		=> DBField::create_field('HTMLText', $rssLink),
			"SearchResults"	=> $results
		);
	}
	
	/**
	 * Get the RSS feed
	 *
	 * This method will output the RSS feed with the last 50 posts to the
	 * browser.
	 */
	function rss() {
		HTTP::set_cache_age(3600); // cache for one hour
		
		$threadID = null;
		$forumID = null;
		
		// optionally allow filtering of the forum posts by the url in the format
		// rss/thread/$ID or rss/forum/$ID
		if(isset($this->urlParams['ID']) && ($action = $this->urlParams['ID'])) {
			if(isset($this->urlParams['OtherID']) && ($id = $this->urlParams['OtherID'])) {
				switch($action) {
					case 'forum': 
						$forumID = (int) $id;
						break;
					case 'thread':
						$threadID = (int) $id;
				}
			}
			else {
				// fallback is that it is the ID of a forum like it was in
				// previous versions
				$forumID = (int) $action;
			}
		}
		
		$data = array('last_created' => null, 'last_id' => null);

		if(!isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && !isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
			// just to get the version data..
			$available = ForumHolder::new_posts_available($this->ID, $data, null, null, $forumID, $threadID);
			
			// No information provided by the client, just return the last posts
			$rss = new RSSFeed(
				$this->getRecentPosts(50, $forumID, $threadID), 
				$this->Link() . 'rss',
				sprintf(_t('Forum.RSSFORUMPOSTSTO'),$this->Title), 
				"", 
				"Title",
				"RSSContent", 
				"RSSAuthor",
				$data['last_created'], 
				$data['last_id']
			);
			$rss->outputToBrowser();
    	} else {
	
			// Return only new posts, check the request headers!
			$since = null;
			$etag = null;

			if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				// Split the If-Modified-Since (Netscape < v6 gets this wrong)
				$since = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
				// Turn the client request If-Modified-Since into a timestamp
				$since = @strtotime($since[0]);
				if(!$since) $since = null;
			}

			if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && is_numeric($_SERVER['HTTP_IF_NONE_MATCH'])) {
				$etag = (int)$_SERVER['HTTP_IF_NONE_MATCH'];
			}
			if($available = ForumHolder::new_posts_available($this->ID, $data, $since, $etag, $forumID, $threadID)) {
				HTTP::register_modification_timestamp($data['last_created']);
				$rss = new RSSFeed(
					$this->getRecentPosts(50, $forumID, $threadID, $etag),
					$this->Link() . 'rss',
					sprintf(_t('Forum.RSSFORUMPOSTSTO'),$this->Title), 
					"", 
					"Title",
					"RSSContent", 
					"RSSAuthor", 
					$data['last_created'],
					$data['last_id']
				);
				$rss->outputToBrowser();
			} else {
				if($data['last_created'])
					HTTP::register_modification_timestamp($data['last_created']);

				if($data['last_id'])
					HTTP::register_etag($data['last_id']);

				// There are no new posts, just output an "304 Not Modified" message
				HTTP::add_cache_headers();
				header('HTTP/1.1 304 Not Modified');
			}
		}
		exit;
	}
	
	/**
	 * Return the GlobalAnnouncements from the individual forums
	 *
	 * @return DataObjectSet
	 */
	function GlobalAnnouncements() {

		// Get all the forums with global sticky threads
		return ForumThread::get()
			->filter('IsGlobalSticky', 1)
			->innerJoin(ForumHolder::baseForumTable(), '"ForumThread"."ForumID"="ForumPage"."ID"', "ForumPage")
			->where('"ForumPage"."ParentID" = '.$this->ID)
			->filterByCallback(function($thread){
				if ($thread->canView()) {
					$post = Post::get()->filter('ThreadID', $thread->ID)->sort('Post.Created DESC');
					$thread->Post = $post;
					return true;
				}
			});
	}
}
