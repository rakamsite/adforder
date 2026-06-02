<?php
/**
 * Customer inquiry request data model module.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_Requests', false ) ) {
	return;
}

/**
 * Registers and manages customer inquiry requests.
 */
class FIP_Requests {

	/** Request post type key. */
	const POST_TYPE = 'fip_request';

	/** Request number meta key. */
	const META_REQUEST_NUMBER = 'fip_request_number';

	/** User ID meta key. */
	const META_USER_ID = 'fip_user_id';

	/** User mobile meta key. */
	const META_USER_MOBILE = 'fip_user_mobile';

	/** Status meta key. */
	const META_STATUS = 'fip_status';

	/** Items meta key. */
	const META_ITEMS = 'fip_items';

	/** Items count meta key. */
	const META_ITEMS_COUNT = 'fip_items_count';

	/** General note meta key. */
	const META_GENERAL_NOTE = 'fip_general_note';

	/** Admin response meta key. */
	const META_ADMIN_RESPONSE = 'fip_admin_response';

	/** Internal note meta key. */
	const META_INTERNAL_NOTE = 'fip_internal_note';

	/** Customer updates meta key. */
	const META_CUSTOMER_UPDATES = 'fip_customer_updates';

	/** Last SMS status meta key. */
	const META_LAST_SMS_STATUS = 'fip_last_sms_status';

