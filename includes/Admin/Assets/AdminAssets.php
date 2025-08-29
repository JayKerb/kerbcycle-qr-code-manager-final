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
            wp_register_script(
                'chartjs',
                KERBCYCLE_QR_URL . 'assets/js/vendor/chart.min.js',
                [],
                '3.9.1',
                true
            );
            wp_enqueue_script('chartjs');
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

        wp_register_script(
            'html5-qrcode',
            KERBCYCLE_QR_URL . 'assets/js/vendor/html5-qrcode.min.js',
            [],
            null,
            true
        );
        wp_enqueue_script('html5-qrcode');
        wp_enqueue_script(
            'kerbcycle-qr-js',
            KERBCYCLE_QR_URL . 'assets/js/admin.js',
            ['html5-qrcode', 'jquery-ui-sortable'],
            '1.0',
            true
        );

        wp_localize_script('kerbcycle-qr-js', 'kerbcycle_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kerbcycle_qr_nonce')
        ]);

        wp_localize_script('kerbcycle-qr-js', 'kerbcycle_i18n', [
            'select_user'        => __('Please select a user and scan or choose a QR code.', 'kerbcycle-qr-code-manager'),
            'assign_success'     => __('QR code assigned successfully.', 'kerbcycle-qr-code-manager'),
            'sms_sent'           => __('SMS notification sent.', 'kerbcycle-qr-code-manager'),
            'sms_failed'         => __('SMS failed:', 'kerbcycle-qr-code-manager'),
            'unknown_error'      => __('Unknown error', 'kerbcycle-qr-code-manager'),
            'assign_failed'      => __('Failed to assign QR code.', 'kerbcycle-qr-code-manager'),
            'assign_error'       => __('An error occurred while assigning the QR code.', 'kerbcycle-qr-code-manager'),
            'scan_or_select'     => __('Please scan or select a QR code to release.', 'kerbcycle-qr-code-manager'),
            'release_success'    => __('QR code released successfully.', 'kerbcycle-qr-code-manager'),
            'release_failed'     => __('Failed to release QR code.', 'kerbcycle-qr-code-manager'),
            'release_error'      => __('An error occurred while releasing the QR code.', 'kerbcycle-qr-code-manager'),
            'bulk_select'        => __('Please select one or more QR codes to release.', 'kerbcycle-qr-code-manager'),
            'bulk_confirm'       => __('Are you sure you want to release the selected QR codes?', 'kerbcycle-qr-code-manager'),
            'error_prefix'       => __('Error:', 'kerbcycle-qr-code-manager'),
            'unexpected_error'   => __('An unexpected error occurred. Please try again.', 'kerbcycle-qr-code-manager'),
            'scan_success'       => __('QR Code Scanned Successfully!', 'kerbcycle-qr-code-manager'),
            'content_label'      => __('Content:', 'kerbcycle-qr-code-manager'),
            'update_failed'      => __('Failed to update QR code', 'kerbcycle-qr-code-manager'),
        ]);
    }
}
