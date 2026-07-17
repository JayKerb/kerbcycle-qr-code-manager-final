<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\AiSettingsPage;
use Kerbcycle\QrCode\Services\AiSettingsService;

final class AiSettingsPageSmokeTest extends TestCase
{
    private const OPTION_KEY =
        'kerbcycle_ai_webhook_options';

    private const DEV_WEBHOOK =
        'https://dev.example.test/webhook';

    private const STAGE_WEBHOOK =
        'https://stage.example.test/webhook';

    private const PROD_WEBHOOK =
        'https://prod.example.test/webhook';

    public function set_up(): void
    {
        parent::set_up();

        $this->resetAiSettingsState();
    }

    public function tear_down(): void
    {
        $this->resetAiSettingsState();

        parent::tear_down();
    }

    private function resetAiSettingsState(): void
    {
        global $wp_registered_settings;
        global $wp_settings_sections;
        global $wp_settings_fields;

        delete_option(self::OPTION_KEY);

        if (is_array($wp_registered_settings)) {
            unset(
                $wp_registered_settings[
                    self::OPTION_KEY
                ]
            );
        }

        if (is_array($wp_settings_sections)) {
            unset(
                $wp_settings_sections[
                    self::OPTION_KEY
                ]
            );
        }

        if (is_array($wp_settings_fields)) {
            unset(
                $wp_settings_fields[
                    self::OPTION_KEY
                ]
            );
        }

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        wp_set_current_user(0);
    }

    private function pageWithoutHooks(): AiSettingsPage
    {
        $reflection = new \ReflectionClass(
            AiSettingsPage::class
        );

        /** @var AiSettingsPage $page */
        $page = $reflection->newInstanceWithoutConstructor();

        return $page;
    }

    private function registeredPage(): AiSettingsPage
    {
        $page = $this->pageWithoutHooks();

        $page->register_settings();

        return $page;
    }

