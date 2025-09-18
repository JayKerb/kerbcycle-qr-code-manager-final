<?php

namespace Kerbcycle\QrCode\Data\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for storing messages, alerts and notifications that occur within the plugin.
 *
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Data\Repositories
 */
class ErrorLogRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'kerbcycle_error_logs';
    }

    /**
     * Record a log entry for any message, alert or notification.
     *
     * @param array $args
     * @return void
     */
    public static function log($args)
    {
        global $wpdb;

        $defaults = [
            'type'    => '',
            'message' => '',
            'page'    => '',
            'status'  => '',
        ];
        $data = wp_parse_args($args, $defaults);

        $row = [
            'type'       => sanitize_text_field($data['type']),
            'message'    => wp_kses_post($data['message']),
            'page'       => sanitize_text_field($data['page']),
            'status'     => sanitize_text_field($data['status']),
            'created_at' => current_time('mysql', true), // UTC
        ];

        $table = $wpdb->prefix . 'kerbcycle_error_logs';
        $wpdb->insert($table, $row, ['%s', '%s', '%s', '%s', '%s']);
    }

    /**
     * Backwards compatibility for previous log_error calls.
     *
     * @param array $args
     * @return void
     */
    public static function log_error($args)
    {
        self::log($args);
    }

    /**
     * Retrieve logs from database.
     */
    public function get_logs($search, $status, $page, $paged, $per_page)
    {
        global $wpdb;

        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (type LIKE %s OR message LIKE %s OR page LIKE %s OR status LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where .= ' AND status = %s';
            $params[] = $status;
        }
        if ($page !== '') {
            $where .= ' AND page = %s';
            $params[] = $page;
        }

        $offset = ($paged - 1) * $per_page;
        $sql    = "SELECT * FROM {$this->table} WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Count logs in database.
     */
    public function count_logs($search, $status, $page)
    {
        global $wpdb;

        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (type LIKE %s OR message LIKE %s OR page LIKE %s OR status LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where .= ' AND status = %s';
            $params[] = $status;
        }
        if ($page !== '') {
            $where .= ' AND page = %s';
            $params[] = $page;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE $where";
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public function delete_by_ids(array $ids)
    {
        if (empty($ids)) {
            return 0;
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return $wpdb->query($wpdb->prepare("DELETE FROM {$this->table} WHERE id IN ($placeholders)", $ids));
    }

    public function table_is_valid()
    {
        global $wpdb;
        $expected = ['id', 'type', 'message', 'page', 'status', 'created_at'];
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$this->table}", 0);
        if (empty($cols) || !is_array($cols)) {
            return false;
        }
        foreach ($expected as $c) {
            if (!in_array($c, $cols, true)) {
                return false;
            }
        }
        return true;
    }

    public function clear_all()
    {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table}");
    }

    /**
     * Get list of distinct pages recorded in the log.
     *
     * @return array
     */
    public function get_pages()
    {
        global $wpdb;
        return $wpdb->get_col("SELECT DISTINCT page FROM {$this->table} WHERE page <> '' ORDER BY page ASC");
    }
}
