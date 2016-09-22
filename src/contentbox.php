<?php
/**
* 2015 emotionLoop
*
* NOTE
* You are free edit and play around with the module.
* Please visit contentbox.org for more information.
*
*  @author    Miguel Costa for emotionLoop
*  @copyright emotionLoop
*  @version   1.1.0
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  http://emotionloop.com
*  http://contentbox.org/
*/

if (!defined( '_PS_VERSION_' ))
	exit;



class CONTENTBOX extends Module
{
	public function __construct()
	{
		$this->name = 'contentbox';
		$this->description = 'Place your content everywhere!';
		$this->tab = 'front_office_features';
		$this->version = '1.1.0';
		$this->author = 'emotionLoop';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');
		$this->monolanguage_content = 'Contentbox_MONOLANGUAGE';
		$this->text_editor_content = 'Contentbox_TEXTEDITOR';
		$this->content_wrapper = 'Contentbox_CONTENTWRAPPER';
		$this->content_wrapper_class = 'Contentbox_CONTENTWRAPPER_CLASS';
		$this->content_wrapper_id = 'Contentbox_CONTENTWRAPPER_ID';
		$this->bootstrap = true;
		$this->_html = '';
		$this->complete_content_files_location = dirname(__FILE__).'/content/';
		$this->simple_content_files_location = $this->_path.'content/';
		$this->ignore_changes_content_changes = false;

		parent::__construct();

		$this->displayName = $this->l('contentBox');
		$this->description = $this->l('Place your content everywhere!');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

		//store selection
		$this->selected_store_id = (!$this->emptyTest( Tools::getValue('contentbox_shop_select') ) )
								? (int)Tools::getValue('contentbox_shop_select')
								: $this->context->shop->id;
		//language selection
		$posted_mono_language = (Tools::getValue('monolanguage') != false)? (int)Tools::getValue('monolanguage') : null;

		if (Configuration::get( $this->monolanguage_content ) || !empty( $posted_mono_language ))
			$this->selected_language_id = (int)Configuration::get('PS_LANG_DEFAULT');
		else
		{
			$this->selected_language_id = ( !$this->emptyTest( Tools::getValue('contentbox_language_select') ))
									? (int)Tools::getValue('contentbox_language_select')
									: $this->context->language->id;
		}

		if (Tools::getValue('contentbox_language_select') != false && $this->selected_language_id != Tools::getValue('contentbox_language_select'))
			$this->ignore_changes_content_changes = true;
	}

	public function install()
	{
		if (Shop::isFeatureActive())
			Shop::setContext(Shop::CONTEXT_ALL);

		if (!parent::install() || !$this->registerHook('header') || !$this->registerHook('footer') || !CONTENTBOXModel::createTables())
			return false;

		Configuration::updateValue($this->monolanguage_content, 0);
		Configuration::updateValue($this->text_editor_content, 1);

		Configuration::updateValue($this->content_wrapper, 0);
		Configuration::updateValue($this->content_wrapper_class, '');
		Configuration::updateValue($this->content_wrapper_id, '');

		$this->_clearCache('template.tpl');

		return true;
	}

	public function uninstall()
	{
		$this->_clearCache('template.tpl');
		if (!parent::uninstall()
			|| !CONTENTBOXModel::DropTables())
		{

			Configuration::deleteByName($this->monolanguage_content);
			Configuration::deleteByName($this->text_editor_content);

			Configuration::deleteByName($this->content_wrapper);
			Configuration::deleteByName($this->content_wrapper_class);
			Configuration::deleteByName($this->content_wrapper_id);

			return false;
		}
		return true;
	}

	public function __call($method, $args)
	{
		//if method exists
		if (function_exists($method))
			return call_user_func_array($method, $args);

		//if head hook: add the css and js files
		if ($method == 'hookdisplayHeader')
			return $this->hookHeader( $args[0] );

		//check for a call to an hook
		if (strpos($method, 'hook') !== false)
			return $this->genericHookMethod( $args[0] );

	}

