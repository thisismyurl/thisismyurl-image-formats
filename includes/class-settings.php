<?php
/**
 * Plugin settings: storage, defaults, sanitising and the Settings screen.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * One option holds every setting, so uninstall has one thing to remove.
 */
class Settings {

	const OPTION = 'timu_formats_options';
	const GROUP  = 'timu_formats_settings';
	const PAGE   = 'thisismyurl-image-formats';

	/**
	 * Defaults. Conversion of formats browsers cannot show is on; changing JPEG and PNG is opt-in.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'convert_nonweb'   => 1,       // BMP, TIFF, HEIC and HEIF on upload.
			'convert_jpeg_png' => 0,       // JPEG and PNG on upload, to the web format below.
			'web_format'       => 'auto',  // auto, avif, webp.
			'graphic_format'   => 'png',   // Target for BMP and TIFF: png or webp.
			'photo_format'     => 'auto',  // Target for HEIC and HEIF: auto, avif, webp, jpeg.
			'quality'          => 82,
			'backup'           => 'local', // local (a folder inside uploads) or none.
		);
	}

	/**
	 * Current settings with defaults filled in.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function value( $key ) {
		$all = self::get();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitise a submitted settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		$out['convert_nonweb']   = empty( $input['convert_nonweb'] ) ? 0 : 1;
		$out['convert_jpeg_png'] = empty( $input['convert_jpeg_png'] ) ? 0 : 1;

		$pick = static function ( $value, array $allowed, $fallback ) {
			$value = is_string( $value ) ? sanitize_key( $value ) : '';
			return in_array( $value, $allowed, true ) ? $value : $fallback;
		};
		$out['web_format']     = $pick( isset( $input['web_format'] ) ? $input['web_format'] : '', array( 'auto', 'avif', 'webp' ), 'auto' );
		$out['graphic_format'] = $pick( isset( $input['graphic_format'] ) ? $input['graphic_format'] : '', array( 'png', 'webp' ), 'png' );
		$out['photo_format']   = $pick( isset( $input['photo_format'] ) ? $input['photo_format'] : '', array( 'auto', 'avif', 'webp', 'jpeg' ), 'auto' );
		$out['backup']         = $pick( isset( $input['backup'] ) ? $input['backup'] : '', array( 'local', 'none' ), 'local' );

		$quality        = isset( $input['quality'] ) ? (int) $input['quality'] : 82;
		$out['quality'] = max( 40, min( 100, $quality ) );
		return $out;
	}

	/**
	 * Register the setting (admin_init).
	 *
	 * @return void
	 */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Register the screen (admin_menu). It lives under Media, next to Image Support, because that is where pictures are looked after.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$hook = add_submenu_page(
			'upload.php',
			__( 'Image Formats', 'thisismyurl-image-formats' ),
			__( 'Image Formats', 'thisismyurl-image-formats' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'enqueue_ui' ) );
		}
	}

	/**
	 * Load the shared stylesheet for this screen.
	 *
	 * @return void
	 */
	public static function enqueue_ui() {
		add_action( 'admin_enqueue_scripts', array( Ui::class, 'enqueue' ) );
	}

	/**
	 * The screen used to live under Settings. Old bookmarks and links still arrive safely.
	 *
	 * @return void
	 */
	public static function redirect_old_address() {
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only a page name is read, to forward the visitor.
		if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && self::PAGE === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			wp_safe_redirect( admin_url( 'upload.php?page=' . self::PAGE ) );
			exit;
		}
	}

	/**
	 * One radio choice, with a short plain-words line under it.
	 *
	 * @param string $key     Setting key.
	 * @param string $value   This choice's value.
	 * @param string $current Saved value.
	 * @param string $label   Words for the choice.
	 * @param string $help    One short line under it.
	 * @param bool   $best    Mark as the recommended choice.
	 * @return void
	 */
	private static function choice( $key, $value, $current, $label, $help = '', $best = false ) {
		$hid = 'timu-h-' . $key . '-' . $value;
		echo '<label class="timu-ui-choice"><input type="radio" name="' . esc_attr( self::OPTION . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" ' . checked( $value, $current, false ) . '> ' . esc_html( $label );
		if ( $best ) {
			echo '<span class="timu-ui-tag">' . esc_html__( 'Recommended', 'thisismyurl-image-formats' ) . '</span>';
		}
		if ( '' !== $help ) {
			echo '<small id="' . esc_attr( $hid ) . '">' . esc_html( $help ) . '</small>';
		}
		echo '</label>';
	}

	/**
	 * Settings screen. Written for someone who does not use computers much: it says what the tool does, says it is safe before anything
	 * else, and has every setting already chosen. The choices a beginner needs are in view; the rest sit under "More options".
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s       = self::get();
		$targets = array_keys( array_filter( Capabilities::targets() ) );
		$names   = array_map(
			static function ( $mime ) {
				return strtoupper( str_replace( 'image/', '', $mime ) );
			},
			$targets
		);
		$kept    = 'local' === $s['backup'];
		$name    = self::OPTION;
		?>
		<div class="wrap timu-ui">
			<header class="timu-ui-head">
				<div>
					<h1><?php esc_html_e( 'Image Formats', 'thisismyurl-image-formats' ); ?></h1>
					<p class="timu-ui-lede"><?php esc_html_e( 'Changes pictures your website cannot show into pictures it can.', 'thisismyurl-image-formats' ); ?></p>
				</div>
				<div class="timu-ui-head-actions">
					<a class="button" href="<?php echo esc_url( admin_url( 'upload.php?mode=list' ) ); ?>"><?php esc_html_e( 'Open your pictures', 'thisismyurl-image-formats' ); ?></a>
				</div>
			</header>
			<hr class="wp-header-end">
			<?php settings_errors(); ?>

			<div class="timu-ui-assure <?php echo $kept ? '' : 'timu-ui-assure--note'; ?>">
				<span class="dashicons dashicons-shield" aria-hidden="true"></span>
				<div>
					<p><strong><?php esc_html_e( 'You do not need to change anything here.', 'thisismyurl-image-formats' ); ?></strong> <?php esc_html_e( 'Everything is already set up for you.', 'thisismyurl-image-formats' ); ?></p>
					<p>
						<?php
						echo esc_html(
							$kept
								? __( 'Before a picture is changed, a copy of the original is saved in a folder on your website. You can put it back at any time, from the picture\'s own page.', 'thisismyurl-image-formats' )
								: __( 'You chose not to keep copies of originals, so a changed picture cannot be put back. You can change that below.', 'thisismyurl-image-formats' )
						);
						?>
					</p>
				</div>
			</div>

			<p>
				<?php
				echo Ui::pill( $s['convert_nonweb'] ? 'good' : 'info', $s['convert_nonweb'] ? __( 'Fixing new BMP, TIFF and iPhone pictures: on', 'thisismyurl-image-formats' ) : __( 'Fixing new BMP, TIFF and iPhone pictures: off', 'thisismyurl-image-formats' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Ui::pill escapes.
				echo ' ';
				echo Ui::pill( $kept ? 'good' : 'note', $kept ? __( 'Copies of originals: kept', 'thisismyurl-image-formats' ) : __( 'Copies of originals: not kept', 'thisismyurl-image-formats' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Ui::pill escapes.
				?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<section class="timu-ui-sec">
					<div class="timu-ui-sec-head">
						<h2><?php esc_html_e( 'Pictures that web browsers cannot show', 'thisismyurl-image-formats' ); ?></h2>
						<p><?php esc_html_e( 'BMP, TIFF and iPhone (HEIC) pictures often do not appear on a website. This turns them into pictures that do.', 'thisismyurl-image-formats' ); ?></p>
					</div>
					<div class="timu-ui-card">
						<div class="timu-ui-field">
							<span class="timu-ui-label" id="timu-l-nonweb"><?php esc_html_e( 'When I add one', 'thisismyurl-image-formats' ); ?></span>
							<div role="group" aria-labelledby="timu-l-nonweb">
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[convert_nonweb]" value="1" aria-describedby="timu-h-nonweb" <?php checked( 1, (int) $s['convert_nonweb'] ); ?>> <?php esc_html_e( 'Fix it for me (recommended)', 'thisismyurl-image-formats' ); ?></label>
								<p class="timu-ui-help" id="timu-h-nonweb"><?php esc_html_e( 'This is already turned on. Pictures you added earlier are not touched unless you choose them yourself.', 'thisismyurl-image-formats' ); ?></p>
							</div>
						</div>
						<details class="timu-ui-more">
							<summary><?php esc_html_e( 'More options', 'thisismyurl-image-formats' ); ?></summary>
							<div class="timu-ui-field">
								<fieldset>
									<legend class="timu-ui-label"><?php esc_html_e( 'BMP and TIFF pictures become', 'thisismyurl-image-formats' ); ?></legend>
									<?php
									self::choice( 'graphic_format', 'png', $s['graphic_format'], __( 'PNG', 'thisismyurl-image-formats' ), __( 'Keeps every detail.', 'thisismyurl-image-formats' ), true );
									self::choice( 'graphic_format', 'webp', $s['graphic_format'], __( 'WebP', 'thisismyurl-image-formats' ), __( 'A smaller file that looks the same to most people.', 'thisismyurl-image-formats' ) );
									?>
								</fieldset>
							</div>
							<div class="timu-ui-field">
								<fieldset>
									<legend class="timu-ui-label"><?php esc_html_e( 'iPhone (HEIC) pictures become', 'thisismyurl-image-formats' ); ?></legend>
									<?php
									self::choice( 'photo_format', 'auto', $s['photo_format'], __( 'The best kind your website can use', 'thisismyurl-image-formats' ), '', true );
									self::choice( 'photo_format', 'avif', $s['photo_format'], __( 'AVIF', 'thisismyurl-image-formats' ), __( 'Very small files.', 'thisismyurl-image-formats' ) );
									self::choice( 'photo_format', 'webp', $s['photo_format'], __( 'WebP', 'thisismyurl-image-formats' ), __( 'Small files.', 'thisismyurl-image-formats' ) );
									self::choice( 'photo_format', 'jpeg', $s['photo_format'], __( 'JPEG', 'thisismyurl-image-formats' ), __( 'Works almost everywhere.', 'thisismyurl-image-formats' ) );
									?>
								</fieldset>
							</div>
						</details>
					</div>
				</section>

				<section class="timu-ui-sec">
					<div class="timu-ui-sec-head">
						<h2><?php esc_html_e( 'Make other pictures smaller (optional)', 'thisismyurl-image-formats' ); ?></h2>
						<p><?php esc_html_e( 'JPEG and PNG pictures already work everywhere, so this is turned off. You do not need it.', 'thisismyurl-image-formats' ); ?></p>
					</div>
					<div class="timu-ui-card">
						<div class="timu-ui-field">
							<span class="timu-ui-label" id="timu-l-jp"><?php esc_html_e( 'New JPEG and PNG pictures', 'thisismyurl-image-formats' ); ?></span>
							<div role="group" aria-labelledby="timu-l-jp">
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[convert_jpeg_png]" value="1" aria-describedby="timu-h-jp" <?php checked( 1, (int) $s['convert_jpeg_png'] ); ?>> <?php esc_html_e( 'Also make them smaller', 'thisismyurl-image-formats' ); ?></label>
								<p class="timu-ui-help" id="timu-h-jp"><?php esc_html_e( 'If the new file would not be smaller, your original picture is kept exactly as it was.', 'thisismyurl-image-formats' ); ?></p>
							</div>
						</div>
						<details class="timu-ui-more">
							<summary><?php esc_html_e( 'More options', 'thisismyurl-image-formats' ); ?></summary>
							<div class="timu-ui-field">
								<fieldset>
									<legend class="timu-ui-label"><?php esc_html_e( 'Kind of picture to use', 'thisismyurl-image-formats' ); ?></legend>
									<?php
									self::choice( 'web_format', 'auto', $s['web_format'], __( 'The best kind your website can use', 'thisismyurl-image-formats' ), '', true );
									self::choice( 'web_format', 'avif', $s['web_format'], __( 'AVIF', 'thisismyurl-image-formats' ), __( 'Very small files.', 'thisismyurl-image-formats' ) );
									self::choice( 'web_format', 'webp', $s['web_format'], __( 'WebP', 'thisismyurl-image-formats' ), __( 'Small files.', 'thisismyurl-image-formats' ) );
									?>
								</fieldset>
							</div>
						</details>
					</div>
				</section>

				<section class="timu-ui-sec">
					<div class="timu-ui-sec-head">
						<h2><?php esc_html_e( 'Keeping your originals safe', 'thisismyurl-image-formats' ); ?></h2>
						<p><?php esc_html_e( 'This is what lets you undo a change.', 'thisismyurl-image-formats' ); ?></p>
					</div>
					<div class="timu-ui-card">
						<div class="timu-ui-field">
							<fieldset>
								<legend class="timu-ui-label"><?php esc_html_e( 'Keep a copy of the original', 'thisismyurl-image-formats' ); ?></legend>
								<?php
								self::choice( 'backup', 'local', $s['backup'], __( 'Yes, keep a copy in a safe folder', 'thisismyurl-image-formats' ), __( 'You can put any original back whenever you like.', 'thisismyurl-image-formats' ), true );
								self::choice( 'backup', 'none', $s['backup'], __( 'No, delete the original after the change', 'thisismyurl-image-formats' ), __( 'You will not be able to put an original back.', 'thisismyurl-image-formats' ) );
								?>
							</fieldset>
						</div>
						<details class="timu-ui-more">
							<summary><?php esc_html_e( 'Picture quality', 'thisismyurl-image-formats' ); ?></summary>
							<div class="timu-ui-field">
								<label class="timu-ui-label" for="timu-quality"><?php esc_html_e( 'Quality', 'thisismyurl-image-formats' ); ?></label>
								<div>
									<input id="timu-quality" type="number" min="40" max="100" name="<?php echo esc_attr( $name ); ?>[quality]" value="<?php echo esc_attr( (string) (int) $s['quality'] ); ?>" class="small-text" aria-describedby="timu-h-q">
									<p class="timu-ui-help" id="timu-h-q"><?php esc_html_e( 'A number from 40 to 100. It is used whenever a picture is changed into WebP, AVIF or JPEG. 82 is a good choice. A higher number looks a little better and makes a bigger file.', 'thisismyurl-image-formats' ); ?></p>
								</div>
							</div>
						</details>
					</div>
				</section>

				<p class="timu-ui-actions"><?php submit_button( __( 'Save changes', 'thisismyurl-image-formats' ), 'primary', 'submit', false ); ?></p>
			</form>

			<p class="timu-ui-help">
				<?php
				echo esc_html(
					$names
						? sprintf(
							/* translators: %s: list of picture types, for example "AVIF, WEBP, PNG, JPEG" */
							__( 'Your website can make these kinds of picture: %s.', 'thisismyurl-image-formats' ),
							implode( ', ', $names )
						)
						: __( 'Your website cannot make new pictures right now, so nothing will be changed.', 'thisismyurl-image-formats' )
				);
				?>
			</p>

			<?php
			Ui::footer(
				'<span class="dashicons dashicons-shield" aria-hidden="true"></span><span>' . esc_html(
					$kept
						? __( 'A copy of every original is kept in a folder on your site, and you can put it back from the picture\'s own page.', 'thisismyurl-image-formats' )
						: __( 'You chose not to keep copies, so changed pictures cannot be put back.', 'thisismyurl-image-formats' )
				) . '</span>'
			);
			?>
		</div>
		<?php
	}
}
