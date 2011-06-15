<?php

class PostTest extends FunctionalTest {
	
	static $fixture_file = "forum/tests/ForumTest.yml";

	function testPermissions() {
		$member = $this->objFromFixture('Member', 'test1');
		$this->session()->inst_set('loggedInAs', $member->ID);

		// read only thread post
		$readonly = $this->objFromFixture('Post', 'ReadonlyThreadPost');
		$this->assertFalse($readonly->canEdit()); // Even though it's user's own
		$this->assertTrue($readonly->canView());
		$this->assertFalse($readonly->canCreate());
		$this->assertFalse($readonly->canDelete());
		
		// normal thread. They can post to these
		$post = $this->objFromFixture('Post', 'Post18');
		$this->assertFalse($post->canEdit()); // Not user's post
		$this->assertTrue($post->canView());
		$this->assertTrue($post->canCreate());
		$this->assertFalse($post->canDelete());
		
		$member = $this->objFromFixture('Member', 'test2');
		$this->session()->inst_set('loggedInAs', $member->ID);

		// Check the user can edit his own post (but not delete)
		$this->assertTrue($post->canEdit()); // User's post
		$this->assertTrue($post->canView());
		$this->assertTrue($post->canCreate());
		$this->assertFalse($post->canDelete());

		// Moderator can delete posts
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();

		$this->assertFalse($post->canEdit());
		$this->assertTrue($post->canView());
		$this->assertTrue($post->canCreate());
		$this->assertTrue($post->canDelete());
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

		// should be false since we're not logged in.
		if($member = Member::currentUser()) $member->logOut();
		
		$this->assertFalse($post->EditLink());
		$this->assertFalse($post->DeleteLink());
		
		// logged in as the moderator. Should be able to delete the post.
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();
		
		$this->assertContains($post->Thread()->URLSegment .'/deletepost/'. $post->ID, $post->DeleteLink());
		
		// because this is the first post test for the class which is used in javascript
		$this->assertContains("class=\"deleteLink firstPost\"", $post->DeleteLink());
		$member->logOut();
		
		// log in as another member who is not in a position to delete this post
		$member = $this->objFromFixture('Member', 'test2');
		$member->logIn();
		
		$this->assertFalse($post->DeleteLink());
		
		// log in as someone who can moderator this post (and therefore delete it)
		$member = $this->objFromFixture('Member', 'moderator');
		$member->logIn();
		
		// should be able to edit post since they're moderators
		$this->assertContains($post->Thread()->URLSegment .'/deletepost/'. $post->ID, $post->DeleteLink());
		
		// test that a 2nd post doesn't have the first post ID hook
		$memberOthersPost = $this->objFromFixture('Post', 'Post2');
		
		$this->assertFalse(strstr($memberOthersPost->DeleteLink(), "firstPost"));
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