	public function addFilesToTemplate($path = null)
	{
		$result = true;
		//list the active files to add
		$files_data = CONTENTBOXModel::getFilesInUse( $this->selected_store_id, $this->selected_language_id );
		if (empty( $files_data ) || gettype( $files_data['files'] ) != null)
			return $result;

		$files = Tools::jsonDecode( $files_data['files'] );

		if (empty( $files ))
			return $result;

		if (empty( $path ))
			$path = $this->simple_content_files_location;

		foreach ($files as $file)
		{
			switch ($file->extension)
			{
				case 'js':
					$this->context->controller->addJs($path.'/'.$file->name, 'all');
				break;
				case 'css':
					$this->context->controller->addCss($path.'/'.$file->name, 'all');
				break;
			}
		}

	}
	public function genericHookMethod()
	{
		$content_query = CONTENTBOXModel::getContent( $this->selected_store_id, $this->selected_language_id );
		$pre_content = '';
		$pos_content = '';

		$content_wrapper = Configuration::get($this->content_wrapper);

		if (!empty( $content_wrapper ) && !is_numeric( $content_wrapper ))
		{
			$content_wrapper_class = Configuration::get($this->content_wrapper_class);
			$content_wrapper_id = Configuration::get($this->content_wrapper_id);

			$pre_content = '<'.$content_wrapper.' ';
			$pre_content .= ( !empty( $content_wrapper_class ) )?' class="'.$content_wrapper_class.'" ' : '';
			$pre_content .= ( !empty( $content_wrapper_id ) )?' id="'.$content_wrapper_id.'" ' : '';
			$pre_content .= '>';
			$pos_content = '</'.$content_wrapper.'>';
		}

		$this->context->smarty->assign(
				array( 'content' => $pre_content.$content_query['content_text'].$pos_content )
			);
		return $this->display(__FILE__, 'views/templates/front/template.tpl');

	}

	public function hookHeader()
	{
		$this->addFilesToTemplate( $this->_path.'content/' );
	}

	public function getContent()
	{
		if (!is_writable( $this->complete_content_files_location ))
			$this->_html .= $this->displayError( 'FOLDER PERMISSIONS ERROR: <br/>writing access denied on '.$this->simple_content_files_location.' <br/> ' );

		$this->processSubmit();
		return $this->displayForm();
	}

	public function processSubmit()
	{
		if (Tools::isSubmit('submit'.$this->name))
		{
			//remove file
			if (!$this->emptyTest( Tools::getValue('delete_file') ))
			{
				$tmp_file = strip_tags(Tools::getValue('delete_file'));
				unlink($this->complete_content_files_location.$tmp_file);
				$this->_html .= $this->displayConfirmation( 'File Deleted' );
			}

			//if change shop or change language submit
			if (!$this->emptyTest( Tools::getValue('ignore_changes') ))
				return true;

			//upload file
			if ($this->hasFile())
				$this->processFileUpload();

			//if the posted language different from the current language -> ignore content changes
			if ((Tools::getValue('contentbox_language_select') != false && (int)Tools::getValue('contentbox_language_select') == $this->selected_language_id )
				|| (Tools::getValue('contentbox_language_select') == false
				&& (int)Configuration::get('PS_LANG_DEFAULT') == $this->selected_language_id ))
			{
				//store the content
				if (Tools::getValue('content_text') !== false && $this->ignore_changes_content_changes == false)
					CONTENTBOXModel::setContent( Tools::getValue('content_text'), $this->selected_store_id, $this->selected_language_id );

				//store the files to be used
				if (Tools::getValue('headerFiles') !== false)
					CONTENTBOXModel::setFiles(
							$this->processFilesList( Tools::getValue('headerFiles'), true ),
							$this->selected_store_id,
							$this->selected_language_id );
			}

			//store the developer configurations
			if (Tools::getIsset( 'monolanguage' ))
				Configuration::updateValue($this->monolanguage_content, (int)Tools::getValue('monolanguage'));

			if (Tools::getIsset( 'use_editor' ))
				Configuration::updateValue($this->text_editor_content, (int)Tools::getValue('use_editor'));

			if (Tools::getIsset( 'use_content_wrapper' ))
				Configuration::updateValue($this->content_wrapper, Tools::getValue('use_content_wrapper'));

			if (Tools::getIsset( 'content_wrapper_class' ))
				Configuration::updateValue($this->content_wrapper_class, Tools::getValue('content_wrapper_class'));

			if (Tools::getIsset( 'content_wrapper_id' ))
				Configuration::updateValue($this->content_wrapper_id, Tools::getValue('content_wrapper_id'));

		}
	}

