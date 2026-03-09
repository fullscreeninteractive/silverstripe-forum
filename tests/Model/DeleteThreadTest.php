<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests\Model;

use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;

class DeleteThreadTest extends FunctionalTest
{
    protected static $fixture_file = [
        './tests/fixtures.yml',
    ];

    protected static $use_draft_site = true;

    public function testRejectsRequestWithoutSecurityToken()
    {
        SecurityToken::enable();

        $moderator = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($moderator);

        $thread = $this->objFromFixture(ForumThread::class, 'Thread2');
        $forum = $this->objFromFixture(Forum::class, 'general');

        $response = $this->get($forum->RelativeLink('deletethread/' . $thread->ID));
        $this->assertEquals(400, $response->getStatusCode());

        $this->assertNotNull(ForumThread::get()->byID($thread->ID), 'Thread should still exist');
    }

    public function testRejectsNonModerator()
    {
        SecurityToken::disable();

        $user = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($user);

        $thread = $this->objFromFixture(ForumThread::class, 'Thread2');
        $forum = $this->objFromFixture(Forum::class, 'general');

        $response = $this->get($forum->RelativeLink('deletethread/' . $thread->ID));
        $this->assertEquals(403, $response->getStatusCode());

        $this->assertNotNull(ForumThread::get()->byID($thread->ID), 'Thread should still exist');
    }

    public function testRejectsRequestWithoutThreadId()
    {
        SecurityToken::disable();

        $moderator = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($moderator);

        $forum = $this->objFromFixture(Forum::class, 'general');

        $response = $this->get($forum->RelativeLink('deletethread'));
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testReturns404ForNonExistentThread()
    {
        SecurityToken::disable();

        $moderator = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($moderator);

        $forum = $this->objFromFixture(Forum::class, 'general');

        $response = $this->get($forum->RelativeLink('deletethread/999999'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testModeratorCanDeleteThread()
    {
        SecurityToken::disable();

        $moderator = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($moderator);

        $thread = $this->objFromFixture(ForumThread::class, 'Thread2');
        $forum = $this->objFromFixture(Forum::class, 'general');
        $threadID = $thread->ID;

        $postsBefore = Post::get()->filter('ThreadID', $threadID);
        $this->assertGreaterThan(0, $postsBefore->count(), 'Thread should have posts before deletion');
        $postIDs = $postsBefore->column('ID');

        $response = $this->get($forum->RelativeLink('deletethread/' . $threadID));
        $this->assertEquals(302, $response->getStatusCode(), 'Should redirect after successful deletion');

        $this->assertNull(ForumThread::get()->byID($threadID), 'Thread should be deleted');

        foreach ($postIDs as $postID) {
            $this->assertNull(Post::get()->byID($postID), "Post #{$postID} should be cascade-deleted");
        }
    }

    public function testAdminCanDeleteThread()
    {
        SecurityToken::disable();

        $admin = $this->objFromFixture(Member::class, 'admin');
        $this->logInAs($admin);

        $thread = $this->objFromFixture(ForumThread::class, 'Thread2');
        $forum = $this->objFromFixture(Forum::class, 'general');
        $threadID = $thread->ID;

        $response = $this->get($forum->RelativeLink('deletethread/' . $threadID));
        $this->assertEquals(302, $response->getStatusCode());

        $this->assertNull(ForumThread::get()->byID($threadID), 'Thread should be deleted by admin');
    }
}
