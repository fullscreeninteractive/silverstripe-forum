<?php

namespace FullscreenInteractive\SilverStripe\Forum\Model;

use SilverStripe\ORM\DataObject;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;

class ForumCategory extends DataObject
{
    private static $table_name = 'ForumCategory';

    private static string $singular_name = 'Forum Category';

    private static string $plural_name = 'Forum Categories';

    private static string $description = 'A category for forums';

    private static $db = [
        'Title' => 'Varchar(100)',
        'SortOrder' => 'Int'
    ];

    private static $has_one = [
        'Parent' => ForumHolder::class
    ];

    private static $has_many = [
        'Forums' => Forum::class
    ];

    private static $default_sort = [
        'SortOrder' => 'DESC'
    ];
}
