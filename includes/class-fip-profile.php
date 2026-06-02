<?php
/**
 * Customer profile module.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_Profile', false ) ) {
	return;
}

/**
 * Handles customer profile completion and editing.
 */
class FIP_Profile {

	/**
	 * Profile meta keys keyed by form field name.
	 *
	 * @var array<string,string>
	 */
	private $meta_keys = array(
		'first_name'     => 'fip_first_name',
		'last_name'      => 'fip_last_name',
		'company'        => 'fip_company',
		'activity_field' => 'fip_activity_field',
		'position'       => 'fip_position',
		'province'       => 'fip_province',
		'city'           => 'fip_city',
		'birth_date'     => 'fip_birth_date',
	);

	/**
	 * Cached province/city list.
	 *
	 * @var array<string,string[]>|null
	 */
	private $cities = null;

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Gets a user's profile data.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	public function get_profile( $user_id ) {
		$user_id = absint( $user_id );
		$profile = array();

		foreach ( $this->meta_keys as $field => $meta_key ) {
			$profile[ $field ] = $user_id ? (string) get_user_meta( $user_id, $meta_key, true ) : '';
		}

		$profile['mobile']            = $user_id ? (string) get_user_meta( $user_id, 'fip_mobile', true ) : '';
		$profile['profile_completed'] = $this->is_profile_completed( $user_id );

		if ( $user_id && '' === $profile['first_name'] ) {
			$profile['first_name'] = (string) get_user_meta( $user_id, 'first_name', true );
		}

		if ( $user_id && '' === $profile['last_name'] ) {
			$profile['last_name'] = (string) get_user_meta( $user_id, 'last_name', true );
		}

