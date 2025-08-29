<?php

namespace Kerbcycle\QrCode\Api;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The rest service.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Api
 */
class RestService
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register the routes for the objects of the controller.
     *
     * @since    1.0.0
     */
    public function register_routes()
    {
        register_rest_route('kerbcycle/v1', '/qr-code/scanned', [
            'methods' => 'POST',
            'callback' => [new Controllers\QrController(), 'handle_qr_code_scan'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('kerbcycle/v1', '/qr-status/(?P<qr_code>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [new Controllers\QrController(), 'get_qr_status'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }
}
