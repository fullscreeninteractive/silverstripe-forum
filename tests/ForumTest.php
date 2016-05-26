<?php

/**
 * @todo Write Tests for doPostMessageForm()
 */
class ForumTest extends FunctionalTest {
	
	static $fixture_file = "forum/tests/ForumTest.yml";
	static $use_draft_site = true;
	
	function testCanView() {
		// test viewing not logged in
		if($member = Member::currentUser()) $member->logOut();
		
		$public = $this->objFromFixture('Forum', 'general');
		$private = $this->objFromFixture('Forum', 'loggedInOnly');
		$limited = $this->objFromFixture('Forum', 'limitedToGroup');
		$noposting = $this->objFromFixture('Forum', 'noPostingForum');
		$inherited = $this->objFromFixture('Forum', 'inheritedForum');
		
		$this->assertTrue($public->canView());
		$this->assertFalse($private->canView());
		$this->assertFalse($limited->canView());
		$this->assertTrue($noposting->canView());
		$this->assertFalse($inherited->canView());
		
		// try logging in a member
		$member = $this->objFromFixture('Member', 'test1');
		$member->logIn();
		
		$this->assertTrue($public->canView());
		$this->assertTrue($private->canView());
		$this->assertFalse($limited->canView());
		$this->assertTrue($noposting->canView());
		$this->assertFalse($inherited->canView());
		
		// login as a person with access to restricted forum
		$member = $this->objFromFixture('Member', 'test2');
		$member->logIn();
		
		$this->assertTrue($public->canView());
		$this->assertTrue($private->canView());
		$this->assertTrue($limited->canView());
		$this->assertTrue($noposting->canView());
		$this->assertFalse($inherited->canView());

		// Moderator should be able to view his own forums
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();

		$this->assertTrue($public->canView());
		$this->assertTrue($private->canView());
		$this->assertTrue($limited->canView());
		$this->assertTrue($noposting->canView());
		$this->assertTrue($inherited->canView());
	}

	function testCanPost() {
		// test viewing not logged in
		if($member = Member::currentUser()) $member->logOut();
		
		$public = $this->objFromFixture('Forum', 'general');
		$private = $this->objFromFixture('Forum', 'loggedInOnly');
		$limited = $this->objFromFixture('Forum', 'limitedToGroup');
		$noposting = $this->objFromFixture('Forum', 'noPostingForum');
		$inherited = $this->objFromFixture('Forum', 'inheritedForum');
		
		$this->assertTrue($public->canPost());
		$this->assertFalse($private->canPost());
		$this->assertFalse($limited->canPost());
		$this->assertFalse($noposting->canPost());
		$this->assertFalse($inherited->canPost());
		
		// try logging in a member
		$member = $this->objFromFixture('Member', 'test1');
		$member->logIn();
		
		$this->assertTrue($public->canPost());
		$this->assertTrue($private->canPost());
		$this->assertFalse($limited->canPost());
		$this->assertFalse($noposting->canPost());
		$this->assertFalse($inherited->canPost());
		
		// login as a person with access to restricted forum
		$member = $this->objFromFixture('Member', 'test2');
		$member->logIn();
		
		$this->assertTrue($public->canPost());
		$this->assertTrue($private->canPost());
		$this->assertTrue($limited->canPost());
		$this->assertFalse($noposting->canPost());
		$this->assertFalse($inherited->canPost());

		// Moderator should be able to view his own forums
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();

		$this->assertTrue($public->canPost());
		$this->assertTrue($private->canPost());
		$this->assertFalse($limited->canPost());
		$this->assertFalse($noposting->canPost());
		$this->assertFalse($inherited->canPost());
	}
	
