<?php

namespace FullscreenInteractive\SilverStripe\Forum\Model;

use SilverStripe\ORM\DataObject;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class ForumThread extends DataObject
{
    private static string $table_name = 'ForumThread';

    private static array $db = [
        "Title" => 'Varchar(255)',
        "NumViews" => 'Int',
        "IsSticky" => 'Boolean',
        "IsReadOnly" => 'Boolean',
        "IsGlobalSticky" => 'Boolean',
        // Latest activity: max of each post's Created/LastEdited; synced from Post on write/delete.
        "LastPostDate" => 'Datetime',
    ];

    private static $has_one = [
        'Forum' => Forum::class,
        'Author' => Member::class
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
        'IsGlobalSticky' => true,
        'LastPostDate' => true,
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
     * Get the latest post from this thread.
     */
    public function getLatestPost(): ?Post
    {
        return Post::get()->filter([
            'ThreadID' => $this->ID
        ])->sort([
            'LastEdited' => 'DESC',
            'ID' => 'DESC',
        ])->first();
    }

    /**
     * Recompute LastPostDate from posts (Created vs LastEdited, whichever is later).
     */
    public function syncLastPostDate(): void
    {
        if (!$this->isInDB() || $this->ID <= 0) {
            return;
        }

        $sql = sprintf(
            'SELECT MAX(GREATEST("Created", "LastEdited")) AS "LastActivity" FROM "Post" WHERE "ThreadID" = %d',
            (int) $this->ID
        );
        $row = DB::query($sql)->record();
        $lastActivity = null;
        if (is_array($row)) {
            $lastActivity = $row['LastActivity'] ?? null;
        }
        if ($lastActivity === '' || $lastActivity === false) {
            $lastActivity = null;
        }

        // No posts: keep listing/sort stable and avoid repeated "null date" hydration.
        if (!$lastActivity) {
            $lastActivity = $this->getField('Created') ?: null;
        }

        $this->LastPostDate = $lastActivity;
        $this->write();
    }

    /**
     * Return the first post from the thread. Useful to working out the original author
     */
    public function getFirstPost(): ?Post
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
    public function getNumPosts(): int
    {
        return Post::get()->filter([
            'ThreadID' => $this->ID,
            'Author.ForumStatus' => 'Normal'
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
    public function Link($action = "show", $showID = true): string
    {
        $forum = $this->Forum();
        $baseLink = $forum->Link();
        $extra = ($showID) ? '/' . $this->ID : '';

        return ($action) ? Controller::join_links($baseLink, $action, $extra) : $baseLink;
    }

    /**
     * Check to see if the user has subscribed to this thread
     *
     * @return bool
     */
    public function getHasSubscribed()
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return false;
        }

        return ForumThreadSubscription::singleton()->isSubscribed($this->ID, $member->ID);
    }


    protected function onBeforeWrite()
    {
        if (!$this->isInDB() && !$this->LastPostDate) {
            $this->LastPostDate = DBDatetime::now()->getValue();
        }

        parent::onBeforeWrite();
    }

    public function onAfterWrite()
    {
        if ($this->isChanged('ForumID', 2)) {
            DB::query(sprintf("UPDATE \"Post\" SET \"ForumID\" = '%s' WHERE \"ThreadID\" = %s", $this->ForumID, $this->ID));
        }

        parent::onAfterWrite();
    }


    public function getEscapedTitle(): DBField
    {
        return DBField::create_field('Text', $this->dbObject('Title')->XML());
    }
}
