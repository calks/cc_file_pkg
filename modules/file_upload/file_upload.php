<?php

	class filePkgFileUploadModule extends coreBaseModule {
		
		protected $field_hash;
		
		protected $task;
		protected $entity_name;
		protected $entity_id;
		protected $params;
		protected $field_name;
		
		protected $entity;
		protected $errors;
		
		protected $files;
		
		protected $max_files;
		
		protected $uploaded_file_info = array();
		
				
		public function run($params = array()) {
				
			$this->deleteTemporaryFiles();
			
			$this->field_hash = @array_shift($params);
			if (!$this->field_hash) return $this->terminate();
			 
			$session_name = filePkgHelperLibrary::getSessionName();
			if (!isset($_SESSION[$session_name][$this->field_hash])) return $this->terminate();
			
			$session_data = $_SESSION[$session_name][$this->field_hash];
			
			
			$this->entity_name = $session_data['entity_name'];
			$this->entity_id = $session_data['entity_id'];
			$this->params = $session_data['params'];
			$this->field_name = $session_data['field_name'];
						
			if (!$this->entity_name) return $this->terminate();
			
			if (!Application::entityExists($this->entity_name)) return $this->terminate();
			
			$this->entity = Application::getEntityInstance($this->entity_name);
			
			$this->files = array();
			$file = Application::getEntityInstance($this->getEntityName());
			
			$this->errors = array();
			
			$this->task = @array_shift($params);
			if (!$this->task) $this->task = 'list';
			
			
			if ($this->entity_id) {
				$this->entity = $this->entity->load($this->entity_id);
				if (!$this->entity) return $this->terminate();
			}
			
			
			$method = 'task' . coreNameUtilsLibrary::underscoredToCamel($this->task);
			if (method_exists($this, $method)) {
				$content = call_user_func(array($this, $method), $params);
				if (in_array($this->task, array('commit', 'count', 'files'))) return $content;
				
				$css_url = $this->getStaticFileUrl('/css/style.css');
				
				$smarty = Application::getSmarty();
				$smarty->assign('content', $content);
				$smarty->assign('css_url', $css_url);				
								
				$template_path = $this->getTemplatePath();
				die($smarty->fetch($template_path));
			}
			else {				
				return $this->terminate();
			}			
		}
		
		
		protected function terminate() {
			/*echo Debug::getTraceSummary();
			die('Something gone wrong...');*/
			return 'Something gone wrong...';
		}
		
		
		protected function getEntityName() {
			return 'file';
		}
		
		protected function genereteFileName() {
			return md5(uniqid());			
		}
		
		protected function getStoredFileDirectory($stored_filename) {			
			$dir = filePkgHelperLibrary::getStorageDirectory($stored_filename);
			$path = Application::getSitePath() . $dir;
			if (!is_dir($path)) {
				if (!mkdir($path, 0777, true)) {
					$this->errors[] = "Не удалось создать директорию для сохранения файла";
					return null;
				}
			}
			
			return $dir;						
		}
		
		
		protected function getValidExtensions() {
			return isset($this->params['valid_extensions']) ? $this->params['valid_extensions'] : array();
		}
		
		protected function isExtensionValid($extension) {
			$valid_extensions = $this->getValidExtensions();			
			return $valid_extensions ? in_array($extension, $valid_extensions) : true;			
		}
		
		
		protected function doUpload() {
			
			$fieldname = 'file';
			if (!isset($_FILES[$fieldname])) {
				$this->errors[] = "Файл не загружен";
				return false;	
			}			
			
			$res = $_FILES[$fieldname];
			if ($res['error'] != 0) {
				if ($res['error'] != 4) $this->errors[] = 'Ошибка при загрузке файла';	
				else $this->errors[] = "Файл не загружен";
				return false;
			}
			
			if (!is_uploaded_file(@$res['tmp_name'])) {
				$this->errors[] = 'Не найден загруженный файл';
				return false;
			}
				
			$uploaded_file_path = $res['tmp_name'];
			$original_file_name = $res['name'];
			
			
			$extension = filePkgHelperLibrary::getFileExtension($original_file_name);
			if (!$this->isExtensionValid($extension)) {
				$this->errors[] = "Недопустимый формат файла: $extension";
				return false;
			}
						
			$stored_file_name = $this->genereteFileName() . '.' . $extension;
			
			$stored_file_directory = $this->getStoredFileDirectory($stored_file_name);
			if (!$stored_file_directory) {				
				return false;
			}
			
			$stored_file_path = Application::getSitePath() . $stored_file_directory . '/' . $stored_file_name;
			
			if (!move_uploaded_file($uploaded_file_path, $stored_file_path)) {
				$this->errors[] = "Не удалось переместить загруженный файл";
				return false;                	
			}
			
			$stored_file_size = filesize($stored_file_path);
		
			$this->uploaded_file_info = array(
				'original_name' => $original_file_name,
				'extension' => $extension,
				'stored_name' => $stored_file_name,
				'directory' => $stored_file_directory,
				'size' => $stored_file_size
			);				

			return true;
		}
		
		
		
		protected function populateDbRecord(&$file) {
			
			$file->entity_name = $this->entity_name;
			$file->entity_id = $this->entity_id;			
			$file->stored_filename = $this->uploaded_file_info['stored_name'];
			$file->original_filename = $this->uploaded_file_info['original_name'];
			$file->size = $this->uploaded_file_info['size'];
			$file->field_hash = $this->field_hash;
			$file->is_temporary = 1;
			$file->field_name = $this->field_name;
		
		}
				
		protected function postProcess() {
			
		}
		
		protected function taskUpload($params=array()) {
			
			if ($this->doUpload()) {				
				$this->postProcess();
				$file = Application::getEntityInstance($this->getEntityName());
				$this->populateDbRecord($file);
				$file->save();
				$redirect_url = Application::getSeoUrl("/{$this->getName()}/$this->field_hash");
				Redirector::redirect($redirect_url);								
			}
			else {
				return $this->taskList();
			}			
		}
		
		protected function taskDelete($params=array()) {
			$id_to_delete = (int)array_shift($params);
			if (!$id_to_delete) {
				$this->errors[] = "Не передан ID файла";
				return $this->taskList();	
			}
			
			$file = Application::getEntityInstance($this->getEntityName());
			$file = $file->load($id_to_delete);
			
			if (!$file) {
				$this->errors[] = "Файл не найден";
				return $this->taskList();				
			}
			
			if ($file->entity_name != $this->entity_name && $file->entity_id != $this->entity_id) {
				$this->errors[] = "Нельзя удалить файл, назначенный другому объекту";
				return $this->taskList();
			}
			
			$file->delete();
			return $this->taskList();
		}

		
		protected function deleteTemporaryFiles() {
			$file = Application::getEntityInstance($this->getEntityName());
			$table = $file->getTableName();

			$params['where'][] = "$table.is_temporary = 1";
			$params['where'][] = "$table.created < DATE_SUB(NOW(), INTERVAL 1 DAY)";
			
			$to_delete = $file->load_list($params);
			if (!$to_delete) return;

			foreach($to_delete as $d) {
				$d->delete();
			}			
		}
		
		
		protected function getListParams() {
			$file = Application::getEntityInstance($this->getEntityName());
			
			$file_list_params = array();
			$table = $file->getTableName();
			$safe_entity_name = addslashes($this->entity_name);
			$file_list_params['where'][] = "$table.entity_name='$safe_entity_name'";
			if ($this->entity_id) {
				$file_list_params['where'][] = "$table.entity_id='$this->entity_id'";				
			}
			else {
				$file_list_params['where'][] = "$table.field_hash='$this->field_hash'";
			}
			$sfield_name = addslashes($this->field_name);
			$file_list_params['where'][] = "$table.field_name='$sfield_name'";			
			
			return $file_list_params;
			
		}
		
		
		protected function getFilesList() {
			$file = Application::getEntityInstance($this->getEntityName());
			
			$file_list_params = $this->getListParams();
			$files = $file->load_list($file_list_params);
			if (!$files) return array();

			foreach($files as $file) {
				$file->delete_link = Application::getSeoUrl("/{$this->getName()}/$this->field_hash/delete/$file->id");				
				$file->size_str = filePkgHelperLibrary::getFileSizeStr($file->size);
			}
			
			return $files;			
		}
		
		protected function taskList($params=array()) {
			
			$this->files = $this->getFilesList();

			$form_action = Application::getSeoUrl("/{$this->getName()}/$this->field_hash/upload");
			
			$smarty = Application::getSmarty();
			$smarty->assign('form_action', $form_action);
			$smarty->assign('errors', $this->errors);
			$smarty->assign('files', $this->files);
			
			$max_files = isset($this->params['max_files']) ? (int)$this->params['max_files'] : 0;
			$files_limit_reached = $max_files && $max_files <= count($this->files); 
			$smarty->assign('files_limit_reached', $files_limit_reached);
			
			$smarty->assign('upload_max_size', filePkgHelperLibrary::getUploadMaxSize());
			
			$template = $this->getTemplatePath('list');
			
			return $smarty->fetch($template);
		}
		
		
		protected function taskCount($params=array()) {
			$file = Application::getEntityInstance($this->getEntityName());			
			$file_list_params = $this->getListParams();
			return (int)$file->count_list($file_list_params);
		}
		
		protected function taskCommit($params=array()) {
			$entity_id = (int)@array_shift($params);
			if (!$entity_id) return;
			
			$file = Application::getEntityInstance($this->getEntityName());			
			$file_list_params = $this->getListParams();
			$files = $file->load_list($file_list_params);
			if (!$files) return;
			
			$ids_to_commit = array();
			foreach($files as $f) {
				if ($f->is_temporary) $ids_to_commit[] = $f->id;
			}
			if (!$ids_to_commit) return;
			
			$ids_to_commit = implode(',', $ids_to_commit);
			$table = $file->getTableName();
			
			$db = Application::getDb();
			
			$db->execute("
				UPDATE $table 
				SET is_temporary=0, entity_id=$entity_id
				WHERE id IN($ids_to_commit)
			");
		}
		
		protected function taskFiles($params=array()) {						
			$file = Application::getEntityInstance($this->getEntityName());			
			$file_list_params = $this->getListParams();			
			$files = $file->load_list($file_list_params);

			$out = array();
			foreach($files as $f) {
				$out[] = $f->stored_filename;	
			}
			
			return $out;
		}
		
	}