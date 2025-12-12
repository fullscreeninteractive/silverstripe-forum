<?php

namespace FullscreenInteractive\SilverStripe\Forum\Model;

use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\ORM\DataObject;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Security;

class ForumThread extends DataObject
{
    private static string $table_name = 'ForumThread';

    private static array $db = [
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

    private static $cascade_deletes = [
        'Posts'
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

        return ($member) ? ForumThreadSubscription::singleton()->isSubscribed($this->ID, $member->ID) : false;
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
        return DBField::create_field('Text', $this->dbObject('Title')->XML());
    }
}
