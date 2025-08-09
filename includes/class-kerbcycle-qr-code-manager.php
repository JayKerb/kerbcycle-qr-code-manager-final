<?php

class KerbCycle_QR_Code_Manager {

    protected $admin_dashboard;
    protected $qr_code_handler;
    protected
    $api_integration;
    protected $notification_handler;

    public function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies() {
        $this->notification_handler = new KerbCycle_Notification_Handler();
        $this->admin_dashboard      = new KerbCycle_Admin_Dashboard();
        $this->qr_code_handler      = new KerbCycle_QR_Code_Handler($this->notification_handler);
        $this->api_integration      = new KerbCycle_API_Integration();
    }

    private function register_hooks() {
        // Cron jobs
        add_action('kerbcycle_qr_reminder', array($this->notification_handler, 'handle_reminder'), 10, 2);

        // Shortcodes
        add_shortcode('kerbcycle_scanner', array($this, 'generate_frontend_scanner'));

        // Frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
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
        // Optional cleanup logic
    }

    public function generate_frontend_scanner() {
        ob_start();
        require KERBCYCLE_QR_PATH . 'public/qr-scanner.php';
        return ob_get_clean();
    }

    public function enqueue_frontend_scripts() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'kerbcycle_scanner')) {
            wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', [], null, true);
            wp_enqueue_script('kerbcycle-qr-public-js', KERBCYCLE_QR_URL . 'admin/script.js', array('html5-qrcode'), KERBCYCLE_QR_VERSION, true);
            wp_localize_script('kerbcycle-qr-public-js', 'kerbcycle_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('kerbcycle_qr_nonce')
            ));
        }
    }
}
