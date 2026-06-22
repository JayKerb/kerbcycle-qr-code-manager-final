<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Data\Repositories\MessageLogRepository;
use Kerbcycle\QrCode\Services\MessagesService;

final class MessagesSmokeTest extends TestCase
{
    private function message_log_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_message_logs';
    }

    private function insert_message_log(array $overrides = []): int
    {
        global $wpdb;

        $defaults = [
            'type' => 'sms',
            'recipient' => '+15551234567',
            'subject' => '',
            'body' => 'KerbCycle test message',
            'status' => 'sent',
            'provider' => 'test-provider',
            'response' => 'ok',
            'created_at' => current_time('mysql', true),
        ];

        $row = array_merge($defaults, $overrides);

        $inserted = $wpdb->insert(
            $this->message_log_table_name(),
            $row,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $this->assertNotFalse($inserted);

        return (int) $wpdb->insert_id;
    }

    public function test_messages_defaults_include_expected_template_types(): void
    {
        $defaults = MessagesService::defaults();

        $this->assertArrayHasKey('assigned', $defaults);
        $this->assertArrayHasKey('released', $defaults);
        $this->assertArrayHasKey('funds_to', $defaults);
        $this->assertArrayHasKey('funds_from', $defaults);

        $this->assertArrayHasKey('sms', $defaults['assigned']);
        $this->assertArrayHasKey('email', $defaults['assigned']);
        $this->assertStringContainsString('{code}', $defaults['assigned']['sms']);
        $this->assertStringContainsString('{user}', $defaults['assigned']['email']);
    }

    public function test_messages_get_all_merges_saved_partial_templates_with_defaults(): void
    {
        update_option(
            MessagesService::OPT,
            [
                'assigned' => [
                    'sms' => 'Custom assigned SMS for {code}',
                ],
            ],
            false
        );

        $messages = MessagesService::get_all();

        $this->assertSame('Custom assigned SMS for {code}', $messages['assigned']['sms']);
        $this->assertArrayHasKey('email', $messages['assigned']);
        $this->assertArrayHasKey('released', $messages);
        $this->assertArrayHasKey('funds_to', $messages);
        $this->assertArrayHasKey('funds_from', $messages);
    }

    public function test_messages_get_template_returns_saved_template_and_empty_unknown_template(): void
    {
        update_option(
            MessagesService::OPT,
            [
                'released' => [
                    'sms' => 'Released SMS {code}',
                    'email' => 'Released email for {user} and {code}',
                ],
            ],
            false
        );

        $released = MessagesService::get_template('released');

        $this->assertSame('Released SMS {code}', $released['sms']);
        $this->assertSame('Released email for {user} and {code}', $released['email']);

        $unknown = MessagesService::get_template('unknown_type');

        $this->assertSame('', $unknown['sms']);
        $this->assertSame('', $unknown['email']);
    }

    public function test_messages_render_replaces_known_placeholders_and_leaves_missing_ones(): void
    {
        update_option(
            MessagesService::OPT,
            [
                'funds_to' => [
                    'sms' => 'Added {amount} to {wallet} for {user}',
                    'email' => 'Hi {user}, {amount} was added to {wallet}. Code: {code}',
                ],
            ],
            false
        );

        $rendered = MessagesService::render(
            'funds_to',
            [
                'user' => 'Sam',
                'amount' => '$10',
                'wallet' => 'TeraWallet',
            ]
        );

        $this->assertSame('Added $10 to TeraWallet for Sam', $rendered['sms']);
        $this->assertSame('Hi Sam, $10 was added to TeraWallet. Code: {code}', $rendered['email']);
    }

    public function test_message_log_repository_log_message_sanitizes_and_defaults_type(): void
    {
        global $wpdb;

        MessageLogRepository::log_message([
            'type' => 'invalid-type',
            'to' => '  +15550001111  ',
            'subject' => 'Subject <script>alert(1)</script>',
            'body' => '<strong>Hello</strong><script>alert(1)</script>',
            'status' => 'sent',
            'provider' => 'webhook',
            'response' => ['ok' => true],
        ]);

        $row = $wpdb->get_row(
            'SELECT * FROM ' . $this->message_log_table_name() . ' ORDER BY id DESC LIMIT 1'
        );

        $this->assertNotNull($row);
        $this->assertSame('sms', $row->type);
        $this->assertSame('+15550001111', $row->recipient);
        $this->assertStringContainsString('Subject', $row->subject);
        $this->assertStringNotContainsString('<script>', $row->subject);
        $this->assertStringContainsString('<strong>Hello</strong>', $row->body);
        $this->assertStringNotContainsString('<script>', $row->body);
        $this->assertSame('sent', $row->status);
        $this->assertSame('webhook', $row->provider);
        $this->assertStringContainsString('ok', $row->response);
        $this->assertNotEmpty($row->created_at);
    }

    public function test_message_log_repository_get_logs_and_count_logs_filter_results(): void
    {
        $repository = new MessageLogRepository();

        $this->insert_message_log([
            'type' => 'sms',
            'recipient' => '+15550002222',
            'subject' => 'Pickup SMS',
            'body' => 'Pickup reminder body',
            'status' => 'sent',
            'provider' => 'textbelt',
            'created_at' => '2026-06-01 12:00:00',
        ]);

        $this->insert_message_log([
            'type' => 'email',
            'recipient' => 'customer@example.com',
            'subject' => 'Pickup Email',
            'body' => 'Email body',
            'status' => 'failed',
            'provider' => 'smtp',
            'created_at' => '2026-06-02 12:00:00',
        ]);

        $smsLogs = $repository->get_logs('sms', 'reminder', '2026-06-01', '2026-06-01', 1, 10);
        $smsCount = $repository->count_logs('sms', 'reminder', '2026-06-01', '2026-06-01');

        $this->assertCount(1, $smsLogs);
        $this->assertSame(1, $smsCount);
        $this->assertSame('+15550002222', $smsLogs[0]->recipient);
        $this->assertSame('Pickup reminder body', $smsLogs[0]->body);

        $emailCount = $repository->count_logs('email', 'smtp', '2026-06-02', '2026-06-02');

        $this->assertSame(1, $emailCount);
    }

    public function test_message_log_repository_table_is_valid(): void
    {
        $repository = new MessageLogRepository();

        $this->assertTrue($repository->table_is_valid());
    }

    public function test_message_log_repository_delete_by_ids_deletes_selected_logs_only(): void
    {
        $repository = new MessageLogRepository();

        $deleteId = $this->insert_message_log([
            'recipient' => '+15550003333',
            'body' => 'Delete this row',
        ]);

        $keepId = $this->insert_message_log([
            'recipient' => '+15550004444',
            'body' => 'Keep this row',
        ]);

        $deleted = $repository->delete_by_ids([$deleteId, 0, -123]);

        $this->assertSame(1, $deleted);

        global $wpdb;

        $deletedRow = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->message_log_table_name() . ' WHERE id = %d',
                $deleteId
            )
        );

        $keptRow = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->message_log_table_name() . ' WHERE id = %d',
                $keepId
            )
        );

        $this->assertNull($deletedRow);
        $this->assertNotNull($keptRow);
        $this->assertSame('+15550004444', $keptRow->recipient);

        $emptyDelete = $repository->delete_by_ids([]);

        $this->assertSame(0, $emptyDelete);
    }
}