		return $profile;
	}

	/**
	 * Checks whether the user has completed the required profile fields.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_profile_completed( $user_id ) {
		$user_id = absint( $user_id );

		return $user_id > 0 && '1' === (string) get_user_meta( $user_id, 'fip_profile_completed', true );
	}

	/**
	 * Saves profile data after sanitization and validation.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $data    Raw profile data.
	 * @return true|WP_Error
	 */
	public function save_profile( $user_id, array $data ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return new WP_Error( 'fip_invalid_user', __( 'کاربر معتبر نیست.', 'filter-inquiry-portal' ) );
		}

		$validated = $this->validate_profile_data( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		foreach ( $this->meta_keys as $field => $meta_key ) {
			update_user_meta( $user_id, $meta_key, $validated[ $field ] );
		}

		update_user_meta( $user_id, 'fip_profile_completed', '1' );
		update_user_meta( $user_id, 'first_name', $validated['first_name'] );
		update_user_meta( $user_id, 'last_name', $validated['last_name'] );

		$user_update = wp_update_user(
			array(
				'ID'         => $user_id,
				'first_name' => $validated['first_name'],
				'last_name'  => $validated['last_name'],
			)
		);

		if ( is_wp_error( $user_update ) ) {
			return $user_update;
		}

		return true;
	}

	/**
	 * Gets required form fields.
	 *
	 * @return string[]
	 */
	public function get_required_fields() {
		return array( 'first_name', 'last_name', 'activity_field', 'province', 'city' );
	}

	/**
	 * Gets all provinces.
	 *
	 * @return string[]
	 */
	public function get_provinces() {
		return array_keys( $this->get_city_data() );
	}

	/**
	 * Gets cities for a province.
	 *
	 * @param string $province Province name.
	 * @return string[]
	 */
	public function get_cities_by_province( $province ) {
		$province = is_scalar( $province ) ? sanitize_text_field( wp_unslash( $province ) ) : '';
		$cities   = $this->get_city_data();

		return isset( $cities[ $province ] ) ? $cities[ $province ] : array();
	}

	/**
	 * Gets all province/city data.
	 *
	 * @return array<string,string[]>
	 */
	public function get_city_data() {
		if ( null !== $this->cities ) {
			return $this->cities;
		}

		$cities_file = FILTER_INQUIRY_PORTAL_PLUGIN_DIR . 'includes/data/iran-cities.php';
		$cities      = file_exists( $cities_file ) ? include $cities_file : array();
		$this->cities = is_array( $cities ) ? $cities : array();

		return $this->cities;
	}

	/**
	 * Gets activity fields from settings, with defaults as fallback.
	 *
	 * @return string[]
	 */
	public function get_activity_fields() {
		$settings = fip_plugin()->get_module( 'settings' );
		$fields   = array();

		if ( $settings && method_exists( $settings, 'get_activity_fields' ) ) {
			$fields = $settings->get_activity_fields();
		}

		if ( empty( $fields ) ) {
			$fields = array(
				'تولیدکننده ماشین‌آلات سنگین',
				'راه‌آهن و ریلی',
				'نفت و پتروشیمی',
				'کمپرسور و ژنراتور',
				'معادن',
			);
		}

		return array_values( array_unique( array_map( 'trim', $fields ) ) );
	}

	/**
	 * Sanitizes and validates profile data.
	 *
	 * @param array<string,mixed> $data Raw profile data.
	 * @return array<string,string>|WP_Error
	 */
	public function validate_profile_data( array $data ) {
		$clean = array();

		foreach ( array_keys( $this->meta_keys ) as $field ) {
			$value           = isset( $data[ $field ] ) && is_scalar( $data[ $field ] ) ? $data[ $field ] : '';
			$clean[ $field ] = sanitize_text_field( wp_unslash( $value ) );
		}

		$labels = array(
			'first_name'     => __( 'نام', 'filter-inquiry-portal' ),
			'last_name'      => __( 'نام خانوادگی', 'filter-inquiry-portal' ),
			'activity_field' => __( 'حوزه فعالیت', 'filter-inquiry-portal' ),
			'province'       => __( 'استان', 'filter-inquiry-portal' ),
			'city'           => __( 'شهر', 'filter-inquiry-portal' ),
		);

		$errors = new WP_Error();
		foreach ( $this->get_required_fields() as $field ) {
			if ( '' === $clean[ $field ] ) {
				$errors->add( 'fip_required_' . $field, sprintf( __( 'فیلد «%s» الزامی است.', 'filter-inquiry-portal' ), $labels[ $field ] ) );
			}
		}

		$cities = $this->get_city_data();
		if ( '' !== $clean['province'] && ! isset( $cities[ $clean['province'] ] ) ) {
			$errors->add( 'fip_invalid_province', __( 'استان انتخاب‌شده معتبر نیست.', 'filter-inquiry-portal' ) );
		}

		if ( '' !== $clean['province'] && isset( $cities[ $clean['province'] ] ) && '' !== $clean['city'] && ! in_array( $clean['city'], $cities[ $clean['province'] ], true ) ) {
			$errors->add( 'fip_invalid_city', __( 'شهر انتخاب‌شده با استان انتخاب‌شده مطابقت ندارد.', 'filter-inquiry-portal' ) );
		}

		$activity_fields = $this->get_activity_fields();
		if ( '' !== $clean['activity_field'] && ! in_array( $clean['activity_field'], $activity_fields, true ) ) {
			$errors->add( 'fip_invalid_activity_field', __( 'حوزه فعالیت انتخاب‌شده معتبر نیست.', 'filter-inquiry-portal' ) );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return $clean;
	}

	/**
	 * Handles a submitted profile form for the current user.
	 *
	 * @param string $context Form context.
	 * @return array<string,mixed>
	 */
	public function handle_profile_submission( $context ) {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return array( 'submitted' => false );
		}

		$action = isset( $_POST['fip_profile_action'] ) ? sanitize_key( wp_unslash( $_POST['fip_profile_action'] ) ) : '';
		if ( 'save_profile' !== $action ) {
			return array( 'submitted' => false );
		}

		$posted_context = isset( $_POST['fip_profile_context'] ) ? sanitize_key( wp_unslash( $_POST['fip_profile_context'] ) ) : '';
		if ( sanitize_key( $context ) !== $posted_context ) {
			return array( 'submitted' => false );
		}

		$nonce = isset( $_POST['fip_profile_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fip_profile_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'fip_profile_nonce' ) ) {
			return array(
				'submitted' => true,
				'success'   => false,
				'errors'    => array( __( 'اعتبار فرم منقضی شده است. لطفاً دوباره تلاش کنید.', 'filter-inquiry-portal' ) ),
			);
		}

		$result = $this->save_profile( get_current_user_id(), $_POST );
		if ( is_wp_error( $result ) ) {
			return array(
				'submitted' => true,
				'success'   => false,
				'errors'    => $result->get_error_messages(),
			);
		}

		return array(
			'submitted' => true,
			'success'   => true,
			'errors'    => array(),
		);
	}
}
