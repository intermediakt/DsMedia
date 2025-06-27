<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class DsMedia_Plugin_Activate{
	public static function activate(){
		require_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
		DsMedia_Plugin_DB_API::create_table();
		DsMedia_Plugin_DB_API::set_value("sig_prefix", "DsMediaUserId:DsMediaDownloadId");
		flush_rewrite_rules();
	}
}

