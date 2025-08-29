<?php

namespace Kerbcycle\QrCode\Admin\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Services\ReportService;
use Kerbcycle\QrCode\Services\QrService;

/**
 * The admin ajax.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Ajax
 */
class AdminAjax
{
    private $qr_service;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->qr_service = new QrService();

        add_action('wp_ajax_assign_qr_code', [$this, 'assign_qr_code']);
        add_action('wp_ajax_release_qr_code', [$this, 'release_qr_code']);
        add_action('wp_ajax_bulk_release_qr_codes', [$this, 'bulk_release_qr_codes']);
        add_action('wp_ajax_update_qr_code', [$this, 'update_qr_code']);
        add_action('wp_ajax_kerbcycle_qr_report_data', [$this, 'ajax_report_data']);
    }

    public function assign_qr_code()
    {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        $qr_code      = sanitize_text_field($_POST['qr_code']);
        $user_id      = intval($_POST['customer_id']);
        $send_email   = !empty($_POST['send_email']) && get_option('kerbcycle_qr_enable_email', 1);
        $send_sms     = !empty($_POST['send_sms']) && get_option('kerbcycle_qr_enable_sms', 0);
        $send_reminder = !empty($_POST['send_reminder']) && get_option('kerbcycle_qr_enable_reminders', 0);

        $result = $this->qr_service->assign($qr_code, $user_id, $send_email, $send_sms, $send_reminder);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            $response = [
                'message' => 'QR code assigned successfully',
                'qr_code' => $qr_code,
                'user_id' => $user_id,
            ];
            if ($send_sms) {
                $response['sms_sent'] = ($result['sms_result'] === true);
                if ($result['sms_result'] !== true) {
                    $response['sms_error'] = is_wp_error($result['sms_result']) ? $result['sms_result']->get_error_message() : __('Unknown error', 'kerbcycle');
                }
            }
            wp_send_json_success($response);
        }
    }

    public function release_qr_code()
    {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        $qr_code   = sanitize_text_field($_POST['qr_code']);
        $send_email = !empty($_POST['send_email']) && get_option('kerbcycle_qr_enable_email', 1);
        $send_sms   = !empty($_POST['send_sms']) && get_option('kerbcycle_qr_enable_sms', 0);

        $result = $this->qr_service->release($qr_code, $send_email, $send_sms);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            $response = ['message' => 'QR code released successfully'];
            if ($send_sms) {
                $response['sms_sent'] = ($result['sms_result'] === true);
                if ($result['sms_result'] !== true) {
                    $response['sms_error'] = is_wp_error($result['sms_result']) ? $result['sms_result']->get_error_message() : __('Unknown error', 'kerbcycle');
                }
            }
            wp_send_json_success($response);
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

        $released_count = $this->qr_service->bulk_release($codes);

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

        $result = $this->qr_service->update($old_code, $new_code);

        if ($result !== false) {
            wp_send_json_success(['message' => 'QR code updated']);
        } else {
            wp_send_json_error(['message' => 'Failed to update QR code']);
        }
    }

    public function ajax_report_data()
    {
        check_ajax_referer('kerbcycle_qr_report_nonce', 'security');
        $report_service = new ReportService();
        $data = $report_service->get_report_data();
        wp_send_json($data);
    }
}
