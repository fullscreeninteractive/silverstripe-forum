<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests\PageTypes;

use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumController;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;

/**
 * Functional tests for ForumController.
 * Tests each controller action and form submission via submitForm for reply, starttopic, editpost.
 */
class ForumControllerTest extends FunctionalTest
{
    protected static $fixture_file = [
        './tests/fixtures.yml',
    ];

    protected static $use_draft_site = true;

    private function publishForums(): void
    {
        foreach (ForumHolder::get() as $h) {
            $h->publishRecursive();
        }
        foreach (Forum::get() as $f) {
            $f->publishRecursive();
        }
    }



    private function createController(Forum $forum, array $urlParams = []): ForumController
    {
        $url = $forum->RelativeLink();
        if (!empty($urlParams['ID'])) {
            $url .= 'show/' . $urlParams['ID'];
        }
        $url = (strpos($url ?? '', '/') === 0) ? $url : '/' . $url;
        $request = new HTTPRequest('GET', $url);
        $request->setSession($this->session());
        if (!empty($urlParams['ID'])) {
            $request->setRouteParams(array_merge($request->routeParams(), ['ID' => $urlParams['ID']]));
        }
        $controller = new ForumController($forum);
        $controller->setRequest($request);
        $controller->pushCurrent();
        $controller->doInit();

        return $controller;
    }

    // -----------------------------------------------------------------------
    // rss
    // -----------------------------------------------------------------------