	private function processFileUpload()
	{
		//test files folder permissions
		if (!is_writable( $this->complete_content_files_location ))
			return false;

		if (file_exists($this->complete_content_files_location.$_FILES['upload_file']['name']))
		{
			$tmp_name = explode('.', $_FILES['upload_file']['name']);
			$tmp_ext = end($tmp_name);
			array_pop($tmp_name);
			$tmp_name = implode('.', $tmp_name);
			$tmp_new_img_name = $this->complete_content_files_location.$tmp_name;

			$control_loop = false;
			$tmp_i = 1;
			while ($control_loop == false)
			{
				if (file_exists($tmp_new_img_name.'('.$tmp_i.').'.$tmp_ext ))
					++ $tmp_i;
				else
				{
					$_FILES['upload_file']['name'] = $tmp_name.'('.$tmp_i.').'.$tmp_ext;
					$control_loop = true;
				}
			}
		}

		$move_result = move_uploaded_file($_FILES['upload_file']['tmp_name'], $this->complete_content_files_location.$_FILES['upload_file']['name']);

		if (empty( $move_result ))
			$this->_html .= $this->displayError( 'UPLOAD ERROR: <br/> There was an unknown error. Please try again.<br/> ' );
		else
			$this->_html .= $this->displayConfirmation( 'File Uploaded' );
	}

