<?php

if ( ! defined( 'ABSPATH' ) || !is_admin() ) {
	exit;
}


$entry_data = null;
$wp_user = null;
$active_menu_option = null;
$cdn_auth_cookie = null;
$backup_result = null;
$restore_result = null;
$did_post = false;
$signature_detected = false;
//Should change how responses appear maybe later update
$response_sig = '';
$response_cookie = '';


//Used to see which menu option was chosen by the user before
if( isset( $_POST[ 'menu-item-option' ] ) ){
	$active_menu_option = intval( $_POST[ 'menu-item-option' ] );
	update_user_meta( get_current_user_id(), 'dsm-active-menu-option', $active_menu_option);
} else {
		$saved_meta = get_user_meta( get_current_user_id(), 'dsm-active-menu-option', true );

		if ($saved_meta !== '') {
			$active_menu_option = intval( $saved_meta );
		} else {
			$active_menu_option = 0;
		}
}

//Handles file uploads to detect signatures related to the user who downloaded the file
if( isset( $_FILES[ 'file' ] ) && $_FILES[ 'file' ][ 'error' ] === UPLOAD_ERR_OK && isset( $_POST[ '_dsmnonce' ] ) && wp_verify_nonce( $_POST['_dsmnonce'], 'file_up' )){
	$did_post = true;
	$file = $_FILES[ 'file' ][ 'tmp_name' ];
	$file_size = $_FILES[ 'file' ][ 'size' ];
	$signature = '';
	if( $file_size > 50){
		$handle = fopen( $file, 'rb' );

		if( $handle ){
			fseek( $handle, -50, SEEK_END );
			$signature = fread( $handle, 50 );
			fclose( $handle );
		}
	}
	$signature = base64_decode( $signature );
	if( strpos( $signature, ':') !== false ){
		$signature = explode(':', $signature);
		$type = $signature[0];
		$id = $signature[1];
		
		if( str_contains( $type, 'DownloadId' ) ){
			$signature_detected = true;
			
			require DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
			$entry_data = DsMedia_Plugin_DB_API::get_by_id( $id );
			$wp_user = get_userdata( $entry_data[0]->user_id );
			
		} elseif( str_contains( $type, 'UserId') ){
			$signature_detected = true;
			$wp_user = get_userdata( $id );
		}
	}
}

//Handles the update of signature signing values
if( isset( $_POST[ 'set_sig' ] ) && isset( $_POST[ 'failed_report_sig' ] ) && isset( $_POST[ 'success_report_sig' ] ) && wp_verify_nonce( $_POST[ '_dsmnonce' ], 'store_sig_value') ){
	require DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
	$result = DsMedia_Plugin_DB_API::set_value('sig_prefix', $_POST[ 'failed_report_sig' ] . ":" . $_POST[ 'success_report_sig' ]);
	if(!$result){
		$response_sig = sprintf("Values %s and %s could not be stored", $_POST[ 'failed_report_sig' ], $_POST[ 'success_report_sig' ]);
	}
}

//Handles the update of the cookie used to retrive external files (*optional)
if( isset( $_POST[ 'store_cdn_cookie' ] ) && isset( $_POST[ 'cdn_auth_cookie' ] ) && wp_verify_nonce( $_POST[ '_dsmnonce' ], 'store_cdn_cookie') ){
	require DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
	$store_cdn_cookie = DsMedia_Plugin_DB_API::set_value( 'cdn_cookie', $_POST['cdn_auth_cookie'] );
	if(!$store_cdn_cookie){
		$response_cookie = sprintf("Cookie %s could not be stored", $_POST['cdn_auth_cookie'] );
	}
}

//Handles the Databse setting to either drop or not to drop the plugins tables
if( isset( $_POST[ 'submit_to_drop_or_not' ] ) && isset( $_POST[ '_dsmnonce' ] ) && wp_verify_nonce( $_POST['_dsmnonce'], 'drop_or_not' ) ){
	if( isset( $_POST[ 'to_drop_or_not_to_drop' ] ) && $_POST[ 'to_drop_or_not_to_drop' ] === 'on' ){
		error_log('Drop tables enabled');
		update_option( 'to_drop_or_not', 'on' );
	} else{
		update_option( 'to_drop_or_not', 'off' );
	}
} 


