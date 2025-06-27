<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class DsMedia_Plugin_Deactivate{
	public static function deactivate(){
		self::drop_plugin_data();
		flush_rewrite_rules();
	}

	public static function drop_plugin_data(){
		$to_drop_or_not = get_option( 'to_drop_or_not' );
		if( $to_drop_or_not === 'on' ){
			delete_option( 'to_drop_or_not' );
			require_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
			DsMedia_Plugin_DB_API::drop_table();
		}
		DsMedia_Plugin_DB_API::remove_value('cdn_cookie');
		DsMedia_Plugin_DB_API::remove_value('sig_prefix');
	}
}