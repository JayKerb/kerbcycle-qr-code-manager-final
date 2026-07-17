<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\ErrorsPage;

final class ErrorsPageSmokeTest extends TestCase
{
    private function errorLogTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_error_logs';
    }

    private function resetErrorState(): void
    {
        global $wpdb;

        $wpdb->query(
            'TRUNCATE TABLE ' . $this->errorLogTableName()
        );

        $_GET = [];

        wp_set_current_user(0);
    }

    private function runWithCleanErrors(callable $callback): void
    {
        $this->resetErrorState();

        try {
            $callback();
        } finally {
            $this->resetErrorState();
        }
    }

    private function insertErrorLog(
        string $type,
        string $message,
        string $page,
        string $status,
        string $createdAt
    ): int {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->errorLogTableName(),
            [
                'type' => $type,
                'message' => $message,
                'page' => $page,
                'status' => $status,
                'created_at' => $createdAt,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        $this->assertSame(1, $inserted);

        return (int) $wpdb->insert_id;
    }

    private function renderErrorsPage(
        int $userId,
        array $query = []
    ): string {
        wp_set_current_user($userId);

        $_GET = $query;

        $bufferLevel = ob_get_level();

        ob_start();

        try {
            (new ErrorsPage())->render();

            return (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    public function test_errors_page_requires_manage_options(): void
    {
        $this->runWithCleanErrors(
            function (): void {
                $subscriberId = $this->create_subscriber_user();

                $html = $this->renderErrorsPage(
                    $subscriberId,
                    [
                        'status' => 'failure',
                    ]
                );

                $this->assertSame('', trim($html));
            }
        );
    }

    public function test_errors_page_renders_empty_state_and_filter_form(): void
    {
        $this->runWithCleanErrors(
            function (): void {
                $adminId = $this->create_admin_user();

                $html = $this->renderErrorsPage(
                    $adminId,
                    [
                        'status' => 'failure',
                    ]
                );

                $this->assertStringContainsString(
                    '<h1>Errors</h1>',
                    $html
                );

                $this->assertStringContainsString(
                    'name="page" value="kerbcycle-errors"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="s"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="status"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="log_page"',
                    $html
                );

                $this->assertStringContainsString(
                    'No errors found.',
                    $html
                );

                $this->assertStringNotContainsString(
                    'tablenav-pages',
                    $html
                );

                $selectedStatusCount = preg_match_all(
                    '/value=(["\'])failure\1[^>]*'
                    . 'selected=(["\'])selected\2/',
                    $html
                );

                $this->assertSame(
                    1,
                    $selectedStatusCount
                );
            }
        );
    }

    public function test_errors_page_renders_structured_and_legacy_messages_safely(): void
    {
        $this->runWithCleanErrors(
            function (): void {
                $structuredMessage = wp_json_encode(
                    [
                        'action' => 'retry_pickup',
                        'status' => 'failure',
                        'actor_user_id' => '77',
                        'context' => [
                            'qr_code' => 'QR-STRUCTURED',
                            'pickup_exception_id' => '456',
                        ],
                        'reason' => 'Remote webhook returned 503',
                        'raw_note' => '<script>alert(1)</script>',
                    ]
                );

                $this->assertIsString($structuredMessage);

                $this->insertErrorLog(
                    'webhook',
                    $structuredMessage,
                    'pickup-exceptions',
                    'failure',
                    '2026-07-10 12:00:00'
                );

                $this->insertErrorLog(
                    'legacy',
                    '<strong>Legacy failure message</strong> with details',
                    'messages',
                    'failure',
                    '2026-07-09 11:00:00'
                );

                $adminId = $this->create_admin_user();

                $html = $this->renderErrorsPage(
                    $adminId,
                    [
                        'status' => 'failure',
                    ]
                );

                $this->assertStringContainsString(
                    'Action: retry_pickup',
                    $html
                );

                $this->assertStringContainsString(
                    'Status: failure',
                    $html
                );

                $this->assertStringContainsString(
                    'QR: QR-STRUCTURED',
                    $html
                );

                $this->assertStringContainsString(
                    'Exception: 456',
                    $html
                );

                $this->assertStringContainsString(
                    'Actor: #77',
                    $html
                );

                $this->assertStringContainsString(
                    'Reason:',
                    $html
                );

                $this->assertStringContainsString(
                    'Remote webhook returned 503',
                    $html
                );

                $this->assertStringContainsString(
                    'Raw payload',
                    $html
                );

                $this->assertStringContainsString(
                    '&quot;action&quot;',
                    $html
                );

                $this->assertStringContainsString(
                    '&lt;script&gt;',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<script>alert(1)</script>',
                    $html
                );

                $this->assertStringContainsString(
                    'Legacy failure message with details',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<strong>Legacy failure message</strong>',
                    $html
        );
            }
        );
    }

    public function test_errors_page_applies_search_status_and_page_filters(): void
    {
        $this->runWithCleanErrors(
            function (): void {
                $this->insertErrorLog(
                    'sms',
                    'FILTER-ALPHA delivery timeout',
                    'messages',
                    'failure',
                    '2026-07-01 08:00:00'
                );

                $this->insertErrorLog(
                    'webhook',
                    'FILTER-BETA delivery timeout',
                    'pickup-exceptions',
                    'failure',
                    '2026-07-02 08:00:00'
                );

                $this->insertErrorLog(
                    'sync',
                    'FILTER-GAMMA successful synchronization',
                    'messages',
                    'success',
                    '2026-07-03 08:00:00'
                );

                $this->insertErrorLog(
                    'security',
                    'FILTER-DELTA unrelated failure',
                    'settings',
                    'failure',
                    '2026-07-04 08:00:00'
                );

                $adminId = $this->create_admin_user();

                $html = $this->renderErrorsPage(
                    $adminId,
                    [
                        's' => 'delivery timeout',
                        'status' => 'failure',
                        'log_page' => 'messages',
                    ]
                );

                $this->assertStringContainsString(
                    'FILTER-ALPHA delivery timeout',
                    $html
                );

                $this->assertStringNotContainsString(
                    'FILTER-BETA delivery timeout',
                    $html
                );

                $this->assertStringNotContainsString(
                    'FILTER-GAMMA successful synchronization',
                    $html
                );

                $this->assertStringNotContainsString(
                    'FILTER-DELTA unrelated failure',
                    $html
                );

                $this->assertStringContainsString(
                    'name="s" value="delivery timeout"',
                    $html
                );

                $selectedStatusCount = preg_match_all(
                    '/value=(["\'])failure\1[^>]*'
                    . 'selected=(["\'])selected\2/',
                    $html
                );

                $this->assertSame(
                    1,
                    $selectedStatusCount
                );

                $selectedPageCount = preg_match_all(
                    '/value=(["\'])messages\1[^>]*'
                    . 'selected=(["\'])selected\2/',
                    $html
                );

                $this->assertSame(
                    1,
                    $selectedPageCount
                );
            }
        );
    }

    public function test_errors_page_applies_pagination_offset(): void
    {
        $this->runWithCleanErrors(
            function (): void {
                for ($index = 1; $index <= 21; $index++) {
                    $this->insertErrorLog(
                        'pagination',
                        sprintf(
                            'PAGINATION-%02d',
                            $index
                        ),
                        'errors-page-test',
                        'failure',
                        sprintf(
                            '2026-07-01 00:00:%02d',
                            $index
                        )
                    );
                }

                $adminId = $this->create_admin_user();

                $html = $this->renderErrorsPage(
                    $adminId,
                    [
                        's' => 'PAGINATION-',
                        'status' => 'failure',
                        'log_page' => 'errors-page-test',
                        'paged' => '2',
                    ]
                );

                $this->assertStringContainsString(
                    'PAGINATION-01',
                    $html
                );

                $this->assertStringNotContainsString(
                    'PAGINATION-21',
                    $html
                );

                $this->assertSame(
                    1,
                    substr_count(
                        $html,
                        'tablenav-pages'
                    )
                );

                $this->assertStringContainsString(
                    'page-numbers current',
                    $html
                );
            }
        );
    }
}
