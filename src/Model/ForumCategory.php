<?php

namespace SilverStripe\Forum\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;

class ForumCategory extends DataObject
{
    private static $table_name = 'ForumCategory';

    private static $db = [
        'Title' => 'Varchar(100)',
        'StackableOrder' => 'Varchar(2)'
    ];

    private static $has_one = [
        'Parent' => ForumHolder::class
    ];

    private static $has_many = [
        'Forums' => Forum::class
    ];


    private static $default_sort = "\"StackableOrder\" DESC";

    /**
     * Get the fields for the category edit/ add
     * in the complex table field popup window.
     *
     * @return FieldList
     */
    public function getCMSFields_forPopup()
    {

        // stackable order is a bit of a workaround for sorting in complex table
        $values = array();
        for ($i = 1; $i < 100; $i++) {
            $values[$i] = $i;
        }

        return new FieldList(
            new TextField('Title'),
            new DropdownField('StackableOrder', 'Select the Ordering (99 top of the page, 1 bottom)', $values)
        );
    }
}
