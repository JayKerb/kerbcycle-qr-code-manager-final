<?php
/*
Plugin Name: KerbCycle QR Code Manager
Description: Manage QR code scanning and assignment with drag-and-drop, inline editing, bulk actions, and notification toggles
Version: 1.4
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KERBCYCLE_QR_VERSION', '1.4');
define('KERBCYCLE_QR_PATH', plugin_dir_path(__FILE__));
define('KERBCYCLE_QR_URL', plugin_dir_url(__FILE__));

// Autoloader for plugin classes
spl_autoload_register('kerbcycle_qr_autoloader');

function kerbcycle_qr_autoloader($class_name) {
    // If the class name does not start with 'KerbCycle_', do nothing
    if (strpos($class_name, 'KerbCycle_') !== 0) {
        return;
    }

    // Remove the prefix
    $class_file = str_replace('KerbCycle_', '', $class_name);
    // Convert to lowercase and replace underscores with hyphens
    $class_file = strtolower($class_file);
    $class_file = str_replace('_', '-', $class_file);

    // Build the file path
    $file_path = KERBCYCLE_QR_PATH . 'includes/class-' . $class_file . '.php';

    // If the file exists, require it
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}

// Activation and deactivation hooks
register_activation_hook(__FILE__, ['KerbCycle_QR_Code_Manager', 'activate']);
register_deactivation_hook(__FILE__, ['KerbCycle_QR_Code_Manager', 'deactivate']);

// Initialize the plugin
function kerbcycle_qr_run() {
    // The main plugin class is loaded by the autoloader
    if (class_exists('KerbCycle_QR_Code_Manager')) {
        new KerbCycle_QR_Code_Manager();
    }
}
add_action('plugins_loaded', 'kerbcycle_qr_run');
