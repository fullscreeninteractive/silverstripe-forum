<?php

/**
 * @todo Write tests to cover the RSS feeds
 */
class ForumHolderTest extends FunctionalTest {

	static $fixture_file = "forum/tests/ForumTest.yml";

	public function setUp() {
		parent::setUp();

		// these assertions assume we're logged in with full permissions
		$this->logInWithPermission('ADMIN');
	}

	/**
	 * Tests around multiple forum holders, to ensure that given a forum holder, methods only retrieve
	 * categories and forums relevant to that holder.
	 *
	 * @return unknown_type
	 */
	public function testGetForums() {
		$fh = $this->objFromFixture("ForumHolder", "fh");
		$fh_controller = new ForumHolder_Controller($fh);

		// one forum which is viewable.
		$this->assertEquals('1', $fh_controller->Forums()->Count(), "Forum holder has 1 forum");

		// Test ForumHolder::Categories() on 'fh', from which we expect 2 categories
		$this->assertTrue($fh_controller->Categories()->Count() == 2, "fh has two categories");

		// Test what we got back from the two categories. The first expects 2 forums, the second
		// expects none.
		$this->assertTrue($fh_controller->Categories()->First()->Forums()->Count() == 0, "fh first category has 0 forums");
		$this->assertTrue($fh_controller->Categories()->Last()->Forums()->Count() == 2, "fh second category has 2 forums");

		// Test ForumHolder::Categories() on 'fh2', from which we expect 2 categories
		$fh2 = $this->objFromFixture("ForumHolder", "fh2");
		$fh2_controller = new ForumHolder_Controller($fh2);
		$this->assertTrue($fh2_controller->Categories()->Count() == 2, "fh first forum has two categories");

		// Test what we got back from the two categories. Each expects 1.
		$this->assertTrue($fh2_controller->Categories()->First()->Forums()->Count() == 1, "fh first category has 1 forums");
		$this->assertTrue($fh2_controller->Categories()->Last()->Forums()->Count() == 1, "fh second category has 1 forums");


		// plain forums (not nested in categories)
		$forumHolder = $this->objFromFixture("ForumHolder", "fhNoCategories");

		$this->assertEquals($forumHolder->Forums()->Count(), 1);
		$this->assertEquals($forumHolder->Forums()->First()->Title, "Forum Without Category");

		// plain forums with nested in categories enabled (but shouldn't effect it)
		$forumHolder = $this->objFromFixture("ForumHolder", "fhNoCategories");
		$forumHolder->ShowInCategories = true;
		$forumHolder->write();

		$this->assertEquals($forumHolder->Forums()->Count(), 1);
		$this->assertEquals($forumHolder->Forums()->First()->Title, "Forum Without Category");

	}

	public function testGetNumPosts() {
		// test holder with posts
		$fh = $this->objFromFixture("ForumHolder", "fh");
		$this->assertEquals(24, $fh->getNumPosts());

		// test holder that doesn't have posts
		$fh2 = $this->objFromFixture("ForumHolder", "fh2");
		$this->assertEquals(0, $fh2->getNumPosts());

		//Mark spammer accounts and retest the posts count
		$this->markGhosts();
		$this->assertEquals(22, $fh->getNumPosts());



	}

	public function testGetNumTopics() {
		// test holder with posts
		$fh = $this->objFromFixture("ForumHolder", "fh");
		$this->assertEquals(6, $fh->getNumTopics());

		// test holder that doesn't have posts
		$fh2 = $this->objFromFixture("ForumHolder", "fh2");
		$this->assertEquals(0, $fh2->getNumTopics());

		//Mark spammer accounts and retest the threads count
		$this->markGhosts();
		$this->assertEquals(5, $fh->getNumTopics());
	}

	public function testGetNumAuthors() {
		// test holder with posts
		$fh = $this->objFromFixture("ForumHolder", "fh");
		$this->assertEquals(4, $fh->getNumAuthors());

		// test holder that doesn't have posts
		$fh2 = $this->objFromFixture("ForumHolder", "fh2");
		$this->assertEquals(0, $fh2->getNumAuthors());

		//Mark spammer accounts and retest the authors count
		$this->markGhosts();
		$this->assertEquals(2, $fh->getNumAuthors());

	}

