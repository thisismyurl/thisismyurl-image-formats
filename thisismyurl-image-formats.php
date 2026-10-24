<?php
/**
 * Plugin Name:       Thisismyurl Image Formats
 * Plugin URI:        https://plugins.thisismyurl.com/thisismyurl-image-formats/
 * Description:       Changes pictures that websites cannot show, or that take up too much space, into pictures that work well. A safe copy of every original is kept, so you can always put it back.
 * Version:           0.6297.1
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Christopher Ross
 * Author URI:        https://thisismyurl.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       thisismyurl-image-formats
 *
 * @package ThisIsMyURL\ImageFormats
 */

namespace ThisIsMyURL\ImageFormats;

defined( 'ABSPATH' ) || exit;

define( 'TIMU_FORMATS_VERSION', '0.6297.1' );
define( 'TIMU_FORMATS_FILE', __FILE__ );
define( 'TIMU_FORMATS_DIR', plugin_dir_path( __FILE__ ) );

require_once TIMU_FORMATS_DIR . 'includes/class-capabilities.php';
require_once TIMU_FORMATS_DIR . 'includes/class-settings.php';
require_once TIMU_FORMATS_DIR . 'includes/class-policy.php';
require_once TIMU_FORMATS_DIR . 'includes/class-backup.php';
require_once TIMU_FORMATS_DIR . 'includes/class-converter.php';
require_once TIMU_FORMATS_DIR . 'includes/class-upload.php';
require_once TIMU_FORMATS_DIR . 'includes/class-actions.php';
require_once TIMU_FORMATS_DIR . 'includes/class-plugin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once TIMU_FORMATS_DIR . 'includes/class-cli.php';
}

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
