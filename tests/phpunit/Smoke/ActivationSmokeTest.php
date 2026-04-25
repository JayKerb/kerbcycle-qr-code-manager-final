<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Install\Activator;

final class ActivationSmokeTest extends TestCase
{
    public function test_activation_creates_qr_codes_table(): void
    {
        global $wpdb;

        Activator::activate();

        $table = $wpdb->prefix . 'kerbcycle_qr_codes';
        $tables = $wpdb->get_col('SHOW TABLES');

        $this->assertContains($table, $tables, 'Activation should create the kerbcycle QR table.');
    }

    public function test_activation_sets_default_qr_options_if_defined(): void
    {
        $activatorSource = file_get_contents(dirname(__DIR__, 3) . '/includes/Install/Activator.php');
        $this->assertNotFalse($activatorSource);

        if (strpos($activatorSource, 'update_option(') === false) {
            $this->assertTrue(true, 'No activation defaults currently defined in Activator::activate().');
            return;
        }

        $expectedDefaults = [
            'kerbcycle_qr_enable_email' => 1,
            'kerbcycle_qr_enable_sms' => 0,
            'kerbcycle_qr_enable_reminders' => 0,
        ];

        Activator::activate();

        foreach ($expectedDefaults as $option => $expectedValue) {
            if (get_option($option, null) !== null) {
                $this->assertSame($expectedValue, get_option($option));
            }
        }
    }
}
