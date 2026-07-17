<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\MessagesHistoryPage;

final class MessagesHistoryPageSmokeTest extends TestCase
{
    private const SMS_PER_PAGE_OPTION =
        'kerbcycle_sms_history_per_page';

    private const EMAIL_PER_PAGE_OPTION =
        'kerbcycle_email_history_per_page';

    private function messageLogTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_message_logs';
    }

    private function resetMessageHistoryState(): void
    {
        global $wpdb;

        $wpdb->query(
            'TRUNCATE TABLE ' . $this->messageLogTableName()
        );

        delete_option(self::SMS_PER_PAGE_OPTION);
        delete_option(self::EMAIL_PER_PAGE_OPTION);

        $_GET = [];
        $_POST = [];

        wp_set_current_user(0);
    }

    private function runWithCleanMessageHistory(
        callable $callback
    ): void {
        $this->resetMessageHistoryState();

        try {
            $callback();
        } finally {
            $this->resetMessageHistoryState();
        }
    }

    private function insertMessageLog(
        array $overrides = []
    ): int {
        global $wpdb;

        $defaults = [
            'type' => 'sms',
            'recipient' => '+12015550123',
            'subject' => '',
            'body' => 'Pickup reminder message.',
            'status' => 'sent',
            'provider' => 'test-provider',
            'response' => '{"ok":true}',
            'created_at' => '2026-07-10 09:00:00',
        ];

        $inserted = $wpdb->insert(
            $this->messageLogTableName(),
            array_merge($defaults, $overrides)
        );

        $this->assertSame(1, $inserted);

        return (int) $wpdb->insert_id;
    }

    private function messagesHistoryWithoutPersistentHooks():
        MessagesHistoryPage
    {
        $page = new MessagesHistoryPage();

        remove_action(
            'admin_post_kerbcycle_clear_logs',
            [$page, 'handle_clear_logs']
        );

        remove_action(
            'admin_post_kerbcycle_delete_logs',
            [$page, 'handle_bulk_delete']
        );

        remove_action(
            'admin_post_kerbcycle_repair_logs',
            [$page, 'handle_repair_logs']
        );

        return $page;
    }

    private function renderMessagesHistoryPage(
        int $userId,
        array $query = []
    ): string {
        wp_set_current_user($userId);

        $_GET = $query;

        $bufferLevel = ob_get_level();

        ob_start();

        try {
            $page = $this->messagesHistoryWithoutPersistentHooks();
            $page->render();

            return (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    public function test_messages_history_page_registers_expected_hooks(): void
    {
        $page = new MessagesHistoryPage();

        $this->assertNotFalse(
            has_action(
                'admin_post_kerbcycle_clear_logs',
                [$page, 'handle_clear_logs']
            )
        );

        $this->assertNotFalse(
            has_action(
                'admin_post_kerbcycle_delete_logs',
                [$page, 'handle_bulk_delete']
            )
        );

        $this->assertNotFalse(
            has_action(
                'admin_post_kerbcycle_repair_logs',
                [$page, 'handle_repair_logs']
            )
        );

        remove_action(
            'admin_post_kerbcycle_clear_logs',
            [$page, 'handle_clear_logs']
        );

        remove_action(
            'admin_post_kerbcycle_delete_logs',
            [$page, 'handle_bulk_delete']
        );

        remove_action(
            'admin_post_kerbcycle_repair_logs',
            [$page, 'handle_repair_logs']
        );
    }

    public function test_messages_history_page_requires_manage_options(): void
    {
        $this->runWithCleanMessageHistory(
            function (): void {
                $subscriberId = $this->create_subscriber_user();

                $html = $this->renderMessagesHistoryPage(
                    $subscriberId
                );

                $this->assertSame('', trim($html));
            }
        );
    }

    public function test_messages_history_page_renders_empty_sms_state_and_actions(): void
    {
        $this->runWithCleanMessageHistory(
            function (): void {
                $adminId = $this->create_admin_user();

                $html = $this->renderMessagesHistoryPage(
                    $adminId
                );

                $this->assertStringContainsString(
                    '<h1>Messages History</h1>',
                    $html
                );

                $this->assertStringContainsString(
                    'class="nav-tab nav-tab-active"',
                    $html
                );

                $smsTabCount = preg_match(
                    '/>\s*SMS\s*<\/a>/',
                    $html
                );

                $emailTabCount = preg_match(
                    '/>\s*Email\s*<\/a>/',
                    $html
        );

        $this->assertSame(1, $smsTabCount);
        $this->assertSame(1, $emailTabCount);

                $this->assertStringContainsString(
                    'name="page" value="kerbcycle-messages-history"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="tab" value="sms"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="s"',
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
                    'No logs found.',
                    $html
                );

                $this->assertStringContainsString(
                    'name="action" value="kerbcycle_delete_logs"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="action" value="kerbcycle_clear_logs"',
                    $html
                );

                $this->assertStringContainsString(
                    'Delete Selected',
                    $html
                );

                $this->assertStringContainsString(
                    'Clear All',
                    $html
                );

                $this->assertStringNotContainsString(
                    'tablenav-pages',
                    $html
                );
            }
        );
    }

    public function test_messages_history_page_renders_logs_and_escapes_content(): void
    {
        $this->runWithCleanMessageHistory(
            function (): void {
                $logId = $this->insertMessageLog(
                    [
                        'recipient'
                            => '<script>recipientAttack()</script>',
                        'subject'
                            => '<strong>Pickup subject</strong>',
                        'body'
                            => '<script>bodyAttack()</script>'
                                . ' Customer pickup message.',
                        'status' => '<b>failed</b>',
                        'provider'
                            => '<b>provider-name</b>',
                        'response'
                            => '<script>responseAttack()</script>',
                    ]
                );

                $adminId = $this->create_admin_user();

                $html = $this->renderMessagesHistoryPage(
                    $adminId
                );

                $this->assertStringContainsString(
                    'value="' . $logId . '"',
                    $html
                );

                $this->assertStringContainsString(
                    '&lt;script&gt;recipientAttack()'
                    . '&lt;/script&gt;',
                    $html
                );

                $this->assertStringContainsString(
                    'Pickup subject',
                    $html
                );

               $this->assertStringContainsString(
                   'Customer pickup message.',
                   $html
               );

               $this->assertStringNotContainsString(
                   'bodyAttack()',
                   $html
        );

                $this->assertStringContainsString(
                    '&lt;b&gt;failed&lt;/b&gt;',
                    $html
        );

                $this->assertStringContainsString(
                    '&lt;b&gt;provider-name&lt;/b&gt;',
                    $html
                );

                $this->assertStringNotContainsString(
                    'responseAttack()',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<script>recipientAttack()</script>',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<script>bodyAttack()</script>',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<b>failed</b>',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<script>responseAttack()</script>',
                    $html
                );
            }
        );
    }

    public function test_message_preview_uses_json_response_fallbacks(): void
    {
        $this->runWithCleanMessageHistory(
            function (): void {
                $this->insertMessageLog(
                    [
                        'recipient' => '+12015550999',
                        'subject' => '',
                        'body' => '',
                        'response' => wp_json_encode(
                            [
                                'message'
                                    => 'Gateway supplied fallback message.',
                            ]
                        ),
                    ]
                );

                $this->insertMessageLog(
                    [
                        'recipient' => '+12015550888',
                        'subject' => '',
                        'body' => '',
                        'response' => '',
                        'created_at' => '2026-07-09 09:00:00',
                    ]
                );

                $adminId = $this->create_admin_user();

                $html = $this->renderMessagesHistoryPage(
                    $adminId
                );

                $this->assertStringContainsString(
                    'Gateway supplied fallback message.',
                    $html
                );

                $this->assertStringContainsString(
                    '—',
                    $html
                );
            }
        );
    }

    public function test_email_tab_applies_search_and_date_filters(): void
    {
        $this->runWithCleanMessageHistory(
            function (): void {
                $this->insertMessageLog(
                    [
                        'type' => 'email',
                        'recipient' => 'alpha@example.test',
                        'subject' => 'FILTER-TARGET pickup receipt',
                        'body' => 'Matching email body',
                        'provider' => 'mail-provider',
                        'created_at' => '2026-07-05 09:00:00',
                    ]
                );

                $this->insertMessageLog(
                    [
                        'type' => 'email',
                        'recipient' => 'outside@example.test',
                        'subject' => 'FILTER-TARGET outside range',
                        'created_at' => '2026-07-20 09:00:00',
                    ]
                );

                $this->insertMessageLog(
                    [
                        'type' => 'email',
                        'recipient' => 'other@example.test',
                        'subject' => 'Unrelated email',
                        'created_at' => '2026-07-05 10:00:00',
                    ]
                );

                $this->insertMessageLog(
                    [
                        'type' => 'sms',
                        'recipient' => '+12015550777',
                        'body' => 'FILTER-TARGET SMS message',
                        'created_at' => '2026-07-05 11:00:00',
                    ]
                );

                $adminId = $this->create_admin_user();

                $html = $this->renderMessagesHistoryPage(
                    $adminId,
                    [
                        'tab' => 'email',
                        's' => 'FILTER-TARGET',
                        'from' => '2026-07-04',
                        'to' => '2026-07-06',
                    ]
                );

                $this->assertStringContainsString(
                    'alpha@example.test',
                    $html
                );

                $this->assertStringContainsString(
                    'FILTER-TARGET pickup receipt',
                    $html
                );

                $this->assertStringNotContainsString(
                    'outside@example.test',
                    $html
                );

                $this->assertStringNotContainsString(
                    'other@example.test',
                    $html
                );

                $this->assertStringNotContainsString(
                    '+12015550777',
                    $html
                );

                $this->assertStringContainsString(
                    'name="tab" value="email"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="s" value="FILTER-TARGET"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="from" value="2026-07-04"',
                    $html
                );

                $this->assertStringContainsString(
                    'name="to" value="2026-07-06"',
                    $html
                );

                $this->assertSame(
                    1,
                    substr_count(
                        $html,
                        'nav-tab nav-tab-active'
                    )
                );
            }
        );
    }

    public function test_messages_history_page_applies_pagination_offset(): void
    {
        $this->runWithCleanMessageHistory(
            function (): void {
                update_option(
                    self::SMS_PER_PAGE_OPTION,
                    1,
                    false
                );

                $this->insertMessageLog(
                    [
                        'recipient' => '+12015550001',
                        'body' => 'PAGINATION-OLDEST',
                        'created_at' => '2026-07-01 08:00:00',
                    ]
                );

                $this->insertMessageLog(
                    [
                        'recipient' => '+12015550002',
                        'body' => 'PAGINATION-MIDDLE',
                        'created_at' => '2026-07-02 08:00:00',
                    ]
                );

                $this->insertMessageLog(
                    [
                        'recipient' => '+12015550003',
                        'body' => 'PAGINATION-NEWEST',
                        'created_at' => '2026-07-03 08:00:00',
                    ]
                );

                $adminId = $this->create_admin_user();

                $html = $this->renderMessagesHistoryPage(
                    $adminId,
                    [
                        'tab' => 'sms',
                        'paged' => '2',
                    ]
                );

                $this->assertStringContainsString(
                    'PAGINATION-MIDDLE',
                    $html
                );

                $this->assertStringNotContainsString(
                    'PAGINATION-NEWEST',
                    $html
                );

                $this->assertStringNotContainsString(
                    'PAGINATION-OLDEST',
                    $html
                );

                $this->assertSame(
                    2,
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
