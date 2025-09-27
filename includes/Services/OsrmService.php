<?php

namespace Kerbcycle\QrCode\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized service for managing OSRM settings.
 */
class OsrmService
{
    private const OPTION_KEY = 'kerbcycle_osrm_options';

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
            'env'              => 'dev',
            'endpoint_dev'     => 'https://router.project-osrm.org',
            'endpoint_stage'   => '',
            'endpoint_prod'    => '',
            'profile'          => 'driving',
            'tile_url'         => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'tile_attrib'      => '© OpenStreetMap',
            'deny_demo_in_prod' => 1,
            'timeout'          => 10,
        ];
    }

    /**
     * Determine the current endpoint based on selected environment.
     */
    public static function current_endpoint($options = null)
    {
        $options = $options ?: self::get_options();
        $environment = $options['env'];
        $map = [
            'dev'   => $options['endpoint_dev'],
            'stage' => $options['endpoint_stage'],
            'prod'  => $options['endpoint_prod'],
        ];
        $url = isset($map[$environment]) ? rtrim((string) $map[$environment], '/') : '';

        /**
         * Filter the resolved endpoint URL.
         */
        return apply_filters('kerbcycle/osrm/endpoint', $url, $options);
    }
}