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
	 * Register the screen (admin_menu).
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_options_page(
			__( 'Image Formats', 'thisismyurl-image-formats' ),
			__( 'Image Formats', 'thisismyurl-image-formats' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Settings screen. Written for someone who does not use computers much: it says what the tool does, says it is safe before anything
	 * else, and has every setting already chosen.
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
		$name = self::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Image Formats', 'thisismyurl-image-formats' ); ?></h1>

			<p style="max-width:44em;font-size:15px">
				<?php esc_html_e( 'Some picture files are too big for a website, or cannot be shown in a web browser at all. This tool changes them into pictures that work well on websites.', 'thisismyurl-image-formats' ); ?>
			</p>

			<div class="notice notice-info inline" style="max-width:44em;padding:4px 14px">
				<p><strong><?php esc_html_e( 'You do not need to change anything on this page.', 'thisismyurl-image-formats' ); ?></strong>
				<?php esc_html_e( 'Everything is already set up for you.', 'thisismyurl-image-formats' ); ?></p>
				<p><?php esc_html_e( 'Before any picture is changed, a copy of the original is saved in a safe place. You can put the original back at any time, from the picture\'s own page in your Media Library.', 'thisismyurl-image-formats' ); ?></p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'Pictures that web browsers cannot show', 'thisismyurl-image-formats' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Fix them when I add them', 'thisismyurl-image-formats' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[convert_nonweb]" value="1" <?php checked( 1, (int) $s['convert_nonweb'] ); ?>> <?php esc_html_e( 'Yes, fix BMP, TIFF and iPhone (HEIC) pictures', 'thisismyurl-image-formats' ); ?></label>
							<p class="description"><?php esc_html_e( 'These kinds of picture often do not show on a website. This is already turned on for you.', 'thisismyurl-image-formats' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="timu-graphic"><?php esc_html_e( 'BMP and TIFF pictures become', 'thisismyurl-image-formats' ); ?></label></th>
						<td>
							<select id="timu-graphic" name="<?php echo esc_attr( $name ); ?>[graphic_format]">
								<option value="png" <?php selected( 'png', $s['graphic_format'] ); ?>><?php esc_html_e( 'PNG (recommended). Keeps every detail.', 'thisismyurl-image-formats' ); ?></option>
								<option value="webp" <?php selected( 'webp', $s['graphic_format'] ); ?>><?php esc_html_e( 'WebP. A smaller file that looks the same to most people.', 'thisismyurl-image-formats' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="timu-photo"><?php esc_html_e( 'iPhone (HEIC) pictures become', 'thisismyurl-image-formats' ); ?></label></th>
						<td>
							<select id="timu-photo" name="<?php echo esc_attr( $name ); ?>[photo_format]">
								<option value="auto" <?php selected( 'auto', $s['photo_format'] ); ?>><?php esc_html_e( 'The best kind your website can use (recommended)', 'thisismyurl-image-formats' ); ?></option>
								<option value="avif" <?php selected( 'avif', $s['photo_format'] ); ?>><?php esc_html_e( 'AVIF. Very small files.', 'thisismyurl-image-formats' ); ?></option>
								<option value="webp" <?php selected( 'webp', $s['photo_format'] ); ?>><?php esc_html_e( 'WebP. Small files.', 'thisismyurl-image-formats' ); ?></option>
								<option value="jpeg" <?php selected( 'jpeg', $s['photo_format'] ); ?>><?php esc_html_e( 'JPEG. Works almost everywhere.', 'thisismyurl-image-formats' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Make other pictures smaller (optional)', 'thisismyurl-image-formats' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'JPEG and PNG pictures', 'thisismyurl-image-formats' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[convert_jpeg_png]" value="1" <?php checked( 1, (int) $s['convert_jpeg_png'] ); ?>> <?php esc_html_e( 'Also make new JPEG and PNG pictures smaller', 'thisismyurl-image-formats' ); ?></label>
							<p class="description"><?php esc_html_e( 'This is turned off. JPEG and PNG pictures already work everywhere, so there is no need. If you turn it on and the new file would not be smaller, your original picture is kept exactly as it was.', 'thisismyurl-image-formats' ); ?></p>
							<p>
								<label for="timu-web"><?php esc_html_e( 'Kind of picture to use:', 'thisismyurl-image-formats' ); ?></label>
								<select id="timu-web" name="<?php echo esc_attr( $name ); ?>[web_format]">
									<option value="auto" <?php selected( 'auto', $s['web_format'] ); ?>><?php esc_html_e( 'The best kind your website can use (recommended)', 'thisismyurl-image-formats' ); ?></option>
									<option value="avif" <?php selected( 'avif', $s['web_format'] ); ?>>AVIF</option>
									<option value="webp" <?php selected( 'webp', $s['web_format'] ); ?>>WebP</option>
								</select>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="timu-quality"><?php esc_html_e( 'Picture quality', 'thisismyurl-image-formats' ); ?></label></th>
						<td>
							<input id="timu-quality" type="number" min="40" max="100" name="<?php echo esc_attr( $name ); ?>[quality]" value="<?php echo esc_attr( (string) (int) $s['quality'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'A number from 40 to 100. 82 is a good choice. A higher number looks a little better and makes a bigger file.', 'thisismyurl-image-formats' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Keeping your originals safe', 'thisismyurl-image-formats' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="timu-backup"><?php esc_html_e( 'Keep a copy of the original', 'thisismyurl-image-formats' ); ?></label></th>
						<td>
							<select id="timu-backup" name="<?php echo esc_attr( $name ); ?>[backup]">
								<option value="local" <?php selected( 'local', $s['backup'] ); ?>><?php esc_html_e( 'Yes, keep a copy in a safe folder (recommended)', 'thisismyurl-image-formats' ); ?></option>
								<option value="none" <?php selected( 'none', $s['backup'] ); ?>><?php esc_html_e( 'No, delete the original after the change', 'thisismyurl-image-formats' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'If you choose "No", you will not be able to put an original back. We suggest leaving this on "Yes".', 'thisismyurl-image-formats' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save changes', 'thisismyurl-image-formats' ) ); ?>
			</form>

			<p class="description" style="max-width:44em">
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
		</div>
		<?php
	}
}
