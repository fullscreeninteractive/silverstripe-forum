<?php

namespace FullscreenInteractive\SilverStripe\Forum\Form;

use FullscreenInteractive\SilverStripe\Forum\Interfaces\PostContentParserInterface;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThreadSubscription;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

class PostMessageForm extends Form
{
    protected ?Post $post = null;

    protected ?ForumThread $thread = null;

    public function __construct($controller, $name)
    {
        $fields = FieldList::create([
            TextField::create("Title", _t('Forum.FORUMTHREADTITLE', 'Title')),
            TextareaField::create("Content", _t('Forum.FORUMREPLYCONTENT', 'Message')),
            HiddenField::create('ThreadID', 'ThreadID'),
            HiddenField::create('ID', 'ID'),
            CheckboxField::create(
                "TopicSubscription",
                _t('Forum.SUBSCRIBETOPIC', 'Subscribe to this topic (Receive notifications when a reply is added)')
            )
        ]);

        $parserClass = Post::config()->get('post_content_parser');
        $parser = Injector::inst()->get($parserClass);

        if ($parser instanceof PostContentParserInterface) {
            $fields->insertAfter(
                "Content",
                LiteralField::create("ContentHelp", $parser->getSupportingHelpText()->getValue())
            );
        }

        if ($controller->data()->canAttach()) {
            $fields->insertAfter("Content", FileField::create("Attachment", _t('Forum.ATTACH', 'Attach file')));
        }

        $actions = FieldList::create([
            FormAction::create("doPostMessageForm", _t('Forum.REPLYFORMPOST', 'Post'))
        ]);

        $required = RequiredFieldsValidator::create(["Title", "Content"]);

        parent::__construct($controller, $name, $fields, $actions, $required);

        // Default "Subscribe to this topic" to checked
        $this->loadDataFrom(['TopicSubscription' => 1]);
    }


    public function setThread(?ForumThread $thread = null)
    {
        $this->loadDataFrom([
            'ThreadID' => $thread ? $thread->ID : null,
            'Title' => $thread ? $thread->Title : null,
        ]);

        $this->thread = $thread;

        // disable the title field
        $titleField = $this->fields->fieldByName('Title');

        if ($titleField) {
            $titleField->setDisabled(true);
        }

        if ($thread) {
            $member = Security::getCurrentUser();
            $isSubscribed = $member && ForumThreadSubscription::singleton()->isSubscribed($thread->ID, $member->ID);

            if ($isSubscribed) {
                $this->loadDataFrom(['TopicSubscription' => 1]);
            }
        }

        return $this;
    }


    public function setPost(?Post $post = null)
    {
        $this->loadDataFrom($post);

        $this->post = $post;

        $member = Security::getCurrentUser();

        if ($post && $post->ThreadID && $member) {
            $isSubscribed = ForumThreadSubscription::singleton()->isSubscribed($post->ThreadID, $member->ID);

            if ($isSubscribed) {
                $this->loadDataFrom(['TopicSubscription' => 1]);
            }
        }

        if (!$post->isFirstPost() || $post->ThreadID) {
            $titleField = $this->fields->fieldByName('Title');

            if ($titleField) {
                $titleField->setDisabled(true);
            }
        }

        if ($post->Attachments()->exists()) {
            $attachments = sprintf(
                "<div id=\"CurrentAttachments\"><h4>%s</h4><ul>",
                _t('Forum.CURRENTATTACHMENTS', 'Current Attachments')
            );

            // An instance of the security token
            $token = SecurityToken::inst();

            foreach ($post->Attachments() as $attachment) {
                // Generate a link properly, since it requires a security token
                $attachmentLink = $this->controller->Link('deleteattachment', $attachment->ID);
                $attachmentLink = $token->addToUrl($attachmentLink);

                $attachments .= sprintf(
                    "<li class='attachment-%d'>%s [<a href='%s' rel='%d' class='deleteAttachment'>%s</a>]</li>",
                    $attachment->ID,
                    $attachment->Name,
                    $attachmentLink,
                    $attachment->ID,
                    _t('Forum.REMOVE', 'remove')
                );
            }

            $attachments .= "</ul></div>";

            $this->fields->push(LiteralField::create('CurrentAttachments', $attachments));
        }

        return $this;
    }


    /**
     * Post a message to the forum. This method is called whenever you want to make a
     * new post or edit an existing post on the forum
     */
    public function doPostMessageForm($data, $form)
    {
        $member = Security::getCurrentUser();

        // Allows interception of a Member posting content to perform some action before the post is made.
        $this->extend('beforePostMessage', $data, $member);

        $content = (isset($data['Content'])) ? $this->controller->filterLanguage($data["Content"]) : "";
        $title = (isset($data['Title'])) ? $this->controller->filterLanguage($data["Title"]) : false;

        $thread = false;
        $startingThread = false;

        if (isset($data['ThreadID']) && $data['ThreadID']) {
            $thread = ForumThread::get()->byID($data['ThreadID']);

            if (!$thread || !$thread->canView()) {
                return $this->controller->redirectBack();
            }
        }

        // If this is a simple edit the post then handle it here. Look up the correct post,
        // make sure we have edit rights to it then update the post
        $post = false;

        if (isset($data['ID']) && $data['ID']) {
            $post = Post::get()->byID($data['ID']);

            if (!$post || !$post->canEdit()) {
                return $this->controller->redirectBack();
            }

            if ($post && $post->isFirstPost()) {
                if ($title) {
                    $thread->Title = $title;
                }
            }
        }


        // Creating new thread
        if (!$thread && !$this->controller->canPost()) {
            return $this->controller->redirectBack();
        }

        // Replying to existing thread
        if ($thread && !$post && !$thread->canPost()) {
            return $this->controller->redirectBack();
        }

        // Editing existing post
        if ($thread && $post && !$post->canEdit()) {
            return $this->controller->redirectBack();
        }

        if (!$thread) {
            $thread = ForumThread::create();
            $thread->AuthorID = $member->ID;
            $thread->ForumID = $this->controller->ID;

            $startingThread = true;

            if ($title) {
                $thread->Title = $title;
            }
        }

        // from now on the user has the correct permissions. save the current thread settings
        $thread->write();

        if (!$post || !$post->canEdit()) {
            $post = Post::create();
        }

        $form->saveInto($post);
        $post->ForumID = $thread->ForumID;
        $post->AuthorID = ($member) ? $member->ID : 0;
        $post->ThreadID = $thread->ID;
        $post->Content = $content;
        $post->write();

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
            $subscriptions = ForumThreadSubscription::get()->filter([
                'ThreadID' => $thread->ID,
                'MemberID' => $member->ID
            ]);

            foreach ($subscriptions as $subscription) {
                $subscription->delete();
            }
        }

        // Send any notifications that need to be sent
        ForumThreadSubscription::notify($post);

        // Send any notifications to moderators of the forum
        if (Forum::config()->get('notify_moderators')) {
            $this->controller->notifyModerators($post, $thread, $startingThread);
        }

        return $this->controller->redirect($post->Link());
    }
}
