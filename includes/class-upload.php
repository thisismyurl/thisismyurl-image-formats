<?php
/**
 * Conversion at upload time.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `wp_handle_upload`, which WordPress applies to browser, REST API and sideloaded (WP-CLI) uploads alike. The converted file replaces the
 * upload before the attachment is created, so the Media Library only ever sees the new format.
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
	 * Convert a freshly uploaded file if the policy says to.
	 *
	 * @param array  $upload  {file, url, type}.
	 * @param string $context upload or sideload.
	 * @return array The upload array, changed if a conversion happened.
	 */
	public static function convert_upload( $upload, $context = 'upload' ) {
		if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) || empty( $upload['type'] ) ) {
			return $upload;
		}
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
		if ( '' === $target ) {
			return $upload;
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
		$uploads  = wp_get_upload_dir();
		$rel_old  = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $upload['file'] ), '/' );
		$old_name = wp_basename( $upload['file'] );

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
		unset( $old_name );
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
