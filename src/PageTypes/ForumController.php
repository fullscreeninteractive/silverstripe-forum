<?php

namespace FullscreenInteractive\SilverStripe\Forum\PageTypes;

use FullscreenInteractive\SilverStripe\Forum\Email\ForumNotifyModeratorEmail;
use FullscreenInteractive\SilverStripe\Forum\Form\AdminActionsForm;
use FullscreenInteractive\SilverStripe\Forum\Form\PostMessageForm;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use PageController;
use SilverStripe\Control\Controller;
use SilverStripe\View\Requirements;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThreadSubscription;
use FullscreenInteractive\SilverStripe\Forum\Model\PostAttachment;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\RSS\RSSFeed;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * The forum controller class
 *
 * @package forum
 */
class ForumController extends PageController
{

    private static $allowed_actions = [
        'AdminFormFeatures',
        'deleteAttachment',
        'deletePost',
        'deletethread',
        'editpost',
        'markasspam',
        'PostMessageForm',
        'reply',
        'show',
        'starttopic',
        'subscribe',
        'unsubscribe',
        'rss',
        'ban',
        'ghost'
    ];


    public function init()
    {
        Requirements::javascript("fullscreeninteractive/silverstripe-forum:client/javascript/Forum.js");
        Requirements::css("fullscreeninteractive/silverstripe-forum:client/css/Forum.css");

        parent::init();

        if ($this->redirectedTo()) {
            return;
        }

        RSSFeed::linkToFeed($this->Parent()->Link("rss/forum/$this->ID"), sprintf(_t('Forum.RSSFORUM', "Posts to the '%s' forum"), $this->Title));
        RSSFeed::linkToFeed($this->Parent()->Link("rss"), _t('Forum.RSSFORUMS', 'Posts to all forums'));

        if (!$this->canView()) {
            $messageSet = [
                'default' => _t('Forum.LOGINDEFAULT', 'Enter your email address and password to view this forum.'),
                'alreadyLoggedIn' => _t('Forum.LOGINALREADY', 'I&rsquo;m sorry, but you can&rsquo;t access this forum until you&rsquo;ve logged in. If you want to log in as someone else, do so below'),
                'logInAgain' => _t('Forum.LOGINAGAIN', 'You have been logged out of the forums. If you would like to log in again, enter a username and password below.')
            ];

            Security::permissionFailure($this, $messageSet);
            return;
        }

        // Log this visit to the ForumMember if they exist
        $member = Security::getCurrentUser();

        if ($member &&  ForumHolder::config()->get('currently_online_enabled')) {
            $member->LastViewed = DBDatetime::now()->Format('Y-m-d H:i:s');
            $member->write();
        }
    }

    /**
     * A convenience function which provides nice URLs for an rss feed on this forum.
     */
    public function rss()
    {
        return $this->redirect($this->Parent()->Link("rss/forum/$this->ID"), 301);
    }


    /**
     * Subscribe a user to a thread given by an ID.
     *
     * Designed to be called via AJAX so return true / false
     *
     * @return bool
     */
    public function subscribe()
    {
        $request = $this->getRequest();
        $id = $request->param('ID');

        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        if (!$id) {
            return $this->httpError(400);
        }

        $subscribed = false;
        $member = Security::getCurrentUser();

        if ($member && !ForumThreadSubscription::singleton()->isSubscribed($id, $member->ID)) {
            $obj = ForumThreadSubscription::create();
            $obj->ThreadID = (int) $id;
            $obj->MemberID = $member->ID;
            $obj->LastSent = date('Y-m-d H:i:s', DBDatetime::now()->getTimestamp());
            $obj->write();

            $subscribed = true;
        }

        return ($request->isAjax()) ? $subscribed : $this->redirectBack();
    }

