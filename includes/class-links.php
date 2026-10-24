<?php
/**
 * Keeps links to a changed picture working while the plugin is active.
 *
 * When a picture that is already used in a post is changed (for example a BMP becomes a PNG), its web address changes. Rather than
 * rewriting anybody's posts, this swaps the old address for the new one when the page is shown. Nothing stored is edited, so putting the
 * original back simply makes the old address work again. The swap only covers post content and widget text, and only while the plugin is
 * active.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Render-time address swap for converted pictures.
 */
class Links {

	const OPTION = 'timu_formats_links';

	/**
	 * Old address to new address.
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
		// After blocks are rendered (9) and before WordPress adds sizes and lazy loading to the images (12), so the new files get their srcset.
		foreach ( array( 'the_content', 'widget_text', 'widget_block_content' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'swap' ), 11 );
		}
		add_action( 'timu_formats_converted', array( __CLASS__, 'rebuild' ) );
		add_action( 'timu_formats_restored', array( __CLASS__, 'rebuild' ) );
	}

	/**
	 * Replace old picture addresses in a piece of content.
	 *
	 * @param string $content Content about to be shown.
	 * @return string
	 */
	public static function swap( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}
		$map = self::map();
		if ( ! $map ) {
			return $content;
		}
		return strtr( $content, $map );
	}

	/**
	 * The stored list, built the first time it is needed.
	 *
	 * @return array<string,string>
	 */
	private static function map() {
		if ( null === self::$map ) {
			$saved     = get_option( self::OPTION, null );
			self::$map = is_array( $saved ) ? $saved : self::rebuild();
		}
		return self::$map;
	}

	/**
	 * Work out the list from the pictures this plugin has changed, and keep it in one option that is not loaded on every page.
	 *
	 * @return array<string,string>
	 */
	public static function rebuild() {
		$map = array();
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => Converter::META_OLD_URLS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- only pictures this plugin changed.
				'fields'         => 'ids',
				'posts_per_page' => 1000,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			$old = get_post_meta( $id, Converter::META_OLD_URLS, true );
			$new = Converter::attachment_urls( $id );
			if ( ! is_array( $old ) || empty( $new['full'] ) ) {
				continue;
			}
			foreach ( $old as $size => $url ) {
				// Each old size points at the same size of the new picture, or at the full one when there is no match.
				$target = isset( $new[ $size ] ) ? $new[ $size ] : $new['full'];
				if ( ! is_string( $url ) || $url === $target ) {
					continue;
				}
				// Both web address styles, so a post written before the site moved to https still works.
				$map[ set_url_scheme( $url, 'https' ) ] = set_url_scheme( $target, 'https' );
				$map[ set_url_scheme( $url, 'http' ) ]  = set_url_scheme( $target, 'http' );
			}
		}
		// Longest first, so a sized address is never half-replaced by a shorter one.
		uksort(
			$map,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		update_option( self::OPTION, $map, false );
		self::$map = $map;
		return $map;
	}
}
