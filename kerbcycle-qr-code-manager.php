<?php
/*
Plugin Name: KerbCycle QR Code Manager
Description: Manage QR code scanning and assignment with drag-and-drop, inline editing, bulk actions, and notification toggles
Version: 2.0
Author: Your Name
Text Domain: kerbcycle
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin URL constant
if (!defined('KERBCYCLE_QR_URL')) {
    define('KERBCYCLE_QR_URL', plugin_dir_url(__FILE__));
}

// Define plugin PATH constant
if (!defined('KERBCYCLE_QR_PATH')) {
    define('KERBCYCLE_QR_PATH', plugin_dir_path(__FILE__));
}

// Define plugin version constant
if (!defined('KERBCYCLE_QR_VERSION')) {
    define('KERBCYCLE_QR_VERSION', '2.0');
}

// Require the autoloader
require_once KERBCYCLE_QR_PATH . 'includes/Autoloader.php';

// Register the autoloader
\Kerbcycle\QrCode\Autoloader::run();

/**
 * The main function for running the plugin.
 *
 * @return \Kerbcycle\QrCode\Plugin
 */
function kerbcycle_qr_code_manager()
{
    return \Kerbcycle\QrCode\Plugin::instance();
}

// Run the plugin
kerbcycle_qr_code_manager();

// Activation/Deactivation hooks
register_activation_hook(__FILE__, ['\\Kerbcycle\\QrCode\\Install\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\\Kerbcycle\\QrCode\\Install\\Uninstaller', 'deactivate']);
