<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests;

use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\Reports\ForumMonthlyPosts;
use FullscreenInteractive\SilverStripe\Forum\Reports\ForumReportMemberSignups;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class ForumReportTest extends SapphireTest
{
    protected static $fixture_file = [
        './tests/fixtures.yml',
    ];

    protected static $use_draft_site = true;

    public function testMemberSignupsReport()
    {
        $r = ForumReportMemberSignups::create();
        $before = $r->sourceRecords([]);

        // Create a new Member in current month
        $member = Member::create();
        $member->Email = 'testMemberSignupsReport';
        $member->write();

        // Ensure the signup count for current month has increased by one
        $this->assertEquals((int)$before->first()->Signups + 1, (int)$r->sourceRecords([])->first()->Signups);

        // Move our member to have signed up in April 2015 and check that month's signups
        $member->Created = '2015-04-01 12:00:00';
        $member->write();
        $this->assertEquals(1, $r->sourceRecords([])->find('Month', '2015 April')->Signups);

        // We should now be back to our original number of members in current month
        $this->assertEquals((int)$before->first()->Signups, (int)$r->sourceRecords([])->first()->Signups);
    }

    public function testMonthlyPostsReport()
    {
        $r = ForumMonthlyPosts::create();
        $before = $r->sourceRecords([]);

        // Create a new post in current month
        $post = Post::create();
        $post->AuthorID = $this->objFromFixture(Member::class, 'test2')->ID;
        $post->ThreadID = $this->objFromFixture(ForumThread::class, 'Thread2')->ID;
        $post->ForumID = $this->objFromFixture(Forum::class, 'forum5')->ID;
        $post->write();

        $firstMonth = $before->first();

        // Ensure the post count for current month has increased by one
        $this->assertEquals((int)$firstMonth->Posts + 1, (int)$r->sourceRecords([])->first()->Posts);

        // Move our post to April 2015 and ensure there are two posts (one is specified in fixture file)
        $post->Created = '2015-04-01 12:00:00';
        $post->write();
        $this->assertEquals(2, $r->sourceRecords([])->find('Month', '2015 April')->Posts);

        // We should now be back to our original number of posts in current month
        $this->assertEquals((int)$firstMonth->Posts, (int)$r->sourceRecords([])->first()->Posts);
    }
}
