<?php

class KerbCycle_API_Integration {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
    }

    public function register_rest_endpoints() {
        register_rest_route('kerbcycle/v1', '/qr-code/scanned', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_qr_code_scan'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('kerbcycle/v1', '/qr-status/(?P<qr_code>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_qr_status'),
            'permission_callback' => '__return_true'
        ));
    }

    public function handle_qr_code_scan(WP_REST_Request $request) {
        $qr_code = sanitize_text_field($request->get_param('qr_code'));
        $user_id = intval($request->get_param('user_id'));

        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';

        $result = $wpdb->insert(
            $table,
            array(
                'qr_code' => $qr_code,
                'user_id' => $user_id,
                'status' => 'assigned',
                'assigned_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s')
        );

        if ($result === false) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Failed to process QR code'), 500);
        }

        return new WP_REST_Response(array('success' => true, 'message' => 'QR code processed', 'qr_code' => $qr_code, 'user_id' => $user_id), 200);
    }

    public function get_qr_status(WP_REST_Request $request) {
        global $wpdb;
        $qr_code = sanitize_text_field($request['qr_code']);
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE qr_code = %s", $qr_code));

        if ($result) {
            return new WP_REST_Response($result, 200);
        } else {
            return new WP_Error('not_found', 'QR Code not found', array('status' => 404));
        }
    }
}
