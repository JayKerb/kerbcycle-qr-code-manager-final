<?php

namespace Kerbcycle\QrCode\Admin\Pages;

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
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records)) : ?>
                    <tr>
                        <td colspan="10"><?php esc_html_e('No pickup exceptions found.', 'kerbcycle'); ?></td>
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
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
