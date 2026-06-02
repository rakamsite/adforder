<?php
/**
 * Plugin settings screens and options module.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_Settings', false ) ) {
	return;
}

/**
 * Handles Phase 1 plugin settings.
 */
class FIP_Settings {

	/**
	 * Option name used to store all plugin settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'fip_settings';

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Page mapping fields.
	 *
	 * @var array<string,string>
	 */
	private $page_fields = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->page_fields = array(
			'login_page_id'            => __( 'صفحه ورود', 'filter-inquiry-portal' ),
			'dashboard_page_id'        => __( 'صفحه داشبورد', 'filter-inquiry-portal' ),
			'complete_profile_page_id' => __( 'صفحه تکمیل پروفایل', 'filter-inquiry-portal' ),
			'edit_profile_page_id'     => __( 'صفحه ویرایش پروفایل', 'filter-inquiry-portal' ),
			'new_request_page_id'      => __( 'صفحه ثبت درخواست', 'filter-inquiry-portal' ),
			'my_requests_page_id'      => __( 'صفحه درخواست‌های من', 'filter-inquiry-portal' ),
			'request_detail_page_id'   => __( 'صفحه جزئیات درخواست', 'filter-inquiry-portal' ),
		);

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_fip_send_test_sms', array( $this, 'handle_test_sms' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Returns default settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_defaults() {
		return array(
			'login_page_id'                         => 0,
			'dashboard_page_id'                     => 0,
			'complete_profile_page_id'              => 0,
			'edit_profile_page_id'                  => 0,
			'new_request_page_id'                   => 0,
			'my_requests_page_id'                   => 0,
			'request_detail_page_id'                => 0,
			'dashboard_notice_enabled'              => 0,
			'dashboard_notice_title'                => '',
			'dashboard_notice_content'              => '',
			'activity_fields'                       => "تولیدکننده ماشین‌آلات سنگین\nراه‌آهن و ریلی\nنفت و پتروشیمی\nکمپرسور و ژنراتور\nمعادن",
			'max_request_items'                     => 10,
			'max_customer_updates'                  => 5,
			'max_image_size_mb'                     => 5,
			'admin_notification_email'              => get_option( 'admin_email' ),
			'sms_admin_new_request_enabled'         => 0,
			'admin_mobile'                          => '',
			'sms_enabled'                           => 0,
			'sms_provider'                          => 'smsir',
			'smsir_api_key'                         => '',
			'smsir_default_line_number'             => '',
			'smsir_use_verify_for_notifications'    => 1,
			'smsir_template_otp'                    => 0,
			'smsir_template_request_created'        => 0,
			'smsir_template_status_changed'         => 0,
			'smsir_template_admin_new_request'      => 0,
			'sms_test_mobile'                       => '',
		);
	}

	/**
	 * Returns all settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $this->get_defaults() );
	}

	/**
	 * Gets one setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public function get_option( $key, $default = null ) {
		$settings = $this->get_settings();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Gets a configured page URL by setting key.
	 *
	 * @param string $key Page ID setting key.
	 * @return string
	 */
	public function get_page_url( $key ) {
		$page_id = absint( $this->get_option( $key, 0 ) );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Gets activity fields as a clean line-based array.
	 *
	 * @return string[]
	 */
	public function get_activity_fields() {
		$fields = preg_split( '/\r\n|\r|\n/', (string) $this->get_option( 'activity_fields', '' ) );
		$fields = array_map( 'trim', is_array( $fields ) ? $fields : array() );
		$fields = array_filter( $fields, 'strlen' );

		return array_values( $fields );
	}

	/**
	 * Gets active SMS settings only.
	 *
	 * Old status-specific template keys may still exist in the saved option,
	 * but they are intentionally excluded from this active settings view.
	 *
	 * @return array<string,mixed>
	 */
	public function get_sms_settings() {
		$settings = $this->get_settings();
		$defaults = $this->get_defaults();
		$keys     = array(
			'sms_enabled',
			'sms_provider',
			'smsir_api_key',
			'smsir_default_line_number',
			'smsir_use_verify_for_notifications',
			'smsir_template_otp',
			'smsir_template_request_created',
			'smsir_template_status_changed',
			'smsir_template_admin_new_request',
			'sms_test_mobile',
		);
		$sms_settings = array();

		foreach ( $keys as $key ) {
			$sms_settings[ $key ] = array_key_exists( $key, $settings ) ? $settings[ $key ] : $defaults[ $key ];
		}

		return $sms_settings;
	}

	/**
	 * Gets an sms.ir template ID by active or backward-compatible template type.
	 *
	 * @param string $type Template type.
	 * @return int
	 */
	public function get_smsir_template_id( $type ) {
		$type = sanitize_key( (string) $type );
		$map  = array(
			'otp'               => 'smsir_template_otp',
			'request_created'   => 'smsir_template_request_created',
			'status_changed'    => 'smsir_template_status_changed',
			'admin_new_request' => 'smsir_template_admin_new_request',
		);

		if ( in_array( $type, array( 'status_reviewing', 'status_need_info', 'status_answered', 'status_rejected', 'status_closed' ), true ) ) {
			$type = 'status_changed';
		}

		if ( ! isset( $map[ $type ] ) ) {
			return 0;
		}

		return absint( $this->get_option( $map[ $type ], 0 ) );
	}

	/**
	 * Registers the admin menu page.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		$this->page_hook = add_menu_page(
			__( 'تنظیمات پنل استعلام', 'filter-inquiry-portal' ),
			__( 'استعلام فیلتر', 'filter-inquiry-portal' ),
			'manage_options',
			'fip_settings',
			array( $this, 'render_settings_page' ),
			'dashicons-filter',
			56
		);

		add_submenu_page(
			'fip_settings',
			__( 'تنظیمات پنل استعلام', 'filter-inquiry-portal' ),
			__( 'تنظیمات پنل استعلام', 'filter-inquiry-portal' ),
			'manage_options',
			'fip_settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers Settings API sections and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'fip_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_defaults(),
			)
		);

		add_settings_section( 'fip_page_settings', __( 'تنظیمات صفحات پنل', 'filter-inquiry-portal' ), array( $this, 'render_page_section_intro' ), 'fip_settings' );
		foreach ( $this->page_fields as $key => $label ) {
			add_settings_field( $key, $label, array( $this, 'render_page_field' ), 'fip_settings', 'fip_page_settings', array( 'key' => $key ) );
		}

		add_settings_section( 'fip_dashboard_notice_settings', __( 'تنظیمات اطلاعیه داشبورد', 'filter-inquiry-portal' ), array( $this, 'render_dashboard_notice_intro' ), 'fip_settings' );
		add_settings_field( 'dashboard_notice_enabled', __( 'فعال بودن اطلاعیه داشبورد', 'filter-inquiry-portal' ), array( $this, 'render_checkbox_field' ), 'fip_settings', 'fip_dashboard_notice_settings', array( 'key' => 'dashboard_notice_enabled' ) );
		add_settings_field( 'dashboard_notice_title', __( 'عنوان اطلاعیه', 'filter-inquiry-portal' ), array( $this, 'render_text_field' ), 'fip_settings', 'fip_dashboard_notice_settings', array( 'key' => 'dashboard_notice_title' ) );
		add_settings_field( 'dashboard_notice_content', __( 'متن اطلاعیه', 'filter-inquiry-portal' ), array( $this, 'render_textarea_field' ), 'fip_settings', 'fip_dashboard_notice_settings', array( 'key' => 'dashboard_notice_content', 'rows' => 4 ) );

		add_settings_section( 'fip_profile_settings', __( 'تنظیمات پروفایل', 'filter-inquiry-portal' ), array( $this, 'render_profile_section_intro' ), 'fip_settings' );
		add_settings_field( 'activity_fields', __( 'حوزه‌های فعالیت', 'filter-inquiry-portal' ), array( $this, 'render_textarea_field' ), 'fip_settings', 'fip_profile_settings', array( 'key' => 'activity_fields', 'rows' => 7, 'description' => __( 'هر حوزه فعالیت را در یک خط جداگانه وارد کنید.', 'filter-inquiry-portal' ) ) );

		add_settings_section( 'fip_request_settings', __( 'تنظیمات درخواست‌ها', 'filter-inquiry-portal' ), array( $this, 'render_request_section_intro' ), 'fip_settings' );
		add_settings_field( 'max_request_items', __( 'حداکثر تعداد آیتم در هر درخواست', 'filter-inquiry-portal' ), array( $this, 'render_number_field' ), 'fip_settings', 'fip_request_settings', array( 'key' => 'max_request_items', 'min' => 1, 'max' => 10 ) );
		add_settings_field( 'max_customer_updates', __( 'حداکثر تعداد اطلاعات تکمیلی برای هر درخواست', 'filter-inquiry-portal' ), array( $this, 'render_number_field' ), 'fip_settings', 'fip_request_settings', array( 'key' => 'max_customer_updates', 'min' => 0, 'max' => 10 ) );
		add_settings_field( 'max_image_size_mb', __( 'حداکثر حجم تصویر، مگابایت', 'filter-inquiry-portal' ), array( $this, 'render_number_field' ), 'fip_settings', 'fip_request_settings', array( 'key' => 'max_image_size_mb', 'min' => 1, 'max' => 10 ) );
		add_settings_field( 'admin_notification_email', __( 'ایمیل مدیر برای اطلاع‌رسانی درخواست‌ها', 'filter-inquiry-portal' ), array( $this, 'render_email_field' ), 'fip_settings', 'fip_request_settings', array( 'key' => 'admin_notification_email' ) );
		add_settings_field( 'sms_admin_new_request_enabled', __( 'ارسال پیامک به مدیر هنگام ثبت درخواست جدید', 'filter-inquiry-portal' ), array( $this, 'render_checkbox_field' ), 'fip_settings', 'fip_request_settings', array( 'key' => 'sms_admin_new_request_enabled' ) );
		add_settings_field( 'admin_mobile', __( 'شماره موبایل مدیر', 'filter-inquiry-portal' ), array( $this, 'render_text_field' ), 'fip_settings', 'fip_request_settings', array( 'key' => 'admin_mobile', 'description' => __( 'در این فاز فقط ذخیره می‌شود و اعتبارسنجی عمیق شماره انجام نمی‌شود.', 'filter-inquiry-portal' ) ) );

		add_settings_section( 'fip_sms_settings', __( 'تنظیمات پیامک sms.ir', 'filter-inquiry-portal' ), array( $this, 'render_sms_section_intro' ), 'fip_settings' );
		add_settings_field( 'sms_enabled', __( 'فعال‌سازی پیامک', 'filter-inquiry-portal' ), array( $this, 'render_checkbox_field' ), 'fip_settings', 'fip_sms_settings', array( 'key' => 'sms_enabled' ) );
		add_settings_field( 'smsir_api_key', __( 'API Key سامانه sms.ir', 'filter-inquiry-portal' ), array( $this, 'render_text_field' ), 'fip_settings', 'fip_sms_settings', array( 'key' => 'smsir_api_key', 'type' => 'password' ) );
		add_settings_field( 'smsir_default_line_number', __( 'شماره خط پیش‌فرض، فقط برای ارسال‌های غیر Verify', 'filter-inquiry-portal' ), array( $this, 'render_text_field' ), 'fip_settings', 'fip_sms_settings', array( 'key' => 'smsir_default_line_number' ) );
		add_settings_field( 'smsir_use_verify_for_notifications', __( 'استفاده از ارسال قالبی/Verify برای پیامک‌های اطلاع‌رسانی', 'filter-inquiry-portal' ), array( $this, 'render_checkbox_field' ), 'fip_settings', 'fip_sms_settings', array( 'key' => 'smsir_use_verify_for_notifications' ) );

		$smsir_template_fields = array(
			'smsir_template_otp'               => array(
				'label'       => __( 'Template ID کد ورود', 'filter-inquiry-portal' ),
				'description' => __( "کد ورود شما: #CODE#
اعتبار: ۳ دقیقه
متغیر الزامی: CODE", 'filter-inquiry-portal' ),
			),
			'smsir_template_request_created'   => array(
				'label'       => __( 'Template ID ثبت درخواست', 'filter-inquiry-portal' ),
				'description' => __( "درخواست شما با شماره #REQUEST# ثبت شد و در انتظار بررسی است.
متغیر الزامی: REQUEST", 'filter-inquiry-portal' ),
			),
			'smsir_template_status_changed'    => array(
				'label'       => __( 'Template ID تغییر وضعیت درخواست', 'filter-inquiry-portal' ),
				'description' => __( "وضعیت درخواست #REQUEST# به #STATUS# تغییر کرد.
لطفاً پنل کاربری را بررسی کنید.
متغیرهای الزامی: REQUEST و STATUS", 'filter-inquiry-portal' ),
			),
			'smsir_template_admin_new_request' => array(
				'label'       => __( 'Template ID پیامک مدیر برای درخواست جدید، اختیاری', 'filter-inquiry-portal' ),
				'description' => __( "درخواست جدید #REQUEST# با موبایل #MOBILE# ثبت شد.
متغیرهای الزامی: REQUEST و MOBILE", 'filter-inquiry-portal' ),
			),
		);

		foreach ( $smsir_template_fields as $key => $field ) {
			add_settings_field( $key, $field['label'], array( $this, 'render_text_field' ), 'fip_settings', 'fip_sms_settings', array( 'key' => $key, 'type' => 'number', 'description' => $field['description'] ) );
		}

		add_settings_field( 'sms_test_mobile', __( 'شماره تست پیامک', 'filter-inquiry-portal' ), array( $this, 'render_text_field' ), 'fip_settings', 'fip_sms_settings', array( 'key' => 'sms_test_mobile' ) );
	}


	/**
	 * Handles admin test SMS sending.
	 *
	 * @return void
	 */
	public function handle_test_sms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما مجوز ارسال پیامک تست را ندارید.', 'filter-inquiry-portal' ) );
		}

		$nonce = isset( $_POST['fip_send_test_sms_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fip_send_test_sms_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'fip_send_test_sms' ) ) {
			$this->redirect_test_sms_result( 'error', __( 'اعتبار درخواست تست پیامک منقضی شده است.', 'filter-inquiry-portal' ) );
		}

		$settings    = $this->get_settings();
		$mobile      = isset( $settings['sms_test_mobile'] ) ? sanitize_text_field( $settings['sms_test_mobile'] ) : '';
		$template_id = isset( $settings['smsir_template_otp'] ) ? absint( $settings['smsir_template_otp'] ) : 0;
		$logger      = fip_plugin()->get_module( 'sms_logger' );

		if ( empty( $settings['smsir_api_key'] ) || $template_id <= 0 || '' === $mobile ) {
			if ( $logger && method_exists( $logger, 'log' ) ) {
				$logger->log( $mobile, 'test', 'failed', __( 'تنظیمات پیامک تست کامل نیست.', 'filter-inquiry-portal' ), null, null, get_current_user_id() );
			}

			$this->redirect_test_sms_result( 'error', __( 'برای تست پیامک، API Key، Template ID کد ورود و شماره تست را ذخیره کنید.', 'filter-inquiry-portal' ) );
		}

		$provider = new FIP_SMSIR_Provider( $settings );
		$result   = $provider->send_verify( $mobile, $template_id, array( 'CODE' => '12345' ) );
		$status   = ! empty( $result['success'] ) ? 'success' : 'failed';
		$message  = isset( $result['message'] ) ? $result['message'] : '';

		if ( $logger && method_exists( $logger, 'log' ) ) {
			$logger->log( $mobile, 'test', $status, $message, isset( $result['response'] ) ? $result['response'] : null, null, get_current_user_id() );
		}

		if ( 'success' === $status ) {
			$this->redirect_test_sms_result( 'success', __( 'پیامک تست با موفقیت ارسال شد.', 'filter-inquiry-portal' ) );
		}

		$this->redirect_test_sms_result( 'error', __( 'ارسال پیامک تست با خطا مواجه شد. تنظیمات و قالب sms.ir را بررسی کنید.', 'filter-inquiry-portal' ) );
	}

	/**
	 * Renders admin notice for test SMS result.
	 *
	 * @return void
	 */
	private function render_test_sms_notice() {
		$status  = isset( $_GET['fip_sms_test_status'] ) ? sanitize_key( wp_unslash( $_GET['fip_sms_test_status'] ) ) : '';
		$message = isset( $_GET['fip_sms_test_message'] ) ? sanitize_text_field( wp_unslash( $_GET['fip_sms_test_message'] ) ) : '';

		if ( ! $status || ! $message ) {
			return;
		}

		$class = 'success' === $status ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';
		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Redirects back to settings with a sanitized test SMS result.
	 *
	 * @param string $status  success|error.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function redirect_test_sms_result( $status, $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                 => 'fip_settings',
					'fip_sms_test_status'  => sanitize_key( $status ),
					'fip_sms_test_message' => sanitize_text_field( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueues admin assets only on this plugin settings page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		$assets = fip_plugin()->get_module( 'assets' );
		if ( $assets && method_exists( $assets, 'enqueue_admin_assets' ) ) {
			$assets->enqueue_admin_assets();
		}
	}

	/**
	 * Sanitizes all settings.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$defaults  = $this->get_defaults();
		$existing  = get_option( self::OPTION_NAME, array() );
		$output    = is_array( $existing ) ? $existing : array();

		foreach ( array_keys( $this->page_fields ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
		}

		$output['dashboard_notice_enabled'] = empty( $input['dashboard_notice_enabled'] ) ? 0 : 1;
		$output['dashboard_notice_title']   = isset( $input['dashboard_notice_title'] ) ? sanitize_text_field( wp_unslash( $input['dashboard_notice_title'] ) ) : '';
		$output['dashboard_notice_content'] = isset( $input['dashboard_notice_content'] ) ? sanitize_textarea_field( wp_unslash( $input['dashboard_notice_content'] ) ) : '';
		$output['activity_fields']          = isset( $input['activity_fields'] ) ? sanitize_textarea_field( wp_unslash( $input['activity_fields'] ) ) : $defaults['activity_fields'];

		$output['max_request_items']    = $this->sanitize_int_range( isset( $input['max_request_items'] ) ? $input['max_request_items'] : $defaults['max_request_items'], 1, 10, $defaults['max_request_items'] );
		$output['max_customer_updates'] = $this->sanitize_int_range( isset( $input['max_customer_updates'] ) ? $input['max_customer_updates'] : $defaults['max_customer_updates'], 0, 10, $defaults['max_customer_updates'] );
		$output['max_image_size_mb']    = $this->sanitize_int_range( isset( $input['max_image_size_mb'] ) ? $input['max_image_size_mb'] : $defaults['max_image_size_mb'], 1, 10, $defaults['max_image_size_mb'] );

		$email = isset( $input['admin_notification_email'] ) ? sanitize_email( wp_unslash( $input['admin_notification_email'] ) ) : '';
		$output['admin_notification_email'] = is_email( $email ) ? $email : get_option( 'admin_email' );
		$output['sms_admin_new_request_enabled'] = empty( $input['sms_admin_new_request_enabled'] ) ? 0 : 1;
		$output['admin_mobile'] = isset( $input['admin_mobile'] ) ? sanitize_text_field( wp_unslash( $input['admin_mobile'] ) ) : '';

		$output['sms_enabled']                        = empty( $input['sms_enabled'] ) ? 0 : 1;
		$output['sms_provider']                       = 'smsir';
		$output['smsir_api_key']                      = isset( $input['smsir_api_key'] ) ? sanitize_text_field( wp_unslash( $input['smsir_api_key'] ) ) : '';
		$output['smsir_default_line_number']          = isset( $input['smsir_default_line_number'] ) ? sanitize_text_field( wp_unslash( $input['smsir_default_line_number'] ) ) : '';
		$output['smsir_use_verify_for_notifications'] = empty( $input['smsir_use_verify_for_notifications'] ) ? 0 : 1;

		$smsir_template_keys = array(
			'smsir_template_otp',
			'smsir_template_request_created',
			'smsir_template_status_changed',
			'smsir_template_admin_new_request',
		);

		foreach ( $smsir_template_keys as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
		}

		$output['sms_test_mobile'] = isset( $input['sms_test_mobile'] ) ? sanitize_text_field( wp_unslash( $input['sms_test_mobile'] ) ) : '';

		return wp_parse_args( $output, $defaults );
	}

	/**
	 * Sanitizes an integer within a closed range.
	 *
	 * @param mixed $value   Raw value.
	 * @param int   $min     Minimum value.
	 * @param int   $max     Maximum value.
	 * @param int   $default Default value.
	 * @return int
	 */
	private function sanitize_int_range( $value, $min, $max, $default ) {
		$value = absint( $value );

		if ( $value < $min || $value > $max ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما مجوز دسترسی به این صفحه را ندارید.', 'filter-inquiry-portal' ) );
		}
		?>
		<div class="wrap fip_admin_wrap" dir="rtl">
			<h1><?php echo esc_html__( 'تنظیمات پنل استعلام', 'filter-inquiry-portal' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'تنظیمات پایه، صفحات، پروفایل، درخواست‌ها و اتصال پیامک sms.ir را مدیریت کنید.', 'filter-inquiry-portal' ); ?></p>
			<?php $this->render_test_sms_notice(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'fip_settings_group' );
				do_settings_sections( 'fip_settings' );
				submit_button( __( 'ذخیره تنظیمات', 'filter-inquiry-portal' ) );
				?>
			</form>
			<hr />
			<h2><?php echo esc_html__( 'تست پیامک sms.ir', 'filter-inquiry-portal' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'پس از ذخیره API Key، Template ID کد ورود و شماره تست، از این دکمه برای ارسال کد تست 12345 استفاده کنید.', 'filter-inquiry-portal' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fip_send_test_sms" />
				<?php wp_nonce_field( 'fip_send_test_sms', 'fip_send_test_sms_nonce' ); ?>
				<?php submit_button( __( 'ارسال پیامک تست', 'filter-inquiry-portal' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/** Section intro. */
	public function render_page_section_intro() {
		echo '<p>' . esc_html__( 'صفحات وردپرس مربوط به هر بخش پنل را انتخاب کنید. ابتدا صفحات را بسازید و شورت‌کد مربوط را داخل هر صفحه قرار دهید.', 'filter-inquiry-portal' ) . '</p>';
	}

	/** Section intro. */
	public function render_dashboard_notice_intro() {
		echo '<p>' . esc_html__( 'این اطلاعیه برای نمایش در داشبورد مشتریان در فازهای بعدی استفاده می‌شود.', 'filter-inquiry-portal' ) . '</p>';
	}

	/** Section intro. */
	public function render_profile_section_intro() {
		echo '<p>' . esc_html__( 'گزینه‌های حوزه فعالیت مشتریان را مدیریت کنید.', 'filter-inquiry-portal' ) . '</p>';
	}

	/** Section intro. */
	public function render_request_section_intro() {
		echo '<p>' . esc_html__( 'محدودیت‌های پایه درخواست و اطلاعات تماس مدیر را تنظیم کنید. ثبت درخواست هنوز پیاده‌سازی نشده است.', 'filter-inquiry-portal' ) . '</p>';
	}

	/** Section intro. */
	public function render_sms_section_intro() {
		echo '<p>' . esc_html__( 'برای ارسال پیامک‌های خدماتی در sms.ir باید ابتدا قالب‌ها را در پنل sms.ir بسازید و Template ID هر قالب را در این بخش وارد کنید. نام متغیرها در قالب باید دقیقاً با نام‌های CODE، REQUEST، STATUS و MOBILE مطابقت داشته باشد.', 'filter-inquiry-portal' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'اگر قبلاً برای هر وضعیت قالب جداگانه وارد کرده‌اید، از این نسخه به بعد فقط قالب عمومی «تغییر وضعیت درخواست» استفاده می‌شود. مقادیر قبلی حذف نمی‌شوند اما دیگر در تنظیمات نمایش داده نمی‌شوند.', 'filter-inquiry-portal' ) . '</p>';
	}

	/**
	 * Renders a page dropdown field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_page_field( $args ) {
		$key = isset( $args['key'] ) ? $args['key'] : '';
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION_NAME . '[' . esc_attr( $key ) . ']',
				'id'                => 'fip_' . esc_attr( $key ),
				'selected'          => absint( $this->get_option( $key, 0 ) ),
				'show_option_none'  => __( '— انتخاب نشده —', 'filter-inquiry-portal' ),
				'option_none_value' => 0,
			)
		);
	}

	/**
	 * Renders a checkbox field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_checkbox_field( $args ) {
		$key = isset( $args['key'] ) ? $args['key'] : '';
		?>
		<label for="fip_<?php echo esc_attr( $key ); ?>">
			<input type="checkbox" id="fip_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="1" <?php checked( 1, absint( $this->get_option( $key, 0 ) ) ); ?> />
			<?php echo esc_html__( 'فعال', 'filter-inquiry-portal' ); ?>
		</label>
		<?php
	}

	/**
	 * Renders a text-like field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_text_field( $args ) {
		$key         = isset( $args['key'] ) ? $args['key'] : '';
		$type        = isset( $args['type'] ) ? $args['type'] : 'text';
		$description = isset( $args['description'] ) ? $args['description'] : '';
		?>
		<input class="regular-text" type="<?php echo esc_attr( $type ); ?>" id="fip_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $this->get_option( $key, '' ) ); ?>" autocomplete="off" />
		<?php if ( $description ) : ?>
			<p class="description"><?php echo wp_kses_post( nl2br( esc_html( $description ) ) ); ?></p>
		<?php endif; ?>
		<?php
		if ( 'sms_test_mobile' === $key ) {
			echo '<p class="description">' . esc_html__( 'برای تست، تنظیمات را ذخیره کنید و سپس از دکمه تست پایین صفحه استفاده کنید.', 'filter-inquiry-portal' ) . '</p>';
		}
	}

	/**
	 * Renders an email field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_email_field( $args ) {
		$args['type'] = 'email';
		$this->render_text_field( $args );
	}

	/**
	 * Renders a number field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_number_field( $args ) {
		$key = isset( $args['key'] ) ? $args['key'] : '';
		$min = isset( $args['min'] ) ? (int) $args['min'] : 0;
		$max = isset( $args['max'] ) ? (int) $args['max'] : 10;
		?>
		<input class="small-text" type="number" id="fip_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( absint( $this->get_option( $key, $min ) ) ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" />
		<p class="description"><?php echo esc_html( sprintf( __( 'عدد مجاز بین %1$d و %2$d است.', 'filter-inquiry-portal' ), $min, $max ) ); ?></p>
		<?php
	}

	/**
	 * Renders a textarea field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_textarea_field( $args ) {
		$key         = isset( $args['key'] ) ? $args['key'] : '';
		$rows        = isset( $args['rows'] ) ? absint( $args['rows'] ) : 5;
		$description = isset( $args['description'] ) ? $args['description'] : '';
		?>
		<textarea class="large-text" id="fip_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" rows="<?php echo esc_attr( $rows ); ?>"><?php echo esc_textarea( $this->get_option( $key, '' ) ); ?></textarea>
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}

}
