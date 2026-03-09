<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests\Model;

use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumController;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Control\Email\Email;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThreadSubscription;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\SecurityToken;

class ForumTest extends FunctionalTest
{

    protected static $fixture_file = [
        './tests/fixtures.yml',
    ];

    protected static $use_draft_site = true;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (ForumHolder::get() as $holder) {
            $holder->publishRecursive();
        }

        foreach (Forum::get() as $forum) {
            $forum->publishRecursive();
        }
    }

    public function testCanView()
    {
        // test viewing not logged in
        $this->logOut();

        $public = $this->objFromFixture(Forum::class, 'general');
        $private = $this->objFromFixture(Forum::class, 'loggedInOnly');
        $limited = $this->objFromFixture(Forum::class, 'limitedToGroup');
        $noposting = $this->objFromFixture(Forum::class, 'noPostingForum');
        $inherited = $this->objFromFixture(Forum::class, 'inheritedForum');

        $this->assertTrue($public->canView());
        $this->assertFalse($private->canView());
        $this->assertFalse($limited->canView());
        $this->assertTrue($noposting->canView());
        $this->assertFalse($inherited->canView());

        // try logging in a member
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);

        $this->assertTrue($public->canView());
        $this->assertTrue($private->canView());
        $this->assertFalse($limited->canView());
        $this->assertTrue($noposting->canView());
        $this->assertFalse($inherited->canView());

        // login as a person with access to restricted forum
        $member = $this->objFromFixture(Member::class, 'test2');
        $this->logInAs($member);

        $this->assertTrue($public->canView());
        $this->assertTrue($private->canView());
        $this->assertTrue($limited->canView());
        $this->assertTrue($noposting->canView());
        $this->assertFalse($inherited->canView());

        // Moderator should be able to view his own forums
        $member = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($member);

        $this->assertTrue($public->canView());
        $this->assertTrue($private->canView());
        $this->assertTrue($limited->canView());
        $this->assertTrue($noposting->canView());
        $this->assertTrue($inherited->canView());
    }

    public function testCanPost()
    {
        // test viewing not logged in
        $this->logOut();

        $public = $this->objFromFixture(Forum::class, 'general');
        $private = $this->objFromFixture(Forum::class, 'loggedInOnly');
        $limited = $this->objFromFixture(Forum::class, 'limitedToGroup');
        $noposting = $this->objFromFixture(Forum::class, 'noPostingForum');
        $inherited = $this->objFromFixture(Forum::class, 'inheritedForum');

        $this->assertTrue($public->canPost());
        $this->assertFalse($private->canPost());
        $this->assertFalse($limited->canPost());
        $this->assertFalse($noposting->canPost());
        $this->assertFalse($inherited->canPost());

        // try logging in a member
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);

        $this->assertTrue($public->canPost());
        $this->assertTrue($private->canPost());
        $this->assertFalse($limited->canPost());
        $this->assertFalse($noposting->canPost());
        $this->assertFalse($inherited->canPost());

        // login as a person with access to restricted forum
        $member = $this->objFromFixture(Member::class, 'test2');
        $this->logInAs($member);

        $this->assertTrue($public->canPost());
        $this->assertTrue($private->canPost());
        $this->assertTrue($limited->canPost());
        $this->assertFalse($noposting->canPost());
        $this->assertFalse($inherited->canPost());

        // Moderator should be able to view his own forums
        $member = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($member);

        $this->assertTrue($public->canPost());
        $this->assertTrue($private->canPost());
        $this->assertFalse($limited->canPost());
        $this->assertFalse($noposting->canPost());
        $this->assertFalse($inherited->canPost());
    }

    public function testSuspended()
    {
        $private = $this->objFromFixture(Forum::class, 'loggedInOnly');
        $limited = $this->objFromFixture(Forum::class, 'limitedToGroup');
        $inheritedForum_loggedInOnly = $this->objFromFixture(Forum::class, 'inheritedForum_loggedInOnly');
        DBDatetime::set_mock_now('2011-10-10 12:00:00');

        // try logging in a member suspendedexpired
        $suspendedexpired = $this->objFromFixture(Member::class, 'suspendedexpired');
        $this->assertFalse($suspendedexpired->IsSuspended());
        $this->logInAs($suspendedexpired);
        $this->assertTrue($private->canPost());
        $this->assertTrue($limited->canPost());
        $this->assertTrue($inheritedForum_loggedInOnly->canPost());

        // try logging in a member suspended
        $suspended = $this->objFromFixture(Member::class, 'suspended');
        $this->assertTrue($suspended->IsSuspended());
        $this->logInAs($suspended);
        $this->assertFalse($private->canPost());
        $this->assertFalse($limited->canPost());
        $this->assertFalse($inheritedForum_loggedInOnly->canPost());
    }

    public function testCanModerate()
    {
        // test viewing not logged in
        $this->logOut();

        $public = $this->objFromFixture(Forum::class, 'general');
        $private = $this->objFromFixture(Forum::class, 'loggedInOnly');
        $limited = $this->objFromFixture(Forum::class, 'limitedToGroup');
        $noposting = $this->objFromFixture(Forum::class, 'noPostingForum');
        $inherited = $this->objFromFixture(Forum::class, 'inheritedForum');

        $this->assertFalse($public->canModerate());
        $this->assertFalse($private->canModerate());
        $this->assertFalse($limited->canModerate());
        $this->assertFalse($noposting->canModerate());
        $this->assertFalse($inherited->canModerate());

        // try logging in a member
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);

        $this->assertFalse($public->canModerate());
        $this->assertFalse($private->canModerate());
        $this->assertFalse($limited->canModerate());
        $this->assertFalse($noposting->canModerate());
        $this->assertFalse($inherited->canModerate());

        // login as a person with access to restricted forum
        $member = $this->objFromFixture(Member::class, 'test2');
        $this->logInAs($member);

        $this->assertFalse($public->canModerate());
        $this->assertFalse($private->canModerate());
        $this->assertFalse($limited->canModerate());
        $this->assertFalse($noposting->canModerate());
        $this->assertFalse($inherited->canModerate());

        // Moderator should be able to view his own forums
        $member = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($member);

        $this->assertTrue($public->canModerate());
        $this->assertTrue($private->canModerate());
        $this->assertTrue($limited->canModerate());
        $this->assertTrue($noposting->canModerate());
        $this->assertTrue($inherited->canModerate());
    }

    public function testCanAttach()
    {
        $canAttach = $this->objFromFixture(Forum::class, 'general');
        $this->assertTrue($canAttach->canAttach());

        $noAttach = $this->objFromFixture(Forum::class, 'forum1cat2');
        $this->assertFalse($noAttach->canAttach());
    }

    public function testgetForbiddenWords()
    {
        $forum = $this->objFromFixture(Forum::class, "general");
        $controller = ForumController::create($forum);
        $this->assertEquals($controller->getForbiddenWords(), "shit,fuck");
    }

    public function testfilterLanguage()
    {
        $forum =  $this->objFromFixture(Forum::class, "general");
        $controller = ForumController::create($forum);
        $this->assertEquals($controller->filterLanguage('shit'), "*");

        $this->assertEquals($controller->filterLanguage('shit and fuck'), "* and *");

        $this->assertEquals($controller->filterLanguage('hello'), "hello");
    }

    public function testGetStickyTopics()
    {
        $forumWithSticky = $this->objFromFixture(Forum::class, "general");
        $stickies = $forumWithSticky->getStickyTopics();
        $this->assertEquals($stickies->Count(), '2');

        // TODO: Sorts by Created, which is all equal on all Posts in test,
        // and can't be overridden, so can't rely on order
        //$this->assertEquals($stickies->First()->Title, 'Global Sticky Thread');

        $stickies = $forumWithSticky->getStickyTopics(false);
        $this->assertEquals($stickies->Count(), '1');
        $this->assertEquals($stickies->First()->Title, 'Sticky Thread');

        $forumWithGlobalOnly = $this->objFromFixture(Forum::class, "forum1cat2");
        $stickies = $forumWithGlobalOnly->getStickyTopics();
        $this->assertEquals($stickies->Count(), '1');
        $this->assertEquals($stickies->First()->Title, 'Global Sticky Thread');
        $stickies = $forumWithGlobalOnly->getStickyTopics($include_global = false);
        $this->assertEquals($stickies->Count(), '0');
    }

    public function testTopics()
    {
        $forumWithPosts = $this->objFromFixture(Forum::class, "general");

        $this->assertEquals($forumWithPosts->getTopics()->Count(), '4');

        $forumWithoutPosts = $this->objFromFixture(Forum::class, "forum1cat2");

        $this->assertNull($forumWithoutPosts->getTopics());
    }

    public function testGetLatestPost()
    {
        $forumWithPosts = $this->objFromFixture(Forum::class, "general");

        $this->assertEquals($forumWithPosts->getLatestPost()->Content, 'This is the last post to a long thread');

        $forumWithoutPosts = $this->objFromFixture(Forum::class, "forum1cat2");

        $this->assertNull($forumWithoutPosts->getLatestPost());
    }

    public function testGetNumPosts()
    {
        $forumWithPosts = $this->objFromFixture(Forum::class, "general");

        $this->assertEquals(24, $forumWithPosts->getNumPosts());

        //Mark spammer accounts and retest the posts count
        $this->markGhosts();
        $this->assertEquals(22, $forumWithPosts->getNumPosts());
    }

    public function testGetNumTopics()
    {
        $forumWithPosts = $this->objFromFixture(Forum::class, "general");

        $this->assertEquals(6, $forumWithPosts->getNumTopics());

        $forumWithoutPosts = $this->objFromFixture(Forum::class, "forum1cat2");

        $this->assertEquals(0, $forumWithoutPosts->getNumTopics());

        //Mark spammer accounts and retest the threads count
        $this->markGhosts();
        $this->assertEquals(5, $forumWithPosts->getNumTopics());
    }

    public function testGetTotalAuthors()
    {
        $forumWithPosts = $this->objFromFixture(Forum::class, "general");

        $this->assertEquals(4, $forumWithPosts->getNumAuthors());

        $forumWithoutPosts = $this->objFromFixture(Forum::class, "forum1cat2");

        $this->assertEquals(0, $forumWithoutPosts->getNumAuthors());

        //Mark spammer accounts and retest the authors count
        $this->markGhosts();
        $this->assertEquals(2, $forumWithPosts->getNumAuthors());
    }

    protected function markGhosts()
    {
        //Mark a members as a spammers
        $spammer = $this->objFromFixture(Member::class, "spammer");
        $spammer->ForumStatus = 'Ghost';
        $spammer->write();

        $spammer2 = $this->objFromFixture(Member::class, "spammer2");
        $spammer2->ForumStatus = 'Ghost';
        $spammer2->write();
    }

    /**
     * Note: See {@link testCanModerate()} for detailed permission tests.
     */
    public function testMarkAsSpamLink()
    {
        $this->markTestSkipped('Requires SS6 controller routing migration for ForumController actions');

        $spampost = $this->objFromFixture(Post::class, 'SpamSecondPost');
        $forum = $spampost->Forum();

        $author = $spampost->Author();
        $moderator = $this->objFromFixture(Member::class, 'moderator'); // moderator for "general" forum

        // without a logged-in moderator
        $this->assertFalse($spampost->MarkAsSpamLink(), 'Link not present by default');

        $controller = ForumController::create($forum);
        $response = $controller->handleRequest(new HTTPRequest('GET', 'markasspam/' . $spampost->ID));
        $this->assertEquals(403, $response->getStatusCode());

        // does not effect the thread
        $thread = ForumThread::get()->byID($spampost->Thread()->ID);
        $this->assertEquals('1', $thread->getNumPosts());

        // mark the first post in that now as spam
        $spamfirst = $this->objFromFixture(Post::class, 'SpamFirstPost');

        $response = $controller->handleRequest(new HTTPRequest('GET', 'markasspam/' . $spamfirst->ID));

        // removes the thread
        $this->assertNull(ForumThread::get()->byID($spamfirst->Thread()->ID));
    }

    public function testBanLink()
    {
        $this->markTestSkipped('Requires SS6 controller routing migration for ForumController actions');

        $spampost = $this->objFromFixture(Post::class, 'SpamSecondPost');
        $forum = $spampost->Forum();
        $author = $spampost->Author();
        $moderator = $this->objFromFixture(Member::class, 'moderator'); // moderator for "general" forum

        // without a logged-in moderator
        $this->assertFalse($spampost->BanLink(), 'Link not present by default');

        $controller = ForumController::create($forum);
        $response = $controller->handleRequest(new HTTPRequest('GET', 'ban/' . $spampost->AuthorID));
        $this->assertEquals(403, $response->getStatusCode());

        // with logged-in moderator
        $this->logInAs($moderator);
        $this->assertNotEquals(false, $spampost->BanLink(), 'Link present for moderators on this forum');

        $controller = ForumController::create($forum);
        $response = $controller->handleRequest(new HTTPRequest('GET', 'ban/' . $spampost->AuthorID));
        $this->assertFalse($response->isError());

        // user is banned
        $author = Member::get()->byId($author->ID);
        $this->assertTrue($author->IsBanned());
    }

    public function testGhostLink()
    {
        $this->markTestSkipped('Requires SS6 controller routing migration for ForumController actions');

        $spampost = $this->objFromFixture(Post::class, 'SpamSecondPost');
        $forum = $spampost->Forum();
        $author = $spampost->Author();
        $moderator = $this->objFromFixture(Member::class, 'moderator'); // moderator for "general" forum

        // without a logged-in moderator
        $this->assertFalse($spampost->GhostLink(), 'Link not present by default');

        $controller = ForumController::create($forum);
        $response = $controller->handleRequest(new HTTPRequest('GET', 'ghost/' . $spampost->AuthorID));
        $this->assertEquals(403, $response->getStatusCode());

        // with logged-in moderator
        $this->logInAs($moderator);
        $this->assertNotEquals(false, $spampost->GhostLink(), 'Link present for moderators on this forum');

        $controller = ForumController::create($forum);
        $response = $controller->handleRequest(new HTTPRequest('GET', 'ghost/' . $spampost->AuthorID));
        $this->assertFalse($response->isError());

        // user is banned
        $author = Member::get()->byId($author->ID);
        $this->assertTrue($author->IsGhost());
    }

    public function testNotifyModerators()
    {
        $this->markTestSkipped('Requires SS6 controller routing migration for form submissions');

        SecurityToken::disable();

        $forum = $this->objFromFixture(Forum::class, 'general');
        $user = $this->objFromFixture(Member::class, 'test1');

        $this->logInAs($user);

        // New thread
        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'New thread',
                'Content' => 'Meticulously crafted content',
                'action_doPostMessageForm' => 1,
            ]
        );

        $adminEmail = Email::config()->get('admin_email');

        $this->assertEmailSent(
            'test3@example.com',
            $adminEmail,
            "New thread \"New thread\" in forum [General Discussion]"
        );
        $this->clearEmails();

        // New response
        $thread = ForumThread::get()->filter([
            'Title' => 'New thread',
        ]);
        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'Re: New thread',
                'Content' => 'Rough response',
                'ThreadID' => $thread->ID,
                'action_doPostMessageForm' => 1,
            ]
        );
        $this->assertEmailSent(
            'test3@example.com',
            $adminEmail,
            "New post \"Re: New thread\" in forum [General Discussion]"
        );
        $this->clearEmails();

        // Edit
        $post = $thread->Posts()->Last();
        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'Re: New thread',
                'Content' => 'Pleasant response',
                'ThreadID' => $thread->ID,
                'ID' => $post->ID,
                'action_doPostMessageForm' => 1,
            ]
        );
        $this->assertEmailSent(
            'test3@example.com',
            $adminEmail,
            "New post \"Re: New thread\" in forum [General Discussion]"
        );
        $this->clearEmails();
    }

    public function testDoPostMessageFormCreatesNewThread()
    {
        SecurityToken::disable();

        $forum = $this->objFromFixture(Forum::class, 'general');
        $user = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($user);

        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'Brand New Thread',
                'Content' => 'This is the first post in a new thread',
                'action_doPostMessageForm' => 1,
            ]
        );

        $thread = ForumThread::get()->filter('Title', 'Brand New Thread')->first();
        $this->assertNotNull($thread, 'New thread should be created');
        $this->assertEquals($forum->ID, $thread->ForumID);
        $this->assertEquals($user->ID, $thread->AuthorID);

        $post = Post::get()->filter('ThreadID', $thread->ID)->first();
        $this->assertNotNull($post, 'Post should be created for the new thread');
        $this->assertStringContainsString('This is the first post in a new thread', $post->Content);
        $this->assertEquals($user->ID, $post->AuthorID);
        $this->assertEquals($forum->ID, $post->ForumID);
    }

    public function testDoPostMessageFormReplyToThread()
    {
        SecurityToken::disable();

        $forum = $this->objFromFixture(Forum::class, 'general');
        $user = $this->objFromFixture(Member::class, 'test1');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $this->logInAs($user);

        $postCountBefore = Post::get()->filter('ThreadID', $thread->ID)->count();

        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'Re: Test Thread',
                'Content' => 'This is a reply to the thread',
                'ThreadID' => $thread->ID,
                'action_doPostMessageForm' => 1,
            ]
        );

        $postCountAfter = Post::get()->filter('ThreadID', $thread->ID)->count();
        $this->assertEquals($postCountBefore + 1, $postCountAfter, 'A new reply post should be created');

        $latestPost = Post::get()->filter('ThreadID', $thread->ID)->sort('ID', 'DESC')->first();
        $this->assertEquals('This is a reply to the thread', $latestPost->Content);
        $this->assertEquals($user->ID, $latestPost->AuthorID);
    }

    public function testDoPostMessageFormEditPost()
    {
        SecurityToken::disable();

        $forum = $this->objFromFixture(Forum::class, 'general');
        $user = $this->objFromFixture(Member::class, 'test1');
        $post = $this->objFromFixture(Post::class, 'Post1');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $this->logInAs($user);

        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'Test Thread',
                'Content' => 'Updated content for the post',
                'ThreadID' => $thread->ID,
                'ID' => $post->ID,
                'action_doPostMessageForm' => 1,
            ]
        );

        $updatedPost = Post::get()->byID($post->ID);
        $this->assertEquals('Updated content for the post', $updatedPost->Content);
    }

    public function testDoPostMessageFormCreatesSubscription()
    {
        SecurityToken::disable();

        $forum = $this->objFromFixture(Forum::class, 'general');
        $user = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($user);

        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'Subscription Test Thread',
                'Content' => 'Testing subscription creation',
                'TopicSubscription' => 1,
                'action_doPostMessageForm' => 1,
            ]
        );

        $thread = ForumThread::get()->filter('Title', 'Subscription Test Thread')->first();
        $this->assertNotNull($thread);

        $this->assertTrue(
            ForumThreadSubscription::get()->filter([
                'ThreadID' => $thread->ID,
                'MemberID' => $user->ID,
            ])->exists(),
            'Subscription should be created when TopicSubscription is checked'
        );
    }

    public function testDoPostMessageFormRemovesSubscription()
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $user = $this->objFromFixture(Member::class, 'test1');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $this->logInAs($user);
        $securityToken = SecurityToken::create();

        $this->assertTrue(
            ForumThreadSubscription::get()->filter([
                'ThreadID' => $thread->ID,
                'MemberID' => $user->ID,
            ])->exists(),
            'test1 should be subscribed to Thread1 via fixtures'
        );

        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'Re: Test Thread',
                'Content' => 'Reply without subscription',
                'ThreadID' => $thread->ID,
                'action_doPostMessageForm' => 1,
                'SecurityID' => $securityToken->getValue(),
            ]
        );

        $hasSubscription = ForumThreadSubscription::get()->filter([
            'ThreadID' => $thread->ID,
            'MemberID' => $user->ID,
        ])->exists();

        $this->assertFalse($hasSubscription, 'Subscription should be removed when TopicSubscription is not checked');
    }

    public function testDoPostMessageFormDeniedNoPermission()
    {
        SecurityToken::disable();

        $forum = $this->objFromFixture(Forum::class, 'noPostingForum');
        $user = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($user);

        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'Should Not Create',
                'Content' => 'This should be rejected',
                'action_doPostMessageForm' => 1,
            ]
        );

        $thread = ForumThread::get()->filter('Title', 'Should Not Create')->first();
        $this->assertNull($thread, 'Thread should not be created in a no-posting forum');
    }

    public function testDoPostMessageFormFiltersLanguage()
    {
        SecurityToken::disable();

        $forum = $this->objFromFixture(Forum::class, 'general');
        $user = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($user);

        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            [
                'Title' => 'Language Filter Test',
                'Content' => 'This is shit content',
                'action_doPostMessageForm' => 1,
            ]
        );

        $thread = ForumThread::get()->filter('Title', 'Language Filter Test')->first();
        $this->assertNotNull($thread);

        $post = Post::get()->filter('ThreadID', $thread->ID)->first();
        $this->assertEquals('This is * content', $post->Content);
    }
}
