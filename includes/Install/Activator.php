<?php

namespace Kerbcycle\QrCode\Install;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Install
 */
class Activator
{
    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        self::grant_capabilities();

        global $wpdb;

        // Create QR codes table
        $table_name = $wpdb->prefix . 'kerbcycle_qr_codes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            qr_code varchar(255) NOT NULL,
            user_id mediumint(9),
            display_name varchar(255) DEFAULT NULL,
            status varchar(20) DEFAULT 'available',
            assigned_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $qr_table_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if ($qr_table_found !== $table_name) {
            $wpdb->query(str_replace('CREATE TABLE ', 'CREATE TABLE IF NOT EXISTS ', $sql));
        }

        // Create QR code history table
        $history_table = $wpdb->prefix . 'kerbcycle_qr_code_history';
        $sql = "CREATE TABLE $history_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            qr_code varchar(255) NOT NULL,
            user_id mediumint(9),
            status varchar(20) NOT NULL,
            changed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY qr_code_idx (qr_code),
            KEY status_idx (status),
            KEY changed_idx (changed_at)
        ) $charset_collate;";

        dbDelta($sql);

        // Create message logs table
        $table_name = $wpdb->prefix . 'kerbcycle_message_logs';

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(10) NOT NULL,
            recipient VARCHAR(190) NOT NULL,
            subject VARCHAR(255) DEFAULT '',
            body LONGTEXT,
            status VARCHAR(30) DEFAULT '',
            provider VARCHAR(100) DEFAULT '',
            response LONGTEXT,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY type_idx (type),
            KEY created_idx (created_at),
            KEY recipient_idx (recipient)
        ) $charset_collate;";

        dbDelta($sql);

        // Create error logs table
        $errors_table = $wpdb->prefix . 'kerbcycle_error_logs';

        $sql = "CREATE TABLE $errors_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(100) NOT NULL,
            message LONGTEXT NOT NULL,
            page VARCHAR(255) DEFAULT '',
            status VARCHAR(30) DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY type_idx (type),
            KEY created_idx (created_at)
        ) $charset_collate;";

        dbDelta($sql);

        // Create QR generator repository table
        $repo_table = $wpdb->prefix . 'kerbcycle_qr_repo';
        $sql = "CREATE TABLE $repo_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(191) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'available',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_unique (code),
            KEY created_at_idx (created_at),
            KEY status_idx (status)
        ) $charset_collate;";

        dbDelta($sql);

        // Create pickup exceptions table
        $pickup_exceptions_table = $wpdb->prefix . 'kerbcycle_pickup_exceptions';
        $sql = "CREATE TABLE $pickup_exceptions_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            qr_code VARCHAR(255) DEFAULT '',
            customer_id BIGINT(20) UNSIGNED DEFAULT 0,
            issue VARCHAR(255) NOT NULL,
            notes LONGTEXT,
            submitted_at VARCHAR(50) NOT NULL,
            webhook_sent TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) DEFAULT 'pending',
            webhook_status_code INT DEFAULT NULL,
            webhook_response_body LONGTEXT,
            source VARCHAR(20) DEFAULT '',
            retry_count INT NOT NULL DEFAULT 0,
            last_retry_at DATETIME NULL,
            ai_severity VARCHAR(100) DEFAULT '',
            ai_category VARCHAR(100) DEFAULT '',
            ai_summary LONGTEXT,
            ai_recommended_action LONGTEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at_idx (created_at),
            KEY submitted_at_idx (submitted_at),
            KEY webhook_sent_idx (webhook_sent)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Grant KerbCycle capabilities to administrators.
     *
     * @return void
     */
    private static function grant_capabilities()
    {
        $administrator = get_role('administrator');
        $caps = [
            \Kerbcycle\QrCode\Helpers\Capabilities::manage_operations(),
            \Kerbcycle\QrCode\Helpers\Capabilities::manage_settings(),
            \Kerbcycle\QrCode\Helpers\Capabilities::view_logs(),
        ];

        if ($administrator) {
            foreach ($caps as $capability) {
                $administrator->add_cap($capability);
            }
        }

        $operator_caps = [
            'read' => true,
            \Kerbcycle\QrCode\Helpers\Capabilities::manage_operations() => true,
        ];

        add_role('kerbcycle_operator', __('KerbCycle Operator', 'kerbcycle'), $operator_caps);

        $operator = get_role('kerbcycle_operator');
        if ($operator) {
            foreach (array_keys($operator_caps) as $capability) {
                $operator->add_cap($capability);
            }
        }
    }
}
