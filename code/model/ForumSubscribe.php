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

}
