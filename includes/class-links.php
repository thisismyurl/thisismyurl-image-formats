<?php
/**
 * Keeps links to a changed picture working.
 *
 * When a picture that is already used in a post is changed (for example a BMP becomes a PNG), its web address changes. Rather than
 * rewriting anybody's posts, this swaps the old address for the new one when the page is shown. Nothing stored is edited, so putting
 * the original back simply makes the old address work again.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Render-time address swap for converted pictures.
 */
class Links {

	/**
	 * Old address to new address, built once per request when first needed.
	 *
	 * @var array<string,string>|null
	 */
	private static $map = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( array( 'the_content', 'widget_text', 'widget_block_content' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'swap' ), 20 );
		}
		add_action( 'timu_formats_converted', array( __CLASS__, 'reset' ) );
		add_action( 'timu_formats_restored', array( __CLASS__, 'reset' ) );
	}

	/**
	 * Forget the built list (a picture was just changed or put back).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$map = null;
	}

	/**
	 * Replace old picture addresses in a piece of content.
	 *
	 * @param string $content Content about to be shown.
	 * @return string
	 */
	public static function swap( $content ) {
		if ( ! is_string( $content ) || '' === $content || false === strpos( $content, '/uploads/' ) ) {
			return $content;
		}
		$map = self::map();
		return $map ? strtr( $content, $map ) : $content;
	}

	/**
	 * The list of old addresses and what they became.
	 *
	 * @return array<string,string>
	 */
	private static function map() {
		if ( null !== self::$map ) {
			return self::$map;
		}
		self::$map = array();
		$ids       = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => Converter::META_OLD_URLS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a small, bounded set: only pictures this plugin changed.
				'fields'         => 'ids',
				'posts_per_page' => 500,
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			$old = get_post_meta( $id, Converter::META_OLD_URLS, true );
			$new = wp_get_attachment_url( $id );
			if ( ! $new || ! is_array( $old ) ) {
				continue;
			}
			foreach ( $old as $url ) {
				if ( ! is_string( $url ) || $url === $new ) {
					continue;
				}
				// Both web address styles, so a post written before the site moved to https still works.
				self::$map[ set_url_scheme( $url, 'https' ) ] = set_url_scheme( $new, 'https' );
				self::$map[ set_url_scheme( $url, 'http' ) ]  = set_url_scheme( $new, 'http' );
			}
		}
		// Longest first, so a sized address is never half-replaced by the shorter full-size one.
		uksort(
			self::$map,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		return self::$map;
	}
}
