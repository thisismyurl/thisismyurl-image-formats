<?php
/**
 * Conversion at upload time.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `wp_handle_upload`. The converted file replaces the upload before the attachment is created, so the Media Library only ever sees the
 * new format. Only pictures added through the Media Library (the uploader, and the block editor when the server makes the sizes) are touched. Other
 * plugins' uploads and WordPress's own background "sideload" files are left exactly alone.
 */
class Upload {

	/**
	 * Conversions made this request, by new file path, waiting for their attachment to exist.
	 *
	 * @var array<string,array>
	 */
	private static $pending = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'wp_handle_upload', array( __CLASS__, 'convert_upload' ), 20, 2 );
		add_action( 'add_attachment', array( __CLASS__, 'record_conversion' ) );
	}

	/**
	 * Is this request somebody adding a picture to the Media Library?
	 *
	 * @return bool
	 */
	private static function is_library_upload() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which action is running; core checks the nonce.
			return isset( $_REQUEST['action'] ) && 'upload-attachment' === sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
			// With in-browser picture processing the browser makes the sizes and sends them separately, so the file is left as it is.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only reads a flag, compared to fixed words; core checks the request.
			$flag          = isset( $_REQUEST['generate_sub_sizes'] ) ? wp_unslash( $_REQUEST['generate_sub_sizes'] ) : '';
			// phpcs:enable
			$browser_sizes = is_string( $flag ) && in_array( strtolower( $flag ), array( 'false', '0' ), true );
			return ! $browser_sizes && 1 === preg_match( '#^/wp/v2/media/?$#', $route );
		}
		global $pagenow;
		return in_array( $pagenow, array( 'async-upload.php', 'media-new.php' ), true );
	}

	/**
	 * Convert a freshly uploaded file if the policy says to.
	 *
	 * @param array  $upload  {file, url, type}.
	 * @param string $context upload or sideload.
	 * @return array The upload array, changed if a conversion happened.
	 */
	public static function convert_upload( $upload, $context = 'upload' ) {
		if ( 'upload' !== $context || ! self::is_library_upload() ) {
			return $upload;
		}
		if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) || empty( $upload['type'] ) ) {
			return $upload;
		}
		// The block editor's upload reaches this point before WordPress has loaded its picture functions.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$source = strtolower( $upload['type'] );
		/**
		 * Return false to leave this upload exactly as it is.
		 *
		 * @param bool   $convert Whether to convert.
		 * @param array  $upload  The upload array.
		 * @param string $context upload or sideload.
		 */
		if ( ! apply_filters( 'timu_formats_convert_upload', true, $upload, $context ) ) {
			return $upload;
		}
		$target = Policy::target_for( $source, 'upload' );
		if ( '' === $target || ! in_array( $target, array_values( get_allowed_mime_types() ), true ) ) {
			return $upload;
		}
		if ( 'image/png' === $source && Converter::is_animated_png( $upload['file'] ) ) {
			return $upload; // Converting would keep only the first frame.
		}


		$new = Converter::convert_file( $upload['file'], $source, $target );
		if ( is_wp_error( $new ) ) {
			return $upload; // The upload still succeeds, unchanged.
		}
		$old_size = (int) filesize( $upload['file'] );
		if ( in_array( $source, array( 'image/jpeg', 'image/png' ), true ) && $old_size > 0 && $new['size'] >= $old_size ) {
			wp_delete_file( $new['path'] );
			return $upload;
		}

		$image_meta = wp_read_image_metadata( $upload['file'] );
		if ( ! empty( $new['rotated'] ) && is_array( $image_meta ) && isset( $image_meta['orientation'] ) ) {
			$image_meta['orientation'] = 1; // The pixels were turned upright, so the new file must not be turned again.
		}
		Converter::remember( $new['path'], is_array( $image_meta ) ? $image_meta : array() );

		$mode   = Backup::mode();
		$stored = 'none';
		if ( 'none' !== $mode ) {
			$kept = Backup::store_local( $upload['file'], wp_basename( $new['path'] ) );
			if ( false === $kept ) {
				wp_delete_file( $new['path'] );
				return $upload;
			}
			$stored = 'local:' . $kept;
		}
		$uploads = wp_get_upload_dir();
		$rel_old = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $upload['file'] ), '/' );

		wp_delete_file( $upload['file'] );
		self::$pending[ wp_normalize_path( $new['path'] ) ] = array(
			'file'      => $rel_old,
			'mime'      => $source,
			'size'      => $old_size,
			'backup'    => $stored,
			'to'        => $target,
			'context'   => $context,
			'converted' => time(),
			'saved'     => $old_size - $new['size'],
		);

		$upload['file'] = $new['path'];
		$upload['url']  = trailingslashit( dirname( $upload['url'] ) ) . wp_basename( $new['path'] );
		$upload['type'] = $target;
		return $upload;
	}

	/**
	 * Attach the conversion record to the attachment WordPress just created for the converted file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function record_conversion( $attachment_id ) {
		if ( ! self::$pending ) {
			return;
		}
		$file = get_attached_file( $attachment_id );
		$key  = $file ? wp_normalize_path( $file ) : '';
		if ( '' === $key || ! isset( self::$pending[ $key ] ) ) {
			return;
		}
		$info  = self::$pending[ $key ];
		$saved = $info['saved'];
		unset( self::$pending[ $key ], $info['saved'] );
		update_post_meta( $attachment_id, Converter::META_ORIGINAL, $info );
		update_post_meta( $attachment_id, Converter::META_SAVINGS, $saved );
	}
}