	function testSuspended() {
		$private = $this->objFromFixture('Forum', 'loggedInOnly');
		$limited = $this->objFromFixture('Forum', 'limitedToGroup');
		$inheritedForum_loggedInOnly = $this->objFromFixture('Forum', 'inheritedForum_loggedInOnly');
		SS_Datetime::set_mock_now('2011-10-10 12:00:00');	
			
		// try logging in a member suspendedexpired
		$suspendedexpired = $this->objFromFixture('Member', 'suspendedexpired');
		$this->assertFalse($suspendedexpired->IsSuspended());
		$suspendedexpired->logIn();
		$this->assertTrue($private->canPost());
		$this->assertTrue($limited->canPost());
		$this->assertTrue($inheritedForum_loggedInOnly->canPost());
		
		// try logging in a member suspended
		$suspended = $this->objFromFixture('Member', 'suspended');
		$this->assertTrue($suspended->IsSuspended());
		$suspended->logIn();
		$this->assertFalse($private->canPost());
		$this->assertFalse($limited->canPost());
		$this->assertFalse($inheritedForum_loggedInOnly->canPost());
	}

	function testCanModerate() {
		// test viewing not logged in
		if($member = Member::currentUser()) $member->logOut();
		
		$public = $this->objFromFixture('Forum', 'general');
		$private = $this->objFromFixture('Forum', 'loggedInOnly');
		$limited = $this->objFromFixture('Forum', 'limitedToGroup');
		$noposting = $this->objFromFixture('Forum', 'noPostingForum');
		$inherited = $this->objFromFixture('Forum', 'inheritedForum');
		
		$this->assertFalse($public->canModerate());
		$this->assertFalse($private->canModerate());
		$this->assertFalse($limited->canModerate());
		$this->assertFalse($noposting->canModerate());
		$this->assertFalse($inherited->canModerate());
		
		// try logging in a member
		$member = $this->objFromFixture('Member', 'test1');
		$member->logIn();
		
		$this->assertFalse($public->canModerate());
		$this->assertFalse($private->canModerate());
		$this->assertFalse($limited->canModerate());
		$this->assertFalse($noposting->canModerate());
		$this->assertFalse($inherited->canModerate());
		
		// login as a person with access to restricted forum
		$member = $this->objFromFixture('Member', 'test2');
		$member->logIn();
		
		$this->assertFalse($public->canModerate());
		$this->assertFalse($private->canModerate());
		$this->assertFalse($limited->canModerate());
		$this->assertFalse($noposting->canModerate());
		$this->assertFalse($inherited->canModerate());

		// Moderator should be able to view his own forums
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();

		$this->assertTrue($public->canModerate());
		$this->assertTrue($private->canModerate());
		$this->assertTrue($limited->canModerate());
		$this->assertTrue($noposting->canModerate());
		$this->assertTrue($inherited->canModerate());
	}

	function testCanAttach() {
		$canAttach = $this->objFromFixture('Forum', 'general');
		$this->assertTrue($canAttach->canAttach());
		
		$noAttach = $this->objFromFixture('Forum', 'forum1cat2');
		$this->assertFalse($noAttach->canAttach());
	}
	
	function testgetForbiddenWords(){
		$forum = $this->objFromFixture("Forum", "general");
		$f_controller = new Forum_Controller($forum);
		$this->assertEquals($f_controller->getForbiddenWords(), "shit,fuck");
	}
	
	function testfilterLanguage(){
		$forum =  $this->objFromFixture("Forum", "general");
		$f_controller = new Forum_Controller($forum);
		$this->assertEquals($f_controller->filterLanguage('shit'), "*");
		
		$this->assertEquals($f_controller->filterLanguage('shit and fuck'), "* and *");
		
		$this->assertEquals($f_controller->filterLanguage('hello'), "hello");
	}
	
