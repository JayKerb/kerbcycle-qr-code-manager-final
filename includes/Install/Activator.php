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
    }
}
