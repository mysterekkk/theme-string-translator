<?php
/**
 * Plugin Name:       Theme String Translator
 * Plugin URI:        https://github.com/mysterekkk/theme-string-translator
 * Description:       Lightweight theme string translation without WPML. Scan your theme, translate strings, export to PO/MO/JSON. No bloat.
 * Version:           1.0.0
 * Author:            LuroWeb - Łukasz Rosikoń
 * Author URI:        https://luroweb.pl
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       theme-string-translator
 * Domain Path:       /languages
 * Requires WP:       5.6
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TST_VERSION', '1.0.0' );
define( 'TST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'TST_PLUGIN_FILE', __FILE__ );

// Require all classes.
require_once TST_PLUGIN_DIR . 'includes/class-activator.php';
require_once TST_PLUGIN_DIR . 'includes/class-string-manager.php';
require_once TST_PLUGIN_DIR . 'includes/class-scanner.php';
require_once TST_PLUGIN_DIR . 'includes/class-translator.php';
require_once TST_PLUGIN_DIR . 'includes/class-exporter.php';
require_once TST_PLUGIN_DIR . 'includes/class-importer.php';
require_once TST_PLUGIN_DIR . 'includes/class-runtime.php';
require_once TST_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once TST_PLUGIN_DIR . 'includes/class-updater.php';
require_once TST_PLUGIN_DIR . 'includes/class-plugin.php';
require_once TST_PLUGIN_DIR . 'admin/class-admin.php';

// Activation.
register_activation_hook( __FILE__, array( 'TST_Activator', 'activate' ) );

// Initialize.
add_action( 'plugins_loaded', function () {
	TST_Plugin::instance();
} );
