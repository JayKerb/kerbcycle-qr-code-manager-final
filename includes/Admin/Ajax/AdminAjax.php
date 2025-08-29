<?php

namespace Kerbcycle\QrCode\Admin\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Services\ReportService;

/**
 * The admin ajax.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Ajax
 */
class AdminAjax
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_action('wp_ajax_assign_qr_code', [$this, 'assign_qr_code']);
        add_action('wp_ajax_release_qr_code', [$this, 'release_qr_code']);
        add_action('wp_ajax_bulk_release_qr_codes', [$this, 'bulk_release_qr_codes']);
        add_action('wp_ajax_update_qr_code', [$this, 'update_qr_code']);
        add_action('wp_ajax_kerbcycle_qr_report_data', [$this, 'ajax_report_data']);
    }

    // Note: The actual logic for these methods will be moved to services later.
    // For now, I'm just moving the methods as is.

    public function assign_qr_code()
    {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        global $wpdb;
        $qr_code      = sanitize_text_field($_POST['qr_code']);
        $user_id      = intval($_POST['customer_id']);
        $send_email   = !empty($_POST['send_email']) && get_option('kerbcycle_qr_enable_email', 1);
        $send_sms     = !empty($_POST['send_sms']) && get_option('kerbcycle_qr_enable_sms', 0);
        $send_reminder = !empty($_POST['send_reminder']) && get_option('kerbcycle_qr_enable_reminders', 0);
        $table        = $wpdb->prefix . 'kerbcycle_qr_codes';

        $result = $wpdb->insert(
            $table,
            [
                'qr_code'     => $qr_code,
                'user_id'     => $user_id,
                'status'      => 'assigned',
                'assigned_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s']
        );

        if ($result !== false) {
            if ($send_email) {
                (new \Kerbcycle\QrCode\Services\EmailService())->send_notification($user_id, $qr_code, 'assigned');
            }
            $sms_result = null;
            if ($send_sms) {
                $sms_result = (new \Kerbcycle\QrCode\Services\SmsService())->send_notification($user_id, $qr_code, 'assigned');
            }
            // if ($send_reminder) {
            //     $this->schedule_reminder($user_id, $qr_code);
            // }

            $response = [
                'message' => 'QR code assigned successfully',
                'qr_code' => $qr_code,
                'user_id' => $user_id,
            ];
            if ($send_sms) {
                $response['sms_sent'] = ($sms_result === true);
                if ($sms_result !== true) {
                    $response['sms_error'] = is_wp_error($sms_result) ? $sms_result->get_error_message() : __('Unknown error', 'kerbcycle');
                }
            }
            wp_send_json_success($response);
        } else {
            wp_send_json_error(['message' => 'Failed to assign QR code']);
        }
    }

    public function release_qr_code()
    {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        global $wpdb;
        $qr_code   = sanitize_text_field($_POST['qr_code']);
        $send_email = !empty($_POST['send_email']) && get_option('kerbcycle_qr_enable_email', 1);
        $send_sms   = !empty($_POST['send_sms']) && get_option('kerbcycle_qr_enable_sms', 0);
        $table     = $wpdb->prefix . 'kerbcycle_qr_codes';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id FROM $table WHERE qr_code = %s ORDER BY id DESC LIMIT 1",
                $qr_code
            )
        );

        if ($row) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table SET user_id = NULL, status = %s, assigned_at = NULL WHERE id = %d",
                    'available',
                    $row->id
                )
            );
        } else {
            $result = false;
        }

        if ($result !== false) {
            $sms_result = null;
            if ($row && $row->user_id) {
                if ($send_email) {
                    (new \Kerbcycle\QrCode\Services\EmailService())->send_notification($row->user_id, $qr_code, 'released');
                }
                if ($send_sms) {
                    $sms_result = (new \Kerbcycle\QrCode\Services\SmsService())->send_notification($row->user_id, $qr_code, 'released');
                }
            }
            $response = ['message' => 'QR code released successfully'];
            if ($send_sms) {
                $response['sms_sent'] = ($sms_result === true);
                if ($sms_result !== true) {
                    $response['sms_error'] = is_wp_error($sms_result) ? $sms_result->get_error_message() : __('Unknown error', 'kerbcycle');
                }
            }
            wp_send_json_success($response);
        } else {
            wp_send_json_error(['message' => 'Failed to release QR code']);
        }
    }

    public function bulk_release_qr_codes()
    {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        if (empty($_POST['qr_codes'])) {
            wp_send_json_error(['message' => 'No QR codes were selected.']);
        }

        $raw_codes = explode(',', $_POST['qr_codes']);
        $codes = array_map('trim', array_map('sanitize_text_field', $raw_codes));
        $codes = array_filter($codes);

        if (empty($codes)) {
            wp_send_json_error(['message' => 'No valid QR codes provided.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $released_count = 0;

        foreach ($codes as $code) {
            $latest_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table WHERE qr_code = %s AND status = 'assigned' ORDER BY id DESC LIMIT 1",
                    $code
                )
            );

            if ($latest_id) {
                $result = $wpdb->update(
                    $table,
                    [
                        'user_id' => null,
                        'status' => 'available',
                        'assigned_at' => null,
                    ],
                    ['id' => $latest_id],
                    [
                        '%d',
                        '%s',
                        '%s',
                    ],
                    ['%d']
                );

                if ($result !== false) {
                    $released_count += $result;
                }
            }
        }

        if ($released_count > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    '%d of %d selected QR code(s) have been successfully released.',
                    $released_count,
                    count($codes)
                )
            ]);
        } else {
            wp_send_json_error(['message' => 'Could not find or release any of the selected QR codes. They may have already been released or do not exist.']);
        }
    }

    public function update_qr_code()
    {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        $old_code = sanitize_text_field($_POST['old_code']);
        $new_code = sanitize_text_field($_POST['new_code']);

        if (empty($old_code) || empty($new_code)) {
            wp_send_json_error(['message' => 'Invalid QR code']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $result = $wpdb->update(
            $table,
            ['qr_code' => $new_code],
            ['qr_code' => $old_code],
            ['%s'],
            ['%s']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'QR code updated']);
        }

        wp_send_json_error(['message' => 'Failed to update QR code']);
    }

    public function ajax_report_data()
    {
        check_ajax_referer('kerbcycle_qr_report_nonce', 'security');
        $report_service = new ReportService();
        $data = $report_service->get_report_data();
        wp_send_json($data);
    }
}
