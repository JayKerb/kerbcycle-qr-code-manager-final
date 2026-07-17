<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\DashboardPage;
use stdClass;

final class DashboardPageSmokeTest extends TestCase
{
    private const PER_PAGE_OPTION =
        'kerbcycle_qr_codes_per_page';

    private const EMAIL_OPTION =
        'kerbcycle_qr_enable_email';

    private const SMS_OPTION =
        'kerbcycle_qr_enable_sms';

    private const REMINDER_OPTION =
        'kerbcycle_qr_enable_reminders';

    private const SCANNER_OPTION =
        'kerbcycle_qr_enable_scanner';

    public function set_up(): void
    {
        parent::set_up();

        $this->resetDashboardState();
    }

    public function tear_down(): void
    {
        $this->resetDashboardState();

        parent::tear_down();
    }

    private function qrTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_qr_codes';
    }

    private function errorLogTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_error_logs';
    }

    private function resetDashboardState(): void
    {
        global $wpdb;

        $wpdb->query(
            'DELETE FROM ' . $this->qrTableName()
        );

        $wpdb->query(
            'DELETE FROM ' . $this->errorLogTableName()
        );

        delete_option(self::PER_PAGE_OPTION);
        delete_option(self::EMAIL_OPTION);
        delete_option(self::SMS_OPTION);
        delete_option(self::REMINDER_OPTION);
        delete_option(self::SCANNER_OPTION);

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        wp_set_current_user(0);
    }

    private function insertQrCode(
        array $overrides = []
    ): int {
        global $wpdb;

        $defaults = [
            'qr_code' => 'QR-DASHBOARD-DEFAULT',
            'user_id' => null,
            'display_name' => null,
            'status' => 'available',
            'assigned_at' => null,
        ];

        $inserted = $wpdb->insert(
            $this->qrTableName(),
            array_merge($defaults, $overrides)
        );

        $this->assertSame(1, $inserted);

        return (int) $wpdb->insert_id;
    }

    private function listingCodes(array $listing): array
    {
        return array_map(
            static function ($row): string {
                return (string) $row->qr_code;
            },
            $listing['codes']
        );
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

    private function renderDashboard(
        array $query = []
    ): string {
        wp_set_current_user(
            $this->create_admin_user()
        );

        $_GET = $query;

        return $this->captureOutput(
            static function (): void {
                (new DashboardPage())->render();
            }
        );
    }

    private function selectMarkup(
        string $html,
        string $id
    ): string {
        $matches = [];

        $matched = preg_match(
            '/<select\b[^>]*id="'
                . preg_quote($id, '/')
                . '"[^>]*>.*?<\/select>/s',
            $html,
            $matches
        );

        $this->assertSame(
            1,
            $matched,
            'Expected select element was not found.'
        );

        return $matches[0];
    }

    public function test_listing_data_returns_counts_and_default_order(): void
    {
        $this->insertQrCode(
            [
                'qr_code' => 'QR-AVAILABLE',
                'status' => 'available',
                'assigned_at' => null,
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-OLDER',
                'user_id' => 1001,
                'display_name' => 'Older Customer',
                'status' => 'assigned',
                'assigned_at' => '2026-07-01 09:00:00',
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-NEWEST',
                'user_id' => 1002,
                'display_name' => 'Newest Customer',
                'status' => 'assigned',
                'assigned_at' => '2026-07-03 09:00:00',
            ]
        );

        $listing = DashboardPage::get_listing_data();

        $this->assertSame(
            3,
            $listing['total_items']
        );

        $this->assertSame(
            1,
            $listing['available_count']
        );

        $this->assertSame(
            2,
            $listing['assigned_count']
        );

        $this->assertSame(
            20,
            $listing['per_page']
        );

        $this->assertSame(
            1,
            $listing['current_page']
        );

        $this->assertSame(
            1,
            $listing['total_pages']
        );

        $this->assertSame(
            [
                'QR-NEWEST',
                'QR-OLDER',
                'QR-AVAILABLE',
            ],
            $this->listingCodes($listing)
        );

        $this->assertSame(
            [
                'status_filter' => '',
                'start_date' => '',
                'end_date' => '',
                'search' => '',
            ],
            $listing['filters']
        );
    }

    public function test_listing_data_filters_status_and_applies_page_offset(): void
    {
        $this->insertQrCode(
            [
                'qr_code' => 'QR-ASSIGNED-OLDER',
                'status' => 'assigned',
                'assigned_at' => '2026-07-01 08:00:00',
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-ASSIGNED-NEWER',
                'status' => 'assigned',
                'assigned_at' => '2026-07-02 08:00:00',
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-AVAILABLE-IGNORED',
                'status' => 'available',
                'assigned_at' => null,
            ]
        );

        $listing = DashboardPage::get_listing_data(
            [
                'status_filter' => 'assigned',
                'per_page' => 1,
                'paged' => 2,
            ]
        );

        $this->assertSame(
            'assigned',
            $listing['filters']['status_filter']
        );

        $this->assertSame(
            2,
            $listing['total_items']
        );

        $this->assertSame(
            2,
            $listing['total_pages']
        );

        $this->assertSame(
            2,
            $listing['current_page']
        );

        $this->assertSame(
            ['QR-ASSIGNED-OLDER'],
            $this->listingCodes($listing)
        );
    }

    public function test_listing_data_supports_search_and_date_filters(): void
    {
        $this->insertQrCode(
            [
                'qr_code' => 'QR-ALPHA',
                'user_id' => 7001,
                'display_name' => 'Alice Customer',
                'status' => 'assigned',
                'assigned_at' => '2026-07-01 09:00:00',
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-BETA',
                'user_id' => 7002,
                'display_name' => 'Bob Customer',
                'status' => 'assigned',
                'assigned_at' => '2026-07-02 10:00:00',
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-GAMMA',
                'user_id' => 7003,
                'display_name' => 'Carol Customer',
                'status' => 'assigned',
                'assigned_at' => '2026-07-03 11:00:00',
            ]
        );

        $nameSearch = DashboardPage::get_listing_data(
            [
                'search' => 'Alice',
            ]
        );

        $this->assertSame(
            ['QR-ALPHA'],
            $this->listingCodes($nameSearch)
        );

        $userSearch = DashboardPage::get_listing_data(
            [
                's' => '7002',
            ]
        );

        $this->assertSame(
            ['QR-BETA'],
            $this->listingCodes($userSearch)
        );

        $codeSearch = DashboardPage::get_listing_data(
            [
                'search' => 'GAMMA',
            ]
        );

        $this->assertSame(
            ['QR-GAMMA'],
            $this->listingCodes($codeSearch)
        );

        $dateSearch = DashboardPage::get_listing_data(
            [
                'search' => '2026-07-02',
            ]
        );

        $this->assertSame(
            ['QR-BETA'],
            $this->listingCodes($dateSearch)
        );

        $dateRange = DashboardPage::get_listing_data(
            [
                'start_date' => '2026-07-02',
                'end_date' => '2026-07-03',
            ]
        );

        $this->assertSame(
            [
                'QR-GAMMA',
                'QR-BETA',
            ],
            $this->listingCodes($dateRange)
        );
    }

    public function test_listing_data_rejects_invalid_status_and_uses_defaults(): void
    {
        update_option(
            self::PER_PAGE_OPTION,
            2,
            false
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-PAGE-ONE',
                'assigned_at' => '2026-07-03 08:00:00',
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-PAGE-TWO',
                'assigned_at' => '2026-07-02 08:00:00',
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-PAGE-THREE',
                'assigned_at' => '2026-07-01 08:00:00',
            ]
        );

        $listing = DashboardPage::get_listing_data(
            [
                'status_filter' => 'deleted',
                'per_page' => 0,
                'page' => 2,
            ]
        );

        $this->assertSame(
            '',
            $listing['filters']['status_filter']
        );

        $this->assertSame(
            2,
            $listing['per_page']
        );

        $this->assertSame(
            2,
            $listing['current_page']
        );

        $this->assertSame(
            2,
            $listing['total_pages']
        );

        $this->assertSame(
            ['QR-PAGE-THREE'],
            $this->listingCodes($listing)
        );
    }

    public function test_render_qr_items_escapes_values_and_uses_fallbacks(): void
    {
        $malicious = new stdClass();
        $malicious->id = 77;
        $malicious->qr_code =
            '<script>qrAttack()</script>';
        $malicious->user_id =
            '<b>7001</b>';
        $malicious->display_name =
            '<b>Alice</b>';
        $malicious->status =
            '<i>ASSIGNED</i>';
        $malicious->assigned_at =
            '<script>dateAttack()</script>';

        $empty = new stdClass();
        $empty->id = 78;
        $empty->qr_code = 'QR-EMPTY';
        $empty->user_id = null;
        $empty->display_name = null;
        $empty->status = '';
        $empty->assigned_at = null;

        $html = DashboardPage::render_qr_items(
            [
                $malicious,
                $empty,
            ]
        );

        $this->assertStringContainsString(
            'data-id="77"',
            $html
        );

        $this->assertStringContainsString(
            'data-code="&lt;script&gt;'
                . 'qrAttack()&lt;/script&gt;"',
            $html
        );

        $this->assertStringContainsString(
            '&lt;b&gt;7001&lt;/b&gt;',
            $html
        );

        $this->assertStringContainsString(
            '&lt;b&gt;Alice&lt;/b&gt;',
            $html
        );

        $this->assertStringContainsString(
            '&lt;i&gt;ASSIGNED&lt;/i&gt;',
            $html
        );

        $this->assertStringContainsString(
            '&lt;script&gt;dateAttack()'
                . '&lt;/script&gt;',
            $html
        );

        $this->assertStringNotContainsString(
            '<script>qrAttack()</script>',
            $html
        );

        $this->assertStringNotContainsString(
            '<b>Alice</b>',
            $html
        );

        $this->assertStringNotContainsString(
            '<script>dateAttack()</script>',
            $html
        );

        $this->assertGreaterThanOrEqual(
            3,
            substr_count($html, '—')
        );
    }

    public function test_pagination_links_preserve_active_filters(): void
    {
        $links = DashboardPage::build_pagination_links(
            2,
            4,
            [
                'status_filter' => 'assigned',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
                'search' => 'FILTER-TARGET',
            ]
        );

        $this->assertStringContainsString(
            'status_filter=assigned',
            $links
        );

        $this->assertStringContainsString(
            'start_date=2026-07-01',
            $links
        );

        $this->assertStringContainsString(
            'end_date=2026-07-31',
            $links
        );

        $this->assertStringContainsString(
            's=FILTER-TARGET',
            $links
        );

        $this->assertStringContainsString(
            'page-numbers current',
            $links
        );

        $this->assertStringContainsString(
            '>2</span>',
            $links
        );

        $this->assertSame(
            '',
            DashboardPage::build_pagination_links(
                1,
                1
            )
        );
    }

    public function test_dashboard_renders_controls_options_and_available_codes(): void
    {
        update_option(
            self::EMAIL_OPTION,
            1,
            false
        );

        update_option(
            self::SMS_OPTION,
            0,
            false
        );

        update_option(
            self::REMINDER_OPTION,
            1,
            false
        );

        update_option(
            self::SCANNER_OPTION,
            1,
            false
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-DROPDOWN-AVAILABLE',
                'status' => 'available',
                'assigned_at' => null,
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'QR-LISTING-ASSIGNED',
                'user_id' => 9001,
                'display_name' => 'Assigned Customer',
                'status' => 'assigned',
                'assigned_at' => '2026-07-10 09:00:00',
            ]
        );

        $html = $this->renderDashboard();

        $this->assertStringContainsString(
            '<h1>KerbCycle QR Code Manager</h1>',
            $html
        );

        $this->assertStringContainsString(
            'Select a customer and scan or choose'
                . ' a QR code to assign.',
            $html
        );

        $this->assertStringContainsString(
            'id="dashboard-add-qr-btn"',
            $html
        );

        $this->assertStringContainsString(
            'id="dashboard-reset-scan-btn"',
            $html
        );

        $this->assertStringContainsString(
            'id="dashboard-customer-id"',
            $html
        );

        $this->assertStringContainsString(
            'id="dashboard-assign-qr-btn"',
            $html
        );

        $this->assertStringContainsString(
            'id="reader"',
            $html
        );

        $this->assertStringContainsString(
            'id="kerbcycle-scanner-exception-panel"',
            $html
        );

        $this->assertStringContainsString(
            'id="kerbcycle-ai-panel"',
            $html
        );

        $this->assertStringContainsString(
            'id="assign-qr-btn"',
            $html
        );

        $this->assertStringContainsString(
            'id="release-qr-btn"',
            $html
        );

        $this->assertStringContainsString(
            'id="import-qr-btn"',
            $html
        );

        $this->assertStringContainsString(
            'id="qr-code-list"',
            $html
        );

        $availableSelect = $this->selectMarkup(
            $html,
            'qr-code-select'
        );

        $this->assertStringContainsString(
            'QR-DROPDOWN-AVAILABLE',
            $availableSelect
        );

        $this->assertStringNotContainsString(
            'QR-LISTING-ASSIGNED',
            $availableSelect
        );

        $this->assertStringContainsString(
            'QR-DROPDOWN-AVAILABLE',
            $html
        );

        $this->assertStringContainsString(
            'QR-LISTING-ASSIGNED',
            $html
        );

        $this->assertStringContainsString(
            '<span class="qr-available-count">1</span>'
                . ' Available '
                . '<span class="qr-assigned-count">1</span>'
                . ' Assigned',
            $html
        );

        $emailChecked = preg_match(
            '/id="send-email"[^>]*'
                . 'checked=([\'"])checked\1/',
            $html
        );

        $smsDisabled = preg_match(
            '/id="send-sms"[^>]*'
                . 'disabled=([\'"])disabled\1/',
            $html
        );

        $smsChecked = preg_match(
            '/id="send-sms"[^>]*'
                . 'checked=([\'"])checked\1/',
            $html
        );

        $reminderChecked = preg_match(
            '/id="send-reminder"[^>]*'
                . 'checked=([\'"])checked\1/',
            $html
        );

        $this->assertSame(1, $emailChecked);
        $this->assertSame(1, $smsDisabled);
        $this->assertSame(0, $smsChecked);
        $this->assertSame(1, $reminderChecked);
    }

    public function test_dashboard_render_applies_query_filters(): void
    {
        update_option(
            self::PER_PAGE_OPTION,
            1,
            false
        );

        $this->insertQrCode(
            [
                'qr_code' => 'FILTER-TARGET-QR',
                'user_id' => 8101,
                'display_name' => 'Filter Target',
                'status' => 'assigned',
                'assigned_at' => '2026-07-05 09:00:00',
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'OUTSIDE-DATE-QR',
                'user_id' => 8102,
                'display_name' => 'Filter Target',
                'status' => 'assigned',
                'assigned_at' => '2026-07-20 09:00:00',
            ]
        );

        $this->insertQrCode(
            [
                'qr_code' => 'AVAILABLE-NONMATCH',
                'status' => 'available',
                'assigned_at' => null,
            ]
        );

        $html = $this->renderDashboard(
            [
                'status_filter' => 'assigned',
                'start_date' => '2026-07-04',
                'end_date' => '2026-07-06',
                's' => 'FILTER-TARGET',
                'paged' => '1',
            ]
        );

        $this->assertStringContainsString(
            'FILTER-TARGET-QR',
            $html
        );

        $this->assertStringNotContainsString(
            'OUTSIDE-DATE-QR',
            $html
        );

        $this->assertStringContainsString(
            'name="status_filter"',
            $html
        );

        $this->assertStringContainsString(
            'value="assigned"',
            $html
        );

        $this->assertStringContainsString(
            'name="start_date"'
                . ' value="2026-07-04"',
            $html
        );

        $this->assertStringContainsString(
            'name="end_date"'
                . ' value="2026-07-06"',
            $html
        );

        $this->assertStringContainsString(
            'name="s" value="FILTER-TARGET"',
            $html
        );

        $this->assertStringContainsString(
            'data-current-page="1"',
            $html
        );

        $this->assertStringContainsString(
            'data-status-filter="assigned"',
            $html
        );

        $this->assertStringContainsString(
            'data-start-date="2026-07-04"',
            $html
        );

        $this->assertStringContainsString(
            'data-end-date="2026-07-06"',
            $html
        );

        $this->assertStringContainsString(
            'data-search="FILTER-TARGET"',
            $html
        );
    }

    public function test_dashboard_renders_scanner_disabled_warning(): void
    {
        update_option(
            self::SCANNER_OPTION,
            0,
            false
        );

        $html = $this->renderDashboard();

        $this->assertStringContainsString(
            'notice notice-warning qr-warning',
            $html
        );

        $this->assertStringContainsString(
            'QR code scanner camera is disabled'
                . ' in settings.',
            $html
        );

        $this->assertStringNotContainsString(
            'id="dashboard-add-qr-btn"',
            $html
        );

        $this->assertStringNotContainsString(
            'id="dashboard-reset-scan-btn"',
            $html
        );

        $this->assertStringNotContainsString(
            'id="dashboard-assign-qr-btn"',
            $html
        );

        $this->assertStringNotContainsString(
            'id="reader"',
            $html
        );

        $this->assertStringContainsString(
            'id="scan-result"',
            $html
        );

        $this->assertStringContainsString(
            'Manual QR Code Tasks',
            $html
        );

        $this->assertStringContainsString(
            'Manage QR Codes',
            $html
        );
    }
}
