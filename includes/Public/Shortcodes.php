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
        add_shortcode('kerbcycle_osrm_map', [$this, 'osrm_map_shortcode']);
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
        <div class="kerbcycle-qr-scanner-container kc-compact">
            <h2>Assign QR Code</h2>
            <p><?php esc_html_e('Select the customer and scan the QR code to assign it.', 'kerbcycle'); ?></p>
        <?php
        wp_dropdown_users([
            'name'             => 'customer_id',
            'id'               => 'customer-id',
            'class'            => 'kc-searchable',
            'show_option_none' => __('Select Customer', 'kerbcycle'),
            'option_none_value' => '',
        ]);
        ?>
            <script>
                (function () {
                    const select = document.getElementById('customer-id');
                    if (!select) {
                        return;
                    }

                    select.setAttribute('data-placeholder', '<?php echo esc_js(__('Select Customer', 'kerbcycle')); ?>');
                    select.setAttribute('data-resettable', 'true');
                    select.setAttribute('data-reset-label', '<?php echo esc_js(__('Reset', 'kerbcycle')); ?>');
                })();
            </script>
            <div class="kerbcycle-scanner-actions">
                <button id="assign-qr-btn" class="button button-primary">Assign QR Code</button>
                <button id="reset-scan-btn" class="button button-primary"><?php esc_html_e('Scan Reset', 'kerbcycle'); ?></button>
                <button id="report-exception-btn" class="button"><?php esc_html_e('Report Exception', 'kerbcycle'); ?></button>
            </div>
            <div id="scanner-exception-form-wrap" style="display:none; margin-top:12px;">
                <input type="text" id="scanner-exception-qr-code" placeholder="<?php esc_attr_e('QR Code', 'kerbcycle'); ?>" style="width:100%; margin-bottom:8px;" />
                <input type="number" id="scanner-exception-customer-id" min="1" step="1" placeholder="<?php esc_attr_e('Customer ID', 'kerbcycle'); ?>" style="width:100%; margin-bottom:8px;" />
                <input type="text" id="scanner-exception-issue" placeholder="<?php esc_attr_e('Issue (required)', 'kerbcycle'); ?>" style="width:100%; margin-bottom:8px;" />
                <textarea id="scanner-exception-notes" rows="3" placeholder="<?php esc_attr_e('Notes', 'kerbcycle'); ?>" style="width:100%; margin-bottom:8px;"></textarea>
                <button id="scanner-submit-exception" class="button button-primary"><?php esc_html_e('Submit Exception', 'kerbcycle'); ?></button>
                <div id="scanner-exception-status" class="updated" style="display:none; margin-top:10px;"></div>
            </div>
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
        $codes = $wpdb->get_results(
            "SELECT id, qr_code, user_id, display_name, status, assigned_at FROM $table"
            . " ORDER BY assigned_at DESC, id DESC"
        );

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
        <!-- Wrap the table so compact CSS/JS can target it -->
        <div class="kerbcycle-qr-scanner-container kc-compact">
            <div class="kerbcycle-table-wrap">
                <table class="kerbcycle-qr-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th data-short="ID"><?php esc_html_e('ID', 'kerbcycle'); ?></th>
                            <th data-short="QR"><?php esc_html_e('QR Code', 'kerbcycle'); ?></th>
                            <th data-short="UID"><?php esc_html_e('User ID', 'kerbcycle'); ?></th>
                            <th data-short="Cust"><?php esc_html_e('Customer', 'kerbcycle'); ?></th>
                            <th data-short="Sts"><?php esc_html_e('Status', 'kerbcycle'); ?></th>
                            <th data-short="At"><?php esc_html_e('Assigned At', 'kerbcycle'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($codes)) : ?>
                            <?php foreach ($codes as $code) : ?>
                                <tr data-qr-code="<?= esc_attr($code->qr_code); ?>">
                                    <td><?= esc_html($code->id); ?></td>
                                    <td title="<?= esc_attr($code->qr_code); ?>"><?= esc_html($code->qr_code); ?></td>
                                    <td><?= $code->user_id ? esc_html($code->user_id) : '—'; ?></td>
                                    <td
                                        title="<?= $code->display_name ? esc_attr($code->display_name) : '—'; ?>"
                                    >
                                        <?= $code->display_name ? esc_html($code->display_name) : '—'; ?>
                                    </td>
                                    <td><?= esc_html(ucfirst($code->status)); ?></td>
                                    <td
                                        class="kc-date"
                                        title="<?= $code->assigned_at ? esc_attr($code->assigned_at) : '—'; ?>"
                                        data-full="<?= $code->assigned_at ? esc_attr($code->assigned_at) : ''; ?>"
                                    >
                                        <?= $code->assigned_at ? esc_html($code->assigned_at) : '—'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="description"><?php esc_html_e('No QR codes found', 'kerbcycle'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="kerbcycle-qr-pagination" data-rows="10"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode renderer for the OSRM map.
     */
    public function osrm_map_shortcode($atts)
    {
        $atts = shortcode_atts([
            'start'  => '40.730,-73.990', // lat,lon
            'end'    => '40.780,-73.970', // lat,lon
            'height' => '420px',
            'zoom'   => 12,
            'id'     => 'kc-osrm-'.wp_generate_uuid4(),
        ], $atts, 'kerbcycle_osrm_map');

        // enqueue assets
        wp_enqueue_style('leaflet');
        wp_enqueue_style('lrm');
        wp_enqueue_style('leaflet-geocoder');
        wp_enqueue_script('leaflet');
        wp_enqueue_script('lrm');
        wp_enqueue_script('leaflet-geocoder');
        wp_enqueue_script('kc-osrm'); // <-- THIS defines KC_OSRM via wp_localize_script

        // output container only
        $id     = esc_attr($atts['id']);
        $start  = esc_js($atts['start']);
        $end    = esc_js($atts['end']);
        $zoom   = (int)$atts['zoom'];
        $height = esc_attr($atts['height']);

        // push params to a queue that kc-osrm.js will consume
        $payload = wp_json_encode([
            'id'    => $id,
            'start' => $start,
            'end'   => $end,
            'zoom'  => $zoom,
        ]);

        // Ensure inline runs AFTER kc-osrm is present
        wp_add_inline_script('kc-osrm', "window.KC_OSRM_QUEUE=(window.KC_OSRM_QUEUE||[]).concat([$payload]);", 'after');

        return '<div id="'.$id.'" class="kc-osrm-container" style="height:'.$height.';"></div>';
    }
}
