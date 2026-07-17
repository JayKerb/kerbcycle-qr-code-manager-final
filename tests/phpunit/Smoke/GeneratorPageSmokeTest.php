<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\GeneratorPage;

final class GeneratorPageSmokeTest extends TestCase
{
    private const QRCODE_SCRIPT = 'kerbcycle-qrcode';
    private const GENERATOR_SCRIPT = 'kerbcycle-qr-generator';
    private const GENERATOR_STYLE = 'kerbcycle-qr-generator';

    private function resetAssetState(): void
    {
        wp_dequeue_script(self::QRCODE_SCRIPT);
        wp_deregister_script(self::QRCODE_SCRIPT);

        wp_dequeue_script(self::GENERATOR_SCRIPT);
        wp_deregister_script(self::GENERATOR_SCRIPT);

        wp_dequeue_style(self::GENERATOR_STYLE);
        wp_deregister_style(self::GENERATOR_STYLE);
    }

    private function localizedData(
        string $handle,
        string $objectName
    ): array {
        $rawData = wp_scripts()->get_data($handle, 'data');

        $this->assertIsString($rawData);

        $matches = [];
        $matched = preg_match(
            '/var\s+' . preg_quote($objectName, '/')
            . '\s*=\s*(\{.*\});/s',
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

    private function renderGeneratorPage(int $userId): string
    {
        wp_set_current_user($userId);

        $bufferLevel = ob_get_level();

        ob_start();

        try {
            GeneratorPage::instance()->render();

            return (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    public function test_generator_page_registers_expected_hooks(): void
    {
        $page = GeneratorPage::instance();

        $this->assertNotFalse(
            has_action(
                'admin_enqueue_scripts',
                [$page, 'assets']
            )
        );

        $this->assertNotFalse(
            has_action(
                'wp_ajax_kerbcycle_generate_qr',
                [$page, 'ajax_generate_qr']
            )
        );

        $this->assertNotFalse(
            has_action(
                'admin_post_kerbcycle_export_qr_csv',
                [$page, 'handle_export_csv']
            )
        );
    }

    public function test_generator_assets_ignore_unrelated_admin_screen(): void
    {
        $this->resetAssetState();

        try {
            GeneratorPage::instance()->assets('plugins.php');

            $this->assertFalse(
                wp_script_is(self::QRCODE_SCRIPT, 'enqueued')
            );

            $this->assertFalse(
                wp_script_is(self::GENERATOR_SCRIPT, 'enqueued')
            );

            $this->assertFalse(
                wp_style_is(self::GENERATOR_STYLE, 'enqueued')
            );
        } finally {
            $this->resetAssetState();
        }
    }

    public function test_generator_assets_enqueue_dependencies_and_localized_data(): void
    {
        $this->resetAssetState();

        try {
            GeneratorPage::instance()->assets(
                'kerbcycle-qr-manager_page_kerbcycle-qr-generator'
            );

            $this->assertTrue(
                wp_script_is(self::QRCODE_SCRIPT, 'enqueued')
            );

            $this->assertTrue(
                wp_script_is(self::GENERATOR_SCRIPT, 'enqueued')
            );

            $this->assertTrue(
                wp_style_is(self::GENERATOR_STYLE, 'enqueued')
            );

            $scripts = wp_scripts();

            $this->assertArrayHasKey(
                self::GENERATOR_SCRIPT,
                $scripts->registered
            );

            $this->assertSame(
                [
                    'jquery',
                    self::QRCODE_SCRIPT,
                ],
                $scripts->registered[
                    self::GENERATOR_SCRIPT
                ]->deps
            );

            $localized = $this->localizedData(
                self::GENERATOR_SCRIPT,
                'KerbcycleQRGen'
            );

            $this->assertSame(
                admin_url('admin-ajax.php'),
                $localized['ajaxUrl']
            );

            $this->assertNotEmpty($localized['nonce']);

            $this->assertSame(
                1,
                wp_verify_nonce(
                    $localized['nonce'],
                    'kerbcycle_generate_qr'
                )
            );
        } finally {
            $this->resetAssetState();
        }
    }

    public function test_generator_page_renders_generation_and_export_forms(): void
    {
        $adminId = $this->create_admin_user();

        $html = $this->renderGeneratorPage($adminId);

        $this->assertStringContainsString(
            '<h1>QR Code Generator</h1>',
            $html
        );

        $this->assertStringContainsString(
            'id="kc-generate-form"',
            $html
        );

        $this->assertStringContainsString(
            'id="kc-gen-type"',
            $html
        );

        $this->assertStringContainsString(
            'value="single"',
            $html
        );

        $this->assertStringContainsString(
            'value="batch"',
            $html
        );

        $this->assertStringContainsString(
            'id="kc-code"',
            $html
        );

        $this->assertStringContainsString(
            'id="kc-count"',
            $html
        );

        $this->assertStringContainsString(
            'min="1" max="5000"',
            $html
        );

        $this->assertStringContainsString(
            'id="kc-prefix"',
            $html
        );

        $this->assertStringContainsString(
            'id="kc-length"',
            $html
        );

        $this->assertStringContainsString(
            'min="4" max="16"',
            $html
        );

        $this->assertStringContainsString(
            'Generate &amp; Save',
            $html
        );

        $this->assertStringContainsString(
            'name="action" value="kerbcycle_export_qr_csv"',
            $html
        );

        $this->assertStringContainsString(
            'name="kc_export_nonce"',
            $html
        );

        $this->assertStringContainsString(
            'name="from"',
            $html
        );

        $this->assertStringContainsString(
            'name="to"',
            $html
        );

        $this->assertStringContainsString(
            'name="format"',
            $html
        );

        $this->assertStringContainsString(
            'value="print"',
            $html
        );

        $this->assertStringContainsString(
            'value="csv"',
            $html
        );

        $this->assertStringContainsString(
            admin_url('admin-post.php'),
            $html
        );
    }
}
