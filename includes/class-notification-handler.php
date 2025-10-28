<?php

class KerbCycle_Notification_Handler {

    public function __construct() {
        // The reminder hook is registered in the main class,
        // as it's a good central place to manage cron jobs.
    }

    public function send_notification_email($user_id, $qr_code) {
        $admin_email = get_option('admin_email');
        $subject = 'QR Code Assignment Notification';
        $message = sprintf("User #%d has been assigned QR code %s\n\nTimestamp: %s", $user_id, $qr_code, current_time('mysql'));
        wp_mail($admin_email, $subject, $message);
    }

    public function send_notification_sms($user_id, $qr_code) {
        $sid   = get_option('kerbcycle_twilio_sid');
        $token = get_option('kerbcycle_twilio_token');
        $from  = get_option('kerbcycle_twilio_from');
        $to    = get_user_meta($user_id, 'phone_number', true) ?: get_user_meta($user_id, 'billing_phone', true);

        if (empty($sid) || empty($token) || empty($from) || empty($to)) {
            return new WP_Error('sms_config', __('Missing SMS configuration or phone number', 'kerbcycle'));
        }

        $body = sprintf(__('You have been assigned QR code %s', 'kerbcycle'), $qr_code);

        $response = wp_remote_post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", array(
            'body'    => array('From' => $from, 'To' => $to, 'Body' => $body),
            'headers' => array('Authorization' => 'Basic ' . base64_encode($sid . ':' . $token)),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            return true;
        }

        $resp_body = wp_remote_retrieve_body($response);
        $decoded   = json_decode($resp_body, true);
        $error     = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : __('Unknown error', 'kerbcycle');
        return new WP_Error('sms_failed', $error);
    }

    public function schedule_reminder($user_id, $qr_code) {
        if (!wp_next_scheduled('kerbcycle_qr_reminder', array($user_id, $qr_code))) {
            wp_schedule_single_event(time() + DAY_IN_SECONDS, 'kerbcycle_qr_reminder', array($user_id, $qr_code));
        }
    }

    public function handle_reminder($user_id, $qr_code) {
        $this->send_notification_email($user_id, $qr_code);
    }
}
