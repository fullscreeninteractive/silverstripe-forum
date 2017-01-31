<?php

class PostTest extends FunctionalTest {

	static $fixture_file = "forum/tests/ForumTest.yml";

	// fixes permission issues with these tests, we don't need to test versioning anyway.
	// without this, SiteTree::canView() would always return false even though CanViewType == Anyone.
	static $use_draft_site = true;

	public function setUp(){
		parent::setUp();

		//track the default state of tokens
		$this->useToken = SecurityToken::is_enabled();

	}

	public function tearDown(){
		parent::tearDown();

		//if the token is turned on reset it before the next test run
		if($this->useToken) {
			SecurityToken::enable();
		} else {
			SecurityToken::disable();
		}
	}

	function testPermissions() {
		$member1 = $this->objFromFixture('Member', 'test1');
		$member2 = $this->objFromFixture('Member', 'test2');
		$moderator = $this->objFromFixture('Member', 'moderator');
		$admin = $this->objFromFixture('Member', 'admin');

		$postMember2 = $this->objFromFixture('Post', 'Post18');

		// read only thread post
		$member1->logIn();
		$postReadonly = $this->objFromFixture('Post', 'ReadonlyThreadPost');
		$this->assertFalse($postReadonly->canEdit()); // Even though it's user's own
		$this->assertTrue($postReadonly->canView());
		$this->assertFalse($postReadonly->canCreate());
		$this->assertFalse($postReadonly->canDelete());

		// normal thread. They can post to these
		$member1->logIn();
		$this->assertFalse($postMember2->canEdit()); // Not user's post
		$this->assertTrue($postMember2->canView());
		$this->assertTrue($postMember2->canCreate());
		$this->assertFalse($postMember2->canDelete());

		// Check the user has full rights on his own post
		$member2->logIn();
		$this->assertTrue($postMember2->canEdit()); // User's post
		$this->assertTrue($postMember2->canView());
		$this->assertTrue($postMember2->canCreate());
		$this->assertTrue($postMember2->canDelete());

		// Moderator can delete posts, even if he doesn't own them
		$moderator->logIn();
		$this->assertFalse($postMember2->canEdit());
		$this->assertTrue($postMember2->canView());
		$this->assertTrue($postMember2->canCreate());
		$this->assertTrue($postMember2->canDelete());

		// Admins should have full rights, even if they're not moderators or own the post
		$admin->logIn();
		$this->assertTrue($postMember2->canEdit());
		$this->assertTrue($postMember2->canView());
		$this->assertTrue($postMember2->canCreate());
		$this->assertTrue($postMember2->canDelete());
	}

	function testGetTitle() {
		$post = $this->objFromFixture('Post', 'Post1');
		$reply = $this->objFromFixture('Post', 'Post2');

		$this->assertEquals($post->Title, "Test Thread");
		$this->assertEquals($reply->Title, "Re: Test Thread");

		$first = $this->objFromFixture('Post', 'Post3');
		$this->assertEquals($first->Title, 'Another Test Thread');
	}

	function testIssFirstPost() {
		$first = $this->objFromFixture('Post', 'Post1');
		$this->assertTrue($first->isFirstPost());

		$notFirst = $this->objFromFixture('Post', 'Post2');
		$this->assertFalse($notFirst->isFirstPost());
	}

	function testReplyLink() {
		$post = $this->objFromFixture('Post', 'Post1');
		$this->assertContains($post->Thread()->URLSegment .'/reply/'.$post->ThreadID , $post->ReplyLink());
	}

	function testShowLink() {
		$post = $this->objFromFixture('Post', 'Post1');
		Forum::$posts_per_page = 8;

		// test for show link on first page
		$this->assertContains($post->Thread()->URLSegment .'/show/'.$post->ThreadID, $post->ShowLink());

		// test for link that should be last post on the first page
		$eighthPost = $this->objFromFixture('Post', 'Post9');
		$this->assertContains($eighthPost->Thread()->URLSegment .'/show/'.$eighthPost->ThreadID.'#post'.$eighthPost->ID , $eighthPost->ShowLink());

		// test for a show link on a subpage
		$lastPost = $this->objFromFixture('Post', 'Post10');
		$this->assertContains($lastPost->Thread()->URLSegment .'/show/'. $lastPost->ThreadID . '?start=8#post'.$lastPost->ID, $lastPost->ShowLink());

		// this is the last post on page 2
		$lastPost = $this->objFromFixture('Post', 'Post17');
		$this->assertContains($lastPost->Thread()->URLSegment .'/show/'. $lastPost->ThreadID . '?start=8#post'.$lastPost->ID, $lastPost->ShowLink());

		// test for a show link on the last subpage
		$lastPost = $this->objFromFixture('Post', 'Post18');
		$this->assertContains($lastPost->Thread()->URLSegment .'/show/'. $lastPost->ThreadID . '?start=16#post'.$lastPost->ID, $lastPost->ShowLink());
	}

