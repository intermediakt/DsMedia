<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} 

if( is_user_logged_in() ){

	global $product;
	$product_id = $product->get_id();
	$downloads = array();
	$wc_downloads = $product->get_downloads();
	if ( $wc_downloads ){
		foreach( $wc_downloads as $key => $product){
			$itmp = array( "file-name" => $product['data']['name'], 'file-path' => $product['data']['file'] );
			array_push( $downloads, $itmp );
		}
		?>

		<style>
			.download-form{
				position: relative;
				display: flex;
				gap: 20px;
				margin-top: 15px;
				margin-bottom: 15px;
				width: auto;
				justify-content: left;
				align-items: center;
			}

			.text-stuff{
				font-family: "Roboto", Sans-serif; 
				font-size: .92rem; 
				font-weight: 400;
				text-wrap: nowrap !important;
			}

			.colle-label{
				color: rgb(22,22,22);
				line-height: 22px;
				font-size: 17px !important; 
			}

			.download-form select,
			.download-form button{
				max-height: 50px;
				padding: 15px 15px 15px 15px;
				height: auto;
				width: auto;
			}

			.download-form select{
				padding: 15px 25px 15px 15px !important;   
			}

		</style>

		<label class="text-stuff colle-label" for="file-select" > Επιλογή Κεφαλαίου: </label>
		<form method="POST" class="download-form"><?php
			wp_nonce_field( 'download_audio_book', '_dsmnonce' ); ?>
			<select name="download-choice" class="file-select text-stuff"><?php 
			foreach ( $downloads as $key => $each_download ) { 
				$file_name = normalize_file_name( $each_download[ 'file-name' ] ); ?>
				<option value="<?= $key ?>" > <?php
					include_once DSMEDIA_PLUGIN_DIR_PATH . 'utils/dsmedia_utils.php';
					echo $file_name; ?>
				</option><?php
			}?>
			</select>
			<button class="text-stuff" type="submit">Λήψη</button>
		</form>


		<?php
		//Handles Download Requests
		if( isset( $_POST[ 'download-choice' ]) && isset( $_POST[ '_dsmnonce' ] ) && wp_verify_nonce( $_POST['_dsmnonce'], 'download_audio_book' ) ){

			$choice = intval( $_POST[ 'download-choice' ] );

			if( strpos( $downloads[ $choice][ 'file-path' ], 'cloudfront.net' ) !== false ){
				include_once DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_download_handler.php';
				if( !DsMedia_Download_Handler::stream_file_from_cdn( $downloads[ $choice ][ 'file-path' ], $downloads[ $choice ][ 'file-name' ], $product_id ) ){
					error_log( "Download failed" );
				}
			}
			else{
				include_once WC_ABSPATH . 'includes/class-wc-download-handler.php';
				update_option( 'woocommerce_downloads_deliver_inline', 'off' );
				WC_Download_Handler::download_file_force( $downloads[ $choice ][ 'file-path' ], normalize_file_name( $downloads[ $choice ][ 'file-name' ] ) );
				update_option( 'woocommerce_downloads_deliver_inline', 'yes' );
				exit;
			}
		}
	}
	else{ ?>
		<h3 class="text-stuff"> Δέν υπάρχουν διαθέσημες λήψεις για το παρόν προϊόν <h3>
	 <?php }
	
}