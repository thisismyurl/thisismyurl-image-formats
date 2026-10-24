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
	 * While a long run is going (WP-CLI --all), the list is rebuilt once at the end instead of after every picture.
	 *
	 * @var bool
	 */
	private static $deferred = false;

	/**
	 * A rebuild was asked for while deferred.
	 *
	 * @var bool
	 */
	private static $dirty = false;

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
		$use = array();
		foreach ( $map as $old => $new ) {
			// An old address that now has a real file behind it belongs to some other picture. Leave it alone.
			$http_old = 'http' . substr( $old, 5 );
			$has_s    = false !== strpos( $content, $old );
			$has_p    = false !== strpos( $content, $http_old );
			if ( ( $has_s || $has_p ) && self::is_live( $old ) ) {
				continue;
			}
			if ( $has_s ) {
				$use[ $old ] = $new;
			}
			if ( $has_p ) {
				$use[ $http_old ] = 'http' . substr( $new, 5 );
			}
		}
		return $use ? strtr( $content, $use ) : $content;
	}

	/**
	 * Is there a real file at this address in the uploads folder right now?
	 *
	 * @param string $url Address.
	 * @return bool
	 */
	private static function is_live( $url ) {
		static $seen = array();
		if ( isset( $seen[ $url ] ) ) {
			return $seen[ $url ];
		}
		$uploads = wp_get_upload_dir();
		$base    = preg_replace( '#^https?:#', '', (string) $uploads['baseurl'] );
		$rel     = preg_replace( '#^https?:#', '', $url );
		if ( 0 !== strpos( $rel, $base ) ) {
			$seen[ $url ] = false;
			return false;
		}
		$seen[ $url ] = is_file( $uploads['basedir'] . substr( $rel, strlen( $base ) ) );
		return $seen[ $url ];
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
	 * Hold back rebuilding the list, or release it and rebuild once if anything changed meanwhile.
	 *
	 * @param bool $on True to hold back, false to release.
	 * @return void
	 */
	public static function defer( $on ) {
		self::$deferred = (bool) $on;
		if ( ! $on && self::$dirty ) {
			self::$dirty = false;
			self::rebuild();
		}
	}

	/**
	 * Work out the list from the pictures this plugin has changed, and keep it in one option that is not loaded on every page.
	 *
	 * @return array<string,string>
	 */
	public static function rebuild() {
		if ( self::$deferred ) {
			self::$dirty = true;
			return is_array( self::$map ) ? self::$map : array();
		}
		$map  = array();
		$page = 1;
		do {
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => array( 'inherit', 'private', 'publish' ),
					'meta_key'       => Converter::META_OLD_URLS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- only pictures this plugin changed.
					'fields'         => 'ids',
					'posts_per_page' => 500,
					'paged'          => $page,
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
				foreach ( $old as $entry ) {
					if ( ! is_array( $entry ) || ! isset( $entry['s'], $entry['u'] ) ) {
						continue;
					}
					// Each old size points at the same size of the picture as it is now, or at the full one when there is no match.
					$target = isset( $new[ $entry['s'] ] ) ? $new[ $entry['s'] ] : $new['full'];
					if ( $entry['u'] === $target ) {
						continue;
					}
					// Kept once, as https. The plain http form is worked out when a page is shown, so a post written before the site moved to https still works.
					$map[ set_url_scheme( $entry['u'], 'https' ) ] = set_url_scheme( $target, 'https' );
				}
			}
			++$page;
		} while ( 500 === count( $ids ) );
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
