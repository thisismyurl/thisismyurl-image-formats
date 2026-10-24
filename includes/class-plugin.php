<?php
/**
 * Plugin bootstrap.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the pieces together.
 */
class Plugin {

	/**
	 * Start the plugin.
	 *
	 * @return void
	 */
	public static function boot() {
		load_plugin_textdomain( 'thisismyurl-image-formats', false, dirname( plugin_basename( TIMU_FORMATS_FILE ) ) . '/languages' );
		Upload::register();
		add_filter( 'wp_read_image_metadata', array( __CLASS__, 'carry_image_meta' ), 10, 2 );
		if ( is_admin() ) {
			add_action( 'admin_init', array( Settings::class, 'register_setting' ) );
			add_action( 'admin_menu', array( Settings::class, 'register_menu' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( TIMU_FORMATS_FILE ), array( __CLASS__, 'action_links' ) );
			add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'attachment_note' ), 10, 2 );
		}
	}

	/**
	 * Fill in image metadata (camera, exposure, date) that WordPress cannot read from the converted file, from the original's.
	 *
	 * @param array  $meta Metadata WordPress read from the file.
	 * @param string $file File it read.
	 * @return array
	 */
	public static function carry_image_meta( $meta, $file ) {
		$known = Converter::registry_match( $file );
		if ( ! $known || empty( $known['image_meta'] ) || ! is_array( $meta ) ) {
			return $meta;
		}
		$blank = static function ( $value ) {
			return null === $value || '' === $value || 0 === $value || '0' === $value || array() === $value;
		};
		foreach ( $known['image_meta'] as $key => $value ) {
			if ( ( ! isset( $meta[ $key ] ) || $blank( $meta[ $key ] ) ) && ! $blank( $value ) ) {
				$meta[ $key ] = $value;
			}
		}
		return $meta;
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . Settings::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'thisismyurl-image-formats' ) . '</a>' );
		return $links;
	}

	/**
	 * Show what happened to a converted image on its attachment screen.
	 *
	 * @param array    $fields Existing fields.
	 * @param \WP_Post $post   Attachment.
	 * @return array
	 */
	public static function attachment_note( $fields, $post ) {
		$info = get_post_meta( $post->ID, Converter::META_ORIGINAL, true );
		if ( ! is_array( $info ) ) {
			return $fields;
		}
		$saved = (int) get_post_meta( $post->ID, Converter::META_SAVINGS, true );
		$text  = sprintf(
			/* translators: 1: original format, 2: new format, 3: bytes saved (may be negative) */
			__( 'Converted from %1$s to %2$s. Size change: %3$s.', 'thisismyurl-image-formats' ),
			strtoupper( str_replace( 'image/', '', (string) $info['mime'] ) ),
			strtoupper( str_replace( 'image/', '', (string) $info['to'] ) ),
			$saved >= 0 ? sprintf( /* translators: %s: formatted size */ __( '%s smaller', 'thisismyurl-image-formats' ), size_format( $saved ) ) : sprintf( /* translators: %s: formatted size */ __( '%s larger', 'thisismyurl-image-formats' ), size_format( abs( $saved ) ) )
		);
		$fields['timu_formats'] = array(
			'label' => __( 'Image Formats', 'thisismyurl-image-formats' ),
			'input' => 'html',
			'html'  => '<p>' . esc_html( $text ) . '</p>',
		);
		return $fields;
	}
}
