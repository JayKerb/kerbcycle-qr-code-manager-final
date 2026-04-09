<?php

namespace Kerbcycle\QrCode\Admin\Pages;

use Kerbcycle\QrCode\Services\AiSettingsService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page and helpers for configuring AI webhook integration.
 */
class AiSettingsPage
{
    private const OPTION_KEY = 'kerbcycle_ai_webhook_options';

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor hooks.
     */
    private function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Render the admin page.
     */
    public function render()
    {
        $options = AiSettingsService::get_options();
        $webhook_url = AiSettingsService::current_webhook_url($options);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Settings', 'kerbcycle'); ?></h1>
            <p class="description"><?php esc_html_e('The selected environment determines which pickup-exception webhook URL is active.', 'kerbcycle'); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_KEY);
        do_settings_sections(self::OPTION_KEY);
        submit_button(__('Save AI Settings', 'kerbcycle'));
        ?>
            </form>

            <h2><?php esc_html_e('Active webhook', 'kerbcycle'); ?></h2>
            <p>
                <code><?php echo esc_html($webhook_url !== '' ? $webhook_url : __('Not configured', 'kerbcycle')); ?></code>
            </p>
        </div>
        <?php
    }

    /**
     * Register AI webhook settings and fields.
     */
    public function register_settings()
    {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [$this, 'sanitize_options']);

        add_settings_section(
            'kc_ai_main',
            __('Pickup Exception Webhook', 'kerbcycle'),
            '__return_false',
            self::OPTION_KEY
        );

        add_settings_field(
            'env',
            __('Environment', 'kerbcycle'),
            [$this, 'render_environment_field'],
            self::OPTION_KEY,
            'kc_ai_main'
        );

        add_settings_field(
            'webhook_url_dev',
            __('Development webhook URL', 'kerbcycle'),
            function () {
                $this->render_webhook_url_field('webhook_url_dev');
            },
            self::OPTION_KEY,
            'kc_ai_main'
        );

        add_settings_field(
            'webhook_url_stage',
            __('Staging webhook URL', 'kerbcycle'),
            function () {
                $this->render_webhook_url_field('webhook_url_stage');
            },
            self::OPTION_KEY,
            'kc_ai_main'
        );

        add_settings_field(
            'webhook_url_prod',
            __('Production webhook URL', 'kerbcycle'),
            function () {
                $this->render_webhook_url_field('webhook_url_prod');
            },
            self::OPTION_KEY,
            'kc_ai_main'
        );

        add_settings_field(
            'timeout',
            __('Request timeout (s)', 'kerbcycle'),
            [$this, 'render_timeout_field'],
            self::OPTION_KEY,
            'kc_ai_main'
        );
    }

    /**
     * Sanitize saved options.
     *
     * @param array $input Raw options.
     */
    public function sanitize_options($input)
    {
        $input = is_array($input) ? $input : [];
        $defaults = AiSettingsService::defaults();

        $output = [];
        $env = isset($input['env']) ? $input['env'] : $defaults['env'];
        $output['env'] = in_array($env, ['dev', 'stage', 'prod'], true) ? $env : $defaults['env'];

        foreach (['webhook_url_dev', 'webhook_url_stage', 'webhook_url_prod'] as $key) {
            $output[$key] = esc_url_raw(trim($input[$key] ?? ''));
        }

        $timeout = isset($input['timeout']) ? (int) $input['timeout'] : $defaults['timeout'];
        $output['timeout'] = max(1, min(60, $timeout));

        return $output;
    }

    /**
     * Render environment selector.
     */
    public function render_environment_field()
    {
        $options = AiSettingsService::get_options();
        ?>
        <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[env]">
            <option value="dev" <?php selected($options['env'], 'dev'); ?>><?php esc_html_e('Development', 'kerbcycle'); ?></option>
            <option value="stage" <?php selected($options['env'], 'stage'); ?>><?php esc_html_e('Staging', 'kerbcycle'); ?></option>
            <option value="prod" <?php selected($options['env'], 'prod'); ?>><?php esc_html_e('Production', 'kerbcycle'); ?></option>
        </select>
        <?php
    }

    /**
     * Render webhook URL input field.
     */
    private function render_webhook_url_field($key)
    {
        $options = AiSettingsService::get_options();
        printf(
            '<input type="url" size="60" name="%1$s[%2$s]" value="%3$s" placeholder="https://your-n8n.example.com/webhook/..." />',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_attr($options[$key])
        );
    }

    /**
     * Render timeout field.
     */
    public function render_timeout_field()
    {
        $options = AiSettingsService::get_options();
        printf(
            '<input type="number" min="1" max="60" name="%1$s[timeout]" value="%2$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($options['timeout'])
        );
    }
}
