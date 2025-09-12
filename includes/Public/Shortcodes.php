<?php

namespace Kerbcycle\QrCode\Public;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Public
 */
class Shortcodes
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_shortcode('kerbcycle_scanner', [$this, 'generate_frontend_scanner']);
        add_shortcode('kerbcycle_qr_table', [$this, 'generate_qr_table']);
    }

    /**
     * Generate the frontend scanner.
     *
     * @since    1.0.0
     */
    public function generate_frontend_scanner()
    {
        ob_start();
        ?>
        <div class="kerbcycle-qr-scanner-container">
            <h2>Assign QR Code</h2>
            <p><?php esc_html_e('Select the customer and scan the QR code to assign it.', 'kerbcycle'); ?></p>
            <?php
            wp_dropdown_users([
                'name'             => 'customer_id',
                'id'               => 'customer-id',
                'class'            => 'kc-searchable',
                'show_option_none' => __('Select Customer', 'kerbcycle')
            ]);
        ?>
            <button id="assign-qr-btn" class="button button-primary">Assign QR Code</button>
            <div id="reader" style="width: 100%; max-width: 400px; margin-top: 20px;"></div>
            <div id="scan-result" class="updated" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate a table of QR codes for the frontend.
     *
     * @since    1.0.0
     */
    public function generate_qr_table()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $codes = $wpdb->get_results("SELECT id, qr_code, user_id, display_name, status, assigned_at FROM $table ORDER BY id DESC");

        ob_start();
        ?>
        <style>
            .kerbcycle-qr-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 1em;
            }
            .kerbcycle-qr-table th,
            .kerbcycle-qr-table td {
                border: 1px solid #c3c4c7;
                padding: 8px;
                text-align: left;
            }
            .kerbcycle-qr-table th {
                background: #f0f0f1;
                font-weight: 600;
            }
            .kerbcycle-qr-pagination {
                text-align: center;
                margin-top: 1em;
            }
            .kerbcycle-qr-pagination button {
                margin: 0 2px;
                padding: 4px 8px;
                cursor: pointer;
            }
            .kerbcycle-qr-pagination button.active {
                background: #2271b1;
                color: #fff;
            }
        </style>
        <table class="kerbcycle-qr-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'kerbcycle'); ?></th>
                    <th><?php esc_html_e('QR Code', 'kerbcycle'); ?></th>
                    <th><?php esc_html_e('User ID', 'kerbcycle'); ?></th>
                    <th><?php esc_html_e('Customer', 'kerbcycle'); ?></th>
                    <th><?php esc_html_e('Status', 'kerbcycle'); ?></th>
                    <th><?php esc_html_e('Assigned At', 'kerbcycle'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($codes)) : ?>
                    <?php foreach ($codes as $code) : ?>
                        <tr>
                            <td><?= esc_html($code->id); ?></td>
                            <td><?= esc_html($code->qr_code); ?></td>
                            <td><?= $code->user_id ? esc_html($code->user_id) : '—'; ?></td>
                            <td><?= $code->display_name ? esc_html($code->display_name) : '—'; ?></td>
                            <td><?= esc_html(ucfirst($code->status)); ?></td>
                            <td><?= $code->assigned_at ? esc_html($code->assigned_at) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6" class="description"><?php esc_html_e('No QR codes found', 'kerbcycle'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="kerbcycle-qr-pagination" data-rows="10"></div>
        <?php
        return ob_get_clean();
    }
}
