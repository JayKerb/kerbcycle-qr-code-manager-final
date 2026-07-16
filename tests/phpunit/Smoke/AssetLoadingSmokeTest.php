<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Assets\AdminAssets;
use Kerbcycle\QrCode\Public\FrontAssets;

final class AssetLoadingSmokeTest extends TestCase
{
    private const ADMIN_SCRIPT = 'kerbcycle-qr-admin-js';
    private const ADMIN_STYLE = 'kerbcycle-qr-admin-css';
    private const DASHBOARD_SCANNER = 'kerbcycle-dashboard-scanner-js';

    private const FRONT_SCRIPT = 'kerbcycle-qr-frontend-js';
    private const FRONT_STYLE = 'kerbcycle-qr-frontend-css';

    private const CHART_SCRIPT = 'chartjs';
    private const REPORT_SCRIPT = 'kerbcycle-qr-reports';

    private const HTML5_QR = 'html5-qrcode';
    private const ZXING = 'zxing-browser';
    private const JSQR = 'jsqr';

    private const LEAFLET = 'leaflet';
    private const ROUTING_MACHINE = 'lrm';
    private const LEAFLET_GEOCODER = 'leaflet-geocoder';
    private const OSRM_SCRIPT = 'kc-osrm';

    private const OSRM_OPTIONS = 'kerbcycle_osrm_options';
    private const ROUTING_ENDPOINT = 'https://routing-assets.test';

    private const SCRIPT_HANDLES = [
        self::ADMIN_SCRIPT,
        self::DASHBOARD_SCANNER,
        self::FRONT_SCRIPT,
        self::CHART_SCRIPT,
        self::REPORT_SCRIPT,
        self::HTML5_QR,
        self::ZXING,
        self::JSQR,
        self::LEAFLET,
        self::ROUTING_MACHINE,
        self::LEAFLET_GEOCODER,
        self::OSRM_SCRIPT,
    ];

    private const STYLE_HANDLES = [
        self::ADMIN_STYLE,
        self::FRONT_STYLE,
        self::LEAFLET,
        self::ROUTING_MACHINE,
        self::LEAFLET_GEOCODER,
    ];

    private function resetAssetState(): void
    {
        foreach (self::SCRIPT_HANDLES as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }

        foreach (self::STYLE_HANDLES as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }

        delete_option('kerbcycle_qr_enable_scanner');
        delete_option('kerbcycle_qr_disable_drag_drop');
        delete_option(self::OSRM_OPTIONS);

        unset($GLOBALS['post']);
    }

    private function runWithCleanAssets(callable $callback): void
    {
        $this->resetAssetState();

        try {
            $callback();
        } finally {
            $this->resetAssetState();
        }
    }

    private function adminAssetsWithoutPersistentHook(): AdminAssets
    {
        $assets = new AdminAssets();

        remove_action(
            'admin_enqueue_scripts',
            [$assets, 'enqueue_scripts']
        );

        return $assets;
    }

    private function frontAssetsWithoutPersistentHook(): FrontAssets
    {
        $assets = new FrontAssets();

        remove_action(
            'wp_enqueue_scripts',
            [$assets, 'enqueue_scripts']
        );

        return $assets;
    }

    private function scriptDependencies(string $handle): array
    {
        $scripts = wp_scripts();

        $this->assertArrayHasKey($handle, $scripts->registered);

        return $scripts->registered[$handle]->deps;
    }

