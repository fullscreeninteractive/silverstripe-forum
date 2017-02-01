<?php

namespace SilverStripe\Forum\Models;

use SilverStripe\Forum\Pages\Forum;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBEnum;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Security\SecurityToken;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Convert;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Assets\File;

/**
 * Forum Post Object. Contains a single post by the user. A thread is generated
 * with multiple posts.
 *
 * @package forum
 * @property DBText Content
 * @property DBEnum Status
 * @method Member Author
 * @method ForumThread Thread
 * @method Forum Forum
 * @method HasManyList Attachments
 */
class Post extends DataObject
{
    /** @var array */
    private static $db = array(
        "Content" => "Text",
        "Status"  => "Enum('Awaiting, Moderated, Rejected, Archived', 'Moderated')",
    );

    /** @var array */
    private static $has_one = array(
        "Author" => Member::class,
        "Thread" => ForumThread::class,
        "Forum"  => Forum::class
    );

    /** @var array */
    private static $has_many = array(
        "Attachments" => PostAttachment::class
    );

    /** @var array */
    private static $casting = array(
        "Updated"    => "Datetime",
        "RSSContent" => "HTMLText",
        "RSSAuthor"  => "Varchar",
        "Content"    => "HTMLText"
    );

    /** @var array */
    private static $summary_fields = array(
        "Content.LimitWordCount" => "Summary",
        "Created"                => "Created",
        "Status"                 => "Status",
        "Thread.Title"           => "Thread",
        "Forum.Title"            => "Forum"
    );

    /**
     * Update all the posts to have a forum ID of their thread ID.
     *
     * @return void
     */
    public function requireDefaultRecords()
    {
        $posts = Post::get()->filter(array('ForumID' => 0, 'ThreadID:GreaterThan' => 0));

        if ($posts->exists()) {
            return;
        }
        /** @var Post $post */
        foreach ($posts as $post) {
            if ($post->ThreadID) {
                $post->ForumID = $post->Thread()->ForumID;
                $post->write();
            }
        }

        DB::alteration_message(_t('Forum.POSTSFORUMIDUPDATED', 'Forum posts forum ID added'), 'created');
    }

    /**
     * Before deleting a post make sure all attachments are also deleted
     *
     * @return void
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        if ($attachments = $this->Attachments()) {
            foreach ($attachments as $file) {
                $file->delete();
                $file->destroy();
            }
        }
    }

    /**
     * Check if user can see the post
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

        if ($this->Author()->ForumStatus != 'Normal') {
            if ($this->AuthorID != $member->ID || $member->ForumStatus != 'Ghost') {
                return false;
            }
        }

        return $this->Thread()->canView($member);
    }

    /**
     * Check if user can edit the post (only if it's his own, or he's an admin user)
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

        if ($member) {
            // Admins can always edit, regardless of thread/post ownership
            if (Permission::checkMember($member, 'ADMIN')) {
                return true;
            }

            // Otherwise check for thread permissions and ownership
            if ($this->Thread()->canPost($member) && $member->ID == $this->AuthorID) {
                return true;
            }
        }

        return false;
    }

    /**
     * Follow edit permissions for this, but additionally allow moderation even
     * if the thread is marked as readonly.
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

        if ($this->canEdit($member)) {
            return true;
        }

        return $this->Thread()->canModerate($member);
    }

    /**
     * Check if user can add new posts - hook up into canPost.
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

        return $this->Thread()->canPost($member);
    }

    /**
     * Returns the absolute url rather then relative. Used in Post RSS Feed
     *
     * @return string
     */
    public function AbsoluteLink()
    {
        return Director::absoluteURL($this->Link());
    }

    /**
     * Return the title of the post. Because we don't have to have the title
     * on individual posts check with the topic
     *
     * @return string
     */
    public function getTitle()
    {
        if ($this->isFirstPost()) {
            return $this->Thread()->Title;
        }

        return _t(
            'Post.RESPONSE',
            "Re: {title}",
            'Post Subject Prefix',
            [
                'title' => $this->Thread()->Title
            ]
        );
    }

    /**
     * Return the last edited date, if it's different from created
     *
     * @return DBDatetime|bool
     */
    public function getUpdated()
    {
        if ($this->LastEdited != $this->Created) {
            return false;
        }

        return $this->LastEdited;
    }

    /**
     * Is this post the first post in the thread. Check if their is a post with an ID less
     * than the one of this post in the same thread
     *
     * @return bool
     */
    public function isFirstPost()
    {
        if (empty($this->ThreadID) || empty($this->ID)) {
            return false;
        }

        $earlierPosts = Post::get()->filter(
            [
                'ThreadID'    => $this->ThreadID,
                'ID:LessThan' => $this->ID
            ]
        )->count();

        return (!$earlierPosts);
    }

