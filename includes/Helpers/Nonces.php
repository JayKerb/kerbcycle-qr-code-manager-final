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
     * @param string $action The nonce action.
     * @param string $field  The request field containing the nonce.
     *
     * @return bool True when the nonce is valid, false otherwise.
     */
    public static function verify($action, $field)
    {
        $nonce = isset($_REQUEST[$field]) ? sanitize_text_field(wp_unslash($_REQUEST[$field])) : '';
        return (bool)wp_verify_nonce($nonce, $action);
    }
}
