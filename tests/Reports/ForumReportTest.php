<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests;

use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\Reports\ForumMonthlyPosts;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class ForumReportTest extends FunctionalTest
{
    protected static $fixture_file = [
        'ForumTest.yml',
    ];

    protected static $use_draft_site = true;

    public function testMemberSignupsReport()
    {
        $r = new ForumMonthlyPosts();
        $before = $r->sourceRecords(array());

        // Create a new Member in current month
        $member = Member::create();
        $member->Email = 'testMemberSignupsReport';
        $member->write();

        // Ensure the signup count for current month has increased by one
        $this->assertEquals((int)$before->first()->Signups + 1, (int)$r->records(array())->first()->Signups);

        // Move our member to have signed up in April 2015 and check that month's signups
        $member->Created = '2015-04-01 12:00:00';
        $member->write();
        $this->assertEquals(1, $r->records(array())->find('Month', '2015 April')->Signups);

        // We should now be back to our original number of members in current month
        $this->assertEquals((int)$before->first()->Signups, (int)$r->records(array())->first()->Signups);
    }

    public function testMonthlyPostsReport()
    {
        $r = new ForumMonthlyPosts();
        $before = $r->sourceRecords([]);

        // Create a new post in current month
        $post = Post::create();
        $post->AuthorID = $this->objFromFixture('Member', 'test2')->ID;
        $post->ThreadID = $this->objFromFixture('ForumThread', 'Thread2')->ID;
        $post->ForumID = $this->objFromFixture('Forum', 'forum5')->ID;
        $post->write();

        // Ensure the post count for current month has increased by one
        $this->assertEquals((int)$before->first()->Posts + 1, (int)$r->records(array())->first()->Posts);

        // Move our post to April 2015 and ensure there are two posts (one is specified in fixture file)
        $post->Created = '2015-04-01 12:00:00';
        $post->write();
        $this->assertEquals(2, $r->records(array())->find('Month', '2015 April')->Posts);

        // We should now be back to our original number of posts in current month
        $this->assertEquals((int)$before->first()->Posts, (int)$r->records(array())->first()->Posts);
    }
}