	public function displayForm()
	{
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		//use text editor
		$use_text_editor = (int)Configuration::get($this->text_editor_content);

		$content_wrapper = Configuration::get($this->content_wrapper);
		$content_wrapper_class = Configuration::get($this->content_wrapper_class);
		$content_wrapper_id = Configuration::get($this->content_wrapper_id);

		$shops_list = $this->getShopsList();
		$languages_list = $this->getLanguagesList();
		$files_list = $this->getFiles();
		$fields_form = array();
		$fields_form[]['form'] = array(
				'input' => array(
					array(
						'name' => 'topform',
						'type' => 'topform',
						'shops' => $shops_list,
						'current_shop_id' => $this->selected_store_id,
						'languages' => $languages_list,
						'current_language_id' => $this->selected_language_id,
						'monolanguage' => Configuration::get($this->monolanguage_content),
						'label' => ';)',
						'logoImg' => $this->_path.'img/contentbox_logo.png',
						'moduleName' => $this->displayName,
						'moduleDescription' => $this->description,
						'moduleVersion' => $this->version,
					),
				),
			);
		$fields_form[]['form'] = array(
				'tinymce' => true,
				'legend' => array(
					'title' => $this->l('Content Configuration'),
				),
				'input' => array(
					array(
						'type' => 'textarea',
						'name' => 'content_text',
						'label' => $this->l("Module's Content"),
						'cols' => 50,
						'rows' => 20,
						'class' => ( !empty( $use_text_editor ) )? 'rte' : '',
						'autoload_rte' => ( !empty( $use_text_editor ) )? true :false,
					),
					array(
						'name' => 'files_area',
						'type' => 'files_area',
						'label' => $this->l("Module's Files"),
						'files' =>  $files_list,
						'path' => $this->_path,
						'imagesExtensions' => array( 'jpg','gif','png' ),
					),
					array(
						'type' => 'file',
						'name' => 'upload_file',
						'path' => $this->_path,
						'imagesExtensions' => array( 'jpg','gif','png' ),
					),

				),
				'submit' => array(
					'title' => $this->l('Save'),
				),
			);

		$fields_form[]['form'] = array(
				'legend' => array(
					'title' => $this->l('Developer Configurations'),
				),
				'input' => array(
					array(
						'type' => 'select',
						'name' => 'monolanguage',
						'label' => $this->l('Use only the main language settings'),
						'options' => array(
							'query' => array(
								array(
									'value' => 0,
									'text' => $this->l('No'),
								),
								array(
									'value' => '1',
									'text' => $this->l('Yes'),
								),
							),
							'id' => 'value',
							'name' => 'text'
						)
					),
					array(
						'type' => 'select',
						'name' => 'use_editor',
						'label' => $this->l('Use Text Editor'),
						'options' => array(
							'query' => array(
								array(
									'value' => 0,
									'text' => $this->l('No'),
								),
								array(
									'value' => '1',
									'text' => $this->l('Yes'),
								),
							),
							'id' => 'value',
							'name' => 'text'
						)
					),
					array(
						'type' => 'select',
						'name' => 'headerFiles[]',
						'label' => $this->l('Load Files on HTML Header'),
						'desc' => $this->l('Please select above the files to be used.'),
						'multiple' => true,
						'options' => array(
							'query' => $this->filterFiles( $files_list ),
							'id' => 'name',
							'name' => 'name'
						)
					),
					array(
						'type' => 'select',
						'name' => 'use_content_wrapper',
						'label' => $this->l('Use a Content Wrapper'),
						'options' => array(
							'query' => array(
								array(
									'value' => 0,
									'text' => $this->l('No'),
								),
								array(
									'value' => 'div',
									'text' => $this->l('&lt;div&gt;'),
								),
								array(
									'value' => 'article',
									'text' => $this->l('&lt;article&gt;'),
								),
								array(
									'value' => 'blockquote',
									'text' => $this->l('&lt;blockquote&gt;'),
								),

								array(
									'value' => 'figure',
									'text' => $this->l('&lt;figure&gt;'),
								),
								array(
									'value' => 'footer',
									'text' => $this->l('&lt;footer&gt;'),
								),
								array(
									'value' => 'hgroup',
									'text' => $this->l('&lt;hgroup&gt;'),
								),
								array(
									'value' => 'main',
									'text' => $this->l('&lt;main&gt;'),
								),
								array(
									'value' => 'menu',
									'text' => $this->l('&lt;menu&gt;'),
								),
								array(
									'value' => 'nav',
									'text' => $this->l('&lt;nav&gt;'),
								),
								array(
									'value' => 'p',
									'text' => $this->l('&lt;p&gt;'),
								),
								array(
									'value' => 'section',
									'text' => $this->l('&lt;section&gt;'),
								),
								array(
									'value' => 'span',
									'text' => $this->l('&lt;span&gt;'),
								),
							),
							'id' => 'value',
							'name' => 'text'
						)
					),
					array(
						'type' => 'text',
						'class' => 'content_wrapper_class',
						'name' => 'content_wrapper_class',
						'disabled' => ( (empty( $content_wrapper ))? true:false ),
						'label' => $this->l('Content Wrapper Class'),
						'desc' => $this->l('Place the Content Wrapper .class here.'),
					),
					array(
						'type' => 'text',
						'class' => 'content_wrapper_id',
						'name' => 'content_wrapper_id',
						'disabled' => ( (empty( $content_wrapper ))? true:false ),
						'label' => $this->l('Content Wrapper ID'),
						'desc' => $this->l('Place the Content Wrapper #id here.'),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				),
			);
		$helper = new HelperForm();

		// Module, t    oken and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;	 // false -> remove toolbar
		$helper->toolbar_scroll = true;	 // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
				array(
					'desc' => $this->l('Save'),
					'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
					'&token='.Tools::getAdminTokenLite('AdminModules'),
				),
			'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);
		// Load current value
		$content_query = CONTENTBOXModel::getContent( $this->selected_store_id, $this->selected_language_id );

		$content_field = ( !empty( $content_query ) )? $content_query['content_text'] : '';

		$helper->fields_value['content_text'] = $content_field;

		$helper->fields_value['monolanguage'] = Configuration::get($this->monolanguage_content);
		$helper->fields_value['use_editor'] = $use_text_editor;

		$helper->fields_value['headerFiles[]'] = $this->processSelectFilesForMultiselect(
																CONTENTBOXModel::getFilesInUse( $this->selected_store_id, $this->selected_language_id )
																);

		$helper->fields_value['use_content_wrapper'] = $content_wrapper;
		$helper->fields_value['content_wrapper_class'] = $content_wrapper_class;
		$helper->fields_value['content_wrapper_id'] = $content_wrapper_id;

		if (Tools::getIsset( $this->context ) && Tools::getIsset( $this->context->controller ))
		{
			$this->context->controller->addJs($this->_path.'/js/contentbox.js');
			$this->context->controller->addCss($this->_path.'/css/contentbox.css');

		}
		else
		{
			Tools::addJs($this->_path.'/js/contentbox.js');
			Tools::addCss($this->_path.'/css/contentbox.css');

		}
		return $this->_html.$helper->generateForm($fields_form);
	}

	private function filterFiles($files = array(), $accepted_files = array( 'css', 'js' ))
	{
		$result = array();

		$result[] = array( 'name' => 'none' );

		foreach ($files as $file)
		{
			if (in_array( $file['extension'], $accepted_files ))
				$result[] = $file;
		}
		return $result;
	}

	private function processSelectFilesForMultiselect($files_data = array())
	{
		if (gettype( $files_data['files'] ) != null)
			$files_data = $files_data['files'];

		$files_type = gettype( $files_data );

		$files = ( $files_type == 'string' )? Tools::jsonDecode($files_data) : $files_data;

		$result = array();

		if (!empty( $files ))
		{
			foreach ($files as $file)
				$result[] = ( is_object( $file ) )? $file->name : $file['name'];
		}

		if (empty( $result ))
			$result[] = 'none';

		return $result;
	}
	private function getLanguagesList()
	{
		$languages_list = array();
		$langs = Language::getLanguages(false);

		foreach ($langs as $lang)
			$languages_list[] = array( 'id_lang' => $lang['id_lang'], 'name' => $lang['name'] );

		return $languages_list;
	}

	private function getShopsList()
	{
		$shops_list = array();
		$shops = Shop::getShops();
		foreach ($shops as $shop)
			$shops_list[] = array( 'id_shop' => $shop['id_shop'], 'name' => $shop['name'] );

		return $shops_list;
	}

	private function getFiles()
	{
		$tmp_files_info = array();

		$handle = opendir($this->complete_content_files_location);

		while (false !== ($file = readdir($handle)))
		{
			if ($file == '.' || $file == '..')
				continue;

			$tmp_files_info[] = $this->extractFileInfo( $file );
		}
		closedir($handle);
		sort($tmp_files_info);

		return $tmp_files_info;
	}

	private function processFilesList($files_list, $join_by_extension = false)
	{
		if (!is_array( $files_list ))
			return array();

		$data_out = array();
		foreach ($files_list as $file)
			$data_out[] = $this->extractFileInfo( $file );

		if (!empty( $join_by_extension ))
		{
			$temp_container = array();

			//group by extension
			foreach ($data_out as $file)
			{
				if (empty( $file['extension'] ))
					continue;

				if (!array_key_exists( $file['extension'], $temp_container ))
					$temp_container[$file['extension']] = array();

				$temp_container[$file['extension']][$file['name']] = $file;
			}
			//create new dataOut
			$data_out = array();
			foreach ($temp_container as $files_array)
				$data_out = array_merge( $data_out, $files_array );
		}

		return $data_out;
	}

	private function extractFileInfo($fileName = null)
	{
		if (empty( $fileName ))
			return $fileName;

		$extension = pathinfo( $fileName, PATHINFO_EXTENSION );
		return array( 'name'=> $fileName, 'extension'=> $extension);
	}


	/**
	* methods bellow: Added to comply with the prestashop module validation 
	*/
	private function emptyTest($value_in)
	{
		return empty( $value_in )?true:false;
	}

	private function hasFile()
	{
		if (count($_FILES) <= 0)
			return false;
		else
		{
			$check_for_file = 'upload_file';

			foreach ($_FILES as $key => $file)
			{
				if ($check_for_file == $key && !empty( $file ) && !empty( $file['name'] ) && !empty( $file['type'] ))
					return true;
			}

			return false;
		}
	}
}


/**
* The model in the same file because of the module generator
*/

class CONTENTBOXModel extends ObjectModel
{

