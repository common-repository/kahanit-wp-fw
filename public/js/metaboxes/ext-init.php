<?php header("Content-type: application/x-javascript"); ?>
<?php require_once("../../../../../../wp-load.php"); ?>
Ext = {
	KahanitWPFWExt: 'http://cdn.sencha.io/ext-4.1.1-gpl/src',
	KahanitWPFWOptionsPages: '<?php echo get_option('siteurl') ?>/wp-content/plugins/kahanit-wp-fw/public/js/options-pages/app',
	KahanitWPFWUx: '<?php echo get_option('siteurl') ?>/wp-content/plugins/kahanit-wp-fw/public/js/extjs/ux',
	KahanitWPFWAdminAjax: '<?php echo get_option('siteurl') ?>/wp-admin/admin-ajax.php',	
	buildSettings:{
		"scopeResetCSS": true
	},
	controller: 'metaboxes',
	appHeight: 400
};