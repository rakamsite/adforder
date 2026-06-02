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
 * Handles real sms.ir Verify API requests.
 */
class FIP_SMSIR_Provider {

	const BASE_URL        = 'https://api.sms.ir';
	const VERIFY_ENDPOINT = '/v1/send/verify';

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
		if ( empty( $settings ) ) {
			$settings = get_option( 'fip_settings', array() );
		}

		$this->settings = is_array( $settings ) ? $settings : array();
	}

	/**
	 * Checks whether the provider has the minimum settings needed for Verify sending.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->settings['smsir_api_key'] );
	}

	/**
	 * Sends a Verify/template SMS through sms.ir.
	 *
	 * @param string              $mobile      Recipient mobile number.
	 * @param int                 $template_id sms.ir template ID.
	 * @param array<string,mixed> $parameters  Verify template parameters.
	 * @return array<string,mixed>
	 */
	public function send_verify( $mobile, $template_id, array $parameters ) {
		$api_key     = isset( $this->settings['smsir_api_key'] ) ? trim( (string) $this->settings['smsir_api_key'] ) : '';
		$template_id = absint( $template_id );
		$mobile      = $this->normalize_mobile_for_provider( $mobile );

		if ( '' === $api_key ) {
			return $this->build_response( false, __( 'کلید API پیامک تنظیم نشده است.', 'filter-inquiry-portal' ), 0, null );
		}

		if ( $template_id <= 0 ) {
			return $this->build_response( false, __( 'شناسه قالب پیامک معتبر نیست.', 'filter-inquiry-portal' ), 0, null );
		}

		if ( ! $this->is_valid_provider_mobile( $mobile ) ) {
			return $this->build_response( false, __( 'شماره موبایل پیامک معتبر نیست.', 'filter-inquiry-portal' ), 0, null );
		}

		$body = array(
			'mobile'     => $mobile,
			'templateId' => $template_id,
			'parameters' => $this->format_verify_parameters( $parameters ),
		);

		$response = wp_remote_post(
			self::BASE_URL . self::VERIFY_ENDPOINT,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json, text/plain',
					'x-api-key'    => $api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->build_response( false, $response->get_error_message(), 0, null );
		}

		$status_code = absint( wp_remote_retrieve_response_code( $response ) );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$parsed_body = $this->parse_response_body( $raw_body );
		$message     = $this->extract_response_message( $parsed_body, $status_code );
		$success     = $this->is_successful_response( $status_code, $parsed_body );

		return $this->build_response( $success, $message, $status_code, $parsed_body );
	}

	/**
	 * Placeholder for future non-Verify/bulk sending.
	 *
	 * @param string $mobile  Recipient mobile number.
	 * @param string $message Message body.
	 * @return array<string,mixed>
	 */
	public function send_bulk( $mobile, $message ) {
		return $this->build_response( false, __( 'ارسال عادی پیامک هنوز پیاده‌سازی نشده است.', 'filter-inquiry-portal' ), 0, null );
	}

	/**
	 * Normalizes a mobile number for sms.ir Verify requests (9123456789).
	 *
	 * @param string $mobile Mobile number.
	 * @return string
	 */
	public function normalize_mobile_for_provider( $mobile ) {
		$mobile = trim( $this->convert_persian_digits( (string) $mobile ) );
		$mobile = preg_replace( '/[^0-9+]/', '', $mobile );

		if ( 0 === strpos( $mobile, '+98' ) ) {
			$mobile = substr( $mobile, 3 );
		} elseif ( 0 === strpos( $mobile, '0098' ) ) {
			$mobile = substr( $mobile, 4 );
		} elseif ( 0 === strpos( $mobile, '98' ) ) {
			$mobile = substr( $mobile, 2 );
		} elseif ( 0 === strpos( $mobile, '0' ) ) {
			$mobile = substr( $mobile, 1 );
		}

		return preg_replace( '/\D+/', '', $mobile );
	}

	/**
	 * Validates provider mobile format.
	 *
	 * @param string $mobile Provider-normalized mobile.
	 * @return bool
	 */
	private function is_valid_provider_mobile( $mobile ) {
		return 1 === preg_match( '/^9\d{9}$/', (string) $mobile );
	}

	/**
	 * Formats associative parameters for sms.ir Verify.
	 *
	 * @param array<string,mixed> $parameters Parameters.
	 * @return array<int,array{name:string,value:string}>
	 */
	private function format_verify_parameters( array $parameters ) {
		$formatted = array();

		foreach ( $parameters as $name => $value ) {
			if ( is_array( $value ) && isset( $value['name'], $value['value'] ) ) {
				$formatted[] = array(
					'name'  => sanitize_text_field( (string) $value['name'] ),
					'value' => sanitize_text_field( (string) $value['value'] ),
				);
				continue;
			}

			$formatted[] = array(
				'name'  => sanitize_text_field( (string) $name ),
				'value' => sanitize_text_field( (string) $value ),
			);
		}

		return $formatted;
	}

	/**
	 * Parses JSON response body safely and falls back to raw text.
	 *
	 * @param string $raw_body Raw response body.
	 * @return mixed
	 */
	private function parse_response_body( $raw_body ) {
		if ( '' === trim( $raw_body ) ) {
			return '';
		}

		$decoded = json_decode( $raw_body, true );

		return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $raw_body;
	}

	/**
	 * Extracts a usable message from the provider response.
	 *
	 * @param mixed $response    Parsed or raw response.
	 * @param int   $status_code HTTP status code.
	 * @return string
	 */
	private function extract_response_message( $response, $status_code ) {
		if ( is_array( $response ) ) {
			foreach ( array( 'message', 'Message', 'error', 'Error' ) as $key ) {
				if ( isset( $response[ $key ] ) && is_scalar( $response[ $key ] ) ) {
					return sanitize_text_field( (string) $response[ $key ] );
				}
			}
		}

		if ( is_string( $response ) && '' !== trim( $response ) ) {
			return sanitize_text_field( $response );
		}

		return $status_code >= 200 && $status_code < 300
			? __( 'پیامک با موفقیت ارسال شد.', 'filter-inquiry-portal' )
			: __( 'ارسال پیامک با خطا مواجه شد.', 'filter-inquiry-portal' );
	}

	/**
	 * Determines whether sms.ir response should be considered successful.
	 *
	 * @param int   $status_code HTTP status code.
	 * @param mixed $response    Parsed or raw response.
	 * @return bool
	 */
	private function is_successful_response( $status_code, $response ) {
		if ( $status_code < 200 || $status_code >= 300 ) {
			return false;
		}

		if ( is_array( $response ) && array_key_exists( 'status', $response ) ) {
			$status = $response['status'];

			if ( is_bool( $status ) ) {
				return $status;
			}

			if ( is_numeric( $status ) ) {
				return (int) $status > 0;
			}
		}

		return true;
	}

	/**
	 * Builds a standard provider response.
	 *
	 * @param bool   $success     Success flag.
	 * @param string $message     Response message.
	 * @param int    $status_code HTTP status code.
	 * @param mixed  $response    Parsed or raw response.
	 * @return array<string,mixed>
	 */
	private function build_response( $success, $message, $status_code, $response ) {
		return array(
			'success'     => (bool) $success,
			'message'     => sanitize_text_field( (string) $message ),
			'status_code' => absint( $status_code ),
			'response'    => $response,
		);
	}

	/**
	 * Converts Persian/Arabic numerals to English digits.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function convert_persian_digits( $value ) {
		return str_replace(
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ),
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			$value
		);
	}
}
