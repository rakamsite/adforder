<?php
/**
 * Main plugin bootstrap class.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_Plugin', false ) ) {
	return;
}

/**
 * Main singleton plugin class.
 */
final class FIP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var FIP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Registered module instances.
	 *
	 * @var array<string,object>
	 */
	private $modules = array();

	/**
	 * Gets the singleton instance.
	 *
	 * @return FIP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin constructor.
	 */
	private function __construct() {
		$this->load_files();
		$this->register_modules();
	}

	/**
	 * Prevent cloning the singleton.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing the singleton.
	 *
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong( __METHOD__, esc_html__( 'Unserializing singleton instances is not allowed.', 'filter-inquiry-portal' ), '0.1.0' );
	}

	/**
	 * Loads module class files.
	 *
	 * @return void
	 */
	private function load_files() {
		$files = array(
			'class-fip-assets.php',
			'class-fip-shortcodes.php',
			'class-fip-auth.php',
			'class-fip-otp.php',
			'class-fip-profile.php',
			'class-fip-requests.php',
			'class-fip-product-search.php',
			'class-fip-admin.php',
			'class-fip-settings.php',
			'class-fip-sms-logger.php',
			'class-fip-smsir-provider.php',
		);

		foreach ( $files as $file ) {
			require_once FILTER_INQUIRY_PORTAL_PLUGIN_DIR . 'includes/' . $file;
		}
	}

	/**
	 * Instantiates plugin modules.
	 *
	 * @return void
	 */
	private function register_modules() {
		$this->modules = array(
			'assets'               => new FIP_Assets(),
			'shortcodes'           => new FIP_Shortcodes(),
			'auth'                 => new FIP_Auth(),
			'otp'                  => new FIP_OTP(),
			'profile'              => new FIP_Profile(),
			'requests'             => new FIP_Requests(),
			'product_search'       => new FIP_Product_Search(),
			'admin'                => new FIP_Admin(),
			'settings'             => new FIP_Settings(),
			'sms_logger'           => new FIP_SMS_Logger(),
			'smsir_provider'       => new FIP_SMSIR_Provider(),
		);
	}

	/**
	 * Retrieves a registered module instance.
	 *
	 * @param string $key Module key.
	 * @return object|null
	 */
	public function get_module( $key ) {
		return isset( $this->modules[ $key ] ) ? $this->modules[ $key ] : null;
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		update_option( 'fip_version', FILTER_INQUIRY_PORTAL_VERSION, false );

		// Phase 1 does not register rewrite rules, so no rewrite flush is needed.
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Keep all user data and settings intact for future phases.
	}
}
