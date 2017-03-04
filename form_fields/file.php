<?php
	
	class filePkgFileFormField extends coreBaseFormField {
	
		protected $entity_name; 
		protected $entity_id;
		protected $width = '100%';
		protected $height = 100;
		protected $max_files;
		protected $entity_existance_check = true;
		protected $valid_extensions = array();
		
		
		public function __construct($name) {
			parent::__construct($name);
			$this->SetValue(md5(uniqid()));
			$this->addClass('file-form-field');
		}
		
		public function setValue($value) {
			if (!$value) return;
			parent::setValue($value);
			//echo('setValue(' . $value . ')');
		}
		
		
		protected function getParamBlock() {
			return array(
				'field_name' => $this->field_name,
				'entity_name' => $this->entity_name,
				'entity_id' => $this->entity_id,
				'params' => array(
					'valid_extensions' => $this->valid_extensions,
					'max_files' => $this->max_files,
					'entity_existance_check' => $this->entity_existance_check
				)
			);
		}
		
		public function render() {
			
			$this->setSessionParams();
			
			$hidden_field_html = "<input type=\"hidden\" name=\"$this->field_name\" value=\"$this->value\" />";
			
			$iframe_src = Application::getSiteUrl() . $this->getIframeSrc();
			
			$iframe_width = $this->width;
			$iframe_height = $this->height;			
			$field_id = $this->value;
			
			$this->attr('id', $field_id);
			
			$page = Application::getPage();
			$page->addScript(coreResourceLibrary::getStaticPath('/js/file-field.js'));
			
			$attr_string = $this->getAttributesString();
			
			$iframe_html = "<iframe name=\"i$field_id\" src=\"$iframe_src\" width=\"$iframe_width\" height=\"$iframe_height\" border=0 style=\"border: none\"></iframe>";
			return "				
					<div $attr_string></div>
					
					$hidden_field_html
					
					<noscript>						
						$iframe_html
					</noscript>
				
					<script type=\"text/javascript\">
						jQuery(document).ready(function(){
							var im = new fileField('$this->field_name', '$field_id', '$iframe_src', '$iframe_width', '$iframe_height');
						});
					</script>				
			";			
		}
		
		protected function getIframeSrc() {
			return Application::getSeoUrl("/file_upload/$this->value");
		}
		
		
		protected function setSessionParams() {
			$session_name = filePkgHelperLibrary::getSessionName();
			if (!isset($_SESSION[$session_name])) $_SESSION[$session_name] = array();			
			$_SESSION[$session_name][$this->value] = $this->getParamBlock();
		}
		
		public function isEmpty() {
			$this->setSessionParams();
			return imagePkgHelperLibrary::getFilesCount($this->value) == 0;
		}
		
		
		
	}