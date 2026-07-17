<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\HistoryPage;

final class HistoryPageSmokeTest extends TestCase
{
    private const PER_PAGE_OPTION = 'kerbcycle_history_per_page';

    private function historyTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_qr_code_history';
    }

    private function resetHistoryState(): void
    {
        global $wpdb;

        $wpdb->query(
            'TRUNCATE TABLE ' . $this->historyTableName()
        );

        delete_option(self::PER_PAGE_OPTION);

        $_GET = [];
    }

    private function runWithCleanHistory(callable $callback): void
    {
        $this->resetHistoryState();

        try {
            $callback();
        } finally {
            $this->resetHistoryState();
        }
    }

    private function insertHistoryRow(
        string $qrCode,
        ?int $userId,
        string $status,
        string $changedAt
    ): int {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->historyTableName(),
            [
                'qr_code' => $qrCode,
                'user_id' => $userId,
                'status' => $status,
                'changed_at' => $changedAt,
            ],
            [
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );

        $this->assertSame(1, $inserted);

        return (int) $wpdb->insert_id;
    }

    private function renderHistoryPage(array $query = []): string
    {
        $_GET = $query;

        $bufferLevel = ob_get_level();

        ob_start();

        try {
            (new HistoryPage())->render();

            return (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    public function test_history_page_renders_empty_state_and_filter_form(): void
    {
        $this->runWithCleanHistory(
            function (): void {
                $html = $this->renderHistoryPage();

                $this->assertStringContainsString(
                    '<h1>QR Code History</h1>',
                    $html
                );

                $this->assertStringContainsString(
                    'name="page" value="kerbcycle-qr-history"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="status_filter"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="start_date"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="end_date"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="s"',
                    $html
                );

                $this->assertStringContainsString(
                    'Recent QR code activity',
                    $html
                );

                $this->assertStringContainsString(
                    'No QR codes found',
                    $html
                );

                $this->assertStringNotContainsString(
                    'tablenav-pages',
                    $html
                );
            }
        );
    }

    public function test_history_page_renders_rows_and_escapes_qr_values(): void
    {
        $this->runWithCleanHistory(
            function (): void {
                $this->insertHistoryRow(
                    'QR-OLDER',
                    42,
                    'assigned',
                    '2026-07-01 09:00:00'
                );

                $this->insertHistoryRow(
                    'QR-<script>alert(1)</script>',
                    null,
                    'released',
                    '2026-07-02 10:00:00'
                );

                $html = $this->renderHistoryPage();

                $this->assertStringContainsString(
                    'QR-OLDER',
                    $html
                );

                $this->assertStringContainsString(
                    'Assigned',
                    $html
                );

                $this->assertStringContainsString(
                    'QR-&lt;script&gt;alert(1)&lt;/script&gt;',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<script>alert(1)</script>',
                    $html
                );

                $this->assertStringContainsString(
                    'Released',
                    $html
                );

                $this->assertStringContainsString(
                    '2026-07-02 10:00:00',
                    $html
                );

                $this->assertStringContainsString(
                    '—',
                    $html
                );
            }
        );
    }

    public function test_history_page_applies_status_date_and_search_filters(): void
    {
        $this->runWithCleanHistory(
            function (): void {
                $this->insertHistoryRow(
                    'QR-ALPHA',
                    10,
                    'assigned',
                    '2026-07-01 08:00:00'
                );

                $this->insertHistoryRow(
                    'QR-BETA',
                    20,
                    'released',
                    '2026-07-05 09:00:00'
                );

                $this->insertHistoryRow(
                    'QR-BETA-OUTSIDE',
                    30,
                    'released',
                    '2026-07-20 10:00:00'
                );

                $this->insertHistoryRow(
                    'QR-GAMMA',
                    40,
                    'deleted',
                    '2026-07-05 11:00:00'
                );

                $html = $this->renderHistoryPage(
                    [
                        'status_filter' => 'released',
                        'start_date' => '2026-07-04',
                        'end_date' => '2026-07-06',
                        's' => 'QR-BETA',
                    ]
                );

                $this->assertStringContainsString(
                    'QR-BETA',
                    $html
                );

                $this->assertStringNotContainsString(
                    'QR-ALPHA',
                    $html
                );

                $this->assertStringNotContainsString(
                    'QR-BETA-OUTSIDE',
                    $html
                );

                $this->assertStringNotContainsString(
                    'QR-GAMMA',
                    $html
                );

                $this->assertStringContainsString(
                    'name="start_date" value="2026-07-04"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="end_date" value="2026-07-06"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="s" value="QR-BETA"',
                    $html
                );

                $selectedCount = preg_match_all(
                    '/value=(["\'])released\1[^>]*'
                    . 'selected=(["\'])selected\2/',
                    $html
                );

                $this->assertSame(1, $selectedCount);
            }
        );
    }

    public function test_history_page_applies_pagination_offset(): void
    {
        $this->runWithCleanHistory(
            function (): void {
                update_option(
                    self::PER_PAGE_OPTION,
                    1,
                    false
                );

                $this->insertHistoryRow(
                    'QR-OLDEST',
                    10,
                    'added',
                    '2026-07-01 08:00:00'
                );

                $this->insertHistoryRow(
                    'QR-MIDDLE',
                    20,
                    'assigned',
                    '2026-07-02 08:00:00'
                );

                $this->insertHistoryRow(
                    'QR-NEWEST',
                    30,
                    'released',
                    '2026-07-03 08:00:00'
                );

                $html = $this->renderHistoryPage(
                    [
                        'paged' => '2',
                    ]
                );

                $this->assertStringContainsString(
                    'QR-MIDDLE',
                    $html
                );

                $this->assertStringNotContainsString(
                    'QR-NEWEST',
                    $html
                );

                $this->assertStringNotContainsString(
                    'QR-OLDEST',
                    $html
                );

                $this->assertSame(
                    2,
                    substr_count($html, 'tablenav-pages')
                );

                $this->assertStringContainsString(
                    'page-numbers current',
                    $html
                );
            }
        );
    }
}
