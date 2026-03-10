<?php

/*
Plugin Name: KerbCycle QR Code Manager
Description: Manage QR code scanning and assignment with drag-and-drop, inline editing, bulk actions, and notification toggles
Version: 2.0.1
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
    // bump to bust cached CSS/JS after compact layout changes
    define('KERBCYCLE_QR_VERSION', '2.0.3');
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

/**
 * Call the configured AI endpoint with a protected JSON payload.
 *
 * @param string $task
 * @param array  $data
 *
 * @return array<string,mixed>
 */
function kc_call_ai_endpoint($task, $data = array())
{
    $task = sanitize_text_field($task);
    $data = is_array($data) ? $data : array();

    if ($task === '') {
        return array(
            'success' => false,
            'message' => __('AI task is required.', 'kerbcycle'),
            'data'    => null,
        );
    }

    $endpoint = defined('KERBCYCLE_AI_ENDPOINT') ? KERBCYCLE_AI_ENDPOINT : get_option('kerbcycle_ai_endpoint', '');
    $api_key  = defined('KERBCYCLE_AI_API_KEY') ? KERBCYCLE_AI_API_KEY : get_option('kerbcycle_ai_api_key', '');

    $endpoint = is_string($endpoint) ? trim($endpoint) : '';
    $api_key  = is_string($api_key) ? trim($api_key) : '';

    if ($endpoint === '' || $api_key === '') {
        return array(
            'success' => false,
            'message' => __('AI endpoint is not configured.', 'kerbcycle'),
            'data'    => null,
        );
    }

    $response = wp_remote_post($endpoint, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key'    => $api_key,
        ),
        'body'    => wp_json_encode(array(
            'task' => $task,
            'data' => $data,
        )),
        'timeout' => 20,
    ));

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => $response->get_error_message(),
            'data'    => null,
        );
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body    = wp_remote_retrieve_body($response);

    if ($status_code !== 200) {
        return array(
            'success' => false,
            'message' => sprintf(__('AI endpoint returned HTTP %d.', 'kerbcycle'), $status_code),
            'data'    => $raw_body,
        );
    }

    $json = json_decode($raw_body, true);
    if (!is_array($json)) {
        return array(
            'success' => false,
            'message' => __('AI endpoint returned invalid JSON.', 'kerbcycle'),
            'data'    => $raw_body,
        );
    }

    return array(
        'success' => true,
        'message' => '',
        'data'    => $json,
    );
}

// Run the plugin
kerbcycle_qr_code_manager();

// Activation/Deactivation hooks
register_activation_hook(__FILE__, ['\\Kerbcycle\\QrCode\\Install\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\\Kerbcycle\\QrCode\\Install\\Uninstaller', 'deactivate']);
