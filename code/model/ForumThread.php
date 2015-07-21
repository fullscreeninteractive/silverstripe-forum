<?php

/**
 * A representation of a forum thread. A forum thread is 1 topic on the forum
 * which has multiple posts underneath it.
 *
 * @package forum
 */

class ForumThread extends DataObject {

	private static $db = array(
		"Title" => "Varchar(255)",
		"NumViews" => "Int",
		"IsSticky" => "Boolean",
		"IsReadOnly" => "Boolean",
		"IsGlobalSticky" => "Boolean"
	);

	private static $has_one = array(
		'Forum' => 'Forum'
	);

	private static $has_many = array(
		'Posts' => 'Post'
	);

	private static $defaults = array(
		'NumViews' => 0,
		'IsSticky' => false,
		'IsReadOnly' => false,
		'IsGlobalSticky' => false
	);

	private static $indexes = array(
		'IsSticky' => true,
		'IsGlobalSticky' => true
	);

	/**
	 * @var null|boolean Per-request cache, whether we should display signatures on a post.
	 */
	private static $_cache_displaysignatures = null;

	/**
	 * Check if the user can create new threads and add responses
	 */
	function canPost($member = null) {
		if(!$member) $member = Member::currentUser();
		return ($this->Forum()->canPost($member) && !$this->IsReadOnly);
	}

	/**
	 * Check if user can moderate this thread
	 */
	function canModerate($member = null) {
		if(!$member) $member = Member::currentUser();
		return $this->Forum()->canModerate($member);
	}

	/**
	 * Check if user can view the thread
	 */
	function canView($member = null) {
		if(!$member) $member = Member::currentUser();
		return $this->Forum()->canView($member);
	}

	/**
	 * Hook up into moderation.
	 */
	function canEdit($member = null) {
		if(!$member) $member = Member::currentUser();
		return $this->canModerate($member);
	}

	/**
	 * Hook up into moderation - users cannot delete their own posts/threads because
	 * we will loose history this way.
	 */
	function canDelete($member = null) {
		if(!$member) $member = Member::currentUser();
		return $this->canModerate($member);
	}

	/**
	 * Hook up into canPost check
	 */
	function canCreate($member = null) {
		if(!$member) $member = Member::currentUser();
		return $this->canPost($member);
	}

	/**
	 * Are Forum Signatures on Member profiles allowed.
	 * This only needs to be checked once, so we cache the initial value once per-request.
	 *
	 * @return bool
	 */
	function getDisplaySignatures() {
		if(isset(self::$_cache_displaysignatures) && self::$_cache_displaysignatures !== null) {
			return self::$_cache_displaysignatures;
		}

		$result = $this->Forum()->Parent()->DisplaySignatures;
		self::$_cache_displaysignatures = $result;
		return $result;
	}

	/**
	 * Get the latest post from this thread. Nicer way then using an control
	 * from the template
	 *
	 * @return Post
	 */
	public function getLatestPost() {
		return DataObject::get_one('Post', "\"ThreadID\" = '$this->ID'", true, '"ID" DESC');
	}

	/**
	 * Return the first post from the thread. Useful to working out the original author
	 *
	 * @return Post
	 */
	function getFirstPost() {
		return DataObject::get_one('Post', "\"ThreadID\" = '$this->ID'", true, '"ID" ASC');
	}

	/**
	 * Return the number of posts in this thread. We could use count on
	 * the dataobject set but that is slower and causes a performance overhead
	 *
	 * @return int
	 */
	public function getNumPosts() {
		return (int)DB::prepared_query(
			"SELECT count(*) FROM \"Post\" WHERE \"ThreadID\" = ?",
			array($this->ID)
		)->value();
	}

	/**
	 * Check if they have visited this thread before. If they haven't increment
	 * the NumViews value by 1 and set visited to true.
	 *
	 * @return void
	 */
	public function incNumViews() {
		if(Session::get('ForumViewed-' . $this->ID)) return false;

		Session::set('ForumViewed-' . $this->ID, 'true');

		$this->NumViews++;

		DB::prepared_query(
			'UPDATE "ForumThread" SET "NumViews" = ? WHERE "ID" = ?',
			array($this->NumViews, $this->ID)
		);
	}

