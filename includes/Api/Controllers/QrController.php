<?php

namespace Kerbcycle\QrCode\Api\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Kerbcycle\QrCode\Helpers\Nonces;
use Kerbcycle\QrCode\Services\QrService;
use Kerbcycle\QrCode\Data\Repositories\QrCodeRepository;

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
        if (!current_user_can('edit_posts')) {
            return new \WP_Error('rest_forbidden', __('Unauthorized', 'kerbcycle'), ['status' => 403]);
        }

        $qr_code = sanitize_text_field($request->get_param('qr_code'));
        $user_id = intval($request->get_param('user_id'));

        $nonce_check = Nonces::verify('kerbcycle_qr_nonce', 'nonce', $request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        $repo = new QrCodeRepository();
        $existing = $repo->find_by_qr_code($qr_code);
        if ($existing && $existing->status === 'assigned') {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('QR code already assigned.', 'kerbcycle'),
            ], 409);
        }

        $service = new QrService();
        $assign = $service->assign($qr_code, $user_id, false, false, false);
        if (is_wp_error($assign)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $assign->get_error_message(),
            ], 500);
        }

        $user  = get_userdata($user_id);
        $name  = $user ? $user->display_name : '';

        return new WP_REST_Response([
            'success'      => true,
            'message'      => __('QR code processed', 'kerbcycle'),
            'qr_code'      => $qr_code,
            'user_id'      => $user_id,
            'display_name' => $name,
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
        global $wpdb;
        $qr_code = sanitize_text_field($request['qr_code']);
        $table   = $wpdb->prefix . 'kerbcycle_qr_codes';
        $result  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE qr_code = %s", $qr_code));
        return $result ? rest_ensure_response($result)
                       : new \WP_Error('not_found', 'QR Code not found', ['status' => 404]);
    }
}
