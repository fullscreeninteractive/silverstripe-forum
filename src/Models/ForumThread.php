<?php

namespace SilverStripe\Forum\Models;

use SilverStripe\Control\Controller;
use SilverStripe\Forum\Pages\Forum;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Session;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Director;

/**
 * A representation of a forum thread. A forum thread is 1 topic on the forum
 * which has multiple posts underneath it.
 *
 * @package forum
 * @property DBVarchar Title
 * @property DBInt     NumViews
 * @property DBBoolean IsSticky
 * @property DBBoolean IsReadOnly
 * @property DBBoolean IsGlobalSticky
 * @method Forum Forum
 * @method HasManyList Posts
 */
class ForumThread extends DataObject
{
    /** @var array */
    private static $db = array(
        "Title"          => "Varchar(255)",
        "NumViews"       => "Int",
        "IsSticky"       => "Boolean",
        "IsReadOnly"     => "Boolean",
        "IsGlobalSticky" => "Boolean"
    );

    /** @var array */
    private static $has_one = array(
        'Forum' => 'Forum'
    );

    /** @var array */
    private static $has_many = array(
        'Posts' => 'Post'
    );

    /** @var array */
    private static $defaults = array(
        'NumViews'       => 0,
        'IsSticky'       => false,
        'IsReadOnly'     => false,
        'IsGlobalSticky' => false
    );

    /** @var array */
    private static $indexes = array(
        'IsSticky'       => true,
        'IsGlobalSticky' => true
    );

    /**
     * @var null|boolean Per-request cache, whether we should display signatures on a post.
     */
    private static $cacheDisplaySignatures = null;

    /**
     * Check if the user can create new threads and add responses
     *
     * @param null|Member $member
     *
     * @return bool
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
     *
     * @param null|Member $member
     *
     * @return bool
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
     *
     * @param null|Member $member
     *
     * @return bool
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
     *
     * @param null|Member $member
     *
     * @return bool
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
     *
     * @param null|Member $member
     *
     * @return bool
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
     *
     * @param null|Member $member
     *
     * @return bool
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
        if (isset(self::$cacheDisplaySignatures) && self::$cacheDisplaySignatures !== null) {
            return self::$cacheDisplaySignatures;
        }

        $result                       = $this->Forum()->Parent()->DisplaySignatures;
        self::$cacheDisplaySignatures = $result;

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
        return Post::get()->filter(['ThreadID' => $this->ID])
            ->sort('ID DESC')
            ->first();
    }

    /**
     * Return the first post from the thread. Useful to working out the original author
     *
     * @return Post
     */
    public function getFirstPost()
    {
        return Post::get()->filter(['ThreadID' => $this->ID])
            ->sort('ID ASC')
            ->first();
    }

    /**
     * Return the number of posts in this thread. We could use count on
     * the dataobject set but that is slower and causes a performance overhead
     *
     * @todo SS4 compat
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
            return;
        }

        Session::set('ForumViewed-' . $this->ID, 'true');

        $this->NumViews++;
        $this->write();
    }

    /**
     * Link to this forum thread
     *
     * @param string $action
     * @param bool   $showID
     *
     * @return String
     */
    public function Link($action = "show", $showID = true)
    {
        /** @var Forum $forum */
        $forum = Forum::get()->byID($this->ForumID);
        if (!$forum) {
            user_error("Bad ForumID '$this->ForumID'", E_USER_WARNING);
        }

        $baseLink = $forum->Link();

        return ($action) ? Controller::join_links($baseLink, $action, ($showID) ? $this->ID : null) : $baseLink;
    }

    /**
     * Check to see if the user has subscribed to this thread
     *
     * @return bool
     */
    public function getHasSubscribed()
    {
        $member = Member::currentUser();

        return ($member) ? ForumThreadSubscription::alreadySubscribed($this->ID, $member->ID) : false;
    }

    /**
     * Before deleting the thread remove all the posts
     *
     * @return void
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

    /**
     * Ensure the correct ForumID is applied to the record
     * 
     * @return void
     */
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
     * @return DBText
     */
    public function getEscapedTitle()
    {
        return DBField::create_field('Text', $this->dbObject('Title')->XML());
    }
}