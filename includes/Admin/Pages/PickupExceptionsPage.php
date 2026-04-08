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
    private const RETRY_LOCK_TTL = 120;

    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        \Kerbcycle\QrCode\Install\Activator::activate();

        $this->handle_retry_request();

        global $wpdb;

        $table_name = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $limit = 50;
        $status_filter = isset($_GET['status_filter']) ? sanitize_key(wp_unslash($_GET['status_filter'])) : '';
        if ($status_filter === 'failed') {
            $sql = $wpdb->prepare(
                "SELECT id, submitted_at, updated_at, qr_code, customer_id, issue, notes, ai_severity, ai_category, webhook_sent, status, ai_recommended_action, ai_summary, webhook_status_code, webhook_response_body, retry_count, last_retry_at
                FROM {$table_name}
                WHERE status = %s
                ORDER BY id DESC
                LIMIT %d",
                'failed',
                $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT id, submitted_at, updated_at, qr_code, customer_id, issue, notes, ai_severity, ai_category, webhook_sent, status, ai_recommended_action, ai_summary, webhook_status_code, webhook_response_body, retry_count, last_retry_at
                FROM {$table_name}
                ORDER BY id DESC
                LIMIT %d",
                $limit
            );
        }

        $records = $wpdb->get_results($sql);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Pickup Exceptions', 'kerbcycle'); ?></h1>
            <p><?php esc_html_e('This page shows locally stored pickup exceptions and webhook/AI outcome data.', 'kerbcycle'); ?></p>
            <p>
                <?php
                $all_url = add_query_arg(['page' => 'kerbcycle-pickup-exceptions'], admin_url('admin.php'));
                $failed_url = add_query_arg(['page' => 'kerbcycle-pickup-exceptions', 'status_filter' => 'failed'], admin_url('admin.php'));
                ?>
                <a href="<?php echo esc_url($all_url); ?>" class="<?php echo esc_attr($status_filter === 'failed' ? '' : 'current'); ?>"><?php esc_html_e('All', 'kerbcycle'); ?></a>
                |
                <a href="<?php echo esc_url($failed_url); ?>" class="<?php echo esc_attr($status_filter === 'failed' ? 'current' : ''); ?>"><?php esc_html_e('Failed Only', 'kerbcycle'); ?></a>
            </p>
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
                .kerbcycle-pickup-details-row {
                    display: none;
                }
                .kerbcycle-pickup-details-content {
                    padding: 8px 12px;
                }
                .kerbcycle-pickup-details-content p {
                    margin: 0 0 8px;
                }
                .kerbcycle-pickup-details-content pre {
                    margin: 0 0 8px;
                    max-height: 240px;
                    overflow: auto;
                    white-space: pre-wrap;
                    word-break: break-word;
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
                        <th><?php esc_html_e('Retry Count', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Last Retry', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Recommended Action', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('AI Summary', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Actions', 'kerbcycle'); ?></th>
                    </tr>
                </thead>
                <tbody id="kerbcycle-pickup-exceptions-tbody">
                <?php if (empty($records)) : ?>
                    <tr>
                        <td colspan="13"><?php esc_html_e('No pickup exceptions found.', 'kerbcycle'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($records as $record) : ?>
                        <tr data-exception-id="<?php echo esc_attr((string) (int) $record->id); ?>">
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
                            <td><?php echo esc_html((string) (isset($record->retry_count) ? (int) $record->retry_count : 0)); ?></td>
                            <td><?php echo esc_html(!empty($record->last_retry_at) ? (string) $record->last_retry_at : '—'); ?></td>
                            <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $record->ai_recommended_action), 20, '…')); ?></td>
                            <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $record->ai_summary), 20, '…')); ?></td>
                            <td>
                                <button type="button" class="button button-small kerbcycle-view-details" data-exception-id="<?php echo esc_attr((string) (int) $record->id); ?>" aria-expanded="false"><?php esc_html_e('View Details', 'kerbcycle'); ?></button>
                                <?php if (((int) $record->webhook_sent) === 0) : ?>
                                    <?php
                                    $retry_args = [
                                        'page' => 'kerbcycle-pickup-exceptions',
                                        'kerbcycle_action' => 'retry_pickup_exception',
                                        'exception_id' => (int) $record->id,
                                    ];
                                    if ($status_filter === 'failed') {
                                        $retry_args['status_filter'] = 'failed';
                                    }
                                    $retry_url = wp_nonce_url(
                                        add_query_arg(
                                            $retry_args,
                                            admin_url('admin.php')
                                        ),
                                        'kerbcycle_retry_pickup_exception_' . (int) $record->id
                                    );
                                    ?>
                                    <a href="<?php echo esc_url($retry_url); ?>" class="button button-small kerbcycle-retry-webhook" data-exception-id="<?php echo esc_attr((string) (int) $record->id); ?>"><?php esc_html_e('Retry Webhook', 'kerbcycle'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="kerbcycle-pickup-details-row" data-exception-id="<?php echo esc_attr((string) (int) $record->id); ?>">
                            <td colspan="13">
                                <div class="kerbcycle-pickup-details-content">
                                    <p><strong><?php esc_html_e('Issue', 'kerbcycle'); ?>:</strong><br><?php echo nl2br(esc_html((string) $record->issue)); ?></p>
                                    <p><strong><?php esc_html_e('Notes', 'kerbcycle'); ?>:</strong><br><?php echo nl2br(esc_html((string) $record->notes)); ?></p>
                                    <p><strong><?php esc_html_e('AI Summary', 'kerbcycle'); ?>:</strong><br><?php echo nl2br(esc_html((string) $record->ai_summary)); ?></p>
                                    <p><strong><?php esc_html_e('Recommended Action', 'kerbcycle'); ?>:</strong><br><?php echo nl2br(esc_html((string) $record->ai_recommended_action)); ?></p>
                                    <p><strong><?php esc_html_e('Webhook Status Code', 'kerbcycle'); ?>:</strong> <?php echo esc_html(isset($record->webhook_status_code) && $record->webhook_status_code !== null ? (string) $record->webhook_status_code : ''); ?></p>
                                    <p><strong><?php esc_html_e('Webhook Response Body', 'kerbcycle'); ?>:</strong></p>
                                    <pre><?php echo esc_html((string) $record->webhook_response_body); ?></pre>
                                    <p><strong><?php esc_html_e('Submitted At', 'kerbcycle'); ?>:</strong> <?php echo esc_html((string) $record->submitted_at); ?></p>
                                    <p><strong><?php esc_html_e('Updated At', 'kerbcycle'); ?>:</strong> <?php echo esc_html((string) $record->updated_at); ?></p>
                                    <p><strong><?php esc_html_e('Retry Count', 'kerbcycle'); ?>:</strong> <?php echo esc_html((string) (isset($record->retry_count) ? (int) $record->retry_count : 0)); ?></p>
                                    <p><strong><?php esc_html_e('Last Retry', 'kerbcycle'); ?>:</strong> <?php echo esc_html(!empty($record->last_retry_at) ? (string) $record->last_retry_at : '—'); ?></p>
                                    <p><strong><?php esc_html_e('Customer ID', 'kerbcycle'); ?>:</strong> <?php echo esc_html((string) $record->customer_id); ?></p>
                                    <p><strong><?php esc_html_e('QR Code', 'kerbcycle'); ?>:</strong> <?php echo esc_html((string) $record->qr_code); ?></p>
                                </div>
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

        if (!$this->acquire_retry_lock($exception_id)) {
            $this->redirect_with_retry_notice('error', __('Retry already in progress for this pickup exception.', 'kerbcycle'));
        }

        $retry_timestamp = current_time('mysql', true);
        PickupExceptionRepository::update_result($exception_id, [
            'retry_count' => ((int) $record->retry_count) + 1,
            'last_retry_at' => $retry_timestamp,
            'updated_at' => $retry_timestamp,
        ]);

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
            $this->release_retry_lock($exception_id);
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
            $this->release_retry_lock($exception_id);
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
        $this->release_retry_lock($exception_id);
        $this->redirect_with_retry_notice('error', __('Retry failed. The record remains saved locally.', 'kerbcycle'));
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
        if (isset($_GET['status_filter']) && sanitize_key(wp_unslash($_GET['status_filter'])) === 'failed') {
            $redirect_url = add_query_arg('status_filter', 'failed', $redirect_url);
        }

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
