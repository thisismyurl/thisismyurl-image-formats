<?php
/**
 * Decides whether an image should be converted, and to what.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * The best-format rule. It looks at the source type, the settings and what the server can write, and returns a target MIME type or an
 * empty string when the image should be left alone. Developers can change the answer with the `timu_formats_target_mime` filter.
 */
class Policy {

	/**
	 * Source types browsers cannot display.
	 *
	 * @var string[]
	 */
	const NON_WEB = array( 'image/bmp', 'image/x-bmp', 'image/x-ms-bmp', 'image/tiff', 'image/heic', 'image/heif' );

	/**
	 * Target MIME type for a source type, or an empty string for "leave it alone".
	 *
	 * @param string $source_mime Source MIME type.
	 * @param string $context     upload, bulk or manual. Upload and bulk follow the switches in Settings; a manual conversion of one chosen image ignores them.
	 * @return string
	 */
	public static function target_for( $source_mime, $context = 'upload' ) {
		$source_mime = strtolower( (string) $source_mime );
		$s           = Settings::get();
		$target      = '';

		if ( in_array( $source_mime, self::NON_WEB, true ) ) {
			if ( 'manual' !== $context && empty( $s['convert_nonweb'] ) ) {
				return '';
			}
			if ( in_array( $source_mime, array( 'image/heic', 'image/heif' ), true ) ) {
				$target = self::first_writable( self::photo_order( $s['photo_format'] ) );
			} else {
				$target = self::first_writable( 'webp' === $s['graphic_format'] ? array( 'image/webp', 'image/png' ) : array( 'image/png' ) );
			}
		} elseif ( in_array( $source_mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			if ( 'manual' !== $context && empty( $s['convert_jpeg_png'] ) ) {
				return '';
			}
			$target = self::first_writable( 'avif' === $s['web_format'] ? array( 'image/avif', 'image/webp' ) : ( 'webp' === $s['web_format'] ? array( 'image/webp' ) : array( 'image/avif', 'image/webp' ) ) );
		}

		/**
		 * Filters the target MIME type. Return an empty string to skip conversion.
		 *
		 * @param string $target      Chosen target MIME type.
		 * @param string $source_mime Source MIME type.
		 * @param string $context     upload, bulk or manual.
		 */
		$target = (string) apply_filters( 'timu_formats_target_mime', $target, $source_mime, $context );
		return $target === $source_mime ? '' : $target;
	}

	/**
	 * Preference order for photographs such as HEIC.
	 *
	 * @param string $choice auto, avif, webp or jpeg.
	 * @return string[]
	 */
	private static function photo_order( $choice ) {
		switch ( $choice ) {
			case 'avif':
				return array( 'image/avif', 'image/webp', 'image/jpeg' );
			case 'webp':
				return array( 'image/webp', 'image/jpeg' );
			case 'jpeg':
				return array( 'image/jpeg' );
			default:
				return array( 'image/avif', 'image/webp', 'image/jpeg' );
		}
	}

	/**
	 * First MIME type in a list that this server can write.
	 *
	 * @param string[] $order Preference order.
	 * @return string
	 */
	private static function first_writable( array $order ) {
		foreach ( $order as $mime ) {
			if ( Capabilities::can_write( $mime ) ) {
				return $mime;
			}
		}
		return '';
	}

	/**
	 * Encoder quality for a target. Lossless targets ignore it.
	 *
	 * @param string $target_mime Target MIME type.
	 * @param string $source_mime Source MIME type.
	 * @return int
	 */
	public static function quality_for( $target_mime, $source_mime ) {
		$quality = (int) Settings::value( 'quality' );
		// BMP and TIFF are usually graphics and screenshots: keep every detail when the target is a lossy format.
		if ( in_array( $source_mime, array( 'image/bmp', 'image/x-bmp', 'image/x-ms-bmp', 'image/tiff' ), true ) && 'image/webp' === $target_mime ) {
			$quality = 100;
		}
		return (int) apply_filters( 'timu_formats_quality', $quality, $target_mime, $source_mime );
	}
}
