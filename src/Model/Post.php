<?php

namespace FullscreenInteractive\SilverStripe\Forum\Model;

use FullscreenInteractive\SilverStripe\Forum\Interfaces\PostContentParserInterface;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\Parsers\MarkdownParser;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Security;

class Post extends DataObject
{
    private static string $table_name = 'Post';

    private static string $singular_name = 'Post';

    private static string $plural_name = 'Posts';

    private static string $description = 'A post in a forum thread';

    /**
     * @config
     */
    private static string $post_content_parser = MarkdownParser::class;

    private static array $db = [
        "Content" => "Text",
        "Status" => "Enum('Awaiting, Moderated, Rejected, Archived', 'Moderated')",
    ];

    private static array $casting = [
        "Updated" => "Datetime",
        "RSSContent" => "HTMLText",
        "RSSAuthor" => "Varchar",
        "Content" => "HTMLText"
    ];

    private static array $has_one = [
        "Author" => Member::class,
        "Thread" => ForumThread::class,
        "Forum" => Forum::class
    ];

    private static array $has_many = [
        "Attachments" => PostAttachment::class
    ];

    private static array $cascade_deletes = [
        "Attachments"
    ];

    private static array $summary_fields = [
        "Content.LimitWordCount" => "Summary",
        "Created" => "Created",
        "Status" => "Status",
        "Thread.Title" => "Thread",
        "Forum.Title" => "Forum"
    ];

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
        }

        return $this->Thread()->canModerate($member);
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
     * Returns the absolute url rather then relative. Used in Post RSS Feed.s
     */
    public function AbsoluteLink(): string
    {
        return Director::absoluteURL($this->Link());
    }

    /**
     * Return the title of the post. Because we don't have to have the title
     * on individual posts check with the thread title
     */
    public function getTitle()
    {
        return ($this->isFirstPost())
            ? $this->Thread()->dbObject('Title')
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

        return null;
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

        return Post::get()->filter([
            'ThreadID' => $this->ThreadID,
            'Created:LessThan' => $this->Created
        ])->count() == 0;
    }

    /**
     * Return a link to edit this post.
     */
    public function EditLink(): ?DBHTMLText
    {
        if ($this->canEdit()) {
            $url = Controller::join_links($this->Link('edit'), $this->ID);

            return DBHTMLText::create_field(
                'HTMLText',
                sprintf('<a href="%s" class="editPostLink">%s</a>', $url, _t('Post.EDIT', 'Edit'))
            );
        }

        return null;
    }

    /**
     * Return a link to delete this post.
     *
     * If the member is an admin of this forum, (ADMIN permissions
     * or a moderator) then they can delete the post.
     *
     * @return string
     */
    public function DeleteLink(): ?DBHTMLText
    {
        if ($this->canDelete()) {
            $url = Controller::join_links($this->Link('deletePost'), $this->ID);
            $token = SecurityToken::inst();
            $url = $token->addToUrl($url);

            $firstPost = ($this->isFirstPost()) ? ' firstPost' : '';

            return DBHTMLText::create_field(
                'HTMLText',
                sprintf('<a class="deleteLink%s" href="%s">%s</a>', $firstPost, $url, _t('Post.DELETE', 'Delete'))
            );
        }

        return null;
    }

    /**
     * Return a link to the reply form. Permission checking is handled on the actual URL
     * and not on this function.
     */
    public function ReplyLink(): DBHTMLText
    {
        $url = $this->Link('reply');

        return DBHTMLText::create_field(
            'HTMLText',
            sprintf('<a href="%s" class="replyLink">%s</a>', $url, _t('Post.REPLYLINK', 'Post Reply'))
        );
    }

    /**
     * Return a link to the post view.
     */
    public function ShowLink(): DBHTMLText
    {
        $url = $this->Link('show');

        return DBHTMLText::create_field(
            'HTMLText',
            sprintf('<a href="%s" class="showLink">%s</a>', $url, _t('Post.SHOWLINK', 'Show Thread'))
        );
    }


    public function MarkAsSpamLink(): ?DBHTMLText
    {
        $member = Security::getCurrentUser();

        if ($member && $member->ID !== $this->AuthorID) {
            return $this->getModerationLink('markasspam', $this->ID, _t('Post.MARKASSPAM', 'Mark as Spam'), 'markAsSpamLink' . ($this->isFirstPost() ? ' firstPost' : ''));
        }

        return null;
    }



    public function BanLink(): ?DBHTMLText
    {
        $member = Security::getCurrentUser();

        if ($member && $member->ID !== $this->AuthorID) {
            return $this->getModerationLink('ban', $this->AuthorID, _t('Post.BANUSER', 'Ban User'), 'banLink');
        }

        return null;
    }


    public function GhostLink(): ?DBHTMLText
    {
        $member = Security::getCurrentUser();

        if (!$member || $member->ID !== $this->AuthorID) {
            // make sure author is not a moderator
            $author = $this->Author();

            if ($author->isModeratingForum($this->Forum()) || Permission::checkMember($author, 'ADMIN')) {
                return null;
            }

            return $this->getModerationLink('ghost', $this->AuthorID, _t('Post.GHOSTUSER', 'Ghost User'), 'ghostLink');
        }

        return null;
    }


    public function getModerationLink(string $action, string $id, string $label, string $class): ?DBHTMLText
    {
        $thread = $this->Thread();

        if (!$thread->canModerate()) {
            return null;
        }

        $link = Controller::join_links($this->Forum()->Link($action), $id);
        $token = SecurityToken::inst();
        $link = $token->addToUrl($link);

        return DBHTMLText::create_field(
            'HTMLText',
            sprintf('<a class="%s" href="%s" rel="%d">%s</a>', $class, $link, $id, $label)
        );
    }

    /**
     * Return the parsed content and the information for the RSS feed
     */
    public function getRSSContent()
    {
        return $this->renderWith('Includes/Post_rss');
    }


    public function getRSSAuthor()
    {
        $author = $this->Author();

        return $author->Nickname();
    }


    public function getParsedContent()
    {
        $parserClass = self::config()->get('post_content_parser');

        if (!$parserClass) {
            return $this->Content;
        }

        $parser = Injector::inst()->get($parserClass);

        if (!$parser instanceof PostContentParserInterface) {
            return $this->Content;
        }

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

        $count = Post::get()->filter([
            'ThreadID' => $this->ThreadID,
            'Status' => 'Moderated',
            'ID:LessThan' => $this->ID
        ])->count();

        $postsPerPage = $this->Forum()->config()->get('posts_per_page');
        $start = ($count >= $postsPerPage) ? floor($count / $postsPerPage) * $postsPerPage : 0;

        $pos = ($start == 0 ? '' : "?start=$start") . ($count == 0 ? '' : "#post{$this->ID}");

        return ($action == "show") ? $link . $pos : $link;
    }
}
