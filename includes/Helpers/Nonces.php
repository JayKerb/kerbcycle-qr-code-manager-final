<?php

namespace Kerbcycle\QrCode\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The nonces helper.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Helpers
 */
class Nonces
{
    /**
     * Verify a nonce value.
     *
     * @param string               $action  Action name.
     * @param string               $field   Field name containing the nonce.
     * @param \WP_REST_Request|null $request Optional REST request.
     *
     * @return bool|\WP_Error True if valid, WP_Error otherwise (REST).
     */
    public static function verify($action, $field = '_wpnonce', $request = null)
    {
        $nonce = '';
        if ($request instanceof \WP_REST_Request) {
            $nonce = $request->get_param($field);
        } elseif (isset($_REQUEST[$field])) {
            $nonce = $_REQUEST[$field];
        }

        $nonce = sanitize_text_field($nonce);

        if (!wp_verify_nonce($nonce, $action)) {
            $message = __('Security check failed', 'kerbcycle');

            if ($request instanceof \WP_REST_Request) {
                return new \WP_Error('rest_nonce_invalid', $message, ['status' => 403]);
            }

            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => $message], 403);
            }

            wp_die($message, __('Error', 'kerbcycle'), 403);
        }

        return true;
    }
}
