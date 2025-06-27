<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * All functions returning data will return 
 * it in an array containing the data and the result
 * of the query if it was successful or not in a form like this:
 * array( 'data' => $data, 'status' => $status )
 * if status is true the query was successful and so the oposite
*/


if( !class_exists( 'DsMedia_Plugin_DB_API' ) ){
	class DsMedia_Plugin_DB_API{
		public static $plugins_tables_prefix = 'dsm_';
		public static $wp_tables_prefix;
		public static $database_table_name;

		public static $queries;

		public static function init(){
			global $wpdb;
			self::$wp_tables_prefix = $wpdb->prefix;
			self::$database_table_name = self::$wp_tables_prefix . self::$plugins_tables_prefix . 'ds_media';
			self::$queries = array(
				'create' => 'CREATE TABLE IF NOT EXISTS `' . self::$database_table_name . '` ( id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT NOT NULL, product_id BIGINT NOT NULL, ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP);',
				'drop' => 'DROP TABLE `' . self::$database_table_name . '`;',
				'insert' => 'INSERT INTO `' . self::$database_table_name . '` ( user_id, product_id ) VALUES ( %d, %d );',
				'get-all' => 'SELECT * FROM `' . self::$database_table_name . '`;',
				'get-by-id' => 'SELECT * FROM `' . self::$database_table_name . '` WHERE id = %d;',
				'count-downloads' => 'SELECT d.product_id,  p.post_title, COUNT(DISTINCT d.product_id) AS unique_downloads, COUNT(*) AS total_downloads FROM ' . self::$database_table_name . ' AS d JOIN ' . self::$wp_tables_prefix . 'posts AS p ON p.ID = d.product_id WHERE p.post_type = \'product\' GROUP BY d.product_id',
				'set-value' => 'INSERT INTO `' . $wpdb->prefix . 'posts` (post_author, post_content, post_type, post_title, post_excerpt, to_ping, pinged, post_content_filtered, post_status) VALUES (%d, \'%s\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\');',
				'update-value' => 'UPDATE `' . $wpdb->prefix . 'posts` SET post_content = \'%s\' WHERE post_type= \'%s\'',
				'get-value' => 'SELECT post_content FROM `' . $wpdb->prefix . 'posts` WHERE post_type = \'%s\'',
				'remove-value' => 'DELETE FROM `' . $wpdb->prefix . 'posts` WHERE post_type = \'%s\''
			);
		}

		public static function create_table(): bool{
			global $wpdb;
			$result = $wpdb->query( self::$queries[ 'create' ] );
			if( !$result ){
				return false;
			}
			return true;
		}

		public static function drop_table(): bool{
			global $wpdb;
			$result = $wpdb->query( self::$queries[ 'drop' ] );
			if( !$result ){
				return false;
			}
			return true;
		}

		public static function insert( $insert_arguments ): array{
			global $wpdb;
			$result = $wpdb->insert( 
				self::$database_table_name, 
				array(
					'user_id' => $insert_arguments[ 'user_id' ],
					'product_id' => $insert_arguments[ 'product_id' ] ) 
			);
			return array( 'data' => $wpdb->insert_id, 'status' => $result !== false );
		}

		public static function get_all(): array | bool{
			global $wpdb;
			$result = $wpdb->get_results( self::$queries[ 'get-all' ] );
			if( !$result ){
				return false;
			}
			return $result;
		}

		public static function get_by_id( $id ): array | bool{
			global $wpdb;
			$result = $wpdb->get_results( sprintf( self::$queries[ 'get-by-id' ], $id ) );
			if( !$result ){
				return false;
			}
			return $result;
		}

		public static function count_single_downloads(): array | bool{
			global $wpdb;
			$result = $wpdb->get_results( self::$queries[ 'count-downloads' ], ARRAY_A );
			if( !$result ){
				return false;
			}
			return $result;
		}

		public static function create_backup( $mode ): string | bool{
			global $wpdb;
			
			if ( $mode === 0 ) {
				$backup_file = WP_CONTENT_DIR . '/uploads/' . self::$plugins_tables_prefix . '_backup_' . date( "Y-m-d_H:i:s" ) . '.sql';

				$sql_dump = null;
				$rows = $wpdb->get_results( 'SELECT * FROM ' . self::$database_table_name, ARRAY_A );

				foreach ( $rows as $row ) {
					$values = array_map( function( $value ) {
						if ( is_numeric( $value ) ) {
							return $value;
						} elseif ( is_null( $value ) ) {
							return 'NULL';
						} else {
							return "'" . esc_sql( $value ) . "'";
						}
					}, array_values( $row ) );

					$sql_dump .= 'INSERT IGNORE INTO ' . self::$database_table_name . ' (' . implode( ',', array_keys( $row ) ) . ') VALUES ( ' . implode( ',', $values ) . ' );' . "\n";
				}

				$did_write = file_put_contents( $backup_file, $sql_dump );

				if ( !$did_write ) {
					return false;
				}

				return "Backup saved to: " . $backup_file;
			}else{
				$result = $wpdb->get_results( self::$queries[ 'get-all' ] );

				if( !$result ){
					return false;
				}

				$backup_file = WP_CONTENT_DIR . '/uploads/' . self::$plugins_tables_prefix . '_backup_' . date("Y-m-d_H:i:s") . '.json';
				$did_write = file_put_contents($backup_file, json_encode($result, JSON_PRETTY_PRINT));
				if( !$did_write ){
					return false;
				}

				return "Backup stored in $backup_file";
			}
		}

		public static function restore_from_backup( $backup_file, $mode ): bool{
			global $wpdb;
			if( $mode === 0 ){
				$sql = file_get_contents( $backup_file );
				$sql_queries = explode( "\n", $sql );
				foreach( $sql_queries as $query => $value ){
					if( !$value ){
						break;
					}
					$wpdb->query( $value );
				}
				if( $wpdb->last_error ){
					error_log( $wpdb->last_error );
					return false;
				}
				return true;
			}else{
				$json_data = file_get_contents($backup_file);
				$rows = json_decode($json_data, true);

				if (!is_array($rows)) return false;

				foreach( $rows as $row ){
					$wpdb->insert(self::$database_table_name, $row);
				}
				return true;
			}	
		}

		public static function set_value( $type, $value ){
			global $wpdb;
			$exists = self::get_value( $type );
			$result;
			if($exists){
				$result = $wpdb->query( sprintf( self::$queries['update-value'], $value, $type) );
			}else{
				$result = $wpdb->query( sprintf( 
					self::$queries['set-value'], 
					get_current_user_id(), 
					$value, //post_content
					$type, //post_type
					'', //post_title,
					'', //post_excerpt,
					'', //to_ping,
					'', //pinged,
					'', //post_content_filtered,
					'private' //post_status
				) );			
			}
			if(!$result){
				return false;
			}
			return true;
		}

		public static function get_value( $type ){
			global $wpdb;
			$result = $wpdb->get_results( sprintf( self::$queries["get-value"], $type )  );
			if(!$result){
				return false;
			}
			return $result[0]->post_content;
		}

		public static function remove_value( $type ){
			global $wpdb;
			$result = $wpdb->query( sprintf( self::$queries['remove-value'], $type ) );
			if(!$result){
				return false;
			}
			return true;
		}
	}
}