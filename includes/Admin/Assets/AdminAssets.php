<?php

namespace Kerbcycle\QrCode\Admin\Assets;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Services\ReportService;

/**
 * The admin assets.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Assets
 */
class AdminAssets
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue the admin scripts.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts($hook)
    {
        if ($hook === 'qr-codes_page_kerbcycle-qr-reports') {
            wp_enqueue_script('chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1', true);
            wp_enqueue_script(
                'kerbcycle-qr-reports',
                KERBCYCLE_QR_URL . 'assets/js/qr-reports.js',
                ['chartjs'],
                '1.0',
                true
            );

            $report_data = (new ReportService())->get_report_data();

            // Use wp_add_inline_script to make data available to the chart script
            wp_add_inline_script(
                'chartjs',
                'const kerbcycleReportData = ' . wp_json_encode($report_data) . ';',
                'after'
            );
            return;
        }

        if (!in_array($hook, ['toplevel_page_kerbcycle-qr-manager', 'kerbcycle-qr-manager_page_kerbcycle-qr-history'])) {
            return;
        }

        $scanner_enabled = (bool) get_option('kerbcycle_qr_enable_scanner', 1);

        // Always enqueue the assign/release script
        wp_enqueue_script(
            'kerbcycle-qr-assign-release-js',
            KERBCYCLE_QR_URL . 'assets/js/qr-assign-release.js',
            ['jquery-ui-sortable'],
            '1.0',
            true
        );

        wp_localize_script('kerbcycle-qr-assign-release-js', 'kerbcycle_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kerbcycle_qr_nonce'),
            'scanner_enabled' => $scanner_enabled,
        ]);

        if ($scanner_enabled) {
            wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', [], null, true);
            wp_enqueue_script(
                'kerbcycle-qr-scanner-js',
                KERBCYCLE_QR_URL . 'assets/js/qr-scanner.js',
                ['html5-qrcode'],
                '1.0',
                true
            );
        }
    }
}
