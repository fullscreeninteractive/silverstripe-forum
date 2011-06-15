<?php

/**
 * ForumHolder represents the top forum overview page. Its children
 * should be Forums. On this page you can also edit your global settings
 * for the entire forum.
 * 
 * @package forum
 */

class ForumHolder extends Page {

	static $db = array(
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
		"ForbiddenWords" => "Text",
		"CanPostType" => "Enum('Anyone, LoggedInUsers, OnlyTheseUsers, NoOne', 'LoggedInUsers')",
	);
	
	static $has_one = array();

	static $has_many = array(
		"Categories" => "ForumCategory"
	);

	static $allowed_children = array('Forum');
	
	static $defaults = array(
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
	
	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldsToTab("Root.Content.Messages", array(
			new TextField("HolderSubtitle","Forum Holder Subtitle"),
			new HTMLEditorField("HolderAbstract","Forum Holder Abstract"),
			new TextField("ProfileSubtitle","Member Profile Subtitle"),
			new HTMLEditorField("ProfileAbstract","Member Profile Abstract"),
			new TextField("ForumSubtitle","Create topic Subtitle"),
			new HTMLEditorField("ForumAbstract","Create topic Abstract"),
			new HTMLEditorField("ProfileModify","Create message after modifing forum member"),
			new HTMLEditorField("ProfileAdd","Create message after adding forum member")
		));
		$fields->addFieldsToTab("Root.Content.Settings", array(
			new CheckboxField("DisplaySignatures", "Display Member Signatures?"),
			new CheckboxField("ShowInCategories", "Show Forums In Categories?"),
			new CheckboxField("AllowGravatars", "Allow <a href='http://www.gravatar.com/' target='_blank'>Gravatars</a>?")
		));
		$fields->addFieldsToTab("Root.Content.LanguageFilter", array(
			new TextField("ForbiddenWords", "Forbidden words (comma separated)"),
			new LiteralField("FWLabel","These words will be replaced by an asterisk")
		));
		
		$fields->addFieldToTab("Root.Access", new HeaderField(_t('Forum.ACCESSPOST','Who can post to the forum?'), 2));
		$fields->addFieldToTab("Root.Access", new OptionsetField("CanPostType", "", array(
		  	"Anyone" => _t('Forum.READANYONE', 'Anyone'),
		  	"LoggedInUsers" => _t('Forum.READLOGGEDIN', 'Logged-in users'),
			"NoOne" => _t('Forum.READNOONE', 'Nobody. Make Forum Read Only')
		)));

		return $fields;
	}

	function canPost() {
		if($this->CanPostType == "Anyone" || $this->canEdit()) return true;
		
		if($this->CanPostType == "NoOne") return false;
		
		if($member = Member::currentUser()) {
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
	 * @see sapphire/core/model/DataObject#requireDefaultRecords()
	 */
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();

		if (!$cats = DataObject::get("ForumCategory", '"ForumCategory"."ForumHolderID" = 0')) return;

		if (!$holder = DataObject::get_one("ForumHolder")) return;

		foreach ($cats as $c) {
			$c->ForumHolderID = $holder->ID;
			$c->write();
		}
	}
	
	/**
	 * If we're on the search action, we need to at least show
	 * a breadcrumb to get back to the ForumHolder page.
	 * @return string
	 */
	function Breadcrumbs() {
		if(isset($this->urlParams['Action'])) {
			switch($this->urlParams['Action']) {
				case 'search':
					return "<a href=\"{$this->Link()}\">{$this->Title}</a> &raquo; " . _t('SEARCHBREADCRUMB', 'Search');
				case 'memberlist':
					return "<a href=\"{$this->Link()}\">{$this->Title}</a> &raquo; " . _t('MEMBERLIST', 'Member List');				
				case 'popularthreads':
					return "<a href=\"{$this->Link()}\">{$this->Title}</a> &raquo; " . _t('MOSTPOPULARTHREADS', 'Most popular threads');
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
			JOIN \"ForumThread\" ON \"Post\".\"ThreadID\" = \"ForumThread\".\"ID\"
			JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"ForumThread\".\"ForumID\" = \"ForumPage\".\"ID\" 
			WHERE \"ForumPage\".\"ParentID\"='{$this->ID}'")->value(); 
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
			WHERE \"ForumPage\".\"ParentID\"='{$this->ID}'")->value(); 
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
			JOIN \"ForumThread\" ON \"Post\".\"ThreadID\" = \"ForumThread\".\"ID\"
			JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"ForumThread\".\"ForumID\"=\"ForumPage\".\"ID\" 
			AND \"ForumPage\".\"ParentID\"='{$this->ID}'")->value();
	}
	
