<?php

namespace SilverStripe\Forum\Forms;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\CompositeField;

class CheckableOption extends CompositeField
{
    protected $childField, $checkbox;

    public function __construct($checkName, $childField, $value = "", $readonly = false)
    {
        $this->name = $checkName;
        $this->checkbox = new CheckboxField($checkName, "", $value);
        if ($readonly) {
            $this->checkbox->setDisabled(true);
        }

        $this->childField = $childField;

        $children = new FieldList(
            $this->childField,
            $this->checkbox
        );

        parent::__construct($children);
    }

    public function FieldHolder($properties = array())
    {
        return FormField::FieldHolder($properties);
    }

    public function Message()
    {
        return $this->childField->Message();
    }

    public function MessageType()
    {
        return $this->childField->MessageType();
    }

    public function Title()
    {
        return $this->childField->Title();
    }

    public function Field($properties = array())
    {
        return $this->childField->Field() . ' ' . $this->checkbox->Field();
    }
}
