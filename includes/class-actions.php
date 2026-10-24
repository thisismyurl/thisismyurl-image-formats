<?php
/**
 * The buttons on a picture's screen: change this picture, and put the original back.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-language actions for people who do not use the command line. Every action checks who is asking, checks a one-time link, and
 * says in ordinary words what happened.
 */
class Actions {

	const CONVERT = 'timu_formats_convert';
	const RESTORE = 'timu_formats_restore';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_' . self::CONVERT, array( __CLASS__, 'handle_convert' ) );
		add_action( 'admin_post_' . self::RESTORE, array( __CLASS__, 'handle_restore' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_notice' ) );
	}

	/**
	 * A one-time link for an action on one picture.
	 *
	 * @param string $action CONVERT or RESTORE.
	 * @param int    $id     Attachment ID.
	 * @return string
	 */
	public static function url( $action, $id ) {
		return wp_nonce_url( add_query_arg( array( 'action' => $action, 'id' => (int) $id ), admin_url( 'admin-post.php' ) ), $action . '_' . (int) $id );
	}

	/**
	 * Change one picture, from its screen.
	 *
	 * @return void
	 */
	public static function handle_convert() {
		$id = self::checked_id( self::CONVERT );
		$r  = Converter::convert_attachment( $id, 'manual' );
		if ( is_wp_error( $r ) ) {
			self::flash( 'error', $r->get_error_message() );
		} elseif ( 'converted' === $r['status'] ) {
			self::flash(
				'success',
				sprintf(
					/* translators: 1: new picture type, 2: how much smaller (for example "3 MB") */
					__( 'Done. This picture is now a %1$s file and takes up %2$s less space. Your original is saved safely, and you can put it back any time.', 'thisismyurl-image-formats' ),
					strtoupper( str_replace( 'image/', '', $r['to'] ) ),
					size_format( max( 0, (int) $r['saved'] ) )
				)
			);
		} else {
			self::flash( 'info', sprintf( /* translators: %s: reason in plain words */ __( 'Nothing was changed. %s', 'thisismyurl-image-formats' ), self::reason( isset( $r['reason'] ) ? $r['reason'] : '' ) ) );
		}
		self::back( $id );
	}

	/**
	 * Put one picture's original back.
	 *
	 * @return void
	 */
	public static function handle_restore() {
		$id = self::checked_id( self::RESTORE );
		$r  = Converter::restore_attachment( $id );
		if ( is_wp_error( $r ) ) {
			self::flash( 'error', $r->get_error_message() );
		} else {
			self::flash( 'success', __( 'Done. Your original picture is back, just as it was before. Its title, caption and description were not touched.', 'thisismyurl-image-formats' ) );
		}
		self::back( $id );
	}

	/**
	 * Show the message left by the last action, once.
	 *
	 * @return void
	 */
	public static function show_notice() {
		$key  = 'timu_formats_notice_' . get_current_user_id();
		$note = get_transient( $key );
		if ( ! is_array( $note ) ) {
			return;
		}
		delete_transient( $key );
		$class = in_array( $note['type'], array( 'success', 'error', 'info' ), true ) ? $note['type'] : 'info';
		echo '<div class="notice notice-' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $note['text'] ) . '</p></div>';
	}

	/**
	 * Plain words for a reason the converter gave for leaving a picture alone.
	 *
	 * @param string $reason Reason code from the converter.
	 * @return string
	 */
	private static function reason( $reason ) {
		$map = array(
			'already converted'             => __( 'This picture was already changed.', 'thisismyurl-image-formats' ),
			'format is left alone'          => __( 'This kind of picture is left exactly as it is.', 'thisismyurl-image-formats' ),
			'no conversion needed'          => __( 'This picture is already a good kind for a website.', 'thisismyurl-image-formats' ),
			'converted file was not smaller' => __( 'The new file would not have been smaller, so your picture was left exactly as it was.', 'thisismyurl-image-formats' ),
		);
		return isset( $map[ $reason ] ) ? $map[ $reason ] : __( 'Your picture was left exactly as it was.', 'thisismyurl-image-formats' );
	}

	/**
	 * Check who is asking and that the link is the one we made, then return the picture's ID.
	 *
	 * @param string $action CONVERT or RESTORE.
	 * @return int
	 */
	private static function checked_id( $action ) {
		$id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified on the next line.
		check_admin_referer( $action . '_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to change this picture.', 'thisismyurl-image-formats' ), '', array( 'response' => 403 ) );
		}
		return $id;
	}

	/**
	 * Leave a message for the next admin screen.
	 *
	 * @param string $type success, error or info.
	 * @param string $text Message in plain words.
	 * @return void
	 */
	private static function flash( $type, $text ) {
		set_transient( 'timu_formats_notice_' . get_current_user_id(), array( 'type' => $type, 'text' => $text ), 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Go back to the picture's screen.
	 *
	 * @param int $id Attachment ID.
	 * @return void
	 */
	private static function back( $id ) {
		$to = wp_get_referer();
		if ( ! $to ) {
			$to = get_edit_post_link( $id, 'url' );
		}
		wp_safe_redirect( $to ? $to : admin_url( 'upload.php' ) );
		exit;
	}
}
