<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests;

use FullscreenInteractive\SilverStripe\Forum\Model\ForumThreadSubscription;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

class ForumThreadTest extends FunctionalTest
{

    protected static $fixture_file = [
        './tests/fixtures.yml',
    ];

    protected static $use_draft_site = true;

    protected function setUp(): void
    {
        parent::setUp();
        Versioned::set_draft_site_secured(false);
    }

    public function testGetNumPosts()
    {
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        $this->assertEquals(17, $thread->getNumPosts());
    }

    public function testIncViews()
    {
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        // clear session
        $this->session()->clear('ForumViewed-' . $thread->ID);

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

        $this->assertNull(Post::get()->byID($postID));
        $this->assertNull(ForumThread::get()->byID($thread->ID));
    }

    public function testPermissions()
    {
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);

        // read only thread. No one should be able to post to this (apart from the )
        $readonly = $this->objFromFixture(ForumThread::class, 'ReadonlyThread');
        $this->assertFalse($readonly->canPost());
        $this->assertTrue($readonly->canView());
        $this->assertFalse($readonly->canModerate());

        // normal thread. They can post to these
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $this->assertTrue($thread->canPost());
        $this->assertTrue($thread->canView());
        $this->assertFalse($thread->canModerate());

        // normal thread in a read only
        $disabledforum = $this->objFromFixture(ForumThread::class, 'ThreadWhichIsInInheritedForum');
        $this->assertFalse($disabledforum->canPost());
        $this->assertFalse($disabledforum->canView());
        $this->assertFalse($disabledforum->canModerate());

        // Moderator can access threads nevertheless
        $member = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($member);

        $this->assertFalse($disabledforum->canPost());
        $this->assertTrue($disabledforum->canView());
        $this->assertTrue($disabledforum->canModerate());
    }

    public function testCanPermissionsAnonymous()
    {
        $this->logOut();

        // Thread1 is in 'general' forum (CanPostType: Anyone)
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $this->assertTrue($thread->canPost());
        $this->assertFalse($thread->canModerate());
        $this->assertFalse($thread->canEdit());
        $this->assertFalse($thread->canDelete());

        // ReadonlyThread is in 'general' but has IsReadOnly = true
        $readonly = $this->objFromFixture(ForumThread::class, 'ReadonlyThread');
        $this->assertFalse($readonly->canPost());
        $this->assertFalse($readonly->canModerate());

        // Thread in inherited forum (inherits NoOne post from holder)
        $inherited = $this->objFromFixture(ForumThread::class, 'ThreadWhichIsInInheritedForum');
        $this->assertFalse($inherited->canPost());
        $this->assertFalse($inherited->canModerate());
    }

    public function testCanPermissionsAdmin()
    {
        $admin = $this->objFromFixture(Member::class, 'admin');
        $this->logInAs($admin);

        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $this->assertTrue($thread->canView());
        $this->assertTrue($thread->canPost());
        $this->assertTrue($thread->canModerate());
        $this->assertTrue($thread->canEdit());
        $this->assertTrue($thread->canDelete());
    }

    public function testCanEditAndDeleteRequireModeration()
    {
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);

        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $this->assertFalse($thread->canEdit(), 'Normal member cannot edit threads');
        $this->assertFalse($thread->canDelete(), 'Normal member cannot delete threads');

        $moderator = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($moderator);
        $this->assertTrue($thread->canEdit(), 'Moderator can edit threads');
        $this->assertTrue($thread->canDelete(), 'Moderator can delete threads');
    }

    public function testCanCreateDelegatesToCanPost()
    {
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);

        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $this->assertTrue($thread->canCreate(), 'Can create when forum allows posting');

        $readonly = $this->objFromFixture(ForumThread::class, 'ReadonlyThread');
        $this->assertFalse($readonly->canCreate(), 'Cannot create in a readonly thread');
    }

    public function testCanPostThreadInNoPostingForum()
    {
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);

        // noPostingForum has CanPostType: NoOne
        $thread = $this->objFromFixture(ForumThread::class, 'ThreadWhichIsInReadonlyForum');
        $this->assertFalse($thread->canPost(), 'Cannot post in a NoOne forum');
        $this->assertFalse($thread->canModerate(), 'Regular member cannot moderate');
        $this->assertFalse($thread->canEdit(), 'Regular member cannot edit');
        $this->assertFalse($thread->canDelete(), 'Regular member cannot delete');
    }

    public function testModeratorPermissionsOnInheritedForum()
    {
        $moderator = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($moderator);

        $thread = $this->objFromFixture(ForumThread::class, 'ThreadWhichIsInInheritedForum');
        $this->assertTrue($thread->canView(), 'Moderator can view inherited-permission thread');
        $this->assertTrue($thread->canModerate(), 'Moderator can moderate inherited-permission thread');
        $this->assertTrue($thread->canEdit(), 'Moderator can edit inherited-permission thread');
        $this->assertTrue($thread->canDelete(), 'Moderator can delete inherited-permission thread');
        $this->assertFalse($thread->canPost(), 'Even moderators cannot post in a NoOne forum');
    }
}
