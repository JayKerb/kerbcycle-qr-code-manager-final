<?php

declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    fwrite(STDERR, "Could not find WordPress tests bootstrap in {$_tests_dir}.\n");
    fwrite(STDERR, "Set WP_TESTS_DIR to your wordpress-tests-lib path.\n");
    exit(1);
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__, 2) . '/kerbcycle-qr-code-manager.php';
});

require $_tests_dir . '/includes/bootstrap.php';