    /**
     * Return a link to edit this post.
     *
     * @return string
     */
    public function EditLink()
    {
        if ($this->canEdit()) {
            $url = Controller::join_links($this->Link('editpost'), $this->ID);

            return '<a href="' . $url . '" class="editPostLink">' . _t('Post.EDIT', 'Edit') . '</a>';
        }

        return false;
    }

    /**
     * Return a link to delete this post.
     *
     * If the member is an admin of this forum, (ADMIN permissions
     * or a moderator) then they can delete the post.
     *
     * @return string
     */
    public function DeleteLink()
    {
        if ($this->canDelete()) {
            $url   = Controller::join_links($this->Link('deletepost'), $this->ID);
            $token = SecurityToken::inst();
            $url   = $token->addToUrl($url);

            $firstPost = ($this->isFirstPost()) ? ' firstPost' : '';

            return '<a class="deleteLink' . $firstPost . '" href="' . $url . '">' . _t('Post.DELETE',
                'Delete') . '</a>';
        }

        return false;
    }

    /**
     * Return a link to the reply form. Permission checking is handled on the actual URL
     * and not on this function
     *
     * @return string
     */
    public function ReplyLink()
    {
        $url = $this->Link('reply');

        return '<a href="' . $url . '" class="replyLink">' . _t('Post.REPLYLINK', 'Post Reply') . '</a>';
    }

    /**
     * Return a link to the post view.
     *
     * @return string
     */
    public function ShowLink()
    {
        $url = $this->Link('show');

        return '<a href="' . $url . '" class="showLink">' . _t('Post.SHOWLINK', 'Show Thread') . "</a>";
    }

    /**
     * Return a link to mark this post as spam. Used for the SpamProtection module
     *
     * @return string
     */
    public function MarkAsSpamLink()
    {
        if ($this->Thread()->canModerate()) {
            $member = Member::currentUser();
            if ($member->ID != $this->AuthorID) {
                $url   = Controller::join_links($this->Forum()->Link('markasspam'), $this->ID);
                $token = SecurityToken::inst();
                $url   = $token->addToUrl($url);

                $firstPost = ($this->isFirstPost()) ? ' firstPost' : '';

                return '<a href="' . $url . '" class="markAsSpamLink' . $firstPost . '" rel="' . $this->ID . '">' . _t('Post.MARKASSPAM',
                    'Mark as Spam') . '</a>';
            }
        }

        return false;
    }

    /**
     * Returns a ban link
     *
     * @return bool|string
     */
    public function BanLink()
    {
        $thread = $this->Thread();
        if ($thread->canModerate()) {
            $link = $thread->Forum()->Link('ban') . '/' . $this->AuthorID;

            return "<a class='banLink' href=\"$link\" rel=\"$this->AuthorID\">" . _t('Post.BANUSER', 'Ban User') . "</a>";
        }

        return false;
    }

    /**
     * Returns a ghost link
     *
     * @return bool|string
     */
    public function GhostLink()
    {
        $thread = $this->Thread();
        if ($thread->canModerate()) {
            $link = Controller::join_links($thread->Forum()->Link('ghost'), $this->AuthorID);

            return "<a class='ghostLink' href=\"$link\" rel=\"$this->AuthorID\">" . _t('Post.GHOSTUSER',
                'Ghost User') . "</a>";
        }

        return false;
    }

    /**
     * Return the parsed content and the information for the RSS feed
     *
     * @return DBHTMLText
     */
    public function getRSSContent()
    {
        return $this->renderWith('Includes/Post_rss');
    }

    /**
     * Get RSS Author
     *
     * @return string
     */
    public function getRSSAuthor()
    {
        $author = $this->Author();

        return $author->Nickname;
    }

    /**
     * Return a link to show this post
     *
     * @param string $action
     *
     * @return string
     */
    public function Link($action = "show")
    {
        // only include the forum thread ID in the URL if we're showing the thread either
        // by showing the posts or replying otherwise we only need to pass a single ID.
        $includeThreadID = ($action == "show" || $action == "reply") ? true : false;
        $link            = $this->Thread()->Link($action, $includeThreadID);

        // calculate what page results the post is on
        // the count is the position of the post in the thread
        $count = Post::get()->filter(
            [
                'ThreadID'    => $this->ThreadID,
                'Status'      => 'Moderated',
                'ID:LessThan' => $this->ID
            ]
        )->count();

        $start = ($count >= Forum::$postsPerPage) ? floor($count / Forum::$postsPerPage) * Forum::$postsPerPage : 0;
        $pos   = ($start == 0 ? '' : "?start=$start") . ($count == 0 ? '' : "#post{$this->ID}");

        return ($action == "show") ? $link . $pos : $link;
    }
}

