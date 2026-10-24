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
	 * The folder name, with its random ending. Made once, by add_option(), which cannot be beaten by a second request.
	 *
	 * @return string
	 */
	private static function folder_name() {
		$token = (string) get_option( self::TOKEN, '' );
		if ( '' === $token ) {
			add_option( self::TOKEN, strtolower( wp_generate_password( 16, false, false ) ), '', false );
			wp_cache_delete( self::TOKEN, 'options' );
			$token = (string) get_option( self::TOKEN, '' );
		}
		return self::DIR . '-' . $token;
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
		$dir = trailingslashit( $uploads['basedir'] ) . self::folder_name() . '/';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		// Apache and IIS honour these. Elsewhere the random folder name is the protection.
		$guards = array(
			'index.php'  => "<?php\n// Silence is golden.\n",
			'.htaccess'  => "Require all denied\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $guards as $file => $body ) {
			if ( ! file_exists( $dir . $file ) ) {
				file_put_contents( $dir . $file, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		return $dir;
	}

	/**
	 * Move the original into the local backup folder.
	 *
	 * @param string $path Absolute path of the original.
	 * @param string $key  A unique key (attachment ID, or the converted file's basename during upload).
	 * @return string|false Path relative to the uploads folder (it includes the backup folder's own name), or false on failure.
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
		if ( filesize( $sub . $name ) !== filesize( $path ) ) {
			wp_delete_file( $sub . $name );
			return false;
		}
		return basename( untrailingslashit( $dir ) ) . '/' . sanitize_file_name( (string) $key ) . '/' . $name;
	}

	/**
	 * Absolute path of a stored backup. The record holds the folder name, so the saved original is found even if the setting that names the
	 * folder is lost.
	 *
	 * @param string $relative Path relative to the uploads folder.
	 * @return string Empty when the path is unsafe or missing.
	 */
	public static function resolve( $relative ) {
		$relative = (string) $relative;
		if ( 0 !== strpos( $relative, self::DIR . '-' ) || false !== strpos( $relative, '..' ) || false !== strpos( $relative, '\\' ) ) {
			return '';
		}
		$uploads = wp_get_upload_dir();
		$full    = trailingslashit( $uploads['basedir'] ) . $relative;
		return is_file( $full ) ? $full : '';
	}
}
