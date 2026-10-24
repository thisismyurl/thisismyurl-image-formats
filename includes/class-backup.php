<?php
/**
 * Keeping the original so a conversion can be undone.
 *
 * Vault is a soft dependency: when it is installed it records the event and holds the original. When it is not, the original goes to a
 * folder inside uploads. Neither is required for conversion to work, and "none" deletes the original.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Original-file storage and the Vault adapter.
 */
class Backup {

	const DIR = 'timu-formats-backups';

	/**
	 * Whether the Vault plugin's event API is available.
	 *
	 * @return bool
	 */
	public static function vault_present() {
		return function_exists( 'timu_vault_begin_event' ) && function_exists( 'timu_vault_complete_event' );
	}

	/**
	 * Which store will hold the original, given the setting and what is installed.
	 *
	 * @return string vault, local or none.
	 */
	public static function mode() {
		$setting = Settings::value( 'backup' );
		if ( 'none' === $setting ) {
			return 'none';
		}
		if ( 'auto' === $setting && self::vault_present() ) {
			return 'vault';
		}
		return 'local';
	}

	/**
	 * Open a Vault event for an attachment conversion, if Vault is in use.
	 *
	 * @param int    $attachment_id Attachment ID, or 0 during upload (no attachment exists yet).
	 * @param string $label         Human label for Vault's history screen.
	 * @return int|false Vault event ID, or false when Vault is not in use.
	 */
	public static function begin_vault_event( $attachment_id, $label ) {
		if ( 'vault' !== self::mode() || ! $attachment_id ) {
			return false;
		}
		$depends_on = function_exists( 'timu_vault_get_latest_event_id' ) ? timu_vault_get_latest_event_id( $attachment_id ) : 0;
		return timu_vault_begin_event(
			$attachment_id,
			array(
				'operation'       => 'convert_image_format',
				'operation_label' => $label,
				'plugin'          => 'thisismyurl-image-formats',
				'depends_on'      => $depends_on,
			)
		);
	}

	/**
	 * Close a Vault event with what the file became.
	 *
	 * @param int|false $event_id Event ID from begin_vault_event().
	 * @param int       $attachment_id Attachment ID.
	 * @param string    $path          New file path.
	 * @param string    $mime          New MIME type.
	 * @param bool      $success       Whether the conversion succeeded.
	 * @return void
	 */
	public static function complete_vault_event( $event_id, $attachment_id, $path, $mime, $success ) {
		if ( ! $event_id ) {
			return;
		}
		timu_vault_complete_event(
			$event_id,
			array(
				'file_after' => array(
					'path' => $path,
					'mime' => $mime,
					'size' => file_exists( $path ) ? (int) filesize( $path ) : 0,
				),
				'meta_after' => wp_get_attachment_metadata( $attachment_id ),
				'success'    => (bool) $success,
			)
		);
	}

	/**
	 * Absolute backup directory, created on demand and closed to directory listing.
	 *
	 * @return string Path with a trailing slash, or an empty string when it cannot be created.
	 */
	public static function dir() {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		$dir = trailingslashit( $uploads['basedir'] ) . self::DIR . '/';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		if ( ! file_exists( $dir . 'index.php' ) ) {
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $dir . '.htaccess' ) ) {
			file_put_contents( $dir . '.htaccess', "Require all denied\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}

	/**
	 * Move the original into the local backup folder.
	 *
	 * @param string $path          Absolute path of the original.
	 * @param string $key           A unique key (attachment ID, or the converted file's basename during upload).
	 * @return string|false Path relative to the backup folder, or false on failure.
	 */
	public static function store_local( $path, $key ) {
		$dir = self::dir();
		if ( '' === $dir || ! file_exists( $path ) ) {
			return false;
		}
		$sub = $dir . sanitize_file_name( (string) $key ) . '/';
		if ( ! wp_mkdir_p( $sub ) ) {
			return false;
		}
		$name = wp_unique_filename( $sub, wp_basename( $path ) );
		if ( ! copy( $path, $sub . $name ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			return false;
		}
		return sanitize_file_name( (string) $key ) . '/' . $name;
	}

	/**
	 * Absolute path of a stored backup, kept inside the backup folder.
	 *
	 * @param string $relative Path relative to the backup folder.
	 * @return string Empty when the path is unsafe or missing.
	 */
	public static function resolve( $relative ) {
		$dir  = self::dir();
		$full = $dir . ltrim( (string) $relative, '/' );
		if ( '' === $dir || false !== strpos( $relative, '..' ) || ! file_exists( $full ) ) {
			return '';
		}
		return $full;
	}
}
