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
        return $wpdb->update(
            $table,
            $args,
            ['id' => (int) $id],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }
}
