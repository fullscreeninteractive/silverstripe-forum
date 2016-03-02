<?php

/**
 * Forum Post Object. Contains a single post by the user. A thread is generated
 * with multiple posts.
 *
 * @package forum
 */

class Post extends DataObject
{
    private static $db = array(
        "Content" => "Text",
        "Status" => "Enum('Awaiting, Moderated, Rejected, Archived', 'Moderated')",
    );

    private static $casting = array(
        "Updated" => "SS_Datetime",
        "RSSContent" => "HTMLText",
        "RSSAuthor" => "Varchar",
        "Content" => "HTMLText"
    );

    private static $has_one = array(
        "Author" => "Member",
        "Thread" => "ForumThread",
        "Forum" => "Forum" // denormalized data but used for read speed
    );

    private static $has_many = array(
        "Attachments" => "Post_Attachment"
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
     * Before deleting a post make sure all attachments are also deleted
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
     */
    public function canView($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return $this->Thread()->canView($member);
    }

    /**
     * Check if user can edit the post (only if it's his own, or he's an admin user)
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
     */
    public function canDelete($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
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
     * @return String
     */
    public function AbsoluteLink()
    {
        return Director::absoluteURL($this->Link());
    }

    /**
     * Return the title of the post. Because we don't have to have the title
     * on individual posts check with the topic
     *
     * @return String
     */
    public function getTitle()
    {
        return ($this->isFirstPost()) ? $this->Thread()->Title : sprintf(_t('Post.RESPONSE', "Re: %s", 'Post Subject Prefix'), $this->Thread()->Title);
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
        $earlierPosts = DB::prepared_query(
            'SELECT COUNT("ID") FROM "Post" WHERE "ThreadID" = ? AND "ID" < ?',
            array($this->ThreadID, $this->ID)
        )->value();
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
            return $url;
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
            return $url;
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
        return $url;
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
     * used for the spamprotection module
     *
     * @return String
     */
    public function MarkAsSpamLink()
    {
        if ($this->Thread()->canModerate()) {
            $member = Member::currentUser();
            if ($member->ID != $this->AuthorID) {
                $url = Controller::join_links($this->Forum()->Link('markasspam'), $this->ID);
                $token = SecurityToken::inst();
                $url = $token->addToUrl($url);

                $firstPost = ($this->isFirstPost()) ? ' firstPost' : '';

                return $url;
            }
        }
        return false;
    }

    public function BanLink()
    {
        $thread = $this->Thread();
        if ($thread->canModerate()) {
            $link = $thread->Forum()->Link('ban') .'/'. $this->AuthorID;
            return $link;
        }
        return false;
    }

    public function GhostLink()
    {
        $thread = $this->Thread();
        if ($thread->canModerate()) {
            $link = $thread->Forum()->Link('ghost') .'/'. $this->AuthorID;
            return $link;
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

    /**
     * Return a link to show this post
     *
     * @return String
     */
    public function Link($action = "show")
    {
        // only include the forum thread ID in the URL if we're showing the thread either
        // by showing the posts or replying therwise we only need to pass a single ID.
        $includeThreadID = ($action == "show" || $action == "reply") ? true : false;
        $link = $this->Thread()->Link($action, $includeThreadID);

        // calculate what page results the post is on
        // the count is the position of the post in the thread
        $count = DB::prepared_query('
			SELECT COUNT("ID")
			FROM "Post"
			WHERE "ThreadID" = ? AND "Status" = \'Moderated\' AND "ID" < ?',
            array($this->ThreadID, $this->ID)
        )->value();

        $start = ($count >= Forum::$posts_per_page) ? floor($count / Forum::$posts_per_page) * Forum::$posts_per_page : 0;
        $pos = ($start == 0 ? '' : "?start=$start") . ($count == 0 ? '' : "#post{$this->ID}");

        return ($action == "show") ? $link . $pos : $link;
    }
}

/**
 * Attachments for posts (one post can have many attachments)
 *
 * @package forum
 */
class Post_Attachment extends File
{
    private static $has_one = array(
        "Post" => "Post"
    );

    private static $defaults = array(
        'ShowInSearch' => 0
    );

    /**
     * Can a user delete this attachment
     *
     * @return bool
     */
    public function canDelete($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return ($this->Post()) ? $this->Post()->canDelete($member) : true;
    }

    /**
     * Can a user edit this attachement
     *
     * @return bool
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return ($this->Post()) ? $this->Post()->canEdit($member) : true;
    }

    /**
     * Allows the user to download a file without right-clicking
     */
    public function download()
    {
        if (isset($this->urlParams['ID'])) {
            $SQL_ID = Convert::raw2sql($this->urlParams['ID']);

            if (is_numeric($SQL_ID)) {
                $file = DataObject::get_by_id("Post_Attachment", $SQL_ID);
                $response = SS_HTTPRequest::send_file(file_get_contents($file->getFullPath()), $file->Name);
                $response->output();
            }
        }

        return $this->redirectBack();
    }
}
