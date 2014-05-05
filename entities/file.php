<?php

	class filePkgFileEntity extends coreBaseEntity {
		
		public $entity_name;
		public $entity_id;
		public $created;
		public $stored_filename;
		public $original_filename;		
		public $mime_type;
		public $size;
		public $seq;
		public $field_name;
		public $field_hash;
		public $is_temporary;
		
		
		public function getTableName() {
			return 'files';
		}		
		
		protected function getSeq() {
			$table = $this->getTableName();
			$entity_name = addslashes($this->entity_name);
			$entity_id = (int)$this->entity_id;
			
			$db = Application::getDb();

			$where[] = "entity_name='$entity_name'";
			if ($entity_id) $where[] = "entity_id=$entity_id";
			$where = implode(' AND ', $where);

			return (int)$db->executeScalar("
				SELECT MAX(seq)+1
				FROM $table
				WHERE $where
			");
		}
		
		public function save() {
			$this->entity_id = (int)$this->entity_id;
			$this->seq = (int)$this->seq;
			$this->is_temporary = !(bool)$this->entity_id;
			$this->is_temporary = (int)$this->is_temporary;
			$this->size = (int)$this->size;
			if (!$this->seq) $this->seq = $this->getSeq();

			if (!$this->id) $this->created = date("Y-m-d H:i:s");
			
			return parent::save();
		}
		
		
		public function load_list($params=array()) {
			$list = parent::load_list($params);
			foreach($list as $item) {
				$item->url = Application::getSiteUrl() . filePkgHelperLibrary::getStorageDirectory($item->stored_filename) . '/' . $item->stored_filename; 
			}
			
			return $list;
		}
		
		public function delete() {
			$file_path = Application::getSitePath() . filePkgHelperLibrary::getStorageDirectory($this->stored_filename) . '/' . $this->stored_filename;
			@unlink($file_path);
			return parent::delete();			
		}
		
	}