	/**
	 * Get a list of currently online users (last 15 minutes)
	 * that belong to the "forum-members" code {@link Group}.
	 * 
	 * @return DataObjectSet of {@link Member} objects
	 */
	function CurrentlyOnline() {
		$filter = '';
		
		if($forumGroup = DataObject::get_one('Group', "\"Code\" = 'forum-members'")) {
			$filter = "\"GroupID\" = '". $forumGroup->ID ."' ";
		}
		if($adminGroup = DataObject::get_one('Group', "(\"Code\" = 'administrators' OR \"Code\" = 'Administrators')")) {
			$filter .= ($filter) ? "OR \"GroupID\" = '". $adminGroup->ID ."'" : "\"GroupID\" = '". $adminGroup->ID ."'";
		}

		if(method_exists(DB::getConn(), 'datetimeIntervalClause')) {
			$timeconstrain = "\"LastVisited\" > " . DB::getConn()->datetimeIntervalClause('now', '-15 MINUTE');
		} else {
			$timeconstrain = "\"LastVisited\" > NOW() - INTERVAL 15 MINUTE";
		}
		
		return DataObject::get(
			'Member',
			$timeconstrain . " AND ($filter)",
			"\"FirstName\", \"Surname\"",
			"LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\""
		);
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
	 * @return DataObjectSet
	 */
	function getLatestMembers($limit = 1) {
		$filter = '';
		
		if($forumGroup = DataObject::get_one('Group', "\"Code\" = 'forum-members'")) {
			$filter = "\"GroupID\" = '". $forumGroup->ID ."' ";
		}
		if($adminGroup = DataObject::get_one('Group', "(\"Code\" = 'administrators' OR \"Code\" = 'Administrators')")) {
			$filter .= ($filter) ? "OR \"GroupID\" = '". $adminGroup->ID ."'" : "\"GroupID\" = '". $adminGroup->ID ."'";
		}
		
		// do a lookup on the specific Group_Members table for the latest member ID
		if($filter) {
			$limit = (int) $limit;

			$query = new SQLQuery();
			$query->select('"MemberID"')->from('"Group_Members"')->where($filter)->orderby('"ID" DESC')->limit($limit);

			$latestMemberIDs = $query->execute()->column();

			if($latestMemberIDs) {
				$members = new DataObjectSet();
				
				foreach($latestMemberIDs as $key => $id) {
					$members->push(DataObject::get_by_id('Member', $id));
				}
				
				return $members;
			}
		}
		
		return DataObject::get("Member", "", "ID DESC", "", $limit);
	}

	function getShowInCategories() {
	 	$categories = DataObject::get("ForumCategory", "\"ForumHolderID\"={$this->ID}");	
		$showInCategories = $this->getField('ShowInCategories');
		return $categories && $showInCategories;
	}

	/**
	 * Get the forums. Actually its a bit more complex than that
	 * we need to group by the Forum Categories.
	 *
	 * @return DataObjectSet
	 */
	function Forums() {
		if(isset($_REQUEST['Category'])){
			$categoryText = Convert::raw2xml($_REQUEST['Category']);
		}
		if($this->getShowInCategories()) {
	 		$categories = DataObject::get("ForumCategory", "\"ForumHolderID\"={$this->ID}");	
			foreach($categories as $category) {
				if(!isset($_REQUEST['Category']) || $category->Title==$categoryText){
					$category->CategoryForums = new DataObjectSet();
					
					$forums = DataObject::get("Forum", "\"CategoryID\" = '$category->ID' AND \"ParentID\" = '$this->ID' AND \"ShowInMenus\"=1");
					
					if($forums) {
						foreach($forums as $forum) {
							if($forum->canView()) {
								$category->CategoryForums->push($forum);
							}
						}
					}
					
					if($category->CategoryForums->Count() < 1) $categories->remove($category);
				}else{
					$categories->remove($category);
				}
			}
			
			return $categories;
		}
		
		$forums = DataObject::get("Forum", "\"ParentID\" = '$this->ID' AND \"ShowInMenus\"=1");

		if($forums) {
			foreach($forums as $forum) {
				if(!$forum->canView()) $forums->remove($forum);
			}
		}
		
		return $forums;
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
		
		if(
			(class_exists('SapphireTest', false) && SapphireTest::is_running_test())
			|| $stage == "Stage"
		) {
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
		if($forumID) $filter[] = "\"ForumThread\".\"ForumID\" = '". Convert::raw2sql($forumID) ."'";

		// limit to a thread
		if($threadID) $filter[] = "\"Post\".\"ThreadID\" = '". Convert::raw2sql($threadID) ."'";

		// limit to just this forum install
		$filter[] = "\"ForumPage\".\"ParentID\"='{$this->ID}'";
		
		return DataObject::get(
			"Post",
			implode(" AND ", $filter),
			"\"Post\".\"ID\" DESC",
			"LEFT JOIN \"ForumThread\" on \"Post\".\"ThreadID\" = \"ForumThread\".\"ID\" 
			 LEFT JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"ForumThread\".\"ForumID\" = \"ForumPage\".\"ID\"",
			$limit
		);
	}


	/**
	 * Are new posts available?
	 *
	 * @param int $lastVisit Unix timestamp of the last visit (GMT)
	 * @param int $lastPostID ID of the last read post
	 * @param int $thread ID of the relevant topic (set to NULL for all
	 *                     topics)
	 * @param array $data Optional: If an array is passed, the timestamp of
	 *                    the last created post and it's ID will be stored in
	 *                    it (keys: 'last_id', 'last_created')
	 * @return bool Returns TRUE if there are new posts available, otherwise
	 *              FALSE.
	 */
	public function getNewPostsAvailable($lastVisit = null, $lastPostID = null, $forumID = null, $threadID = null, array &$data = null) {
	
		$filter = array();
		
		// last post viewed
		$filter[] = "\"ForumPage\".\"ParentID\" = {$this->ID}";  
		if($lastPostID) $filter[] = "\"Post\".\"ID\" > '". Convert::raw2sql($lastPostID) ."'";
		if($lastVisit) $filter[] = "\"Post\".\"Created\" > '". Convert::raw2sql($lastVisit) ."'"; 
		if($forumID) $filter[] = "\"ForumThread\".\"ForumID\" = '". Convert::raw2sql($forumID) ."'";
		if($threadID) $filter[] = "\"ThreadID\" = '". Convert::raw2sql($threadID) ."'";
		
		$filter = implode(" AND ", $filter);
		
		$version = DB::query("
			SELECT MAX(\"Post\".\"ID\") AS \"LastID\", MAX(\"Post\".\"Created\") AS \"LastCreated\" 
			FROM \"Post\"
			JOIN \"ForumThread\" ON \"Post\".\"ThreadID\" = \"ForumThread\".\"ID\"
			JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"ForumThread\".\"ForumID\"=\"ForumPage\".\"ID\" 
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


	public function init() {
		Requirements::themedCSS('Forum');
		Requirements::javascript(THIRDPARTY_DIR . "/jquery/jquery.js");
		
		Requirements::javascript("forum/javascript/jquery.MultiFile.js");
		Requirements::javascript("forum/javascript/forum.js");
		
		RSSFeed::linkToFeed($this->Link("rss"), _t('ForumHolder.POSTSTOALLFORUMS', "Posts to all forums"));
		
		parent::init();
		
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

		$forumGroupID = (int) DataObject::get_one('Group', "\"Code\" = 'forum-members'")->ID;
		
		// If sort has been defined then save it as in the session
		$order = (isset($_GET['order'])) ? $_GET['order']: "";
		
		if(!isset($_GET['start']) || !is_numeric($_GET['start']) || (int) $_GET['start'] < 1) {
			$_GET['start'] = 0;
		}
		
		$SQL_start = (int) $_GET['start'];

		switch($order) {
			case "joined":
				$members = DataObject::get("Member", "\"GroupID\" = '$forumGroupID'", "\"Member\".\"Created\" ASC", "LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"", "{$SQL_start},100");
			break;
			case "name":
				$members = DataObject::get("Member", "\"GroupID\" = '$forumGroupID'", "\"Member\".\"Nickname\" ASC", "LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"", "{$SQL_start},100");
			break;
			case "country":
				$members = DataObject::get("Member", "\"GroupID\" = '$forumGroupID' AND \"Member\".\"CountryPublic\" = TRUE", "\"Member\".\"Country\" ASC", "LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"", "{$SQL_start},100");
			break;
			case "posts": 
				$query = singleton('Member')->extendedSQL('', "\"NumPosts\" DESC", "{$SQL_start},100");
				$query->select[] = "(SELECT COUNT(*) FROM \"Post\" WHERE \"Post\".\"AuthorID\" = \"Member\".\"ID\") AS \"NumPosts\"";
				$records = $query->execute();
				$members = singleton('Member')->buildDataObjectSet($records, 'DataObjectSet', $query, 'Member');
				$members->parseQueryLimit($query);
			break;
			default:
				$members = DataObject::get("Member", "\"GroupID\" = '$forumGroupID'", "\"Member\".\"Created\" DESC", "LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"", "{$SQL_start},100");
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
	 * Show the 20 most popular threads.
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
		$method = isset($_GET['by']) ? $_GET['by'] : null;
		if(!$method) $method = 'posts';
		
		if($method == 'posts') {
			$threadRecords = DB::query("
				SELECT \"Post\".*, (SELECT COUNT(*) FROM \"Post\" AS \"P\" WHERE \"Post\".\"ID\" = P.\"TopicID\") AS \"PostCount\"
				FROM \"Post\" JOIN \"SiteTree_Live\" \"ForumPage\" ON \"Post\".\"ForumID\" = \"ForumPage\".\"ID\"
				WHERE \"TopicID\" = \"Post\".\"ID\" AND \"ForumPage\".\"ParentID\"='{$this->ID}'
				ORDER BY \"PostCount\" DESC
				LIMIT $start,20
			");
			
			$allThreadsCount = DB::query("
				SELECT COUNT(*) AS \"theCount\"
				FROM \"Post\" JOIN \"" . ForumHolder::baseForumTable() . "\" \"ForumPage\" ON \"Post\".\"ForumID\"=\"ForumPage\".\"ID\"
				WHERE \"TopicID\" = \"Post\".\"ID\" AND \"ForumPage\".\"ParentID\"='{$this->ID}'")->value();
				
			$threads = singleton('Post')->buildDataObjectSet($threadRecords);
			if($threads) $threads->setPageLimits($start, '20', $allThreadsCount);
			
		} elseif($method == 'views') {
			$threads = DataObject::get('ForumThread', '', '"NumViews" DESC', '', "$start,20");
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

		
		// if the user has requested this search as an RSS feed then output the contents as xml
		// rather than passing it to the template
		if(isset($_REQUEST['rss'])) {
			$rss = new RSSFeed($this->SearchResults(), $this->Link(), _t('ForumHolder.SEARCHRESULTS','Search results'), "", "Title", "RSSContent", "RSSAuthor");
			
			return $rss->outputToBrowser();	
		}
		
		// attach a link to a RSS feed version of the search results
		$rssLink = $this->Link() ."search/?Search=".urlencode($keywords). "&amp;order=".urlencode($order)."&amp;rss";
		RSSFeed::linkToFeed($rssLink, _t('ForumHolder.SEARCHRESULTS','Search results'));
		
		return array(
			"Subtitle"		=> DBField::create('Text', _t('ForumHolder.SEARCHRESULTS','Search results')),
			"Abstract"		=> DBField::create('HTMLText', $abstract),
			"Query"			=> DBField::create('Text', $_REQUEST['Search']),
			"Order"			=> DBField::create('Text', ($order) ? $order : "relevance"),
			"RSSLink"		=> DBField::create('HTMLText', $rssLink),
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
			$this->getNewPostsAvailable(null, null, $forumID, $threadID, &$data);
			
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
			if($this->getNewPostsAvailable($since, $etag, $forumID, $threadID, $data)) {
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
		/*return DataObject::get(
			"ForumThread", 
			"\"ForumThread\".\"IsGlobalSticky\" = 1 AND \"ForumPage\".\"ParentID\"={$this->ID}", 
			"MAX(\"PostList\".\"Created\") DESC",	
			"INNER JOIN \"Post\" AS \"PostList\" ON \"PostList\".\"ThreadID\" = \"ForumThread\".\"ID\" 
		  	 INNER JOIN \"" . ForumHolder::baseForumTable() . "\" \"ForumPage\" ON \"ForumThread\".\"ForumID\"=\"ForumPage\".\"ID\"");
		*/
		
		
		
		//Get all the forums with global sticky threads, and then get the most recent post for each of these		
		$threads=DataObject::get(
			'ForumThread',
			"\"ForumThread\".\"IsGlobalSticky\"=1 AND \"ForumPage\".\"ParentID\"={$this->ID}",
			'',
			"INNER JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"ForumThread\".\"ForumID\"=\"ForumPage\".\"ID\""
		);
		
		//Now go and get the most recent post for each of these forum threads
		$trimmed = new DataObjectSet;
		if($threads){
			foreach($threads as $thread){
				if ($thread->canView()) {
					$post = DataObject::get_one('Post', "\"Post\".\"ThreadID\"={$thread->ID}", "\"Created\" DESC");
					$thread->Post = $post;
					$trimmed->Push($thread);
				}
			}
		}
		
		return $trimmed;
	}
}