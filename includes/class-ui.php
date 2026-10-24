<?php
/**
 * Small helpers for the shared admin look (assets/timu-ui.css, identical in every thisismyurl plugin).
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Markup helpers. The stylesheet is shared and registered once, whichever plugin loads first.
 */
class Ui {

	/**
	 * Load the shared stylesheet.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! wp_style_is( 'timu-ui', 'registered' ) ) {
			wp_register_style( 'timu-ui', plugins_url( 'assets/timu-ui.css', TIMU_FORMATS_FILE ), array(), TIMU_FORMATS_VERSION );
		}
		wp_enqueue_style( 'timu-ui' );
	}

	/**
	 * A status pill: an icon and words, never colour alone.
	 *
	 * @param string $tone  good, info, note or problem.
	 * @param string $text  Words.
	 * @return string
	 */
	public static function pill( $tone, $text ) {
		$icons = array(
			'good'    => 'yes-alt',
			'info'    => 'info-outline',
			'note'    => 'visibility',
			'problem' => 'warning',
		);
		$tone  = isset( $icons[ $tone ] ) ? $tone : 'info';
		return '<span class="timu-ui-pill timu-ui-pill--' . esc_attr( $tone ) . '"><span class="dashicons dashicons-' . esc_attr( $icons[ $tone ] ) . '" aria-hidden="true"></span>' . esc_html( $text ) . '</span>';
	}

	/**
	 * Is the sister plugin, Image Support, active?
	 *
	 * @return bool
	 */
	public static function support_active() {
		return defined( 'TIMU_IMAGE_SUPPORT_VERSION' );
	}

	/**
	 * Footer: the trust line that is true right now, and a quiet link to the sister plugin.
	 *
	 * @param string $trust_html Already-escaped trust line, with its icon.
	 * @return void
	 */
	public static function footer( $trust_html ) {
		echo '<p class="timu-ui-foot">' . $trust_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts by the caller.
		echo '<span class="timu-ui-foot-links">';
		if ( self::support_active() ) {
			echo '<span class="timu-ui-switch"><a href="' . esc_url( admin_url( 'upload.php?page=timu-image-support' ) ) . '">' . esc_html__( 'Image Support', 'thisismyurl-image-formats' ) . '</a><a href="' . esc_url( admin_url( 'upload.php?page=' . Settings::PAGE ) ) . '" aria-current="page">' . esc_html__( 'Image Formats', 'thisismyurl-image-formats' ) . '</a></span>';
		} else {
			echo '<a href="' . esc_url( 'https://wordpress.org/plugins/thisismyurl-image-support/' ) . '">' . esc_html__( 'Also from us: Image Support, to find pictures that need a description', 'thisismyurl-image-formats' ) . '</a>';
		}
		echo '</span></p>';
	}
}
