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

if ( defined( 'FILTER_INQUIRY_PORTAL_PLUGIN_FILE' ) || class_exists( 'FIP_Plugin', false ) || function_exists( 'fip_plugin' ) ) {
	return;
}

define( 'FILTER_INQUIRY_PORTAL_VERSION', '0.1.0' );
define( 'FILTER_INQUIRY_PORTAL_PLUGIN_FILE', __FILE__ );
define( 'FILTER_INQUIRY_PORTAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FILTER_INQUIRY_PORTAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FILTER_INQUIRY_PORTAL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'FIP_VERSION' ) ) {
	define( 'FIP_VERSION', FILTER_INQUIRY_PORTAL_VERSION );
}

if ( ! defined( 'FIP_PLUGIN_FILE' ) ) {
	define( 'FIP_PLUGIN_FILE', FILTER_INQUIRY_PORTAL_PLUGIN_FILE );
}

if ( ! defined( 'FIP_PLUGIN_DIR' ) ) {
	define( 'FIP_PLUGIN_DIR', FILTER_INQUIRY_PORTAL_PLUGIN_DIR );
}

if ( ! defined( 'FIP_PLUGIN_URL' ) ) {
	define( 'FIP_PLUGIN_URL', FILTER_INQUIRY_PORTAL_PLUGIN_URL );
}

if ( ! defined( 'FIP_PLUGIN_BASENAME' ) ) {
	define( 'FIP_PLUGIN_BASENAME', FILTER_INQUIRY_PORTAL_PLUGIN_BASENAME );
}

if ( ! class_exists( 'FIP_Plugin', false ) ) {
	require_once FILTER_INQUIRY_PORTAL_PLUGIN_DIR . 'includes/class-fip-plugin.php';
}

register_activation_hook( FILTER_INQUIRY_PORTAL_PLUGIN_FILE, array( 'FIP_Plugin', 'activate' ) );
register_deactivation_hook( FILTER_INQUIRY_PORTAL_PLUGIN_FILE, array( 'FIP_Plugin', 'deactivate' ) );

if ( ! function_exists( 'fip_plugin' ) ) {
	/**
	 * Returns the main plugin instance.
	 *
	 * @return FIP_Plugin
	 */
	function fip_plugin() {
		return FIP_Plugin::instance();
	}
}

add_action( 'plugins_loaded', 'fip_plugin' );
