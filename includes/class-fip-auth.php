<?php
/**
 * Mobile authentication module.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_Auth', false ) ) {
	return;
}

/**
 * Handles mobile OTP authentication and account bootstrap.
 */
class FIP_Auth {

	/**
	 * Renders the login/register form shortcode.
	 *
	 * @return string
	 */
	public function render_login_form() {
		if ( is_user_logged_in() ) {
			return $this->maybe_redirect_logged_in_user_from_login();
		}

		$state = array(
			'step'                => 'mobile',
			'mobile'              => '',
			'errors'              => array(),
			'messages'            => array(),
			'redirect_to'         => $this->get_posted_redirect_to(),
			'resend_wait_seconds' => 0,
			'can_show_dev_notice' => $this->can_show_dev_notice(),
			'mock_otp'            => '',
		);

		if ( 'POST' === $this->get_request_method() ) {
			$action = isset( $_POST['fip_auth_action'] ) ? sanitize_key( wp_unslash( $_POST['fip_auth_action'] ) ) : '';

			if ( 'send_otp' === $action ) {
				$state = $this->handle_send_otp();
			} elseif ( 'verify_otp' === $action ) {
				$state = $this->handle_verify_otp();
			}
		}

		$auth = $this;

		ob_start();
		include FILTER_INQUIRY_PORTAL_PLUGIN_DIR . 'templates/login.php';
		return (string) ob_get_clean();
	}

