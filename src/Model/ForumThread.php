<?php

namespace FullscreenInteractive\SilverStripe\Forum\Model;

use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\ORM\DataObject;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Security;

class ForumThread extends DataObject
{

    private static $db = [
        "Title" => 'Varchar(255)',
        "NumViews" => 'Int',
        "IsSticky" => 'Boolean',
        "IsReadOnly" => 'Boolean',
        "IsGlobalSticky" => 'Boolean'
    ];

    private static $has_one = [
        'Forum' => Forum::class
    ];

    private static $has_many = [
        'Posts' => Post::class
    ];

    private static $defaults = [
        'NumViews' => 0,
        'IsSticky' => false,
        'IsReadOnly' => false,
        'IsGlobalSticky' => false
    ];

    private static $indexes = [
        'IsSticky' => true,
        'IsGlobalSticky' => true
    ];


    /**
     * Check if the user can create new threads and add responses
     */
    public function canPost($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return ($this->Forum()->canPost($member) && !$this->IsReadOnly);
    }


    /**
     * Check if user can moderate this thread
     */
    public function canModerate($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return $this->Forum()->canModerate($member);
    }


    /**
     * Check if user can view the thread
     */
    public function canView($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return $this->Forum()->canView($member);
    }


    /**
     * Hook up into moderation.
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
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
            $member = Security::getCurrentUser();
        }
        return $this->canModerate($member);
    }

    /**
     * Hook up into canPost check
     */
    public function canCreate($member = null, $context = [])
    {
        return $this->canPost($member, $context);
    }

    /**
     * Are Forum Signatures on Member profiles allowed.
     * This only needs to be checked once, so we cache the initial value once per-request.
     *
     * @return bool
     */
    public function getDisplaySignatures()
    {
        $result = $this->Forum()->Parent()->DisplaySignatures;

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
        return Post::get()->filter([
            'ThreadID' => $this->ID
        ])->sort([
            'Created' => 'DESC'
        ])->first();
    }

    /**
     * Return the first post from the thread. Useful to working out the original author
     *
     * @return Post
     */
    public function getFirstPost()
    {
        return Post::get()->filter([
            'ThreadID' => $this->ID
        ])->sort([
            'Created' => 'ASC'
        ])->first();
    }

    /**
     * Return the number of posts in this thread
     *
     * @return int
     */
    public function getNumPosts()
    {
        return Post::get()->filter([
            'ThreadID' => $this->ID
        ])->count();
    }

    /**
     * Check if they have visited this thread before. If they haven't increment
     * the NumViews value by 1 and set visited to true.
     *
     * @return void
     */
    public function incNumViews()
    {
        $session = Controller::curr()->getRequest()->getSession();
        if ($session->get('ForumViewed-' . $this->ID)) {
            return false;
        }

        $session->set('ForumViewed-' . $this->ID, 'true');

        $this->NumViews++;

        DB::query(sprintf("UPDATE \"ForumThread\" SET \"NumViews\" = '%s' WHERE \"ID\" = %s", $this->NumViews, $this->ID));
    }

    /**
     * Link to this forum thread.
     */
    public function Link($action = "show", $showID = true)
    {
        $forum = $this->Forum();
        $baseLink = $forum->Link();
        $extra = ($showID) ? '/' . $this->ID : '';
        return ($action) ? $baseLink . $action . $extra : $baseLink;
    }

    /**
     * Check to see if the user has subscribed to this thread
     *
     * @return bool
     */
    public function getHasSubscribed()
    {
        $member = Security::getCurrentUser();

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
                    $post->ForumID = $this->ForumID;
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
    static function already_subscribed($threadID, $memberID = null)
    {
        if (!$memberID) {
            $memberID = Member::currentUserID();
        }
        $SQL_threadID = Convert::raw2sql($threadID);
        $SQL_memberID = Convert::raw2sql($memberID);

        if ($SQL_threadID == '' || $SQL_memberID == '') {
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
            "\"ThreadID\" = '" . $post->ThreadID . "' AND \"MemberID\" != '$post->AuthorID'"
        );

        if ($list) {
            foreach ($list as $obj) {
                $SQL_id = Convert::raw2sql((int)$obj->MemberID);

                // Get the members details
                $member = DataObject::get_one("Member", "\"Member\".\"ID\" = '$SQL_id'");
                $adminEmail = Config::inst()->get('Email', 'admin_email');

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
