<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests\Email;

use FullscreenInteractive\SilverStripe\Forum\Email\ForumSubscriptionEmail;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThreadSubscription;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use SilverStripe\Control\Email\Email;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class ForumSubscriptionEmailTest extends SapphireTest
{
    protected $usesDatabase = true;

    private Forum $forum;
    private ForumThread $thread;
    private Post $post;
    private Member $subscriber;
    private ForumThreadSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        Email::config()->set('admin_email', 'admin@example.com');

        $holder = ForumHolder::create();
        $holder->Title = 'Forums';
        $holder->write();
        $holder->publishRecursive();

        $this->forum = Forum::create();
        $this->forum->Title = 'General Discussion';
        $this->forum->ParentID = $holder->ID;
        $this->forum->CanViewType = 'Anyone';
        $this->forum->CanPostType = 'Anyone';
        $this->forum->write();
        $this->forum->publishRecursive();

        $this->thread = ForumThread::create();
        $this->thread->Title = 'Test Thread';
        $this->thread->ForumID = $this->forum->ID;
        $this->thread->write();

        $author = Member::create();
        $author->Email = 'author@example.com';
        $author->Nickname = 'Author';
        $author->write();

        $this->post = Post::create();
        $this->post->Content = 'A reply to the thread';
        $this->post->ThreadID = $this->thread->ID;
        $this->post->ForumID = $this->forum->ID;
        $this->post->AuthorID = $author->ID;
        $this->post->write();

        $this->subscriber = Member::create();
        $this->subscriber->Email = 'subscriber@example.com';
        $this->subscriber->Nickname = 'Subscriber';
        $this->subscriber->write();

        $this->subscription = ForumThreadSubscription::create();
        $this->subscription->ThreadID = $this->thread->ID;
        $this->subscription->MemberID = $this->subscriber->ID;
        $this->subscription->write();
    }

    public function testSendEmailToSubscriber(): void
    {
        $email = ForumSubscriptionEmail::create();
        $email->setSubscription($this->subscription);
        $email->setPost($this->post);
        $email->send();

        $this->assertEmailSent(
            $this->subscriber->Email,
            'admin@example.com',
        );
    }

    public function testEmailBodyContainsUnsubscribeLink(): void
    {
        $email = ForumSubscriptionEmail::create();
        $email->setSubscription($this->subscription);
        $email->setPost($this->post);
        $email->send();

        $sent = $this->findEmail($this->subscriber->Email);

        $this->assertNotNull($sent, 'Subscription email should have been sent');
        $this->assertStringContainsString(
            'unsubscribe/' . $this->post->ID,
            $sent['HtmlContent']
        );
    }
}
