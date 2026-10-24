<?php
/**
 * WP-CLI commands.
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Convert and restore images from the command line.
 */
class CLI {

	/**
	 * Show what this server can do and how the plugin is set up.
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-formats status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		WP_CLI::log( 'Image library: ' . Capabilities::library() );
		foreach ( Capabilities::targets() as $mime => $ok ) {
			WP_CLI::log( sprintf( '  can write %-11s %s', $mime, $ok ? 'yes' : 'no' ) );
		}
		foreach ( array( 'image/bmp', 'image/tiff', 'image/heic' ) as $mime ) {
			WP_CLI::log( sprintf( '  can read  %-11s %s', $mime, Capabilities::can_read( $mime ) ? 'yes' : 'no' ) );
		}
		WP_CLI::log( 'Originals are kept in: ' . Backup::mode() );
		$s = Settings::get();
		WP_CLI::log( sprintf( 'Settings: nonweb=%d jpeg_png=%d graphic=%s photo=%s web=%s quality=%d', $s['convert_nonweb'], $s['convert_jpeg_png'], $s['graphic_format'], $s['photo_format'], $s['web_format'], $s['quality'] ) );
	}

	/**
	 * Convert one image, a list of images or every image the policy would convert.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Attachment IDs. Leave empty with --all.
	 *
	 * [--all]
	 * : Every image in the Media Library whose format the policy would convert.
	 *
	 * [--to=<mime>]
	 * : Force a target MIME type, for example image/webp.
	 *
	 * [--dry-run]
	 * : Say what would happen without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp image-formats convert 123
	 *     wp image-formats convert --all --dry-run
	 *
	 * @param array $args       Attachment IDs.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function convert( $args, $assoc_args ) {
		$ids = array_map( 'intval', $args );
		if ( ! empty( $assoc_args['all'] ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
				)
			);
		}
		if ( ! $ids ) {
			WP_CLI::error( 'Give attachment IDs or --all.' );
		}
		$to      = isset( $assoc_args['to'] ) ? (string) $assoc_args['to'] : '';
		$dry     = ! empty( $assoc_args['dry-run'] );
		$counts  = array( 'converted' => 0, 'would-convert' => 0, 'skipped' => 0, 'failed' => 0 );
		$saved   = 0;
		foreach ( $ids as $id ) {
			$r = Converter::convert_attachment( $id, ! empty( $assoc_args['all'] ) ? 'bulk' : 'manual', $to, $dry );
			if ( is_wp_error( $r ) ) {
				++$counts['failed'];
				WP_CLI::warning( sprintf( '#%d: %s', $id, $r->get_error_message() ) );
				continue;
			}
			++$counts[ $r['status'] ];
			$saved += (int) $r['saved'];
			if ( 'skipped' !== $r['status'] || empty( $assoc_args['all'] ) ) {
				WP_CLI::log( sprintf( '#%d: %s %s -> %s%s', $id, $r['status'], $r['from'], $r['to'], isset( $r['reason'] ) ? ' (' . $r['reason'] . ')' : '' ) );
			}
		}
		WP_CLI::success( sprintf( '%d converted, %d would convert, %d skipped, %d failed. %s saved.', $counts['converted'], $counts['would-convert'], $counts['skipped'], $counts['failed'], size_format( max( 0, $saved ) ) ?: '0 B' ) );
	}

	/**
	 * Put an image back the way it was, from the local backup.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Attachment IDs.
	 *
	 * @param array $args       Attachment IDs.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function restore( $args, $assoc_args ) {
		foreach ( array_map( 'intval', $args ) as $id ) {
			$r = Converter::restore_attachment( $id );
			if ( is_wp_error( $r ) ) {
				WP_CLI::warning( sprintf( '#%d: %s', $id, $r->get_error_message() ) );
			} else {
				WP_CLI::log( sprintf( '#%d: restored to %s', $id, $r['mime'] ) );
			}
		}
	}
}

WP_CLI::add_command( 'image-formats', CLI::class );
