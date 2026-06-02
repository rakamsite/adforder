<?php
/**
 * Asset registration module.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_Assets', false ) ) {
	return;
}

/**
 * Handles plugin asset registration.
 */
class FIP_Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_assets' ) );
	}

	/**
	 * Registers frontend and admin assets without globally enqueueing them.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'fip_frontend',
			FILTER_INQUIRY_PORTAL_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			FILTER_INQUIRY_PORTAL_VERSION
		);

		wp_register_script(
			'fip_frontend',
			FILTER_INQUIRY_PORTAL_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			FILTER_INQUIRY_PORTAL_VERSION,
			true
		);

		wp_register_style(
			'fip_admin',
			FILTER_INQUIRY_PORTAL_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			FILTER_INQUIRY_PORTAL_VERSION
		);

		wp_register_script(
			'fip_admin',
			FILTER_INQUIRY_PORTAL_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			FILTER_INQUIRY_PORTAL_VERSION,
			true
		);
	}

	/**
	 * Enqueues frontend assets when a future Phase 1+ feature explicitly needs them.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style( 'fip_frontend' );
		wp_enqueue_script( 'fip_frontend' );
	}

	/**
	 * Enqueues admin assets when future plugin admin pages explicitly need them.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style( 'fip_admin' );
		wp_enqueue_script( 'fip_admin' );
	}
}
