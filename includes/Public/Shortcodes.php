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
        $atts = shortcode_atts(
            [
                'start'  => '40.730,-73.990',
                'end'    => '40.780,-73.970',
                'height' => '420px',
                'zoom'   => 12,
            ],
            $atts,
            'kerbcycle_osrm_map'
        );

        $element_id = 'kc-osrm-' . wp_generate_uuid4();
        ob_start();
        ?>
        <div id="<?php echo esc_attr($element_id); ?>" style="height:<?php echo esc_attr($atts['height']); ?>;position:relative;"></div>
        <script>
        KC_OSRM.ready(function(KC_OSRM) {
            var el = document.getElementById('<?php echo esc_js($element_id); ?>');
            function showMsg(el, msg) {
                el.innerHTML = '<div style="padding:.5rem;border:1px solid #e33;background:#fee;position:absolute;top:0;left:0;right:0;z-index:999;">' + msg + '</div>';
            }

            try {
                var start = "<?php echo esc_js($atts['start']); ?>".split(',').map(Number);
                var end = "<?php echo esc_js($atts['end']); ?>".split(',').map(Number);
                var zoom = <?php echo (int) $atts['zoom']; ?>;

                if (start.length !== 2 || end.length !== 2 || isNaN(start[0]) || isNaN(end[0])) {
                    showMsg(el, '<strong>Error:</strong> Invalid start/end coordinates. Expected format: "lat,lon".');
                    return;
                }

                var map = L.map(el).setView(start, zoom);
                window._kcMap = map; // For external hooks like invalidateSize()
                L.tileLayer(KC_OSRM.tileUrl, { attribution: KC_OSRM.tileAttrib }).addTo(map);

                var control = L.Routing.control({
                    waypoints: [ L.latLng(start[0], start[1]), L.latLng(end[0], end[1]) ],
                    router: L.Routing.osrmv1({
                        serviceUrl: KC_OSRM.endpoint.replace(/\/route\/v1\/.*$/, '/route/v1')
                    })
                }).on('routingerror', function(e) {
                    var message = (e && e.error && e.error.message) ? e.error.message : 'Unknown reason.';
                    showMsg(el, '<strong>Routing Error:</strong> ' + message);
                }).addTo(map);

            } catch (e) {
                showMsg(el, '<strong>Map Init Error:</strong> ' + e.message);
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
