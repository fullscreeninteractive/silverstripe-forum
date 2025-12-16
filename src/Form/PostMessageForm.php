<?php

namespace FullscreenInteractive\SilverStripe\Forum\Form;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Security\SecurityToken;

class PostMessageForm extends Form
{
    protected ?Post $post = null;

    public function __construct($controller, $name)
    {
        $fields = FieldList::create([
            TextField::create("Title", _t('Forum.FORUMTHREADTITLE', 'Title')),
            TextareaField::create("Content", _t('Forum.FORUMREPLYCONTENT', 'Content')),
            HiddenField::create('ThreadID', 'ThreadID'),
            HiddenField::create('ID', 'ID'),
            CheckboxField::create(
                "TopicSubscription",
                _t('Forum.SUBSCRIBETOPIC', 'Subscribe to this topic (Receive email notifications when a new reply is added)')
            )
        ]);

        if ($controller->data()->canAttach()) {
            $fields->insertAfter("Content", FileField::create("Attachment", _t('Forum.ATTACH', 'Attach file')));
        }

        $actions = FieldList::create([
            FormAction::create("doPostMessageForm", _t('Forum.REPLYFORMPOST', 'Post'))
        ]);

        $required = RequiredFieldsValidator::create(["Title", "Content"]);

        parent::__construct($controller, $name, $fields, $actions, $required);
    }


    public function setPost(Post $post)
    {
        $this->loadDataFrom($post);

        $this->post = $post;

        if (!$post->isFirstPost() || $post->ThreadID) {
            $this->fields->makeFieldReadonly('Title');
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
}
