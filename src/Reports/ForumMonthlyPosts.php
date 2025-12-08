<?php

namespace FullscreenInteractive\SilverStripe\Forum\Reports;

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
        $posts = DB::query('SELECT DATE_FORMAT(Created, "%Y-%m") AS Month, COUNT(Created) AS Posts FROM "Post" GROUP BY Month ORDER BY Month DESC');

        return $posts;
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