//Handles Backup creation
if( isset( $_POST[ 'create-backup' ] ) && isset( $_POST[ 'mode' ] ) && isset( $_POST[ '_dsmnonce' ] ) && wp_verify_nonce( $_POST[ '_dsmnonce' ], 'create-backup' ) ){
	require DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
	$created_backup = DsMedia_Plugin_DB_API::create_backup( intval( $_POST[ 'mode' ] ) );
	if( !$created_backup ){
		$backup_result = "Backup was not created please try again";
	} else{
		$backup_result = "Backup saved to: " . $created_backup;
	}
}


//Handles databse restoration by uploaded backup
if( isset( $_POST[ 'restore-backup' ] ) && isset( $_POST[ 'mode' ] ) && isset( $_FILES[ 'backup-file' ] ) && $_FILES[ 'backup-file' ][ 'error' ] === UPLOAD_ERR_OK && isset( $_POST[ '_dsmnonce' ] ) && wp_verify_nonce( $_POST[ '_dsmnonce' ], 'restore-backup' ) ){

	$upload_dir = WP_CONTENT_DIR . '/uploads/';
	$backup_file_path = $upload_dir . basename($_FILES['backup-file']['name']);

	$allowed_types = [ 'application/sql', 'application/octet-stream', 'text/plain', 'application/json' ];
	$file_type = mime_content_type( $_FILES[ 'backup-file' ][ 'tmp_name' ] );

	if (!in_array( $file_type, $allowed_types ) ){
		$restore_result = "Invalid file type. Only .sql and .json files are allowed.";
	}

	if ( move_uploaded_file( $_FILES[ 'backup-file' ][ 'tmp_name' ], $backup_file_path ) ) {
		$mode = ( pathinfo( $backup_file_path, PATHINFO_EXTENSION) === 'sql' ) ? 0 : 1;
		$restore_status = DsMedia_Plugin_DB_API::restore_from_backup( $backup_file_path, $mode );
		if( $restore_status ){
			$restore_result = 'Table restored successfully.';
		}else{
			$restore_result = 'Failed to restore the backup.';
		}
	} else{
		$restore_result = 'Faild to upload the backup file.';
	}
}?>

<script defer>
//can never be too sure
document.addEventListener( "DOMContentLoaded", function () {
	let form = document.querySelector('.collections_drop');
	if ( form ) {
		form.addEventListener( "submit", function ( event ) {
			let checkbox = document.querySelector( 'input[name="to_drop_or_not_to_drop"]' );
			if ( checkbox && checkbox.checked ) {
				let confirmAction = confirm( "Are you sure you want to enable this setting? This action may have serious consequences." );
				if ( !confirmAction ) {
					event.preventDefault();
				}
			}
		});
	}
});
</script>



