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
    public function __construct()
    {
    }

    public function handle_retry_webhook()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized request.', 'kerbcycle'));
        }

        $exception_id = isset($_GET['exception_id']) ? absint(wp_unslash($_GET['exception_id'])) : 0;
        if ($exception_id < 1) {
            $this->redirect_with_notice('error', 'invalid_id');
        }

        check_admin_referer('kerbcycle_retry_pickup_exception_' . $exception_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, qr_code, customer_id, issue, notes, submitted_at, webhook_sent
                FROM {$table_name}
                WHERE id = %d",
                $exception_id
            )
        );

        if (!$record) {
            $this->redirect_with_notice('error', 'not_found');
        }

        if ((int) $record->webhook_sent === 1) {
            $this->redirect_with_notice('error', 'ineligible');
        }

        $result = (new QrService())->send_pickup_exception_to_n8n([
            'qr_code'     => (string) $record->qr_code,
            'customer_id' => (int) $record->customer_id,
            'issue'       => (string) $record->issue,
            'notes'       => (string) $record->notes,
            'timestamp'   => (string) $record->submitted_at,
        ]);

        if (is_wp_error($result)) {
            PickupExceptionRepository::update_result($exception_id, [
                'webhook_sent'          => 0,
                'webhook_status_code'   => 0,
                'webhook_response_body' => $result->get_error_message(),
                'ai_severity'           => '',
                'ai_category'           => '',
                'ai_summary'            => '',
                'ai_recommended_action' => '',
                'updated_at'            => current_time('mysql', true),
            ]);
            $this->redirect_with_notice('error', 'failed');
        }

        if (!is_array($result)) {
            PickupExceptionRepository::update_result($exception_id, [
                'webhook_sent'          => 0,
                'webhook_status_code'   => 0,
                'webhook_response_body' => __('Invalid webhook response.', 'kerbcycle'),
                'ai_severity'           => '',
                'ai_category'           => '',
                'ai_summary'            => '',
                'ai_recommended_action' => '',
                'updated_at'            => current_time('mysql', true),
            ]);
            $this->redirect_with_notice('error', 'invalid_result');
        }

        $is_success = !empty($result['success']) || !empty($result['ok']);
        $status_code = isset($result['status_code']) ? (int) $result['status_code'] : 0;
        $body = '';
        if (isset($result['body'])) {
            $body = $result['body'];
        } elseif (isset($result['raw_body'])) {
            $body = $result['raw_body'];
        } elseif (isset($result['response'])) {
            $body = $result['response'];
        }

        if ($is_success) {
            $decoded_body = json_decode((string) $body, true);
            $ai_result = is_array($decoded_body) && isset($decoded_body['result']) && is_array($decoded_body['result']) ? $decoded_body['result'] : $decoded_body;
            $ai_summary = is_array($ai_result) && isset($ai_result['summary']) ? (string) $ai_result['summary'] : '';
            $ai_category = is_array($ai_result) && isset($ai_result['category']) ? (string) $ai_result['category'] : '';
            $ai_severity = is_array($ai_result) && isset($ai_result['severity']) ? (string) $ai_result['severity'] : '';
            $ai_recommended_action = is_array($ai_result) && isset($ai_result['recommended_action']) ? (string) $ai_result['recommended_action'] : '';

            PickupExceptionRepository::update_result($exception_id, [
                'webhook_sent'          => 1,
                'webhook_status_code'   => $status_code,
                'webhook_response_body' => is_scalar($body) ? (string) $body : wp_json_encode($body),
                'ai_severity'           => $ai_severity,
                'ai_category'           => $ai_category,
                'ai_summary'            => $ai_summary,
                'ai_recommended_action' => $ai_recommended_action,
                'updated_at'            => current_time('mysql', true),
            ]);
            $this->redirect_with_notice('success', 'ok');
        }

        PickupExceptionRepository::update_result($exception_id, [
            'webhook_sent'          => 0,
            'webhook_status_code'   => $status_code,
            'webhook_response_body' => is_scalar($body) ? (string) $body : wp_json_encode($body),
            'ai_severity'           => '',
            'ai_category'           => '',
            'ai_summary'            => '',
            'ai_recommended_action' => '',
            'updated_at'            => current_time('mysql', true),
        ]);

        $this->redirect_with_notice('error', 'failed');
    }

    private function redirect_with_notice($type, $code)
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'kerbcycle-pickup-exceptions',
                    'kerbcycle_retry' => sanitize_key((string) $type),
                    'kerbcycle_retry_code' => sanitize_key((string) $code),
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $limit = 50;

        $sql = $wpdb->prepare(
            "SELECT id, submitted_at, qr_code, customer_id, issue, ai_severity, ai_category, webhook_sent, ai_recommended_action, ai_summary
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
            <?php
            $retry_state = isset($_GET['kerbcycle_retry']) ? sanitize_key(wp_unslash($_GET['kerbcycle_retry'])) : '';
            if ($retry_state === 'success') :
                ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Pickup exception webhook retry sent successfully.', 'kerbcycle'); ?></p></div>
            <?php elseif ($retry_state === 'error') : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Pickup exception webhook retry failed.', 'kerbcycle'); ?></p></div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Submitted At', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('QR Code', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Customer ID', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Issue', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Severity', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Category', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Webhook Sent', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Recommended Action', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('AI Summary', 'kerbcycle'); ?></th>
                        <th><?php esc_html_e('Actions', 'kerbcycle'); ?></th>
                    </tr>
                </thead>
                <tbody>
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
                            <td><?php echo esc_html(((int) $record->webhook_sent) === 1 ? 'Yes' : 'No'); ?></td>
                            <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $record->ai_recommended_action), 20, '…')); ?></td>
                            <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $record->ai_summary), 20, '…')); ?></td>
                            <td>
                                <?php if ((int) $record->webhook_sent === 0) : ?>
                                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'kerbcycle_retry_pickup_exception', 'exception_id' => (int) $record->id], admin_url('admin-post.php')), 'kerbcycle_retry_pickup_exception_' . (int) $record->id)); ?>"><?php esc_html_e('Retry Webhook', 'kerbcycle'); ?></a>
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
}

add_action('admin_post_kerbcycle_retry_pickup_exception', function () {
    (new PickupExceptionsPage())->handle_retry_webhook();
});
