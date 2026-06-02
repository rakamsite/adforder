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

	const BASE_URL         = 'https://api.sms.ir';
	const VERIFY_ENDPOINT  = '/v1/send/verify';
	const SUCCESS_HTTP_MIN = 200;
	const SUCCESS_HTTP_MAX = 299;

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

		// sms.ir's current v1 client examples use these PascalCase JSON keys.
		// Keeping the exact documented shape avoids ambiguous provider-side
		// validation/authentication responses on stricter gateways.
		$body = array(
			'Mobile'     => $mobile,
			'TemplateId' => $template_id,
			'Parameters' => $this->format_verify_parameters( $parameters ),
		);

		$this->maybe_allow_smsir_host_when_http_is_blocked();

		if ( $this->is_smsir_host_blocked_by_wordpress() ) {
			return $this->build_response( false, $this->get_http_blocked_message(), 0, null );
		}

		$response = wp_remote_post(
			self::BASE_URL . self::VERIFY_ENDPOINT,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'User-Agent'   => 'FilterInquiryPortal/' . ( defined( 'FILTER_INQUIRY_PORTAL_VERSION' ) ? FILTER_INQUIRY_PORTAL_VERSION : '1.0.0' ) . '; WordPress/' . get_bloginfo( 'version' ),
					'X-API-KEY'    => $api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$message = $this->is_http_block_error( $response ) ? $this->get_http_blocked_message() : $response->get_error_message();

			return $this->build_response( false, $message, 0, null );
		}

		$status_code = absint( wp_remote_retrieve_response_code( $response ) );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$parsed_body = $this->parse_response_body( $raw_body );
		$message     = $this->extract_response_message( $parsed_body, $status_code );
		$success     = $this->is_successful_response( $status_code, $parsed_body );
		$debug       = $this->build_debug_response( $parsed_body, $body );

		return $this->build_response( $success, $message, $status_code, $debug );
	}

	/**
	 * Allows sms.ir when WordPress is configured to block external HTTP requests.
	 *
	 * WordPress checks the WP_ACCESSIBLE_HOSTS constant when WP_HTTP_BLOCK_EXTERNAL
	 * is enabled. Defining the allow-list at runtime keeps the sms.ir integration
	 * working on sites that have not already defined a custom allow-list.
	 *
	 * @return void
	 */
	private function maybe_allow_smsir_host_when_http_is_blocked() {
		if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL || defined( 'WP_ACCESSIBLE_HOSTS' ) ) {
			return;
		}

		define( 'WP_ACCESSIBLE_HOSTS', 'api.sms.ir' );
	}

	/**
	 * Checks whether WordPress will block requests to the sms.ir API host.
	 *
	 * @return bool
	 */
	private function is_smsir_host_blocked_by_wordpress() {
		if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
			return false;
		}

		if ( ! defined( 'WP_ACCESSIBLE_HOSTS' ) ) {
			return true;
		}

		return ! $this->is_host_allowed_by_wordpress( 'api.sms.ir', WP_ACCESSIBLE_HOSTS );
	}

	/**
	 * Determines whether a host exists in WordPress' external HTTP allow-list.
	 *
	 * @param string $host             Hostname to check.
	 * @param string $accessible_hosts Comma-separated WP_ACCESSIBLE_HOSTS value.
	 * @return bool
	 */
	private function is_host_allowed_by_wordpress( $host, $accessible_hosts ) {
		$host  = strtolower( trim( (string) $host ) );
		$items = preg_split( '/,\s*/', strtolower( (string) $accessible_hosts ) );

		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$item = trim( $item );

			if ( '' === $item ) {
				continue;
			}

			$pattern = '/^' . str_replace( '\\*', '.+', preg_quote( $item, '/' ) ) . '$/i';

			if ( 1 === preg_match( $pattern, $host ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether a WP_Error came from WordPress external HTTP blocking.
	 *
	 * @param WP_Error $error WordPress HTTP error.
	 * @return bool
	 */
	private function is_http_block_error( $error ) {
		return is_wp_error( $error ) && 'http_request_not_executed' === $error->get_error_code();
	}

	/**
	 * Returns an actionable message for blocked outbound sms.ir requests.
	 *
	 * @return string
	 */
	private function get_http_blocked_message() {
		return sprintf(
			/* translators: 1: WordPress constant name, 2: WordPress constant name, 3: example wp-config.php code. */
			__( 'درخواست خروجی وردپرس به api.sms.ir مسدود شده است. اگر در wp-config.php ثابت %1$s فعال است، api.sms.ir را به %2$s اضافه کنید؛ مثال: %3$s', 'filter-inquiry-portal' ),
			'WP_HTTP_BLOCK_EXTERNAL',
			'WP_ACCESSIBLE_HOSTS',
			"define( 'WP_ACCESSIBLE_HOSTS', 'api.sms.ir' );"
		);
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
	 * Normalizes a mobile number for sms.ir Verify requests (09123456789).
	 *
	 * @param string $mobile Mobile number.
	 * @return string
	 */
	public function normalize_mobile_for_provider( $mobile ) {
		$mobile = trim( $this->convert_persian_digits( (string) $mobile ) );
		$mobile = preg_replace( '/[^0-9+]/', '', $mobile );

		if ( 0 === strpos( $mobile, '+98' ) ) {
			$mobile = '0' . substr( $mobile, 3 );
		} elseif ( 0 === strpos( $mobile, '0098' ) ) {
			$mobile = '0' . substr( $mobile, 4 );
		} elseif ( 0 === strpos( $mobile, '98' ) ) {
			$mobile = '0' . substr( $mobile, 2 );
		} elseif ( 0 === strpos( $mobile, '9' ) ) {
			$mobile = '0' . $mobile;
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
		return 1 === preg_match( '/^09\d{9}$/', (string) $mobile );
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
		if ( 403 === absint( $status_code ) && $this->looks_like_forbidden_response( $response ) ) {
			return $this->get_forbidden_message();
		}

		if ( is_array( $response ) ) {
			foreach ( array( 'message', 'Message', 'error', 'Error' ) as $key ) {
				if ( isset( $response[ $key ] ) && is_scalar( $response[ $key ] ) ) {
					return sanitize_text_field( (string) $response[ $key ] );
				}
			}

			$detail = $this->extract_response_detail( $response );
			if ( '' !== $detail ) {
				return $detail;
			}
		}

		if ( is_string( $response ) && '' !== trim( $response ) ) {
			return sanitize_text_field( $response );
		}

		return $status_code >= self::SUCCESS_HTTP_MIN && $status_code <= self::SUCCESS_HTTP_MAX
			? __( 'پیامک با موفقیت ارسال شد.', 'filter-inquiry-portal' )
			: __( 'ارسال پیامک با خطا مواجه شد.', 'filter-inquiry-portal' );
	}

	/**
	 * Checks whether the provider returned a generic 403/HTML forbidden page.
	 *
	 * @param mixed $response Parsed or raw provider response.
	 * @return bool
	 */
	private function looks_like_forbidden_response( $response ) {
		if ( is_array( $response ) ) {
			foreach ( array( 'message', 'Message', 'error', 'Error' ) as $key ) {
				if ( isset( $response[ $key ] ) && is_scalar( $response[ $key ] ) ) {
					return $this->looks_like_forbidden_response( (string) $response[ $key ] );
				}
			}

			return false;
		}

		if ( ! is_string( $response ) ) {
			return false;
		}

		$response = strtolower( wp_strip_all_tags( $response ) );

		return false !== strpos( $response, 'forbidden' ) || false !== strpos( $response, "don't have permission" );
	}

	/**
	 * Returns an actionable admin-safe message for sms.ir 403 responses.
	 *
	 * @return string
	 */
	private function get_forbidden_message() {
		$server_ips = $this->get_server_ip_candidates();
		$ip_hint    = empty( $server_ips ) ? '' : sprintf(
			/* translators: %s: server IP candidates. */
			__( ' IP احتمالی سرور برای بررسی در پنل sms.ir: %s', 'filter-inquiry-portal' ),
			implode( ', ', $server_ips )
		);

		return sprintf(
			/* translators: %s: server IP hint. */
			__( 'sms.ir پاسخ 403 Forbidden برگرداند؛ این معمولاً از محدودیت دسترسی API، غیرفعال بودن وب‌سرویس برای API Key، محدودیت/Whitelist آی‌پی یا بلاک فایروال/دیتاسنتر است. API Key و دسترسی وب‌سرویس را در پنل sms.ir بررسی کنید و اگر محدودیت IP فعال است، IP سرور سایت را مجاز کنید.%s', 'filter-inquiry-portal' ),
			$ip_hint
		);
	}

	/**
	 * Gets public-ish server IP candidates for admin diagnostics.
	 *
	 * @return string[]
	 */
	private function get_server_ip_candidates() {
		$candidates = array();

		foreach ( array( 'SERVER_ADDR', 'LOCAL_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
					$candidates[] = $value;
				}
			}
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $host ) {
			$resolved = gethostbyname( $host );
			if ( $resolved && $resolved !== $host && filter_var( $resolved, FILTER_VALIDATE_IP ) ) {
				$candidates[] = $resolved;
			}
		}

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Builds a sanitized debug payload for SMS logs.
	 *
	 * @param mixed               $provider_response Parsed or raw provider response.
	 * @param array<string,mixed> $request_body      Request body sent to sms.ir without secrets.
	 * @return array<string,mixed>
	 */
	private function build_debug_response( $provider_response, array $request_body ) {
		return array(
			'provider_response' => $provider_response,
			'request'           => array(
				'url'  => self::BASE_URL . self::VERIFY_ENDPOINT,
				'body' => $request_body,
			),
		);
	}

	/**
	 * Extracts nested validation details from sms.ir responses when present.
	 *
	 * @param array<string,mixed> $response Parsed provider response.
	 * @return string
	 */
	private function extract_response_detail( array $response ) {
		$details = array();
		$keys    = array( 'data', 'errors', 'Errors', 'validationErrors' );

		foreach ( $keys as $key ) {
			if ( isset( $response[ $key ] ) ) {
				$details = array_merge( $details, $this->flatten_response_messages( $response[ $key ] ) );
			}
		}

		$details = array_filter( array_map( 'trim', $details ), 'strlen' );

		return empty( $details ) ? '' : sanitize_text_field( implode( ' | ', array_slice( array_unique( $details ), 0, 3 ) ) );
	}

	/**
	 * Flattens scalar messages from a nested provider response.
	 *
	 * @param mixed $value Response value.
	 * @return string[]
	 */
	private function flatten_response_messages( $value ) {
		$messages = array();

		if ( is_scalar( $value ) ) {
			return array( (string) $value );
		}

		if ( ! is_array( $value ) ) {
			return $messages;
		}

		foreach ( $value as $nested ) {
			$messages = array_merge( $messages, $this->flatten_response_messages( $nested ) );
		}

		return $messages;
	}

	/**
	 * Determines whether sms.ir response should be considered successful.
	 *
	 * @param int   $status_code HTTP status code.
	 * @param mixed $response    Parsed or raw response.
	 * @return bool
	 */
	private function is_successful_response( $status_code, $response ) {
		if ( $status_code < self::SUCCESS_HTTP_MIN || $status_code > self::SUCCESS_HTTP_MAX ) {
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
