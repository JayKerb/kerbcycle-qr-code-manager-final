<?php

namespace Kerbcycle\QrCode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autoloader class.
 *
 * @since 1.0.0
 */
class Autoloader
{
    /**
     * Run autoloader.
     *
     * Register a function as an implementation of __autoload()
     *
     * @since 1.0.0
     * @access public
     * @static
     */
    public static function run()
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload.
     *
     * For a given class name, require the file that contains it.
     *
     * @param string $class The class name.
     */
    public static function autoload($class)
    {
        // Project-specific namespace prefix
        $prefix = 'Kerbcycle\\QrCode\\';

        // Base directory for the namespace prefix
        $base_dir = __DIR__ . '/';

        // Does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // No, move to the next registered autoloader
            return;
        }

        // Get the relative class name
        $relative_class = substr($class, $len);

        // Replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
}