	function testEditLink() {
		$post = $this->objFromFixture('Post', 'Post1');

		// should be false since we're not logged in.
		if($member = Member::currentUser()) $member->logOut();

		$this->assertFalse($post->EditLink());

		// logged in as the member. Should be able to edit it
		$member = $this->objFromFixture('Member', 'test1');
		$member->logIn();

		$this->assertContains($post->Thread()->URLSegment .'/editpost/'. $post->ID, $post->EditLink());

		// log in as another member who is not
		$member->logOut();

		$memberOther = $this->objFromFixture('Member', 'test2');
		$memberOther->logIn();

		$this->assertFalse($post->EditLink());
	}

	function testDeleteLink() {
		$post = $this->objFromFixture('Post', 'Post1');

		//enable token
		SecurityToken::enable();

		// should be false since we're not logged in.
		if($member = Member::currentUser()) $member->logOut();

		$this->assertFalse($post->EditLink());
		$this->assertFalse($post->DeleteLink());

		// logged in as the moderator. Should be able to delete the post.
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();

		$this->assertContains($post->Thread()->URLSegment .'/deletepost/'. $post->ID, $post->DeleteLink());

		// because this is the first post test for the class which is used in javascript
		$this->assertContains("deletepost", $post->DeleteLink());

		$member->logOut();

		// log in as another member who is not in a position to delete this post
		$member = $this->objFromFixture('Member', 'test2');
		$member->logIn();

		$this->assertFalse($post->DeleteLink());

		// log in as someone who can moderate this post (and therefore delete it)
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();


		//check for the existance of a CSRF token
		$this->assertContains("SecurityID=", $post->DeleteLink());

		// should be able to edit post since they're moderators
		$this->assertContains($post->Thread()->URLSegment .'/deletepost/'. $post->ID, $post->DeleteLink());

		// test that a 2nd post doesn't have the first post ID hook
		$memberOthersPost = $this->objFromFixture('Post', 'Post2');

		$this->assertFalse(strstr($memberOthersPost->DeleteLink(), "firstPost"));

	}

	function testMarkAsSpamLink() {
		$post = $this->objFromFixture('Post', 'Post1');

		//enable token
		SecurityToken::enable();

		// should be false since we're not logged in.
		if($member = Member::currentUser()) $member->logOut();

		$this->assertFalse($post->EditLink());
		$this->assertFalse($post->MarkAsSpamLink());

		// logged in as the moderator. Should be able to mark the post as spam.
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();

		$this->assertContains($post->Thread()->URLSegment .'/markasspam/'. $post->ID, $post->MarkAsSpamLink());

		// because this is the first post test for the class which is used in javascript
		$this->assertContains("markasspam", $post->MarkAsSpamLink());

		$member->logOut();

		// log in as another member who is not in a position to mark post as spam this post
		$member = $this->objFromFixture('Member', 'test2');
		$member->logIn();

		$this->assertFalse($post->MarkAsSpamLink());

		// log in as someone who can moderate this post (and therefore mark as spam)
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();


		//check for the existance of a CSRF token
		$this->assertContains("SecurityID=", $post->MarkAsSpamLink());

		// should be able to edit post since they're moderators
		$this->assertContains($post->Thread()->URLSegment .'/markasspam/'. $post->ID, $post->MarkAsSpamLink());

		// test that a 2nd post doesn't have the first post ID hook
		$memberOthersPost = $this->objFromFixture('Post', 'Post2');

		$this->assertFalse(strstr($memberOthersPost->MarkAsSpamLink(), "firstPost"));
	}

	public function testBanAndGhostLink() {
		$post = $this->objFromFixture('Post', 'Post1');

		// should be false since we're not logged in.
		if($member = Member::currentUser()) $member->logOut();

		$this->assertFalse($post->EditLink());
		$this->assertFalse($post->BanLink());
		$this->assertFalse($post->GhostLink());

		// logged in as the moderator. Should be able to mark the post as spam.
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();

		$forum = $post->Thread()->Forum();
		$this->assertContains($forum->URLSegment . '/ban/' . $post->AuthorID, $post->BanLink());
		$this->assertContains($forum->URLSegment . '/ghost/' . $post->AuthorID, $post->GhostLink());

		$member->logOut();

		// log in as another member who is not in a position to mark post as spam this post
		$member = $this->objFromFixture('Member', 'test2');
		$member->logIn();

		$this->assertFalse($post->BanLink());
		$this->assertFalse($post->GhostLink());
	}

	function testGetUpdated() {
		$post = new Post();
		$post->Content = "Original Content";
		$post->write();

		$this->assertNull($post->Updated);
		sleep(2);
		$post->Content = "Some Content Now";
		$post->write();

		$this->assertNotNull($post->Updated);
	}

	function testRSSContent() {
		// @todo escaping tests. They are handled by bbcode parser tests?
	}

	function testRSSAuthor() {
		// @todo
	}
}
