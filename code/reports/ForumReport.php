<?php
/**
 * Forum Reports.
 * These are some basic reporting tools which sit in the CMS for the user to view.
 * No fancy graphing tools or anything just some simple querys and numbers
 *
 * @package forum
 */

/**
 * Member Signups Report.
 * Lists the Number of people who have signed up in the past months categorized
 * by month.
 */
class ForumReport_MemberSignups extends SS_Report {

	public function title() {
		return _t('Forum.FORUMSIGNUPS', 'Forum Signups by Month');
	}

	public function sourceRecords($params = array()) {
		$membersQuery = new SQLQuery();
		$membersQuery->setFrom('Member');
		$membersQuery->selectField(DB::getConn()->formattedDatetimeClause('"Created"', '%Y %m'), 'Month');
		$membersQuery->selectField('COUNT("Created")', 'Signups');
		$membersQuery->setGroupBy('Month');
		$membersQuery->setOrderBy('Created', 'DESC');
		$members = $membersQuery->execute();

		$output = ArrayList::create();
		foreach ($members as $member) {
			$member['Month'] = date('Y F', strtotime($member['Created']));
			$output->add(ArrayData::create($member));
		}
		return $output;
	}

	public function columns() {
		$fields = array(
			'Month' => 'Month',
			'Signups' => 'Signups'
		);

		return $fields;
	}

	public function group() {
		return 'Forum Reports';
	}
}

/**
 * Member Posts Report.
 * Lists the Number of Posts made in the forums in the past months categorized
 * by month.
 */
class ForumReport_MonthlyPosts extends SS_Report {

	public function title() {
		return _t('Forum.FORUMMONTHLYPOSTS', 'Forum Posts by Month');
	}

	public function sourceRecords($params = array()) {
		$postsQuery = new SQLQuery();
		$postsQuery->setFrom('Post');
		$postsQuery->selectField(DB::getConn()->formattedDatetimeClause('"Created"', '%Y %m'), 'Month');
		$postsQuery->selectField('COUNT("Created")', 'Posts');
		$postsQuery->setGroupBy('Month');
		$postsQuery->setOrderBy('Created', 'DESC');
		$posts = $postsQuery->execute();

		$output = ArrayList::create();
		foreach ($posts as $post) {
			$post['Month'] = date('Y F', strtotime($post['Created']));
			$output->add(ArrayData::create($post));
		}
		return $output;
	}

	public function columns() {
		$fields = array(
			'Month' => 'Month',
			'Posts' => 'Posts'
		);

		return $fields;
	}

	public function group() {
		return 'Forum Reports';
	}
}
