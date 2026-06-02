<?php
/**
 * sms.ir SMS provider integration module.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_SMSIR_Provider', false ) ) {
	return;
}

/**
 * Placeholder for sms.ir SMS provider integration.
 */
class FIP_SMSIR_Provider {

	/**
	 * Provider settings.
	 *
	 * @var array<string,mixed>
	 */
	private $settings = array();

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Provider settings.
	 */
	public function __construct( $settings = array() ) {
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	/**
	 * Checks whether the provider has the minimum settings needed for future sending.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->settings['smsir_api_key'] );
	}

	/**
	 * Placeholder for future sms.ir Verify/template sending.
	 *
	 * @param string              $mobile      Recipient mobile number.
	 * @param int                 $template_id sms.ir template ID.
	 * @param array<string,mixed> $parameters  Verify template parameters.
	 * @return array<string,mixed>
	 */
	public function send_verify( $mobile, $template_id, array $parameters ) {
		return $this->get_not_implemented_response();
	}

	/**
	 * Placeholder for future non-Verify/bulk sending.
	 *
	 * @param string $mobile  Recipient mobile number.
	 * @param string $message Message body.
	 * @return array<string,mixed>
	 */
	public function send_bulk( $mobile, $message ) {
		return $this->get_not_implemented_response();
	}

	/**
	 * Normalizes a mobile number for future sms.ir requests.
	 *
	 * @param string $mobile Mobile number.
	 * @return string
	 */
	public function normalize_mobile_for_provider( $mobile ) {
		return preg_replace( '/[^0-9+]/', '', (string) $mobile );
	}

	/**
	 * Returns the standard Phase 1.5 placeholder response.
	 *
	 * @return array<string,mixed>
	 */
	private function get_not_implemented_response() {
		return array(
			'success' => false,
			'message' => 'sms.ir provider is not implemented yet.',
			'data'    => null,
		);
	}
}