    /**
     * Unsubscribe a user from a thread by an ID
     *
     * Designed to be called via AJAX so return true / false
     *
     * @return bool
     */
    public function unsubscribe()
    {
        $request = $this->getRequest();
        $id = $request->param('ID');

        if (!$id) {
            return $this->httpError(400);
        }

        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        $member = Security::getCurrentUser();

        if (!$member) {
            return $this->httpError(403);
        }

        $subscriptions = ForumThreadSubscription::get()->filter([
            'ThreadID' => $id,
            'MemberID' => $member->ID
        ]);

        foreach ($subscriptions as $subscription) {
            $subscription->delete();
        }

        return ($request->isAjax()) ? true : $this->redirectBack();
    }


    /**
     * Mark a post as spam. Deletes any posts or threads created by that user
     * and removes their user account from the site
     *
     * Must be logged in and have the correct permissions to do marking
     */
    public function markasspam()
    {
        $currentUser = Security::getCurrentUser();
        $request = $this->getRequest();
        $id = $request->param('ID');

        if (!$id) {
            return $this->httpError(400);
        }

        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        $post = Post::get()->byID($id);

        if ($post) {
            // post was the start of a thread, Delete the whole thing
            if ($post->isFirstPost()) {
                $post->Thread()->delete();
            }

            // Delete the current post
            $post->delete();
            $post->extend('onAfterMarkAsSpam');

            // Log deletion event
            Injector::inst()->get(LoggerInterface::class)->info(sprintf(
                'Marked post #%d as spam, by moderator %s (#%d)',
                $post->ID,
                $currentUser->Email,
                $currentUser->ID
            ));

            // Suspend the member (rather than deleting him),
            // which gives him or a moderator the chance to revoke a decision.
            if ($author = $post->Author()) {
                $author->SuspendedUntil = date('Y-m-d', strtotime('+99 years', DBDatetime::now()->getTimestamp()));
                $author->write();
            }

            Injector::inst()->get(LoggerInterface::class)->info(sprintf(
                'Marked post #%d as spam, by moderator %s (#%d)',
                $author->Email,
                $author->ID,
                $currentUser->Email,
                $currentUser->ID
            ));
        }

        return ($request->isAjax()) ? true : $this->redirect($this->Link());
    }


    public function ban()
    {
        $request = $this->getRequest();
        $id = $request->param('ID');

        if (!$id) {
            return $this->httpError(404);
        }

        // check security token
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        if (!$this->canModerate()) {
            return $this->httpError(403);
        }

        $member = Member::get()->byID($id);
        if (!$member || !$member->exists()) {
            return $this->httpError(404);
        }

        $member->ForumStatus = 'Banned';
        $member->write();

        // Log event
        $currentUser = Security::getCurrentUser();
        Injector::inst()->get(LoggerInterface::class)->info(sprintf(
            'Banned member %s (#%d), by moderator %s (#%d)',
            $member->Email,
            $member->ID,
            $currentUser->Email ?? 'Unknown',
            $currentUser->ID ?? 0
        ));

        return ($request->isAjax()) ? true : $this->redirectBack();
    }


    public function ghost()
    {
        $request = $this->getRequest();
        $id = $request->param('ID');

        if (!$id) {
            return $this->httpError(400);
        }

        // check security token
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        if (!$this->canModerate()) {
            return $this->httpError(403);
        }

        $member = Member::get()->byID($id);
        if (!$member || !$member->exists()) {
            return $this->httpError(404);
        }

        $member->ForumStatus = 'Ghost';
        $member->write();

        // Log event
        $currentUser = Security::getCurrentUser();

        Injector::inst()->get(LoggerInterface::class)->info(sprintf(
            'Ghosted member %s (#%d), by moderator %s (#%d)',
            $member->Email,
            $member->ID,
            $currentUser->Email ?? 'Unknown',
            $currentUser->ID ?? 0
        ));

        return ($request->isAjax()) ? true : $this->redirectBack();
    }


