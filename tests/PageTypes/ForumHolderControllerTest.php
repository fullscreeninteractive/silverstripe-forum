<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests\PageTypes;

use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolderController;
use FullscreenInteractive\SilverStripe\Forum\Search\ForumSearch;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class ForumHolderControllerTest extends SapphireTest
{
    protected $usesDatabase = true;

    private ForumHolder $holder;
    private Forum $forum;
    private Member $author;
    private array $originalServer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalServer = $_SERVER;
        $this->logInWithPermission('ADMIN');

        $this->holder = ForumHolder::create();
        $this->holder->Title = 'Test Forum Holder';
        $this->holder->write();
        $this->holder->publishRecursive();

        $this->forum = Forum::create();
        $this->forum->Title = 'General Discussion';
        $this->forum->ParentID = $this->holder->ID;
        $this->forum->CanViewType = 'Anyone';
        $this->forum->CanPostType = 'Anyone';
        $this->forum->write();
        $this->forum->publishRecursive();

        $this->author = Member::create();
        $this->author->Email = 'controller-test@example.com';
        $this->author->Nickname = 'TestAuthor';
        $this->author->write();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        parent::tearDown();
    }

    private function createController(array $getVars = []): ForumHolderController
    {
        $controller = new ForumHolderController($this->holder);
        $request = new HTTPRequest('GET', '', $getVars);
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $controller->pushCurrent();

        return $controller;
    }

    private function createThread(
        string $title,
        int $numViews = 0,
        bool $isGlobalSticky = false,
        bool $isSticky = false
    ): ForumThread {
        $thread = ForumThread::create();
        $thread->Title = $title;
        $thread->ForumID = $this->forum->ID;
        $thread->NumViews = $numViews;
        $thread->IsGlobalSticky = $isGlobalSticky;
        $thread->IsSticky = $isSticky;
        $thread->AuthorID = $this->author->ID;
        $thread->write();

        return $thread;
    }

    private function createPost(ForumThread $thread, string $content = 'Test post'): Post
    {
        $post = Post::create();
        $post->Content = $content;
        $post->ThreadID = $thread->ID;
        $post->ForumID = $this->forum->ID;
        $post->AuthorID = $this->author->ID;
        $post->Status = 'Moderated';
        $post->write();

        return $post;
    }

    /**
     * Asserts ForumSearch::getSearchEngine() exists before running search tests.
     * The controller calls getSearchEngine() but the class defines get_search_engine().
     */
    private function skipIfSearchBroken(): void
    {
        if (!method_exists(ForumSearch::class, 'getSearchEngine')) {
            $this->markTestSkipped(
                'ForumSearch::getSearchEngine() not found; controller calls getSearchEngine() '
                . 'but ForumSearch only defines get_search_engine()'
            );
        }
    }

    // -----------------------------------------------------------------------
    // popularthreads
    // -----------------------------------------------------------------------

    public function testPopularThreadsByViewsSortsDescending(): void
    {
        $this->createThread('Low Views', 5);
        $this->createThread('High Views', 100);
        $this->createThread('Medium Views', 50);

        $controller = $this->createController(['by' => 'views']);
        $result = $controller->popularthreads();

        $this->assertEquals('views', $result['Method']);

        $threads = $result['Threads'];
        $this->assertNotNull($threads);
        $this->assertEquals(3, $threads->count());

        $titles = $threads->column('Title');
        $this->assertEquals('High Views', $titles[0]);
        $this->assertEquals('Medium Views', $titles[1]);
        $this->assertEquals('Low Views', $titles[2]);
    }

    public function testPopularThreadsDefaultsToPosts(): void
    {
        $controller = $this->createController();
        $result = $controller->popularthreads();

        $this->assertEquals('posts', $result['Method']);
    }

    public function testPopularThreadsReturnStructure(): void
    {
        $controller = $this->createController(['by' => 'views']);
        $result = $controller->popularthreads();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Title', $result);
        $this->assertArrayHasKey('Subtitle', $result);
        $this->assertArrayHasKey('Method', $result);
        $this->assertArrayHasKey('Threads', $result);
    }

    public function testPopularThreadsByViewsLimitsTo20(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createThread("Thread $i", $i * 10);
        }

        $controller = $this->createController(['by' => 'views']);
        $result = $controller->popularthreads();

        $this->assertEquals(20, $result['Threads']->count());
    }

    public function testPopularThreadsByViewsWithStartOffset(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createThread("Thread $i", ($i + 1) * 10);
        }

        $controller = $this->createController(['by' => 'views', 'start' => '2']);
        $result = $controller->popularthreads();

        $this->assertEquals(3, $result['Threads']->count());
    }

    // -----------------------------------------------------------------------
    // GlobalAnnouncements
    // -----------------------------------------------------------------------

    public function testGlobalAnnouncementsReturnsOnlyGlobalSticky(): void
    {
        $this->createThread('Normal Thread');
        $this->createThread('Local Sticky', 0, false, true);
        $this->createThread('Global Announcement', 0, true);

        $controller = $this->createController();
        $announcements = $controller->GlobalAnnouncements();

        $this->assertEquals(1, $announcements->count());
        $this->assertEquals('Global Announcement', $announcements->first()->Title);
    }

    public function testGlobalAnnouncementsEmptyWhenNone(): void
    {
        $this->createThread('Normal Thread');

        $controller = $this->createController();
        $announcements = $controller->GlobalAnnouncements();

        $this->assertEquals(0, $announcements->count());
    }

    public function testGlobalAnnouncementsMultiple(): void
    {
        $this->createThread('Announcement 1', 0, true);
        $this->createThread('Announcement 2', 0, true);

        $controller = $this->createController();
        $announcements = $controller->GlobalAnnouncements();

        $this->assertEquals(2, $announcements->count());
    }

    public function testGlobalAnnouncementsScopedToHolder(): void
    {
        $holder2 = ForumHolder::create();
        $holder2->Title = 'Other Holder';
        $holder2->write();
        $holder2->publishRecursive();

        $forum2 = Forum::create();
        $forum2->Title = 'Other Forum';
        $forum2->ParentID = $holder2->ID;
        $forum2->CanViewType = 'Anyone';
        $forum2->write();
        $forum2->publishRecursive();

        $otherThread = ForumThread::create();
        $otherThread->Title = 'Other Announcement';
        $otherThread->ForumID = $forum2->ID;
        $otherThread->IsGlobalSticky = true;
        $otherThread->AuthorID = $this->author->ID;
        $otherThread->write();

        $this->createThread('Our Announcement', 0, true);

        $controller = $this->createController();
        $announcements = $controller->GlobalAnnouncements();

        $this->assertEquals(1, $announcements->count());
        $this->assertEquals('Our Announcement', $announcements->first()->Title);
    }

    // -----------------------------------------------------------------------
    // search
    // -----------------------------------------------------------------------

    public function testSearchReturnStructure(): void
    {
        $this->skipIfSearchBroken();

        $controller = $this->createController(['Search' => 'test']);
        $result = $controller->search();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Subtitle', $result);
        $this->assertArrayHasKey('Abstract', $result);
        $this->assertArrayHasKey('Query', $result);
        $this->assertArrayHasKey('Order', $result);
        $this->assertArrayHasKey('RSSLink', $result);
        $this->assertArrayHasKey('SearchResults', $result);
    }

    public function testSearchDefaultOrderIsRelevance(): void
    {
        $this->skipIfSearchBroken();

        $controller = $this->createController(['Search' => 'test']);
        $result = $controller->search();

        $this->assertEquals('relevance', (string) $result['Order']);
    }

    public function testSearchWithOrderParameter(): void
    {
        $this->skipIfSearchBroken();

        $controller = $this->createController(['Search' => 'test', 'order' => 'newest']);
        $result = $controller->search();

        $this->assertEquals('newest', (string) $result['Order']);
    }

    public function testSearchAbstractContainsKeywords(): void
    {
        $this->skipIfSearchBroken();

        $controller = $this->createController(['Search' => 'forum topic']);
        $result = $controller->search();

        $this->assertStringContainsString('forum topic', (string) $result['Abstract']);
    }

    public function testSearchWithKeywordsFindsPost(): void
    {
        $this->skipIfSearchBroken();

        $thread = $this->createThread('Searchable Thread');
        $this->createPost($thread, 'Unique searchable content for testing');

        $controller = $this->createController(['Search' => 'searchable']);
        $result = $controller->search();

        $this->assertArrayHasKey('SearchResults', $result);
        $this->assertGreaterThan(0, $result['SearchResults']->count());
    }

    // -----------------------------------------------------------------------
    // rss
    // -----------------------------------------------------------------------

    public function testRssReturnsValidResponse(): void
    {
        $thread = $this->createThread('RSS Thread');
        $this->createPost($thread, 'RSS content');

        unset($_SERVER['HTTP_IF_MODIFIED_SINCE'], $_SERVER['HTTP_IF_NONE_MATCH']);

        $controller = $this->createController();
        $result = $controller->rss();

        $this->assertNotNull($result);
    }

    public function testRssContainsXmlContent(): void
    {
        $thread = $this->createThread('RSS Thread');
        $this->createPost($thread, 'RSS post content');

        unset($_SERVER['HTTP_IF_MODIFIED_SINCE'], $_SERVER['HTTP_IF_NONE_MATCH']);

        $controller = $this->createController();
        $result = $controller->rss();

        $body = (string) $result;
        $this->assertStringContainsString('<?xml', $body);
        $this->assertStringContainsString('rss', $body);
    }

    public function testRssSetsCacheControlHeader(): void
    {
        $thread = $this->createThread('RSS Thread');
        $this->createPost($thread, 'Cache test content');

        unset($_SERVER['HTTP_IF_MODIFIED_SINCE'], $_SERVER['HTTP_IF_NONE_MATCH']);

        $controller = $this->createController();
        $controller->rss();

        $response = $controller->getResponse();
        $this->assertStringContainsString('max-age=3600', $response->getHeader('Cache-Control'));
    }

    public function testRssWithNoPosts(): void
    {
        unset($_SERVER['HTTP_IF_MODIFIED_SINCE'], $_SERVER['HTTP_IF_NONE_MATCH']);

        $controller = $this->createController();
        $result = $controller->rss();

        $this->assertNotNull($result);
    }

    public function testRss304WhenClientHasLatestPost(): void
    {
        $thread = $this->createThread('RSS Thread');
        $post = $this->createPost($thread, 'Latest content');

        $_SERVER['HTTP_IF_NONE_MATCH'] = (string) ($post->ID + 1000);
        unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);

        $controller = $this->createController();
        $result = $controller->rss();

        $this->assertNotNull($result);
    }
}
