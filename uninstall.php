<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Only this plugin's own settings are removed. Saved copies of original pictures, and the small note kept with each changed picture,
 * are left alone on purpose: they are the way back to an original, and deleting someone's pictures is not this plugin's decision.
 *
 * @package ThisIsMyURL\ImageFormats
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'timu_formats_options' );
// The folder-name token (option timu_formats_token) stays too, so saved originals can still be found if the plugin is installed again.
