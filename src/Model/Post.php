<?php

namespace FullscreenInteractive\SilverStripe\Forum\Model;

use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\Parsers\BBCodeParser;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Security;

class Post extends DataObject
{
    private static $table_name = 'Post';

    private static $singular_name = 'Post';

    private static $plural_name = 'Posts';

    private static $description = 'A post in a forum thread';

    /**
     * @config
     */
    private static $fpost_content_parser = BBCodeParser::class;

    private static $db = [
        "Content" => "Text",
        "Status" => "Enum('Awaiting, Moderated, Rejected, Archived', 'Moderated')",
    ];

    private static $casting = [
        "Updated" => "Datetime",
        "RSSContent" => "HTMLText",
        "RSSAuthor" => "Varchar",
        "Content" => "HTMLText"
    ];

    private static $has_one = [
        "Author" => Member::class,
        "Thread" => ForumThread::class,
        "Forum" => Forum::class
    ];

    private static $has_many = [
        "Attachments" => PostAttachment::class
    ];

    private static $cascade_deletes = [
        "Attachments"
    ];

    private static $summary_fields = array(
        "Content.LimitWordCount" => "Summary",
        "Created" => "Created",
        "Status" => "Status",
        "Thread.Title" => "Thread",
        "Forum.Title" => "Forum"
    );

    /**
     * Update all the posts to have a forum ID of their thread ID.
     */
    public function requireDefaultRecords()
    {
        $posts = Post::get()->filter(array('ForumID' => 0, 'ThreadID:GreaterThan' => 0));

        if ($posts->exists()) {
            foreach ($posts as $post) {
                if ($post->ThreadID) {
                    $post->ForumID = $post->Thread()->ForumID;
                    $post->write();
                }
            }

            DB::alteration_message(_t('Forum.POSTSFORUMIDUPDATED', 'Forum posts forum ID added'), 'created');
        }
    }


    /**
     * Check if user can see the post
     */
    public function canView($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
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
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
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
     */
    public function canDelete($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        if ($this->canEdit($member)) {
            return true;
        } else {
            return $this->Thread()->canModerate($member);
        }
    }

    /**
     * Check if user can add new posts - hook up into canPost.
     */
    public function canCreate($member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        return $this->Thread()->canPost($member);
    }

    /**
     * Returns the absolute url rather then relative. Used in Post RSS Feed
     *
     * @return String
     */
    public function AbsoluteLink()
    {
        return Director::absoluteURL($this->Link());
    }

    /**
     * Return the title of the post. Because we don't have to have the title
     * on individual posts check with the thread title
     */
    public function getTitle(): string
    {
        return ($this->isFirstPost())
            ? $this->Thread()->Title
            : DBField::create_field('Text', sprintf(_t('Post.RESPONSE', "Re: %s", 'Post Subject Prefix'), $this->Thread()->Title));
    }

    /**
     * Return the last edited date, if it's different from created
     */
    public function getUpdated()
    {
        if ($this->LastEdited != $this->Created) {
            return $this->LastEdited;
        }
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
        $earlierPosts = DB::query(sprintf(
            'SELECT COUNT("ID") FROM "Post" WHERE "ThreadID" = \'%d\' and "ID" < \'%d\'',
            $this->ThreadID,
            $this->ID
        ))->value();
        return empty($earlierPosts);
    }

    /**
     * Return a link to edit this post.
     *
     * @return String
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
     * @return String
     */
    public function DeleteLink()
    {
        if ($this->canDelete()) {
            $url = Controller::join_links($this->Link('deletepost'), $this->ID);
            $token = SecurityToken::inst();
            $url = $token->addToUrl($url);

            $firstPost = ($this->isFirstPost()) ? ' firstPost' : '';

            return '<a class="deleteLink' . $firstPost . '" href="' . $url . '">' . _t('Post.DELETE', 'Delete') . '</a>';
        }

        return false;
    }

    /**
     * Return a link to the reply form. Permission checking is handled on the actual URL
     * and not on this function
     *
     * @return String
     */
    public function ReplyLink()
    {
        $url = $this->Link('reply');

        return '<a href="' . $url . '" class="replyLink">' . _t('Post.REPLYLINK', 'Post Reply') . '</a>';
    }

    /**
     * Return a link to the post view.
     *
     * @return String
     */
    public function ShowLink()
    {
        $url = $this->Link('show');

        return '<a href="' . $url . '" class="showLink">' . _t('Post.SHOWLINK', 'Show Thread') . "</a>";
    }

    /**
     * Return a link to mark this post as spam.
     *
     * @return String
     */
    public function MarkAsSpamLink()
    {
        if ($this->Thread()->canModerate()) {
            $member = Security::getCurrentUser();
            if ($member->ID != $this->AuthorID) {
                $url = Controller::join_links($this->Forum()->Link('markasspam'), $this->ID);
                $token = SecurityToken::inst();
                $url = $token->addToUrl($url);

                $firstPost = ($this->isFirstPost()) ? ' firstPost' : '';

                return sprintf('<a href="%s" class="markAsSpamLink%s" rel="%d">%s</a>', $url, $firstPost, $this->ID, _t('Post.MARKASSPAM', 'Mark as Spam'));
            }
        }
        return false;
    }

    public function BanLink()
    {
        $thread = $this->Thread();
        if ($thread->canModerate()) {
            $link = $thread->Forum()->Link('ban') . '/' . $this->AuthorID;
            return sprintf(
                '<a class="banLink" href="%s" rel="%d">%s</a>',
                $link,
                $this->AuthorID,
                _t('Post.BANUSER', 'Ban User')
            );
        }

        return false;
    }

    public function GhostLink()
    {
        $thread = $this->Thread();
        if ($thread->canModerate()) {
            $link = $thread->Forum()->Link('ghost') . '/' . $this->AuthorID;
            return sprintf('<a class="ghostLink" href="%s" rel="%d">%s</a>', $link, $this->AuthorID, _t('Post.GHOSTUSER', 'Ghost User'));
        }
        return false;
    }

    /**
     * Return the parsed content and the information for the
     * RSS feed
     */
    public function getRSSContent()
    {
        return $this->renderWith('Includes/Post_rss');
    }


    public function getRSSAuthor()
    {
        $author = $this->Author();

        return $author->Nickname;
    }


    public function getParsedContent()
    {
        $parser = Injector::inst()->get(self::$fpost_content_parser);
        return $parser->parse($this->Content);
    }

    /**
     * Return a link to show this post
     */
    public function Link($action = "show")
    {
        // only include the forum thread ID in the URL if we're showing the thread either
        // by showing the posts or replying therwise we only need to pass a single ID.
        $includeThreadID = ($action == "show" || $action == "reply") ? true : false;
        $link = $this->Thread()->Link($action, $includeThreadID);

        // calculate what page results the post is on
        // the count is the position of the post in the thread
        $count = DB::query("
			SELECT COUNT(\"ID\")
			FROM \"Post\"
			WHERE \"ThreadID\" = '$this->ThreadID' AND \"Status\" = 'Moderated' AND \"ID\" < $this->ID
		")->value();

        $postsPerPage = $this->Forum()->PostsPerPage || 10;

        $start = ($count >= $postsPerPage) ? floor($count / $postsPerPage) * $postsPerPage : 0;
        $pos = ($start == 0 ? '' : "?start=$start") . ($count == 0 ? '' : "#post{$this->ID}");

        return ($action == "show") ? $link . $pos : $link;
    }
}