	function testGetStickyTopics() {
		$forumWithSticky = $this->objFromFixture("Forum", "general");
		$stickies = $forumWithSticky->getStickyTopics();
		$this->assertEquals($stickies->Count(), '2');

		// TODO: Sorts by Created, which is all equal on all Posts in test, and can't be overridden, so can't rely on order
		//$this->assertEquals($stickies->First()->Title, 'Global Sticky Thread');

		$stickies = $forumWithSticky->getStickyTopics($include_global = false);
		$this->assertEquals($stickies->Count(), '1');
		$this->assertEquals($stickies->First()->Title, 'Sticky Thread');
		
		$forumWithGlobalOnly = $this->objFromFixture("Forum", "forum1cat2");
		$stickies = $forumWithGlobalOnly->getStickyTopics();
		$this->assertEquals($stickies->Count(), '1');
		$this->assertEquals($stickies->First()->Title, 'Global Sticky Thread');
		$stickies = $forumWithGlobalOnly->getStickyTopics($include_global = false);
		$this->assertEquals($stickies->Count(), '0');
	}
	
	function testTopics() {
		$forumWithPosts = $this->objFromFixture("Forum", "general");
		
		$this->assertEquals($forumWithPosts->getTopics()->Count(), '4');
		
		$forumWithoutPosts = $this->objFromFixture("Forum", "forum1cat2");
		
		$this->assertNull($forumWithoutPosts->getTopics());
	}
	
	function testGetLatestPost() {
		$forumWithPosts = $this->objFromFixture("Forum", "general");

		$this->assertEquals($forumWithPosts->getLatestPost()->Content, 'This is the last post to a long thread');
	
		$forumWithoutPosts = $this->objFromFixture("Forum", "forum1cat2");

		$this->assertNull($forumWithoutPosts->getLatestPost());
	}

	function testGetNumPosts() {
		$forumWithPosts = $this->objFromFixture("Forum", "general");

		$this->assertEquals(24, $forumWithPosts->getNumPosts());

		//Mark spammer accounts and retest the posts count
		$this->markGhosts();
		$this->assertEquals(22, $forumWithPosts->getNumPosts());
	}
	
	function testGetNumTopics() {
		$forumWithPosts = $this->objFromFixture("Forum", "general");
		
		$this->assertEquals(6, $forumWithPosts->getNumTopics());
		
		$forumWithoutPosts = $this->objFromFixture("Forum", "forum1cat2");

		$this->assertEquals(0, $forumWithoutPosts->getNumTopics());

		//Mark spammer accounts and retest the threads count
		$this->markGhosts();
		$this->assertEquals(5, $forumWithPosts->getNumTopics());
	}
	