<style type="text/css">
	.dsm-admin-panel{
		min-width: 100%;
		width: 100vw;
		position: relative;
		display: flex;
		flex-direction: column;
		align-items: flex-start; 
		padding: 20px 0; 
		gap: 20px; 
		overflow: auto; 
		font-family: "Roboto", Sans-serif; 
		background-color: #f8f9fa;
		color: #343a40;
	}

	.dsm-admin-panel-menu{
		margin-top: 20px;
		padding: 10px;
		background-color: #ffffff;
		border-radius: 12px;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
		display: flex;
		justify-content: center;
	}

	.dsm-admin-panel-menu-form{
		display: flex;
		flex-direction: row;
		gap: 15px;
	}

	.dsm-admin-panel button{
		max-height: 50px;
		height: 50px;
		padding: 0 20px;
		background-color: #007bff;
		color: #ffffff;
		border: none;
		border-radius: 8px;
		font-size: 1rem;
		font-weight: 500;
		cursor: pointer;
		transition: background-color 0.3s ease;
	}

	.dsm-admin-panel button:hover {
		background-color: #0056b3;
	}

	.dsm-admin-panel-content-child {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		padding: 20px;
		gap: 15px;
		background-color: #ffffff;
		border-radius: 12px;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
		max-width: 800px;
		width: 100%;
		margin: auto;
	}

	.dsm-admin-panel-content-child h2,
	.dsm-admin-panel-content-child h3 {
		margin-bottom: 10px;
	}

	.dsm-admin-panel-action-result{
		display: flex;
		padding: 20px;
		justify-content: center;
		text-align: center;
	}

	.dsm-admin-panel input[type="file"],
	.dsm-admin-panel input[type="checkbox"] {
		text-align: center;
		margin-top: 10px;
		margin-bottom: 20px;
		padding: 8px;
		font-size: 1rem;
		border: 1px solid #ced4da;
		border-radius: 8px;
	}

	.dsm-admin-panel input[type="file"]{
		max-width: 300px;
		width: 100%;
	}

	.dsm-admin-panel label {
		font-size: 1rem;
		font-weight: 500;
		color: #495057;
		margin-bottom: 5px;
		display: block;
	}

	.dsm-admin-panel-content-child form {
		display: flex;
		align-items: center;
		flex-direction: row;
		gap: 10px;
	}
	
	.dsm-admin-panel-content-child form .form-row {
		display: flex;
		gap: 15px;
		align-items: center;
	}
	
	.dsm-admin-panel-content-child form .form-row input[type="checkbox"] {
		margin: 0; 
	}

	.dsm-admin-panel-home {
		background: #ffffff;
		border-radius: 12px;
		box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
		padding: 20px 30px;
		max-width: 600px;
		text-align: center;
		margin: auto;
	}

	.dsm-admin-panel-home h1 {
		font-size: 1.8rem;
		color: #0073e6;
		margin-bottom: 20px;
	}

	.dsm-admin-panel-home ul {
		list-style-type: none;
		padding: 0;
		margin: 0;
	}

	.dsm-admin-panel-content-child table {
		width: 100%;
		text-align: left;
		border-collapse: collapse;  /* Better table formatting */
		margin-top: 10px;  /* Ensures spacing below Total Downloads */
	}

	.dsm-admin-panel-content-child table th,
	.dsm-admin-panel-content-child table td {
		border: 1px solid #dee2e6;
		padding: 8px;
	}

	.dsm-admin-panel-content-child table th {
		background-color: #007bff;
		color: white;
		font-weight: bold;
		text-align: center;
	}

	.dsm-admin-panel-content-child table td {
		text-align: center;
	}

	.dsm-admin-panel-home ul li {
		background: #f9f9f9;
		border: 1px solid #e0e0e0;
		border-radius: 8px;
		padding: 10px 15px;
		margin: 10px 0;
		text-align: left;
		display: flex;
		align-items: center;
		font-size: 1rem;
		color: #212529;
	}

	.dsm-admin-panel-home ul li::before {
		content: "✔";
		color: #28a745;
		font-weight: bold;
		margin-right: 10px;
	}

	.dsm-admin-panel-home p {
		font-size: 0.9rem;
		color: #666;
		margin-top: 10px;
	}

	.dsm-admin-panel-action-result-success,
	.dsm-admin-panel-action-result-error,
	.dsm-admin-panel-action-default {
		display: flex;
		flex-direction: column;
		padding: 15px 20px;
		border-radius: 8px;
		font-size: 1rem;
		color: #fff;
		max-width: 600px;
		width: 100%;
	}

	.dsm-admin-panel-action-result-success-entry{
		display: flex;
		flex-direction: row;
		text-align: left;
	}
	
	.dsm-admin-panel-action-result-success label {
		width: 150px;
		font-weight: bold;
	}

	.dsm-admin-panel-action-result-success text {
		display: inline-block;
		margin-left: 10px;
	}


	.dsm-admin-panel-action-result-success {
		background-color: #28a745;
	}

	.dsm-admin-panel-action-result-error {
		background-color: #dc3545;
	}

	.dsm-admin-panel-action-default {
		background-color: #6c757d;
	}
	
	description{
		font-weight: bolder;
		font-size: 1.1rem;
		width: 800px;
		text-wrap: wrap;
	}

	note{
		color: light-grey;
		font-size: 1rem;
		width: 600px;
		text-wrap: wrap;
		text-decoration: underline dotted red;		
		text-decoration-skip-ink: auto;
	}
</style>