	protected function markGhosts() {
		//Mark a members as a spammers
		$spammer = $this->objFromFixture("Member", "spammer");
		$spammer->ForumStatus = 'Ghost';
		$spammer->write();

		$spammer2 = $this->objFromFixture("Member", "spammer2");
		$spammer2->ForumStatus = 'Ghost';
		$spammer2->write();
	}

	public function testGetRecentPosts() {
		// test holder with posts
		$fh = $this->objFromFixture("ForumHolder", "fh");

		// make sure all the posts are included
		$this->assertEquals($fh->getRecentPosts()->Count(), 24);

		// check they're in the right order (well if the first and last are right its fairly safe)
		$this->assertEquals($fh->getRecentPosts()->First()->Content, "This is the last post to a long thread");

		// test holder that doesn't have posts
		$fh2 = $this->objFromFixture("ForumHolder", "fh2");
		$this->assertNull($fh2->getRecentPosts());

		// test trying to get recent posts specific forum without posts
		$forum = $this->objFromFixture("Forum", "forum1cat2");
		$this->assertNull($fh->getRecentPosts(50, $forum->ID));

		// test trying to get recent posts specific to a forum which has posts
		$forum = $this->objFromFixture("Forum", "general");

		$this->assertEquals($fh->getRecentPosts(50, $forum->ID)->Count(), 24);
		$this->assertEquals($fh->getRecentPosts(50, $forum->ID)->First()->Content, "This is the last post to a long thread");

		// test trying to filter by a specific thread
		$thread = $this->objFromFixture("ForumThread","Thread1");

		$this->assertEquals($fh->getRecentPosts(50, null, $thread->ID)->Count(), 17);
		$this->assertEquals($fh->getRecentPosts(10, null, $thread->ID)->Count(), 10);
		$this->assertEquals($fh->getRecentPosts(50, null, $thread->ID)->First()->Content, 'This is the last post to a long thread');

		// test limiting the response
		$this->assertEquals($fh->getRecentPosts(1)->Count(), 1);
	}

	public function testGlobalAnnouncements() {
		// test holder with posts
		$fh = $this->objFromFixture("ForumHolder", "fh");
		$controller = new ForumHolder_Controller($fh);

		// make sure all the announcements are included
		$this->assertEquals($controller->GlobalAnnouncements()->Count(), 1);

		// test holder that doesn't have posts
		$fh2 = $this->objFromFixture("ForumHolder", "fh2");
		$controller2 = new ForumHolder_Controller($fh2);

		$this->assertEquals($controller2->GlobalAnnouncements()->Count(), 0);
	}

	public function testGetNewPostsAvailable() {
		$fh = $this->objFromFixture("ForumHolder", "fh");

		// test last visit. we can assume that these tests have been reloaded in the past 24 hours
		$data = array();
		$this->assertTrue(ForumHolder::new_posts_available($fh->ID, $data, date('Y-m-d H:i:s', mktime(0, 0, 0, date('m'), date('d')-1, date('Y')))));

		// set the last post ID (test the first post - so there should be a post, last post (false))
		$fixtureIDs = $this->allFixtureIDs('Post');
		$lastPostID = end($fixtureIDs);

		$this->assertTrue(ForumHolder::new_posts_available($fh->ID, $data,null, 1));
		$this->assertFalse(ForumHolder::new_posts_available($fh->ID, $data, null, $lastPostID));

		// limit to a specific forum
		$forum = $this->objFromFixture("Forum", "general");
		$this->assertTrue(ForumHolder::new_posts_available($fh->ID, $data, null, null, $forum->ID));
		$this->assertFalse(ForumHolder::new_posts_available($fh->ID, $data, null, $lastPostID, $forum->ID));

		// limit to a specific thread
		$thread = $this->objFromFixture("ForumThread", "Thread1");
		$this->assertTrue(ForumHolder::new_posts_available($fh->ID, $data, null, null, null, $thread->ID));
		$this->assertFalse(ForumHolder::new_posts_available($fh->ID, $data, null, $lastPostID, null, $thread->ID));
	}
}
