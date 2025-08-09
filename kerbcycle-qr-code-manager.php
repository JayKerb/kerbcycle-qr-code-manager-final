<?php
/*
Plugin Name: KerbCycle QR Code Manager
Description: Manage QR code scanning and assignment with drag-and-drop, inline editing, bulk actions, and notification toggles
Version: 1.4
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin URL constant
if (!defined('KERBCYCLE_QR_URL')) {
    define('KERBCYCLE_QR_URL', plugin_dir_url(__FILE__));
}

// Main plugin class
class KerbCycle_QR_Manager {

    public function __construct() {
        // Admin hooks
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));

        // AJAX handlers
        add_action('wp_ajax_assign_qr_code', array($this, 'assign_qr_code'));
        add_action('wp_ajax_release_qr_code', array($this, 'release_qr_code'));
        add_action('wp_ajax_bulk_release_qr_codes', array($this, 'bulk_release_qr_codes'));
        add_action('wp_ajax_update_qr_code', array($this, 'update_qr_code'));
        add_action('wp_ajax_kerbcycle_qr_report_data', array($this, 'ajax_report_data'));

        // REST API endpoint
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));

        // Reminder handler
        add_action('kerbcycle_qr_reminder', array($this, 'handle_reminder'), 10, 2);

        // Shortcode support
        add_shortcode('kerbcycle_scanner', array($this, 'generate_frontend_scanner'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    // Plugin activation
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

    // Plugin deactivation
    public static function deactivate() {
        // Optional cleanup
    }

    // Register admin menu
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

        add_submenu_page(
            'kerbcycle-qr-manager',
            'QR Code History',
            'History',
            'manage_options',
            'kerbcycle-qr-history',
            array($this, 'history_page')
        );

        add_submenu_page(
            'kerbcycle-qr-manager',
            'QR Code Reports',
            'Reports',
            'manage_options',
            'kerbcycle-qr-reports',
            array($this, 'reports_page')
        );

        add_submenu_page(
            'kerbcycle-qr-manager',
            'Settings',
            'Settings',
            'manage_options',
            'kerbcycle-qr-settings',
            array($this, 'settings_page')
        );
    }

    // Enqueue admin scripts
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'kerbcycle-qr-manager_page_kerbcycle-qr-reports') {
            die('Hook is firing!');
            wp_enqueue_script('chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1', true);
            wp_enqueue_script(
                'kerbcycle-qr-reports',
                KERBCYCLE_QR_URL . 'assets/js/qr-reports.js',
                array(),
                '1.0',
                true
            );

            $report_data = $this->get_report_data();

            // Use wp_add_inline_script to make data available to the chart script
            wp_add_inline_script(
                'chartjs',
                'const kerbcycleReportData = ' . wp_json_encode($report_data) . ';',
                'after'
            );
            return;
        }

        if (!in_array($hook, ['toplevel_page_kerbcycle-qr-manager', 'kerbcycle-qr-manager_page_kerbcycle-qr-history'])) {
            return;
        }

        wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', [], null, true);
        wp_enqueue_script(
            'kerbcycle-qr-js',
            KERBCYCLE_QR_URL . 'assets/js/qr-scanner.js',
            array('html5-qrcode', 'jquery-ui-sortable'),
            '1.0',
            true
        );

        wp_localize_script('kerbcycle-qr-js', 'kerbcycle_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kerbcycle_qr_nonce')
        ));
    }

    // Register plugin settings
    public function register_settings() {
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_email');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_sms');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_reminders');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_twilio_sid');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_twilio_token');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_twilio_from');

        add_settings_section(
            'kerbcycle_qr_main',
            __('General Settings', 'kerbcycle'),
            '__return_false',
            'kerbcycle_qr_settings'
        );

        add_settings_field(
            'kerbcycle_qr_enable_email',
            __('Enable Email Notifications', 'kerbcycle'),
            array($this, 'render_enable_email_field'),
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_qr_enable_sms',
            __('Enable SMS Notifications', 'kerbcycle'),
            array($this, 'render_enable_sms_field'),
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_qr_enable_reminders',
            __('Enable Automated Reminders', 'kerbcycle'),
            array($this, 'render_enable_reminders_field'),
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_twilio_sid',
            __('Twilio Account SID', 'kerbcycle'),
            array($this, 'render_twilio_sid_field'),
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_twilio_token',
            __('Twilio Auth Token', 'kerbcycle'),
            array($this, 'render_twilio_token_field'),
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_twilio_from',
            __('Twilio From Number', 'kerbcycle'),
            array($this, 'render_twilio_from_field'),
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );
    }

    public function render_enable_email_field() {
        $value = get_option('kerbcycle_qr_enable_email', 1);
        ?>
        <input type="checkbox" name="kerbcycle_qr_enable_email" value="1" <?php checked(1, $value); ?> />
        <span class="description"><?php esc_html_e('Send email when QR codes are assigned', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_enable_sms_field() {
        $value = get_option('kerbcycle_qr_enable_sms', 0);
        ?>
        <input type="checkbox" name="kerbcycle_qr_enable_sms" value="1" <?php checked(1, $value); ?> />
        <span class="description"><?php esc_html_e('Send SMS when QR codes are assigned', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_enable_reminders_field() {
        $value = get_option('kerbcycle_qr_enable_reminders', 0);
        ?>
        <input type="checkbox" name="kerbcycle_qr_enable_reminders" value="1" <?php checked(1, $value); ?> />
        <span class="description"><?php esc_html_e('Schedule automated reminders after assignment', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_twilio_sid_field() {
        $value = get_option('kerbcycle_twilio_sid', '');
        ?>
        <input type="text" name="kerbcycle_twilio_sid" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <?php
    }

    public function render_twilio_token_field() {
        $value = get_option('kerbcycle_twilio_token', '');
        ?>
        <input type="text" name="kerbcycle_twilio_token" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <?php
    }

    public function render_twilio_from_field() {
        $value = get_option('kerbcycle_twilio_from', '');
        ?>
        <input type="text" name="kerbcycle_twilio_from" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <span class="description"><?php esc_html_e('Twilio phone number including country code', 'kerbcycle'); ?></span>
        <?php
    }

    // Admin dashboard page
    public function admin_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $available_codes = $wpdb->get_results("SELECT qr_code FROM $table WHERE status = 'available' ORDER BY id DESC");
        $all_codes = $wpdb->get_results("SELECT id, qr_code, user_id, status, assigned_at FROM $table ORDER BY id DESC");
        ?>
        <div class="wrap">
            <h1>KerbCycle QR Code Manager</h1>
            <div class="notice notice-info">
                <p><?php esc_html_e('Select a customer and scan or choose a QR code to assign.', 'kerbcycle'); ?></p>
            </div>
            <div id="qr-scanner-container">
                <?php
                wp_dropdown_users(array(
                    'name' => 'customer_id',
                    'id' => 'customer-id',
                    'show_option_none' => __('Select Customer', 'kerbcycle')
                ));
                ?>
                <select id="qr-code-select">
                    <option value=""><?php esc_html_e('Select QR Code', 'kerbcycle'); ?></option>
                    <?php foreach ($available_codes as $code) : ?>
                        <option value="<?= esc_attr($code->qr_code); ?>"><?= esc_html($code->qr_code); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php
                $email_enabled    = (bool) get_option('kerbcycle_qr_enable_email', 1);
                $sms_enabled      = (bool) get_option('kerbcycle_qr_enable_sms', 0);
                $reminder_enabled = (bool) get_option('kerbcycle_qr_enable_reminders', 0);
                ?>
                <label><input type="checkbox" id="send-email" <?php checked($email_enabled); ?> <?php disabled(!$email_enabled); ?>> <?php esc_html_e('Send notification email', 'kerbcycle'); ?></label>
                <label><input type="checkbox" id="send-sms" <?php checked($sms_enabled); ?> <?php disabled(!$sms_enabled); ?>> <?php esc_html_e('Send SMS', 'kerbcycle'); ?></label>
                <label><input type="checkbox" id="send-reminder" <?php checked($reminder_enabled); ?> <?php disabled(!$reminder_enabled); ?>> <?php esc_html_e('Schedule reminder', 'kerbcycle'); ?></label>
                <p>
                    <button id="assign-qr-btn" class="button button-primary"><?php esc_html_e('Assign QR Code', 'kerbcycle'); ?></button>
                    <button id="release-qr-btn" class="button"><?php esc_html_e('Release QR Code', 'kerbcycle'); ?></button>
                </p>
                <div id="reader" style="width: 100%; max-width: 400px; margin-top: 20px;"></div>
                <div id="scan-result" class="updated" style="display: none;"></div>
            </div>

            <h2><?php esc_html_e('Manage QR Codes', 'kerbcycle'); ?></h2>
            <p class="description"><?php esc_html_e('Drag and drop to reorder, select multiple codes for bulk actions, or click a code to edit.', 'kerbcycle'); ?></p>
            <form id="qr-code-bulk-form">
                <ul id="qr-code-list">
                    <li class="qr-header">
                        <input type="checkbox" class="qr-select" disabled style="visibility:hidden" aria-hidden="true" />
                        <span class="qr-id"><?php esc_html_e('ID', 'kerbcycle'); ?></span>
                        <span class="qr-text"><?php esc_html_e('QR Code', 'kerbcycle'); ?></span>
                        <span class="qr-user"><?php esc_html_e('User ID', 'kerbcycle'); ?></span>
                        <span class="qr-status"><?php esc_html_e('Status', 'kerbcycle'); ?></span>
                        <span class="qr-assigned"><?php esc_html_e('Assigned At', 'kerbcycle'); ?></span>
                    </li>
                    <?php foreach ($all_codes as $code) : ?>
                        <li class="qr-item" data-code="<?= esc_attr($code->qr_code); ?>" data-id="<?= esc_attr($code->id); ?>">
                            <input type="checkbox" class="qr-select" />
                            <span class="qr-id"><?= esc_html($code->id); ?></span>
                            <span class="qr-text" contenteditable="true"><?= esc_html($code->qr_code); ?></span>
                            <span class="qr-user"><?= $code->user_id ? esc_html($code->user_id) : '—'; ?></span>
                            <span class="qr-status"><?= esc_html(ucfirst($code->status)); ?></span>
                            <span class="qr-assigned"><?= $code->assigned_at ? esc_html($code->assigned_at) : '—'; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <select id="bulk-action">
                    <option value=""><?php esc_html_e('Bulk actions', 'kerbcycle'); ?></option>
                    <option value="release"><?php esc_html_e('Release', 'kerbcycle'); ?></option>
                </select>
                <button id="apply-bulk" class="button"><?php esc_html_e('Apply', 'kerbcycle'); ?></button>
            </form>
        </div>
        <?php
    }

    // QR code history page
    public function history_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kerbcycle_qr_codes';

        $qr_codes = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name ORDER BY assigned_at DESC LIMIT %d", 100)
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

    public function get_report_data() {
        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';

        // Weekly assignment counts
        $results = $wpdb->get_results("SELECT DATE(assigned_at) AS date, COUNT(*) AS count FROM $table WHERE assigned_at IS NOT NULL GROUP BY DATE(assigned_at) ORDER BY date DESC LIMIT 7");
        $labels  = array();
        $counts  = array();
        if ($results) {
            foreach (array_reverse($results) as $row) {
                $labels[] = $row->date;
                $counts[] = (int) $row->count;
            }
        }

        // Today's assignment counts by hour
        $hour_results = $wpdb->get_results("SELECT HOUR(assigned_at) AS hour, COUNT(*) AS count FROM $table WHERE assigned_at >= CURDATE() GROUP BY HOUR(assigned_at) ORDER BY hour");
        $daily_labels = array();
        $daily_counts = array();
        if ($hour_results) {
            foreach ($hour_results as $row) {
                $daily_labels[] = sprintf('%02d:00', $row->hour);
                $daily_counts[] = (int) $row->count;
            }
        }

        return array(
            'labels'       => $labels,
            'counts'       => $counts,
            'daily_labels' => $daily_labels,
            'daily_counts' => $daily_counts,
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('kerbcycle_qr_report_nonce'),
        );
    }

    public function reports_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('QR Code Reports', 'kerbcycle'); ?></h1>

            <div id="js-debug-output" style="border: 2px solid blue; padding: 10px; margin-bottom: 20px;">
                <h2>JavaScript Debug Output</h2>
            </div>

            <?php
            $report_data = $this->get_report_data();
            echo '<h2>PHP Debug Output</h2>';
            echo '<pre style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">';
            print_r($report_data);
            echo '</pre>';
            ?>

            <h2><?php esc_html_e('Today\'s Assignments', 'kerbcycle'); ?></h2>
            <canvas id="qr-daily-chart" style="max-width:600px;width:100%;"></canvas>
            <h2><?php esc_html_e('Weekly Assignments', 'kerbcycle'); ?></h2>
            <canvas id="qr-report-chart" style="max-width:600px;width:100%;"></canvas>
        </div>
        <?php
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

    // Plugin settings page
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('KerbCycle QR Settings', 'kerbcycle'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('kerbcycle_qr_settings');
                do_settings_sections('kerbcycle_qr_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // Shortcode to render scanner on any page
    public function generate_frontend_scanner() {
        ob_start();
        ?>
        <div class="kerbcycle-qr-scanner-container">
            <h2>Assign QR Code</h2>
            <p>Enter the customer ID and scan the QR code to assign it.</p>
            <input type="number" id="customer-id" class="regular-text" placeholder="Enter Customer ID" />
            <button id="assign-qr-btn" class="button button-primary">Assign QR Code</button>
            <div id="reader" style="width: 100%; max-width: 400px; margin-top: 20px;"></div>
            <div id="scan-result" class="updated" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    // Enqueue frontend scripts only when shortcode is used
    public function enqueue_frontend_scripts() {
        global $post;

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'kerbcycle_scanner')) {
            wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', [], null, true);
            wp_enqueue_script(
                'kerbcycle-qr-frontend-js',
                KERBCYCLE_QR_URL . 'assets/js/qr-scanner.js',
                array('html5-qrcode'),
                '1.0',
                true
            );

            wp_localize_script('kerbcycle-qr-frontend-js', 'kerbcycle_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kerbcycle_qr_nonce')
            ));
        }
    }

    // AJAX: Assign QR code
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
                $this->send_notification_email($user_id, $qr_code);
            }
            $sms_result = null;
            if ($send_sms) {
                $sms_result = $this->send_notification_sms($user_id, $qr_code);
            }
            if ($send_reminder) {
                $this->schedule_reminder($user_id, $qr_code);
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

    // AJAX: Release QR code
    public function release_qr_code() {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        global $wpdb;
        $qr_code = sanitize_text_field($_POST['qr_code']);
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';

        $latest_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE qr_code = %s ORDER BY id DESC LIMIT 1",
                $qr_code
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
            $result = false;
        }

        if ($result !== false) {
            wp_send_json_success(array('message' => 'QR code released successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to release QR code'));
        }
    }

    // AJAX: Bulk release QR codes
    public function bulk_release_qr_codes() {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        if (empty($_POST['qr_codes'])) {
            wp_send_json_error(array('message' => 'No QR codes were selected.'));
        }

        // Sanitize each code individually and remove whitespace.
        $raw_codes = explode(',', $_POST['qr_codes']);
        $codes = array_map('trim', array_map('sanitize_text_field', $raw_codes));
        $codes = array_filter($codes); // Remove any empty values

        if (empty($codes)) {
            wp_send_json_error(array('message' => 'No valid QR codes provided.'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $released_count = 0;

        foreach ($codes as $code) {
            // Find the latest *assigned* entry for this QR code.
            $latest_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table WHERE qr_code = %s AND status = 'assigned' ORDER BY id DESC LIMIT 1",
                    $code
                )
            );

            if ($latest_id) {
                // Use wpdb->update for a safe and clear update operation.
                $result = $wpdb->update(
                    $table,
                    array( // Data
                        'user_id' => null,
                        'status' => 'available',
                        'assigned_at' => null,
                    ),
                    array('id' => $latest_id), // Where
                    array( // Data format
                        '%d',
                        '%s',
                        '%s',
                    ),
                    array('%d') // Where format
                );

                if ($result !== false) {
                    // $result is number of rows affected, should be 1.
                    $released_count += $result;
                }
            }
        }

        if ($released_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(
                    '%d of %d selected QR code(s) have been successfully released.',
                    $released_count,
                    count($codes)
                )
            ));
        } else {
            wp_send_json_error(array('message' => 'Could not find or release any of the selected QR codes. They may have already been released or do not exist.'));
        }
    }

    // AJAX: Inline update QR code text
    public function update_qr_code() {
        check_ajax_referer('kerbcycle_qr_nonce', 'security');

        $old_code = sanitize_text_field($_POST['old_code']);
        $new_code = sanitize_text_field($_POST['new_code']);

        if (empty($old_code) || empty($new_code)) {
            wp_send_json_error(array('message' => 'Invalid QR code'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $result = $wpdb->update(
            $table,
            array('qr_code' => $new_code),
            array('qr_code' => $old_code),
            array('%s'),
            array('%s')
        );

        if ($result !== false) {
            wp_send_json_success(array('message' => 'QR code updated'));
        }

        wp_send_json_error(array('message' => 'Failed to update QR code'));
    }

    // REST API: Handle QR code scan
    public function register_rest_endpoints() {
        register_rest_route('kerbcycle/v1', '/qr-code/scanned', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_qr_code_scan'),
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

    // Helper: Send email notification
    private function send_notification_email($user_id, $qr_code) {
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

    private function send_notification_sms($user_id, $qr_code) {
        $sid   = get_option('kerbcycle_twilio_sid');
        $token = get_option('kerbcycle_twilio_token');
        $from  = get_option('kerbcycle_twilio_from');

        $to = get_user_meta($user_id, 'phone_number', true);
        if (empty($to)) {
            $to = get_user_meta($user_id, 'billing_phone', true);
        }

        if (empty($sid) || empty($token) || empty($from) || empty($to)) {
            return new WP_Error('sms_config', __('Missing SMS configuration or phone number', 'kerbcycle'));
        }

        $body = sprintf(__('You have been assigned QR code %s', 'kerbcycle'), $qr_code);

        $response = wp_remote_post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", array(
            'body'    => array(
                'From' => $from,
                'To'   => $to,
                'Body' => $body,
            ),
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token),
            ),
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

    private function schedule_reminder($user_id, $qr_code) {
        if (!wp_next_scheduled('kerbcycle_qr_reminder', array($user_id, $qr_code))) {
            wp_schedule_single_event(time() + DAY_IN_SECONDS, 'kerbcycle_qr_reminder', array($user_id, $qr_code));
        }
    }

    public function handle_reminder($user_id, $qr_code) {
        // By default send an email reminder
        $this->send_notification_email($user_id, $qr_code);
    }
}

// Instantiate the plugin
if (class_exists('KerbCycle_QR_Manager')) {
    new KerbCycle_QR_Manager();
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, array('KerbCycle_QR_Manager', 'activate'));
register_deactivation_hook(__FILE__, array('KerbCycle_QR_Manager', 'deactivate'));
