<?php

namespace Kerbcycle\QrCode\Admin\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Services\ReportService;
use Kerbcycle\QrCode\Services\QrService;
use Kerbcycle\QrCode\Helpers\Nonces;
use Kerbcycle\QrCode\Data\Repositories\MessageLogRepository;

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
        add_action('wp_ajax_bulk_delete_qr_codes', [$this, 'bulk_delete_qr_codes']);
        add_action('wp_ajax_update_qr_code', [$this, 'update_qr_code']);
        add_action('wp_ajax_add_qr_code', [$this, 'add_qr_code']);
        add_action('wp_ajax_get_assigned_qr_codes', [$this, 'get_assigned_qr_codes']);
        add_action('wp_ajax_import_qr_codes', [$this, 'import_qr_codes']);
        add_action('wp_ajax_kerbcycle_qr_report_data', [$this, 'ajax_report_data']);
        add_action('wp_ajax_kerbcycle_delete_logs', [$this, 'delete_logs']);
    }

    public function assign_qr_code()
    {
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        $qr_code      = sanitize_text_field(wp_unslash($_POST['qr_code']));
        $user_id      = intval(wp_unslash($_POST['customer_id']));
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
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        $qr_code   = sanitize_text_field(wp_unslash($_POST['qr_code']));
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

    public function get_assigned_qr_codes()
    {
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        $user_id = isset($_POST['customer_id']) ? intval(wp_unslash($_POST['customer_id'])) : 0;
        if (!$user_id) {
            wp_send_json_error(['message' => __('Invalid user ID', 'kerbcycle')]);
        }

        $codes = $this->qr_service->get_assigned_by_user($user_id);
        wp_send_json_success($codes);
    }

    public function bulk_release_qr_codes()
    {
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        if (empty($_POST['qr_codes'])) {
            wp_send_json_error(['message' => __('No QR codes were selected.', 'kerbcycle')]);
        }

        $raw_codes = explode(',', wp_unslash($_POST['qr_codes']));
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

    public function bulk_delete_qr_codes()
    {
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        if (empty($_POST['qr_codes'])) {
            wp_send_json_error(['message' => __('No QR codes were selected.', 'kerbcycle')]);
        }

        $raw_codes = explode(',', wp_unslash($_POST['qr_codes']));
        $codes = array_map('trim', array_map('sanitize_text_field', $raw_codes));
        $codes = array_filter($codes);

        if (empty($codes)) {
            wp_send_json_error(['message' => 'No valid QR codes provided.']);
        }

        $deleted_count = $this->qr_service->bulk_delete($codes);

        if ($deleted_count > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    '%d of %d selected QR code(s) have been deleted.',
                    $deleted_count,
                    count($codes)
                )
            ]);
        } else {
            wp_send_json_error(['message' => 'Could not delete any of the selected QR codes. Ensure they are available.']);
        }
    }

    public function update_qr_code()
    {
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        $old_code = sanitize_text_field(wp_unslash($_POST['old_code']));
        $new_code = sanitize_text_field(wp_unslash($_POST['new_code']));

        if (empty($old_code) || empty($new_code)) {
            wp_send_json_error(['message' => __('Invalid QR code', 'kerbcycle')]);
        }

        $result = $this->qr_service->update($old_code, $new_code);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if ($result !== false) {
            wp_send_json_success(['message' => __('QR code updated', 'kerbcycle')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update QR code', 'kerbcycle')]);
        }
    }

    public function add_qr_code()
    {
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        $qr_code = sanitize_text_field(wp_unslash($_POST['qr_code']));

        if (empty($qr_code)) {
            wp_send_json_error(['message' => __('Invalid QR code', 'kerbcycle')]);
        }

        $result = $this->qr_service->add($qr_code);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } elseif ($result !== false) {
            wp_send_json_success([
                'message' => __('QR code added successfully.', 'kerbcycle'),
                'row'     => $result,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to add QR code due to a database error.', 'kerbcycle')]);
        }
    }

    public function import_qr_codes()
    {
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        if (empty($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            wp_send_json_error(['message' => __('No file uploaded.', 'kerbcycle')]);
        }

        $handle = fopen($_FILES['import_file']['tmp_name'], 'r');
        if (!$handle) {
            wp_send_json_error(['message' => __('Could not read uploaded file.', 'kerbcycle')]);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            wp_send_json_error(['message' => __('Invalid CSV file.', 'kerbcycle')]);
        }
        $code_index = array_search('Code', $header);
        if ($code_index === false) {
            fclose($handle);
            wp_send_json_error(['message' => __('Could not find Code column.', 'kerbcycle')]);
        }

        $added = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if (empty($data[$code_index])) {
                continue;
            }
            $code   = sanitize_text_field($data[$code_index]);
            $result = $this->qr_service->add($code);
            if (!is_wp_error($result) && $result !== false) {
                $added++;
            }
        }
        fclose($handle);

        if ($added > 0) {
            wp_send_json_success([
                'message' => sprintf(__('Imported %d QR code(s).', 'kerbcycle'), $added),
                'added'   => $added,
            ]);
        } else {
            wp_send_json_error(['message' => __('No QR codes were imported.', 'kerbcycle')]);
        }
    }

    public function ajax_report_data()
    {
        Nonces::verify('kerbcycle_qr_report_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }
        $report_service = new ReportService();
        $data = $report_service->get_report_data();
        wp_send_json($data);
    }

    public function delete_logs()
    {
        Nonces::verify('kerbcycle_delete_logs', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        $ids = isset($_POST['log_ids']) && is_array($_POST['log_ids']) ? array_map('absint', wp_unslash($_POST['log_ids'])) : [];
        if (!$ids) {
            wp_send_json_error(['message' => __('No logs selected', 'kerbcycle')]);
        }

        $repo = new MessageLogRepository();
        $deleted = $repo->delete_by_ids($ids);

        wp_send_json_success(['deleted' => (int) $deleted]);
    }
}
