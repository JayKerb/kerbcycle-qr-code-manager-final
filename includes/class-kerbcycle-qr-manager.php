<?php
/*
Plugin Name: KerbCycle QR Code Manager
Description: Manages QR code scanning and assignment for customers
Version: 1.0
Author: Your Name
*/

// Define constants
if (!defined('KERBCYCLE_QR_URL')) {
    define('KERBCYCLE_QR_URL', plugin_dir_url(__FILE__));
}

// Instantiate the main class
if (class_exists('KerbCycle_QR_Manager')) {
    new KerbCycle_QR_Manager();
}

class KerbCycle_QR_Manager {

    public function __construct() {
        // Initialize hooks
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_assign_qr_code', array($this, 'assign_qr_code'));
        add_action('wp_ajax_release_qr_code', array($this, 'release_qr_code'));
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
    }

    public static function activate() {
        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            qr_code varchar(255) NOT NULL,
            user_id mediumint(9),
            status varchar(20) DEFAULT 'available',
            assigned_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function deactivate() {
        // Optional cleanup
    }

    public function register_admin_menu() {
        add_menu_page(
            'QR Code Manager', 
            'QR Codes', 
            'manage_options', 
            'kerbcycle-qr-manager', 
            array($this, 'admin_page'), 
            'dashicons-qrcode', 
            20
        );
        
        // Add submenu page for history
        add_submenu_page(
            'kerbcycle-qr-manager',
            'QR Code History',
            'History',
            'manage_options',
            'kerbcycle-qr-history',
            array($this, 'history_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, ['toplevel_page_kerbcycle-qr-manager', 'kerbcycle-qr-manager_page_kerbcycle-qr-history'])) {
            return;
        }
        
        wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', [], null, true);
        wp_enqueue_script(
            'kerbcycle-qr-js', 
            KERBCYCLE_QR_URL . 'assets/js/qr-scanner.js', 
            array('html5-qrcode'), 
            '1.0', 
            true
        );
        
        wp_localize_script('kerbcycle-qr-js', 'kerbcycle_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kerbcycle_qr_nonce')
        ));
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>KerbCycle QR Code Manager</h1>
            <div class="notice notice-info">
                <p>Scan or enter customer ID to assign QR codes</p>
            </div>
            
            <div id="qr-scanner-container">
                <input type="number" id="customer-id" class="regular-text" placeholder="Enter Customer ID" />
                <button id="assign-qr-btn" class="button button-primary">Assign QR Code</button>
                <div id="reader" style="width: 100%; max-width: 400px; margin-top: 20px;"></div>
                <div id="scan-result" class="updated" style="display: none;"></div>
            </div>
        </div>
        <?php
    }

    public function history_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kerbcycle_qr_codes';
        
        // Fetch all QR codes with status history
        $qr_codes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY assigned_at DESC LIMIT %d", 
                100
            )
        );
        ?>
        <div class="wrap">
            <h1>QR Code History</h1>
            <p class="description">Recent QR code assignments</p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>QR Code</th>
                        <th>User ID</th>
                        <th>Status</th>
                        <th>Assigned At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($qr_codes)) : ?>
                        <?php foreach ($qr_codes as $qr) : ?>
                            <tr>
                                <td><?= esc_html($qr->id) ?></td>
                                <td><?= esc_html($qr->qr_code) ?></td>
                                <td><?= $qr->user_id ? esc_html($qr->user_id) : '—' ?></td>
                                <td><?= esc_html(ucfirst($qr->status)) ?></td>
                                <td><?= $qr->assigned_at ? esc_html($qr->assigned_at) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5" class="description">No QR codes found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function assign_qr_code() {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');
        
        global $wpdb;
        $qr_code = sanitize_text_field($_POST['qr_code']);
        $user_id = intval($_POST['customer_id']);
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

        if ($result !== false) {
            $this->send_notification_email($user_id, $qr_code);
            wp_send_json_success(array(
                'message' => 'QR code assigned successfully',
                'qr_code' => $qr_code,
                'user_id' => $user_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to assign QR code'));
        }
    }

    public function release_qr_code() {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');
        
        global $wpdb;
        $qr_code = sanitize_text_field($_POST['qr_code']);
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';

        $latest_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE qr_code = %s AND status = %s ORDER BY id DESC LIMIT 1",
                $qr_code,
                'assigned'
            )
        );

        if ($latest_id) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table SET user_id = NULL, status = %s, assigned_at = NULL WHERE id = %d",
                    'available',
                    $latest_id
                )
            );
        } else {
            $result = 0;
        }

        if ($result > 0) {
            wp_send_json_success(array('message' => 'QR code released successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to release QR code'));
        }
    }

    public function register_rest_endpoints() {
        register_rest_route('kerbcycle/v1', '/qr-code/scanned', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_qr_code_scan'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_qr_code_scan(WP_REST_Request $request) {
        $qr_code = sanitize_text_field($request->get_param('qr_code'));
        $user_id = intval($request->get_param('user_id'));
        
        // Log the scan event (placeholder for actual logging implementation)
        $this->log_scan_event($qr_code, $user_id);
        
        // Notify admins
        $this->send_notification_email($user_id, $qr_code);
        
        // Update QR code status
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
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to process QR code'
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'QR code processed',
            'qr_code' => $qr_code,
            'user_id' => $user_id
        ), 200);
    }

    private function log_scan_event($qr_code, $user_id) {
        // Implement actual logging here
        error_log(sprintf(
            "QR Code %s scanned by user %d at %s",
            $qr_code,
            $user_id,
            current_time('mysql')
        ));
    }

    private function send_notification_email($user_id, $qr_code) {
        // Implement admin notification logic
        $admin_email = get_option('admin_email');
        $subject = 'QR Code Assignment Notification';
        $message = sprintf(
            "User #%d has been assigned QR code %s\n\nTimestamp: %s",
            $user_id,
            $qr_code,
            current_time('mysql')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
}

// Activation Hooks
register_activation_hook(__FILE__, array('KerbCycle_QR_Manager', 'activate'));
register_deactivation_hook(__FILE__, array('KerbCycle_QR_Manager', 'deactivate'));