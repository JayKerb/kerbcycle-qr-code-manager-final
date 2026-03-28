<?php

namespace Kerbcycle\QrCode\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized service for managing AI webhook settings.
 */
class AiSettingsService
{
    private const OPTION_KEY = 'kerbcycle_ai_webhook_options';

    /**
     * Retrieve stored options merged with defaults.
     */
    public static function get_options()
    {
        return wp_parse_args(get_option(self::OPTION_KEY, []), self::defaults());
    }

    /**
     * Provide default option values.
     */
    public static function defaults()
    {
        return [
            'env'                  => 'dev',
            'webhook_url_dev'      => '',
            'webhook_url_stage'    => '',
            'webhook_url_prod'     => '',
            'timeout'              => 20,
        ];
    }

    /**
     * Determine the active webhook URL based on selected environment.
     */
    public static function current_webhook_url($options = null)
    {
        $options = $options ?: self::get_options();
        $environment = $options['env'];
        $map = [
            'dev'   => $options['webhook_url_dev'],
            'stage' => $options['webhook_url_stage'],
            'prod'  => $options['webhook_url_prod'],
        ];

        $url = isset($map[$environment]) ? trim((string) $map[$environment]) : '';

        /**
         * Filter the resolved pickup exception webhook URL.
         */
        return apply_filters('kerbcycle/ai/pickup_exception_webhook_url', $url, $options);
    }

    /**
     * Determine the configured timeout.
     */
    public static function current_timeout($options = null)
    {
        $options = $options ?: self::get_options();
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 20;

        return max(1, min(60, $timeout));
    }
}
