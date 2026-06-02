<?php
/**
 * Plugin Name: Filter Inquiry Portal
 * Plugin URI: https://example.com/filter-inquiry-portal
 * Description: Foundation for a Persian/RTL filter inquiry portal plugin. Phase 0 provides only the modular plugin bootstrap.
 * Version: 0.1.0
 * Author: Filter Inquiry Portal Team
 * Author URI: https://example.com
 * Text Domain: filter-inquiry-portal
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FIP_VERSION', '0.1.0' );
define( 'FIP_PLUGIN_FILE', __FILE__ );
define( 'FIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FIP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once FIP_PLUGIN_DIR . 'includes/class-fip-plugin.php';

register_activation_hook( FIP_PLUGIN_FILE, array( 'FIP_Plugin', 'activate' ) );
register_deactivation_hook( FIP_PLUGIN_FILE, array( 'FIP_Plugin', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return FIP_Plugin
 */
function fip_plugin() {
	return FIP_Plugin::instance();
}

add_action( 'plugins_loaded', 'fip_plugin' );
