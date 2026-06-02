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
			'sms_connection_type'                   => 'rest_token',
			'sms_api_token'                         => '',
			'sms_username'                          => '',
			'sms_password'                          => '',
			'sms_sender'                            => '',
			'sms_pattern_otp'                       => '',
			'sms_pattern_request_created'           => '',
			'sms_pattern_status_reviewing'          => '',
			'sms_pattern_status_need_info'          => '',
			'sms_pattern_status_answered'           => '',
			'sms_pattern_status_rejected'           => '',
			'sms_pattern_status_closed'             => '',
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

		add_settings_section( 'fip_sms_settings', __( 'تنظیمات پیامک', 'filter-inquiry-portal' ), array( $this, 'render_sms_section_intro' ), 'fip_settings' );
		add_settings_field( 'sms_enabled', __( 'فعال‌سازی پیامک', 'filter-inquiry-portal' ), array( $this, 'render_checkbox_field' ), 'fip_settings', 'fip_sms_settings', array( 'key' => 'sms_enabled' ) );
		add_settings_field( 'sms_connection_type', __( 'نوع اتصال ملی‌پیامک', 'filter-inquiry-portal' ), array( $this, 'render_sms_connection_type_field' ), 'fip_settings', 'fip_sms_settings' );

		$sms_text_fields = array(
			'sms_api_token'                => __( 'API Key / Token', 'filter-inquiry-portal' ),
			'sms_username'                 => __( 'Username', 'filter-inquiry-portal' ),
			'sms_password'                 => __( 'Password', 'filter-inquiry-portal' ),
			'sms_sender'                   => __( 'شماره فرستنده', 'filter-inquiry-portal' ),
			'sms_pattern_otp'              => __( 'Pattern ID کد ورود', 'filter-inquiry-portal' ),
			'sms_pattern_request_created'  => __( 'Pattern ID ثبت درخواست', 'filter-inquiry-portal' ),
			'sms_pattern_status_reviewing' => __( 'Pattern ID وضعیت در حال بررسی', 'filter-inquiry-portal' ),
			'sms_pattern_status_need_info' => __( 'Pattern ID وضعیت نیاز به اطلاعات بیشتر', 'filter-inquiry-portal' ),
			'sms_pattern_status_answered'  => __( 'Pattern ID وضعیت پاسخ داده شد', 'filter-inquiry-portal' ),
			'sms_pattern_status_rejected'  => __( 'Pattern ID وضعیت رد شد', 'filter-inquiry-portal' ),
			'sms_pattern_status_closed'    => __( 'Pattern ID وضعیت بسته شد', 'filter-inquiry-portal' ),
			'sms_test_mobile'              => __( 'شماره تست پیامک', 'filter-inquiry-portal' ),
		);

		foreach ( $sms_text_fields as $key => $label ) {
			$type = in_array( $key, array( 'sms_api_token', 'sms_password' ), true ) ? 'password' : 'text';
			add_settings_field( $key, $label, array( $this, 'render_text_field' ), 'fip_settings', 'fip_sms_settings', array( 'key' => $key, 'type' => $type ) );
		}
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
		$input    = is_array( $input ) ? $input : array();
		$defaults = $this->get_defaults();
		$output   = array();

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

		$output['sms_enabled'] = empty( $input['sms_enabled'] ) ? 0 : 1;
		$connection_type = isset( $input['sms_connection_type'] ) ? sanitize_key( wp_unslash( $input['sms_connection_type'] ) ) : 'rest_token';
		$output['sms_connection_type'] = in_array( $connection_type, array( 'rest_token', 'username_password' ), true ) ? $connection_type : 'rest_token';

		$sms_keys = array(
			'sms_api_token',
			'sms_username',
			'sms_password',
			'sms_sender',
			'sms_pattern_otp',
			'sms_pattern_request_created',
			'sms_pattern_status_reviewing',
			'sms_pattern_status_need_info',
			'sms_pattern_status_answered',
			'sms_pattern_status_rejected',
			'sms_pattern_status_closed',
			'sms_test_mobile',
		);

		foreach ( $sms_keys as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : '';
		}

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
			<p class="description"><?php echo esc_html__( 'در Phase 1 فقط تنظیمات پایه، نگاشت صفحات و شورت‌کدهای نمایشی فعال شده‌اند.', 'filter-inquiry-portal' ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'fip_settings_group' );
				do_settings_sections( 'fip_settings' );
				submit_button( __( 'ذخیره تنظیمات', 'filter-inquiry-portal' ) );
				?>
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
		echo '<p>' . esc_html__( 'در این فاز فقط تنظیمات پیامک ذخیره می‌شود و هیچ پیامکی ارسال نخواهد شد.', 'filter-inquiry-portal' ) . '</p>';
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
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
		if ( 'sms_test_mobile' === $key ) {
			echo '<p class="description">' . esc_html__( 'ارسال پیامک تست در فاز اتصال ملی‌پیامک فعال خواهد شد.', 'filter-inquiry-portal' ) . '</p>';
			echo '<button type="button" class="button" disabled="disabled">' . esc_html__( 'ارسال تست پیامک', 'filter-inquiry-portal' ) . '</button>';
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

	/**
	 * Renders SMS connection type select.
	 *
	 * @return void
	 */
	public function render_sms_connection_type_field() {
		$value   = $this->get_option( 'sms_connection_type', 'rest_token' );
		$options = array(
			'rest_token'        => __( 'REST Token', 'filter-inquiry-portal' ),
			'username_password' => __( 'Username / Password', 'filter-inquiry-portal' ),
		);
		?>
		<select id="fip_sms_connection_type" name="<?php echo esc_attr( self::OPTION_NAME . '[sms_connection_type]' ); ?>">
			<?php foreach ( $options as $option_value => $label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php echo esc_html__( 'نوع اتصال فقط ذخیره می‌شود؛ اتصال واقعی ملی‌پیامک در فاز بعدی انجام خواهد شد.', 'filter-inquiry-portal' ); ?></p>
		<?php
	}
}
