<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The dashboard page.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Pages
 */
class DashboardPage
{
    /**
     * Render the dashboard page.
     *
     * @since    1.0.0
     */
    public function render()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $available_codes = $wpdb->get_results("SELECT qr_code FROM $table WHERE status = 'available' ORDER BY id DESC");
        $all_codes = $wpdb->get_results("SELECT id, qr_code, user_id, status, assigned_at FROM $table ORDER BY id DESC");
        ?>
        <style>
            #qr-code-list {
                display: flex;
                flex-direction: column;
                padding: 0;
                border: 1px solid #c3c4c7;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
                background: #fff;
            }
            .qr-header, .qr-item {
                display: flex;
                align-items: center;
                border-bottom: 1px solid #c3c4c7;
                padding: 8px 10px;
            }
            .qr-header {
                font-weight: 600;
                background: #f0f0f1;
            }
            .qr-item:last-child {
                border-bottom: none;
            }
            .qr-select {
                flex-basis: 30px;
                flex-shrink: 0;
                margin-right: 10px;
            }
            .qr-id, .qr-user, .qr-status {
                flex-basis: 80px;
                padding: 0 8px;
            }
            .qr-text {
                flex-grow: 1;
                padding: 0 8px;
            }
            .qr-assigned {
                flex-basis: 150px;
                padding: 0 8px;
            }
        </style>
        <div class="wrap">
            <h1>KerbCycle QR Code Manager</h1>
            <div class="notice notice-info">
                <p><?php esc_html_e('Select a customer and scan or choose a QR code to assign.', 'kerbcycle'); ?></p>
            </div>
            <div id="qr-scanner-container">
                <?php
                wp_dropdown_users(array(
                    'name' => 'customer_id',
                    'id' => 'customer-id',
                    'show_option_none' => __('Select Customer', 'kerbcycle')
                ));
                ?>
                <select id="qr-code-select">
                    <option value=""><?php esc_html_e('Select QR Code', 'kerbcycle'); ?></option>
                    <?php foreach ($available_codes as $code) : ?>
                        <option value="<?= esc_attr($code->qr_code); ?>"><?= esc_html($code->qr_code); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php
                $email_enabled    = (bool) get_option('kerbcycle_qr_enable_email', 1);
                $sms_enabled      = (bool) get_option('kerbcycle_qr_enable_sms', 0);
                $reminder_enabled = (bool) get_option('kerbcycle_qr_enable_reminders', 0);
                $scanner_enabled  = (bool) get_option('kerbcycle_qr_enable_scanner', 1);
                ?>
                <label><input type="checkbox" id="send-email" <?php checked($email_enabled); ?> <?php disabled(!$email_enabled); ?>> <?php esc_html_e('Send notification email', 'kerbcycle'); ?></label>
                <label><input type="checkbox" id="send-sms" <?php checked($sms_enabled); ?> <?php disabled(!$sms_enabled); ?>> <?php esc_html_e('Send SMS', 'kerbcycle'); ?></label>
                <label><input type="checkbox" id="send-reminder" <?php checked($reminder_enabled); ?> <?php disabled(!$reminder_enabled); ?>> <?php esc_html_e('Schedule reminder', 'kerbcycle'); ?></label>
                <p>
                    <button id="assign-qr-btn" class="button button-primary"><?php esc_html_e('Assign QR Code', 'kerbcycle'); ?></button>
                    <button id="release-qr-btn" class="button"><?php esc_html_e('Release QR Code', 'kerbcycle'); ?></button>
                </p>
                <?php if ($scanner_enabled) : ?>
                    <div id="reader" style="width: 100%; max-width: 400px; margin-top: 20px;"></div>
                <?php else : ?>
                    <div class="notice notice-warning" style="margin-top: 20px;">
                        <p><?php esc_html_e('QR code scanner camera is disabled in settings.', 'kerbcycle'); ?></p>
                    </div>
                <?php endif; ?>
                <div id="scan-result" class="updated" style="display: none;"></div>
            </div>

            <h2><?php esc_html_e('Manage QR Codes', 'kerbcycle'); ?></h2>
            <p class="description"><?php esc_html_e('Drag and drop to reorder, select multiple codes for bulk actions, or click a code to edit.', 'kerbcycle'); ?></p>
            <form id="qr-code-bulk-form">
                <ul id="qr-code-list">
                    <li class="qr-header">
                        <input type="checkbox" class="qr-select" disabled style="visibility:hidden" aria-hidden="true" />
                        <span class="qr-id"><?php esc_html_e('ID', 'kerbcycle'); ?></span>
                        <span class="qr-text"><?php esc_html_e('QR Code', 'kerbcycle'); ?></span>
                        <span class="qr-user"><?php esc_html_e('User ID', 'kerbcycle'); ?></span>
                        <span class="qr-status"><?php esc_html_e('Status', 'kerbcycle'); ?></span>
                        <span class="qr-assigned"><?php esc_html_e('Assigned At', 'kerbcycle'); ?></span>
                    </li>
                    <?php foreach ($all_codes as $code) : ?>
                        <li class="qr-item" data-code="<?= esc_attr($code->qr_code); ?>" data-id="<?= esc_attr($code->id); ?>">
                            <input type="checkbox" class="qr-select" />
                            <span class="qr-id"><?= esc_html($code->id); ?></span>
                            <span class="qr-text" contenteditable="true"><?= esc_html($code->qr_code); ?></span>
                            <span class="qr-user"><?= $code->user_id ? esc_html($code->user_id) : '—'; ?></span>
                            <span class="qr-status"><?= esc_html(ucfirst($code->status)); ?></span>
                            <span class="qr-assigned"><?= $code->assigned_at ? esc_html($code->assigned_at) : '—'; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <select id="bulk-action">
                    <option value=""><?php esc_html_e('Bulk actions', 'kerbcycle'); ?></option>
                    <option value="release"><?php esc_html_e('Release', 'kerbcycle'); ?></option>
                </select>
                <button id="apply-bulk" class="button"><?php esc_html_e('Apply', 'kerbcycle'); ?></button>
            </form>
        </div>
        <?php
    }
}
