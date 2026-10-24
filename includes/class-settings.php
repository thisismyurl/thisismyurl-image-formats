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
	 * Settings screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s       = self::get();
		$targets = Capabilities::targets();
		$name    = self::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Image Formats', 'thisismyurl-image-formats' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: image library name (GD or Imagick), 2: list of formats */
					esc_html__( 'Your server uses %1$s and can write: %2$s.', 'thisismyurl-image-formats' ),
					esc_html( Capabilities::library() ),
					esc_html( implode( ', ', array_map( static function ( $m ) { return strtoupper( str_replace( 'image/', '', $m ) ); }, array_keys( array_filter( $targets ) ) ) ) ?: __( 'nothing', 'thisismyurl-image-formats' ) )
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Formats browsers cannot show', 'thisismyurl-image-formats' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[convert_nonweb]" value="1" <?php checked( 1, (int) $s['convert_nonweb'] ); ?>> <?php esc_html_e( 'Convert BMP, TIFF and HEIC uploads', 'thisismyurl-image-formats' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="timu-graphic"><?php esc_html_e( 'BMP and TIFF become', 'thisismyurl-image-formats' ); ?></label></th>
						<td>
							<select id="timu-graphic" name="<?php echo esc_attr( $name ); ?>[graphic_format]">
								<option value="png" <?php selected( 'png', $s['graphic_format'] ); ?>>PNG (lossless)</option>
								<option value="webp" <?php selected( 'webp', $s['graphic_format'] ); ?>>WebP (maximum quality)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="timu-photo"><?php esc_html_e( 'HEIC and HEIF become', 'thisismyurl-image-formats' ); ?></label></th>
						<td>
							<select id="timu-photo" name="<?php echo esc_attr( $name ); ?>[photo_format]">
								<option value="auto" <?php selected( 'auto', $s['photo_format'] ); ?>><?php esc_html_e( 'Best available (AVIF, then WebP, then JPEG)', 'thisismyurl-image-formats' ); ?></option>
								<option value="avif" <?php selected( 'avif', $s['photo_format'] ); ?>>AVIF</option>
								<option value="webp" <?php selected( 'webp', $s['photo_format'] ); ?>>WebP</option>
								<option value="jpeg" <?php selected( 'jpeg', $s['photo_format'] ); ?>>JPEG</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'JPEG and PNG', 'thisismyurl-image-formats' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[convert_jpeg_png]" value="1" <?php checked( 1, (int) $s['convert_jpeg_png'] ); ?>> <?php esc_html_e( 'Also convert JPEG and PNG uploads to a modern format', 'thisismyurl-image-formats' ); ?></label>
							<p class="description"><?php esc_html_e( 'Off by default. WordPress keeps JPEG and PNG as they are, and they work everywhere.', 'thisismyurl-image-formats' ); ?></p>
							<select name="<?php echo esc_attr( $name ); ?>[web_format]">
								<option value="auto" <?php selected( 'auto', $s['web_format'] ); ?>><?php esc_html_e( 'Best available (AVIF, then WebP)', 'thisismyurl-image-formats' ); ?></option>
								<option value="avif" <?php selected( 'avif', $s['web_format'] ); ?>>AVIF</option>
								<option value="webp" <?php selected( 'webp', $s['web_format'] ); ?>>WebP</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="timu-quality"><?php esc_html_e( 'Quality for lossy formats', 'thisismyurl-image-formats' ); ?></label></th>
						<td><input id="timu-quality" type="number" min="40" max="100" name="<?php echo esc_attr( $name ); ?>[quality]" value="<?php echo esc_attr( (string) (int) $s['quality'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="timu-backup"><?php esc_html_e( 'Keep the original', 'thisismyurl-image-formats' ); ?></label></th>
						<td>
							<select id="timu-backup" name="<?php echo esc_attr( $name ); ?>[backup]">
								<option value="local" <?php selected( 'local', $s['backup'] ); ?>><?php esc_html_e( 'In a backup folder inside uploads (recommended)', 'thisismyurl-image-formats' ); ?></option>
								<option value="none" <?php selected( 'none', $s['backup'] ); ?>><?php esc_html_e( 'Do not keep it (the original is deleted)', 'thisismyurl-image-formats' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
