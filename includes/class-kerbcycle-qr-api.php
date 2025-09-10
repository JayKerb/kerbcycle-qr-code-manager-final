<?php

class KerbCycle_QR_API
{
    public function __construct()
    {
        add_action('rest_api_init', function () {
            register_rest_route('kerbcycle/v1', '/qr-status/(?P<qr_code>[a-zA-Z0-9-]+)', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_qr_status'),
                'permission_callback' => '__return_true'
            ));
        });
    }

    public function get_qr_status($request)
    {
        global $wpdb;
        $qr_code = sanitize_text_field($request['qr_code']);
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kerbcycle_qr_codes WHERE qr_code = %s", $qr_code));
        return $result ? rest_ensure_response($result) : new WP_Error('not_found', 'QR Code not found', ['status' => 404]);
    }
}
