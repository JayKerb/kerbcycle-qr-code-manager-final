<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The reports page.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Pages
 */
class ReportsPage
{
    /**
     * Render the reports page.
     *
     * @since    1.0.0
     */
    public function render()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('QR Code Reports', 'kerbcycle'); ?></h1>
            <h2><?php esc_html_e('Today\'s Assignments', 'kerbcycle'); ?></h2>
            <canvas id="qr-daily-chart" style="max-width:600px;width:100%;"></canvas>
            <h2><?php esc_html_e('Weekly Assignments', 'kerbcycle'); ?></h2>
            <canvas id="qr-report-chart" style="max-width:600px;width:100%;"></canvas>
        </div>
        <?php
    }
}