    private function captureOutput(
        callable $callback
    ): string {
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

    private function renderPage(
        array $options
    ): string {
        update_option(
            self::OPTION_KEY,
            $options,
            false
        );

        wp_set_current_user(
            $this->create_admin_user()
        );

        $page = $this->registeredPage();

        return $this->captureOutput(
            static function () use ($page): void {
                $page->render();
            }
        );
    }

    public function test_ai_settings_registers_setting_section_and_fields(): void
    {
        global $wp_registered_settings;
        global $wp_settings_sections;
        global $wp_settings_fields;

        $this->registeredPage();

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
            'kc_ai_main',
            $wp_settings_sections[
                self::OPTION_KEY
            ]
        );

        $expectedFields = [
            'env',
            'webhook_url_dev',
            'webhook_url_stage',
            'webhook_url_prod',
            'timeout',
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $wp_settings_fields[
                    self::OPTION_KEY
                ]['kc_ai_main']
            );
        }
    }

    public function test_sanitize_options_handles_non_array_input(): void
    {
        $page = $this->pageWithoutHooks();

        $sanitized = $page->sanitize_options(
            'not-an-array'
        );

        $this->assertSame(
            'dev',
            $sanitized['env']
        );

        $this->assertSame(
            '',
            $sanitized['webhook_url_dev']
        );

        $this->assertSame(
            '',
            $sanitized['webhook_url_stage']
        );

        $this->assertSame(
            '',
            $sanitized['webhook_url_prod']
        );

        $this->assertSame(
            20,
            $sanitized['timeout']
        );
    }

    public function test_sanitize_options_accepts_and_normalizes_valid_values(): void
    {
        $page = $this->pageWithoutHooks();

        $sanitized = $page->sanitize_options(
            [
                'env' => 'prod',
                'webhook_url_dev'
                    => '  ' . self::DEV_WEBHOOK . '  ',
                'webhook_url_stage'
                    => self::STAGE_WEBHOOK,
                'webhook_url_prod'
                    => self::PROD_WEBHOOK,
                'timeout' => '35',
            ]
        );

        $this->assertSame(
            'prod',
            $sanitized['env']
        );

        $this->assertSame(
            self::DEV_WEBHOOK,
            $sanitized['webhook_url_dev']
        );

        $this->assertSame(
            self::STAGE_WEBHOOK,
            $sanitized['webhook_url_stage']
        );

        $this->assertSame(
            self::PROD_WEBHOOK,
            $sanitized['webhook_url_prod']
        );

        $this->assertSame(
            35,
            $sanitized['timeout']
        );
    }

    public function test_sanitize_options_rejects_unsafe_urls_and_clamps_timeout(): void
    {
        $page = $this->pageWithoutHooks();

        $minimum = $page->sanitize_options(
            [
                'env' => 'dev',
                'webhook_url_dev'
                    => 'javascript:alert(1)',
                'webhook_url_stage'
                    => 'data:text/html,unsafe',
                'webhook_url_prod'
                    => self::PROD_WEBHOOK,
                'timeout' => '-100',
            ]
        );

        $this->assertSame(
            '',
            $minimum['webhook_url_dev']
        );

        $this->assertSame(
            '',
            $minimum['webhook_url_stage']
        );

        $this->assertSame(
            self::PROD_WEBHOOK,
            $minimum['webhook_url_prod']
        );

        $this->assertSame(
            1,
            $minimum['timeout']
        );

        $maximum = $page->sanitize_options(
            [
                'timeout' => '500',
            ]
        );

        $this->assertSame(
            60,
            $maximum['timeout']
        );
    }

    public function test_ai_settings_page_renders_registered_fields_and_active_webhook(): void
    {
        $html = $this->renderPage(
            [
                'env' => 'stage',
                'webhook_url_dev'
                    => self::DEV_WEBHOOK,
                'webhook_url_stage'
                    => self::STAGE_WEBHOOK,
                'webhook_url_prod'
                    => self::PROD_WEBHOOK,
                'timeout' => 45,
            ]
        );

        $this->assertStringContainsString(
            '<h1>AI Settings</h1>',
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
                . 'value=[\'"]'
                . preg_quote(self::OPTION_KEY, '/')
                . '[\'"]/',
            $html
        );

        $this->assertSame(
            1,
            $optionPageCount
        );

        $nonceCount = preg_match(
            '/name=[\'"]_wpnonce[\'"]/',
            $html
        );

        $this->assertSame(
            1,
            $nonceCount
        );

        $this->assertStringContainsString(
            'name="'
                . self::OPTION_KEY
                . '[env]"',
            $html
        );

        $this->assertStringContainsString(
            'name="'
                . self::OPTION_KEY
                . '[webhook_url_dev]"',
            $html
        );

        $this->assertStringContainsString(
            'name="'
                . self::OPTION_KEY
                . '[webhook_url_stage]"',
            $html
        );

        $this->assertStringContainsString(
            'name="'
                . self::OPTION_KEY
                . '[webhook_url_prod]"',
            $html
        );

        $this->assertStringContainsString(
            'name="'
                . self::OPTION_KEY
                . '[timeout]"',
            $html
        );

        $this->assertStringContainsString(
            'value="' . self::DEV_WEBHOOK . '"',
            $html
        );

        $this->assertStringContainsString(
            'value="' . self::STAGE_WEBHOOK . '"',
            $html
        );

        $this->assertStringContainsString(
            'value="' . self::PROD_WEBHOOK . '"',
            $html
        );

        $this->assertStringContainsString(
            'value="45"',
            $html
        );

        $selectedStageCount = preg_match(
            '/<option\b[^>]*'
                . 'value=[\'"]stage[\'"]'
                . '[^>]*selected=[\'"]selected[\'"]/',
            $html
        );

        $this->assertSame(
            1,
            $selectedStageCount
        );

        $this->assertStringContainsString(
            '<code>' . self::STAGE_WEBHOOK . '</code>',
            $html
        );

        $this->assertStringContainsString(
            'Save AI Settings',
            $html
        );

        $this->assertStringNotContainsString(
            'Not configured',
            $html
        );
    }

    public function test_ai_settings_page_renders_not_configured_fallback(): void
    {
        $html = $this->renderPage(
            [
                'env' => 'prod',
                'webhook_url_dev' => '',
                'webhook_url_stage' => '',
                'webhook_url_prod' => '',
                'timeout' => 20,
            ]
        );

        $this->assertStringContainsString(
            'Active webhook',
            $html
        );

        $this->assertStringContainsString(
            '<code>Not configured</code>',
            $html
        );
    }

    public function test_ai_settings_page_escapes_stored_webhook_values(): void
    {
        $malicious =
            '<script>webhookAttack()</script>';

        $html = $this->renderPage(
            [
                'env' => 'dev',
                'webhook_url_dev' => $malicious,
                'webhook_url_stage' => '',
                'webhook_url_prod' => '',
                'timeout' => 20,
            ]
        );

        $this->assertStringContainsString(
            '&lt;script&gt;webhookAttack()'
                . '&lt;/script&gt;',
            $html
        );

        $this->assertStringNotContainsString(
            $malicious,
            $html
        );
    }

    public function test_ai_settings_service_resolves_each_environment(): void
    {
        $options = [
            'env' => 'dev',
            'webhook_url_dev'
                => self::DEV_WEBHOOK,
            'webhook_url_stage'
                => self::STAGE_WEBHOOK,
            'webhook_url_prod'
                => self::PROD_WEBHOOK,
            'timeout' => 20,
        ];

        $this->assertSame(
            self::DEV_WEBHOOK,
            AiSettingsService::current_webhook_url(
                array_merge(
                    $options,
                    ['env' => 'dev']
                )
            )
        );

        $this->assertSame(
            self::STAGE_WEBHOOK,
            AiSettingsService::current_webhook_url(
                array_merge(
                    $options,
                    ['env' => 'stage']
                )
            )
        );

        $this->assertSame(
            self::PROD_WEBHOOK,
            AiSettingsService::current_webhook_url(
                array_merge(
                    $options,
                    ['env' => 'prod']
                )
            )
        );

        $this->assertSame(
            '',
            AiSettingsService::current_webhook_url(
                array_merge(
                    $options,
                    ['env' => 'invalid']
                )
            )
        );
    }

    public function test_ai_settings_service_clamps_current_timeout(): void
    {
        $this->assertSame(
            1,
            AiSettingsService::current_timeout(
                ['timeout' => 0]
            )
        );

        $this->assertSame(
            30,
            AiSettingsService::current_timeout(
                ['timeout' => 30]
            )
        );

        $this->assertSame(
            60,
            AiSettingsService::current_timeout(
                ['timeout' => 100]
            )
        );

        $this->assertSame(
            20,
            AiSettingsService::current_timeout(
                []
            )
        );
    }
}