	public static $definition = array(
		'table' => 'contentbox',
		'primary' => 'file_id',
		'multishop' => true,
		'multilang' => true,
		'fields' => array(
			'file_id' =>       array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
			'file_name' =>    		array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true,'size' => 255),
			'file_type' =>		    array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
			'id_store' =>      array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true)
		),
	);

	public static function createTables()
	{
		//main table for the files
		return ( CONTENTBOXModel::createFilesTable()
				&& CONTENTBOXModel::createContentTable());
	}

	public static function dropTables()
	{
		$sql = 'DROP TABLE
			`'._DB_PREFIX_.self::$definition['table'].'_files`,
			`'._DB_PREFIX_.self::$definition['table'].'`
		';
		$result = Db::getInstance()->execute($sql);
		return $result;
	}

	public static function createContentTable()
	{
		$sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.self::$definition['table'].'`(
			`content_id` int(10) unsigned NOT NULL auto_increment,
			`content_text` text NOT NULL,
			`id_lang` int(10) unsigned NOT NULL,
			`id_store` int(10) unsigned NOT NULL default \'1\',
			PRIMARY KEY (`content_id`),
			UNIQUE KEY `id_lang_id_store` (`id_lang`,`id_store`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';

		return Db::getInstance()->execute($sql);
	}

	public static function setContent($content_text = null, $id_store = 1, $id_lang = null)
	{
		//special thanks to MarkOG (http://www.prestashop.com/forums/user/817367-markog/)
		$content_text = pSQL( $content_text, true );
		$id_lang = (int)$id_lang;
		$id_store = (int)$id_store;
		$sql = 'INSERT INTO `'._DB_PREFIX_.self::$definition['table'].'` (`content_text`,`id_lang`,`id_store`)
					VALUES ("'.$content_text.'","'.$id_lang.'","'.$id_store.'")
					ON DUPLICATE KEY UPDATE `content_text` = "'.$content_text.'"
				';

		return Db::getInstance()->execute( $sql );
	}

	public static function getContent($shop, $language)
	{
		$sql = 'SELECT * FROM '._DB_PREFIX_.self::$definition['table'].' WHERE `id_lang` = "'.(int)$language.'" and `id_store`="'.(int)$shop.'"';
		return Db::getInstance()->getRow($sql);
	}

	public static function createFilesTable()
	{
		//file_type 0 =>css, 1=> js, 2=>html
		$sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.self::$definition['table'].'_files`(
			`id` int(10) unsigned NOT NULL auto_increment,
			`id_store` int(10) unsigned NOT NULL ,
			`id_lang` int(10) unsigned NOT NULL,
			`files` text NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `id_lang_id_store` (`id_lang`,`id_store`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';

		return Db::getInstance()->execute($sql);
	}

	public static function getFilesInUse($id_store = 1, $id_lang = 1)
	{
		$sql = 'SELECT * FROM `'._DB_PREFIX_.self::$definition['table'].'_files` where `id_store`='.(int)$id_store.' and `id_lang`='.(int)$id_lang;
		return Db::getInstance()->getRow($sql);
	}

	public static function setFiles($files_list = null, $id_store = 1, $id_lang = null)
	{
		$files_list = Tools::jsonEncode( $files_list );
		$files = pSQL( $files_list );
		$id_lang = (int)$id_lang;
		$id_store = (int)$id_store;

		$sql = 'INSERT INTO `'._DB_PREFIX_.self::$definition['table'].'_files` (`files`,`id_lang`,`id_store`)
					VALUES ("'.$files.'","'.$id_lang.'","'.$id_store.'")
					ON DUPLICATE KEY UPDATE `files` = "'.$files.'"
				';

		return Db::getInstance()->execute( $sql );
	}
}