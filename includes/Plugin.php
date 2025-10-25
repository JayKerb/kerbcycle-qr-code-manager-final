<?php

namespace Kerbcycle\QrCode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin class.
 * Orchestrates the entire plugin.
 */
class Plugin
{
    /**
     * The single instance of the class.
     */
    private static $_instance = null;

    /**
     * Main Plugin Instance.
     * Ensures only one instance of the plugin is loaded.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Plugin constructor.
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the plugin.
     *
     * Load the different components of the plugin.
     */
    public function init()
    {
        // Load admin components
        if (is_admin()) {
            new \Kerbcycle\QrCode\Admin\Admin();
            new \Kerbcycle\QrCode\Admin\Assets\AdminAssets();
            new \Kerbcycle\QrCode\Admin\Ajax\AdminAjax();
            new \Kerbcycle\QrCode\Admin\Pages\SettingsPage();
            \Kerbcycle\QrCode\Admin\Pages\GeneratorPage::instance();
            new \Kerbcycle\QrCode\Services\SmsService();
        }

        // Load public components
        new \Kerbcycle\QrCode\Public\Shortcodes();
        new \Kerbcycle\QrCode\Public\FrontAssets();

        // Load API components
        new \Kerbcycle\QrCode\Api\RestService();

        // Load services
        // These will be instantiated as needed by other classes
    }
}
