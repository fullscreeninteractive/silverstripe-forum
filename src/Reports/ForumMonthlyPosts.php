<?php

namespace FullscreenInteractive\SilverStripe\Forum\Reports;

use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\DB;
use SilverStripe\Reports\Report;

class ForumMonthlyPosts extends Report
{

    public function title()
    {
        return _t('Forum.FORUMMONTHLYPOSTS', 'Forum Posts by Month');
    }

    public function sourceRecords($params = [])
    {
        $postsQuery = SQLSelect::create();
        $postsQuery->setFrom('Post');
        $postsQuery->setSelect([
            'Month' => DB::get_conn()->formattedDatetimeClause('Created', '%Y-%m'),
            'Posts' => 'COUNT(Created)'
        ]);
        $postsQuery->setGroupBy('Month');
        $postsQuery->setOrderBy('Month', 'DESC');
        $posts = $postsQuery->execute();

        $output = ArrayList::create();
        foreach ($posts as $post) {
            $post['Month'] = date('Y F', strtotime($post['Month']));
            $output->add(ArrayData::create($post));
        }

        return $output;
    }

    public function columns()
    {
        $fields = [
            'Month' => 'Month',
            'Posts' => 'Posts'
        ];

        return $fields;
    }

    public function group()
    {
        return 'Forum Reports';
    }
}
