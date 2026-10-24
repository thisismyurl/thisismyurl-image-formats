<?php
/**
 * The conversion pipeline: convert a file, convert an attachment, restore an attachment.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Converts images with WordPress's own image editor (GD or Imagick). Nothing is installed on the server and nothing leaves the site.
 */
class Converter {

	const META_ORIGINAL = '_timu_formats_original';
	const META_SAVINGS  = '_timu_formats_savings';
	const META_OLD_URLS = '_timu_formats_old_urls';

	/**
	 * Write a converted copy of a file next to it. The original is not touched.
	 *
	 * @param string $path        Absolute path of the source file.
	 * @param string $source_mime Source MIME type.
	 * @param string $target_mime Target MIME type.
	 * @return array|WP_Error {path, mime, width, height, size} on success.
	 */
	public static function convert_file( $path, $source_mime, $target_mime ) {
		$ext = Capabilities::extension( $target_mime );
		if ( '' === $ext ) {
			return new WP_Error( 'timu_formats_target', __( 'That target format is not supported.', 'thisismyurl-image-formats' ) );
		}
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'timu_formats_missing', __( 'The source file is missing or unreadable.', 'thisismyurl-image-formats' ) );
		}

		// The target MIME type is passed so GD, which picks its editor from the file's own type, still writes the format asked for.
		$editor = wp_get_image_editor( $path, array( 'mime_type' => $target_mime ) );
		if ( is_wp_error( $editor ) ) {
			return new WP_Error( 'timu_formats_read', __( 'This server cannot read that image.', 'thisismyurl-image-formats' ), $editor->get_error_message() );
		}

		$size = $editor->get_size();
		if ( in_array( $source_mime, array( 'image/jpeg', 'image/heic', 'image/heif' ), true ) && method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}
		$editor->set_quality( Policy::quality_for( $target_mime, $source_mime ) );

		$dir  = dirname( $path );
		$name = wp_unique_filename( $dir, pathinfo( $path, PATHINFO_FILENAME ) . '.' . $ext );
		$dest = trailingslashit( $dir ) . $name;

		$saved = $editor->save( $dest, $target_mime );
		if ( is_wp_error( $saved ) ) {
			return new WP_Error( 'timu_formats_write', __( 'This server could not write that format.', 'thisismyurl-image-formats' ), $saved->get_error_message() );
		}
		$out = isset( $saved['path'] ) ? $saved['path'] : $dest;

		// Trust nothing: the file has to exist, be non-empty and really be the format asked for.
		$real = file_exists( $out ) ? wp_get_image_mime( $out ) : false;
		if ( ! $real || $real !== $target_mime || 0 === (int) filesize( $out ) ) {
			if ( file_exists( $out ) ) {
				wp_delete_file( $out );
			}
			return new WP_Error( 'timu_formats_verify', __( 'The converted file did not check out, so the original was kept.', 'thisismyurl-image-formats' ) );
		}

		return array(
			'path'   => $out,
			'mime'   => $target_mime,
			'width'  => isset( $saved['width'] ) ? (int) $saved['width'] : (int) $size['width'],
			'height' => isset( $saved['height'] ) ? (int) $saved['height'] : (int) $size['height'],
			'size'   => (int) filesize( $out ),
		);
	}

	/**
	 * Convert an attachment already in the Media Library.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $context       manual, bulk or upload (upload conversions use convert_upload() instead).
	 * @param string $force_target  Optional target MIME type, overriding the policy.
	 * @param bool   $dry_run       Report what would happen without changing anything.
	 * @return array|WP_Error {status, from, to, saved, ...}
	 */
	public static function convert_attachment( $attachment_id, $context = 'manual', $force_target = '', $dry_run = false ) {
		$attachment_id = (int) $attachment_id;
		$mime          = get_post_mime_type( $attachment_id );
		$path          = get_attached_file( $attachment_id );
		if ( ! $mime || ! $path || ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error( 'timu_formats_not_image', __( 'That is not an image in the Media Library.', 'thisismyurl-image-formats' ) );
		}
		if ( get_post_meta( $attachment_id, self::META_ORIGINAL, true ) ) {
			return array( 'status' => 'skipped', 'reason' => 'already converted', 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}
		if ( 'image/gif' === $mime || 'image/svg+xml' === $mime ) {
			return array( 'status' => 'skipped', 'reason' => 'format is left alone', 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}

		$target = '' !== $force_target ? $force_target : Policy::target_for( $mime, $context );
		if ( '' === $target || $target === $mime ) {
			return array( 'status' => 'skipped', 'reason' => 'no conversion needed', 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}
		if ( ! Capabilities::can_write( $target ) ) {
			return new WP_Error( 'timu_formats_unsupported', __( 'This server cannot write that format.', 'thisismyurl-image-formats' ) );
		}
		$old_size = file_exists( $path ) ? (int) filesize( $path ) : 0;
		if ( $dry_run ) {
			return array( 'status' => 'would-convert', 'from' => $mime, 'to' => $target, 'saved' => 0, 'old_size' => $old_size );
		}

		$lock = 'timu_formats_lock_' . $attachment_id;
		if ( get_transient( $lock ) ) {
			return new WP_Error( 'timu_formats_locked', __( 'That image is being converted right now.', 'thisismyurl-image-formats' ) );
		}
		set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );

		$result = self::convert_attachment_locked( $attachment_id, $mime, $path, $target, $context, $old_size );
		delete_transient( $lock );
		return $result;
	}

	/**
	 * The work of convert_attachment(), run while the lock is held.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $mime          Current MIME type.
	 * @param string $path          Current absolute path.
	 * @param string $target        Target MIME type.
	 * @param string $context       Context label.
	 * @param int    $old_size      Current file size in bytes.
	 * @return array|WP_Error
	 */
	private static function convert_attachment_locked( $attachment_id, $mime, $path, $target, $context, $old_size ) {
		$new = self::convert_file( $path, $mime, $target );
		if ( is_wp_error( $new ) ) {
			return $new;
		}

		// A modern format should be an improvement. If JPEG or PNG would grow, leave them as they are.
		if ( in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) && $old_size > 0 && $new['size'] >= $old_size ) {
			wp_delete_file( $new['path'] );
			return array( 'status' => 'skipped', 'reason' => 'converted file was not smaller', 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}

		// Remember every URL the old image answered to, so references to it can be updated.
		$old_urls = self::attachment_urls( $attachment_id );
		$uploads  = wp_get_upload_dir();
		$rel_old  = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $path ), '/' );

		$mode   = Backup::mode();
		$stored = 'none';
		if ( 'local' === $mode ) {
			$kept = Backup::store_local( $path, $attachment_id );
			if ( false === $kept ) {
				wp_delete_file( $new['path'] );
					return new WP_Error( 'timu_formats_backup', __( 'The original could not be backed up, so nothing was changed.', 'thisismyurl-image-formats' ) );
			}
			$stored = 'local:' . $kept;
		}

		// Remove the old file and its generated sizes, then point the attachment at the new file.
		$old_meta = self::meta_for_delete( $attachment_id );
		$backups  = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		wp_delete_attachment_files( $attachment_id, $old_meta, is_array( $backups ) ? $backups : array(), $path );

		update_attached_file( $attachment_id, $new['path'] );
		wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => $target ) );
		$meta = wp_generate_attachment_metadata( $attachment_id, $new['path'] );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}

		$saved = $old_size - $new['size'];
		update_post_meta(
			$attachment_id,
			self::META_ORIGINAL,
			array(
				'file'      => $rel_old,
				'mime'      => $mime,
				'size'      => $old_size,
				'backup'    => $stored,
				'to'        => $target,
				'context'   => $context,
				'converted' => time(),
			)
		);
		update_post_meta( $attachment_id, self::META_SAVINGS, $saved );
		update_post_meta( $attachment_id, self::META_OLD_URLS, $old_urls );

		/**
		 * Fires after an attachment was converted.
		 *
		 * @param int   $attachment_id Attachment ID.
		 * @param array $details       from, to, saved, old_urls.
		 */
		do_action( 'timu_formats_converted', $attachment_id, array( 'from' => $mime, 'to' => $target, 'saved' => $saved, 'old_urls' => $old_urls ) );

		return array( 'status' => 'converted', 'from' => $mime, 'to' => $target, 'saved' => $saved, 'old_size' => $old_size, 'new_size' => $new['size'], 'backup' => $stored );
	}

	/**
	 * Attachment metadata in the shape wp_delete_attachment_files() expects. Formats WordPress cannot make sizes for (TIFF) have no
	 * metadata at all, and the function reads the 'file' key unguarded.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	private static function meta_for_delete( $attachment_id ) {
		$meta = wp_get_attachment_metadata( $attachment_id );
		$meta = is_array( $meta ) ? $meta : array();
		if ( empty( $meta['file'] ) ) {
			$meta['file'] = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		}
		return $meta;
	}

	/**
	 * Every URL an attachment currently answers to: the full file and each generated size.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string[]
	 */
	public static function attachment_urls( $attachment_id ) {
		$urls = array();
		$full = wp_get_attachment_url( $attachment_id );
		if ( $full ) {
			$urls[] = $full;
			$meta   = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				$base = trailingslashit( dirname( $full ) );
				foreach ( $meta['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) ) {
						$urls[] = $base . $size['file'];
					}
				}
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Put an attachment back the way it was before conversion, from the local backup.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|WP_Error
	 */
	public static function restore_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$info          = get_post_meta( $attachment_id, self::META_ORIGINAL, true );
		if ( ! is_array( $info ) ) {
			return new WP_Error( 'timu_formats_nothing', __( 'That image has not been converted by this plugin.', 'thisismyurl-image-formats' ) );
		}
		if ( 0 !== strpos( (string) $info['backup'], 'local:' ) ) {
			return new WP_Error( 'timu_formats_no_backup', __( 'No original was kept for that image.', 'thisismyurl-image-formats' ) );
		}
		$source = Backup::resolve( substr( $info['backup'], 6 ) );
		if ( '' === $source ) {
			return new WP_Error( 'timu_formats_backup_missing', __( 'The backed-up original could not be found.', 'thisismyurl-image-formats' ) );
		}

		$current = get_attached_file( $attachment_id );
		$dir     = dirname( $current );
		$name    = wp_unique_filename( $dir, wp_basename( $info['file'] ) );
		$dest    = trailingslashit( $dir ) . $name;
		if ( ! copy( $source, $dest ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			return new WP_Error( 'timu_formats_restore_copy', __( 'The original could not be copied back.', 'thisismyurl-image-formats' ) );
		}

		wp_delete_attachment_files( $attachment_id, self::meta_for_delete( $attachment_id ), array(), $current );

		update_attached_file( $attachment_id, $dest );
		wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => $info['mime'] ) );
		$meta = wp_generate_attachment_metadata( $attachment_id, $dest );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}
		wp_delete_file( $source );
		delete_post_meta( $attachment_id, self::META_ORIGINAL );
		delete_post_meta( $attachment_id, self::META_SAVINGS );
		delete_post_meta( $attachment_id, self::META_OLD_URLS );

		do_action( 'timu_formats_restored', $attachment_id );
		return array( 'status' => 'restored', 'mime' => $info['mime'] );
	}
}
