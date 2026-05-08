<?php

namespace Kerbcycle\QrCode\Data\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The message log repository.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Data\Repositories
 */
class MessageLogRepository {
	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'kerbcycle_message_logs';
	}

	/**
	 * Public helper to record a message log
	 */
	public static function log_message( $args ) {
		global $wpdb;

		$defaults = array(
			'type'     => '',
			'to'       => '',
			'subject'  => '',
			'body'     => '',
			'status'   => '',
			'provider' => '',
			'response' => '',
		);
		$data     = wp_parse_args( $args, $defaults );

		$row = array(
			'type'       => in_array( $data['type'], array( 'sms', 'email' ), true ) ? $data['type'] : 'sms',
			'recipient'  => sanitize_text_field( $data['to'] ),
			'subject'    => sanitize_text_field( $data['subject'] ),
			'body'       => wp_kses_post( $data['body'] ),
			'status'     => sanitize_text_field( $data['status'] ),
			'provider'   => sanitize_text_field( $data['provider'] ),
			'response'   => is_scalar( $data['response'] ) ? wp_kses_post( (string) $data['response'] ) : wp_json_encode( $data['response'] ),
			'created_at' => current_time( 'mysql', true ), // UTC.
		);

		$table = $wpdb->prefix . 'kerbcycle_message_logs';
		$wpdb->insert( $table, $row, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	/**
	 * Get logs from the database.
	 */
	public function get_logs( $type, $search, $from, $to, $paged, $per_page ) {
		global $wpdb;

		$where  = array( 'type = %s' );
		$params = array( $type );

		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(recipient LIKE %s OR subject LIKE %s OR body LIKE %s OR status LIKE %s OR provider LIKE %s)';
			array_push( $params, $like, $like, $like, $like, $like );
		}
		if ( $from ) {
			$where[]  = 'DATE(created_at) >= %s';
			$params[] = $from;
		}
		if ( $to ) {
			$where[]  = 'DATE(created_at) <= %s';
			$params[] = $to;
		}

		$offset   = ( $paged - 1 ) * $per_page;
		$sql      = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed fragments and internally derived table name; dynamic values are prepared before execution.
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Count logs in the database.
	 */
	public function count_logs( $type, $search, $from, $to ) {
		global $wpdb;

		$where  = array( 'type = %s' );
		$params = array( $type );

		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(recipient LIKE %s OR subject LIKE %s OR body LIKE %s OR status LIKE %s OR provider LIKE %s)';
			array_push( $params, $like, $like, $like, $like, $like );
		}
		if ( $from ) {
			$where[]  = 'DATE(created_at) >= %s';
			$params[] = $from;
		}
		if ( $to ) {
			$where[]  = 'DATE(created_at) <= %s';
			$params[] = $to;
		}

		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed fragments and internally derived table name; dynamic values are prepared before execution.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Quick structural validation (are the expected columns present?)
	 */
	public function table_is_valid() {
		global $wpdb;
		$expected = array(
			'id',
			'type',
			'recipient',
			'subject',
			'body',
			'status',
			'provider',
			'response',
			'created_at',
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from the WordPress table prefix and fixed plugin table suffix; query has no user-supplied SQL fragments.
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$this->table}", 0 );
		if ( empty( $cols ) || ! is_array( $cols ) ) {
			return false;
		}

		foreach ( $expected as $c ) {
			if ( ! in_array( $c, $cols, true ) ) {
				return false;
			}
		}
		return true;
	}

	public function delete_by_ids( array $ids ) {
		if ( empty( $ids ) ) {
			return 0;
		}

		global $wpdb;
		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "DELETE FROM {$this->table} WHERE id IN ($placeholders)";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name is internally derived and IDs are absint-normalized then passed to prepare with generated placeholders.
		return $wpdb->query( $wpdb->prepare( $sql, $ids ) );
	}
}
