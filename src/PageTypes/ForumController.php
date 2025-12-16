<?php

namespace FullscreenInteractive\SilverStripe\Forum\PageTypes;

use FullscreenInteractive\SilverStripe\Forum\Email\ForumNotifyModeratorEmail;
use FullscreenInteractive\SilverStripe\Forum\Form\AdminActionsForm;
use FullscreenInteractive\SilverStripe\Forum\Form\PostMessageForm;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use PageController;
use SilverStripe\View\Requirements;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThreadSubscription;
use FullscreenInteractive\SilverStripe\Forum\Model\PostAttachment;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\RSS\RSSFeed;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationException;
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
            $member->LastViewed = date("Y-m-d H:i:s");
            $member->write();
        }

        // Set the back url
        if (isset($_SERVER['REQUEST_URI'])) {
            $this->request->getSession()->set('BackURL', $_SERVER['REQUEST_URI']);
        } else {
            $this->request->getSession()->set('BackURL', $this->Link());
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

        if ($member && !ForumThreadSubscription::singleton()->isSubscribed($this->urlParams['ID'], $member->ID)) {
            $obj = new ForumThreadSubscription();
            $obj->ThreadID = (int) $this->urlParams['ID'];
            $obj->MemberID = $member->ID;
            $obj->LastSent = date("Y-m-d H:i:s");
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

        $unsubscribed = false;

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
                $author->SuspendedUntil = date('Y-m-d', strtotime('+99 years', DBDatetime::now()->Format('U')));
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


    public function ban(SS_HTTPRequest $r)
    {
        if (!$r->param('ID')) {
            return $this->httpError(404);
        }
        if (!$this->canModerate()) {
            return $this->httpError(403);
        }

        $member = Member::get()->byID($r->param('ID'));
        if (!$member || !$member->exists()) {
            return $this->httpError(404);
        }

        $member->ForumStatus = 'Banned';
        $member->write();

        // Log event
        $currentUser = Member::currentUser();
        SS_Log::log(sprintf(
            'Banned member %s (#%d), by moderator %s (#%d)',
            $member->Email,
            $member->ID,
            $currentUser->Email,
            $currentUser->ID
        ), SS_Log::NOTICE);

        return ($r->isAjax()) ? true : $this->redirectBack();
    }

    public function ghost(SS_HTTPRequest $r)
    {
        if (!$r->param('ID')) {
            return $this->httpError(400);
        }
        if (!$this->canModerate()) {
            return $this->httpError(403);
        }

        $member = Member::get()->byID($r->param('ID'));
        if (!$member || !$member->exists()) {
            return $this->httpError(404);
        }

        $member->ForumStatus = 'Ghost';
        $member->write();

        // Log event
        $currentUser = Member::currentUser();
        SS_Log::log(sprintf(
            'Ghosted member %s (#%d), by moderator %s (#%d)',
            $member->Email,
            $member->ID,
            $currentUser->Email,
            $currentUser->ID
        ), SS_Log::NOTICE);

        return ($r->isAjax()) ? true : $this->redirectBack();
    }

    /**
     * Get posts to display. This method assumes an URL parameter "ID" which contains the thread ID.
     * @param string sortDirection The sort order direction, either ASC for ascending (default) or DESC for descending
     * @return DataObjectSet Posts
     */
    public function Posts($sortDirection = "ASC")
    {
        $numPerPage = Forum::$posts_per_page;

        $posts = Post::get()
            ->filter('ThreadID', $this->urlParams['ID'])
            ->sort('Created', $sortDirection);

        if (isset($_GET['showPost']) && !isset($_GET['start'])) {
            $postIDList = clone $posts;
            $postIDList = $postIDList->select('ID')->toArray();

            if ($postIDList->exists()) {
                $foundPos = array_search($_GET['showPost'], $postIDList);
                $_GET['start'] = floor($foundPos / $numPerPage) * $numPerPage;
            }
        }

        if (!isset($_GET['start'])) {
            $_GET['start'] = 0;
        }

        $member = Security::getCurrentUser();

        $posts = $posts->exclude([
            'Author.ForumStatus' => 'Banned'
        ]);

        if ($member) {
            $posts = $posts->exclude(array(
                'Author.ForumStatus' => 'Ghost',
                'Author.ID:not' => $member->ID
            ));
        } else {
            $posts = $posts->exclude(array(
                'Author.ForumStatus' => 'Ghost'
            ));
        }

        $paginated = new PaginatedList($posts, $_GET);
        $paginated->setPageLength(Forum::$posts_per_page);
        return $paginated;
    }


    /**
     * Section for dealing with reply / edit / create threads form
     */
    public function PostMessageForm(bool $addMode = false, ?Post $post = null): ?PostMessageForm
    {
        $thread = false;
        $id = $this->getRequest()->param('ID');
        $thread = $post ? $post->Thread() : ($id && is_numeric($id) ? ForumThread::get()->byID($id) : false);

        // Check permissions
        $messageSet = [
            'default' => _t('Forum.LOGINTOPOST', 'You\'ll need to login before you can post to that forum. Please do so below.'),
            'alreadyLoggedIn' => _t(
                'Forum.LOGINTOPOSTLOGGEDIN',
                'I\'m sorry, but you can\'t post to this forum until you\'ve logged in.'
                    . 'If you want to log in as someone else, do so below. If you\'re logged in and you still can\'t post, you don\'t have the correct permissions to post.'
            ),
            'logInAgain' => _t('Forum.LOGINTOPOSTAGAIN', 'You have been logged out of the forums. If you would like to log in again to post, enter a username and password below.'),
        ];

        // Creating new thread
        if ($addMode && !$this->canPost()) {
            Security::permissionFailure($this, $messageSet);
            return null;
        }

        // Replying to existing thread
        if (!$addMode && !$post && $thread && !$thread->canPost()) {
            Security::permissionFailure($this, $messageSet);
            return null;
        }

        // Editing existing post
        if (!$addMode && $post && !$post->canEdit()) {
            Security::permissionFailure($this, $messageSet);
            return null;
        }

        $form = PostMessageForm::create($this, 'PostMessageForm');
        $form->setPost($post);

        $this->extend('updatePostMessageForm', $form, $post);

        return $form;
    }

    /**
     * Post a message to the forum. This method is called whenever you want to make a
     * new post or edit an existing post on the forum
     */
    public function doPostMessageForm($data, $form)
    {
        $member = Security::getCurrentUser();

        //Allows interception of a Member posting content to perform some action before the post is made.
        $this->extend('beforePostMessage', $data, $member);

        $content = (isset($data['Content'])) ? $this->filterLanguage($data["Content"]) : "";
        $title = (isset($data['Title'])) ? $this->filterLanguage($data["Title"]) : false;

        // If a thread id is passed append the post to the thread. Otherwise create
        // a new thread
        $thread = false;

        if (isset($data['ThreadID'])) {
            $thread = ForumThread::get()->byID($data['ThreadID']);
        }

        // If this is a simple edit the post then handle it here. Look up the correct post,
        // make sure we have edit rights to it then update the post
        $post = false;
        if (isset($data['ID'])) {
            $post = Post::get()->byID($data['ID']);

            if ($post && $post->isFirstPost()) {
                if ($title) {
                    $thread->Title = $title;
                }
            }
        }


        // Check permissions
        $messageSet = [
            'default' => _t('Forum.LOGINTOPOST', 'You\'ll need to login before you can post to that forum. Please do so below.'),
            'alreadyLoggedIn' => _t('Forum.NOPOSTPERMISSION', 'I\'m sorry, but you do not have permission post to this forum.'),
            'logInAgain' => _t('Forum.LOGINTOPOSTAGAIN', 'You have been logged out of the forums. If you would like to log in again to post, enter a username and password below.'),
        ];

        // Creating new thread
        if (!$thread && !$this->canPost()) {
            Security::permissionFailure($this, $messageSet);
            return false;
        }

        // Replying to existing thread
        if ($thread && !$post && !$thread->canPost()) {
            Security::permissionFailure($this, $messageSet);
            return false;
        }

        // Editing existing post
        if ($thread && $post && !$post->canEdit()) {
            Security::permissionFailure($this, $messageSet);
            return false;
        }

        if (!$thread) {
            $thread = new ForumThread();
            $thread->ForumID = $this->ID;
            if ($title) {
                $thread->Title = $title;
            }
            $starting_thread = true;
        }

        // Upload and Save all files attached to the field
        // Attachment will always be blank, If they had an image it will be at least in Attachment-0
        //$attachments = new DataObjectSet();
        $attachments = new ArrayList();

        if (!empty($data['Attachment-0']) && !empty($data['Attachment-0']['tmp_name'])) {
            $id = 0;
            //
            // @todo this only supports ajax uploads. Needs to change the key (to simply Attachment).
            //
            while (isset($data['Attachment-' . $id])) {
                $image = $data['Attachment-' . $id];

                if ($image && !empty($image['tmp_name'])) {
                    $file = PostAttachment::create();
                    $file->OwnerID = $member->ID;
                    $folder = ForumHolder::config()->get('attachments_folder');

                    try {
                        $upload = Upload::create()->loadIntoFile($image, $file, $folder);
                        $file->write();
                        $attachments->push($file);
                    } catch (ValidationException $e) {
                        $message = _t('Forum.UPLOADVALIDATIONFAIL', 'Unallowed file uploaded. Please only upload files of the following: ');
                        $message .= implode(', ', File::config()->get('allowed_extensions'));
                        $form->sessionMessage($message, ValidationResult::TYPE_ERROR);
                        return $this->redirectBack();
                    }
                }

                $id++;
            }
        }

        // from now on the user has the correct permissions. save the current thread settings
        $thread->write();

        if (!$post || !$post->canEdit()) {
            $post = Post::create();
            $post->AuthorID = ($member) ? $member->ID : 0;
            $post->ThreadID = $thread->ID;
        }

        $post->ForumID = $thread->ForumID;
        $post->Content = $content;
        $post->write();


        if ($attachments) {
            foreach ($attachments as $attachment) {
                $attachment->PostID = $post->ID;
                $attachment->write();
            }
        }

        // Add a topic subscription entry if required
        $isSubscribed = ForumThreadSubscription::singleton()->isSubscribed($thread->ID, $member->ID);

        if (isset($data['TopicSubscription'])) {
            if (!$isSubscribed) {
                // Create a new topic subscription for this member
                $obj = ForumThreadSubscription::create();
                $obj->ThreadID = $thread->ID;
                $obj->MemberID = $member->ID;
                $obj->write();
            }
        } elseif ($isSubscribed) {
            // See if the member wanted to remove themselves
            ForumThreadSubscription::get()->filter([
                'ThreadID' => $thread->ID,
                'MemberID' => $member->ID
            ])->delete();
        }

        // Send any notifications that need to be sent
        ForumThreadSubscription::notify($post);

        // Send any notifications to moderators of the forum
        if (Forum::config()->get('notify_moderators')) {
            if (isset($starting_thread) && $starting_thread) {
                $this->notifyModerators($post, $thread, true);
            } else {
                $this->notifyModerators($post, $thread, false);
            }
        }

        return $this->redirect($post->Link());
    }

    /**
     * Send email to moderators notifying them the thread has been created or post added/edited.
     */
    public function notifyModerators(Post $post, ForumThread $thread, bool $starting_thread = false)
    {
        $moderators = $this->Moderators();
        if ($moderators && $moderators->exists()) {
            foreach ($moderators as $moderator) {
                if ($moderator->Email) {
                    $email = ForumNotifyModeratorEmail::create();
                    $email->setPost($post);
                    $email->setThread($thread);
                    $email->setStartingThread($starting_thread);
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
     *
     * @param String $content (it can be Post Content or Post Title)
     * @return String $content (filtered string)
     */
    public function filterLanguage($content)
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

    /**
     * Show will get the selected thread to the user. Also increments the forums view count.
     */
    public function show()
    {
        $thread = $this->getForumThread();

        if (!$thread) {
            return $this->httpError(404);
        }

        //If there is not first post either the thread has been removed or thread if a banned spammer.
        if (!$thread->getFirstPost()) {
            // don't hide the post for logged in admins or moderators
            $member = Security::getCurrentUser();

            if (!$this->canModerate($member)) {
                return $this->httpError(404);
            }
        }

        $thread->incNumViews();

        $posts = sprintf(_t('Forum.POSTTOTOPIC', "Posts to the %s topic"), $thread->Title);

        RSSFeed::linkToFeed($this->Link("rss") . '/thread/' . (int) $this->urlParams['ID'], $posts);

        $title = Convert::raw2xml($thread->Title) . ' &raquo; ' . $title;
        $field = DBField::create_field('HTMLText', $title);

        return [
            'Thread' => $thread,
            'Title' => $field
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

        $post->delete();

        return $this->redirectBack();
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