	/** Last SMS sent-at meta key. */
	const META_LAST_SMS_SENT_AT = 'fip_last_sms_sent_at';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
	}

	/**
	 * Registers the private request CPT.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'درخواست‌های استعلام', 'filter-inquiry-portal' ),
			'singular_name'      => __( 'درخواست استعلام', 'filter-inquiry-portal' ),
			'add_new'            => __( 'افزودن درخواست', 'filter-inquiry-portal' ),
			'add_new_item'       => __( 'افزودن درخواست', 'filter-inquiry-portal' ),
			'edit_item'          => __( 'ویرایش درخواست', 'filter-inquiry-portal' ),
			'view_item'          => __( 'مشاهده درخواست', 'filter-inquiry-portal' ),
			'all_items'          => __( 'درخواست‌های استعلام', 'filter-inquiry-portal' ),
			'search_items'       => __( 'جستجوی درخواست‌ها', 'filter-inquiry-portal' ),
			'not_found'          => __( 'درخواستی پیدا نشد.', 'filter-inquiry-portal' ),
			'not_found_in_trash' => __( 'درخواستی در زباله‌دان پیدا نشد.', 'filter-inquiry-portal' ),
			'menu_name'          => __( 'درخواست‌های استعلام', 'filter-inquiry-portal' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'fip_settings',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'menu_icon'       => 'dashicons-clipboard',
			'has_archive'     => false,
			'rewrite'         => false,
			'show_in_rest'    => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Returns allowed request statuses.
	 *
	 * @return array<string,string>
	 */
	public static function get_statuses() {
		return array(
			'pending'   => __( 'در انتظار بررسی', 'filter-inquiry-portal' ),
			'reviewing' => __( 'در حال بررسی', 'filter-inquiry-portal' ),
			'need_info' => __( 'نیاز به اطلاعات بیشتر', 'filter-inquiry-portal' ),
			'answered'  => __( 'پاسخ داده شد', 'filter-inquiry-portal' ),
			'rejected'  => __( 'رد شد', 'filter-inquiry-portal' ),
			'closed'    => __( 'بسته شد', 'filter-inquiry-portal' ),
		);
	}

	/**
	 * Returns all known request meta keys.
	 *
	 * @return array<string,string>
	 */
	public static function get_meta_keys() {
		return array(
			'request_number'   => self::META_REQUEST_NUMBER,
			'user_id'          => self::META_USER_ID,
			'user_mobile'      => self::META_USER_MOBILE,
			'status'           => self::META_STATUS,
			'items'            => self::META_ITEMS,
			'items_count'      => self::META_ITEMS_COUNT,
			'general_note'     => self::META_GENERAL_NOTE,
			'admin_response'   => self::META_ADMIN_RESPONSE,
			'internal_note'    => self::META_INTERNAL_NOTE,
			'customer_updates' => self::META_CUSTOMER_UPDATES,
			'last_sms_status'  => self::META_LAST_SMS_STATUS,
			'last_sms_sent_at' => self::META_LAST_SMS_SENT_AT,
		);
	}

	/**
	 * Generates a human-readable request number from a post ID.
	 *
	 * @param int $post_id Request post ID.
	 * @return string
	 */
	public static function generate_request_number( $post_id ) {
		return 'FQ-' . str_pad( (string) absint( $post_id ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Creates a request for internal use/tests.
	 *
	 * @param int    $user_id      Customer user ID.
	 * @param array  $items        Request items.
	 * @param string $general_note General note.
	 * @return int|WP_Error
	 */
	public static function create_request( $user_id, array $items = array(), $general_note = '' ) {
		$user_id = absint( $user_id );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return new WP_Error( 'fip_invalid_user', __( 'کاربر معتبر نیست.', 'filter-inquiry-portal' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => __( 'درخواست استعلام', 'filter-inquiry-portal' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id        = absint( $post_id );
		$request_number = self::generate_request_number( $post_id );
		$items          = self::sanitize_items( $items );
		$mobile         = sanitize_text_field( (string) get_user_meta( $user_id, 'fip_mobile', true ) );

		$updated_post_id = wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => sprintf( '%1$s - User #%2$d', $request_number, $user_id ),
			),
			true
		);

		if ( is_wp_error( $updated_post_id ) ) {
			return $updated_post_id;
		}

		update_post_meta( $post_id, self::META_REQUEST_NUMBER, $request_number );
		update_post_meta( $post_id, self::META_USER_ID, $user_id );
		update_post_meta( $post_id, self::META_USER_MOBILE, $mobile );
		update_post_meta( $post_id, self::META_STATUS, 'pending' );
		update_post_meta( $post_id, self::META_ITEMS, $items );
		update_post_meta( $post_id, self::META_ITEMS_COUNT, count( $items ) );
		update_post_meta( $post_id, self::META_GENERAL_NOTE, sanitize_textarea_field( $general_note ) );
		update_post_meta( $post_id, self::META_CUSTOMER_UPDATES, array() );

		return $post_id;
	}

	/**
	 * Gets a paginated query of requests for a customer.
	 *
	 * @param int $user_id  Customer user ID.
	 * @param int $paged    Page number.
	 * @param int $per_page Requests per page.
	 * @return WP_Query
	 */
	public static function get_user_requests( $user_id, $paged = 1, $per_page = 10 ) {
		return new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, absint( $per_page ) ),
				'paged'          => max( 1, absint( $paged ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'     => self::META_USER_ID,
						'value'   => absint( $user_id ),
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	}

	/**
	 * Gets structured request data.
	 *
	 * @param int $request_id Request post ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_request( $request_id ) {
		$post = get_post( absint( $request_id ) );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$status   = self::normalize_status( get_post_meta( $post->ID, self::META_STATUS, true ) );
		$statuses = self::get_statuses();
		$items    = get_post_meta( $post->ID, self::META_ITEMS, true );
		$updates  = get_post_meta( $post->ID, self::META_CUSTOMER_UPDATES, true );

		if ( ! is_array( $items ) ) {
			$items = array();
		}

		if ( ! is_array( $updates ) ) {
			$updates = array();
		}

		return array(
			'id'               => absint( $post->ID ),
			'request_number'   => (string) get_post_meta( $post->ID, self::META_REQUEST_NUMBER, true ),
			'user_id'          => absint( get_post_meta( $post->ID, self::META_USER_ID, true ) ),
			'user_mobile'      => (string) get_post_meta( $post->ID, self::META_USER_MOBILE, true ),
			'status'           => $status,
			'status_label'     => isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status,
			'items'            => $items,
			'items_count'      => absint( get_post_meta( $post->ID, self::META_ITEMS_COUNT, true ) ),
			'general_note'     => (string) get_post_meta( $post->ID, self::META_GENERAL_NOTE, true ),
			'admin_response'   => (string) get_post_meta( $post->ID, self::META_ADMIN_RESPONSE, true ),
			'internal_note'    => (string) get_post_meta( $post->ID, self::META_INTERNAL_NOTE, true ),
			'customer_updates' => $updates,
			'created_at'       => $post->post_date,
			'updated_at'       => $post->post_modified,
		);
	}

	/**
	 * Checks whether a user can view a request.
	 *
	 * @param int $user_id    User ID.
	 * @param int $request_id Request post ID.
	 * @return bool
	 */
	public static function user_can_view_request( $user_id, $request_id ) {
		$user_id = absint( $user_id );
		$post    = get_post( absint( $request_id ) );

		if ( ! $user_id || ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$request_user_id = absint( get_post_meta( $post->ID, self::META_USER_ID, true ) );

		return $request_user_id === $user_id;
	}

	/**
	 * Gets customer updates for a request.
	 *
	 * @param int $request_id Request post ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_customer_updates( $request_id ) {
		$updates = get_post_meta( absint( $request_id ), self::META_CUSTOMER_UPDATES, true );

		return is_array( $updates ) ? $updates : array();
	}

	/**
	 * Adds a customer update to a request.
	 *
	 * @param int    $request_id Request post ID.
	 * @param int    $user_id    Customer user ID.
	 * @param string $note       Update note.
	 * @param int    $image_id   Attachment ID.
	 * @return true|WP_Error
	 */
	public static function add_customer_update( $request_id, $user_id, $note = '', $image_id = 0 ) {
		$request_id = absint( $request_id );
		$user_id    = absint( $user_id );

		if ( ! self::user_can_view_request( $user_id, $request_id ) ) {
			return new WP_Error( 'fip_request_forbidden', __( 'امکان مشاهده یا به‌روزرسانی این درخواست وجود ندارد.', 'filter-inquiry-portal' ) );
		}

		$updates     = self::get_customer_updates( $request_id );
		$max_updates = self::get_max_customer_updates();

		if ( $max_updates > 0 && count( $updates ) >= $max_updates ) {
			return new WP_Error( 'fip_max_customer_updates_reached', __( 'حداکثر تعداد به‌روزرسانی‌های مشتری ثبت شده است.', 'filter-inquiry-portal' ) );
		}

		$updates[] = array(
			'id'         => uniqid( 'upd_', true ),
			'user_id'    => $user_id,
			'note'       => sanitize_textarea_field( $note ),
			'image_id'   => absint( $image_id ),
			'created_at' => current_time( 'mysql' ),
		);

		update_post_meta( $request_id, self::META_CUSTOMER_UPDATES, $updates );

		if ( 'need_info' === self::normalize_status( get_post_meta( $request_id, self::META_STATUS, true ) ) ) {
			update_post_meta( $request_id, self::META_STATUS, 'reviewing' );
		}

		return true;
	}

	/**
	 * Replaces admin list table columns for requests.
	 *
	 * @param array<string,string> $columns Current columns.
	 * @return array<string,string>
	 */
	public static function filter_admin_columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : __( 'تاریخ', 'filter-inquiry-portal' );

		return array(
			'cb'                 => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'title'              => __( 'عنوان', 'filter-inquiry-portal' ),
			'request_number'     => __( 'شماره درخواست', 'filter-inquiry-portal' ),
			'request_user'       => __( 'کاربر', 'filter-inquiry-portal' ),
			'user_mobile'        => __( 'موبایل', 'filter-inquiry-portal' ),
			'request_status'     => __( 'وضعیت', 'filter-inquiry-portal' ),
			'request_item_count' => __( 'تعداد آیتم‌ها', 'filter-inquiry-portal' ),
			'date'               => $date,
		);
	}

	/**
	 * Renders request custom admin column output.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_admin_column( $column, $post_id ) {
		switch ( $column ) {
			case 'request_number':
				echo esc_html( get_post_meta( $post_id, self::META_REQUEST_NUMBER, true ) );
				break;

			case 'request_user':
				$user_id = absint( get_post_meta( $post_id, self::META_USER_ID, true ) );
				$user    = $user_id ? get_userdata( $user_id ) : false;

				if ( $user ) {
					echo esc_html( sprintf( '%1$s (#%2$d)', $user->display_name, $user_id ) );
				} elseif ( $user_id ) {
					echo esc_html( sprintf( 'User #%d', $user_id ) );
				} else {
					echo '&mdash;';
				}
				break;

			case 'user_mobile':
				echo esc_html( get_post_meta( $post_id, self::META_USER_MOBILE, true ) );
				break;

			case 'request_status':
				$status   = self::normalize_status( get_post_meta( $post_id, self::META_STATUS, true ) );
				$statuses = self::get_statuses();
				echo esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status );
				break;

			case 'request_item_count':
				echo esc_html( absint( get_post_meta( $post_id, self::META_ITEMS_COUNT, true ) ) );
				break;
		}
	}

	/**
	 * Sanitizes request items recursively for safe storage.
	 *
	 * @param array $items Raw items.
	 * @return array
	 */
	private static function sanitize_items( array $items ) {
		$sanitized = array();

		foreach ( $items as $key => $value ) {
			$item_key = is_string( $key ) ? sanitize_key( $key ) : absint( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $item_key ] = self::sanitize_items( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $item_key ] = 0 + $value;
			} else {
				$sanitized[ $item_key ] = sanitize_text_field( (string) $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Normalizes a status value against allowed statuses.
	 *
	 * @param mixed $status Raw status.
	 * @return string
	 */
	private static function normalize_status( $status ) {
		$status   = sanitize_key( (string) $status );
		$statuses = self::get_statuses();

		return isset( $statuses[ $status ] ) ? $status : 'pending';
	}

	/**
	 * Gets configured max customer updates.
	 *
	 * @return int
	 */
	private static function get_max_customer_updates() {
		$settings = get_option( FIP_Settings::OPTION_NAME, array() );

		if ( is_array( $settings ) && array_key_exists( 'max_customer_updates', $settings ) ) {
			return absint( $settings['max_customer_updates'] );
		}

		return 5;
	}
}