	/**
	 * Link to this forum thread
	 *
	 * @return String
	 */
	function Link($action = "show", $showID = true) {
		$forum = DataObject::get_by_id("Forum", $this->ForumID);
		if($forum) {
			$baseLink = $forum->Link();
			$extra = ($showID) ? '/'.$this->ID : '';
			return ($action) ? $baseLink . $action . $extra : $baseLink;
		} else {
			user_error("Bad ForumID '$this->ForumID'", E_USER_WARNING);
		}
	}

	/**
	 * Check to see if the user has subscribed to this thread
	 *
	 * @return bool
	 */
	function getHasSubscribed() {
		$member = Member::currentUser();

		return ($member) ? ForumThread_Subscription::already_subscribed($this->ID, $member->ID) : false;
	}

	/**
	 * Before deleting the thread remove all the posts
	 */
	function onBeforeDelete() {
		parent::onBeforeDelete();

		if($posts = $this->Posts()) {
			foreach($posts as $post) {
				// attachment deletion is handled by the {@link Post::onBeforeDelete}
				$post->delete();
			}
		}
	}

	function onAfterWrite() {
		if($this->isChanged('ForumID', 2)){
			$posts = $this->Posts();
			if($posts && $posts->count()) {
				foreach($posts as $post) {
					$post->ForumID=$this->ForumID;
					$post->write();
				}
			}
		}
		parent::onAfterWrite();
	}

	/**
	 * @return Text
	 */
	function getEscapedTitle() {
		//return DBField::create('Text', $this->dbObject('Title')->XML());
		return DBField::create_field('Text', $this->dbObject('Title')->XML());
	}
}


/**
 * Forum Thread Subscription: Allows members to subscribe to this thread
 * and receive email notifications when these topics are replied to.
 *
 * @package forum
 */
class ForumThread_Subscription extends DataObject {

	private static $db = array(
		"LastSent" => "SS_Datetime"
	);

	private static $has_one = array(
		"Thread" => "ForumThread",
		"Member" => "Member"
	);

	/**
	 * Checks to see if a Member is already subscribed to this thread
	 *
	 * @param int $threadID The ID of the thread to check
	 * @param int $memberID The ID of the currently logged in member (Defaults to Member::currentUserID())
	 *
	 * @return bool true if they are subscribed, false if they're not
	 */
	static function already_subscribed($threadID, $memberID = null) {
		if($threadID && !$memberID) $memberID = Member::currentUserID();
		if(!$threadID || !$memberID) return false;

		return DB::prepared_query('
			SELECT COUNT("ID")
			FROM "ForumThread_Subscription"
			WHERE "ThreadID" = ? AND "MemberID" = ?',
			array($threadID, $memberID)
		)->value() > 0;
	}

	/**
	 * Notifies everybody that has subscribed to this topic that a new post has been added.
	 * To get emailed, people subscribed to this topic must have visited the forum
	 * since the last time they received an email
	 *
	 * @param Post $post The post that has just been added
	 */
	public static function notify(Post $post) {
		$subscriptions = ForumThread_Subscription::get()
				->filter('ThreadID', $post->ThreadID)
				->exclude('MemberID', $post->AuthorID);
		foreach($subscriptions as $subscription) {
			// Get the members details
			$member = $subscription->Member();
			$adminEmail = Config::inst()->get('Email', 'admin_email');

			if($member) {
				$email = new Email();
				$email->setFrom($adminEmail);
				$email->setTo($member->Email);
				$email->setSubject(_t('Post.NEWREPLY', 'New reply for {title}', array('title' => $post->Title)));
				$email->setTemplate('ForumMember_TopicNotification');
				$email->populateTemplate($member);
				$email->populateTemplate($post);
				$email->populateTemplate(array(
					'UnsubscribeLink' => Director::absoluteBaseURL() . $post->Thread()->Forum()->Link() . '/unsubscribe/' . $post->ID
				));
				$email->send();
			}
		}
	}
}
