<?php

namespace Kerbcycle\QrCode\Data\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The qr code repository.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Data\Repositories
 */
class QrCodeRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'kerbcycle_qr_codes';
    }

    public function insert_available($qr_code)
    {
        global $wpdb;
        return $wpdb->insert(
            $this->table,
            [
                'qr_code' => $qr_code,
                'status'  => 'available',
            ],
            ['%s', '%s']
        );
    }

    public function insert_assigned($qr_code, $user_id)
    {
        global $wpdb;
        return $wpdb->insert(
            $this->table,
            [
                'qr_code'     => $qr_code,
                'user_id'     => $user_id,
                'status'      => 'assigned',
                'assigned_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s']
        );
    }

    public function release_latest_assigned($qr_code)
    {
        global $wpdb;
        $latest_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $this->table WHERE qr_code = %s AND status = 'assigned' ORDER BY id DESC LIMIT 1",
                $qr_code
            )
        );

        if ($latest_id) {
            return $wpdb->update(
                $this->table,
                [
                    'user_id'     => null,
                    'status'      => 'available',
                    'assigned_at' => null,
                ],
                ['id' => $latest_id],
                ['%d', '%s', '%s'],
                ['%d']
            );
        }
        return false;
    }

    public function bulk_release(array $codes)
    {
        global $wpdb;
        $released_count = 0;
        foreach ($codes as $code) {
            $latest_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $this->table WHERE qr_code = %s AND status = 'assigned' ORDER BY id DESC LIMIT 1",
                    $code
                )
            );

            if ($latest_id) {
                $result = $wpdb->update(
                    $this->table,
                    [
                        'user_id' => null,
                        'status' => 'available',
                        'assigned_at' => null,
                    ],
                    ['id' => $latest_id],
                    ['%d', '%s', '%s'],
                    ['%d']
                );

                if ($result !== false) {
                    $released_count += $result;
                }
            }
        }
        return $released_count;
    }

    public function update_code($old_code, $new_code)
    {
        global $wpdb;
        return $wpdb->update(
            $this->table,
            ['qr_code' => $new_code],
            ['qr_code' => $old_code],
            ['%s'],
            ['%s']
        );
    }

    public function find_by_qr_code($qr_code)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table WHERE qr_code = %s ORDER BY id DESC LIMIT 1", $qr_code));
    }

    public function list_available()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT qr_code FROM $this->table WHERE status = 'available' ORDER BY id DESC");
    }

    public function list_all()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT id, qr_code, user_id, status, assigned_at FROM $this->table ORDER BY id DESC");
    }

    public function recent_history($limit)
    {
        global $wpdb;
        $limit = absint($limit);
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $this->table ORDER BY assigned_at DESC LIMIT %d", $limit));
    }

    // Legacy wrappers
    public function assign($qr_code, $user_id)
    {
        return $this->insert_assigned($qr_code, $user_id);
    }

    public function release($qr_code)
    {
        return $this->release_latest_assigned($qr_code);
    }

    public function update($old_code, $new_code)
    {
        return $this->update_code($old_code, $new_code);
    }

    public function get_available_codes()
    {
        return $this->list_available();
    }

    public function get_all_codes()
    {
        return $this->list_all();
    }
}
