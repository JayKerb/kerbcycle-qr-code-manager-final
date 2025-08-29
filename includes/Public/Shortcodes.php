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
            <p>Enter the customer ID and scan the QR code to assign it.</p>
            <input type="number" id="customer-id" class="regular-text" placeholder="Enter Customer ID" />
            <button id="assign-qr-btn" class="button button-primary">Assign QR Code</button>
            <div id="reader" style="width: 100%; max-width: 400px; margin-top: 20px;"></div>
            <div id="scan-result" class="updated" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
