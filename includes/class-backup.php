<?php
/**
 * Keeping the original so a conversion can be undone.
 *
 * The original goes to a folder inside uploads, closed to directory listing. "None" deletes it after a successful conversion.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Original-file storage.
 */
class Backup {

	const DIR    = 'timu-formats-backups';
	const TOKEN  = 'timu_formats_token';

	/**
	 * Where the original goes, given the setting.
	 *
	 * @return string local or none.
	 */
	public static function mode() {
		return 'none' === Settings::value( 'backup' ) ? 'none' : 'local';
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
		// A random ending on the folder name, so that on a server that ignores the access rule below the folder still cannot be guessed.
		$token = (string) get_option( self::TOKEN, '' );
		if ( '' === $token ) {
			$token = strtolower( wp_generate_password( 16, false, false ) );
			update_option( self::TOKEN, $token, false );
		}
		$dir = trailingslashit( $uploads['basedir'] ) . self::DIR . '-' . $token . '/';
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
