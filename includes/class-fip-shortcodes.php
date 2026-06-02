<?php
/**
 * Shortcode placeholders for portal templates.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_Shortcodes', false ) ) {
	return;
}

/**
 * Registers Phase 1 shortcode placeholders.
 */
class FIP_Shortcodes {

	/**
	 * Shortcode to template map.
	 *
	 * @var array<string,string>
	 */
	private $shortcodes = array(
		'filter_portal_login'            => 'login.php',
		'filter_portal_dashboard'        => 'dashboard.php',
		'filter_portal_complete_profile' => 'complete-profile.php',
		'filter_portal_edit_profile'     => 'edit-profile.php',
		'filter_portal_new_request'      => 'new-request.php',
		'filter_portal_my_requests'      => 'my-requests.php',
		'filter_portal_request_detail'   => 'request-detail.php',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Registers all portal shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		foreach ( array_keys( $this->shortcodes ) as $shortcode ) {
			add_shortcode( $shortcode, array( $this, 'render_shortcode' ) );
		}
	}

	/**
	 * Renders a shortcode by loading the matching template.
	 *
	 * @param array<string,mixed> $atts      Shortcode attributes.
	 * @param string|null         $content   Enclosed shortcode content.
	 * @param string              $shortcode Current shortcode tag.
	 * @return string
	 */
	public function render_shortcode( $atts = array(), $content = null, $shortcode = '' ) {
		unset( $atts, $content );

		if ( ! isset( $this->shortcodes[ $shortcode ] ) ) {
			return '';
		}

		if ( 'filter_portal_login' === $shortcode ) {
			$this->enqueue_frontend_assets();

			$auth = fip_plugin()->get_module( 'auth' );
			if ( $auth && method_exists( $auth, 'render_login_form' ) ) {
				return $auth->render_login_form();
			}
		}

		$is_profile_shortcode = in_array( $shortcode, array( 'filter_portal_complete_profile', 'filter_portal_edit_profile' ), true );
		if ( $is_profile_shortcode ) {
			$this->enqueue_profile_assets();
		} else {
			$this->enqueue_frontend_assets();
		}

		$template = FILTER_INQUIRY_PORTAL_PLUGIN_DIR . 'templates/' . $this->shortcodes[ $shortcode ];
		if ( ! file_exists( $template ) ) {
			return '<div class="fip_template fip_template--missing" dir="rtl"><p>' . esc_html__( 'قالب این بخش پیدا نشد.', 'filter-inquiry-portal' ) . '</p></div>';
		}

		ob_start();
		include $template;
		return (string) ob_get_clean();
	}

	/**
	 * Enqueues frontend assets only when a shortcode is rendered.
	 *
	 * @return void
	 */
	private function enqueue_frontend_assets() {
		$assets = fip_plugin()->get_module( 'assets' );
		if ( $assets && method_exists( $assets, 'enqueue_frontend_assets' ) ) {
			$assets->enqueue_frontend_assets();
		}
	}

	/**
	 * Enqueues frontend assets and localized city data for profile forms.
	 *
	 * @return void
	 */
	private function enqueue_profile_assets() {
		$this->enqueue_frontend_assets();

		$profile = fip_plugin()->get_module( 'profile' );
		if ( ! $profile || ! method_exists( $profile, 'get_city_data' ) ) {
			return;
		}

		wp_add_inline_script(
			'fip_frontend',
			'window.fipProfileCities = ' . wp_json_encode( $profile->get_city_data() ) . ';',
			'before'
		);
	}
}
