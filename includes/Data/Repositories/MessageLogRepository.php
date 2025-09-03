<?php

namespace Kerbcycle\QrCode\Data\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The message log repository.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Data\Repositories
 */
class MessageLogRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'kerbcycle_message_logs';
    }

    /**
     * Public helper to record a message log
     */
    public static function log_message($args)
    {
        global $wpdb;

        $defaults = [
            'type'     => '',
            'to'       => '',
            'subject'  => '',
            'body'     => '',
            'status'   => '',
            'provider' => '',
            'response' => '',
        ];
        $data = wp_parse_args($args, $defaults);

        $row = [
            'type'       => in_array($data['type'], ['sms', 'email'], true) ? $data['type'] : 'sms',
            'recipient'  => sanitize_text_field($data['to']),
            'subject'    => sanitize_text_field($data['subject']),
            'body'       => wp_kses_post($data['body']),
            'status'     => sanitize_text_field($data['status']),
            'provider'   => sanitize_text_field($data['provider']),
            'response'   => is_scalar($data['response']) ? wp_kses_post((string)$data['response']) : wp_json_encode($data['response']),
            'created_at' => current_time('mysql', true), // UTC
        ];

        $table = $wpdb->prefix . 'kerbcycle_message_logs';
        $wpdb->insert($table, $row, ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
    }

    /**
     * Get logs from the database.
     */
    public function get_logs($type, $search, $from, $to, $paged, $per_page)
    {
        global $wpdb;

        $where   = ['type = %s'];
        $params  = [$type];

        if ($search !== '') {
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(recipient LIKE %s OR subject LIKE %s OR body LIKE %s OR status LIKE %s OR provider LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($from) {
            $where[] = 'DATE(created_at) >= %s';
            $params[] = $from;
        }
        if ($to) {
            $where[] = 'DATE(created_at) <= %s';
            $params[] = $to;
        }

        $offset = ($paged - 1) * $per_page;
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Count logs in the database.
     */
    public function count_logs($type, $search, $from, $to)
    {
        global $wpdb;

        $where   = ['type = %s'];
        $params  = [$type];

        if ($search !== '') {
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(recipient LIKE %s OR subject LIKE %s OR body LIKE %s OR status LIKE %s OR provider LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($from) {
            $where[] = 'DATE(created_at) >= %s';
            $params[] = $from;
        }
        if ($to) {
            $where[] = 'DATE(created_at) <= %s';
            $params[] = $to;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $where);
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * Quick structural validation (are the expected columns present?)
     */
    public function table_is_valid()
    {
        global $wpdb;
        $expected = [
            'id', 'type', 'recipient', 'subject', 'body', 'status', 'provider', 'response', 'created_at'
        ];
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$this->table}", 0);
        if (empty($cols) || !is_array($cols)) return false;

        foreach ($expected as $c) {
            if (!in_array($c, $cols, true)) return false;
        }
        return true;
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

    public function clear_all()
    {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table}");
    }
}
