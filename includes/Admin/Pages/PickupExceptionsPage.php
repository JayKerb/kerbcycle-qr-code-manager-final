<?php

namespace Kerbcycle\QrCode\Admin\Pages;

use Kerbcycle\QrCode\Data\Repositories\PickupExceptionRepository;
use Kerbcycle\QrCode\Services\QrService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page to display pickup exception logs.
 */
class PickupExceptionsPage
{
    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->handle_retry_request();

        global $wpdb;

        $table_name = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $limit = 50;

        $sql = $wpdb->prepare(
            "SELECT id, submitted_at, qr_code, customer_id, issue, ai_severity, ai_category, webhook_sent, status, ai_recommended_action, ai_summary
            FROM {$table_name}
            ORDER BY id DESC
            LIMIT %d",
            $limit
        );

        $records = $wpdb->get_results($sql);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Pickup Exceptions', 'kerbcycle'); ?></h1>
            <p><?php esc_html_e('This page shows locally stored pickup exceptions and webhook/AI outcome data.', 'kerbcycle'); ?></p>
            <?php $this->render_retry_notice(); ?>
            <style>
                .kerb-badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .kerb-badge-success {
                    background: #d1fae5;
                    color: #065f46;
                }
                .kerb-badge-error {
                    background: #fee2e2;
                    color: #7f1d1d;
                }
                .kerb-badge-pending {
                    background: #fef3c7;
                    color: #92400e;
                }
            </style>

            <table id="kerbcycle-pickup-exceptions-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Submitted At', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('QR Code', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Customer ID', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Issue', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Severity', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Category', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Status', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Recommended Action', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('AI Summary', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Actions', 'kerbcycle'); ?></th>
                    </tr>
                </thead>
                <tbody id="kerbcycle-pickup-exceptions-tbody">
                <?php if (empty($records)) : ?>
                    <tr>
                        <td colspan="11"><?php esc_html_e('No pickup exceptions found.', 'kerbcycle'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($records as $record) : ?>
                        <tr>
                            <td><?php echo esc_html($record->id); ?></td>
                            <td><?php echo esc_html($record->submitted_at); ?></td>
                            <td><?php echo esc_html($record->qr_code); ?></td>
                            <td><?php echo esc_html($record->customer_id); ?></td>
                            <td><?php echo esc_html($record->issue); ?></td>
                            <td><?php echo esc_html($record->ai_severity); ?></td>
                            <td><?php echo esc_html($record->ai_category); ?></td>
                            <td>
                                <?php
                                $status = isset($record->status) ? (string) $record->status : (((int) $record->webhook_sent) === 1 ? 'sent' : 'failed');
                                if ($status === 'sent') {
                                    echo '<span class="kerb-badge kerb-badge-success">Sent</span>';
                                } elseif ($status === 'failed') {
                                    echo '<span class="kerb-badge kerb-badge-error">Failed</span>';
                                } else {
                                    echo '<span class="kerb-badge kerb-badge-pending">Pending</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $record->ai_recommended_action), 20, '…')); ?></td>
                            <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $record->ai_summary), 20, '…')); ?></td>
                            <td>
                                <?php if (((int) $record->webhook_sent) === 0) : ?>
                                    <?php
                                    $retry_url = wp_nonce_url(
                                        add_query_arg(
                                            [
                                                'page' => 'kerbcycle-pickup-exceptions',
                                                'kerbcycle_action' => 'retry_pickup_exception',
                                                'exception_id' => (int) $record->id,
                                            ],
                                            admin_url('admin.php')
                                        ),
                                        'kerbcycle_retry_pickup_exception_' . (int) $record->id
                                    );
                                    ?>
                                    <a href="<?php echo esc_url($retry_url); ?>" class="button button-small kerbcycle-retry-webhook" data-exception-id="<?php echo esc_attr((string) (int) $record->id); ?>"><?php esc_html_e('Retry Webhook', 'kerbcycle'); ?></a>
                                <?php else : ?>
                                    <span aria-hidden="true">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function handle_retry_request()
    {
        if (!isset($_GET['kerbcycle_action']) || $_GET['kerbcycle_action'] !== 'retry_pickup_exception') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $exception_id = isset($_GET['exception_id']) ? absint($_GET['exception_id']) : 0;
        if ($exception_id < 1) {
            $this->redirect_with_retry_notice('error', __('Invalid pickup exception ID.', 'kerbcycle'));
        }

        check_admin_referer('kerbcycle_retry_pickup_exception_' . $exception_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $exception_id));

        if (!$record) {
            $this->redirect_with_retry_notice('error', __('Pickup exception record not found.', 'kerbcycle'));
        }

        if ((int) $record->webhook_sent === 1) {
            $this->redirect_with_retry_notice('error', __('This pickup exception is not eligible for retry.', 'kerbcycle'));
        }

        $result = (new QrService())->send_pickup_exception_to_n8n([
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

            $this->redirect_with_retry_notice('error', __('Retry failed. The record remains saved locally.', 'kerbcycle'));
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

            $this->redirect_with_retry_notice('success', __('Pickup exception resent successfully.', 'kerbcycle'));
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

        $this->redirect_with_retry_notice('error', __('Retry failed. The record remains saved locally.', 'kerbcycle'));
    }

    private function redirect_with_retry_notice($status, $message)
    {
        $redirect_url = add_query_arg(
            [
                'page' => 'kerbcycle-pickup-exceptions',
                'retry_status' => sanitize_key((string) $status),
                'retry_message' => (string) $message,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function render_retry_notice()
    {
        if (!isset($_GET['retry_status'])) {
            return;
        }

        $status = sanitize_key(wp_unslash($_GET['retry_status']));
        $message = isset($_GET['retry_message']) ? sanitize_text_field(wp_unslash((string) $_GET['retry_message'])) : '';

        if ($message === '') {
            return;
        }

        $notice_class = $status === 'success' ? 'notice notice-success' : 'notice notice-error';
        ?>
        <div class="<?php echo esc_attr($notice_class); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
}
