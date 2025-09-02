<?php

namespace Kerbcycle\QrCode\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin
 */
class Admin
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_menu']);
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function register_admin_menu()
    {
        add_menu_page(
            'QR Code Manager',
            'QR Codes',
            'manage_options',
            'kerbcycle-qr-manager',
            [new Pages\DashboardPage(), 'render'],
            'dashicons-qrcode',
            20
        );

        add_submenu_page(
            'kerbcycle-qr-manager',
            'QR Code History',
            'QR Code History',
            'manage_options',
            'kerbcycle-qr-history',
            [new Pages\HistoryPage(), 'render']
        );

        add_submenu_page(
            'kerbcycle-qr-manager',
            'Messages History',
            'Messages History',
            'manage_options',
            'kerbcycle-messages-history',
            [new Pages\MessagesHistoryPage(), 'render']
        );

        add_submenu_page(
            'kerbcycle-qr-manager',
            'QR Code Reports',
            'QR Code Reports',
            'manage_options',
            'kerbcycle-qr-reports',
            [new Pages\ReportsPage(), 'render']
        );

        // Shortcut to Bookly appointments if Bookly is active
        $bookly_active = class_exists('Bookly\\Lib\\Plugin') || defined('BOOKLY_VERSION');
        if ($bookly_active) {
            add_submenu_page(
                'kerbcycle-qr-manager',
                'Pickup Schedule',
                'Pickup Schedule',
                'manage_options',
                'kerbcycle-bookly-appointments',
                [new Pages\RedirectsPage(), 'bookly_appointments']
            );
        }

        // Shortcut to TeraWallet user wallets if TeraWallet is active
        $wallet_active = function_exists('woo_wallet') || class_exists('Woo_Wallet');
        if ($wallet_active) {
            add_submenu_page(
                'kerbcycle-qr-manager',
                'Customer Wallet',
                'Customer Wallet',
                'manage_options',
                'kerbcycle-terawallet',
                [new Pages\RedirectsPage(), 'terawallet']
            );
        }

        add_submenu_page(
            'kerbcycle-qr-manager',
            'Settings',
            'Settings',
            'manage_options',
            'kerbcycle-qr-settings',
            [new Pages\SettingsPage(), 'render']
        );

        add_submenu_page(
            'kerbcycle-qr-manager',
            'Message Settings',
            'Message Settings',
            'manage_options',
            'kerbcycle-messages',
            [new \Kerbcycle\QrCode\Services\MessagesService(), 'render_page']
        );

        add_submenu_page(
            'kerbcycle-qr-manager',
            'SMS Settings',
            'SMS Settings',
            'manage_options',
            'kerbcycle-sms',
            ['\Kerbcycle\QrCode\Services\SmsService', 'render_settings_page']
        );

        add_submenu_page(
            'kerbcycle-qr-manager',
            'Plugin Integrations',
            'Integrations',
            'manage_options',
            'kerbcycle-plugin-integrations',
            [new Pages\IntegrationsPage(), 'render']
        );
    }
}
