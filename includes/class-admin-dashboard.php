<?php

class KerbCycle_Admin_Dashboard {

    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
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
        add_submenu_page('kerbcycle-qr-manager', 'QR Code History', 'History', 'manage_options', 'kerbcycle-qr-history', array($this, 'history_page'));
        add_submenu_page('kerbcycle-qr-manager', 'QR Code Reports', 'Reports', 'manage_options', 'kerbcycle-qr-reports', array($this, 'reports_page'));
        add_submenu_page('kerbcycle-qr-manager', 'Settings', 'Settings', 'manage_options', 'kerbcycle-qr-settings', array($this, 'settings_page'));
    }

    public function enqueue_admin_scripts($hook) {
        // Enqueue styles
        wp_enqueue_style('kerbcycle-admin-style', KERBCYCLE_QR_URL . 'admin/style.css', array(), KERBCYCLE_QR_VERSION);

        // Enqueue scripts for the reports page
        if ($hook === 'qr-codes_page_kerbcycle-qr-reports') {
            wp_enqueue_script('chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1', true);
            wp_enqueue_script('kerbcycle-qr-reports', KERBCYCLE_QR_URL . 'admin/reports.js', array('chartjs'), KERBCYCLE_QR_VERSION, true);
            wp_localize_script('kerbcycle-qr-reports', 'kerbcycleReportData', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('kerbcycle_qr_report_nonce')
            ));
            return;
        }

        // Enqueue scripts for the main dashboard and history pages
        if (in_array($hook, ['toplevel_page_kerbcycle-qr-manager', 'kerbcycle-qr-manager_page_kerbcycle-qr-history'])) {
            wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', [], null, true);
            wp_enqueue_script('kerbcycle-qr-admin-js', KERBCYCLE_QR_URL . 'admin/script.js', array('html5-qrcode', 'jquery-ui-sortable'), KERBCYCLE_QR_VERSION, true);
            wp_localize_script('kerbcycle-qr-admin-js', 'kerbcycle_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('kerbcycle_qr_nonce')
            ));
        }
    }

    public function register_settings() {
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_email');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_sms');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_reminders');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_twilio_sid');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_twilio_token');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_twilio_from');

        add_settings_section('kerbcycle_qr_main', __('General Settings', 'kerbcycle'), '__return_false', 'kerbcycle_qr_settings');
        add_settings_field('kerbcycle_qr_enable_email', __('Enable Email Notifications', 'kerbcycle'), array($this, 'render_enable_email_field'), 'kerbcycle_qr_settings', 'kerbcycle_qr_main');
        add_settings_field('kerbcycle_qr_enable_sms', __('Enable SMS Notifications', 'kerbcycle'), array($this, 'render_enable_sms_field'), 'kerbcycle_qr_settings', 'kerbcycle_qr_main');
        add_settings_field('kerbcycle_qr_enable_reminders', __('Enable Automated Reminders', 'kerbcycle'), array($this, 'render_enable_reminders_field'), 'kerbcycle_qr_settings', 'kerbcycle_qr_main');
        add_settings_field('kerbcycle_twilio_sid', __('Twilio Account SID', 'kerbcycle'), array($this, 'render_twilio_sid_field'), 'kerbcycle_qr_settings', 'kerbcycle_qr_main');
        add_settings_field('kerbcycle_twilio_token', __('Twilio Auth Token', 'kerbcycle'), array($this, 'render_twilio_token_field'), 'kerbcycle_qr_settings', 'kerbcycle_qr_main');
        add_settings_field('kerbcycle_twilio_from', __('Twilio From Number', 'kerbcycle'), array($this, 'render_twilio_from_field'), 'kerbcycle_qr_settings', 'kerbcycle_qr_main');
    }

    public function render_enable_email_field() {
        $value = get_option('kerbcycle_qr_enable_email', 1);
        echo '<input type="checkbox" name="kerbcycle_qr_enable_email" value="1" ' . checked(1, $value, false) . ' /> <span class="description">' . esc_html__('Send email when QR codes are assigned', 'kerbcycle') . '</span>';
    }

    public function render_enable_sms_field() {
        $value = get_option('kerbcycle_qr_enable_sms', 0);
        echo '<input type="checkbox" name="kerbcycle_qr_enable_sms" value="1" ' . checked(1, $value, false) . ' /> <span class="description">' . esc_html__('Send SMS when QR codes are assigned', 'kerbcycle') . '</span>';
    }

    public function render_enable_reminders_field() {
        $value = get_option('kerbcycle_qr_enable_reminders', 0);
        echo '<input type="checkbox" name="kerbcycle_qr_enable_reminders" value="1" ' . checked(1, $value, false) . ' /> <span class="description">' . esc_html__('Schedule automated reminders after assignment', 'kerbcycle') . '</span>';
    }

    public function render_twilio_sid_field() {
        $value = get_option('kerbcycle_twilio_sid', '');
        echo '<input type="text" name="kerbcycle_twilio_sid" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function render_twilio_token_field() {
        $value = get_option('kerbcycle_twilio_token', '');
        echo '<input type="text" name="kerbcycle_twilio_token" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function render_twilio_from_field() {
        $value = get_option('kerbcycle_twilio_from', '');
        echo '<input type="text" name="kerbcycle_twilio_from" value="' . esc_attr($value) . '" class="regular-text" /> <span class="description">' . esc_html__('Twilio phone number including country code', 'kerbcycle') . '</span>';
    }

    public function admin_page() {
        // The content of this page will be moved to admin/dashboard.php and included here.
        require_once KERBCYCLE_QR_PATH . 'admin/dashboard.php';
    }

    public function history_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kerbcycle_qr_codes';
        $qr_codes = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY assigned_at DESC LIMIT %d", 100));
        require KERBCYCLE_QR_PATH . 'admin/history-page.php'; // I will create this file
    }

    public function reports_page() {
        require KERBCYCLE_QR_PATH . 'admin/reports-page.php'; // I will create this file
    }

    public function settings_page() {
        require KERBCYCLE_QR_PATH . 'admin/settings-page.php'; // I will create this file
    }

}
