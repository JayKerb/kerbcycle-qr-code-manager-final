<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The settings page.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Pages
 */
class SettingsPage
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Render the settings page.
     *
     * @since    1.0.0
     */
    public function render()
    {
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

    /**
     * Register the settings.
     *
     * @since    1.0.0
     */
    public function register_settings()
    {
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_email');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_sms');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_reminders');

        add_settings_section(
            'kerbcycle_qr_main',
            __('General Settings', 'kerbcycle'),
            '__return_false',
            'kerbcycle_qr_settings'
        );

        add_settings_field(
            'kerbcycle_qr_enable_email',
            __('Enable Email Notifications', 'kerbcycle'),
            [$this, 'render_enable_email_field'],
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_qr_enable_sms',
            __('Enable SMS Notifications', 'kerbcycle'),
            [$this, 'render_enable_sms_field'],
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_qr_enable_reminders',
            __('Enable Automated Reminders', 'kerbcycle'),
            [$this, 'render_enable_reminders_field'],
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );
    }

    public function render_enable_email_field()
    {
        $value = get_option('kerbcycle_qr_enable_email', 1);
        ?>
        <input type="checkbox" name="kerbcycle_qr_enable_email" value="1" <?php checked(1, $value); ?> />
        <span class="description"><?php esc_html_e('Send email when QR codes are assigned', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_enable_sms_field()
    {
        $value = get_option('kerbcycle_qr_enable_sms', 0);
        ?>
        <input type="checkbox" name="kerbcycle_qr_enable_sms" value="1" <?php checked(1, $value); ?> />
        <span class="description"><?php esc_html_e('Send SMS when QR codes are assigned', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_enable_reminders_field()
    {
        $value = get_option('kerbcycle_qr_enable_reminders', 0);
        ?>
        <input type="checkbox" name="kerbcycle_qr_enable_reminders" value="1" <?php checked(1, $value); ?> />
        <span class="description"><?php esc_html_e('Schedule automated reminders after assignment', 'kerbcycle'); ?></span>
        <?php
    }
}
