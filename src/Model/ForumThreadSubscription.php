<?php

namespace FullscreenInteractive\SilverStripe\Forum\Model;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Core\Injector\Injector;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\Email\ForumSubscriptionEmail;

/**
 * Forum Thread Subscription: Allows members to subscribe to this thread
 * and receive email notifications when these topics are replied to.
 */
class ForumThreadSubscription extends DataObject
{
    private static $table_name = 'ForumThreadSubscription';

    private static $db = [
        "LastSent" => "Datetime"
    ];

    private static $has_one = [
        "Thread" => ForumThread::class,
        "Member" => Member::class
    ];

    /**
     * Checks to see if a Member is already subscribed to this thread
     *
     * @param int $threadID The ID of the thread to check
     * @param int $memberID The ID of the member to check
     *
     * @return bool true if they are subscribed, false if they're not
     */
    public function isSubscribed($threadID, $memberID = null)
    {
        return self::get()->filter([
            'ThreadID' => $threadID,
            'MemberID' => $memberID
        ])->exists();
    }


    /**
     * Static method to notify all subscribers of a thread about a new post
     * Notifies everybody that has subscribed to this topic that a new post has been added.
     * To get emailed, people subscribed to this topic must have visited the forum
     * since the last time they received an email
     *
     * @param Post $post The post that has just been added
     */
    public static function notify(Post $post)
    {
        // Get all subscriptions for this thread, excluding the post author
        $list = self::get()->filter([
            'ThreadID' => $post->ThreadID
        ])->exclude([
            'MemberID' => $post->AuthorID
        ]);

        if ($list) {
            foreach ($list as $obj) {
                $email = ForumSubscriptionEmail::create();
                $email->setSubscription($obj);
                $email->setPost($post);

                try {
                    $email->send();
                } catch (Exception $e) {
                    Injector::inst()->get(LoggerInterface::class)->error($e->getMessage(), $e);
                }
            }
        }
    }
}
