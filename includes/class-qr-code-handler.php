<?php

class KerbCycle_QR_Code_Handler {

    private $notification_handler;

    public function __construct($notification_handler) {
        $this->notification_handler = $notification_handler;

        add_action('wp_ajax_assign_qr_code', array($this, 'assign_qr_code'));
        add_action('wp_ajax_release_qr_code', array($this, 'release_qr_code'));
        add_action('wp_ajax_bulk_release_qr_codes', array($this, 'bulk_release_qr_codes'));
        add_action('wp_ajax_update_qr_code', array($this, 'update_qr_code'));
        add_action('wp_ajax_kerbcycle_qr_report_data', array($this, 'ajax_report_data'));
    }

    public function assign_qr_code() {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        global $wpdb;
        $qr_code      = sanitize_text_field($_POST['qr_code']);
        $user_id      = intval($_POST['customer_id']);
        $send_email   = !empty($_POST['send_email']) && get_option('kerbcycle_qr_enable_email', 1);
        $send_sms     = !empty($_POST['send_sms']) && get_option('kerbcycle_qr_enable_sms', 0);
        $send_reminder = !empty($_POST['send_reminder']) && get_option('kerbcycle_qr_enable_reminders', 0);
        $table        = $wpdb->prefix . 'kerbcycle_qr_codes';

        $result = $wpdb->insert(
            $table,
            array(
                'qr_code'     => $qr_code,
                'user_id'     => $user_id,
                'status'      => 'assigned',
                'assigned_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s')
        );

        if ($result !== false) {
            if ($send_email) {
                $this->notification_handler->send_notification_email($user_id, $qr_code);
            }
            $sms_result = null;
            if ($send_sms) {
                $sms_result = $this->notification_handler->send_notification_sms($user_id, $qr_code);
            }
            if ($send_reminder) {
                $this->notification_handler->schedule_reminder($user_id, $qr_code);
            }

            $response = array(
                'message' => 'QR code assigned successfully',
                'qr_code' => $qr_code,
                'user_id' => $user_id,
            );
            if ($send_sms) {
                $response['sms_sent'] = ($sms_result === true);
                if ($sms_result !== true) {
                    $response['sms_error'] = is_wp_error($sms_result) ? $sms_result->get_error_message() : __('Unknown error', 'kerbcycle');
                }
            }
            wp_send_json_success($response);
        } else {
            wp_send_json_error(array('message' => 'Failed to assign QR code'));
        }
    }

    public function release_qr_code() {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        global $wpdb;
        $qr_code = sanitize_text_field($_POST['qr_code']);
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';

        $latest_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE qr_code = %s ORDER BY id DESC LIMIT 1", $qr_code));

        if ($latest_id) {
            $result = $wpdb->query($wpdb->prepare("UPDATE $table SET user_id = NULL, status = %s, assigned_at = NULL WHERE id = %d", 'available', $latest_id));
        } else {
            $result = false;
        }

        if ($result !== false) {
            wp_send_json_success(array('message' => 'QR code released successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to release QR code'));
        }
    }

    public function bulk_release_qr_codes() {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        if (empty($_POST['qr_codes'])) {
            wp_send_json_error(array('message' => 'No QR codes were selected.'));
        }

        $raw_codes = explode(',', $_POST['qr_codes']);
        $codes = array_map('trim', array_map('sanitize_text_field', $raw_codes));
        $codes = array_filter($codes);

        if (empty($codes)) {
            wp_send_json_error(array('message' => 'No valid QR codes provided.'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $released_count = 0;

        foreach ($codes as $code) {
            $latest_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE qr_code = %s AND status = 'assigned' ORDER BY id DESC LIMIT 1", $code));
            if ($latest_id) {
                $result = $wpdb->update($table, array('user_id' => null, 'status' => 'available', 'assigned_at' => null), array('id' => $latest_id), array('%d', '%s', '%s'), array('%d'));
                if ($result !== false) {
                    $released_count += $result;
                }
            }
        }

        if ($released_count > 0) {
            wp_send_json_success(array('message' => sprintf('%d of %d selected QR code(s) have been successfully released.', $released_count, count($codes))));
        } else {
            wp_send_json_error(array('message' => 'Could not find or release any of the selected QR codes. They may have already been released or do not exist.'));
        }
    }

    public function update_qr_code() {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        $old_code = sanitize_text_field($_POST['old_code']);
        $new_code = sanitize_text_field($_POST['new_code']);

        if (empty($old_code) || empty($new_code)) {
            wp_send_json_error(array('message' => 'Invalid QR code'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $result = $wpdb->update($table, array('qr_code' => $new_code), array('qr_code' => $old_code), array('%s'), array('%s'));

        if ($result !== false) {
            wp_send_json_success(array('message' => 'QR code updated'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update QR code'));
        }
    }

    public function ajax_report_data() {
        check_ajax_referer('kerbcycle_qr_report_nonce', 'security');

        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';

        $results = $wpdb->get_results("SELECT DATE(assigned_at) AS date, COUNT(*) AS count FROM $table WHERE assigned_at IS NOT NULL GROUP BY DATE(assigned_at) ORDER BY date DESC LIMIT 7");
        $labels  = array();
        $counts  = array();
        if ($results) {
            foreach (array_reverse($results) as $row) {
                $labels[] = $row->date;
                $counts[] = (int) $row->count;
            }
        }

        $hour_results = $wpdb->get_results("SELECT HOUR(assigned_at) AS hour, COUNT(*) AS count FROM $table WHERE assigned_at >= CURDATE() GROUP BY HOUR(assigned_at) ORDER BY hour");
        $daily_labels = array();
        $daily_counts = array();
        if ($hour_results) {
            foreach ($hour_results as $row) {
                $daily_labels[] = sprintf('%02d:00', $row->hour);
                $daily_counts[] = (int) $row->count;
            }
        }

        wp_send_json(array(
            'labels'       => $labels,
            'counts'       => $counts,
            'daily_labels' => $daily_labels,
            'daily_counts' => $daily_counts,
        ));
    }
}
