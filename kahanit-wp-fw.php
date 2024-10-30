<?php
/**
 * Plugin Name: Kahanit WP FW
 * Plugin URI: http://www.kahanit.com/kahanit-wp-fw
 * Description: Easily create post types, taxonomies, sidebars, metaboxes, options pages, forms for metaboxes and options pages.
 * Author: Amit Sidhpura
 * Version: 1.0.3
 * Author URI: http://www.kahanit.com
 * License: GPL2+
 * Text Domain: kahanit-wp-fw
 */

defined('ABSPATH') or die('No direct script access.');

if(!extension_loaded('pdo_mysql'))
{
	function required_pdo_mysql_admin_notice()
	{
		echo '<div class="error"><p>Your PHP installation appears to be missing the PDO Mysql extension which is required by Kahanit WP FW.</p></div>';
	}

	add_action('admin_notices', 'required_pdo_mysql_admin_notice');
} else
{
	/**
	 * Loading Zend Framework
	 */
	set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__file__) . '/library');
	
	require_once ("library/Zend/Loader/Autoloader.php");
	Zend_Loader_Autoloader::getInstance();
	
	global $kwfdb, $kahanit_wp_fw_request;
	
	$kwfdb = new Zend_Db_Adapter_Pdo_Mysql(array(
		'host' => DB_HOST,
		'username' => DB_USER,
		'password' => DB_PASSWORD,
		'dbname' => DB_NAME));
	Zend_Db_Table_Abstract::setDefaultAdapter($kwfdb);
	
	$kahanit_wp_fw_request = new Zend_Controller_Request_Http();
	
	/**
	 * Constants
	 */
	define("KAHANIT_WP_FW_URL", plugin_dir_url(__file__));
	define("KAHANIT_WP_FW_CSS_URL", KAHANIT_WP_FW_URL . "public/css");
	define("KAHANIT_WP_FW_IMG_URL", KAHANIT_WP_FW_URL . "public/images");
	define("KAHANIT_WP_FW_JS_URL", KAHANIT_WP_FW_URL . "public/js");
	define("KAHANIT_WP_FW_ADMIN_URL", KAHANIT_WP_FW_URL . "admin");
	define("KAHANIT_WP_FW_LIB_URL", KAHANIT_WP_FW_URL . "library");
	
	define("KAHANIT_WP_FW_PATH", plugin_dir_path(__file__));
	define("KAHANIT_WP_FW_ADMIN_PATH", KAHANIT_WP_FW_PATH . "admin");
	define("KAHANIT_WP_FW_ADMIN_CONTROLLERS_PATH", KAHANIT_WP_FW_PATH . "admin/controllers");
	define("KAHANIT_WP_FW_ADMIN_MODELS_PATH", KAHANIT_WP_FW_PATH . "admin/models");
	define("KAHANIT_WP_FW_ADMIN_VIEWS_PATH", KAHANIT_WP_FW_PATH . "admin/views");
	define("KAHANIT_WP_FW_LIB_PATH", KAHANIT_WP_FW_PATH . "library");
	
	/**
	 * Include controllers
	 */
	require_once ("admin/controllers/manage.php");
	require_once ("admin/controllers/post-types.php");
	require_once ("admin/controllers/taxonomies.php");
	require_once ("admin/controllers/sidebars.php");
	require_once ("admin/controllers/metaboxes.php");
	require_once ("admin/controllers/options-pages.php");
	require_once ("admin/controllers/forms.php");
	
	class Kahanit_WP_FW_Admin
	{
		public $version = 103;
	
		public $db_version = 100;
	
		public $manage_obj;
	
		public $post_types_obj;
	
		public $taxonomies_obj;
	
		public $sidebars_obj;
	
		public $metaboxes_obj;
	
		public $options_pages_obj;
	
		public $forms_obj;
	
		public function __construct()
		{
			$this->install();
	
			$plugin_basename = plugin_basename(__file__);
			add_filter("plugin_action_links_$plugin_basename", array($this, 'manage_link'));
	
			$this->manage_obj = new Kahanit_WP_FW_Manage();
			$this->post_types_obj = new Kahanit_WP_FW_Post_Types();
			$this->taxonomies_obj = new Kahanit_WP_FW_Taxonomies();
			$this->sidebars_obj = new Kahanit_WP_FW_Sidebars();
			$this->metaboxes_obj = new Kahanit_WP_FW_Metaboxes();
			$this->options_pages_obj = new Kahanit_WP_FW_Options_Pages();
			$this->forms_obj = new Kahanit_WP_FW_Forms();
	
			add_action('init', array($this, 'init'));
			add_action('add_meta_boxes', array($this->metaboxes_obj, 'add_metaboxes'));
			$this->forms_obj->add_forms_shortcodes();
			add_action('wp_ajax_kahanit_wp_fw', array($this, 'router'));
		}
	
		public function init()
		{
			add_action('admin_menu', array($this, 'menu_items'), 0);
	
			// Register Post Types
			$this->post_types_obj->register_post_types();
	
			// Register Taxonomies
			$this->taxonomies_obj->register_taxonomies();
	
			// Register Sidebars
			$this->sidebars_obj->register_sidebars();
	
			add_action('save_post', array($this->metaboxes_obj, 'save_form_options'));
		}
	
		public function menu_items()
		{
			add_menu_page('Kahanit WP FW', 'Kahanit&trade;', 'manage_options', 'kahanit-wp-fw', array($this->manage_obj, 'display'),
				KAHANIT_WP_FW_IMG_URL . '/kahanit-wp-fw.png');
			$manage_page = add_submenu_page('kahanit-wp-fw', 'Kahanit WP FW', 'Manage', 'manage_options', 'kahanit-wp-fw', array($this->
					manage_obj, 'display'));
			add_action('admin_print_scripts-' . $manage_page, array($this->manage_obj, "enqueue_scripts"));
			add_action('admin_print_styles-' . $manage_page, array($this->manage_obj, "enqueue_styles"));
	
			// common styles across whole framework
			add_action('admin_print_styles', array($this->manage_obj, "enqueue_styles_common"));
	
			// Add Options Pages
			$this->options_pages_obj->add_options_pages();
		}
	
		public function router()
		{
			if ((isset($_GET['controller']) && !empty($_GET['controller'])) && (isset($_GET['method']) && !empty($_GET['method'])) &&
				current_user_can('manage_options'))
			{
				$controller_obj = $_GET['controller'] . '_obj';
				call_user_func(array($this->$controller_obj, $_GET['method']));
			}
		}
	
		public function manage_link($links)
		{
			$manage_link = '<a href="admin.php?page=kahanit-wp-fw">Manage</a>';
			array_unshift($links, $manage_link);
			return $links;
		}
	
		public function install()
		{
			global $wpdb, $kwfdb;
	
			$kwf_version = (int)get_option('kwf_version', 0);
			$kwf_db_version = (int)get_option('kwf_db_version', 0);
	
			if ($kwf_version != $this->version)
			{
				update_option('kwf_version', $this->version);
			}
	
			if ($kwf_db_version != $this->db_version)
			{
				update_option('kwf_db_version', $this->db_version);
	
				include_once ("db/" . $this->db_version . '.php');
	
				global $kwfdb_sql;
				$kwfdb->query(str_replace("{prefix}", $wpdb->prefix, $kwfdb_sql));
			}
		}
	}
	
	class Kahanit_WP_FW_Front
	{
		public $post_types_obj;
	
		public $taxonomies_obj;
	
		public $sidebars_obj;
	
		public function __construct()
		{
			$this->post_types_obj = new Kahanit_WP_FW_Post_Types();
			$this->taxonomies_obj = new Kahanit_WP_FW_Taxonomies();
			$this->sidebars_obj = new Kahanit_WP_FW_Sidebars();
	
			add_action('init', array($this, 'init'));
		}
	
		public function init()
		{
			// Register Post Types
			$this->post_types_obj->register_post_types();
	
			// Register Taxonomies
			$this->taxonomies_obj->register_taxonomies();
	
			// Register Sidebars
			$this->sidebars_obj->register_sidebars();
		}
	}
	
	if (is_admin())
		$kahanit_wp_fw_admin_obj = new Kahanit_WP_FW_Admin();
	else
		$kahanit_wp_fw_front_obj = new Kahanit_WP_FW_Front();
	
	function getParams($raw = false)
	{
		$rawData = '';
		$httpContent = fopen('php://input', 'r');
		while ($kb = fread($httpContent, 1024))
		{
			$rawData .= $kb;
		}
		if ($raw)
		{
			return $rawData;
		} else
		{
			$params = json_decode($rawData, true);
			return $params;
		}
	}
	
	function array_addslashes($data)
	{
		foreach ($data as $key => $value)
		{
			$data[$key] = preg_replace('/\n/', '\n', addslashes($value));
		}
	
		return $data;
	}
	
	function array_stripslashes($data)
	{
		foreach ($data as $key => $value)
		{
			$data[$key] = stripslashes($value);
		}
	
		return $data;
	}
	
	function array_htmlspecialchars($data)
	{
		foreach ($data as $key => $value)
		{
			$data[$key] = htmlspecialchars($value);
		}
	
		return $data;
	}
} ?>