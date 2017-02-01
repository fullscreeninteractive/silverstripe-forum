<?php

namespace SilverStripe\Forum\Tests;

use SilverStripe\Control\Session;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\FunctionalTest;
use ForumThread_Subscription;
use ForumThread;
use Post;

/**
 * @todo Write some more complex tests for testing the can*() functionality
 */
class ForumThreadTest extends FunctionalTest
{

    static $fixture_file = "forum/tests/ForumTest.yml";

    // fixes permission issues with these tests, we don't need to test versioning anyway.
    // without this, SiteTree::canView() would always return false even though CanViewType == Anyone.
    static $use_draft_site = true;

    public function testGetNumPosts()
    {
        $thread = $this->objFromFixture("ForumThread", "Thread1");

        $this->assertEquals(17, $thread->getNumPosts());
    }

    public function testIncViews()
    {
        $thread = $this->objFromFixture("ForumThread", "Thread1");

        // clear session
        Session::clear('ForumViewed-'.$thread->ID);

        $this->assertEquals($thread->NumViews, '10');

        $thread->incNumViews();

        $this->assertEquals($thread->NumViews, '11');
    }

    public function testGetLatestPost()
    {
        $thread = $this->objFromFixture("ForumThread", "Thread1");

        $this->assertEquals($thread->getLatestPost()->Content, "This is the last post to a long thread");
    }

    public function testGetFirstPost()
    {
        $thread = $this->objFromFixture("ForumThread", "Thread1");

        $this->assertEquals($thread->getFirstPost()->Content, "This is my first post");
    }

    public function testSubscription()
    {
        $thread = $this->objFromFixture("ForumThread", "Thread1");
        $thread2 = $this->objFromFixture("ForumThread", "Thread2");

        $member = $this->objFromFixture("SilverStripe\\Security\\Member", "test1");
        $member2 = $this->objFromFixture("SilverStripe\\Security\\Member", "test2");

        $this->assertTrue(ForumThread_Subscription::already_subscribed($thread->ID, $member->ID));
        $this->assertTrue(ForumThread_Subscription::already_subscribed($thread->ID, $member2->ID));

        $this->assertFalse(ForumThread_Subscription::already_subscribed($thread2->ID, $member->ID));
        $this->assertFalse(ForumThread_Subscription::already_subscribed($thread2->ID, $member2->ID));
    }

    public function testOnBeforeDelete()
    {
        $thread = new ForumThread();
        $thread->write();

        $post = new Post();
        $post->ThreadID = $thread->ID;
        $post->write();

        $postID = $post->ID;

        $thread->delete();

        $this->assertFalse(DataObject::get_by_id('Post', $postID));
        $this->assertFalse(DataObject::get_by_id('ForumThread', $thread->ID));
    }

    public function testPermissions()
    {
        $member = $this->objFromFixture('SilverStripe\\Security\\Member', 'test1');
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
        $member = $this->objFromFixture('SilverStripe\\Security\\Member', 'moderator');
        $member->logIn();

        $this->assertFalse($disabledforum->canPost());
        $this->assertTrue($disabledforum->canView());
        $this->assertTrue($disabledforum->canModerate());
    }
}
