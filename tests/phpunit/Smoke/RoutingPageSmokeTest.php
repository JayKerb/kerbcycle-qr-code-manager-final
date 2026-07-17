<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\RoutingPage;

final class RoutingPageSmokeTest extends TestCase
{
    private const OPTION_KEY = 'kerbcycle_osrm_options';

    private function resetRoutingState(): void
    {
        global $wp_settings_errors;

        delete_option(self::OPTION_KEY);

        $wp_settings_errors = [];

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        wp_set_current_user(0);
    }

    public function set_up(): void
    {
        parent::set_up();

        $this->resetRoutingState();
    }

    public function tear_down(): void
    {
        $this->resetRoutingState();

        parent::tear_down();
    }

    private function captureOutput(callable $callback): string
    {
        $bufferLevel = ob_get_level();

        ob_start();

        try {
            $callback();

            return (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    private function renderRoutingPage(array $options): string
    {
        update_option(
            self::OPTION_KEY,
            $options,
            false
        );

        wp_set_current_user($this->create_admin_user());

        $page = RoutingPage::instance();

        /*
         * WordPress normally invokes this through admin_init before the
         * settings page renders. The test invokes it explicitly.
         */
        $page->register_settings();

        return $this->captureOutput(
            static function () use ($page): void {
                $page->render();
            }
        );
    }

    public function test_routing_page_registers_expected_hooks(): void
    {
        $page = RoutingPage::instance();

        $this->assertNotFalse(
            has_action(
                'admin_init',
                [$page, 'register_settings']
            )
        );

        $this->assertNotFalse(
            has_action(
                'wp_ajax_kc_osrm_test',
                [$page, 'ajax_test']
            )
        );
    }

    public function test_routing_page_registers_setting_section_and_fields(): void
    {
        global $wp_registered_settings;
        global $wp_settings_sections;
        global $wp_settings_fields;

        $page = RoutingPage::instance();
        $page->register_settings();

        $this->assertArrayHasKey(
            self::OPTION_KEY,
            $wp_registered_settings
        );

        $this->assertTrue(
            is_callable(
                $wp_registered_settings[
                    self::OPTION_KEY
                ]['sanitize_callback']
            )
        );

        $this->assertArrayHasKey(
            self::OPTION_KEY,
            $wp_settings_sections
        );

        $this->assertArrayHasKey(
            'kc_osrm_main',
            $wp_settings_sections[self::OPTION_KEY]
        );

        $expectedFields = [
            'env',
            'endpoint_dev',
            'endpoint_stage',
            'endpoint_prod',
            'profile',
            'tile_url',
            'tile_attrib',
            'default_start',
            'timeout',
            'deny_demo_in_prod',
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $wp_settings_fields[
                    self::OPTION_KEY
                ]['kc_osrm_main']
            );
        }
    }

    public function test_sanitize_options_accepts_and_normalizes_valid_values(): void
    {
        $page = RoutingPage::instance();

        $sanitized = $page->sanitize_options(
            [
                'env' => 'stage',
                'endpoint_dev'
                    => 'https://dev.example.test/osrm/',
                'endpoint_stage'
                    => ' https://stage.example.test/osrm/ ',
                'endpoint_prod'
                    => 'https://prod.example.test/osrm',
                'profile' => 'cycling',
                'tile_url'
                    => 'https://tiles.example.test/{z}/{x}/{y}.png',
                'tile_attrib'
                    => '<b>Map data</b>',
                'deny_demo_in_prod' => '1',
                'timeout' => '75',
                'default_start'
                    => ' 40.7300 , -73.9900 ',
            ]
        );

        $this->assertSame(
            'stage',
            $sanitized['env']
        );

        $this->assertSame(
            'https://dev.example.test/osrm/',
            $sanitized['endpoint_dev']
        );

        $this->assertSame(
            'https://stage.example.test/osrm/',
            $sanitized['endpoint_stage']
        );

        $this->assertSame(
            'https://prod.example.test/osrm',
            $sanitized['endpoint_prod']
        );

        $this->assertSame(
            'cycling',
            $sanitized['profile']
        );

        $this->assertSame(
            'https://tiles.example.test/{z}/{x}/{y}.png',
            $sanitized['tile_url']
        );

        $this->assertSame(
            'Map data',
            $sanitized['tile_attrib']
        );

        $this->assertSame(
            1,
            $sanitized['deny_demo_in_prod']
        );

        $this->assertSame(
            60,
            $sanitized['timeout']
        );

        $this->assertSame(
            '40.730000,-73.990000',
            $sanitized['default_start']
        );
    }

    public function test_sanitize_options_rejects_invalid_values_and_coordinates(): void
    {
        global $wp_settings_errors;

        $wp_settings_errors = [];

        $page = RoutingPage::instance();

        $sanitized = $page->sanitize_options(
            [
                'env' => 'qa',
                'endpoint_dev'
                    => 'javascript:alert(1)',
                'endpoint_stage' => '',
                'endpoint_prod' => '',
                'profile' => 'hovercraft',
                'timeout' => '-50',
                'default_start' => '91,181',
            ]
        );

        $this->assertSame(
            'dev',
            $sanitized['env']
        );

        $this->assertSame(
            '',
            $sanitized['endpoint_dev']
        );

        $this->assertSame(
            'driving',
            $sanitized['profile']
        );

        $this->assertSame(
            0,
            $sanitized['deny_demo_in_prod']
        );

        $this->assertSame(
            1,
            $sanitized['timeout']
        );

        $this->assertSame(
            '',
            $sanitized['default_start']
        );

        $errors = get_settings_errors(
            self::OPTION_KEY
        );

        $this->assertContains(
            'default_start_oob',
            array_column($errors, 'code')
        );
    }

    public function test_sanitize_options_rejects_malformed_default_start(): void
    {
        global $wp_settings_errors;

        $wp_settings_errors = [];

        $page = RoutingPage::instance();

        $sanitized = $page->sanitize_options(
            [
                'default_start'
                    => 'not-valid-coordinates',
            ]
        );

        $this->assertSame(
            '',
            $sanitized['default_start']
        );

        $errors = get_settings_errors(
            self::OPTION_KEY
        );

        $this->assertContains(
            'default_start_invalid',
            array_column($errors, 'code')
        );
    }

    public function test_routing_page_renders_saved_settings_and_test_controls(): void
    {
        $html = $this->renderRoutingPage(
            [
                'env' => 'stage',
                'endpoint_dev'
                    => 'https://dev.example.test/osrm',
                'endpoint_stage'
                    => 'https://stage.example.test/osrm',
                'endpoint_prod'
                    => 'https://prod.example.test/osrm',
                'profile' => 'walking',
                'tile_url'
                    => 'https://tiles.example.test/{z}/{x}/{y}.png',
                'tile_attrib' => 'Map data',
                'deny_demo_in_prod' => 1,
                'timeout' => 25,
                'default_start'
                    => '40.730000,-73.990000',
            ]
        );

        $this->assertStringContainsString(
            '<h1>OSRM Settings</h1>',
            $html
        );

        $this->assertStringContainsString(
            'method="post"',
            $html
        );

        $this->assertStringContainsString(
            'action="options.php"',
            $html
        );

        $optionPageCount = preg_match(
           '/name=[\'"]option_page[\'"]\s+'
               . 'value=[\'"]kerbcycle_osrm_options[\'"]/',
           $html
        );

        $this->assertSame(
            1,
            $optionPageCount
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options[env]"',
            $html
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options'
                . '[endpoint_dev]"',
            $html
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options'
                . '[endpoint_stage]"',
            $html
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options'
                . '[endpoint_prod]"',
            $html
        );

        $this->assertStringContainsString(
            'value="https://stage.example.test/osrm"',
            $html
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options[profile]"',
            $html
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options[tile_url]"',
            $html
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options[tile_attrib]"',
            $html
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options'
                . '[default_start]"',
            $html
        );

        $this->assertStringContainsString(
            'value="40.730000,-73.990000"',
            $html
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options[timeout]"'
                . ' value="25"',
            $html
        );

        $this->assertStringContainsString(
            'name="kerbcycle_osrm_options'
                . '[deny_demo_in_prod]"',
            $html
        );

        $selectedCount = preg_match_all(
            '/selected\s*=\s*([\'"])selected\1/',
            $html
        );

        $checkedCount = preg_match_all(
            '/checked\s*=\s*([\'"])checked\1/',
            $html
        );

        $this->assertSame(
            2,
            $selectedCount
        );

        $this->assertSame(
            1,
            $checkedCount
        );

        $this->assertStringContainsString(
            '<code>https://stage.example.test/osrm</code>',
            $html
        );

        $this->assertStringContainsString(
            '<code>walking</code>',
            $html
        );

        $this->assertStringContainsString(
            'Save OSRM Settings',
            $html
        );

        $this->assertStringContainsString(
            'id="kc-osrm-test"',
            $html
        );

        $this->assertStringContainsString(
            'id="kc-osrm-test-out"',
            $html
        );

        $this->assertStringContainsString(
            'action=kc_osrm_test',
            $html
        );

        $this->assertStringContainsString(
            '_wpnonce=',
            $html
        );

        $this->assertStringNotContainsString(
            'Production cannot use the public demo endpoint.',
            $html
        );
    }

    public function test_routing_page_warns_when_production_uses_demo_endpoint(): void
    {
        $html = $this->renderRoutingPage(
            [
                'env' => 'prod',
                'endpoint_dev'
                    => 'https://router.project-osrm.org',
                'endpoint_stage' => '',
                'endpoint_prod'
                    => 'https://router.project-osrm.org/',
                'profile' => 'driving',
                'tile_url'
                    => 'https://tiles.example.test/{z}/{x}/{y}.png',
                'tile_attrib' => 'Map data',
                'deny_demo_in_prod' => 1,
                'timeout' => 10,
                'default_start' => '',
            ]
        );

        $this->assertStringContainsString(
            'notice notice-error',
            $html
        );

        $this->assertStringContainsString(
            'Production cannot use the public demo endpoint.',
            $html
        );

        $this->assertStringContainsString(
            '<code>https://router.project-osrm.org</code>',
            $html
        );
    }
}
