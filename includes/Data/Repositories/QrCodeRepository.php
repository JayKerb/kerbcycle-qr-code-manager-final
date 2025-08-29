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

    public function insert_assigned($qr_code, $user_id)
    {
        global $wpdb;
        return $wpdb->insert(
            $this->table,
            [
                'qr_code'     => $qr_code,
                'user_id'     => $user_id,
                'status'      => 'assigned',
                'assigned_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s']
        );
    }

    public function get_latest_assigned($qr_code)
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id FROM {$this->table} WHERE qr_code = %s ORDER BY id DESC LIMIT 1",
                $qr_code
            )
        );
    }

    public function release_by_id($id)
    {
        global $wpdb;
        return $wpdb->update(
            $this->table,
            [
                'user_id'     => null,
                'status'      => 'available',
                'assigned_at' => null,
            ],
            ['id' => $id],
            ['%d', '%s', '%s'],
            ['%d']
        );
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

    public function find_by_code($qr_code)
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE qr_code = %s",
                $qr_code
            )
        );
    }

    public function bulk_release(array $codes)
    {
        global $wpdb;
        if (empty($codes)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($codes), '%s'));
        $sql = "UPDATE {$this->table} SET user_id = NULL, status = 'available', assigned_at = NULL WHERE qr_code IN ($placeholders)";
        return $wpdb->query($wpdb->prepare($sql, $codes));
    }
}
