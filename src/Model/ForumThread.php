<?php

namespace SilverStripe\Forum\Models;

use SilverStripe\Security\Member;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Session;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Director;
use SQLQuery;

/**
 * A representation of a forum thread. A forum thread is 1 topic on the forum
 * which has multiple posts underneath it.
 *
 * @package forum
 */

class ForumThread extends DataObject
{

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
    public function canPost($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return ($this->Forum()->canPost($member) && !$this->IsReadOnly);
    }

    /**
     * Check if user can moderate this thread
     */
    public function canModerate($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return $this->Forum()->canModerate($member);
    }

    /**
     * Check if user can view the thread
     */
    public function canView($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return $this->Forum()->canView($member);
    }

    /**
     * Hook up into moderation.
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return $this->canModerate($member);
    }

    /**
     * Hook up into moderation - users cannot delete their own posts/threads because
     * we will loose history this way.
     */
    public function canDelete($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return $this->canModerate($member);
    }

    /**
     * Hook up into canPost check
     */
    public function canCreate($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return $this->canPost($member);
    }

    /**
     * Are Forum Signatures on Member profiles allowed.
     * This only needs to be checked once, so we cache the initial value once per-request.
     *
     * @return bool
     */
    public function getDisplaySignatures()
    {
        if (isset(self::$_cache_displaysignatures) && self::$_cache_displaysignatures !== null) {
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
    public function getLatestPost()
    {
        return DataObject::get_one('Post', "\"ThreadID\" = '$this->ID'", true, '"ID" DESC');
    }

    /**
     * Return the first post from the thread. Useful to working out the original author
     *
     * @return Post
     */
    public function getFirstPost()
    {
        return DataObject::get_one('Post', "\"ThreadID\" = '$this->ID'", true, '"ID" ASC');
    }

    /**
     * Return the number of posts in this thread. We could use count on
     * the dataobject set but that is slower and causes a performance overhead
     *
     * @return int
     */
    public function getNumPosts()
    {
        $sqlQuery = new SQLQuery();
        $sqlQuery->setFrom('"Post"');
        $sqlQuery->setSelect('COUNT("Post"."ID")');
        $sqlQuery->addInnerJoin('SilverStripe\\Security\\Member', '"Post"."AuthorID" = "Member"."ID"');
        $sqlQuery->addWhere('"Member"."ForumStatus" = \'Normal\'');
        $sqlQuery->addWhere('"ThreadID" = ' . $this->ID);
        return $sqlQuery->execute()->value();
    }

    /**
     * Check if they have visited this thread before. If they haven't increment
     * the NumViews value by 1 and set visited to true.
     *
     * @return void
     */
    public function incNumViews()
    {
        if (Session::get('ForumViewed-' . $this->ID)) {
            return false;
        }

        Session::set('ForumViewed-' . $this->ID, 'true');

        $this->NumViews++;
        $SQL_numViews = Convert::raw2sql($this->NumViews);

        DB::query("UPDATE \"ForumThread\" SET \"NumViews\" = '$SQL_numViews' WHERE \"ID\" = $this->ID");
    }

    /**
     * Link to this forum thread
     *
     * @return String
     */
    public function Link($action = "show", $showID = true)
    {
        $forum = DataObject::get_by_id("Forum", $this->ForumID);
        if ($forum) {
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
    public function getHasSubscribed()
    {
        $member = Member::currentUser();

        return ($member) ? ForumThread_Subscription::already_subscribed($this->ID, $member->ID) : false;
    }

    /**
     * Before deleting the thread remove all the posts
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        if ($posts = $this->Posts()) {
            foreach ($posts as $post) {
                // attachment deletion is handled by the {@link Post::onBeforeDelete}
                $post->delete();
            }
        }
    }

    public function onAfterWrite()
    {
        if ($this->isChanged('ForumID', 2)) {
            $posts = $this->Posts();
            if ($posts && $posts->count()) {
                foreach ($posts as $post) {
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
    public function getEscapedTitle()
    {
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
class ForumThread_Subscription extends DataObject
{

    private static $db = array(
        "LastSent" => "SS_Datetime"
    );

    private static $has_one = array(
        "Thread" => "ForumThread",
        "Member" => "SilverStripe\\Security\\Member"
    );

    /**
     * Checks to see if a Member is already subscribed to this thread
     *
     * @param int $threadID The ID of the thread to check
     * @param int $memberID The ID of the currently logged in member (Defaults to Member::currentUserID())
     *
     * @return bool true if they are subscribed, false if they're not
     */
    static function already_subscribed($threadID, $memberID = null)
    {
        if (!$memberID) {
            $memberID = Member::currentUserID();
        }
        $SQL_threadID = Convert::raw2sql($threadID);
        $SQL_memberID = Convert::raw2sql($memberID);

        if ($SQL_threadID=='' || $SQL_memberID=='') {
            return false;
        }

        return (DB::query("
			SELECT COUNT(\"ID\")
			FROM \"ForumThread_Subscription\"
			WHERE \"ThreadID\" = '$SQL_threadID' AND \"MemberID\" = $SQL_memberID")->value() > 0) ? true : false;
    }

    /**
     * Notifies everybody that has subscribed to this topic that a new post has been added.
     * To get emailed, people subscribed to this topic must have visited the forum
     * since the last time they received an email
     *
     * @param Post $post The post that has just been added
     */
    static function notify(Post $post)
    {
        $list = DataObject::get(
            "ForumThread_Subscription",
            "\"ThreadID\" = '". $post->ThreadID ."' AND \"MemberID\" != '$post->AuthorID'"
        );

        if ($list) {
            foreach ($list as $obj) {
                $SQL_id = Convert::raw2sql((int)$obj->MemberID);

                // Get the members details
                $member = DataObject::get_one("SilverStripe\\Security\\Member", "\"Member\".\"ID\" = '$SQL_id'");
                $adminEmail = Config::inst()->get('SilverStripe\\Control\\Email\\Email', 'admin_email');

                if ($member) {
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
}
