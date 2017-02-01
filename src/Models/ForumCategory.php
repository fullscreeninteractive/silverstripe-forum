<?php

namespace SilverStripe\Forum\Models;

use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forum\Pages\ForumHolder;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * A Forum Category is applied to each forum page in a has one relation.
 *
 * These will be editable via the {@link GridField} on the Forum object.
 *
 * @TODO    replace StackableOrder with the SortableGridField module implementation.
 *
 * @package forum
 * @property DBVarchar Title
 * @property DBVarchar StackableOrder
 * @method ForumHolder ForumHolder
 */
class ForumCategory extends DataObject
{
    /** @var array */
    private static $db = array(
        'Title'          => 'Varchar(100)',
        'StackableOrder' => 'Varchar(2)'
    );

    /** @var array */
    private static $has_one = array(
        'ForumHolder' => 'ForumHolder'
    );

    /** @var array */
    private static $has_many = array(
        'Forums' => 'Forum'
    );

    /** @var string */
    private static $default_sort = 'StackableOrder DESC';

    /**
     * Get the fields for the category edit/ add
     * in the complex table field popup window.
     *
     * @return FieldList
     */
    public function getCMSFieldsForPopup()
    {

        // stackable order is a bit of a workaround for sorting in complex table
        $values = array();
        for ($i = 1; $i < 100; $i++) {
            $values[$i] = $i;
        }

        return FieldList::create(
            TextField::create('Title'),
            DropdownField::create('StackableOrder', 'Select the Ordering (99 top of the page, 1 bottom)', $values)
        );
    }
}
