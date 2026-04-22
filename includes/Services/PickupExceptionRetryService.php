<?php

namespace Kerbcycle\QrCode\Services;

use Kerbcycle\QrCode\Data\Repositories\PickupExceptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

class PickupExceptionRetryService
{
    private const RETRY_LOCK_TTL = 120;

    /**
     * @param int            $exception_id
     * @param QrService|null $qr_service
     *
     * @return array{state:string,message:string,status_code?:int,error_code?:string}
     */
    public function retry($exception_id, QrService $qr_service = null)
    {
        $exception_id = (int) $exception_id;
        if ($exception_id < 1) {
            return [
                'state' => 'invalid_id',
                'message' => __('Invalid pickup exception ID.', 'kerbcycle'),
                'status_code' => 400,
            ];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $exception_id));

        if (!$record) {
            return [
                'state' => 'not_found',
                'message' => __('Pickup exception record not found.', 'kerbcycle'),
                'status_code' => 404,
            ];
        }

        if ((int) $record->webhook_sent === 1) {
            return [
                'state' => 'ineligible',
                'message' => __('This pickup exception is not eligible for retry.', 'kerbcycle'),
                'status_code' => 400,
            ];
        }

        if (!$this->acquire_retry_lock($exception_id)) {
            return [
                'state' => 'lock_conflict',
                'message' => __('Retry already in progress for this pickup exception.', 'kerbcycle'),
                'status_code' => 409,
            ];
        }

        try {
            $retry_timestamp = current_time('mysql', true);
            PickupExceptionRepository::update_result($exception_id, [
                'retry_count' => ((int) $record->retry_count) + 1,
                'last_retry_at' => $retry_timestamp,
                'updated_at' => $retry_timestamp,
            ]);

            if ($qr_service === null) {
                $qr_service = new QrService();
            }

            $result = $qr_service->send_pickup_exception_to_n8n([
                'qr_code'     => (string) $record->qr_code,
                'customer_id' => (int) $record->customer_id,
                'issue'       => (string) $record->issue,
                'notes'       => (string) $record->notes,
                'timestamp'   => !empty($record->submitted_at) ? (string) $record->submitted_at : '',
            ]);

            if (is_wp_error($result)) {
                PickupExceptionRepository::update_result($exception_id, [
                    'webhook_sent'             => 0,
                    'webhook_status_code'      => 0,
                    'status'                   => 'failed',
                    'webhook_response_body'    => $result->get_error_message(),
                    'ai_severity'              => '',
                    'ai_category'              => '',
                    'ai_summary'               => '',
                    'ai_recommended_action'    => '',
                    'updated_at'               => current_time('mysql', true),
                ]);

                return [
                    'state' => 'webhook_error',
                    'message' => __('Retry failed. The record remains saved locally.', 'kerbcycle'),
                    'status_code' => 500,
                    'error_code' => $result->get_error_code(),
                ];
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
                    'status'                   => 'sent',
                    'webhook_response_body'    => is_scalar($body) ? (string) $body : wp_json_encode($body),
                    'ai_severity'              => $ai_severity,
                    'ai_category'              => $ai_category,
                    'ai_summary'               => $ai_summary,
                    'ai_recommended_action'    => $ai_recommended_action,
                    'updated_at'               => current_time('mysql', true),
                ]);

                return [
                    'state' => 'success',
                    'message' => __('Pickup exception resent successfully.', 'kerbcycle'),
                    'status_code' => 200,
                    'webhook_status_code' => isset($result['status_code']) ? (int) $result['status_code'] : 0,
                ];
            }

            $result_body = isset($result['body']) ? $result['body'] : '';
            PickupExceptionRepository::update_result($exception_id, [
                'webhook_sent'             => 0,
                'webhook_status_code'      => isset($result['status_code']) ? (int) $result['status_code'] : 0,
                'status'                   => 'failed',
                'webhook_response_body'    => is_scalar($result_body) ? (string) $result_body : wp_json_encode($result_body),
                'ai_severity'              => '',
                'ai_category'              => '',
                'ai_summary'               => '',
                'ai_recommended_action'    => '',
                'updated_at'               => current_time('mysql', true),
            ]);

            return [
                'state' => 'webhook_non_success',
                'message' => __('Retry failed. The record remains saved locally.', 'kerbcycle'),
                'status_code' => 500,
                'webhook_status_code' => isset($result['status_code']) ? (int) $result['status_code'] : 0,
            ];
        } finally {
            $this->release_retry_lock($exception_id);
        }
    }

    private function retry_lock_key($exception_id)
    {
        return 'kerbcycle_pickup_retry_lock_' . (int) $exception_id;
    }

    private function acquire_retry_lock($exception_id)
    {
        $key = $this->retry_lock_key($exception_id);
        $now = time();
        $expires_at = (int) get_option($key, 0);
        if ($expires_at > 0 && $expires_at <= $now) {
            delete_option($key);
        }
        return add_option($key, (string) ($now + self::RETRY_LOCK_TTL), '', 'no');
    }

    private function release_retry_lock($exception_id)
    {
        delete_option($this->retry_lock_key($exception_id));
    }
}
