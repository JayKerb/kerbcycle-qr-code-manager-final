<?php

namespace Kerbcycle\QrCode\Api\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

/**
 * Option B AI endpoint controller.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Api\Controllers
 */
class AiController
{
    /**
     * Validate endpoint permissions for admin-only access.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return true|\WP_Error
     */
    public function permissions(WP_REST_Request $request)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('rest_forbidden', __('Unauthorized', 'kerbcycle'), ['status' => 403]);
        }

        $nonce = sanitize_text_field($request->get_header('X-WP-Nonce'));
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('rest_nonce_invalid', __('Security check failed', 'kerbcycle'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Dispatch Option B AI actions.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return WP_REST_Response|\WP_Error
     */
    public function dispatch_action(WP_REST_Request $request)
    {
        $action = sanitize_key($request->get_param('action'));

        if (empty($action)) {
            return new \WP_Error('kerbcycle_ai_action_missing', __('Missing action parameter.', 'kerbcycle'), ['status' => 400]);
        }

        switch ($action) {
            case 'pickup_summary':
                return new WP_REST_Response([
                    'success' => true,
                    'action'  => $action,
                    'data'    => [
                        'summary' => __('Mock pickup summary response.', 'kerbcycle'),
                    ],
                ], 200);
            case 'qr_exceptions':
                return new WP_REST_Response([
                    'success' => true,
                    'action'  => $action,
                    'data'    => [
                        'exceptions' => [],
                        'message'    => __('Mock QR exceptions response.', 'kerbcycle'),
                    ],
                ], 200);
            case 'draft_template':
                return new WP_REST_Response([
                    'success'  => true,
                    'action'   => $action,
                    'data'     => [
                        'template' => __('Mock draft template response.', 'kerbcycle'),
                    ],
                ], 200);
            default:
                return new \WP_Error('kerbcycle_ai_action_invalid', __('Invalid action parameter.', 'kerbcycle'), ['status' => 400]);
        }
    }
}
