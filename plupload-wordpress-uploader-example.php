<?php
/**
 * Plugin Name: Plupload WordPress Uploader Example
 * Plugin URI: https://pph.me/vijayhardaha/
 * Description: This is a custom plugin with custom Plupload Drag &amp; Drop uploader. Use [plupload_wordpress_uploader] shortcode to display upload form in frontend.
 * Version: 1.0.0
 * Author: Vijay Hardaha
 * Author URI: https://pph.me/vijayhardaha/
 * Text Domain: plupload-wordpress-uploader
 * Domain Path: /languages/
 * Requires at least: 5.2
 * Requires PHP: 7.0
 *
 * @package KV_Galleries
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'PLWUE_PLUGIN_FILE' ) ) {
	define( 'PLWUE_PLUGIN_FILE', __FILE__ );
}

/**
 * Enqueue scripts in frontend
 *
 * @since 1.0.0
 */
function plupload_wordpress_uploader_enqueue_scripts() {
	wp_enqueue_style( 'plwue', plugins_url( '/', PLWUE_PLUGIN_FILE ) . '/assets/uploader.css', array(), '1.0.0' );
	wp_enqueue_script( 'plupload' );
	wp_enqueue_script( 'plwue', plugins_url( '/', PLWUE_PLUGIN_FILE ) . '/assets/uploader.js', array( 'jquery' ), '1.0.0', true );
	// Error messages for Plupload.
	$uploader_l10n = array(
		'queue_limit_exceeded'      => __( 'You have attempted to queue too many files.' ),
		/* translators: %s: File name. */
		'file_exceeds_size_limit'   => __( '%s exceeds the maximum upload size for this site.' ),
		'zero_byte_file'            => __( 'This file is empty. Please try another.' ),
		'invalid_filetype'          => __( 'Sorry, this file type is not permitted for security reasons.' ),
		'not_an_image'              => __( 'This file is not an image. Please try another.' ),
		'image_memory_exceeded'     => __( 'Memory exceeded. Please try another smaller file.' ),
		'image_dimensions_exceeded' => __( 'This is larger than the maximum size. Please try another.' ),
		'default_error'             => __( 'An error occurred in the upload. Please try again later.' ),
		'missing_upload_url'        => __( 'There was a configuration error. Please contact the server administrator.' ),
		'upload_limit_exceeded'     => __( 'You may only upload 1 file.' ),
		'http_error'                => __( 'Unexpected response from the server. The file may have been uploaded successfully. Check in the Media Library or reload the page.' ),
		'http_error_image'          => __( 'Post-processing of the image failed likely because the server is busy or does not have enough resources. Uploading a smaller image may help. Suggested maximum size is 2500 pixels.' ),
		'upload_failed'             => __( 'Upload failed.' ),
		/* translators: 1: Opening link tag, 2: Closing link tag. */
		'big_upload_failed'         => __( 'Please try uploading this file with the %1$sbrowser uploader%2$s.' ),
		/* translators: %s: File name. */
		'big_upload_queued'         => __( '%s exceeds the maximum upload size for the multi-file uploader when used in your browser.' ),
		'io_error'                  => __( 'IO error.' ),
		'security_error'            => __( 'Security error.' ),
		'file_cancelled'            => __( 'File canceled.' ),
		'upload_stopped'            => __( 'Upload stopped.' ),
		'dismiss'                   => __( 'Dismiss' ),
		'crunching'                 => __( 'Crunching&hellip;' ),
		'deleted'                   => __( 'moved to the Trash.' ),
		/* translators: %s: File name. */
		'error_uploading'           => __( '&#8220;%s&#8221; has failed to upload.' ),
	);
	wp_localize_script( 'plwue', 'pluploadL10n', $uploader_l10n );
	wp_localize_script( 'plwue', 'plwue', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'wp_enqueue_scripts', 'plupload_wordpress_uploader_enqueue_scripts' );

/**
 * Register [plupload_wordpress_uploader] shortcode
 *
 * @since 1.0.0
 * @return string
 */
function plupload_wordpress_uploader_shortcode() {
	$max_upload_size = wp_max_upload_size();
	if ( ! $max_upload_size ) {
		$max_upload_size = 0;
	}

	$extensions = array( 'jpg', 'jpeg', 'png' );

	/*
	* Since 4.9 the `runtimes` setting is hardcoded in our version of Plupload to `html5,html4`,
	* and the `flash_swf_url` and `silverlight_xap_url` are not used.
	*/
	$plupload_init = array(
		'browse_button'    => 'plupload-browse-button',
		'container'        => 'plupload-upload-ui',
		'drop_element'     => 'drag-drop-area',
		'file_data_name'   => 'async-upload',
		'url'              => admin_url( 'admin-ajax.php' ),
		'filters'          => array(
			'max_file_size' => $max_upload_size . 'b',
			'mime_types'    => array( array( 'extensions' => implode( ',', $extensions ) ) ),
		),
		'multipart_params' => array(
			'action'   => 'plupload_wordpress_uploader_action',
			'_wpnonce' => wp_create_nonce( 'plupload_wordpress_uploader_form' ),
		),
	);

	/*
	 * Currently only iOS Safari supports multiple files uploading,
	 * but iOS 7.x has a bug that prevents uploading of videos when enabled.
	 * See #29602.
	 */
	if ( wp_is_mobile() && strpos( $_SERVER['HTTP_USER_AGENT'], 'OS 7_' ) !== false && strpos( $_SERVER['HTTP_USER_AGENT'], 'like Mac OS X' ) !== false ) { // @codingStandardsIgnoreLine
		$plupload_init['multi_selection'] = false;
	}

	ob_start();
	?>
	<script type="text/javascript">
		var _uploaderInit = <?php echo wp_json_encode( $plupload_init ); ?>;
	</script>
	<div class="uploader-form">
		<div id="plupload-upload-ui" class="hide-if-no-js">
			<div id="drag-drop-area">
				<div class="drag-drop-inside">
				<p class="drag-drop-info"><?php esc_html_e( 'Drop files to upload' ); ?></p>
				<p><?php esc_html_e( 'or', 'Uploader: Drop files here - or - Select Files' ); ?></p>
				<p class="drag-drop-buttons"><input id="plupload-browse-button" type="button" value="<?php esc_attr_e( 'Select Files' ); ?>" class="button" /></p>
				</div>
			</div>
		</div>
		<p class="max-upload-size">
			<?php
			/* translators: %s: Maximum allowed file size. */
			printf( esc_html__( 'Maximum upload file size: %s.' ), esc_html( size_format( $max_upload_size ) ) );
			?>
		</p>
		<div id="media-upload-error"></div>
		<div id="media-items"></div>
		<div id="media-file-errors"></div>
		</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'plupload_wordpress_uploader', 'plupload_wordpress_uploader_shortcode' );

/**
 * Ajax Callback
 */
function plupload_wordpress_uploader_action() {
	// Check ajax noonce.
	check_ajax_referer( 'plupload_wordpress_uploader_form' );

	$post_id = 0;
	if ( isset( $_REQUEST['post_id'] ) ) {
		$post_id = absint( $_REQUEST['post_id'] );
		if ( ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			$post_id = 0;
		}
	}

	$id = media_handle_upload( 'async-upload', $post_id );
	if ( is_wp_error( $id ) ) {
		printf(
			'<div class="error-div error">%s <strong>%s</strong><br />%s</div>',
			sprintf(
				'<button type="button" class="dismiss button-link" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">%s</button>',
				esc_html__( 'Dismiss' )
			),
			sprintf(
			/* translators: %s: Name of the file that failed to upload. */
				esc_html__( '&#8220;%s&#8221; has failed to upload.' ),
				esc_html( $_FILES['async-upload']['name'] ) // @codingStandardsIgnoreLine
			),
			esc_html( $id->get_error_message() )
		);
		exit;
	}

	if ( $_REQUEST['short'] ) { // @codingStandardsIgnoreLine
		// Short form response - attachment ID only.
		echo $id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		// Long form response - big chunk of HTML.
		$type = $_REQUEST['type']; // @codingStandardsIgnoreLine

		/**
		 * Filters the returned ID of an uploaded attachment.
		 *
		 * The dynamic portion of the hook name, `$type`, refers to the attachment type,
		 * such as 'image', 'audio', 'video', 'file', etc.
		 *
		 * @since 2.5.0
		 *
		 * @param int $id Uploaded attachment ID.
		 */
		echo apply_filters( "async_upload_{$type}", $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	exit;
}
add_action( 'wp_ajax_plupload_wordpress_uploader_action', 'plupload_wordpress_uploader_action' );
add_action( 'wp_ajax_nopriv_plupload_wordpress_uploader_action', 'plupload_wordpress_uploader_action' );

/**
 * Ajax Callback
 */
function plupload_wordpress_uploader_fetch_action() {
	if ( isset( $_REQUEST['attachment_id'] ) && intval( $_REQUEST['attachment_id'] ) ) { // @codingStandardsIgnoreLine
		$id   = intval( $_REQUEST['attachment_id'] ); // @codingStandardsIgnoreLine
		$post = get_post( $id );
		if ( 'attachment' !== $post->post_type ) {
			wp_die( esc_html__( 'Invalid post type.' ) );
		}
		$thumb_url = wp_get_attachment_image_src( $id, 'medium', true );
		if ( $thumb_url ) {
			$width       = $thumb_url[1];
			$height      = $thumb_url[2];
			$orientation = $width > $height ? 'landscape' : 'portrait';
			echo '<div class="centered ' . esc_attr( $orientation ) . '"><img class="attachment" src="' . esc_url( $thumb_url[0] ) . '" alt="" /></div>';
		}
	}
	exit;
}
add_action( 'wp_ajax_plupload_wordpress_uploader_fetch_action', 'plupload_wordpress_uploader_fetch_action' );
add_action( 'wp_ajax_nopriv_plupload_wordpress_uploader_fetch_action', 'plupload_wordpress_uploader_fetch_action' );
