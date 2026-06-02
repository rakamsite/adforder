<?php
/**
 * OTP generation and verification module.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_OTP', false ) ) {
	return;
}

/**
 * Handles one-time password creation, storage, verification and mock delivery.
 */
class FIP_OTP {

	const OTP_TTL          = 180;
	const RESEND_TTL       = 60;
	const RATE_TTL         = 600;
	const MAX_ATTEMPTS     = 5;
	const MAX_MOBILE_SENDS = 3;
	const MAX_IP_SENDS     = 10;

	/**
	 * Generates a 5-digit numeric OTP code.
	 *
	 * @return string
	 */
	public function generate_code() {
		return (string) wp_rand( 10000, 99999 );
	}

	/**
	 * Creates and stores a hashed OTP for a mobile number.
	 *
	 * @param string $mobile     Normalized mobile number.
	 * @param string $ip_address Request IP address.
	 * @return string|WP_Error Raw code for the mock sender, or error.
	 */
	public function create_otp( $mobile, $ip_address ) {
		$mobile     = sanitize_text_field( $mobile );
		$ip_address = sanitize_text_field( $ip_address );
		$allowed    = $this->can_send_otp( $mobile, $ip_address );

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$code = $this->generate_code();
		$data = array(
			'code_hash'  => $this->hash_code( $code ),
			'attempts'   => 0,
			'created_at' => time(),
		);

		set_transient( $this->get_otp_transient_key( $mobile ), $data, self::OTP_TTL );
		$this->mark_otp_sent( $mobile, $ip_address );

		return $code;
	}

	/**
	 * Verifies an OTP code and consumes it on success.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @param string $code   OTP code.
	 * @return true|WP_Error
	 */
	public function verify_otp( $mobile, $code ) {
		$mobile = sanitize_text_field( $mobile );
		$code   = preg_replace( '/\D+/', '', (string) $code );
		$key    = $this->get_otp_transient_key( $mobile );
		$data   = get_transient( $key );

		if ( ! is_array( $data ) || empty( $data['code_hash'] ) ) {
			return new WP_Error( 'fip_otp_missing', __( 'کد واردشده معتبر نیست یا منقضی شده است.', 'filter-inquiry-portal' ) );
		}

		if ( ! hash_equals( (string) $data['code_hash'], $this->hash_code( $code ) ) ) {
			$attempts = isset( $data['attempts'] ) ? absint( $data['attempts'] ) + 1 : 1;

			if ( $attempts >= self::MAX_ATTEMPTS ) {
				$this->clear_otp( $mobile );

				return new WP_Error( 'fip_otp_attempts_exceeded', __( 'تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً دوباره کد دریافت کنید.', 'filter-inquiry-portal' ) );
			}

			$data['attempts'] = $attempts;
			$remaining       = self::OTP_TTL;
			if ( ! empty( $data['created_at'] ) ) {
				$remaining = max( 1, self::OTP_TTL - ( time() - absint( $data['created_at'] ) ) );
			}
			set_transient( $key, $data, $remaining );

			return new WP_Error( 'fip_otp_invalid', __( 'کد واردشده صحیح نیست.', 'filter-inquiry-portal' ) );
		}

		$this->clear_otp( $mobile );

		return true;
	}

	/**
	 * Checks resend and rate limits for mobile/IP.
	 *
	 * @param string $mobile     Normalized mobile number.
	 * @param string $ip_address Request IP address.
	 * @return true|WP_Error
	 */
	public function can_send_otp( $mobile, $ip_address ) {
		$wait = $this->get_resend_wait_seconds( $mobile );
		if ( $wait > 0 ) {
			return new WP_Error(
				'fip_otp_resend_wait',
				sprintf( __( 'برای دریافت مجدد کد، لطفاً %d ثانیه صبر کنید.', 'filter-inquiry-portal' ), $wait )
			);
		}

		$mobile_rate = $this->get_rate_data( $this->get_mobile_rate_transient_key( $mobile ) );
		if ( $mobile_rate['count'] >= self::MAX_MOBILE_SENDS ) {
			return new WP_Error( 'fip_otp_mobile_rate', __( 'تعداد درخواست کد برای این شماره بیش از حد مجاز است. لطفاً چند دقیقه دیگر تلاش کنید.', 'filter-inquiry-portal' ) );
		}

		$ip_rate = $this->get_rate_data( $this->get_ip_rate_transient_key( $ip_address ) );
		if ( $ip_rate['count'] >= self::MAX_IP_SENDS ) {
			return new WP_Error( 'fip_otp_ip_rate', __( 'تعداد درخواست‌ها بیش از حد مجاز است. لطفاً چند دقیقه دیگر تلاش کنید.', 'filter-inquiry-portal' ) );
		}

		return true;
	}

