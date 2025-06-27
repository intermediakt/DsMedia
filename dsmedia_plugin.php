<?php
/**
 * Plugin Name: DsMedia Plugin
 * Description: Bypasses woo(s) checkout proccess for free digital downloadable products.
 * Version: 1.0.0
 * Author: Charalambos Rentoumis
 *
 * WC requires at least: 6.0
 * WC tested up to: 6.6.2
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
**/


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DSMEDIA_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'UPLOADS_DIRECTORY_PATH', wp_upload_dir()['basedir'] );


//Initializing the static variables 
include_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
DsMedia_Plugin_DB_API::init();

register_activation_hook( __FILE__, array( 'DsMedia_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DsMedia_Plugin', 'deactivate' ) );

if( !has_action( 'init', 'init_dsmedia' ) ){
	add_action( 'init', 'init_dsmedia' );
}

function init_dsmedia(){
	if( class_exists( 'DsMedia_Plugin' )){
		$ds_media = new DsMedia_Plugin();
	}
}


if( !class_exists( 'DsMedia_Plugin' ) ){

	class DsMedia_Plugin{

		public function __construct(){
			if( !has_action( 'admin_menu', array( $this, 'add_menu_item' ) ) ){
				add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
			}
			//Hook for display next to  image: woocommerce_product_meta_end (use priority 30 cause collections_plug uses 25)
			//Hook for display under image: woocommerce_simple_add_to_cart
			if( !has_action( 'woocommerce_simple_add_to_cart', array( $this, 'display_downloads_on_single_product_page' ) ) ){
				add_action ( 'woocommerce_simple_add_to_cart', array( $this, 'display_downloads_on_single_product_page' ), 30 );
			}

		}
		
		public static function activate(){
			include_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_activate.php';
			DsMedia_Plugin_Activate::activate();
		}

		public static function deactivate(){
			include_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_deactivate.php';
			DsMedia_Plugin_Deactivate::deactivate();
		}

		public function add_menu_item(){
			add_menu_page( 'DsMediaSettings', 'DsMedia', 'manage_options', 'dsm_plug', array( $this, 'render_settings') );
		}

		public function render_settings(){
			include_once DSMEDIA_PLUGIN_DIR_PATH . 'templates/admin/admin_panel.php';
		}

		public function display_downloads_on_single_product_page(){
			include DSMEDIA_PLUGIN_DIR_PATH . 'templates/display_downloads.php';
		}

	}


}