    private function localizedData(string $handle, string $objectName): array
    {
        $rawData = wp_scripts()->get_data($handle, 'data');

        $this->assertIsString($rawData);

        $matches = [];
        $matched = preg_match(
            '/var\s+' . preg_quote($objectName, '/') . '\s*=\s*(\{.*\});/s',
            $rawData,
            $matches
        );

        $this->assertSame(
            1,
            $matched,
            'Expected localized JavaScript object was not found.'
        );

        $decoded = json_decode($matches[1], true);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function setCurrentPostContent(string $content): void
    {
        $postId = self::factory()->post->create(
            [
                'post_title' => 'Asset loading test',
                'post_content' => $content,
                'post_status' => 'publish',
            ]
        );

        $GLOBALS['post'] = get_post($postId);
    }

    public function test_asset_classes_register_expected_wordpress_hooks(): void
    {
        $adminAssets = new AdminAssets();
        $frontAssets = new FrontAssets();

        $this->assertSame(
            10,
            has_action(
                'admin_enqueue_scripts',
                [$adminAssets, 'enqueue_scripts']
            )
        );

        $this->assertSame(
            10,
            has_action(
                'wp_enqueue_scripts',
                [$frontAssets, 'enqueue_scripts']
            )
        );

        remove_action(
            'admin_enqueue_scripts',
            [$adminAssets, 'enqueue_scripts']
        );

        remove_action(
            'wp_enqueue_scripts',
            [$frontAssets, 'enqueue_scripts']
        );
    }

    public function test_admin_assets_ignore_unrelated_admin_screen(): void
    {
        $this->runWithCleanAssets(
            function (): void {
                $assets = $this->adminAssetsWithoutPersistentHook();

                $assets->enqueue_scripts('plugins.php');

                $this->assertFalse(
                    wp_script_is(self::ADMIN_SCRIPT, 'enqueued')
                );

                $this->assertFalse(
                    wp_style_is(self::ADMIN_STYLE, 'enqueued')
                );

                $this->assertFalse(
                    wp_script_is(self::DASHBOARD_SCANNER, 'enqueued')
                );
            }
        );
    }

    public function test_admin_report_screen_enqueues_chart_assets_and_inline_data(): void
    {
        $this->runWithCleanAssets(
            function (): void {
                $assets = $this->adminAssetsWithoutPersistentHook();

                $assets->enqueue_scripts(
                    'qr-codes_page_kerbcycle-qr-reports'
                );

                $this->assertTrue(
                    wp_script_is(self::CHART_SCRIPT, 'enqueued')
                );

                $this->assertTrue(
                    wp_script_is(self::REPORT_SCRIPT, 'enqueued')
                );

                $this->assertSame(
                    [self::CHART_SCRIPT],
                    $this->scriptDependencies(self::REPORT_SCRIPT)
                );

                $inlineData = wp_scripts()->get_data(
                    self::CHART_SCRIPT,
                    'after'
                );

                $this->assertIsArray($inlineData);

                $this->assertStringContainsString(
                    'const kerbcycleReportData = ',
                    implode("\n", $inlineData)
                );
            }
        );
    }

    public function test_admin_assets_respect_disabled_scanner_and_drag_drop(): void
    {
        $this->runWithCleanAssets(
            function (): void {
                update_option(
                    'kerbcycle_qr_enable_scanner',
                    0,
                    false
                );

                update_option(
                    'kerbcycle_qr_disable_drag_drop',
                    1,
                    false
                );

                $assets = $this->adminAssetsWithoutPersistentHook();

                $assets->enqueue_scripts(
                    'toplevel_page_kerbcycle-qr-manager'
                );

                $this->assertTrue(
                    wp_style_is(self::ADMIN_STYLE, 'enqueued')
                );

                $this->assertTrue(
                    wp_script_is(self::ADMIN_SCRIPT, 'enqueued')
                );

                $this->assertSame(
                    [],
                    $this->scriptDependencies(self::ADMIN_SCRIPT)
                );

                $this->assertFalse(
                    wp_script_is(self::ZXING, 'enqueued')
                );

                $this->assertFalse(
                    wp_script_is(self::JSQR, 'enqueued')
                );

                $this->assertFalse(
                    wp_script_is(self::DASHBOARD_SCANNER, 'enqueued')
                );

                $localized = $this->localizedData(
                    self::ADMIN_SCRIPT,
                    'kerbcycle_ajax'
                );

                $this->assertFalse($localized['scanner_enabled']);
                $this->assertTrue($localized['drag_drop_disabled']);
                $this->assertNotEmpty($localized['nonce']);
                $this->assertNotEmpty($localized['rest_nonce']);

                $this->assertStringContainsString(
                    'admin-ajax.php',
                    $localized['ajax_url']
                );

                $this->assertStringContainsString(
                    '/wp-json/kerbcycle/v1/ai',
                    $localized['rest_url']
                );
            }
        );
    }

    public function test_admin_assets_load_scanner_and_sortable_dependencies_when_enabled(): void
    {
        $this->runWithCleanAssets(
            function (): void {
                update_option(
                    'kerbcycle_qr_enable_scanner',
                    1,
                    false
                );

                update_option(
                    'kerbcycle_qr_disable_drag_drop',
                    0,
                    false
                );

                $assets = $this->adminAssetsWithoutPersistentHook();

                $assets->enqueue_scripts(
                    'kerbcycle-qr-manager_page_kerbcycle-qr-history'
                );

                $this->assertSame(
                    ['jquery-ui-sortable'],
                    $this->scriptDependencies(self::ADMIN_SCRIPT)
                );

                $this->assertTrue(
                    wp_script_is(self::ZXING, 'enqueued')
                );

                $this->assertTrue(
                    wp_script_is(self::JSQR, 'enqueued')
                );

                $this->assertTrue(
                    wp_script_is(self::DASHBOARD_SCANNER, 'enqueued')
                );

                $this->assertSame(
                    [
                        self::ZXING,
                        self::JSQR,
                        self::ADMIN_SCRIPT,
                    ],
                    $this->scriptDependencies(
                        self::DASHBOARD_SCANNER
                    )
                );

                $localized = $this->localizedData(
                    self::ADMIN_SCRIPT,
                    'kerbcycle_ajax'
                );

                $this->assertTrue($localized['scanner_enabled']);
                $this->assertFalse($localized['drag_drop_disabled']);
            }
        );
    }

    public function test_front_assets_register_osrm_assets_without_a_post(): void
    {
        $this->runWithCleanAssets(
            function (): void {
                update_option(
                    self::OSRM_OPTIONS,
                    [
                        'env' => 'stage',
                        'endpoint_stage' => self::ROUTING_ENDPOINT . '/',
                        'profile' => 'cycling',
                        'tile_url' => 'https://tiles-assets.test/{z}/{x}/{y}.png',
                        'tile_attrib' => 'Test tiles',
                        'default_start' => '40.7128,-74.0060',
                    ],
                    false
                );

                $assets = $this->frontAssetsWithoutPersistentHook();

                $assets->enqueue_scripts();

                $this->assertTrue(
                    wp_style_is(self::LEAFLET, 'registered')
                );

                $this->assertTrue(
                    wp_style_is(self::ROUTING_MACHINE, 'registered')
                );

                $this->assertTrue(
                    wp_style_is(self::LEAFLET_GEOCODER, 'registered')
                );

                $this->assertTrue(
                    wp_script_is(self::LEAFLET, 'registered')
                );

                $this->assertTrue(
                    wp_script_is(self::ROUTING_MACHINE, 'registered')
                );

                $this->assertTrue(
                    wp_script_is(self::LEAFLET_GEOCODER, 'registered')
                );

                $this->assertTrue(
                    wp_script_is(self::OSRM_SCRIPT, 'registered')
                );

                $this->assertFalse(
                    wp_script_is(self::FRONT_SCRIPT, 'enqueued')
                );

                $localized = $this->localizedData(
                    self::OSRM_SCRIPT,
                    'KC_OSRM'
                );

                $this->assertSame(
                    self::ROUTING_ENDPOINT . '/route/v1',
                    $localized['base']
                );

                $this->assertSame(
                    'cycling',
                    $localized['profile']
                );

                $this->assertSame(
                    'https://tiles-assets.test/{z}/{x}/{y}.png',
                    $localized['tileUrl']
                );

                $this->assertSame(
                    'Test tiles',
                    $localized['tileAttrib']
                );

                $this->assertSame(
                    '40.7128,-74.0060',
                    $localized['defaultStart']
                );
            }
        );
    }

    public function test_qr_table_shortcode_loads_front_assets_without_scanner_libraries(): void
    {
        $this->runWithCleanAssets(
            function (): void {
                $this->setCurrentPostContent(
                    '[kerbcycle_qr_table]'
                );

                $assets = $this->frontAssetsWithoutPersistentHook();

                $assets->enqueue_scripts();

                $this->assertTrue(
                    wp_style_is(self::FRONT_STYLE, 'enqueued')
                );

                $this->assertTrue(
                    wp_script_is(self::FRONT_SCRIPT, 'enqueued')
                );

                $this->assertSame(
                    [],
                    $this->scriptDependencies(self::FRONT_SCRIPT)
                );

                $this->assertFalse(
                    wp_script_is(self::HTML5_QR, 'enqueued')
                );

                $this->assertFalse(
                    wp_script_is(self::ZXING, 'enqueued')
                );

                $this->assertFalse(
                    wp_script_is(self::JSQR, 'enqueued')
                );

                $localized = $this->localizedData(
                    self::FRONT_SCRIPT,
                    'kerbcycle_ajax'
                );

                $this->assertFalse($localized['scanner_enabled']);
                $this->assertNotEmpty($localized['nonce']);

                $this->assertStringContainsString(
                    'admin-ajax.php',
                    $localized['ajax_url']
                );
            }
        );
    }

    public function test_scanner_shortcode_loads_scanner_libraries_and_dependencies(): void
    {
        $this->runWithCleanAssets(
            function (): void {
                $this->setCurrentPostContent(
                    '[kerbcycle_scanner]'
                );

                $assets = $this->frontAssetsWithoutPersistentHook();

                $assets->enqueue_scripts();

                $this->assertTrue(
                    wp_script_is(self::HTML5_QR, 'enqueued')
                );

                $this->assertTrue(
                    wp_script_is(self::ZXING, 'enqueued')
                );

                $this->assertTrue(
                    wp_script_is(self::JSQR, 'enqueued')
                );

                $this->assertTrue(
                    wp_style_is(self::FRONT_STYLE, 'enqueued')
                );

                $this->assertTrue(
                    wp_script_is(self::FRONT_SCRIPT, 'enqueued')
                );

                $this->assertSame(
                    [
                        self::HTML5_QR,
                        self::ZXING,
                        self::JSQR,
                    ],
                    $this->scriptDependencies(self::FRONT_SCRIPT)
                );

                $localized = $this->localizedData(
                    self::FRONT_SCRIPT,
                    'kerbcycle_ajax'
                );

                $this->assertTrue($localized['scanner_enabled']);
            }
        );
    }
}
