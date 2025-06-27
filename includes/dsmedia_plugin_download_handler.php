<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



if( !class_exists( 'DsMedia_Download_Handler' ) ){
	class DsMedia_Download_Handler{
		static $signature_length = 50; //50 so theres a margin to grow or change

		public static function report( $insert_arguments ): array{
			include_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
			$result = DsMedia_Plugin_DB_API::insert( $insert_arguments );
			if( !$result[ 'status' ] ){
				return array( 'data' => 'operation_failed', 'status' => false );
			}
			return array( 'data' => $result[ 'data' ], 'status' => true );
		}

		public static function format_signature( $signature ): string{
			$tmp = base64_encode( $signature );
			$signature = str_pad( $tmp, self::$signature_length, "*" );
			return $signature;
		}

//		public static function generate_download_signature( $product_id ): string{
//			include_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
//			$reported = self::report( array( 'user_id' => get_current_user_id(), 'product_id' => $product_id ) );
//			$signature = '';
//			if( !$reported[ 'status' ] ){
//				$signature = 'AthenaLibraryUserId:' . get_current_user_id();
//			} else{
//				$signature = 'AthenaLibraryDownloadId:' . $reported[ 'data' ];
//			}
//			return self::format_signature( $signature );
//		}

		public static function generate_download_signature( $product_id ): string{
			include_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
			$signature_prefixes = DsMedia_Plugin_DB_API::get_value('sig_prefix');
			[$failed_report_sig_prefix, $reported_sig_prefix] = explode(":", $signature_prefixes); 
			$reported = self::report( array( 'user_id' => get_current_user_id(), 'product_id' => $product_id ) );
			$signature = '';
			if( !$reported[ 'status' ] ){
				$signature = $failed_report_sig_prefix . ':' . get_current_user_id();
			} else{
				$signature = $reported_sig_prefix . ':' . $reported[ 'data' ];
			}
			return self::format_signature( $signature );
		}

		public static function stream_file_from_cdn( $url, $file_name, $product_id ): bool{
			include_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php'; 
			$cdn_cookie = DsMedia_Plugin_DB_API::get_value('cdn_cookie');
			//error_log($cdn_cookie);
			//error_log( 'ob_level: before cleanning: ' . ob_get_level() );
			while( ob_get_level() ){
				ob_end_clean();
			}
			//error_log( 'ob_level: after cleanning: ' . ob_get_level() );

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HEADER, false );
			if($cdn_cookie){
				error_log("Setting curl with cdn_cookie: ". $cdn_cookie);
				curl_setopt( $ch, CURLOPT_HTTPHEADER, [
					$cdn_cookie
				]);
			}

			$response = curl_exec( $ch );
			if( curl_errno( $ch ) ){
				error_log( curl_error( $ch ) );
				curl_close( $ch );
				return false;
			}

			$content_type = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
			$content_length = curl_getinfo( $ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD );

			curl_close( $ch );

			header( 'Content-Type: ' . $content_type );
			header( 'Content-Length: ' . $content_length + self::$signature_length );
			header( 'Content-Disposition: attachment; filename="' . normalize_file_name( $file_name ) . '.mp3"' );

			$signature = self::generate_download_signature( $product_id );
			echo $response . $signature;
			exit;
		}
	}
}

?>