    /**
     * Get posts to display. This method assumes an URL parameter "ID" which contains the thread ID.
     */
    public function Posts(string $sortDirection = "ASC"): PaginatedList
    {
        $numPerPage = Forum::config()->get('posts_per_page');
        $threadID = $this->getRequest()->param('ID');

        if (!$threadID) {
            return PaginatedList::create();
        }

        $posts = Post::get()
            ->filter('ThreadID', $threadID)
            ->sort('Created', $sortDirection);

        $member = Security::getCurrentUser();

        $posts = $posts->exclude([
            'Author.ForumStatus' => 'Banned'
        ]);

        if ($member) {
            $posts = $posts->exclude([
                'Author.ForumStatus' => 'Ghost',
                'Author.ID:not' => $member->ID
            ]);
        } else {
            $posts = $posts->exclude([
                'Author.ForumStatus' => 'Ghost'
            ]);
        }

        $paginated = PaginatedList::create($posts);
        $paginated->setPageLength($numPerPage);

        return $paginated;
    }


    /**
     * Section for dealing with reply / edit / create threads form
     */
    public function PostMessageForm(): ?PostMessageForm
    {
        return PostMessageForm::create($this, __FUNCTION__);
    }


    /**
     * Send email to moderators notifying them the thread has been created or post added/edited.
     */
    public function notifyModerators(Post $post, ForumThread $thread, bool $startingThread = false)
    {
        $moderators = $this->Moderators();
        if ($moderators && $moderators->exists()) {
            foreach ($moderators as $moderator) {
                if ($moderator->Email) {
                    $email = ForumNotifyModeratorEmail::create();
                    $email->setPost($post);
                    $email->setThread($thread);
                    $email->setStartingThread($startingThread);
                    $email->setModerator($moderator);
                    $email->setTo($moderator->Email);
                    $email->send();
                }
            }
        }
    }


    /**
     * Return the Forbidden Words in this Forum
     */
    public function getForbiddenWords()
    {
        return $this->Parent()->dbObject('ForbiddenWords');
    }

    /**
     * This function filters $content by forbidden words, entered in forum holder.
     */
    public function filterLanguage(string $content): string
    {
        $words = $this->getForbiddenWords();
        if ($words != "") {
            $words = explode(",", $words);
            foreach ($words as $word) {
                $content = str_ireplace(trim($word), "*", $content);
            }
        }

        return $content;
    }


    /**
     * Get the link for the reply action
     *
     * @return string URL for the reply action
     */
    public function ReplyLink()
    {
        return self::join_links($this->Link(), 'reply', $this->urlParams['ID']);
    }


    public function reply()
    {
        $thread = $this->getForumThread();

        if (!$thread) {
            return $this->httpError(404);
        }

        $form = $this->PostMessageForm();
        $form->setThread($thread);

        return [
            'Thread' => $thread,
            'PostMessageForm' => $form,
            'Title' => DBField::create_field('HTMLText', _t('Forum.REPLYTO', 'Replying to: %s', $thread->Title))
        ];
    }


    /**
     * Show will get the selected thread to the user. Also increments the forums view count.
     */
    public function show()
    {
        $thread = $this->getForumThread();

        if (!$thread) {
            return $this->httpError(404);
        }

        // If there is not first post either the thread has been removed or thread if a banned spammer.
        if (!$thread->getFirstPost()) {
            $member = Security::getCurrentUser();

            if (!$this->canModerate($member)) {
                return $this->httpError(404);
            }
        }

        $thread->incNumViews();
        $posts = sprintf(_t('Forum.POSTTOTOPIC', "Posts to the %s topic"), $thread->Title);

        RSSFeed::linkToFeed($this->Link("rss") . '/thread/' . (int) $this->urlParams['ID'], $posts);

        return [
            'Thread' => $thread,
            'Title' => $thread->getEscapedTitle()
        ];
    }


    /**
     * Start topic action
     *
     * @return array Returns an array to render the start topic page
     */
    public function starttopic()
    {
        $topic = [
            'Subtitle' => DBField::create_field('HTMLText', _t('Forum.NEWTOPIC', 'Start a new topic')),
            'Abstract' => $this->data()->dbObject('ForumAbstract')
        ];

        return $topic;
    }

    /**
     * Get the forum title
     *
     * @return string Returns the forum title
     */
    public function getHolderSubtitle()
    {
        return $this->dbObject('Title');
    }

