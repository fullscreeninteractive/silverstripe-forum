<?php

namespace FullscreenInteractive\SilverStripe\Forum\Email;

use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThreadSubscription;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;

class ForumSubscriptionEmail extends Email
{
    private $post;
    private $subscription;

    public function setSubscription(ForumThreadSubscription $subscription)
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function setPost(Post $post)
    {
        $this->post = $post;

        return $this;
    }


    public function send(): void
    {
        $this->setFrom(Email::config()->get('admin_email'));
        $this->setTo($this->subscription->Member()->Email);
        $this->setSubject(_t('Post.NEWREPLY', 'New reply for {title}', array('title' => $this->post->Title)));
        $this->setTemplate('ForumMember_TopicNotification');
        $this->populateTemplate($this->subscription->Member());
        $this->populateTemplate($this->post);
        $this->populateTemplate([
            'UnsubscribeLink' => sprintf(
                '%s%s/unsubscribe/%d',
                Director::absoluteBaseURL(),
                $this->post->Thread()->Forum()->Link(),
                $this->post->ID
            )
        ]);

        parent::send();
    }
}
