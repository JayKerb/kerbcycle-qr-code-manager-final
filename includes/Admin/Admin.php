<?php

namespace Kerbcycle\QrCode\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Data\Repositories\ErrorLogRepository;

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
        add_action('admin_notices', [$this, 'capture_admin_notices'], 0);
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function register_admin_menu()
    {
        add_management_page(
            'KerbCycle AI Test',
            'KerbCycle AI Test',
            'manage_options',
            'kerbcycle-ai-test',
            [$this, 'render_ai_test_page']
        );

        add_menu_page(
            'QR Code Manager',
            'QR Codes',
            'manage_options',
            'kerbcycle-qr-manager',
            [new Pages\DashboardPage(), 'render'],
            'dashicons-admin-generic',
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

        $generator = Pages\GeneratorPage::instance();
        add_submenu_page(
            'kerbcycle-qr-manager',
            'QR Code Generator',
            'QR Code Generate',
            'manage_options',
            'kerbcycle-qr-generator',
            [$generator, 'render']
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
            'Errors',
            'Errors',
            'manage_options',
            'kerbcycle-errors',
            [new Pages\ErrorsPage(), 'render']
        );

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
        $routing = Pages\RoutingPage::instance();
        add_submenu_page(
            'kerbcycle-qr-manager',
            'OSRM Settings',
            'Routing',
            'manage_options',
            'kerbcycle-routing',
            [$routing, 'render']
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

    /**
     * Render a minimal Tools page to test the AI endpoint.
     */
    public function render_ai_test_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $customer_name = '';
        $result        = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('kerbcycle_ai_test_action', 'kerbcycle_ai_test_nonce');

            $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';

            $result = \kc_call_ai_endpoint(
                'draft_sms',
                array(
                    'customer_name' => $customer_name,
                )
            );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('KerbCycle AI Test', 'kerbcycle'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('kerbcycle_ai_test_action', 'kerbcycle_ai_test_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="customer_name"><?php echo esc_html__('Customer Name', 'kerbcycle'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="customer_name" name="customer_name" class="regular-text" value="<?php echo esc_attr($customer_name); ?>" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Test AI Endpoint', 'kerbcycle')); ?>
            </form>

            <?php if (null !== $result) : ?>
                <h2><?php echo esc_html__('Response', 'kerbcycle'); ?></h2>
                <pre><?php echo esc_html(wp_json_encode($result, JSON_PRETTY_PRINT)); ?></pre>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Capture and log admin notices for plugin pages.
     */
    public function capture_admin_notices()
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'kerbcycle') !== 0) {
            return;
        }

        $errors = get_settings_errors();
        if (empty($errors)) {
            return;
        }

        foreach ($errors as $err) {
            $notice_type = $err['type'] ?? '';

            ErrorLogRepository::log([
                'type'   => $err['code'] ?? '',
                'message' => $err['message'] ?? '',
                'page'   => $page,
                'status' => $this->determine_status_from_notice_type($notice_type),
            ]);
        }
    }

    /**
     * Map a WordPress notice type to a Kerbcycle log status value.
     *
     * WordPress historically uses both "updated" and "success" to represent
     * successful outcomes, so treat either (and any variant that contains the
     * keyword) as a success in the log. All other types are treated as
     * failures by default so they remain prominent in the Errors view.
     *
     * @param string $notice_type WordPress notice type (e.g. success, updated, error).
     * @return string
     */
    private function determine_status_from_notice_type($notice_type)
    {
        if (!is_string($notice_type)) {
            return 'failure';
        }

        $normalized = strtolower(trim($notice_type));
        $success_keywords = ['success', 'updated'];

        foreach ($success_keywords as $keyword) {
            if ($normalized === $keyword || strpos($normalized, $keyword) !== false) {
                return 'success';
            }
        }

        return 'failure';
    }
}
