<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests;

use FullscreenInteractive\SilverStripe\Forum\Model\ForumThreadSubscription;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;

/**
 * @todo Write some more complex tests for testing the can*() functionality
 */
class ForumThreadTest extends FunctionalTest
{

    protected static $fixture_file = [
        'ForumTest.yml',
    ];

    // fixes permission issues with these tests, we don't need to test versioning anyway.
    // without this, SiteTree::canView() would always return false even though CanViewType == Anyone.
    protected static $use_draft_site = true;

    public function testGetNumPosts()
    {
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        $this->assertEquals(17, $thread->getNumPosts()->count());
    }

    public function testIncViews()
    {
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        // clear session
        $this->getRequest()->getSession()->clear('ForumViewed-' . $thread->ID);

        $this->assertEquals($thread->NumViews, '10');

        $thread->incNumViews();

        $this->assertEquals($thread->NumViews, '11');
    }

    public function testGetLatestPost()
    {
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        $this->assertEquals($thread->getLatestPost()->Content, "This is the last post to a long thread");
    }

    public function testGetFirstPost()
    {
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        $this->assertEquals($thread->getFirstPost()->Content, "This is my first post");
    }

    public function testSubscription()
    {
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");
        $thread2 = $this->objFromFixture(ForumThread::class, "Thread2");

        $member = $this->objFromFixture(Member::class, "test1");
        $member2 = $this->objFromFixture(Member::class, "test2");

        $this->assertTrue(ForumThreadSubscription::get()->filter([
            'ThreadID' => $thread->ID,
            'MemberID' => $member->ID
        ])->exists());
        $this->assertTrue(ForumThreadSubscription::get()->filter([
            'ThreadID' => $thread->ID,
            'MemberID' => $member2->ID
        ])->exists());

        $this->assertFalse(ForumThreadSubscription::get()->filter([
            'ThreadID' => $thread2->ID,
            'MemberID' => $member->ID
        ])->exists());
        $this->assertFalse(ForumThreadSubscription::get()->filter([
            'ThreadID' => $thread2->ID,
            'MemberID' => $member2->ID
        ])->exists());
    }

    public function testOnBeforeDelete()
    {
        $thread = ForumThread::create();
        $thread->write();

        $post = Post::create();
        $post->ThreadID = $thread->ID;
        $post->write();

        $postID = $post->ID;

        $thread->delete();

        $this->assertFalse(Post::get()->byID($postID));
        $this->assertFalse(ForumThread::get()->byID($thread->ID));
    }

    public function testPermissions()
    {
        $member = $this->objFromFixture('Member', 'test1');
        $this->session()->inst_set('loggedInAs', $member->ID);

        // read only thread. No one should be able to post to this (apart from the )
        $readonly = $this->objFromFixture('ForumThread', 'ReadonlyThread');
        $this->assertFalse($readonly->canPost());
        $this->assertTrue($readonly->canView());
        $this->assertFalse($readonly->canModerate());

        // normal thread. They can post to these
        $thread = $this->objFromFixture('ForumThread', 'Thread1');
        $this->assertTrue($thread->canPost());
        $this->assertTrue($thread->canView());
        $this->assertFalse($thread->canModerate());

        // normal thread in a read only
        $disabledforum = $this->objFromFixture('ForumThread', 'ThreadWhichIsInInheritedForum');
        $this->assertFalse($disabledforum->canPost());
        $this->assertFalse($disabledforum->canView());
        $this->assertFalse($disabledforum->canModerate());

        // Moderator can access threads nevertheless
        $member = $this->objFromFixture('Member', 'moderator');
        $member->logIn();

        $this->assertFalse($disabledforum->canPost());
        $this->assertTrue($disabledforum->canView());
        $this->assertTrue($disabledforum->canModerate());
    }
}
