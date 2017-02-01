<?php
namespace SilverStripe\Forum\Models;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;

/**
 * Forum Thread Subscription: Allows members to subscribe to this thread
 * and receive email notifications when these topics are replied to.
 *
 * @package forum
 * @property DBDatetime LastSent
 * @method ForumThread Thread
 * @method Member Member
 */
class ForumThreadSubscription extends DataObject
{
    /** @var array */
    private static $db = array(
        "LastSent" => "Datetime"
    );

    /** @var array */
    private static $has_one = array(
        "Thread" => ForumThread::class,
        "Member" => Member::class
    );

    /**
     * Checks to see if a Member is already subscribed to this thread
     *
     * @param int $threadID The ID of the thread to check
     * @param int $memberID The ID of the currently logged in member (Defaults to Member::currentUserID())
     *
     * @return bool true if they are subscribed, false if they're not
     */
    public static function alreadySubscribed($threadID, $memberID = null)
    {
        if (!$memberID) {
            $memberID = Member::currentUserID();
        }

        $threadID = Convert::raw2sql($threadID);
        $memberID = Convert::raw2sql($memberID);

        if ($threadID == '' || $memberID == '') {
            return false;
        }

        return (bool)self::get()->filter(
            [
                'ThreadID' => $threadID,
                'MemberID' => $memberID
            ]
        )->count();
    }

    /**
     * Notifies everybody that has subscribed to this topic that a new post has been added.
     * To get emailed, people subscribed to this topic must have visited the forum
     * since the last time they received an email
     *
     * @param Post $post The post that has just been added
     */
    public static function notify(Post $post)
    {
        $list = self::get()->filter(
            [
                'ThreadID' => $post->ThreadID,
                'MemberID' => $post->AuthorID
            ]
        );

        if (!$list) {
            return;
        }

        foreach ($list as $obj) {
            $id = Convert::raw2sql((int)$obj->MemberID);

            // Get the members details
            $member     = Member::get()->byID($id);
            $adminEmail = Config::inst()->get(Email::class, 'admin_email');

            if ($member) {
                Email::create()
                    ->setFrom($adminEmail)
                    ->setTo($member->Email)
                    ->setSubject(_t('Post.NEWREPLY', 'New reply for {title}', array('title' => $post->Title)))
                    ->setHTMLTemplate('ForumMember_TopicNotification')
                    ->todo()
                    ->populateTemplate($member)
                    ->populateTemplate($post)
                    ->populateTemplate(
                        [
                            'UnsubscribeLink' => Controller::join_links(Director::absoluteBaseURL(), $post->Thread()->Forum()->Link(), 'unsubscribe', $post->ID)
                        ]
                    )
                    ->send();
            }
        }
    }

}
