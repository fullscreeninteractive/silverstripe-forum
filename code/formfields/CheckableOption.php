<?php

class CheckableOption extends CompositeField {
	protected $childField, $checkbox;
		
	function __construct($checkName, $childField, $value = "", $readonly = false) {
		$this->name = $checkName;
		$this->checkbox = new CheckboxField($checkName, "", $value);
		if($readonly) $this->checkbox->setDisabled(true);
				
		$this->childField = $childField;
		
		$children = new FieldList(
			$this->checkbox,
			$this->childField			
		);
		
		parent::__construct($children);
	}
	
	function FieldHolder($properties = array()) {
		return FormField::FieldHolder($properties);
	}		
	
	function Message() {
		return $this->childField->Message();
	}
	
	function MessageType() {
		return $this->childField->MessageType();
	}
	
	function Title() {
		return $this->childField->Title();
	}
	
	function Field($properties = array()) {
		return $this->childField->Field() . ' ' . $this->checkbox->Field();
	}
}