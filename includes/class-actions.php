<?php
/**
 * The buttons on a picture's screen: change this picture, and put the original back.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-language actions for people who do not use the command line. Every action checks who is asking, checks a one-time code, and
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
	 * The form for an action on one picture. It is printed outside the editing form (WordPress does not allow a form inside a form) and
	 * the button refers to it by id, so the button can sit wherever it reads best.
	 *
	 * @param string $action CONVERT or RESTORE.
	 * @param int    $id     Attachment ID.
	 * @return string The form's id.
	 */
	public static function form_id( $action, $id ) {
		return 'timu-formats-form-' . $action . '-' . (int) $id;
	}

	/**
	 * Queue the hidden form for one action, printed in the footer.
	 *
	 * @param string $action CONVERT or RESTORE.
	 * @param int    $id     Attachment ID.
	 * @return void
	 */
	public static function queue_form( $action, $id ) {
		$id = (int) $id;
		add_action(
			'admin_footer',
			static function () use ( $action, $id ) {
				echo '<form id="' . esc_attr( self::form_id( $action, $id ) ) . '" class="timu-formats-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
				echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '">';
				echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action . '_' . $id ) ) . '">';
				echo '</form>';
			}
		);
		if ( ! has_action( 'admin_footer', array( __CLASS__, 'busy_script' ) ) ) {
			add_action( 'admin_footer', array( __CLASS__, 'busy_script' ) );
		}
	}

	/**
	 * Once a button is pressed, show that work is happening and ignore a second press.
	 *
	 * @return void
	 */
	public static function busy_script() {
		echo '<p id="timu-formats-live" class="screen-reader-text" role="status" aria-live="polite"></p>';
		wp_print_inline_script_tag(
			"document.addEventListener('submit',function(e){var f=e.target;if(!f.classList||!f.classList.contains('timu-formats-form')){return;}" .
			"if(f.dataset.busy){e.preventDefault();return;}f.dataset.busy='1';var msg=" . wp_json_encode( __( 'Working. Please wait.', 'thisismyurl-image-formats' ) ) . ";" .
			"var b=document.querySelectorAll('button[form=\"'+f.id+'\"]');for(var i=0;i<b.length;i++){b[i].setAttribute('aria-disabled','true');b[i].setAttribute('aria-busy','true');b[i].textContent=msg;}" .
			"var l=document.getElementById('timu-formats-live');if(l){l.textContent=msg;}});"
		);
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
			$kept = isset( $r['backup'] ) && 0 === strpos( (string) $r['backup'], 'local:' );
			self::flash(
				'success',
				sprintf(
					/* translators: 1: new picture type, 2: how much smaller (for example "3 MB"), 3: what happened to the original */
					__( 'Done. This picture is now a %1$s file and takes up %2$s less space. %3$s', 'thisismyurl-image-formats' ),
					strtoupper( str_replace( 'image/', '', $r['to'] ) ),
					size_format( max( 0, (int) $r['saved'] ) ),
					$kept ? __( 'Your original is saved safely, and you can put it back any time.', 'thisismyurl-image-formats' ) : __( 'Your original was not kept, so it cannot be put back.', 'thisismyurl-image-formats' )
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
		$class = isset( $note['type'] ) && in_array( $note['type'], array( 'success', 'error', 'info' ), true ) ? $note['type'] : 'info';
		echo '<div class="notice notice-' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( isset( $note['text'] ) ? (string) $note['text'] : '' ) . '</p></div>';
	}

	/**
	 * Plain words for a reason the converter gave for leaving a picture alone.
	 *
	 * @param string $reason Reason code from the converter.
	 * @return string
	 */
	public static function reason( $reason ) {
		$map = array(
			'already converted'             => __( 'This picture was already changed.', 'thisismyurl-image-formats' ),
			'format is left alone'          => __( 'This kind of picture is left exactly as it is.', 'thisismyurl-image-formats' ),
			'no conversion needed'          => __( 'This picture is already a good kind for a website.', 'thisismyurl-image-formats' ),
			'converted file was not smaller' => __( 'The new file would not have been smaller, so your picture was left exactly as it was.', 'thisismyurl-image-formats' ),
			'edited in wordpress'           => __( 'This picture was cropped or turned in WordPress. It is left exactly as it is, so your edits stay safe.', 'thisismyurl-image-formats' ),
			'extra files'                   => __( 'This picture has extra files that belong with it, so it is left exactly as it is.', 'thisismyurl-image-formats' ),
			'shared file'                   => __( 'Another picture in your Media Library uses the same file, so it is left exactly as it is.', 'thisismyurl-image-formats' ),
			'animated'                      => __( 'This picture is animated, so it is left exactly as it is. Changing it would keep only the first frame.', 'thisismyurl-image-formats' ),
			'type not allowed'              => __( 'Your website does not allow that kind of picture, so this one is left exactly as it is.', 'thisismyurl-image-formats' ),
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
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified on the next line.
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
		$to = get_edit_post_link( $id, 'url' );
		wp_safe_redirect( $to ? $to : admin_url( 'upload.php' ) );
		exit;
	}
}
