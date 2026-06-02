<?php
/**
 * SMS logging module.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'FIP_SMS_Logger', false ) ) {
	return;
}

/**
 * Stores SMS delivery attempts in a custom table.
 */
class FIP_SMS_Logger {

	const DB_VERSION = '1.0.0';

	/**
	 * Allowed log statuses.
	 *
	 * @var string[]
	 */
	private $allowed_statuses = array( 'success', 'failed', 'skipped' );

	/**
	 * Allowed SMS types.
	 *
	 * @var string[]
	 */
	private $allowed_types = array(
		'otp',
		'request_created',
		'status_reviewing',
		'status_need_info',
		'status_answered',
		'status_rejected',
		'status_closed',
		'admin_new_request',
		'test',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_create_table' ) );
	}

	/**
	 * Creates or updates the SMS logs table when needed.
	 *
	 * @return void
	 */
	public function maybe_create_table() {
		if ( get_option( 'fip_sms_logs_db_version' ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;

		$table_name      = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			mobile VARCHAR(20) NOT NULL,
			type VARCHAR(50) NOT NULL,
			request_id BIGINT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL,
			message TEXT NULL,
			provider_response LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY mobile (mobile),
			KEY type (type),
			KEY request_id (request_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'fip_sms_logs_db_version', self::DB_VERSION, false );
	}

	/**
	 * Writes one SMS log row.
	 *
	 * @param string $mobile            Recipient mobile.
	 * @param string $type              SMS type.
	 * @param string $status            success|failed|skipped.
	 * @param string $message           Human-readable message.
	 * @param mixed  $provider_response Provider response.
	 * @param int    $request_id        Related request ID.
	 * @param int    $user_id           Related user ID.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function log( $mobile, $type, $status, $message = '', $provider_response = null, $request_id = null, $user_id = null ) {
		global $wpdb;

		$type   = sanitize_key( $type );
		$status = sanitize_key( $status );

		if ( ! in_array( $type, $this->allowed_types, true ) ) {
			$type = 'otp';
		}

		if ( ! in_array( $status, $this->allowed_statuses, true ) ) {
			$status = 'failed';
		}

		$this->maybe_create_table();

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'mobile'            => substr( sanitize_text_field( (string) $mobile ), 0, 20 ),
				'type'              => $type,
				'request_id'        => $request_id ? absint( $request_id ) : null,
				'user_id'           => $user_id ? absint( $user_id ) : null,
				'status'            => $status,
				'message'           => sanitize_textarea_field( (string) $message ),
				'provider_response' => $this->encode_provider_response( $provider_response ),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? false : absint( $wpdb->insert_id );
	}

	/**
	 * Gets SMS log rows.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,object>
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			is_array( $args ) ? $args : array(),
			array(
				'limit'      => 50,
				'offset'     => 0,
				'mobile'     => '',
				'type'       => '',
				'status'     => '',
				'request_id' => 0,
				'user_id'    => 0,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['mobile'] ) {
			$where[]  = 'mobile = %s';
			$params[] = sanitize_text_field( (string) $args['mobile'] );
		}

		if ( '' !== $args['type'] ) {
			$where[]  = 'type = %s';
			$params[] = sanitize_key( $args['type'] );
		}

		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['request_id'] ) ) {
			$where[]  = 'request_id = %d';
			$params[] = absint( $args['request_id'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = absint( $args['user_id'] );
		}

		$limit    = max( 1, min( 200, absint( $args['limit'] ) ) );
		$offset   = max( 0, absint( $args['offset'] ) );
		$params[] = $limit;
		$params[] = $offset;

		$sql = 'SELECT * FROM ' . $this->get_table_name() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Returns the full SMS logs table name.
	 *
	 * @return string
	 */
	private function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'fip_sms_logs';
	}

	/**
	 * Encodes provider response safely for storage.
	 *
	 * @param mixed $provider_response Provider response.
	 * @return string|null
	 */
	private function encode_provider_response( $provider_response ) {
		if ( null === $provider_response ) {
			return null;
		}

		if ( is_scalar( $provider_response ) ) {
			return sanitize_textarea_field( (string) $provider_response );
		}

		$encoded = wp_json_encode( $provider_response );

		return false === $encoded ? null : $encoded;
	}
}
