<?php

namespace Kerbcycle\QrCode\Admin\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Services\ReportService;
use Kerbcycle\QrCode\Services\QrService;
use Kerbcycle\QrCode\Helpers\Nonces;
use Kerbcycle\QrCode\Data\Repositories\MessageLogRepository;
use Kerbcycle\QrCode\Data\Repositories\ErrorLogRepository;
use Kerbcycle\QrCode\Data\Repositories\PickupExceptionRepository;
use Kerbcycle\QrCode\Admin\Pages\DashboardPage;

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
        add_action('wp_ajax_kerbcycle_paginate_qr_codes', [$this, 'paginate_qr_codes']);
        add_action('wp_ajax_kerbcycle_qr_report_data', [$this, 'ajax_report_data']);
        add_action('wp_ajax_kerbcycle_delete_logs', [$this, 'delete_logs']);
        add_action('wp_ajax_kerbcycle_test_pickup_exception', [$this, 'test_pickup_exception']);
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
            if ($send_email) {
                $response['email_sent'] = ($result['email_result'] === true);
                if ($result['email_result'] !== true) {
                    $response['email_error'] = is_wp_error($result['email_result']) ? $result['email_result']->get_error_message() : __('Unknown error', 'kerbcycle');
                }
            }
            if ($send_sms) {
                $response['sms_sent'] = ($result['sms_result'] === true);
                if ($result['sms_result'] !== true) {
                    $response['sms_error'] = is_wp_error($result['sms_result']) ? $result['sms_result']->get_error_message() : __('Unknown error', 'kerbcycle');
                }
            }
            if (!empty($result['record'])) {
                $formatted = $this->format_qr_record($result['record']);
                if ($formatted) {
                    $response['record'] = $formatted;
                }
            }
            wp_send_json_success($response);
        }
    }

    private function format_qr_record($record)
    {
        if (!is_object($record)) {
            return null;
        }

        return [
            'id'           => isset($record->id) ? (int) $record->id : 0,
            'qr_code'      => isset($record->qr_code) ? (string) $record->qr_code : '',
            'user_id'      => isset($record->user_id) ? (int) $record->user_id : 0,
            'display_name' => isset($record->display_name) ? (string) $record->display_name : '',
            'status'       => isset($record->status) ? (string) $record->status : '',
            'assigned_at'  => isset($record->assigned_at) ? (string) $record->assigned_at : '',
        ];
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
            if ($send_email) {
                $response['email_sent'] = ($result['email_result'] === true);
                if ($result['email_result'] !== true) {
                    $response['email_error'] = is_wp_error($result['email_result']) ? $result['email_result']->get_error_message() : __('Unknown error', 'kerbcycle');
                }
            }
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

    public function paginate_qr_codes()
    {
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        $args = [
            'status_filter' => isset($_POST['status_filter']) ? wp_unslash($_POST['status_filter']) : '',
            'start_date'    => isset($_POST['start_date']) ? wp_unslash($_POST['start_date']) : '',
            'end_date'      => isset($_POST['end_date']) ? wp_unslash($_POST['end_date']) : '',
            'search'        => isset($_POST['search']) ? wp_unslash($_POST['search']) : '',
            's'             => isset($_POST['s']) ? wp_unslash($_POST['s']) : '',
            'per_page'      => isset($_POST['per_page']) ? wp_unslash($_POST['per_page']) : '',
            'paged'         => isset($_POST['paged']) ? wp_unslash($_POST['paged']) : 1,
        ];

        $listing = DashboardPage::get_listing_data($args);

        $pagination_links = DashboardPage::build_pagination_links(
            $listing['current_page'],
            $listing['total_pages'],
            $listing['filters']
        );

        wp_send_json_success([
            'items_html' => DashboardPage::render_qr_items($listing['codes']),
            'pagination' => [
                'links'        => $pagination_links,
                'current_page' => $listing['current_page'],
                'total_pages'  => $listing['total_pages'],
                'total_items'  => $listing['total_items'],
                'per_page'     => $listing['per_page'],
                'filters'      => $listing['filters'],
            ],
            'counts'      => [
                'available' => $listing['available_count'],
                'assigned'  => $listing['assigned_count'],
            ],
        ]);
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

    public function test_pickup_exception()
    {
        Nonces::verify('kerbcycle_qr_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kerbcycle')], 403);
        }

        $qr_code_raw = isset($_POST['qr_code']) ? wp_unslash($_POST['qr_code']) : '';
        $customer_id_raw = isset($_POST['customer_id']) ? wp_unslash($_POST['customer_id']) : '';
        $issue_raw = isset($_POST['issue']) ? wp_unslash($_POST['issue']) : '';
        $notes_raw = isset($_POST['notes']) ? wp_unslash($_POST['notes']) : '';
        $timestamp_raw = isset($_POST['timestamp']) ? wp_unslash($_POST['timestamp']) : '';

        $qr_code = sanitize_text_field($qr_code_raw);
        $customer_id = absint($customer_id_raw);
        $issue = sanitize_text_field($issue_raw);
        $notes = sanitize_textarea_field($notes_raw);
        $timestamp = sanitize_text_field($timestamp_raw);
        if ($timestamp === '') {
            $timestamp = gmdate('c');
        }

        if ($issue === '') {
            wp_send_json_error(['message' => __('Issue is required.', 'kerbcycle')], 400);
        }

        if ($qr_code === '' && $customer_id < 1) {
            wp_send_json_error(['message' => __('Provide at least a QR Code or Customer ID.', 'kerbcycle')], 400);
        }

        $payload = [
            'qr_code'     => $qr_code,
            'customer_id' => $customer_id,
            'issue'       => $issue,
            'notes'       => $notes,
            'timestamp'   => $timestamp,
        ];

        $now_utc_mysql = current_time('mysql', true);
        $exception_id = PickupExceptionRepository::create([
            'qr_code'      => $qr_code,
            'customer_id'  => $customer_id,
            'issue'        => $issue,
            'notes'        => $notes,
            'submitted_at' => $timestamp,
            'webhook_sent' => 0,
            'created_at'   => $now_utc_mysql,
            'updated_at'   => $now_utc_mysql,
        ]);

        if ($exception_id < 1) {
            wp_send_json_error(['message' => __('Failed to save pickup exception locally.', 'kerbcycle')], 500);
        }

        // Local save intentionally happens first; webhook delivery must not block local persistence.
        ErrorLogRepository::log([
            'type'    => 'pickup_exception',
            'message' => wp_json_encode($payload),
            'page'    => 'kerbcycle-qr-manager',
            'status'  => 'saved',
        ]);

        $result = $this->qr_service->send_pickup_exception_to_n8n([
            'qr_code'     => $qr_code,
            'customer_id' => $customer_id,
            'issue'       => $issue,
            'notes'       => $notes,
            'timestamp'   => $timestamp,
        ]);

        if (is_wp_error($result)) {
            PickupExceptionRepository::update_result($exception_id, [
                'webhook_sent'             => 0,
                'webhook_status_code'      => 0,
                'webhook_response_body'    => $result->get_error_message(),
                'ai_severity'              => '',
                'ai_category'              => '',
                'ai_summary'               => '',
                'ai_recommended_action'    => '',
                'updated_at'               => current_time('mysql', true),
            ]);

            // Webhook failures return partial success because local persistence already succeeded.
            wp_send_json_success([
                'status'      => 'partial_success',
                'message'     => __('Pickup exception saved locally, but webhook delivery failed.', 'kerbcycle'),
                'exception_id' => $exception_id,
                'local_save'  => ['success' => true, 'id' => $exception_id],
                'webhook'     => [
                    'success' => false,
                    'message' => $result->get_error_message(),
                    'code'    => $result->get_error_code(),
                ],
                'ai_recommended_action' => '',
                'ai_summary'  => '',
                'ai_category' => '',
                'ai_severity' => '',
            ]);
        }

        if (!empty($result['success'])) {
            $body = isset($result['body']) ? $result['body'] : '';
            $decoded_body = json_decode((string) $body, true);
            $ai_summary = is_array($decoded_body) && isset($decoded_body['summary']) ? (string) $decoded_body['summary'] : '';
            $ai_category = is_array($decoded_body) && isset($decoded_body['category']) ? (string) $decoded_body['category'] : '';
            $ai_severity = is_array($decoded_body) && isset($decoded_body['severity']) ? (string) $decoded_body['severity'] : '';
            $ai_recommended_action = is_array($decoded_body) && isset($decoded_body['recommended_action']) ? (string) $decoded_body['recommended_action'] : '';

            PickupExceptionRepository::update_result($exception_id, [
                'webhook_sent'             => 1,
                'webhook_status_code'      => isset($result['status_code']) ? (int) $result['status_code'] : 0,
                'webhook_response_body'    => is_scalar($body) ? (string) $body : wp_json_encode($body),
                'ai_severity'              => $ai_severity,
                'ai_category'              => $ai_category,
                'ai_summary'               => $ai_summary,
                'ai_recommended_action'    => $ai_recommended_action,
                'updated_at'               => current_time('mysql', true),
            ]);

            wp_send_json_success([
                'status'      => 'success',
                'message'     => __('Pickup exception saved locally and sent to webhook.', 'kerbcycle'),
                'exception_id' => $exception_id,
                'local_save'  => ['success' => true, 'id' => $exception_id],
                'webhook'     => $result,
                'webhook_body' => $body,
                'ai_recommended_action' => $ai_recommended_action,
                'ai_summary'  => $ai_summary,
                'ai_category' => $ai_category,
                'ai_severity' => $ai_severity,
            ]);
        }

        $result_body = isset($result['body']) ? $result['body'] : '';
        PickupExceptionRepository::update_result($exception_id, [
            'webhook_sent'             => 0,
            'webhook_status_code'      => isset($result['status_code']) ? (int) $result['status_code'] : 0,
            'webhook_response_body'    => is_scalar($result_body) ? (string) $result_body : wp_json_encode($result_body),
            'ai_severity'              => '',
            'ai_category'              => '',
            'ai_summary'               => '',
            'ai_recommended_action'    => '',
            'updated_at'               => current_time('mysql', true),
        ]);

        wp_send_json_success([
            'status'      => 'partial_success',
            'message'     => __('Pickup exception saved locally, but webhook delivery failed.', 'kerbcycle'),
            'exception_id' => $exception_id,
            'local_save'  => ['success' => true, 'id' => $exception_id],
            'webhook'     => is_array($result) ? $result : ['success' => false],
            'webhook_body' => isset($result['body']) ? $result['body'] : '',
            'ai_recommended_action' => '',
            'ai_summary'  => '',
            'ai_category' => '',
            'ai_severity' => '',
        ]);
    }
}
