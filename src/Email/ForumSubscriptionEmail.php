<?php

namespace FullscreenInteractive\SilverStripe\Forum\Email;

use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThreadSubscription;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;

class ForumSubscriptionEmail extends Email
{
    private static string $from = '';

    private Post $post;

    private ForumThreadSubscription $subscription;

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
        $from = self::config()->get('from');

        if (!$from) {
            $from = Email::config()->get('admin_email');
        }

        $this->setFrom($from);
        $this->setTo($this->subscription->Member()->Email);
        $this->setSubject(_t('Post.NEWREPLY', 'New reply for {title}', [
            'title' => $this->post->Title,
        ]));
        $this->setHTMLTemplate('email/ForumMember_TopicNotification');
        $this->setData($this->post);
        $this->addData('Nickname', $this->subscription->Member()->Nickname);
        $this->addData('UnsubscribeLink', sprintf(
            '%s%s/unsubscribe/%d',
            Director::absoluteBaseURL(),
            $this->post->Thread()->Forum()->Link(),
            $this->post->ID
        ));

        parent::send();
    }
}
