<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\AiSettingsPage;
use Kerbcycle\QrCode\Admin\Pages\SettingsPage;

final class AdminSettingsPageSmokeTest extends TestCase
{
    private const AI_OPTIONS_KEY = 'kerbcycle_ai_webhook_options';
    private const DEV_WEBHOOK = 'https://ai-settings-dev.test/webhook';
    private const STAGE_WEBHOOK = 'https://ai-settings-stage.test/webhook';
    private const PROD_WEBHOOK = 'https://ai-settings-prod.test/webhook';

    public function tear_down(): void
    {
        delete_option('kerbcycle_qr_enable_email');
        delete_option('kerbcycle_qr_enable_sms');
        delete_option('kerbcycle_qr_enable_reminders');
        delete_option('kerbcycle_qr_enable_scanner');
        delete_option('kerbcycle_qr_disable_drag_drop');
        delete_option('kerbcycle_qr_codes_per_page');
        delete_option('kerbcycle_history_per_page');
        delete_option('kerbcycle_sms_history_per_page');
        delete_option('kerbcycle_email_history_per_page');
        delete_option(self::AI_OPTIONS_KEY);
    }

    private function captureOutput(callable $callback): string
    {
        ob_start();

        $callback();

        return (string) ob_get_clean();
    }

    public function test_settings_page_registers_expected_settings(): void
    {
        global $wp_registered_settings;

        $page = new SettingsPage();
        $page->register_settings();

        $this->assertArrayHasKey('kerbcycle_qr_enable_email', $wp_registered_settings);
        $this->assertArrayHasKey('kerbcycle_qr_enable_sms', $wp_registered_settings);
        $this->assertArrayHasKey('kerbcycle_qr_enable_reminders', $wp_registered_settings);
        $this->assertArrayHasKey('kerbcycle_qr_enable_scanner', $wp_registered_settings);
        $this->assertArrayHasKey('kerbcycle_qr_disable_drag_drop', $wp_registered_settings);
        $this->assertArrayHasKey('kerbcycle_qr_codes_per_page', $wp_registered_settings);
        $this->assertArrayHasKey('kerbcycle_history_per_page', $wp_registered_settings);
        $this->assertArrayHasKey('kerbcycle_sms_history_per_page', $wp_registered_settings);
        $this->assertArrayHasKey('kerbcycle_email_history_per_page', $wp_registered_settings);
    }

