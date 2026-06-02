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
 * Handles real sms.ir API requests.
 */
class FIP_SMSIR_Provider {

	const BASE_URL        = 'https://api.sms.ir';
	const VERIFY_ENDPOINT = '/v1/send/verify';
	const CREDIT_ENDPOINT = '/v1/credit';
	const MESSAGE_ENDPOINT = '/v1/send/%d';
	const PARAMETER_MAX_LENGTH = 25;

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
	 * Checks whether the provider has the minimum settings needed for sms.ir calls.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->get_api_key();
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
		$api_key     = $this->get_api_key();
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

		$formatted_parameters = $this->format_verify_parameters( $parameters );
		if ( is_wp_error( $formatted_parameters ) ) {
			return $this->build_response( false, $formatted_parameters->get_error_message(), 0, null );
		}

		$body = array(
			'mobile'     => $mobile,
			'templateId' => $template_id,
			'parameters' => $formatted_parameters,
		);

		return $this->request( 'POST', self::VERIFY_ENDPOINT, $body );
	}

	/**
	 * Gets the sms.ir report/delivery status for a message ID.
	 *
	 * @param int $message_id sms.ir message ID returned by send/verify.
	 * @return array<string,mixed>
	 */
	public function get_message_status( $message_id ) {
		$message_id = absint( $message_id );

		if ( '' === $this->get_api_key() ) {
			return $this->build_response( false, __( 'کلید API پیامک تنظیم نشده است.', 'filter-inquiry-portal' ), 0, null );
		}

		if ( $message_id <= 0 ) {
			return $this->build_response( false, __( 'شناسه پیامک معتبر نیست.', 'filter-inquiry-portal' ), 0, null );
		}

		return $this->request( 'GET', sprintf( self::MESSAGE_ENDPOINT, $message_id ) );
	}

	/**
	 * Gets current sms.ir account credit.
	 *
	 * @return array<string,mixed>
	 */
	public function get_credit() {
		if ( '' === $this->get_api_key() ) {
			return $this->build_response( false, __( 'کلید API پیامک تنظیم نشده است.', 'filter-inquiry-portal' ), 0, null );
		}

		return $this->request( 'GET', self::CREDIT_ENDPOINT );
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
	 * Executes an authenticated sms.ir API request.
	 *
	 * @param string     $method   HTTP method.
	 * @param string     $endpoint Endpoint path.
	 * @param array|null $body     Optional JSON body.
	 * @return array<string,mixed>
	 */
	private function request( $method, $endpoint, $body = null ) {
		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'X-API-KEY'    => $this->get_api_key(),
			),
			'method'  => strtoupper( $method ),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::BASE_URL . $endpoint, $args );

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
	 * Reads the configured sms.ir API key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		return isset( $this->settings['smsir_api_key'] ) ? trim( (string) $this->settings['smsir_api_key'] ) : '';
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
	 * @return array<int,array{name:string,value:string}>|WP_Error
	 */
	private function format_verify_parameters( array $parameters ) {
		$formatted = array();

		foreach ( $parameters as $name => $value ) {
			if ( is_array( $value ) && isset( $value['name'], $value['value'] ) ) {
				$name  = $value['name'];
				$value = $value['value'];
			}

			$name  = $this->normalize_parameter_name( $name );
			$value = sanitize_text_field( $this->convert_persian_digits( (string) $value ) );

			if ( '' === $name || '' === $value ) {
				return new WP_Error( 'fip_smsir_empty_parameter', __( 'نام و مقدار پارامترهای قالب پیامک الزامی است.', 'filter-inquiry-portal' ) );
			}

			if ( function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) > self::PARAMETER_MAX_LENGTH : strlen( $value ) > self::PARAMETER_MAX_LENGTH ) {
				return new WP_Error( 'fip_smsir_long_parameter', __( 'مقدار هر پارامتر قالب پیامک sms.ir حداکثر می‌تواند ۲۵ کاراکتر باشد.', 'filter-inquiry-portal' ) );
			}

			$formatted[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}

		if ( empty( $formatted ) ) {
			return new WP_Error( 'fip_smsir_missing_parameters', __( 'برای ارسال Verify حداقل یک پارامتر قالب لازم است.', 'filter-inquiry-portal' ) );
		}

		return $formatted;
	}

	/**
	 * Normalizes sms.ir template parameter names without surrounding # signs.
	 *
	 * @param mixed $name Parameter name.
	 * @return string
	 */
	private function normalize_parameter_name( $name ) {
		$name = trim( sanitize_text_field( (string) $name ) );
		$name = trim( $name, " \t\n\r\0\x0B#" );

		return $name;
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

		return 200 === absint( $status_code )
			? __( 'پیامک با موفقیت ارسال شد.', 'filter-inquiry-portal' )
			: __( 'ارسال پیامک با خطا مواجه شد.', 'filter-inquiry-portal' );
	}

	/**
	 * Determines whether sms.ir response should be considered successful.
	 *
	 * sms.ir documents a unified response model where HTTP 200 plus body status=1
	 * is the only successful API result. Other positive status values such as 10,
	 * 16, 113 and 114 are business/API errors and must not pass as success.
	 *
	 * @param int   $status_code HTTP status code.
	 * @param mixed $response    Parsed or raw response.
	 * @return bool
	 */
	private function is_successful_response( $status_code, $response ) {
		if ( 200 !== absint( $status_code ) ) {
			return false;
		}

		if ( is_array( $response ) && array_key_exists( 'status', $response ) ) {
			return 1 === absint( $response['status'] );
		}

		return false;
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
		$result = array(
			'success'         => (bool) $success,
			'message'         => sanitize_text_field( (string) $message ),
			'status_code'     => absint( $status_code ),
			'provider_status' => null,
			'data'            => null,
			'message_id'      => null,
			'cost'            => null,
			'response'        => $response,
		);

		if ( is_array( $response ) ) {
			if ( array_key_exists( 'status', $response ) ) {
				$result['provider_status'] = absint( $response['status'] );
			}

			if ( array_key_exists( 'data', $response ) ) {
				$result['data'] = $response['data'];
			}

			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				if ( isset( $response['data']['messageId'] ) ) {
					$result['message_id'] = absint( $response['data']['messageId'] );
				}

				if ( isset( $response['data']['cost'] ) && is_numeric( $response['data']['cost'] ) ) {
					$result['cost'] = (float) $response['data']['cost'];
				}
			}
		}

		return $result;
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