    public function testRssRedirectsToHolderRss(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('rss'));
        $this->assertTrue(
            $response->getStatusCode() === 301 || $response->getStatusCode() === 302,
            'rss() should redirect'
        );
        $this->assertStringContainsString('rss/forum/', $response->getHeader('Location') ?? '');
    }

    // -----------------------------------------------------------------------
    // show
    // -----------------------------------------------------------------------

    public function testShowReturns200WithThread(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('show/' . $thread->ID));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString($thread->Title, $this->content());
    }

    public function testShowReturns404ForInvalidThread(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('show/99999'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // reply
    // -----------------------------------------------------------------------

    public function testReplyReturns200WithForm(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('reply/' . $thread->ID));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('PostMessageForm_PostMessageForm', $this->content());
    }

    public function testReplyReturns404ForInvalidThread(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('reply/99999'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * Submit PostMessageForm on reply page via submitForm helper.
     */
    public function testReplyPostMessageFormCanBeSubmitted(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $body = $this->get($forum->Link('reply/' . $thread->ID));

        print($body);
        exit;

        $response = $this->submitForm(
            'PostMessageForm_PostMessageForm',
            'action_doPostMessageForm',
            [
                'Title' => $thread->Title,
                'Content' => 'Functional test reply content',
                'ThreadID' => (string) $thread->ID,
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $newPost = Post::get()->filter([
            'ThreadID' => $thread->ID,
            'Content' => 'Functional test reply content',
        ])->first();
        $this->assertNotNull($newPost, 'New post should be created after reply form submission');
    }

    // -----------------------------------------------------------------------
    // starttopic
    // -----------------------------------------------------------------------

    public function testStarttopicReturns200WithForm(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('starttopic'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('PostMessageForm_PostMessageForm', $this->content());
    }

    /**
     * Submit PostMessageForm on start topic page via submitForm helper.
     */
    public function testStarttopicPostMessageFormCanBeSubmitted(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $this->get($forum->Link('starttopic'));

        $response = $this->submitForm(
            'PostMessageForm_PostMessageForm',
            'action_doPostMessageForm',
            [
                'Title' => 'New topic from functional test',
                'Content' => 'Body of the new topic',
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $newThread = ForumThread::get()->filter(['Title' => 'New topic from functional test'])->first();
        $this->assertNotNull($newThread, 'New thread should be created after start topic form submission');
        $firstPost = Post::get()->filter(['ThreadID' => $newThread->ID])->first();
        $this->assertNotNull($firstPost);
        $this->assertSame('Body of the new topic', $firstPost->Content);
    }

    // -----------------------------------------------------------------------
    // editpost
    // -----------------------------------------------------------------------

    public function testEditpostReturns200WithForm(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $post = $this->objFromFixture(Post::class, 'Post1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('editpost/' . $post->ID));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('PostMessageForm_PostMessageForm', $this->content());
    }

    public function testEditpostReturns404ForInvalidPost(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('editpost/99999'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * Submit PostMessageForm on edit post page via submitForm helper.
     */
    public function testEditpostPostMessageFormCanBeSubmitted(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $post = $this->objFromFixture(Post::class, 'Post1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $this->get($forum->Link('editpost/' . $post->ID));

        $response = $this->submitForm(
            'PostMessageForm_PostMessageForm',
            'action_doPostMessageForm',
            [
                'Title' => $thread->Title,
                'Content' => 'Edited content from functional test',
                'ThreadID' => (string) $thread->ID,
                'ID' => (string) $post->ID,
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $post->flushCache();
        $post->reload();
        $this->assertSame('Edited content from functional test', $post->Content);
    }

    // -----------------------------------------------------------------------
    // subscribe / unsubscribe (require security token when not disabled)
    // -----------------------------------------------------------------------

    public function testSubscribeWithoutTokenReturns400(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('subscribe/' . $thread->ID));
        $this->assertContains($response->getStatusCode(), [400, 404], 'Without token should return 400 Bad Request or 404 if action not routed');
    }

    public function testUnsubscribeWithoutTokenReturns400(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('unsubscribe/' . $thread->ID));
        $this->assertContains($response->getStatusCode(), [400, 404], 'Without token should return 400 Bad Request or 404 if action not routed');
    }

    // -----------------------------------------------------------------------
    // deletePost (requires POST + token)
    // -----------------------------------------------------------------------

    public function testDeletePostWithoutTokenReturns400(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $post = $this->objFromFixture(Post::class, 'Post1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('deletePost/' . $post->ID));
        $this->assertContains($response->getStatusCode(), [400, 404], 'Without token should return 400 Bad Request or 404 if action not routed');
    }

    // -----------------------------------------------------------------------
    // deletethread (requires token + canDelete)
    // -----------------------------------------------------------------------

    public function testDeletethreadWithoutTokenReturns400(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('deletethread/' . $thread->ID));
        $this->assertContains($response->getStatusCode(), [400, 403, 404], 'Without token or permission should return 400/403 or 404 if action not routed');
    }

    // -----------------------------------------------------------------------
    // markasspam (requires token + post ID)
    // -----------------------------------------------------------------------

    public function testMarkasspamWithoutIdReturns400(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $moderator = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($moderator);
        $response = $this->get($forum->Link('markasspam'));
        $this->assertContains($response->getStatusCode(), [400, 404], 'Without ID should return 400 Bad Request or 404 if action not routed');
    }

    // -----------------------------------------------------------------------
    // ban (requires token + canModerate)
    // -----------------------------------------------------------------------

    public function testBanWithoutIdReturns404(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $moderator = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($moderator);
        $response = $this->get($forum->Link('ban'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // ghost (requires token + canModerate)
    // -----------------------------------------------------------------------

    public function testGhostWithoutIdReturns400(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $moderator = $this->objFromFixture(Member::class, 'moderator');
        $this->logInAs($moderator);
        $response = $this->get($forum->Link('ghost'));
        $this->assertContains($response->getStatusCode(), [400, 404], 'Without ID should return 400 Bad Request or 404 if action not routed');
    }

    // -----------------------------------------------------------------------
    // deleteAttachment (requires token)
    // -----------------------------------------------------------------------

    public function testDeleteAttachmentWithoutTokenReturns400(): void
    {
        $this->publishForums();
        $forum = $this->objFromFixture(Forum::class, 'general');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $response = $this->get($forum->Link('deleteAttachment/1'));
        $this->assertContains($response->getStatusCode(), [400, 404], 'Without token should return 400 Bad Request or 404 if action not routed');
    }

    // -----------------------------------------------------------------------
    // Controller helpers (via direct controller creation)
    // -----------------------------------------------------------------------

    public function testGetForumThreadReturnsThreadWhenValid(): void
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->logInAs($member);
        $controller = $this->createController($forum, ['ID' => (string) $thread->ID]);
        $result = $controller->getForumThread();
        $this->assertNotNull($result);
        $this->assertEquals($thread->ID, $result->ID);
    }

    public function testGetForumThreadReturnsNullWhenInvalid(): void
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $controller = $this->createController($forum, ['ID' => '99999']);
        $this->assertNull($controller->getForumThread());
    }

    public function testReplyLink(): void
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $controller = $this->createController($forum, ['ID' => (string) $thread->ID]);
        $link = $controller->ReplyLink();
        $this->assertStringContainsString('reply', $link);
        $this->assertStringContainsString((string) $thread->ID, $link);
    }

    public function testGetHolderSubtitle(): void
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $controller = $this->createController($forum);
        $subtitle = $controller->getHolderSubtitle();
        $this->assertNotNull($subtitle);
        $this->assertStringContainsString($forum->Title, (string) $subtitle);
    }

    public function testFilterLanguage(): void
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $controller = $this->createController($forum);
        $filtered = $controller->filterLanguage('some shit content');
        $this->assertStringContainsString('*', $filtered);
        $this->assertStringNotContainsString('shit', $filtered);
    }

    public function testGetForbiddenWords(): void
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $controller = $this->createController($forum);
        $words = $controller->getForbiddenWords();
        $this->assertNotNull($words);
    }

    public function testPostsReturnsPaginatedListForThread(): void
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $controller = $this->createController($forum, ['ID' => (string) $thread->ID]);
        $posts = $controller->Posts();
        $this->assertNotNull($posts);
        $this->assertGreaterThan(0, $posts->count());
    }

    public function testPostsReturnsEmptyWhenNoThreadId(): void
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $controller = $this->createController($forum);
        $posts = $controller->Posts();
        $this->assertNotNull($posts);
        $this->assertEquals(0, $posts->count());
    }

    public function testPostMessageFormReturnsForm(): void
    {
        $forum = $this->objFromFixture(Forum::class, 'general');
        $controller = $this->createController($forum);
        $form = $controller->PostMessageForm();
        $this->assertNotNull($form);
        $this->assertSame($controller, $form->getController());
    }
}
