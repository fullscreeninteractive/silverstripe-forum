<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests\Email;

use FullscreenInteractive\SilverStripe\Forum\Email\ForumNotifyModeratorEmail;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use SilverStripe\Control\Email\Email;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class ForumNotifyModeratorEmailTest extends SapphireTest
{
    protected $usesDatabase = true;

    private Forum $forum;
    private ForumThread $thread;
    private Post $post;
    private Member $moderator;
    private Member $author;

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

        $this->author = Member::create();
        $this->author->Email = 'author@example.com';
        $this->author->Nickname = 'Author';
        $this->author->write();

        $this->post = Post::create();
        $this->post->Content = 'Some post content';
        $this->post->ThreadID = $this->thread->ID;
        $this->post->ForumID = $this->forum->ID;
        $this->post->AuthorID = $this->author->ID;
        $this->post->write();

        $this->moderator = Member::create();
        $this->moderator->Email = 'moderator@example.com';
        $this->moderator->Nickname = 'Moderator';
        $this->moderator->write();
    }

    public function testSendNewPostNotification(): void
    {
        $email = ForumNotifyModeratorEmail::create();
        $email->setPost($this->post);
        $email->setThread($this->thread);
        $email->setModerator($this->moderator);
        $email->setTo($this->moderator->Email);
        $email->send();

        $this->assertEmailSent(
            $this->moderator->Email,
            'admin@example.com',
            'New post "Test Thread" in forum [General Discussion]',
        );
    }

    public function testSendNewThreadNotification(): void
    {
        $email = ForumNotifyModeratorEmail::create();
        $email->setPost($this->post);
        $email->setThread($this->thread);
        $email->setStartingThread(true);
        $email->setModerator($this->moderator);
        $email->setTo($this->moderator->Email);
        $email->send();

        $this->assertEmailSent(
            $this->moderator->Email,
            'admin@example.com',
            'New thread "Test Thread" in forum [General Discussion]',
        );
    }

    public function testEmailBodyContainsPostContent(): void
    {
        $email = ForumNotifyModeratorEmail::create();
        $email->setPost($this->post);
        $email->setThread($this->thread);
        $email->setModerator($this->moderator);
        $email->setTo($this->moderator->Email);
        $email->send();

        $sent = $this->findEmail($this->moderator->Email);

        $this->assertNotNull($sent, 'Moderator notification email should have been sent');
        $this->assertStringContainsString('Some post content', $sent['HtmlContent']);
    }

    public function testEmailBodyContainsModerateLink(): void
    {
        $email = ForumNotifyModeratorEmail::create();
        $email->setPost($this->post);
        $email->setThread($this->thread);
        $email->setModerator($this->moderator);
        $email->setTo($this->moderator->Email);
        $email->send();

        $sent = $this->findEmail($this->moderator->Email);

        $this->assertNotNull($sent, 'Moderator notification email should have been sent');
        $this->assertStringContainsString('Moderate the thread', $sent['HtmlContent']);
    }
}
