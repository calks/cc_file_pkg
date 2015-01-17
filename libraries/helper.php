<?php

	class filePkgHelperLibrary {
		
		
		public static function getField($aName, $entity_name=null, $entity_id=null, $params=array()) {
			require_once Application::getSitePath() . '/packages/file/includes/field.php';
						
			$field = new TEntityFilesField($aName, $entity_name, $entity_id, $params);

			return $field;
		}
				
		
		public static function getSessionName() {
			return md5('file_uploader_fields');			
		}
		
		public static function getStorageDirectory($stored_filename) {
			return Application::getVarDirectory() . '/upload/' . substr($stored_filename, 0, 2) . '/' . substr($stored_filename, 2, 2);
		}
		
		public static function getFileExtension($filename) {
			return strtolower(substr($filename, 1 + strrpos($filename, '.')));
		}
		
		public static function getFileSizeStr($size) {
			if (!$size) {
				return '0';
			}
			elseif ($size < 1024) {
				return number_format($size, 0, ',', ' ') . ' байт';
			}
			elseif ($size < 1024*1024) {
				return number_format($size/1024, 2, ',', ' ') . ' Кб';
			}
			else {
				return number_format($size/1024/1024, 2, ',', ' ') . ' Мб';
			}						
		}
		
		public static function getFiles($field_hash) {			
			return Application::runModule('file_upload', array($field_hash, 'files'));
		}		
		
		public static function getFilesCount($field_hash) {			
			return Application::runModule('file_upload', array($field_hash, 'count'));
		}
		
		public static function commitUploadedFiles($field_hash, $entity_id) {
			Application::runModule('file_upload', array($field_hash, 'commit', $entity_id));			
		}
		
		
		public static function loadFiles(&$entity_or_array, $fieldname, $storage_entity_name='file') {			
			if (!$entity_or_array) return;
			$array_given = is_array($entity_or_array);
			if (!$array_given) $entity_or_array = array($entity_or_array);
			
			$mapping = array();
			
			$list_fieldname = $fieldname . '_list';
			
			foreach($entity_or_array as $entity) {
				$entity->$list_fieldname = array();
				$entity_name = $entity->getName();
				if (!isset($mapping[$entity_name])) $mapping[$entity_name] = array();
				$mapping[$entity_name][(int)$entity->id] = $entity;
			}
			
			$condition = array();
			
			foreach($mapping as $entity_name => $entities) {				
				$entity_name = addslashes($entity_name);
				$entity_ids = array_keys($entities);
				$entity_ids = implode(',', $entity_ids);
				
				$condition[] = "(entity_name='$entity_name' AND entity_id IN($entity_ids))";
			}
			
			$db = Application::getDb();
			$condition = implode(' OR ', $condition);
			
			$sfieldname = $fieldname;
			$condition .= " AND field_name='$sfieldname' ";
			
			$file = Application::getEntityInstance($storage_entity_name);
			
			$load_params = array();
			$load_params['where'] = array($condition);
			//$load_params['group_by'] = array('entity_name', 'entity_id'); 
			
			$data = $file->load_list($load_params);
			
			foreach($data as $item) {
				array_push($mapping[$item->entity_name][$item->entity_id]->$list_fieldname, $item);
			}
			
			if (!$array_given) $entity_or_array = array_shift($entity_or_array);
		}
		
		
		public static function loadFilesCount(&$entity_or_array, $count_fieldname, $storage_entity_name='file') {
			if (!$entity_or_array) return;
			$array_given = is_array($entity_or_array);
			if (!$array_given) $entity_or_array = array($entity_or_array);
			
			$object_fieldname = $count_fieldname . '_count';
			
			$mapping = array();
			
			foreach($entity_or_array as $entity) {
				$entity->$object_fieldname = 0;
				$entity_name = $entity->getName();
				if (!isset($mapping[$entity_name])) $mapping[$entity_name] = array();
				$mapping[$entity_name][(int)$entity->id] = $entity;
			}
			
			$condition = array();
			
			foreach($mapping as $entity_name => $entities) {				
				$entity_name = addslashes($entity_name);
				$entity_ids = array_keys($entities);
				$entity_ids = implode(',', $entity_ids);
				
				$condition[] = "(entity_name='$entity_name' AND entity_id IN($entity_ids))";
			}
			
			$db = Application::getDb();
			$condition = implode(' OR ', $condition);
			$file = Application::getEntityInstance($storage_entity_name);
			$table = $file->getTableName();
			
			$scount_fieldname = addslashes($count_fieldname); 
			
			$sql = "
				SELECT 
					entity_name,
					entity_id,
					COUNT(*) AS files_count 
				FROM $table
				WHERE $condition AND field_name='$scount_fieldname'
				GROUP BY entity_name, entity_id
			";
						
			$data = $db->executeSelectAllObjects($sql);
			
			foreach($data as $item) {				
				$mapping[$item->entity_name][$item->entity_id]->$object_fieldname = $item->files_count;				
			}
			
			if (!$array_given) $entity_or_array = array_shift($entity_or_array);
		}
		
		
		public static function getUploadMaxSize() {			
			$upload_max_filesize = ini_get('upload_max_filesize'); 
			$post_max_size = ini_get('post_max_size');
			
			$upload_max_filesize_int = (int)str_replace('M', '', $upload_max_filesize);
			$post_max_size_int = (int)str_replace('M', '', $post_max_size);
			
			$max_size = $upload_max_filesize_int < $post_max_size_int ? $upload_max_filesize_int : $post_max_size_int;			
			return self::getFileSizeStr($max_size*1024*1024);  
		}
		
		
		public static function getFileMetadata($stored_filename) {
			$path = Application::getSitePath() . self::getStorageDirectory($stored_filename) . '/' .$stored_filename;
			$getid3_path = Application::getSitePath() . "/packages/file/includes/getid3/getid3.php";
			if (!is_file($getid3_path)) return array('error' => 'getID3 not found');
			require_once $getid3_path;			
			$getID3 = new getID3();			
			return $getID3->analyze($path);
		}
		
		
		public static function deleteFiles($entity, $storage_entity_name='file') {
			$storage_entity = Application::getEntityInstance($storage_entity_name);
			$load_params = array();
			$entity_name = addslashes($entity->getName());
			$entity_id = (int)$entity->id;
			$table = $storage_entity->getTableName();
			
			$load_params['where'][] = "$table.entity_name='$entity_name'";
			$load_params['where'][] = "$table.entity_id='$entity_id'";
			
			$files = $storage_entity->load_list($load_params);
			
			foreach($files as $f) {
				$f->delete();
			}
		}
		
		
		public static function copyFiles($src_entity, $dest_entity, $storage_entity_name='file') {
			if (!$src_entity) return false;
			if (!$dest_entity) return false;
						
			$src_entity_id = $src_entity->getId();
			$dest_entity_id = $dest_entity->getId();
			if (!$src_entity_id) return false;
			if (!$dest_entity_id) return false;
			
			$src_entity_name = $src_entity->getName();
			$dest_entity_name = $dest_entity->getName();
			
			
			$file = Application::getEntityInstance($storage_entity_name);
			$table = $file->getTableName();
			$ssrc_entity_name = addslashes($src_entity_name);
			
			$load_params = array();
			$load_params['where'][] = "$table.entity_name='$ssrc_entity_name'";
			$load_params['where'][] = "$table.entity_id='$src_entity_id'";
			
			$data = $file->load_list($load_params);
			
			foreach($data as $item) {
				$file_path = Application::getSitePath() . self::getStorageDirectory($item->stored_filename) . '/' . $item->stored_filename;
				
				$extension = self::getFileExtension($item->stored_filename);
				$stored_filename = md5(uniqid());
				$storage_dir = Application::getSitePath() . self::getStorageDirectory($stored_filename);
				
				if (!is_dir($storage_dir)) {
					if (!@mkdir($storage_dir, 0777, true)) {
						die("copyFiles: Can't create directory $storage_dir");
					}
				}
				
				$storage_path = "$storage_dir/$stored_filename.$extension";
			
				if (!copy($file_path, $storage_path)) {
					die("copyFiles: Can't copy file");
				}
				
				
				$copied_file = Application::getEntityInstance('file');
				
				$copied_file->entity_name = $dest_entity_name;
				$copied_file->entity_id = $dest_entity_id;			
				$copied_file->stored_filename = "$stored_filename.$extension";
				$copied_file->original_filename = $item->original_filename;
				$copied_file->size = filesize($storage_path);
				$copied_file->field_hash = '';
				$copied_file->is_temporary = 0;
				$copied_file->field_name = $item->field_name;
				
				$copied_file->save();
				
			}
			
			return true;
			
		}
		
		public static function copyExistingFile($entity, $file_path, $field_name) {

			/*if (!is_file($file_path)) {
				return false;
				die("copyExistingFile: File $file_path not found");
			}*/
			
			$stored_filename = md5(uniqid());
			$extension = self::getFileExtension($file_path);
			
			$stored_filename = md5(uniqid());
			$storage_dir = Application::getSitePath() . self::getStorageDirectory($stored_filename);
			
			if (!is_dir($storage_dir)) {
				if (!@mkdir($storage_dir, 0777, true)) {
					die("copyExistingFile: Can't create directory $storage_dir");
				}
			}
			
			$storage_path = "$storage_dir/$stored_filename.$extension";
			
			if (!@copy($file_path, $storage_path)) {
				return false;
				die("copyExistingFile: Can't copy file");
			}
			
			$file = Application::getEntityInstance('file');
			
			$file->entity_name = $entity->getName();
			$file->entity_id = $entity->id;			
			$file->stored_filename = "$stored_filename.$extension";
			$file->original_filename = basename($file_path);
			$file->size = filesize($storage_path);
			$file->field_hash = '';
			$file->field_name = $field_name;
			$file->is_temporary = 0;
			
			$file->save();
			
			return $file->id;
		}
		
		
		public static function serveFile($stored_filename, $content_type, $filename) {
			$path = Application::getSitePath() . self::getStorageDirectory($stored_filename) . "/$stored_filename";
			
			$fsize = @filesize($path);			
			$fname = rawurlencode(self::getSafeFilename($filename));
			
			header("Content-type: $content_type");
			header("Content-length: $fsize");
			header("Content-Disposition: attachment; filename*=UTF-8''$fname");
			
			@copy($path, 'php://output');
			die();			
		}
		
		public static function getSafeFilename($filename) {
			$filename = coreFormattingLibrary::plaintext($filename);
			
			$remove = array(			 
				',',
				';',
				'`',
				'~',
				'%',
				'\\',
				'/',
				'«',
				'»',
				'<',
				'>',
				'"',
				"'",				
				"!",
				"?"
			);
			$filename = str_replace($remove, ' ', $filename);
			$filename = preg_replace('/\s+/', ' ', $filename);
			return $filename;
		}
		
		
		
	}
	
	
	