<div class="dsm-admin-panel">
	<div class="dsm-admin-panel-menu">
		<div class="dsm-admin-panel-menu-content">
			<form class="dsm-admin-panel-menu-form" method="POST">
				<div class="dsm-admin-panel-menu-entry">
					<button type="submit" name="menu-item-option" value=0 class="dsm-menu-button">Home</button>
				</div>
				<div class="dsm-admin-panel-menu-entry">
					<button type="submit" name="menu-item-option" value=3 class="dsm-menu-button">Analytics</button>
				</div>
				<div class="dsm-admin-panel-menu-entry">
					<button type="submit" name="menu-item-option" value=1 class="dsm-menu-button">Upload</button>
				</div>
				<div class="dsm-admin-panel-menu-entry">
					<button type="submit" name="menu-item-option" value=2 class="dsm-menu-button">Settings</button>
				</div>
				<div class="dsm-admin-panel-menu-entry">
					<button type="submit" name="menu-item-option" value=4 class="dsm-menu-button">Backup</button>
				</div>
				<div class="dsm-admin-panel-menu-entry">
					<button type="submit" name="menu-item-option" value=5 class="dsm-menu-button">Restore</button>
				</div>
			</form>
		</div>
	</div>
	
	<div class="dsm-admin-panel-content"> <?php
		if( $active_menu_option === 1 ){ ?>
		<div class="dsm-admin-panel-content-child">
			<form method="POST" enctype="multipart/form-data">
				<label for="file">Upload File Here</label> <?php
				wp_nonce_field( 'file_up', '_dsmnonce' ); ?>
				<input type="file" name="file" id="file">
				<button type="submit">Upload</button>
			</form>
		</div> <?php
	} elseif ( $active_menu_option === 2 ) { 
		require DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
		$cdn_auth_cookie = DsMedia_Plugin_DB_API::get_value('cdn_cookie'); 
		[$failed_report_sig, $success_report_sig] = explode(":", DsMedia_Plugin_DB_API::get_value('sig_prefix') );
		error_log( $failed_report_sig . "  " . $success_report_sig );
		?>
		<div class="dsm-admin-panel-content-child">
			<form method="POST">
				<label>Set signature name</label>
				<input type="text" name="failed_report_sig" placeholder="<?php echo $failed_report_sig ? esc_attr($failed_report_sig) : 'ex. SiteNameUserId'; ?>">
				<input type="text" name="success_report_sig" placeholder="<?php echo $success_report_sig ? esc_attr($success_report_sig) : 'ex. SiteNameDownloadId'; ?>">
				<?php wp_nonce_field( 'store_sig_value', '_dsmnonce' ); ?>
				<button type="submit" name="set_sig" value="0"> Save </button>
				<?php
				if ( isset($response_sig) ) {
					echo "<p>" . esc_html($response_sig) . "</p>";
				}
				?>
			</form>
			<form method="POST">
				<label>Optional* Set Authentication-Cookie for files from CDN</label>
				<input type="text" name="cdn_auth_cookie" placeholder="<?php echo($cdn_auth_cookie ? esc_attr($cdn_auth_cookie) : "ex. CookieName: CookieValue") ?>"><?php
				wp_nonce_field( 'store_cdn_cookie', '_dsmnonce' ) ?>
				<button type="submit" name="store_cdn_cookie" value=0> Save </button><?php
				if($response_cookie){
					echo "<p>" . esc_html($response_cookie) . "</p>";
				}?>
			</form>
			<form method="POST" class="collections_drop">
				<label> Drop plugins database tables on Deactivate? </label>  <?php
				wp_nonce_field( 'drop_or_not', '_dsmnonce' ); ?>
				<div class="form-row">
					<input name="to_drop_or_not_to_drop" type="checkbox" <?php echo checked( get_option( 'to_drop_or_not' ), 'on', false ); ?> >
					<button type="submit" name="submit_to_drop_or_not" value=0 class="button">Save</button>
				</div>
			</form>
		</div> <?php
	} elseif ( $active_menu_option === 3 ) { ?>
		<div class="dsm-admin-panel-content-child"><?php
			require DSMEDIA_PLUGIN_DIR_PATH . 'includes/dsmedia_plugin_db_api.php';
			$download_stats = DsMedia_Plugin_DB_API::count_single_downloads(); ?>

			<h2>Download Analytics</h2><?php
			if( $download_stats ){ ?>
			<h3>Total Downloads: <?= esc_html( $download_stats[0][ 'total_downloads' ] ) ?> </h3>
			<table border="1" style="width: 100%; text-align: left;">
			<tr><th>Book_Id</th><th>Book_Name</th><th>Unique Downloads</th></tr> <?php
			foreach ($download_stats as $stat) { ?>
				<tr>
				<td> <?= esc_html( $stat[ 'product_id' ] ) ?> </td>
				<td> <?= esc_html( $stat[ 'post_title' ] ) ?> </td>
				<td> <?= esc_html( $stat[ 'unique_downloads' ] ) ?> </td>
				</tr> <?php
			} ?>
			</table><?php
			} else{ ?>
				<div>
					<h2>No data available</h2>
				</div><?php 
			} 
			?>
		</div> <?php
	} elseif ( $active_menu_option === 4 ) { ?>
		<div class="dsm-admin-panel-content-child">
			<form method="POST">
				<label>Create Backup: </label><?php
				wp_nonce_field( 'create-backup', '_dsmnonce' ); ?>
				<label for="select-type"> Choose Backup type: </label>
				<select id="select-type" name="mode">
					<option value=0> .sql </option>
					<option value=1> .json </option>
				</select>
				<button type="submit" name="create-backup" value=0>Create</button>
			</form>
		</div> <?php
	} elseif ( $active_menu_option === 5 ) { ?>
		<div class="dsm-admin-panel-content-child">
			<form method="POST" enctype="multipart/form-data">
				<label>Restore Backup: </label><?php
				wp_nonce_field( 'restore-backup', '_dsmnonce' ); ?>

				<label for="select-type"> Choose Backup type: </label>
				<select id="select-type" name="mode">
					<option value=0> .sql </option>
					<option value=1> .json </option>
				</select>

				<label for="restore-file"> Upload backup: </label>
				<input type="file" name="backup-file" id="restore-file">
				<button type="submit" name="restore-backup" value=0>Restore</button>
			</form>
		</div> <?php
	} else{ ?>
		<div class="dsm-admin-panel-content-child">
			<div class="dsm-admin-panel-home">
				<h1>Download & Sign Media Plugin</h1>
				<ul>
					<li>Bypasses WooCommerce cart and checkout processes for free digital downloadables</li>
					<li>Signs each audiobook with a unique key and reports the event to the database (trace leaks back to the user)</li>
					<li>Supports both local and remote files</li>
				</ul>
				<p>Enhance your digital delivery workflow with the DsMedia Plugin.</p>
			</div>
		</div> <?php
	} ?>
	</div><?php
	if( $active_menu_option === 2 ){ ?>
		<description>Description:<note>Enable this setting only if you are sure you want to fully remove all the plugins data</note>, enabling the setting will do nothing until you disable the plugin, after you disable the plugin the database storing all the data about the downloads will be deleted, so please create a backup before you do so. </description>
		<note> Caution: performing this action will result in the loss of all the collected data, metrics will be unavailable and any leaks before that will be untracable</note> <?php
	} elseif( $active_menu_option === 4 ) { ?>
	<text> <?= $backup_result ?> </text><?php 
	}
	elseif( $active_menu_option === 5 ) { ?>
	<text> <?= $restore_result ?> </text><?php 
	} ?>
	<div class="dsm-admin-panel-action-result">

