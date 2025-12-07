<?php

namespace SilverStripe\Forum\Reports;

use SilverStripe\ORM\DB;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\SQLQuery;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Reports\Report;

class ForumReportMemberSignups extends Report
{

    public function title()
    {
        return _t('Forum.FORUMSIGNUPS', 'Forum Signups by Month');
    }

    public function sourceRecords($params = array())
    {
        $membersQuery = DataQuery::create();
        $membersQuery->setFrom('Member');
        $membersQuery->setSelect([
            'Month' => DB::getConn()->formattedDatetimeClause('Created', '%Y-%m'),
            'Signups' => 'COUNT(Created)'
        ]);
        $membersQuery->setGroupBy('Month');
        $membersQuery->setOrderBy('Month', 'DESC');
        $members = $membersQuery->execute();

        $output = ArrayList::create();
        foreach ($members as $member) {
            $member['Month'] = date('Y F', strtotime($member['Month']));
            $output->add(ArrayData::create($member));
        }
        return $output;
    }

    public function columns()
    {
        $fields = array(
            'Month' => 'Month',
            'Signups' => 'Signups'
        );

        return $fields;
    }

    public function group()
    {
        return 'Forum Reports';
    }
}
