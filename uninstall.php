<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Only this plugin's own settings and housekeeping are removed. Saved copies of original pictures, and the small note kept with each
 * changed picture, are left alone on purpose: they are the way back to an original, and deleting someone's pictures is not this
 * plugin's decision. Each note records where its saved original is, so nothing depends on the settings removed here.
 *
 * @package ThisIsMyURL\ImageFormats
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove this plugin's options from the current site.
 *
 * @return void
 */
function timu_formats_uninstall_site() {
	global $wpdb;
	delete_option( 'timu_formats_options' );
	delete_option( 'timu_formats_token' );
	delete_option( 'timu_formats_links' );
	// Housekeeping left by a change that was interrupted, and one-time messages.
	foreach ( array( 'timu_formats_lock_', '_transient_timu_formats_notice_', '_transient_timeout_timu_formats_notice_' ) as $prefix ) {
		$like = $wpdb->esc_like( $prefix ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $timu_formats_site_id ) {
		switch_to_blog( $timu_formats_site_id );
		timu_formats_uninstall_site();
		restore_current_blog();
	}
} else {
	timu_formats_uninstall_site();
}