<?php


//If a signature was found within the uploaded file the folowing will display the users info
if( $active_menu_option === 1 && $wp_user ){ ?>
		<div class="dsm-admin-panel-action-result-success">
			<div class="dsm-admin-panel-action-result-success-entry">
				<label>Username:	</label> <text><?= esc_html($wp_user->user_nicename) ?></text> 
			</div>
			<div class="dsm-admin-panel-action-result-success-entry">
				<label>Email:		</label> <text><?= esc_html($wp_user->user_email) ?></text>
			</div>
			<div class="dsm-admin-panel-action-result-success-entry">
				<label>First Name:	</label> <text><?= esc_html($wp_user->first_name) ?></text>
			</div>
			<div class="dsm-admin-panel-action-result-success-entry">
				<label>Last Name:	</label> <text><?= esc_html($wp_user->last_name) ?></text>
			</div> 
			<div class="dsm-admin-panel-action-result-success-entry">
				<label>Time of Download: </label> <text><?= esc_html($entry_data[0]->ts) ?></text> 
			</div>
		</div> <?php
} elseif( $active_menu_option === 1 && $did_post && !$signature_detected ){ ?>
		<div class="dsm-admin-panel-action-result-error">
			<text> No signature was detected</text>
		</div><?php		
} elseif( $active_menu_option === 1 && !$did_post ){ ?>
		<div class="dsm-admin-panel-action-default">
			<text> Upload a file to see a users information </text>
		</div><?php
}

?>

	</div>
</div>
