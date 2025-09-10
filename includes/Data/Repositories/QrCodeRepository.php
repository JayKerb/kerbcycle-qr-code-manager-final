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
    private $history;

    public function __construct()
    {
        global $wpdb;
        $this->table   = $wpdb->prefix . 'kerbcycle_qr_codes';
        $this->history = new QrCodeHistoryRepository();
    }

    public function insert_available($qr_code)
    {
        global $wpdb;
        $result = $wpdb->insert(
            $this->table,
            [
                'qr_code' => $qr_code,
                'status'  => 'available',
            ],
            ['%s', '%s']
        );

        if ($result !== false) {
            $this->history->log($qr_code, null, 'added');
        }

        return $result;
    }

    public function update_available_to_assigned($qr_code, $user_id, $display_name)
    {
        global $wpdb;
        $result = $wpdb->update(
            $this->table,
            [
                'user_id'     => $user_id,
                'display_name' => $display_name,
                'status'      => 'assigned',
                'assigned_at' => current_time('mysql')
            ],
            [
                'qr_code' => $qr_code,
                'status'  => 'available'
            ],
            ['%d', '%s', '%s', '%s'],
            ['%s', '%s']
        );

        if ($result !== false) {
            $this->history->log($qr_code, $user_id, 'assigned');
        }

        return $result;
    }

    public function insert_assigned($qr_code, $user_id, $display_name)
    {
        global $wpdb;
        $result = $wpdb->insert(
            $this->table,
            [
                'qr_code'     => $qr_code,
                'user_id'     => $user_id,
                'display_name' => $display_name,
                'status'      => 'assigned',
                'assigned_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s']
        );

        if ($result !== false) {
            $this->history->log($qr_code, $user_id, 'assigned');
        }

        return $result;
    }

    public function release_latest_assigned($qr_code)
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id FROM $this->table WHERE qr_code = %s AND status = 'assigned' ORDER BY id DESC LIMIT 1",
                $qr_code
            )
        );

        if ($row) {
            $result = $wpdb->update(
                $this->table,
                [
                    'user_id'      => null,
                    'status'       => 'available',
                    'assigned_at'  => null,
                    'display_name' => null,
                ],
                ['id' => $row->id],
                ['%d', '%s', '%s', '%s'],
                ['%d']
            );

            if ($result !== false) {
                $this->history->log($qr_code, $row->user_id, 'released');
            }

            return $result;
        }
        return false;
    }

    public function bulk_release(array $codes)
    {
        global $wpdb;
        $released_count = 0;
        foreach ($codes as $code) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, user_id FROM $this->table WHERE qr_code = %s AND status = 'assigned' ORDER BY id DESC LIMIT 1",
                    $code
                )
            );

            if ($row) {
                $result = $wpdb->update(
                    $this->table,
                    [
                        'user_id'      => null,
                        'status'       => 'available',
                        'assigned_at'  => null,
                        'display_name' => null,
                    ],
                    ['id' => $row->id],
                    ['%d', '%s', '%s', '%s'],
                    ['%d']
                );

                if ($result !== false) {
                    $this->history->log($code, $row->user_id, 'released');
                    $released_count += $result;
                }
            }
        }
        return $released_count;
    }

    public function bulk_delete_available(array $codes)
    {
        global $wpdb;
        if (empty($codes)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($codes), '%s'));
        $query = $wpdb->prepare(
            "SELECT qr_code FROM {$this->table} WHERE status = 'available' AND qr_code IN ($placeholders)",
            $codes
        );
        $existing = $wpdb->get_col($query);

        if (empty($existing)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($existing), '%s'));
        $delete_query = $wpdb->prepare(
            "DELETE FROM {$this->table} WHERE status = 'available' AND qr_code IN ($placeholders)",
            $existing
        );
        $deleted_count = $wpdb->query($delete_query);

        if ($deleted_count) {
            foreach ($existing as $code) {
                $this->history->log($code, null, 'deleted');
            }
        }

        return (int) $deleted_count;
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

    public function available_exists($qr_code)
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $this->table WHERE qr_code = %s AND status = 'available'",
            $qr_code
        ));
        return $count > 0;
    }

    public function list_available()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT qr_code FROM $this->table WHERE status = 'available' ORDER BY id DESC");
    }

    public function list_assigned_by_user($user_id)
    {
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT qr_code FROM {$this->table} WHERE status = 'assigned' AND user_id = %d ORDER BY id DESC",
                $user_id
            )
        );
    }

    public function list_all()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT id, qr_code, user_id, display_name, status, assigned_at FROM $this->table ORDER BY id DESC");
    }

    public function recent_history($limit)
    {
        return $this->history->recent($limit);
    }

    // Legacy wrappers
    public function assign($qr_code, $user_id, $display_name = '')
    {
        return $this->insert_assigned($qr_code, $user_id, $display_name);
    }

    public function add($qr_code)
    {
        return $this->insert_available($qr_code);
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

    public function get_assigned_codes_by_user($user_id)
    {
        return $this->list_assigned_by_user($user_id);
    }

    public function get_all_codes()
    {
        return $this->list_all();
    }
}
