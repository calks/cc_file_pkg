<?php

	Application::loadLibrary('olmi/field');

	class TEntityFilesField extends THiddenField {
		
		protected $entity_name;
		protected $entity_id;
		protected $params;
		protected $hash;
		
		
		function __construct($aName, $entity_name=null, $entity_id=null, $params=array()) {
			parent::TField($aName);
			$this->entity_name = $entity_name;
			$this->entity_id = $entity_id;
			$this->params = $params;
			
			$this->Value = $this->hash = md5(uniqid());
		}

		function GetAsHTML() {
			
			if (!$this->Value) $this->Value = $this->hash;
			
			$hidden_field_html = THiddenField::GetAsHTML();
			
			$iframe_src = $this->getIframeSrc();
			$iframe_width = isset($this->params['width']) ? $this->params['width'] : 800;
			$iframe_height = isset($this->params['height']) ? $this->params['height'] : 300;
			
			echo $hidden_field_html;

			echo "<iframe src=\"$iframe_src\" width=\"$iframe_width\" height=\"$iframe_height\" border=0 style=\"border: none\"></iframe>";
			
		}
		
		function getIframeSrc() {
			return Application::getSeoUrl("/file_upload/$this->Value");
		}
		
	}