	function testGetTotalAuthors() {
		$forumWithPosts = $this->objFromFixture("Forum", "general");
		
		$this->assertEquals(4, $forumWithPosts->getNumAuthors());
		
		$forumWithoutPosts = $this->objFromFixture("Forum", "forum1cat2");

		$this->assertEquals(0, $forumWithoutPosts->getNumAuthors());

		//Mark spammer accounts and retest the authors count
		$this->markGhosts();
		$this->assertEquals(2, $forumWithPosts->getNumAuthors());
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

	function testBanLink() {
		$spampost = $this->objFromFixture('Post', 'SpamSecondPost');
		$forum = $spampost->Forum();
		$author = $spampost->Author();
		$moderator = $this->objFromFixture('Member', 'moderator'); // moderator for "general" forum

		// without a logged-in moderator
		$this->assertFalse($spampost->BanLink(), 'Link not present by default');

		$c = new Forum_Controller($forum);
		$response = $c->handleRequest(new SS_HTTPRequest('GET', 'ban/'. $spampost->AuthorID), DataModel::inst());
		$this->assertEquals(403, $response->getStatusCode());

		// with logged-in moderator
		$moderator->logIn();
		$this->assertNotEquals(false, $spampost->BanLink(), 'Link present for moderators on this forum');

		$c = new Forum_Controller($forum);
		$response = $c->handleRequest(new SS_HTTPRequest('GET', 'ban/'. $spampost->AuthorID), DataModel::inst());
		$this->assertFalse($response->isError());

		// user is banned
		$author = Member::get()->byId($author->ID);
		$this->assertTrue($author->IsBanned());
	}

	function testGhostLink() {
		$spampost = $this->objFromFixture('Post', 'SpamSecondPost');
		$forum = $spampost->Forum();
		$author = $spampost->Author();
		$moderator = $this->objFromFixture('Member', 'moderator'); // moderator for "general" forum

		// without a logged-in moderator
		$this->assertFalse($spampost->GhostLink(), 'Link not present by default');

		$c = new Forum_Controller($forum);
		$response = $c->handleRequest(new SS_HTTPRequest('GET', 'ghost/'. $spampost->AuthorID), DataModel::inst());
		$this->assertEquals(403, $response->getStatusCode());

		// with logged-in moderator
		$moderator->logIn();
		$this->assertNotEquals(false, $spampost->GhostLink(), 'Link present for moderators on this forum');

		$c = new Forum_Controller($forum);
		$response = $c->handleRequest(new SS_HTTPRequest('GET', 'ghost/'. $spampost->AuthorID), DataModel::inst());
		$this->assertFalse($response->isError());

		// post isn't available anymore in normal queries. {@link ForumSpamPostExtension}
		$post = Post::get()->byId($spampost->ID);
		$this->assertNull($post);

		// user is banned
		$author = Member::get()->byId($author->ID);
		$this->assertTrue($author->IsGhost());
	}

	function testNotifyModerators() {
		SecurityToken::disable();
		$notifyModerators = Forum::$notify_moderators;
		Forum::$notify_moderators = true;

		$forum = $this->objFromFixture('Forum', 'general');
		$controller = new Forum_Controller($forum);
		$user = $this->objFromFixture('Member', 'test1');
		$this->session()->inst_set('loggedInAs', $user->ID);

		// New thread
		$this->post(
			$forum->RelativeLink('PostMessageForm'),
			array(
				'Title' => 'New thread',
				'Content' => 'Meticulously crafted content',
				'action_doPostMessageForm' => 1
			)
		);

		$adminEmail = Config::inst()->get('Email', 'admin_email');

		$this->assertEmailSent('test3@example.com', $adminEmail, "New thread \"New thread\" in forum [General Discussion]");
		$this->clearEmails();

		// New response
		$thread = DataObject::get_one('ForumThread', "\"ForumThread\".\"Title\"='New thread'");
		$this->post(
			$forum->RelativeLink('PostMessageForm'),
			array(
				'Title' => 'Re: New thread',
				'Content' => 'Rough response',
				'ThreadID' => $thread->ID,
				'action_doPostMessageForm' => 1
			)
		);
		$this->assertEmailSent('test3@example.com', $adminEmail, "New post \"Re: New thread\" in forum [General Discussion]");
		$this->clearEmails();

		// Edit
		$post = $thread->Posts()->Last();
		$this->post(
			$forum->RelativeLink('PostMessageForm'),
			array(
				'Title' => 'Re: New thread',
				'Content' => 'Pleasant response',
				'ThreadID' => $thread->ID,
				'ID' => $post->ID,
				'action_doPostMessageForm' => 1
			)
		);
		$this->assertEmailSent('test3@example.com', $adminEmail, "New post \"Re: New thread\" in forum [General Discussion]");
		$this->clearEmails();

		Forum::$notify_moderators = $notifyModerators;
	}

	/**
	 * Confirm that when a post is deleted, Member with corresponding ID still exists
	 *
	 * @throws ValidationException
	 * @throws null
	 */
	function testPostDeletionMemberIntegrity()
	{
		$checkID = 100012;

		$post = new Post();
		$post->ID = $checkID;
		$post->write();

		$user = new Member();
		$user->ID = $checkID;
		$user->FirstName = 'TestUser100012';
		$user->write();

		$post->delete();

		$member = DataObject::get_by_id('Member', $checkID);
		$this->assertTrue($member->ID == $checkID);
	}
}