    public function test_settings_page_render_outputs_form_shell(): void
    {
        $page = new SettingsPage();

        $html = $this->captureOutput(
            static function () use ($page): void {
                $page->render();
            }
        );

        $this->assertStringContainsString('KerbCycle QR Settings', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('action="options.php"', $html);
        $this->assertStringContainsString('kerbcycle_qr_settings', $html);
    }

    public function test_settings_checkbox_fields_reflect_saved_options(): void
    {
        update_option('kerbcycle_qr_enable_email', 1, false);
        update_option('kerbcycle_qr_enable_sms', 1, false);
        update_option('kerbcycle_qr_enable_reminders', 1, false);
        update_option('kerbcycle_qr_enable_scanner', 1, false);
        update_option('kerbcycle_qr_disable_drag_drop', 1, false);

        $page = new SettingsPage();

        $html = $this->captureOutput(
            static function () use ($page): void {
                $page->render_enable_email_field();
                $page->render_enable_sms_field();
                $page->render_enable_reminders_field();
                $page->render_enable_scanner_field();
                $page->render_disable_drag_drop_field();
            }
        );

        $this->assertStringContainsString('name="kerbcycle_qr_enable_email"', $html);
        $this->assertStringContainsString('name="kerbcycle_qr_enable_sms"', $html);
        $this->assertStringContainsString('name="kerbcycle_qr_enable_reminders"', $html);
        $this->assertStringContainsString('name="kerbcycle_qr_enable_scanner"', $html);
        $this->assertStringContainsString('name="kerbcycle_qr_disable_drag_drop"', $html);
        $this->assertSame(5, substr_count($html, 'checked'));
    }

    public function test_settings_number_fields_reflect_saved_options(): void
    {
        update_option('kerbcycle_qr_codes_per_page', 15, false);
        update_option('kerbcycle_history_per_page', 25, false);
        update_option('kerbcycle_sms_history_per_page', 35, false);
        update_option('kerbcycle_email_history_per_page', 45, false);

        $page = new SettingsPage();

        $html = $this->captureOutput(
            static function () use ($page): void {
                $page->render_codes_per_page_field();
                $page->render_history_per_page_field();
                $page->render_sms_history_per_page_field();
                $page->render_email_history_per_page_field();
            }
        );

        $this->assertStringContainsString('name="kerbcycle_qr_codes_per_page" value="15"', $html);
        $this->assertStringContainsString('name="kerbcycle_history_per_page" value="25"', $html);
        $this->assertStringContainsString('name="kerbcycle_sms_history_per_page" value="35"', $html);
        $this->assertStringContainsString('name="kerbcycle_email_history_per_page" value="45"', $html);
    }

    public function test_ai_settings_sanitize_options_clamps_timeout_and_rejects_invalid_environment(): void
    {
        $page = AiSettingsPage::instance();

        $sanitized = $page->sanitize_options(
            [
                'env' => 'invalid',
                'webhook_url_dev' => self::DEV_WEBHOOK,
                'webhook_url_stage' => self::STAGE_WEBHOOK,
                'webhook_url_prod' => self::PROD_WEBHOOK,
                'timeout' => 999,
            ]
        );

        $this->assertSame('dev', $sanitized['env']);
        $this->assertSame(self::DEV_WEBHOOK, $sanitized['webhook_url_dev']);
        $this->assertSame(self::STAGE_WEBHOOK, $sanitized['webhook_url_stage']);
        $this->assertSame(self::PROD_WEBHOOK, $sanitized['webhook_url_prod']);
        $this->assertSame(60, $sanitized['timeout']);
    }

    public function test_ai_settings_sanitize_options_accepts_stage_and_minimum_timeout(): void
    {
        $page = AiSettingsPage::instance();

        $sanitized = $page->sanitize_options(
            [
                'env' => 'stage',
                'webhook_url_dev' => '',
                'webhook_url_stage' => self::STAGE_WEBHOOK,
                'webhook_url_prod' => '',
                'timeout' => 0,
            ]
        );

        $this->assertSame('stage', $sanitized['env']);
        $this->assertSame(self::STAGE_WEBHOOK, $sanitized['webhook_url_stage']);
        $this->assertSame(1, $sanitized['timeout']);
    }

    public function test_ai_settings_render_outputs_active_webhook(): void
    {
        update_option(
            self::AI_OPTIONS_KEY,
            [
                'env' => 'prod',
                'webhook_url_dev' => self::DEV_WEBHOOK,
                'webhook_url_stage' => self::STAGE_WEBHOOK,
                'webhook_url_prod' => self::PROD_WEBHOOK,
                'timeout' => 30,
            ],
            false
        );

        $page = AiSettingsPage::instance();

        $html = $this->captureOutput(
            static function () use ($page): void {
                $page->render();
            }
        );

        $this->assertStringContainsString('AI Settings', $html);
        $this->assertStringContainsString('Active webhook', $html);
        $this->assertStringContainsString(self::PROD_WEBHOOK, $html);
        $this->assertStringContainsString('Save AI Settings', $html);
    }

    public function test_ai_settings_render_environment_and_timeout_fields(): void
    {
        update_option(
            self::AI_OPTIONS_KEY,
            [
                'env' => 'stage',
                'webhook_url_dev' => self::DEV_WEBHOOK,
                'webhook_url_stage' => self::STAGE_WEBHOOK,
                'webhook_url_prod' => self::PROD_WEBHOOK,
                'timeout' => 45,
            ],
            false
        );

        $page = AiSettingsPage::instance();

        $html = $this->captureOutput(
            static function () use ($page): void {
                $page->render_environment_field();
                $page->render_timeout_field();
            }
        );

        $this->assertStringContainsString('value="dev"', $html);
        $this->assertStringContainsString('value="stage"', $html);
        $this->assertStringContainsString('value="prod"', $html);
        $this->assertStringContainsString('selected', $html);
        $this->assertStringContainsString('name="kerbcycle_ai_webhook_options[timeout]" value="45"', $html);
    }
}