	/**
	 * Handles mobile submission and OTP creation/delivery.
	 *
	 * @return array<string,mixed>
	 */
	public function handle_send_otp() {
		$state = $this->get_default_state();
		$nonce = isset( $_POST['fip_send_otp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fip_send_otp_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'fip_send_otp_nonce' ) ) {
			$state['errors'][] = __( 'اعتبار فرم منقضی شده است. لطفاً دوباره تلاش کنید.', 'filter-inquiry-portal' );
			return $state;
		}

		$mobile              = isset( $_POST['fip_mobile'] ) ? $this->normalize_mobile( wp_unslash( $_POST['fip_mobile'] ) ) : '';
		$state['mobile']     = $mobile;
		$state['redirect_to'] = $this->get_posted_redirect_to();

		if ( ! $this->is_valid_iran_mobile( $mobile ) ) {
			$state['errors'][] = __( 'شماره موبایل واردشده معتبر نیست.', 'filter-inquiry-portal' );
			return $state;
		}

		$otp  = $this->get_otp_module();
		$code = $otp->create_otp( $mobile, $this->get_ip_address() );

		if ( is_wp_error( $code ) ) {
			$state['errors']              = $code->get_error_messages();
			$state['step']                = 'otp';
			$state['resend_wait_seconds'] = $otp->get_resend_wait_seconds( $mobile );
			$state['mock_otp']            = $this->can_show_dev_notice() ? $otp->get_last_mock_otp( $mobile ) : '';
			return $state;
		}

		$delivery = $this->deliver_otp( $mobile, $code );

		if ( is_wp_error( $delivery ) ) {
			$otp->clear_otp( $mobile );
			$state['errors'][] = $delivery->get_error_message();
			return $state;
		}

		$state['step']                = 'otp';
		$state['messages'][]          = __( 'کد ورود برای شماره واردشده ارسال شد.', 'filter-inquiry-portal' );
		$state['resend_wait_seconds'] = $otp->get_resend_wait_seconds( $mobile );
		$state['mock_otp']            = $this->can_show_dev_notice() ? $otp->get_last_mock_otp( $mobile ) : '';

		return $state;
	}


	/**
	 * Delivers an OTP by real sms.ir Verify SMS when configured, otherwise uses mock mode.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @param string $code   OTP code.
	 * @return true|WP_Error
	 */
	private function deliver_otp( $mobile, $code ) {
		$settings = $this->get_sms_settings();
		$logger   = fip_plugin()->get_module( 'sms_logger' );
		$otp      = $this->get_otp_module();

		if ( $this->should_send_real_otp_sms( $settings ) ) {
			$provider    = new FIP_SMSIR_Provider( $settings );
			$template_id = isset( $settings['smsir_template_otp'] ) ? absint( $settings['smsir_template_otp'] ) : 0;
			$result      = $provider->send_verify( $mobile, $template_id, array( 'CODE' => $code ) );

			if ( ! empty( $result['success'] ) ) {
				if ( $logger && method_exists( $logger, 'log' ) ) {
					$logger->log( $mobile, 'otp', 'success', isset( $result['message'] ) ? $result['message'] : '', isset( $result['response'] ) ? $result['response'] : null, null, null );
				}

				return true;
			}

			if ( $logger && method_exists( $logger, 'log' ) ) {
				$logger->log( $mobile, 'otp', 'failed', isset( $result['message'] ) ? $result['message'] : '', isset( $result['response'] ) ? $result['response'] : null, null, null );
			}

			return new WP_Error( 'fip_otp_sms_failed', __( 'ارسال کد تایید با خطا مواجه شد. لطفاً کمی بعد دوباره تلاش کنید.', 'filter-inquiry-portal' ) );
		}

		$otp->send_otp_mock( $mobile, $code );

		if ( $logger && method_exists( $logger, 'log' ) ) {
			$logger->log( $mobile, 'otp', 'skipped', __( 'ارسال واقعی پیامک غیرفعال یا تنظیم نشده است؛ کد در حالت توسعه تولید شد.', 'filter-inquiry-portal' ), null, null, null );
		}

		return true;
	}

	/**
	 * Checks whether OTP should be sent by sms.ir.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return bool
	 */
	private function should_send_real_otp_sms( $settings ) {
		return ! empty( $settings['sms_enabled'] ) && ! empty( $settings['smsir_api_key'] ) && ! empty( $settings['smsir_template_otp'] );
	}

	/**
	 * Gets plugin SMS settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_sms_settings() {
		$settings = fip_plugin()->get_module( 'settings' );

		if ( $settings && method_exists( $settings, 'get_sms_settings' ) ) {
			return $settings->get_sms_settings();
		}

		if ( $settings && method_exists( $settings, 'get_settings' ) ) {
			return $settings->get_settings();
		}

		$raw_settings = get_option( 'fip_settings', array() );

		return is_array( $raw_settings ) ? $raw_settings : array();
	}

	/**
	 * Handles OTP verification and user login/creation.
	 *
	 * @return array<string,mixed>
	 */
	public function handle_verify_otp() {
		$state = $this->get_default_state();
		$nonce = isset( $_POST['fip_verify_otp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fip_verify_otp_nonce'] ) ) : '';

		$state['step']        = 'otp';
		$state['redirect_to'] = $this->get_posted_redirect_to();

		if ( ! wp_verify_nonce( $nonce, 'fip_verify_otp_nonce' ) ) {
			$state['errors'][] = __( 'اعتبار فرم منقضی شده است. لطفاً دوباره تلاش کنید.', 'filter-inquiry-portal' );
			return $state;
		}

		$mobile          = isset( $_POST['fip_mobile'] ) ? $this->normalize_mobile( wp_unslash( $_POST['fip_mobile'] ) ) : '';
		$code            = isset( $_POST['fip_otp_code'] ) ? preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( $_POST['fip_otp_code'] ) ) ) : '';
		$state['mobile'] = $mobile;

		if ( ! $this->is_valid_iran_mobile( $mobile ) || 5 !== strlen( $code ) ) {
			$state['errors'][]              = __( 'شماره موبایل یا کد ورود معتبر نیست.', 'filter-inquiry-portal' );
			$state['resend_wait_seconds']   = $this->get_otp_module()->get_resend_wait_seconds( $mobile );
			$state['mock_otp']              = $this->can_show_dev_notice() ? $this->get_otp_module()->get_last_mock_otp( $mobile ) : '';
			return $state;
		}

		$verified = $this->get_otp_module()->verify_otp( $mobile, $code );
		if ( is_wp_error( $verified ) ) {
			$state['errors']              = $verified->get_error_messages();
			$state['resend_wait_seconds'] = $this->get_otp_module()->get_resend_wait_seconds( $mobile );
			$state['mock_otp']            = $this->can_show_dev_notice() ? $this->get_otp_module()->get_last_mock_otp( $mobile ) : '';
			return $state;
		}

		$user_id = $this->login_user_by_mobile( $mobile );
		if ( is_wp_error( $user_id ) ) {
			$state['errors'] = $user_id->get_error_messages();
			return $state;
		}

		$redirect = $this->get_redirect_after_login( $user_id, $state['redirect_to'] );
		$this->safe_redirect_or_message( $redirect );

		return $state;
	}

	/**
	 * Finds or creates a user by mobile and logs them in.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @return int|WP_Error
	 */
	public function login_user_by_mobile( $mobile ) {
		$user_id = $this->find_user_by_mobile( $mobile );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( ! $user_id ) {
			$user_id = $this->create_user_by_mobile( $mobile );
		}

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, 'fip_mobile_verified', '1' );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', get_userdata( $user_id )->user_login, get_userdata( $user_id ) );

		return $user_id;
	}

	/**
	 * Finds a unique user matching mobile across supported meta keys.
	 *
	 * @param string $mobile Mobile number.
	 * @return int|WP_Error
	 */
	public function find_user_by_mobile( $mobile ) {
		$mobile = $this->normalize_mobile( $mobile );
		if ( ! $this->is_valid_iran_mobile( $mobile ) ) {
			return 0;
		}

		$meta_query = array( 'relation' => 'OR' );
		foreach ( array( 'fip_mobile', 'billing_phone', 'mobile', 'phone' ) as $meta_key ) {
			$meta_query[] = array(
				'key'     => $meta_key,
				'compare' => 'EXISTS',
			);
		}

		$query = new WP_User_Query(
			array(
				'fields'     => 'ID',
				'meta_query' => $meta_query,
			)
		);

		$user_ids = array();
		foreach ( $query->get_results() as $user_id ) {
			$user_id = absint( $user_id );
			if ( $this->user_has_mobile( $user_id, $mobile ) ) {
				$user_ids[] = $user_id;
			}
		}

		$user_ids = array_values( array_unique( $user_ids ) );

		if ( 1 === count( $user_ids ) ) {
			update_user_meta( $user_ids[0], 'fip_mobile', $mobile );
			return $user_ids[0];
		}

		if ( count( $user_ids ) > 1 ) {
			return new WP_Error( 'fip_duplicate_mobile', __( 'برای ورود با این شماره مشکلی وجود دارد. لطفاً با پشتیبانی تماس بگیرید.', 'filter-inquiry-portal' ) );
		}

		return 0;
	}

	/**
	 * Creates a user for a verified mobile number.
	 *
	 * @param string $mobile Normalized mobile number.
	 * @return int|WP_Error
	 */
	public function create_user_by_mobile( $mobile ) {
		$mobile   = $this->normalize_mobile( $mobile );
		$username = 'fip_' . $mobile;
		$base     = $username;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			$username = $base . '_' . $suffix;
			$suffix++;
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_pass'  => wp_generate_password( 32, true, true ),
				'user_email' => $mobile . '@no-email.local',
				'role'       => class_exists( 'WooCommerce' ) ? 'customer' : 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, 'fip_mobile', $mobile );
		update_user_meta( $user_id, 'fip_mobile_verified', '1' );
		update_user_meta( $user_id, 'fip_profile_completed', '0' );

		return absint( $user_id );
	}

	/**
	 * Normalizes Iranian mobile formats to 09xxxxxxxxx.
	 *
	 * @param string $mobile Mobile number.
	 * @return string
	 */
	public function normalize_mobile( $mobile ) {
		$mobile = trim( (string) $mobile );
		$mobile = str_replace( array( ' ', '-', '(', ')' ), '', $mobile );
		$mobile = $this->convert_persian_digits( $mobile );

		if ( 0 === strpos( $mobile, '+98' ) ) {
			$mobile = '0' . substr( $mobile, 3 );
		} elseif ( 0 === strpos( $mobile, '98' ) ) {
			$mobile = '0' . substr( $mobile, 2 );
		} elseif ( 0 === strpos( $mobile, '9' ) ) {
			$mobile = '0' . $mobile;
		}

		return preg_replace( '/\D+/', '', $mobile );
	}

	/**
	 * Validates a normalized Iranian mobile number.
	 *
	 * @param string $mobile Mobile number.
	 * @return bool
	 */
	public function is_valid_iran_mobile( $mobile ) {
		$mobile = $this->normalize_mobile( $mobile );

		return 1 === preg_match( '/^09\d{9}$/', $mobile );
	}

	/**
	 * Gets configured login page URL.
	 *
	 * @return string
	 */
	public function get_login_page_url() {
		return $this->get_page_url_or_home( 'login_page_id' );
	}

	/**
	 * Gets configured dashboard page URL.
	 *
	 * @return string
	 */
	public function get_dashboard_page_url() {
		return $this->get_page_url_or_home( 'dashboard_page_id' );
	}

	/**
	 * Gets configured complete-profile page URL.
	 *
	 * @return string
	 */
	public function get_complete_profile_page_url() {
		return $this->get_page_url_or_home( 'complete_profile_page_id' );
	}

	/**
	 * Determines redirect target after login while enforcing profile rules.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $fallback Optional safe fallback URL.
	 * @return string
	 */
	public function get_redirect_after_login( $user_id, $fallback = '' ) {
		$user_id  = absint( $user_id );
		$profile  = fip_plugin()->get_module( 'profile' );
		$complete = $profile && method_exists( $profile, 'is_profile_completed' ) ? $profile->is_profile_completed( $user_id ) : false;

		if ( ! $complete ) {
			return $this->get_complete_profile_page_url();
		}

		if ( $fallback ) {
			$safe = wp_validate_redirect( $fallback, '' );
			if ( $safe ) {
				return $safe;
			}
		}

		return $this->get_dashboard_page_url();
	}

	/**
	 * Gets a WordPress logout URL redirecting to the login page.
	 *
	 * @return string
	 */
	public function logout_url() {
		return wp_logout_url( $this->get_login_page_url() );
	}

	/**
	 * Returns logged-in user guidance instead of the OTP form.
	 *
	 * @return string
	 */
	public function maybe_redirect_logged_in_user_from_login() {
		$user_id  = get_current_user_id();
		$profile  = fip_plugin()->get_module( 'profile' );
		$complete = $profile && method_exists( $profile, 'is_profile_completed' ) ? $profile->is_profile_completed( $user_id ) : false;
		$url      = $complete ? $this->get_dashboard_page_url() : $this->get_complete_profile_page_url();
		$text     = $complete ? __( 'رفتن به داشبورد', 'filter-inquiry-portal' ) : __( 'تکمیل پروفایل', 'filter-inquiry-portal' );
		$message  = $complete ? __( 'شما وارد حساب کاربری خود شده‌اید.', 'filter-inquiry-portal' ) : __( 'شما وارد شده‌اید؛ برای ادامه لطفاً پروفایل خود را تکمیل کنید.', 'filter-inquiry-portal' );

		return '<div class="fip_template fip_login" dir="rtl"><div class="fip_template__card"><p class="fip_notice fip_notice--info">' . esc_html( $message ) . '</p><p><a class="fip_button fip_button--primary" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></p></div></div>';
	}

	/**
	 * Gets a reusable login-required message.
	 *
	 * @return string
	 */
	public function require_login_message() {
		return '<div class="fip_notice fip_notice--info" dir="rtl">' . esc_html__( 'برای مشاهده این بخش ابتدا وارد شوید.', 'filter-inquiry-portal' ) . ' <a href="' . esc_url( $this->get_login_page_url() ) . '">' . esc_html__( 'ورود به پورتال', 'filter-inquiry-portal' ) . '</a></div>';
	}

	/**
	 * Gets a reusable profile-completion-required message.
	 *
	 * @return string
	 */
	public function require_completed_profile_message() {
		return '<div class="fip_notice fip_notice--info" dir="rtl">' . esc_html__( 'برای ادامه، تکمیل پروفایل الزامی است.', 'filter-inquiry-portal' ) . ' <a href="' . esc_url( $this->get_complete_profile_page_url() ) . '">' . esc_html__( 'تکمیل پروفایل', 'filter-inquiry-portal' ) . '</a></div>';
	}

	/**
	 * Builds a default rendering state.
	 *
	 * @return array<string,mixed>
	 */
	private function get_default_state() {
		return array(
			'step'                => 'mobile',
			'mobile'              => '',
			'errors'              => array(),
			'messages'            => array(),
			'redirect_to'         => $this->get_posted_redirect_to(),
			'resend_wait_seconds' => 0,
			'can_show_dev_notice' => $this->can_show_dev_notice(),
			'mock_otp'            => '',
		);
	}

	/**
	 * Gets the OTP module.
	 *
	 * @return FIP_OTP
	 */
	private function get_otp_module() {
		return fip_plugin()->get_module( 'otp' );
	}

	/**
	 * Gets request method.
	 *
	 * @return string
	 */
	private function get_request_method() {
		return isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	}

	/**
	 * Gets request IP address.
	 *
	 * @return string
	 */
	private function get_ip_address() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Gets and validates posted redirect URL.
	 *
	 * @return string
	 */
	private function get_posted_redirect_to() {
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';

		return $redirect ? wp_validate_redirect( $redirect, '' ) : '';
	}

	/**
	 * Checks whether development-only mock OTP may be displayed.
	 *
	 * @return bool
	 */
	private function can_show_dev_notice() {
		return current_user_can( 'manage_options' ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
	}

	/**
	 * Gets configured page URL or home fallback.
	 *
	 * @param string $key Page setting key.
	 * @return string
	 */
	private function get_page_url_or_home( $key ) {
		$settings = fip_plugin()->get_module( 'settings' );
		$url      = $settings && method_exists( $settings, 'get_page_url' ) ? $settings->get_page_url( $key ) : '';

		return $url ? $url : home_url( '/' );
	}

	/**
	 * Redirects safely or prints fallback markup if headers are unavailable.
	 *
	 * @param string $url Redirect URL.
	 * @return void
	 */
	private function safe_redirect_or_message( $url ) {
		if ( ! headers_sent() ) {
			wp_safe_redirect( $url );
			exit;
		}

		echo '<div class="fip_notice fip_notice--success" dir="rtl">' . esc_html__( 'ورود موفق بود.', 'filter-inquiry-portal' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'ادامه', 'filter-inquiry-portal' ) . '</a></div>';
	}

	/**
	 * Converts Persian/Arabic digits to English digits.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function convert_persian_digits( $value ) {
		return strtr(
			$value,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
	}

	/**
	 * Checks whether a user's supported mobile meta normalizes to the searched mobile.
	 *
	 * @param int    $user_id User ID.
	 * @param string $mobile  Normalized mobile.
	 * @return bool
	 */
	private function user_has_mobile( $user_id, $mobile ) {
		foreach ( array( 'fip_mobile', 'billing_phone', 'mobile', 'phone' ) as $meta_key ) {
			$value = (string) get_user_meta( $user_id, $meta_key, true );
			if ( $value && $this->normalize_mobile( $value ) === $mobile ) {
				return true;
			}
		}

		return false;
	}
}
