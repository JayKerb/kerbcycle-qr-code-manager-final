<?php

namespace Kerbcycle\QrCode\Data\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

class PickupExceptionRepository
{
    public static function create(array $args)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $inserted = $wpdb->insert($table, $args, [
            '%s', // qr_code
            '%d', // customer_id
            '%s', // issue
            '%s', // notes
            '%s', // submitted_at
            '%d', // webhook_sent
            '%s', // status
            '%d', // retry_count
            '%s', // last_retry_at
            '%s', // created_at
            '%s', // updated_at
        ]);

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public static function update_result($id, array $args)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $format_map = [
            'webhook_sent' => '%d',
            'webhook_status_code' => '%d',
            'status' => '%s',
            'webhook_response_body' => '%s',
            'ai_severity' => '%s',
            'ai_category' => '%s',
            'ai_summary' => '%s',
            'ai_recommended_action' => '%s',
            'retry_count' => '%d',
            'last_retry_at' => '%s',
            'updated_at' => '%s',
        ];
        $formats = [];
        foreach ($args as $key => $value) {
            $formats[] = isset($format_map[$key]) ? $format_map[$key] : '%s';
        }

        return $wpdb->update(
            $table,
            $args,
            ['id' => (int) $id],
            $formats,
            ['%d']
        );
    }
}
