<?php

namespace Kerbcycle\QrCode\Admin\Pages;

use Kerbcycle\QrCode\Services\PickupExceptionRetryService;

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
        \Kerbcycle\QrCode\Install\Activator::activate();

        $this->handle_retry_request();

        global $wpdb;

        $table_name = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $limit = 50;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filter state; no server-side state is changed by this GET value.
        $status_filter = isset($_GET['status_filter']) ? sanitize_key(wp_unslash($_GET['status_filter'])) : '';
        if ($status_filter === 'failed') {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from the WordPress table prefix and fixed plugin table suffix; values are prepared below.
            $sql = $wpdb->prepare(
                "SELECT id, submitted_at, updated_at, qr_code, customer_id, issue, notes, source, ai_severity, ai_category, webhook_sent, status, ai_recommended_action, ai_summary, webhook_status_code, webhook_response_body, retry_count, last_retry_at
                FROM {$table_name}
                WHERE status = %s
                ORDER BY id DESC
                LIMIT %d",
                'failed',
                $limit
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from the WordPress table prefix and fixed plugin table suffix; values are prepared below.
            $sql = $wpdb->prepare(
                "SELECT id, submitted_at, updated_at, qr_code, customer_id, issue, notes, source, ai_severity, ai_category, webhook_sent, status, ai_recommended_action, ai_summary, webhook_status_code, webhook_response_body, retry_count, last_retry_at
                FROM {$table_name}
                ORDER BY id DESC
                LIMIT %d",
                $limit
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built with $wpdb->prepare() immediately above; table name is fixed plugin table derived from $wpdb->prefix.
        $records = $wpdb->get_results($sql);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Pickup Exceptions', 'kerbcycle-qr-code-manager'); ?></h1>
            <p><?php esc_html_e('This page shows locally stored pickup exceptions and webhook/AI outcome data.', 'kerbcycle-qr-code-manager'); ?></p>
            <p>
                <?php
                $all_url = add_query_arg(['page' => 'kerbcycle-pickup-exceptions'], admin_url('admin.php'));
        $failed_url = add_query_arg(['page' => 'kerbcycle-pickup-exceptions', 'status_filter' => 'failed'], admin_url('admin.php'));
        ?>
                <a href="<?php echo esc_url($all_url); ?>" class="<?php echo esc_attr($status_filter === 'failed' ? '' : 'current'); ?>"><?php esc_html_e('All', 'kerbcycle-qr-code-manager'); ?></a>
                |
                <a href="<?php echo esc_url($failed_url); ?>" class="<?php echo esc_attr($status_filter === 'failed' ? 'current' : ''); ?>"><?php esc_html_e('Failed Only', 'kerbcycle-qr-code-manager'); ?></a>
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
                        <th><?php esc_html_e('ID', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Submitted At', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('QR Code', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Customer ID', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Issue', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Source', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Severity', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Category', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Status', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Retry Count', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Last Retry', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Recommended Action', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('AI Summary', 'kerbcycle-qr-code-manager'); ?></th>
                        <th><?php esc_html_e('Actions', 'kerbcycle-qr-code-manager'); ?></th>
                    </tr>
                </thead>
                <tbody id="kerbcycle-pickup-exceptions-tbody">
                <?php if (empty($records)) : ?>
                    <tr>
                        <td colspan="14"><?php esc_html_e('No pickup exceptions found.', 'kerbcycle-qr-code-manager'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($records as $record) : ?>
                        <tr data-exception-id="<?php echo esc_attr((string) (int) $record->id); ?>">
                            <td><?php echo esc_html($record->id); ?></td>
                            <td><?php echo esc_html($record->submitted_at); ?></td>
                            <td><?php echo esc_html($record->qr_code); ?></td>
                            <td><?php echo esc_html($record->customer_id); ?></td>
                            <td><?php echo esc_html($record->issue); ?></td>
                            <td><?php echo esc_html(!empty($record->source) ? (string) $record->source : '—'); ?></td>
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
                                <button type="button" class="button button-small kerbcycle-view-details" data-exception-id="<?php echo esc_attr((string) (int) $record->id); ?>" aria-expanded="false"><?php esc_html_e('View Details', 'kerbcycle-qr-code-manager'); ?></button>
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
                                    <a href="<?php echo esc_url($retry_url); ?>" class="button button-small kerbcycle-retry-webhook" data-exception-id="<?php echo esc_attr((string) (int) $record->id); ?>"><?php esc_html_e('Retry Webhook', 'kerbcycle-qr-code-manager'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="kerbcycle-pickup-details-row" data-exception-id="<?php echo esc_attr((string) (int) $record->id); ?>">
                            <td colspan="14">
                                <div class="kerbcycle-pickup-details-content">
                                    <p><strong><?php esc_html_e('Issue', 'kerbcycle-qr-code-manager'); ?>:</strong><br><?php echo nl2br(esc_html((string) $record->issue)); ?></p>
                                    <p><strong><?php esc_html_e('Notes', 'kerbcycle-qr-code-manager'); ?>:</strong><br><?php echo nl2br(esc_html((string) $record->notes)); ?></p>
                                    <p><strong><?php esc_html_e('AI Summary', 'kerbcycle-qr-code-manager'); ?>:</strong><br><?php echo nl2br(esc_html((string) $record->ai_summary)); ?></p>
                                    <p><strong><?php esc_html_e('Recommended Action', 'kerbcycle-qr-code-manager'); ?>:</strong><br><?php echo nl2br(esc_html((string) $record->ai_recommended_action)); ?></p>
                                    <p><strong><?php esc_html_e('Webhook Status Code', 'kerbcycle-qr-code-manager'); ?>:</strong> <?php echo esc_html(isset($record->webhook_status_code) && $record->webhook_status_code !== null ? (string) $record->webhook_status_code : ''); ?></p>
                                    <p><strong><?php esc_html_e('Webhook Response Body', 'kerbcycle-qr-code-manager'); ?>:</strong></p>
                                    <pre><?php echo esc_html((string) $record->webhook_response_body); ?></pre>
                                    <p><strong><?php esc_html_e('Submitted At', 'kerbcycle-qr-code-manager'); ?>:</strong> <?php echo esc_html((string) $record->submitted_at); ?></p>
                                    <p><strong><?php esc_html_e('Updated At', 'kerbcycle-qr-code-manager'); ?>:</strong> <?php echo esc_html((string) $record->updated_at); ?></p>
                                    <p><strong><?php esc_html_e('Retry Count', 'kerbcycle-qr-code-manager'); ?>:</strong> <?php echo esc_html((string) (isset($record->retry_count) ? (int) $record->retry_count : 0)); ?></p>
                                    <p><strong><?php esc_html_e('Last Retry', 'kerbcycle-qr-code-manager'); ?>:</strong> <?php echo esc_html(!empty($record->last_retry_at) ? (string) $record->last_retry_at : '—'); ?></p>
                                    <p><strong><?php esc_html_e('Customer ID', 'kerbcycle-qr-code-manager'); ?>:</strong> <?php echo esc_html((string) $record->customer_id); ?></p>
                                    <p><strong><?php esc_html_e('QR Code', 'kerbcycle-qr-code-manager'); ?>:</strong> <?php echo esc_html((string) $record->qr_code); ?></p>
                                    <p><strong><?php esc_html_e('Source', 'kerbcycle-qr-code-manager'); ?>:</strong> <?php echo esc_html(!empty($record->source) ? (string) $record->source : '—'); ?></p>
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
            $this->redirect_with_retry_notice('error', __('Invalid pickup exception ID.', 'kerbcycle-qr-code-manager'));
        }

        check_admin_referer('kerbcycle_retry_pickup_exception_' . $exception_id);

        $retry_result = (new PickupExceptionRetryService())->retry($exception_id);
        if ($retry_result['state'] === 'not_found') {
            $this->redirect_with_retry_notice('error', __('Pickup exception record not found.', 'kerbcycle-qr-code-manager'));
        }

        if ($retry_result['state'] === 'ineligible') {
            $this->redirect_with_retry_notice('error', __('This pickup exception is not eligible for retry.', 'kerbcycle-qr-code-manager'));
        }

        if ($retry_result['state'] === 'lock_conflict') {
            $this->redirect_with_retry_notice('error', __('Retry already in progress for this pickup exception.', 'kerbcycle-qr-code-manager'));
        }

        if ($retry_result['state'] === 'webhook_error') {
            $this->redirect_with_retry_notice('error', __('Retry failed. The record remains saved locally.', 'kerbcycle-qr-code-manager'));
        }

        if ($retry_result['state'] === 'success') {
            $this->redirect_with_retry_notice('success', __('Pickup exception resent successfully.', 'kerbcycle-qr-code-manager'));
        }

        $this->redirect_with_retry_notice('error', __('Retry failed. The record remains saved locally.', 'kerbcycle-qr-code-manager'));
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state preserved in redirect URL after already-handled retry flow.
        if (isset($_GET['status_filter']) && sanitize_key(wp_unslash($_GET['status_filter'])) === 'failed') {
            $redirect_url = add_query_arg('status_filter', 'failed', $redirect_url);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function render_retry_notice()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only retry notice display state; no server-side state is changed here.
        if (!isset($_GET['retry_status'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only retry notice display state; no server-side state is changed here.
        $status = sanitize_key(wp_unslash($_GET['retry_status']));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only retry notice display state; no server-side state is changed here.
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
