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

    public function assign($qr_code, $user_id)
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

    public function release($qr_code)
    {
        global $wpdb;
        $row = $this->find_by_qr_code($qr_code);
        if ($row) {
            return $wpdb->update(
                $this->table,
                [
                    'user_id'     => null,
                    'status'      => 'available',
                    'assigned_at' => null
                ],
                ['id' => $row->id],
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

    public function update($old_code, $new_code)
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

    public function get_available_codes()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT qr_code FROM $this->table WHERE status = 'available' ORDER BY id DESC");
    }

    public function get_all_codes()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT id, qr_code, user_id, status, assigned_at FROM $this->table ORDER BY id DESC");
    }
}
