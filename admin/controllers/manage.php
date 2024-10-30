<?php
class Kahanit_WP_FW_Manage
{
	public function display()
	{
		include (KAHANIT_WP_FW_ADMIN_VIEWS_PATH . "/manage/view-manage.php");
	}

	public function enqueue_scripts()
	{
		$dev = false;

		wp_register_script('kahanit-wp-fw-ext-init', KAHANIT_WP_FW_JS_URL . "/manage/ext-init.php", array("jquery-ui-sortable"));

		if ($dev)
		{
			wp_register_script('kahanit-wp-fw-ext', "http://cdn.sencha.io/ext-4.1.1-gpl/ext-debug.js", array("kahanit-wp-fw-ext-init"));
			wp_register_script('kahanit-wp-fw-manager', KAHANIT_WP_FW_JS_URL . "/manage/app.js", array("kahanit-wp-fw-ext"));
		} else
		{
			wp_register_script('kahanit-wp-fw-ext', KAHANIT_WP_FW_JS_URL . "/extjs/ext.js", array("kahanit-wp-fw-ext-init"));
			wp_register_script('kahanit-wp-fw-manager', KAHANIT_WP_FW_JS_URL . "/manage/app-all.js", array("kahanit-wp-fw-ext"));
		}

		wp_enqueue_script('kahanit-wp-fw-manager');
	}

	public function enqueue_styles()
	{
		wp_register_style('kahanit-wp-fw-ext-all-gray-scoped', KAHANIT_WP_FW_JS_URL . "/extjs/resources/css/ext-all-gray-scoped.css");

		wp_enqueue_style('kahanit-wp-fw-ext-all-gray-scoped');
	}

	public function enqueue_styles_common()
	{
		wp_register_style('kahanit-wp-fw-common', KAHANIT_WP_FW_CSS_URL . "/common.css");

		wp_enqueue_style('kahanit-wp-fw-common');
	}
} ?>