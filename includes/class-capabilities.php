<?php
/**
 * What this server can read and write.
 *
 * Everything the converter decides starts here, so a plugin that offers AVIF never promises it on a server that cannot write it.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Reports image format support through WordPress's own image editors (GD or Imagick).
 */
class Capabilities {

	/**
	 * Source formats this plugin knows how to convert.
	 *
	 * @return array<string,string> MIME type => short label.
	 */
	public static function source_formats() {
		return array(
			'image/bmp'    => 'BMP',
			'image/x-bmp'  => 'BMP',
			'image/x-ms-bmp' => 'BMP',
			'image/tiff'   => 'TIFF',
			'image/heic'   => 'HEIC',
			'image/heif'   => 'HEIF',
			'image/jpeg'   => 'JPEG',
			'image/png'    => 'PNG',
		);
	}

	/**
	 * Whether the active image editor can write a MIME type.
	 *
	 * @param string $mime Target MIME type.
	 * @return bool
	 */
	public static function can_write( $mime ) {
		return (bool) wp_image_editor_supports(
			array(
				'mime_type' => $mime,
				'methods'   => array( 'save' ),
			)
		);
	}

	/**
	 * Whether the active image editor can open a file of this MIME type.
	 *
	 * @param string $mime Source MIME type.
	 * @return bool
	 */
	public static function can_read( $mime ) {
		return (bool) wp_image_editor_supports( array( 'mime_type' => $mime ) );
	}

	/**
	 * Write support for every target format this plugin can produce.
	 *
	 * @return array<string,bool> MIME type => supported.
	 */
	public static function targets() {
		$out = array();
		foreach ( array( 'image/avif', 'image/webp', 'image/png', 'image/jpeg' ) as $mime ) {
			$out[ $mime ] = self::can_write( $mime );
		}
		return $out;
	}

	/**
	 * File extension for a target MIME type.
	 *
	 * @param string $mime MIME type.
	 * @return string Extension without the dot, or an empty string.
	 */
	public static function extension( $mime ) {
		$map = array(
			'image/avif' => 'avif',
			'image/webp' => 'webp',
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
		);
		return isset( $map[ $mime ] ) ? $map[ $mime ] : '';
	}

	/**
	 * Name of the image library in use, for the status screen and the CLI.
	 *
	 * @return string
	 */
	public static function library() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter, read so the answer matches what WordPress will use.
		foreach ( (array) apply_filters( 'wp_image_editors', array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' ) ) as $editor ) {
			if ( is_string( $editor ) && class_exists( $editor ) && call_user_func( array( $editor, 'test' ) ) ) {
				return false !== strpos( $editor, 'Imagick' ) ? 'Imagick' : 'GD';
			}
		}
		return 'none';
	}
}
