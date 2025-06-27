# Bypass - Woo(s) Checkout Process

There(s) a pretty simple way to bypass the checkout proccess of woo
for free digital downloadables

To acheive this we have to focus a bit on how woocommerce handles the downloads it self.

Inside their github repo, i searched for any file containing the word `download` and sure enough this came up 
[Class-WC-Download-Handler](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-download-handler.php)

on initialization we see 4 action hooks getting registered [35](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-download-handler.php#L32)

```php
		add_action( 'woocommerce_download_file_redirect', array( __CLASS__, 'download_file_redirect' ), 10, 2 );
		add_action( 'woocommerce_download_file_xsendfile', array( __CLASS__, 'download_file_xsendfile' ), 10, 2 );
		add_action( 'woocommerce_download_file_force', array( __CLASS__, 'download_file_force' ), 10, 2 );
		self::add_action( self::TRACK_DOWNLOAD_CALLBACK, array( __CLASS__, 'track_download' ), 10, 3 );
```
in my case i want the download to be forced to the user so the example bellow will only use the `download_file_force` method provided by the class

Searching for the function in the source code, one thing that i realy like to see, are the keywords `public` and `static`
meaning we don't have to initiate any object and can bluntly use it [475](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-download-handler.php#L475)
```php
	public static function download_file_force( $file_path, $filename ) {
		$parsed_file_path = self::parse_file_path( $file_path );
		$download_range   = self::get_download_range( @filesize( $parsed_file_path['file_path'] ) ); // @codingStandardsIgnoreLine.

		self::download_headers( $parsed_file_path['file_path'], $filename, $download_range );
		.....
```

this is the function that will be used to force a download uppon a user, the thing is we don't only have to worry about the file but the headers also, depending on the file type the browser might still attempt to display it so let's have a look in the headers to see that they are correctly generated [479](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-download-handler.php#L479)
```php
		self::download_headers( $parsed_file_path['file_path'], $filename, $download_range );
```
it calls the `download_headers()` which will set the headers for the download [529](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-download-handler.php#L529)

```php
	private static function download_headers( $file_path, $filename, $download_range = array() ) {
		self::check_server_config();
		self::clean_buffers();
		wc_nocache_headers();

		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: ' . self::get_download_content_type( $file_path ) );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: ' . self::get_content_disposition() . '; filename="' . $filename . '";' );
		header( 'Content-Transfer-Encoding: binary' );
		.....
```
Now, the Content-Disposition header will determine if the file will be `inline` and presented as a web page or part of a web page, or `attached` as a downloadable attachment,
in this case we need the value to be set to attached, this is determined here 

```php
header( 'Content-Disposition: ' . self::get_content_disposition() . '; filename="' . $filename . '";' );
```

by the `get_content_disposition()` [601](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-download-handler.php#L601)

```php
	private static function get_content_disposition() : string {
		$disposition = 'attachment';
		if ( 'yes' === get_option( 'woocommerce_downloads_deliver_inline' ) ) {
			$disposition = 'inline';
		}
		return $disposition;
	}
```

In the code above we can see that the result depends on an option stored in the database and if set to yes the
disposition becomes `inline`, my plugin (gonna provide code at the end) wasn't working for sometime now and was trying to display the content instead of
download it, so i installed a plugin within my wordpress instance to be able to see the values in the database [WP Data access](https://wordpress.org/plugins/wp-data-access/),
and the value was set to yes and had (still have) no idea where woo processes that option so ill have to change it by hand
```php
update_option( 'woocommerce_downloads_deliver_inline', 'off' );
```

# Example Plugin

```php
<?php
/**
 * Plugin Name: ByPasser
 * Description: Bypasses woocommerce checkout process.
 * Version: 1.0.0
 * Author: Charalambos Rentoumis
 *
 * WC requires at least: 6.0
 * WC tested up to: 6.6.2
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
**/


define( 'UPLOADS_DIRECTORY_PATH', wp_upload_dir()['basedir'] );

register_activation_hook( __FILE__, array( 'Bypass_Checkout_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Bypass_Checkout_Plugin', 'deactivate' ) );

if( !has_action( 'init', 'init_bypass_checkout_plugin' ) ){
	add_action( 'init', 'init_bypass_checkout_plugin' );
}

function init_bypass_checkout_plugin(){
	if( class_exists( 'Bypass_Checkout_Plugin' ) ){
		$ = new Bypass_Checkout_Plugin();
	}
}

if( !class_exists( 'Bypass_Checkout_Plugin' ) ){
	class Bypass_Checkout_Plugin{

		public function __construct(){
			add_action ( 'woocommerce_product_meta_end', array( $this, 'display_downloads_on_single_product_page' ) );
		}

		public static function activate(){
			flush_rewrite_rules();
		}

		public static function deactivate(){
			flush_rewrite_rules();
		}

		public function display_downloads_on_single_product_page(){
			$echoed = "";
			global $product;
			//retrieving all the downloads from the product and displaying them
			$downloads = $product->get_downloads();
			foreach ( $downloads as $key => $each_download ) {
				$echoed .= "<form method='POST'>
						<label>" . $each_download[ 'name' ] . "</label>
						<input type='hidden' name='file-path' value='" . $each_download[ 'file' ] . "'> 
						<input type='hidden' name='file-name' value='" . $each_download[ 'name' ] . "'>
						<input type='submit'>
					</form>";
			}
			echo $echoed;

			if( isset( $_POST[ 'file-path' ] ) && isset($_POST[ 'file-name' ] ) ){
				//Sorry in advance for the path manipulation i'm pulling here
				$path_parsed = parse_url( $_POST[ 'file-path' ] );
				$path_array = explode( '/', $path_parsed[ 'path' ] );

				//the paths retrieved for the downloads contain the /wp-content/uploads/ path
				//and so does UPLOADS_DIRECTORY_PATH so i'm removing it
				if( $key = array_search( 'wp-content', $path_array ) !==false ){
					unset( $path_array[ $key ] );
				}

				if (($key = array_search( 'uploads', $path_array)) !== false ) { 
					unset($path_array[$key]);
				}

				$final_path = UPLOADS_DIRECTORY_PATH . implode( '/', $path_array );

				update_option( 'woocommerce_downloads_deliver_inline', 'off' );

				include_once WC_ABSPATH . 'includes/class-wc-download-handler.php';
				WC_Download_Handler::download_file_force( $final_path, esc($_POST[ 'file-name' ] ));

				update_option( 'woocommerce_downloads_deliver_inline', 'yes' );
				exit;
			}
		}
	}

}

?>
```

 These lines do all the magic:
 - first we disable the inline option
 - then we include the file containning the code we want to execute
 - we use the function to force download
 - and reset the option to not interfer with anything else

  ```php
 				update_option( 'woocommerce_downloads_deliver_inline', 'off' );

				include_once WC_ABSPATH . 'includes/class-wc-download-handler.php';
				WC_Download_Handler::download_file_force( $final_path, esc($_POST[ 'file-name' ] ));

				update_option( 'woocommerce_downloads_deliver_inline', 'yes' );
```

## Tip:
You can locate the download files of a product from the `$product` global object within a single product page loop (propably any product loop)
by using the `get_downloads()` method provided by `WC_Product` [642](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/abstracts/abstract-wc-product.php#L642)

```php
			$echoed = "";
			global $product;

			$downloads = $product->get_downloads();
			foreach ( $downloads as $key => $each_download ) {
				$echoed .= "<form method='POST'>
						<label>" . $each_download[ 'name' ] . "</label>
						<input type='hidden' name='file-path' value='" . $each_download[ 'file' ] . "'> 
						<input type='hidden' name='file-name' value='" . $each_download[ 'name' ] . "'>
						<input type='submit'>
					</form>";
			}
```

# Resources:

[StackOverflow Answer](https://stackoverflow.com/a/62203924)

[Woo(s) Github](https://github.com/woocommerce/woocommerce/tree/trunk)

[Woo(s) Documentaion Page WC-Product](https://woocommerce.github.io/code-reference/classes/WC-Product.html)

[Woo(s) Documentaion Page WC-Download-Handler](https://woocommerce.github.io/code-reference/classes/WC-Download-Handler.html)