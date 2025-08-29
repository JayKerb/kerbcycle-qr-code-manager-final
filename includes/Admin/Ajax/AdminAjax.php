<?php

namespace Kerbcycle\QrCode\Admin\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Services\ReportService;
use Kerbcycle\QrCode\Services\QrService;
use Kerbcycle\QrCode\Helpers\Nonces;

/**
 * The admin ajax.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Ajax
 */
class AdminAjax
{
    private $service;

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
        add_action('wp_ajax_kerbcycle_delete_logs', [$this, 'delete_logs']);

        $this->service = new QrService();
    }

    // Note: The actual logic for these methods will be moved to services later.
    // For now, I'm just moving the methods as is.

    public function assign_qr_code()
    {
        if (!Nonces::verify('kerbcycle_qr_nonce', 'security')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $qr_code    = sanitize_text_field($_POST['qr_code']);
        $user_id    = intval($_POST['customer_id']);
        $send_email = !empty($_POST['send_email']) && get_option('kerbcycle_qr_enable_email', 1);
        $send_sms   = !empty($_POST['send_sms']) && get_option('kerbcycle_qr_enable_sms', 0);

        $result = $this->service->assign_code($qr_code, $user_id, $send_email, $send_sms);

        if ($result !== false) {
            $response = [
                'message' => 'QR code assigned successfully',
                'qr_code' => $qr_code,
                'user_id' => $user_id,
            ];
            wp_send_json_success($response);
        }

        wp_send_json_error(['message' => 'Failed to assign QR code']);
    }

    public function release_qr_code()
    {
        if (!Nonces::verify('kerbcycle_qr_nonce', 'security')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $qr_code   = sanitize_text_field($_POST['qr_code']);
        $send_email = !empty($_POST['send_email']) && get_option('kerbcycle_qr_enable_email', 1);
        $send_sms   = !empty($_POST['send_sms']) && get_option('kerbcycle_qr_enable_sms', 0);

        $row = $this->service->release_code($qr_code, $send_email, $send_sms);

        if ($row) {
            wp_send_json_success(['message' => 'QR code released successfully']);
        }

        wp_send_json_error(['message' => 'Failed to release QR code']);
    }

    public function bulk_release_qr_codes()
    {
        if (!Nonces::verify('kerbcycle_qr_nonce', 'security')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        if (empty($_POST['qr_codes'])) {
            wp_send_json_error(['message' => 'No QR codes were selected.']);
        }

        $raw_codes = explode(',', $_POST['qr_codes']);
        $codes = array_map('trim', array_map('sanitize_text_field', $raw_codes));
        $codes = array_filter($codes);

        if (empty($codes)) {
            wp_send_json_error(['message' => 'No valid QR codes provided.']);
        }

        $released_count = $this->service->bulk_release_codes($codes);

        if ($released_count > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    '%d of %d selected QR code(s) have been successfully released.',
                    $released_count,
                    count($codes)
                )
            ]);
        }

        wp_send_json_error(['message' => 'Could not find or release any of the selected QR codes. They may have already been released or do not exist.']);
    }

    public function update_qr_code()
    {
        if (!Nonces::verify('kerbcycle_qr_nonce', 'security')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $old_code = sanitize_text_field($_POST['old_code']);
        $new_code = sanitize_text_field($_POST['new_code']);

        if (empty($old_code) || empty($new_code)) {
            wp_send_json_error(['message' => 'Invalid QR code']);
        }

        $result = $this->service->update_code($old_code, $new_code);

        if ($result !== false) {
            wp_send_json_success(['message' => 'QR code updated']);
        }

        wp_send_json_error(['message' => 'Failed to update QR code']);
    }

    public function ajax_report_data()
    {
        if (!Nonces::verify('kerbcycle_qr_report_nonce', 'security')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $report_service = new ReportService();
        $data = $report_service->get_report_data();
        wp_send_json($data);
    }

    public function delete_logs()
    {
        if (!Nonces::verify('kerbcycle_delete_logs', 'security')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $ids = isset($_POST['log_ids']) && is_array($_POST['log_ids']) ? array_map('absint', $_POST['log_ids']) : [];
        $deleted = (new \Kerbcycle\QrCode\Data\Repositories\MessageLogRepository())->delete_logs($ids);
        wp_send_json_success(['deleted' => (int) $deleted]);
    }
}
