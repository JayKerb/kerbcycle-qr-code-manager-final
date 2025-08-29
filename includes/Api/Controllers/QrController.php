<?php

namespace Kerbcycle\QrCode\Api\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Kerbcycle\QrCode\Helpers\Nonces;

/**
 * The qr controller.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Api\Controllers
 */
class QrController
{
    /**
     * Handle the QR code scan.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return WP_REST_Response The response object.
     * @since    1.0.0
     *
     */
    public function handle_qr_code_scan(WP_REST_Request $request)
    {
        if (!Nonces::verify('wp_rest', '_wpnonce')) {
            return new \WP_Error('rest_nonce_invalid', 'Invalid nonce', ['status' => 403]);
        }

        $qr_code = sanitize_text_field($request->get_param('qr_code'));
        $user_id = intval($request->get_param('user_id'));

        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';

        $result = $wpdb->insert(
            $table,
            [
                'qr_code' => $qr_code,
                'user_id' => $user_id,
                'status' => 'assigned',
                'assigned_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to process QR code'
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'QR code processed',
            'qr_code' => $qr_code,
            'user_id' => $user_id
        ], 200);
    }

    /**
     * Get the status of a QR code.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return WP_REST_Response The response object.
     * @since    1.0.0
     *
     */
    public function get_qr_status(WP_REST_Request $request)
    {
        if (!Nonces::verify('wp_rest', '_wpnonce')) {
            return new \WP_Error('rest_nonce_invalid', 'Invalid nonce', ['status' => 403]);
        }

        global $wpdb;
        $qr_code = sanitize_text_field($request['qr_code']);
        $table   = $wpdb->prefix . 'kerbcycle_qr_codes';
        $result  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE qr_code = %s", $qr_code));
        return $result ? rest_ensure_response($result)
                       : new \WP_Error('not_found', 'QR Code not found', ['status' => 404]);
    }
}
