<?php
/*
Plugin Name: KerbCycle QR Code Manager
Description: Manages QR code scanning and assignment for customers with frontend shortcode
Version: 1.1
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin URL constant
if (!defined('KERBCYCLE_QR_URL')) {
    define('KERBCYCLE_QR_URL', plugin_dir_url(__FILE__));
}

// Include the main plugin class
require_once __DIR__ . '/includes/class-kerbcycle-qr-manager.php';
require_once __DIR__ . '/includes/class-kerbcycle-qr-api.php';

// Instantiate the plugin
if (class_exists('KerbCycle_QR_Manager')) {
    new KerbCycle_QR_Manager();
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, array('KerbCycle_QR_Manager', 'activate'));
register_deactivation_hook(__FILE__, array('KerbCycle_QR_Manager', 'deactivate'));