    /**
     * Get the currently viewed forum. Ensure that the user can access it.
     */
    public function getForumThread(): ?ForumThread
    {
        $id = $this->getRequest()->param('ID');
        $thread = $id ? ForumThread::get()->byID($id) : false;

        if (!$thread || !$thread->canView()) {
            return null;
        }

        return $thread;
    }

    /**
     * Delete an attachment
     *
     * @return bool
     */
    public function deleteAttachment()
    {
        $request = $this->getRequest();

        // Check CSRF token
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        $id = $request->param('ID');

        if (!$id) {
            return $this->httpError(400);
        }

        $file = PostAttachment::get()->byID($id);

        if ($file && $file->canDelete()) {
            $file->delete();

            return $this->redirectBack();
        }

        return $this->httpError(404);
    }


    /**
     * Edit post action
     *
     * @return array Returns an array to render the edit post page
     */
    public function edit()
    {
        return [
            'Subtitle' => _t('Forum.EDITPOST', 'Edit post')
        ];
    }

    /**
     * Get the post edit form if the user has the necessary permissions
     *
     * @return Form
     */
    public function EditForm()
    {
        $id = $this->getRequest()->param('ID');

        if (!$id) {
            return $this->httpError(404);
        }

        $post = Post::get()->byID($id);

        return $this->PostMessageForm(false, $post);
    }


    /**
     * Delete a post via the url.
     *
     * @return bool
     */
    public function deletePost()
    {
        $request = $this->getRequest();

        // Check CSRF token
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        $id = $request->param('ID');

        if (!$id) {
            return $this->httpError(400);
        }

        $post = Post::get()->byID($id);

        if (!$post) {
            return $this->httpError(404);
        }

        if (!$post->canDelete()) {
            return $this->httpError(403);
        }

        $thread = $post->Thread();
        $post->delete();

        $remainingPosts = Post::get()->filter('ThreadID', $thread->ID)->count();

        if ($remainingPosts === 0) {
            $thread->delete();

            return $this->redirect($this->Link());
        }

        return $this->redirectBack();
    }


    /**
     * Delete an entire thread and all its posts.
     *
     * Requires moderator permissions and a valid security token.
     */
    public function deletethread()
    {
        $request = $this->getRequest();

        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        $id = $request->param('ID');

        if (!$id) {
            return $this->httpError(400);
        }

        $thread = ForumThread::get()->byID($id);

        if (!$thread) {
            return $this->httpError(404);
        }

        if (!$thread->canDelete()) {
            return $this->httpError(403);
        }

        $currentUser = Security::getCurrentUser();

        Injector::inst()->get(LoggerInterface::class)->info(sprintf(
            'Deleted thread "%s" (#%d), by moderator %s (#%d)',
            $thread->Title,
            $thread->ID,
            $currentUser->Email ?? 'Unknown',
            $currentUser->ID ?? 0
        ));

        $thread->delete();

        return ($request->isAjax()) ? true : $this->redirect($this->Link());
    }


    /**
     * Returns a tokenised URL for deleting the current thread, if the user can moderate.
     */
    public function DeleteThreadLink(): ?string
    {
        $thread = $this->getForumThread();

        if (!$thread || !$thread->canDelete()) {
            return null;
        }

        $url = Controller::join_links($this->Link('deletethread'), $thread->ID);

        return SecurityToken::inst()->addToUrl($url);
    }


    /**
     * Forum Admin Features form.
     * Handles the dropdown to select the new forum category and the checkbox for stickyness
     *
     * @return Form
     */
    public function AdminFormFeatures()
    {
        if (!$this->canModerate()) {
            return;
        }

        $id = (isset($this->urlParams['ID'])) ? $this->urlParams['ID'] : false;

        $form = AdminActionsForm::create($this, 'AdminFormFeatures');

        // need this id wrapper since the form method is called on save as
        // well and needs to return a valid form object
        if ($id) {
            $thread = ForumThread::get()->byID($id);

            if ($thread && $thread->canView()) {
                $form->loadDataFrom($thread);
            }
        }

        $this->extend('updateAdminFormFeatures', $form);

        return $form;
    }
}
