<?php

declare(strict_types=1);

// Stub the handful of WordPress functions that Admin::capture_admin_notices uses.

namespace {
    if (!function_exists('add_action')) {
        function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
        {
            // No-op stub for testing.
        }
    }

    if (!function_exists('get_settings_errors')) {
        function get_settings_errors()
        {
            return $GLOBALS['mock_settings_errors'] ?? [];
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($value)
        {
            return is_string($value) ? trim($value) : $value;
        }
    }

    if (!function_exists('wp_unslash')) {
        function wp_unslash($value)
        {
            return $value;
        }
    }
}

// Provide a minimal ErrorLogRepository double that records log entries in memory.

namespace Kerbcycle\QrCode\Data\Repositories {
    class ErrorLogRepository
    {
        public static $logged = [];

        public static function log($args)
        {
            self::$logged[] = $args;
        }

        public static function reset()
        {
            self::$logged = [];
        }
    }
}

namespace {
    require_once __DIR__ . '/../includes/Admin/Admin.php';

    use Kerbcycle\QrCode\Admin\Admin;
    use Kerbcycle\QrCode\Data\Repositories\ErrorLogRepository;

    function assert_equals($expected, $actual, $message)
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                sprintf("Assertion failed: %s. Expected %s but got %s", $message, var_export($expected, true), var_export($actual, true))
            );
        }
    }

    $admin_reflection = new \ReflectionClass(Admin::class);
    /** @var Admin $admin */
    $admin = $admin_reflection->newInstanceWithoutConstructor();

    $_GET['page'] = 'kerbcycle-qr-settings';

    // Success notices should be logged as success regardless of whether WordPress
    // uses "success" or the legacy "updated" type.
    $scenarios = [
        ['type' => 'success', 'expected_status' => 'success'],
        ['type' => 'updated', 'expected_status' => 'success'],
        ['type' => 'notice-success', 'expected_status' => 'success'],
        ['type' => 'error', 'expected_status' => 'failure'],
    ];

    foreach ($scenarios as $scenario) {
        ErrorLogRepository::reset();
        $GLOBALS['mock_settings_errors'] = [
            [
                'code'    => 'settings_updated',
                'message' => 'Settings saved',
                'type'    => $scenario['type'],
            ],
        ];

        $admin->capture_admin_notices();

        assert_equals(1, count(ErrorLogRepository::$logged), 'One log entry should be recorded');
        $log = ErrorLogRepository::$logged[0];
        assert_equals('settings_updated', $log['type'], 'Log type should match the settings notice code');
        assert_equals($scenario['expected_status'], $log['status'], 'Log status should reflect the notice type');
    }

    echo "capture_admin_notices tests passed\n";
}
