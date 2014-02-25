<?php

/**
 * Forum Subscription: Allows members to subscribe to a Forum
 * and receive email notifications when forums are modified.
 *
 * @package forum
 */

class Forum_Subscription extends DataObject {
	
	private static $db = array(
		"LastSent" => "SS_Datetime"
	);

	private static $has_one = array(
		"Forum" => "ForumSubscripton",
		"Member" => "Member"
	);
	
	
	/**
	 * Checks to see if a Member is already subscribed to this forum
	 *
	 * @param int $forumID The ID of the thread to check
	 * @param int $memberID The ID of the currently logged in member (Defaults to Member::currentUserID())
	 *
	 * @return bool true if they are subscribed, false if they're not
	 */
	static function already_subscribed($forumID, $memberID = null) {
		if(!$memberID) $memberID = Member::currentUserID();
		$SQL_forumID = Convert::raw2sql($forumID);
		$SQL_memberID = Convert::raw2sql($memberID);

		if($SQL_forumID=='' || $SQL_memberID=='')
			return false;
		
		return (DB::query("
			SELECT COUNT(\"ID\") 
			FROM \"Forum_Subscription\" 
			WHERE \"ForumID\" = '$SQL_forumID' AND \"MemberID\" = $SQL_memberID"
		)->value() > 0) ? true : false;
	}
	
	/**
	 * Notifies everybody that has subscribed to this forum that a new post has been added.
	 * To get emailed, people subscribed to this Forum must have visited the forum 
	 * since the last time they received an email
	 *
	 * @param Post $post The post that has just been added
	 */
	static function notify(Post $post) {
		$list = DataObject::get(
			"Forum_Subscription",
			"\"ForumID\" = '". $post->ForumID ."' AND \"MemberID\" != '$post->AuthorID'"
		);
		
		if($list) {
			foreach($list as $obj) {
				$SQL_id = Convert::raw2sql((int)$obj->MemberID);

				// Get the members details
				$member = DataObject::get_one("Member", "\"Member\".\"ID\" = '$SQL_id'");
				$adminEmail = Config::inst()->get('Email', 'admin_email');

				if($member) {
					$email = new Email();
					$email->setFrom($adminEmail);
					$email->setTo($member->Email);
					$email->setSubject('New reply for ' . $post->Title);
					$email->setTemplate('ForumMember_ForumNotification');
					$email->populateTemplate($member);
					$email->populateTemplate($post);
					$email->populateTemplate(array(
						'UnsubscribeLink' => Director::absoluteBaseURL() . $post->Thread()->Forum()->Link() . '/unsubscribe/' . $post->ID
					));
					$email->send();
				}
			}
		}
	}
}
