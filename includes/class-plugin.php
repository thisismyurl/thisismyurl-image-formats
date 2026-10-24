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
		Upload::register();
		Links::register();
		if ( is_admin() ) {
			Actions::register();
		}
		add_filter( 'wp_read_image_metadata', array( __CLASS__, 'carry_image_meta' ), 10, 2 );
		if ( is_admin() ) {
			add_action( 'admin_init', array( Settings::class, 'register_setting' ) );
			add_action( 'admin_menu', array( Settings::class, 'register_menu' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( TIMU_FORMATS_FILE ), array( __CLASS__, 'action_links' ) );
			add_action( 'add_meta_boxes_attachment', array( __CLASS__, 'add_box' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_for_picture' ) );
			add_action( 'admin_init', array( Settings::class, 'redirect_old_address' ), 1 );
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
		$url = admin_url( 'upload.php?page=' . Settings::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'thisismyurl-image-formats' ) . '</a>' );
		return $links;
	}

	/**
	 * Load the shared look on a picture's own screen.
	 *
	 * @param string $hook Admin page.
	 * @return void
	 */
	public static function enqueue_for_picture( $hook ) {
		if ( 'post.php' === $hook ) {
			$screen = get_current_screen();
			if ( $screen && 'attachment' === $screen->post_type ) {
				Ui::enqueue();
			}
		}
	}

	/**
	 * Add the "Picture type" box to a picture's screen, in the side column under Save.
	 *
	 * @param \WP_Post $post Attachment.
	 * @return void
	 */
	public static function add_box( $post ) {
		if ( ! wp_attachment_is_image( $post->ID ) || ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( 'upload_files' ) ) {
			return;
		}
		add_meta_box( 'timu-formats-box', __( 'Picture type', 'thisismyurl-image-formats' ), array( __CLASS__, 'render_box' ), 'attachment', 'side', 'default' );
	}

	/**
	 * What happened to this picture, and the one button that makes sense next. Always says whether it can be undone.
	 *
	 * @param \WP_Post $post Attachment.
	 * @return void
	 */
	public static function render_box( $post ) {
		$info = get_post_meta( $post->ID, Converter::META_ORIGINAL, true );
		echo '<div class="timu-ui timu-ui--bare">';

		if ( is_array( $info ) ) {
			$saved = (int) get_post_meta( $post->ID, Converter::META_SAVINGS, true );
			echo '<p>' . Ui::pill( 'good', sprintf( /* translators: 1: old picture type, 2: new picture type */ __( 'Changed from %1$s to %2$s', 'thisismyurl-image-formats' ), strtoupper( str_replace( 'image/', '', (string) $info['mime'] ) ), strtoupper( str_replace( 'image/', '', (string) $info['to'] ) ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Ui::pill escapes.
			echo '<p>' . esc_html(
				$saved >= 0
					? sprintf( /* translators: %s: amount of space, for example "3 MB" */ __( 'It now takes up %s less space.', 'thisismyurl-image-formats' ), size_format( $saved ) )
					: sprintf( /* translators: %s: amount of space, for example "3 MB" */ __( 'It now takes up %s more space.', 'thisismyurl-image-formats' ), size_format( abs( $saved ) ) )
			) . '</p>';
			if ( 0 === strpos( (string) $info['backup'], 'local:' ) ) {
				echo '<p>' . esc_html__( 'Your original is saved safely. You can put it back whenever you like. Your title, caption and description will not change.', 'thisismyurl-image-formats' ) . '</p>';
				Actions::queue_form( Actions::RESTORE, $post->ID );
				echo '<p><button type="submit" class="button" form="' . esc_attr( Actions::form_id( Actions::RESTORE, $post->ID ) ) . '">' . esc_html__( 'Put the original back', 'thisismyurl-image-formats' ) . '</button></p>';
			} else {
				echo '<p>' . Ui::pill( 'note', __( 'No copy was kept', 'thisismyurl-image-formats' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Ui::pill escapes.
				echo '<p>' . esc_html__( 'No copy of the original was kept, so it cannot be put back.', 'thisismyurl-image-formats' ) . '</p>';
			}
		} else {
			$mime   = (string) get_post_mime_type( $post->ID );
			$target = in_array( $mime, array_keys( Capabilities::source_formats() ), true ) ? Policy::target_for( $mime, 'manual' ) : '';
			if ( '' === $target ) {
				echo '<p>' . esc_html__( 'This picture is already a good kind for a website. Nothing needs changing.', 'thisismyurl-image-formats' ) . '</p></div>';
				return;
			}
			$nonweb = in_array( $mime, Policy::NON_WEB, true );
			echo '<p>' . ( $nonweb ? Ui::pill( 'note', __( 'Browsers often cannot show this', 'thisismyurl-image-formats' ) ) : Ui::pill( 'info', __( 'Can be made smaller', 'thisismyurl-image-formats' ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Ui::pill escapes.
			echo '<p>' . esc_html( $nonweb ? __( 'You can change it into a kind that works on every website.', 'thisismyurl-image-formats' ) : __( 'You can make it take up less space, so your website loads faster.', 'thisismyurl-image-formats' ) ) . '</p>';
			echo '<p>' . esc_html__( 'A safe copy of your original is saved first, and you can put it back any time. Nothing happens until you press the button.', 'thisismyurl-image-formats' ) . '</p>';
			echo '<p class="description">' . esc_html__( 'If this picture is already in your pages, those pages keep working while this plugin is turned on.', 'thisismyurl-image-formats' ) . '</p>';
			Actions::queue_form( Actions::CONVERT, $post->ID );
			echo '<p><button type="submit" class="button button-primary" form="' . esc_attr( Actions::form_id( Actions::CONVERT, $post->ID ) ) . '">' . esc_html( $nonweb ? __( 'Fix this picture', 'thisismyurl-image-formats' ) : __( 'Make this picture smaller', 'thisismyurl-image-formats' ) ) . '</button></p>';
		}
		echo '</div>';
	}
}