	/**
	 * Marks one OTP send for rate limiting.
	 *
	 * @param string $mobile     Normalized mobile number.
	 * @param string $ip_address Request IP address.
	 * @return void
	 */
	public function mark_otp_sent( $mobile, $ip_address ) {
		$this->increment_rate( $this->get_mobile_rate_transient_key( $mobile ) );
		$this->increment_rate( $this->get_ip_rate_transient_key( $ip_address ) );
		set_transient( $this->get_resend_transient_key( $mobile ), time(), self::RESEND_TTL );
	}

	/**
	 * Returns seconds until a new code can be requested.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @return int
	 */
	public function get_resend_wait_seconds( $mobile ) {
		$sent_at = absint( get_transient( $this->get_resend_transient_key( $mobile ) ) );

		if ( ! $sent_at ) {
			return 0;
		}

		return max( 0, self::RESEND_TTL - ( time() - $sent_at ) );
	}

	/**
	 * Clears a stored OTP.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @return void
	 */
	public function clear_otp( $mobile ) {
		delete_transient( $this->get_otp_transient_key( $mobile ) );
	}

	/**
	 * Hashes an OTP code for storage/comparison.
	 *
	 * @param string $code OTP code.
	 * @return string
	 */
	public function hash_code( $code ) {
		$code = preg_replace( '/\D+/', '', (string) $code );

		return hash_hmac( 'sha256', $code, wp_salt( 'auth' ) );
	}

	/**
	 * Stores the mock OTP for development without sending real SMS.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @param string $code   OTP code.
	 * @return true
	 */
	public function send_otp_mock( $mobile, $code ) {
		set_transient( 'fip_last_mock_otp_' . $this->hash_identifier( $mobile ), sanitize_text_field( $code ), self::OTP_TTL );

		return true;
	}

	/**
	 * Gets last mock OTP for development display.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @return string
	 */
	public function get_last_mock_otp( $mobile ) {
		return (string) get_transient( 'fip_last_mock_otp_' . $this->hash_identifier( $mobile ) );
	}

	/**
	 * Gets hashed OTP transient key.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @return string
	 */
	private function get_otp_transient_key( $mobile ) {
		return 'fip_otp_' . $this->hash_identifier( $mobile );
	}

	/**
	 * Gets hashed mobile rate transient key.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @return string
	 */
	private function get_mobile_rate_transient_key( $mobile ) {
		return 'fip_otp_mobile_rate_' . $this->hash_identifier( $mobile );
	}

	/**
	 * Gets hashed IP rate transient key.
	 *
	 * @param string $ip_address Request IP address.
	 * @return string
	 */
	private function get_ip_rate_transient_key( $ip_address ) {
		return 'fip_otp_ip_rate_' . $this->hash_identifier( $ip_address );
	}

	/**
	 * Gets hashed resend transient key.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @return string
	 */
	private function get_resend_transient_key( $mobile ) {
		return 'fip_otp_resend_' . $this->hash_identifier( $mobile );
	}

	/**
	 * Hashes mobile/IP identifiers for transient names.
	 *
	 * @param string $identifier Identifier.
	 * @return string
	 */
	private function hash_identifier( $identifier ) {
		return substr( hash_hmac( 'sha256', (string) $identifier, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Reads rate data from a transient.
	 *
	 * @param string $key Transient key.
	 * @return array{count:int,started_at:int}
	 */
	private function get_rate_data( $key ) {
		$data = get_transient( $key );

		if ( ! is_array( $data ) ) {
			return array(
				'count'      => 0,
				'started_at' => time(),
			);
		}

		return array(
			'count'      => isset( $data['count'] ) ? absint( $data['count'] ) : 0,
			'started_at' => isset( $data['started_at'] ) ? absint( $data['started_at'] ) : time(),
		);
	}

	/**
	 * Increments a rate-limit transient.
	 *
	 * @param string $key Transient key.
	 * @return void
	 */
	private function increment_rate( $key ) {
		$data          = $this->get_rate_data( $key );
		$data['count'] = $data['count'] + 1;
		$remaining     = max( 1, self::RATE_TTL - ( time() - $data['started_at'] ) );

		set_transient( $key, $data, $remaining );
	}
}
