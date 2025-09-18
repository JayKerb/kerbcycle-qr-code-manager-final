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
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_enable_scanner');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_disable_drag_drop');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_qr_codes_per_page');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_history_per_page');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_sms_history_per_page');
        register_setting('kerbcycle_qr_settings', 'kerbcycle_email_history_per_page');

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

        add_settings_field(
            'kerbcycle_qr_enable_scanner',
            __('Enable Dashboard QR Scanner Camera', 'kerbcycle'),
            [$this, 'render_enable_scanner_field'],
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_qr_disable_drag_drop',
            __('Disable Drag and Drop Reordering', 'kerbcycle'),
            [$this, 'render_disable_drag_drop_field'],
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_qr_codes_per_page',
            __('QR Codes per Page', 'kerbcycle'),
            [$this, 'render_codes_per_page_field'],
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_history_per_page',
            __('History Entries per Page', 'kerbcycle'),
            [$this, 'render_history_per_page_field'],
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_sms_history_per_page',
            __('SMS History Entries per Page', 'kerbcycle'),
            [$this, 'render_sms_history_per_page_field'],
            'kerbcycle_qr_settings',
            'kerbcycle_qr_main'
        );

        add_settings_field(
            'kerbcycle_email_history_per_page',
            __('Email History Entries per Page', 'kerbcycle'),
            [$this, 'render_email_history_per_page_field'],
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

    public function render_enable_scanner_field()
    {
        $value = get_option('kerbcycle_qr_enable_scanner', 1);
        ?>
        <input type="checkbox" name="kerbcycle_qr_enable_scanner" value="1" <?php checked(1, $value); ?> />
        <span class="description"><?php esc_html_e('Allow camera use on the dashboard scanner', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_disable_drag_drop_field()
    {
        $value = get_option('kerbcycle_qr_disable_drag_drop', 0);
        ?>
        <input type="checkbox" name="kerbcycle_qr_disable_drag_drop" value="1" <?php checked(1, $value); ?> />
        <span class="description"><?php esc_html_e('Prevent drag-and-drop reordering on the QR Codes table', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_codes_per_page_field()
    {
        $value = get_option('kerbcycle_qr_codes_per_page', 20);
        ?>
        <input type="number" min="1" name="kerbcycle_qr_codes_per_page" value="<?= esc_attr($value); ?>" />
        <span class="description"><?php esc_html_e('Number of QR codes displayed per page', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_history_per_page_field()
    {
        $value = get_option('kerbcycle_history_per_page', 20);
        ?>
        <input type="number" min="1" name="kerbcycle_history_per_page" value="<?= esc_attr($value); ?>" />
        <span class="description"><?php esc_html_e('Number of history entries displayed per page', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_sms_history_per_page_field()
    {
        $value = get_option('kerbcycle_sms_history_per_page', 20);
        ?>
        <input type="number" min="1" name="kerbcycle_sms_history_per_page" value="<?= esc_attr($value); ?>" />
        <span class="description"><?php esc_html_e('Number of SMS history entries displayed per page', 'kerbcycle'); ?></span>
        <?php
    }

    public function render_email_history_per_page_field()
    {
        $value = get_option('kerbcycle_email_history_per_page', 20);
        ?>
        <input type="number" min="1" name="kerbcycle_email_history_per_page" value="<?= esc_attr($value); ?>" />
        <span class="description"><?php esc_html_e('Number of email history entries displayed per page', 'kerbcycle'); ?></span>
        <?php
    }
}
