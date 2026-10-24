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
	 * Files converted during this request, keyed by path without extension, with what WordPress should carry over for them.
	 *
	 * WordPress generates thumbnails and reads image metadata after we hand the converted file back, so these are looked up by path from
	 * the `wp_read_image_metadata` filter.
	 *
	 * @var array<string,array>
	 */
	private static $registry = array();

	/**
	 * Remember a converted file and the image metadata (camera, exposure, date) of the original it came from.
	 *
	 * @param string $path       Absolute path of the converted file.
	 * @param array  $image_meta The original's image_meta.
	 * @return void
	 */
	public static function remember( $path, array $image_meta ) {
		self::$registry[ self::registry_key( $path ) ] = array( 'image_meta' => $image_meta );
	}

	/**
	 * Registry entry for a file or any file derived from it (the -scaled copy, a thumbnail).
	 *
	 * @param string $filename Absolute path.
	 * @return array|null
	 */
	public static function registry_match( $filename ) {
		$norm = wp_normalize_path( (string) $filename );
		foreach ( self::$registry as $key => $data ) {
			// The file itself, or one WordPress derived from it (name-scaled.png, name-rotated.png, name-150x150.png). Never just a longer name.
			if ( 1 === preg_match( '/^' . preg_quote( $key, '/' ) . '(-scaled|-rotated)?(-\d+x\d+)?\.[a-z0-9]+$/i', $norm ) ) {
				return $data;
			}
		}
		return null;
	}

	/**
	 * Let go of a picture's remembered details once WordPress has finished building its metadata (filter: wp_generate_attachment_metadata).
	 *
	 * @param array $metadata      Attachment metadata, passed through untouched.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public static function release( $metadata, $attachment_id ) {
		$file = get_attached_file( (int) $attachment_id );
		if ( $file ) {
			unset( self::$registry[ self::registry_key( $file ) ] );
		}
		return $metadata;
	}

	/**
	 * Registry key: the normalised path without its extension.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	private static function registry_key( $path ) {
		$norm = wp_normalize_path( $path );
		$ext  = pathinfo( $norm, PATHINFO_EXTENSION );
		return '' === $ext ? $norm : substr( $norm, 0, -( strlen( $ext ) + 1 ) );
	}

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
			return new WP_Error( 'timu_formats_target', __( 'This website cannot make that kind of picture.', 'thisismyurl-image-formats' ) );
		}
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'timu_formats_missing', __( 'The picture file could not be found, so nothing was changed.', 'thisismyurl-image-formats' ) );
		}

		// The target MIME type is passed so GD, which picks its editor from the file's own type, still writes the format asked for.
		$editor = wp_get_image_editor( $path, array( 'mime_type' => $target_mime ) );
		if ( is_wp_error( $editor ) ) {
			return new WP_Error( 'timu_formats_read', __( 'This website could not open that picture, so nothing was changed.', 'thisismyurl-image-formats' ), $editor->get_error_message() );
		}

		$size    = $editor->get_size();
		$rotated = false;
		if ( in_array( $source_mime, array( 'image/jpeg', 'image/heic', 'image/heif' ), true ) && method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$rotated = ( true === $editor->maybe_exif_rotate() ); // The pixels are upright now, so the new file must not be turned again.
		}
		$editor->set_quality( Policy::quality_for( $target_mime, $source_mime ) );

		$dir  = dirname( $path );
		$name = wp_unique_filename( $dir, pathinfo( $path, PATHINFO_FILENAME ) . '.' . $ext );
		$dest = trailingslashit( $dir ) . $name;

		$saved = $editor->save( $dest, $target_mime );
		if ( is_wp_error( $saved ) ) {
			return new WP_Error( 'timu_formats_write', __( 'This website could not make the new picture, so nothing was changed. Your picture is safe.', 'thisismyurl-image-formats' ), $saved->get_error_message() );
		}
		$out = isset( $saved['path'] ) ? $saved['path'] : $dest;

		// Trust nothing: the file has to exist, be non-empty and really be the format asked for.
		$real = file_exists( $out ) ? wp_get_image_mime( $out ) : false;
		if ( ! $real || $real !== $target_mime || 0 === (int) filesize( $out ) ) {
			if ( file_exists( $out ) ) {
				wp_delete_file( $out );
			}
			return new WP_Error( 'timu_formats_verify', __( 'The new picture did not turn out right, so your original was kept. Nothing was changed.', 'thisismyurl-image-formats' ) );
		}

		return array(
			'path'   => $out,
			'mime'   => $target_mime,
			'width'  => isset( $saved['width'] ) ? (int) $saved['width'] : (int) $size['width'],
			'height' => isset( $saved['height'] ) ? (int) $saved['height'] : (int) $size['height'],
			'size'   => (int) filesize( $out ),
			'rotated' => $rotated,
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
			return new WP_Error( 'timu_formats_not_image', __( 'That is not a picture in your Media Library.', 'thisismyurl-image-formats' ) );
		}
		$real = file_exists( $path ) ? wp_get_image_mime( $path ) : false;
		if ( $real && $real !== $mime ) {
			$mime = $real;
		}
		if ( get_post_meta( $attachment_id, self::META_ORIGINAL, true ) ) {
			return array( 'status' => 'skipped', 'reason' => 'already converted', 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}
		if ( 'image/gif' === $mime || 'image/svg+xml' === $mime ) {
			return array( 'status' => 'skipped', 'reason' => 'format is left alone', 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}
		$unsafe = self::unsafe_reason( $attachment_id, $path );
		if ( '' !== $unsafe ) {
			return array( 'status' => 'skipped', 'reason' => $unsafe, 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}

		$target = '' !== $force_target ? $force_target : Policy::target_for( $mime, $context );
		if ( '' === $target || $target === $mime ) {
			return array( 'status' => 'skipped', 'reason' => 'no conversion needed', 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}
		if ( ! Capabilities::can_write( $target ) ) {
			return new WP_Error( 'timu_formats_unsupported', __( 'This website cannot make that kind of picture.', 'thisismyurl-image-formats' ) );
		}
		// On a network, the site's allowed file types still apply to what this plugin makes.
		if ( ! in_array( $target, array_values( get_allowed_mime_types() ), true ) ) {
			return array( 'status' => 'skipped', 'reason' => 'type not allowed', 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}
		$old_size = file_exists( $path ) ? (int) filesize( $path ) : 0;
		if ( $dry_run ) {
			return array( 'status' => 'would-convert', 'from' => $mime, 'to' => $target, 'saved' => 0, 'old_size' => $old_size );
		}

		if ( ! self::take_lock( $attachment_id ) ) {
			return new WP_Error( 'timu_formats_locked', __( 'This picture is being changed right now. Please wait a minute and try again.', 'thisismyurl-image-formats' ) );
		}
		try {
			return self::convert_attachment_locked( $attachment_id, $mime, $path, $target, $context, $old_size );
		} finally {
			self::release_lock( $attachment_id );
		}
	}

	/**
	 * Take the per-picture lock. The row is inserted with INSERT IGNORE, so only one request can create it. A lock older than five minutes is
	 * stale and is taken over with a compare-and-set.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private static function take_lock( $attachment_id ) {
		global $wpdb;
		$name = 'timu_formats_lock_' . (int) $attachment_id;
		$now  = (string) time();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- a lock must be taken in one atomic step, which the Options API cannot do.
		$got = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $now ) );
		if ( 1 === (int) $got ) {
			return true;
		}
		$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( '' !== $held && (int) $now - (int) $held >= 5 * MINUTE_IN_SECONDS ) {
			$took = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $now, $name, $held ) );
			return 1 === (int) $took;
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		return false;
	}

	/**
	 * Let go of the per-picture lock.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private static function release_lock( $attachment_id ) {
		global $wpdb;
		$name = 'timu_formats_lock_' . (int) $attachment_id;
		$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( $name, 'options' );
	}

	/**
	 * Cases where changing a picture could lose something that cannot be put back, so the picture is left exactly as it is.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $path          Absolute path of the picture.
	 * @return string A reason code, or an empty string when it is safe to go on.
	 */
	private static function unsafe_reason( $attachment_id, $path ) {
		if ( self::is_animated_png( $path ) ) {
			return 'animated';
		}
		// A picture cropped or turned inside WordPress keeps its untouched original as a "backup size". Changing the picture would delete it.
		$edited = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		if ( ! empty( $edited ) ) {
			return 'edited in wordpress';
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) ) {
			foreach ( array( 'source_image', 'animated_video', 'animated_video_poster' ) as $key ) {
				if ( ! empty( $meta[ $key ] ) ) {
					return 'extra files';
				}
			}
		}
		if ( self::shared_file( $attachment_id ) ) {
			return 'shared file';
		}
		return '';
	}

	/**
	 * Does another picture in the Library use the very same file? (Some translation plugins do this.) Changing one would break the other.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private static function shared_file( $attachment_id ) {
		$relative = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( '' === $relative ) {
			return false;
		}
		$others = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => array( 'inherit', 'private', 'publish', 'trash' ),
				'meta_key'       => '_wp_attached_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a single, bounded lookup.
				'meta_value'     => $relative, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 2,
				'no_found_rows'  => true,
			)
		);
		return (bool) array_diff( array_map( 'intval', $others ), array( (int) $attachment_id ) );
	}

	/**
	 * Why a picture would be left alone, for the box on its screen. An empty string means it can be changed.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Reason code.
	 */
	public static function refusal_reason( $attachment_id ) {
		$path = get_attached_file( (int) $attachment_id );
		return $path ? self::unsafe_reason( (int) $attachment_id, $path ) : '';
	}

	/**
	 * Is this file an animated PNG? Converting it would keep only the first frame.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_animated_png( $path ) {
		if ( ! is_string( $path ) || ! is_file( $path ) ) {
			return false;
		}
		$h = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $h ) {
			return false;
		}
		$animated = false;
		if ( "\x89PNG\r\n\x1a\n" === fread( $h, 8 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			// Walk the chunks: an animation control chunk comes before the first image data. If there are too many to walk, play it safe.
			$animated = true;
			for ( $i = 0; $i < 400; $i++ ) {
				$head = fread( $h, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				if ( 8 !== strlen( (string) $head ) ) {
					$animated = false;
					break;
				}
				$len  = (int) unpack( 'N', substr( $head, 0, 4 ) )[1];
				$type = substr( $head, 4, 4 );
				if ( 'acTL' === $type ) {
					break;
				}
				if ( 'IDAT' === $type || 'IEND' === $type ) {
					$animated = false;
					break;
				}
				if ( 0 !== fseek( $h, $len + 4, SEEK_CUR ) ) {
					$animated = false;
					break;
				}
			}
		}
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $animated;
	}

	/**
	 * When a changed picture is deleted for good, its saved original goes with it.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function forget_backup( $attachment_id ) {
		$info = get_post_meta( (int) $attachment_id, self::META_ORIGINAL, true );
		if ( ! is_array( $info ) ) {
			return;
		}
		global $wpdb;
		foreach ( array( 'backup', 'backup_original' ) as $key ) {
			if ( ! empty( $info[ $key ] ) && 0 === strpos( (string) $info[ $key ], 'local:' ) ) {
				$file = Backup::resolve( substr( (string) $info[ $key ], 6 ) );
				// Another picture's note (a copy made by a translation plugin) may point at the same saved original. Then it stays.
				$like   = '%' . $wpdb->esc_like( substr( (string) $info[ $key ], 6 ) ) . '%';
				$shared = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id <> %d AND meta_value LIKE %s", self::META_ORIGINAL, (int) $attachment_id, $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( '' !== $file && 0 === $shared ) {
					wp_delete_file( $file );
					self::tidy_backup_folder( dirname( $file ) );
				}
			}
		}
	}

	/**
	 * Change the picture's type without touching its title, caption or description. wp_update_post() would run those through the
	 * content filters for people who may not post raw HTML.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $mime          New MIME type.
	 * @return void
	 */
	private static function set_mime( $attachment_id, $mime ) {
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_mime_type' => $mime ), array( 'ID' => (int) $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one narrow column; the cache is cleared on the next line.
		clean_post_cache( (int) $attachment_id );
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
		$source_meta = wp_get_attachment_metadata( $attachment_id );
		$source_meta = is_array( $source_meta ) ? $source_meta : array();

		$new = self::convert_file( $path, $mime, $target );
		if ( is_wp_error( $new ) ) {
			return $new;
		}

		// A modern format should be an improvement. If JPEG or PNG would grow, leave them as they are.
		if ( in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) && $old_size > 0 && $new['size'] >= $old_size ) {
			wp_delete_file( $new['path'] );
			return array( 'status' => 'skipped', 'reason' => 'converted file was not smaller', 'from' => $mime, 'to' => $mime, 'saved' => 0 );
		}

		// Remember every address the old picture answered to, so links to it keep working. Earlier addresses (from a change that was undone) stay.
		$old_urls = self::merge_entries( self::url_entries( $attachment_id ), get_post_meta( $attachment_id, self::META_OLD_URLS, true ) );
		$uploads  = wp_get_upload_dir();
		$rel_old  = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $path ), '/' );

		$mode   = Backup::mode();
		$stored = 'none';
		if ( 'local' === $mode ) {
			$kept = Backup::store_local( $path, $attachment_id );
			if ( false === $kept ) {
				wp_delete_file( $new['path'] );
				return new WP_Error( 'timu_formats_backup', __( 'A safe copy of your original could not be made, so nothing was changed.', 'thisismyurl-image-formats' ) );
			}
			$stored = 'local:' . $kept;
		}

		// Large images are scaled down by WordPress, and the full-size file it keeps is part of the original. Keep that too.
		$orig_name   = isset( $source_meta['original_image'] ) ? (string) $source_meta['original_image'] : '';
		$orig_path   = '' !== $orig_name ? trailingslashit( dirname( $path ) ) . $orig_name : '';
		$orig_stored = '';
		if ( 'local' === $mode && '' !== $orig_path && file_exists( $orig_path ) ) {
			$kept_orig = Backup::store_local( $orig_path, $attachment_id );
			if ( false === $kept_orig ) {
				wp_delete_file( $new['path'] );
				return new WP_Error( 'timu_formats_backup', __( 'A safe copy of the large original could not be made, so nothing was changed.', 'thisismyurl-image-formats' ) );
			}
			$orig_stored = 'local:' . $kept_orig;
		}

		// Write the way back down BEFORE anything is removed, so even an interruption part-way leaves a working "put the original back".
		$record = array(
			'file'            => $rel_old,
			'mime'            => $mime,
			'size'            => $old_size,
			'backup'          => $stored,
			'to'              => $target,
			'context'         => $context,
			'converted'       => time(),
			'original_image'  => $orig_name,
			'backup_original' => $orig_stored,
		);
		update_post_meta( $attachment_id, self::META_ORIGINAL, array_merge( $record, array( 'state' => 'pending', 'new_file' => wp_basename( $new['path'] ), 'new_size' => (int) $new['size'] ) ) );

		// Carry the camera, exposure and date details over: the converted file may not hold them in a form WordPress can read.
		$carry = isset( $source_meta['image_meta'] ) && is_array( $source_meta['image_meta'] ) ? $source_meta['image_meta'] : array();
		if ( ! empty( $new['rotated'] ) && isset( $carry['orientation'] ) ) {
			$carry['orientation'] = 1;
		}
		self::remember( $new['path'], $carry );

		// Remove the old file and its generated sizes, then point the attachment at the new file.
		$old_meta = self::meta_for_delete( $attachment_id );
		wp_delete_attachment_files( $attachment_id, $old_meta, array(), $path );

		update_attached_file( $attachment_id, $new['path'] );
		self::set_mime( $attachment_id, $target );
		$meta = wp_generate_attachment_metadata( $attachment_id, $new['path'] );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}

		$saved = $old_size - $new['size'];
		update_post_meta( $attachment_id, self::META_ORIGINAL, $record );
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
	 * Every address an attachment currently answers to, by size name: "full" and each generated size.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,string>
	 */
	public static function attachment_urls( $attachment_id ) {
		$urls = array();
		$full = wp_get_attachment_url( $attachment_id );
		if ( $full ) {
			$urls['full'] = $full;
			$meta         = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				$base = trailingslashit( dirname( $full ) );
				foreach ( $meta['sizes'] as $name => $size ) {
					if ( ! empty( $size['file'] ) ) {
						$urls[ (string) $name ] = $base . $size['file'];
					}
				}
			}
		}
		return $urls;
	}

	/**
	 * The same addresses as a list of size and address pairs, the shape kept in the picture's note.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int,array{s:string,u:string}>
	 */
	private static function url_entries( $attachment_id ) {
		$out = array();
		foreach ( self::attachment_urls( $attachment_id ) as $size => $url ) {
			$out[] = array( 's' => (string) $size, 'u' => $url );
		}
		return $out;
	}

	/**
	 * Join two lists of size and address pairs, without repeats.
	 *
	 * @param array $first  List.
	 * @param mixed $second List (anything else is ignored).
	 * @return array
	 */
	private static function merge_entries( array $first, $second ) {
		$seen = array();
		$out  = array();
		foreach ( array_merge( $first, is_array( $second ) ? $second : array() ) as $e ) {
			if ( is_array( $e ) && isset( $e['s'], $e['u'] ) && ! isset( $seen[ $e['s'] . '|' . $e['u'] ] ) ) {
				$seen[ $e['s'] . '|' . $e['u'] ] = true;
				$out[]                           = array( 's' => (string) $e['s'], 'u' => (string) $e['u'] );
			}
		}
		return $out;
	}

	/**
	 * The original file name when it is free, otherwise a unique one. WordPress adds "-1" to names ending in -scaled or -rotated even when
	 * nothing else has the name, which would break every link to the restored picture. But a name is only "free" if no other picture's
	 * files could be written over: WordPress may make a JPEG next to a restored HEIC, so any file in the folder that shares the name, in
	 * any format, and is not one of this picture's own, makes the name unsafe.
	 *
	 * @param string   $dir  Folder.
	 * @param string   $base File name.
	 * @param string[] $own  Normalised paths of this picture's own current files.
	 * @return string Absolute path.
	 */
	private static function exact_or_unique( $dir, $base, array $own ) {
		$path = trailingslashit( $dir ) . $base;
		if ( file_exists( $path ) || in_array( wp_normalize_path( $path ), $own, true ) ) {
			return self::unique_path( $dir, $base, $own );
		}
		$name  = pathinfo( $base, PATHINFO_FILENAME );
		$match = '/^' . preg_quote( $name, '/' ) . '(-scaled|-rotated)?(-\d+x\d+)?\.[a-z0-9]+$/i';
		$files = is_dir( $dir ) ? (array) scandir( $dir ) : array();
		foreach ( $files as $file ) {
			if ( 1 === preg_match( $match, $file ) && ! in_array( wp_normalize_path( trailingslashit( $dir ) . $file ), $own, true ) ) {
				return self::unique_path( $dir, $base, $own );
			}
		}
		return $path;
	}

	/**
	 * A free name that is neither on disk nor one of this picture's own files, even one that has just been removed.
	 *
	 * @param string   $dir  Folder.
	 * @param string   $base File name.
	 * @param string[] $own  Normalised paths of the picture's own files.
	 * @return string Absolute path.
	 */
	private static function unique_path( $dir, $base, array $own ) {
		$name = pathinfo( $base, PATHINFO_FILENAME );
		$ext  = pathinfo( $base, PATHINFO_EXTENSION );
		$try  = wp_unique_filename( $dir, $base );
		$i    = 1;
		while ( in_array( wp_normalize_path( trailingslashit( $dir ) . $try ), $own, true ) ) {
			if ( $i > 1000 ) {
				return ''; // A site filter keeps giving the same name. Give up rather than loop.
			}
			$try = wp_unique_filename( $dir, $name . '-' . $i . ( '' !== $ext ? '.' . $ext : '' ) );
			++$i;
		}
		return trailingslashit( $dir ) . $try;
	}

	/**
	 * This picture's own files as they are now: the main file, its sizes and the large original.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $current       Absolute path of the main file.
	 * @return string[] Normalised paths.
	 */
	private static function own_files( $attachment_id, $current ) {
		$dir  = trailingslashit( dirname( $current ) );
		$own  = array( wp_normalize_path( $current ) );
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) ) {
			foreach ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array() as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$own[] = wp_normalize_path( $dir . $size['file'] );
				}
			}
			if ( ! empty( $meta['original_image'] ) ) {
				$own[] = wp_normalize_path( $dir . $meta['original_image'] );
			}
		}
		return $own;
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
			return new WP_Error( 'timu_formats_nothing', __( 'This picture has not been changed by this tool.', 'thisismyurl-image-formats' ) );
		}
		if ( 0 !== strpos( (string) $info['backup'], 'local:' ) ) {
			return new WP_Error( 'timu_formats_no_backup', __( 'No copy of the original was kept for this picture.', 'thisismyurl-image-formats' ) );
		}
		$source = Backup::resolve( substr( $info['backup'], 6 ) );
		if ( '' === $source ) {
			return new WP_Error( 'timu_formats_backup_missing', __( 'The saved copy of the original could not be found.', 'thisismyurl-image-formats' ) );
		}
		if ( self::shared_file( $attachment_id ) ) {
			return new WP_Error( 'timu_formats_shared', __( 'Another picture in your Media Library uses the same file, so this one is left exactly as it is.', 'thisismyurl-image-formats' ) );
		}
		if ( ! self::take_lock( $attachment_id ) ) {
			return new WP_Error( 'timu_formats_locked', __( 'This picture is being changed right now. Please wait a minute and try again.', 'thisismyurl-image-formats' ) );
		}
		try {
			return self::restore_locked( $attachment_id, $info, $source );
		} finally {
			self::release_lock( $attachment_id );
		}
	}

	/**
	 * The work of restore_attachment(), run while the lock is held. Everything is copied and checked before anything is removed.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $info          The picture's note.
	 * @param string $source        Absolute path of the saved original.
	 * @return array|WP_Error
	 */
	private static function restore_locked( $attachment_id, array $info, $source ) {
		// Read again now that the lock is held: another request may have finished first.
		$fresh = get_post_meta( $attachment_id, self::META_ORIGINAL, true );
		if ( ! is_array( $fresh ) || empty( $fresh['backup'] ) ) {
			return new WP_Error( 'timu_formats_nothing', __( 'This picture has not been changed by this tool.', 'thisismyurl-image-formats' ) );
		}
		$info   = $fresh;
		$source = Backup::resolve( substr( (string) $info['backup'], 6 ) );
		$file   = get_attached_file( $attachment_id );
		if ( '' === $source || ! $file || empty( $info['file'] ) || empty( $info['mime'] ) ) {
			return new WP_Error( 'timu_formats_backup_missing', __( 'The saved copy of the original could not be found.', 'thisismyurl-image-formats' ) );
		}
		$current = $file;
		$dir     = dirname( $current );
		$base    = wp_basename( $info['file'] );
		$orphan  = ! empty( $info['new_file'] ) ? trailingslashit( $dir ) . wp_basename( (string) $info['new_file'] ) : '';

		// A change that stopped before it removed anything: the original is still in place, so only the half-made new file has to go.
		if ( isset( $info['state'] ) && 'pending' === $info['state'] && self::nothing_removed( $attachment_id, $info, $current, $base, $dir, $source ) ) {
			self::drop_orphan( $info, $orphan, $current );
			wp_delete_file( $source );
			self::tidy_backup_folder( dirname( $source ) );
			if ( ! empty( $info['backup_original'] ) ) {
				$kept = Backup::resolve( substr( (string) $info['backup_original'], 6 ) );
				if ( '' !== $kept ) {
					wp_delete_file( $kept );
					self::tidy_backup_folder( dirname( $kept ) );
				}
			}
			delete_post_meta( $attachment_id, self::META_ORIGINAL );
			return array( 'status' => 'restored', 'mime' => $info['mime'] );
		}

		$own  = self::own_files( $attachment_id, $current );
		$dest = self::exact_or_unique( $dir, $base, $own );
		if ( '' === $dest ) {
			return new WP_Error( 'timu_formats_restore_name', __( 'A free file name could not be found, so nothing was changed.', 'thisismyurl-image-formats' ) );
		}

		// Put the large original back too, when one was kept.
		$orig_src  = '';
		$orig_dest = '';
		if ( ! empty( $info['backup_original'] ) && 0 === strpos( (string) $info['backup_original'], 'local:' ) ) {
			$orig_src = Backup::resolve( substr( $info['backup_original'], 6 ) );
			if ( '' === $orig_src ) {
				return new WP_Error( 'timu_formats_backup_missing', __( 'The saved copy of the original could not be found.', 'thisismyurl-image-formats' ) );
			}
			$orig_dest = self::exact_or_unique( $dir, wp_basename( (string) $info['original_image'] ), $own );
			if ( '' === $orig_dest ) {
				return new WP_Error( 'timu_formats_restore_name', __( 'A free file name could not be found, so nothing was changed.', 'thisismyurl-image-formats' ) );
			}
		}
		$copied = array();
		foreach ( array( array( $source, $dest ), array( $orig_src, $orig_dest ) ) as $pair ) {
			list( $from, $to ) = $pair;
			if ( '' === $to || '' === $from ) {
				continue;
			}
			if ( ! copy( $from, $to ) || filesize( $from ) !== filesize( $to ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
				foreach ( $copied as $done ) {
					wp_delete_file( $done );
				}
				if ( file_exists( $to ) ) {
					wp_delete_file( $to );
				}
				return new WP_Error( 'timu_formats_restore_copy', __( 'The original could not be put back, so nothing was changed.', 'thisismyurl-image-formats' ) );
			}
			$copied[] = $to;
		}

		// Addresses the changed picture has now: links written since the change still need to work after it is undone.
		$converted_urls = self::url_entries( $attachment_id );

		// If the changed picture was cropped or turned afterwards, its edit copies go too.
		$edits = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		wp_delete_attachment_files( $attachment_id, self::meta_for_delete( $attachment_id ), is_array( $edits ) ? $edits : array(), $current );
		delete_post_meta( $attachment_id, '_wp_attachment_backup_sizes' );

		$current_meta = wp_get_attachment_metadata( $attachment_id );
		self::remember( $dest, is_array( $current_meta ) && isset( $current_meta['image_meta'] ) && is_array( $current_meta['image_meta'] ) ? $current_meta['image_meta'] : array() );

		update_attached_file( $attachment_id, $dest );
		self::set_mime( $attachment_id, $info['mime'] );
		$meta = wp_generate_attachment_metadata( $attachment_id, $dest );
		if ( is_array( $meta ) ) {
			if ( '' !== $orig_dest ) {
				$meta['original_image'] = wp_basename( $orig_dest );
			}
			wp_update_attachment_metadata( $attachment_id, $meta );
		}
		// Only now that the restored file is in place and the right size is the saved copy let go of.
		if ( ! is_file( $dest ) || filesize( $dest ) !== filesize( $source ) || ( '' !== $orig_src && ( ! is_file( $orig_dest ) || filesize( $orig_dest ) !== filesize( $orig_src ) ) ) ) {
			return new WP_Error( 'timu_formats_restore_verify', __( 'The original could not be checked after it was put back, so the saved copy was kept. Nothing was lost.', 'thisismyurl-image-formats' ) );
		}
		self::drop_orphan( $info, $orphan, $dest );
		wp_delete_file( $source );
		self::tidy_backup_folder( dirname( $source ) );
		if ( '' !== $orig_src ) {
			wp_delete_file( $orig_src );
			self::tidy_backup_folder( dirname( $orig_src ) );
		}

		delete_post_meta( $attachment_id, self::META_ORIGINAL );
		delete_post_meta( $attachment_id, self::META_SAVINGS );
		// Keep the address list: the addresses the changed picture had now point at the restored one.
		update_post_meta( $attachment_id, self::META_OLD_URLS, self::merge_entries( $converted_urls, get_post_meta( $attachment_id, self::META_OLD_URLS, true ) ) );

		do_action( 'timu_formats_restored', $attachment_id );
		return array( 'status' => 'restored', 'mime' => $info['mime'] );
	}

	/**
	 * Has an interrupted change removed nothing yet? Core deletes a picture's sizes, then its large original, then the main file, so every
	 * one of them has to still be there before "nothing was removed" is true.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $info          The picture's note.
	 * @param string $current       Absolute path of the main file.
	 * @param string $base          Original file name.
	 * @param string $dir           Folder.
	 * @param string $source        Saved original.
	 * @return bool
	 */
	private static function nothing_removed( $attachment_id, array $info, $current, $base, $dir, $source ) {
		if ( wp_basename( $current ) !== $base || ! is_file( $current ) || filesize( $current ) !== filesize( $source ) ) {
			return false;
		}
		if ( ! empty( $info['original_image'] ) ) {
			$kept = ! empty( $info['backup_original'] ) ? Backup::resolve( substr( (string) $info['backup_original'], 6 ) ) : '';
			$full = trailingslashit( $dir ) . wp_basename( (string) $info['original_image'] );
			if ( '' === $kept || ! is_file( $full ) || filesize( $full ) !== filesize( $kept ) ) {
				return false;
			}
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		foreach ( is_array( $meta ) && isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array() as $size ) {
			if ( ! empty( $size['file'] ) && ! is_file( trailingslashit( $dir ) . $size['file'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Delete the half-made new file of an interrupted change, but only if it still is that file: the right size, and not now some other
	 * picture's file.
	 *
	 * @param array  $info   The picture's note.
	 * @param string $orphan Absolute path of the half-made file.
	 * @param string $keep   A path that must never be deleted (the file the picture now uses).
	 * @return void
	 */
	private static function drop_orphan( array $info, $orphan, $keep ) {
		if ( '' === $orphan || ! is_file( $orphan ) || wp_normalize_path( $orphan ) === wp_normalize_path( $keep ) ) {
			return;
		}
		if ( empty( $info['new_size'] ) || filesize( $orphan ) !== (int) $info['new_size'] ) {
			return;
		}
		$uploads  = wp_get_upload_dir();
		$relative = ltrim( str_replace( trailingslashit( wp_normalize_path( $uploads['basedir'] ) ), '', wp_normalize_path( $orphan ) ), '/' );
		$owners   = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'meta_key'       => '_wp_attached_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a single, bounded lookup.
				'meta_value'     => $relative, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		if ( ! $owners ) {
			wp_delete_file( $orphan );
		}
	}

	/**
	 * Remove a picture's folder inside the backup folder once nothing but the guard file is left in it.
	 *
	 * @param string $folder Absolute path.
	 * @return void
	 */
	private static function tidy_backup_folder( $folder ) {
		if ( ! is_dir( $folder ) ) {
			return;
		}
		$left = array_diff( (array) scandir( $folder ), array( '.', '..', 'index.php' ) );
		if ( ! $left && 0 === strpos( wp_basename( dirname( $folder ) ), Backup::DIR . '-' ) ) {
			wp_delete_file( trailingslashit( $folder ) . 'index.php' );
			rmdir( $folder ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
	}
}
