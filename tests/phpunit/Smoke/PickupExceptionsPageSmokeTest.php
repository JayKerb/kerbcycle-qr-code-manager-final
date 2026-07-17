<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\PickupExceptionsPage;

final class PickupExceptionsPageSmokeTest extends TestCase
{
    private function pickupExceptionsTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_pickup_exceptions';
    }

    private function resetPickupExceptionState(): void
    {
        global $wpdb;

        $wpdb->query(
            'TRUNCATE TABLE ' . $this->pickupExceptionsTableName()
        );

        $_GET = [];

        wp_set_current_user(0);
    }

    private function runWithCleanPickupExceptions(
        callable $callback
    ): void {
        $this->resetPickupExceptionState();

        try {
            $callback();
        } finally {
            $this->resetPickupExceptionState();
        }
    }

    private function insertPickupException(
        array $overrides = []
    ): int {
        global $wpdb;

        $defaults = [
            'qr_code' => 'QR-PICKUP-001',
            'customer_id' => 42,
            'issue' => 'Bag could not be collected',
            'notes' => 'Customer left the bag near the side door.',
            'submitted_at' => '2026-07-10 09:00:00',
            'webhook_sent' => 0,
            'status' => 'pending',
            'webhook_status_code' => null,
            'webhook_response_body' => '',
            'source' => 'scanner',
            'retry_count' => 0,
            'last_retry_at' => null,
            'ai_severity' => 'medium',
            'ai_category' => 'collection',
            'ai_summary' => 'Collection could not be completed.',
            'ai_recommended_action' => 'Contact the customer.',
            'created_at' => '2026-07-10 09:00:00',
            'updated_at' => '2026-07-10 09:00:00',
        ];

        $data = array_merge($defaults, $overrides);

        $inserted = $wpdb->insert(
            $this->pickupExceptionsTableName(),
            $data
        );

        $this->assertSame(1, $inserted);

        return (int) $wpdb->insert_id;
    }

    private function renderPickupExceptionsPage(
        int $userId,
        array $query = []
    ): string {
        wp_set_current_user($userId);

        $_GET = $query;

        $bufferLevel = ob_get_level();

        ob_start();

        try {
            (new PickupExceptionsPage())->render();

            return (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    public function test_pickup_exceptions_page_requires_manage_options(): void
    {
        $this->runWithCleanPickupExceptions(
            function (): void {
                $subscriberId = $this->create_subscriber_user();

                $html = $this->renderPickupExceptionsPage(
                    $subscriberId
                );

                $this->assertSame('', trim($html));
            }
        );
    }

    public function test_pickup_exceptions_page_renders_empty_state_and_filters(): void
    {
        $this->runWithCleanPickupExceptions(
            function (): void {
                $adminId = $this->create_admin_user();

                $html = $this->renderPickupExceptionsPage(
                    $adminId
                );

                $this->assertStringContainsString(
                    '<h1>Pickup Exceptions</h1>',
                    $html
                );

                $this->assertStringContainsString(
                    'This page shows locally stored pickup exceptions',
                    $html
                );

                $this->assertStringContainsString(
                    '>All</a>',
                    $html
                );

                $this->assertStringContainsString(
                    '>Failed Only</a>',
                    $html
                );

                $this->assertStringContainsString(
                    'status_filter=failed',
                    $html
                );

                $this->assertStringContainsString(
                    'No pickup exceptions found.',
                    $html
                );

                $this->assertStringContainsString(
                    'id="kerbcycle-pickup-exceptions-table"',
                    $html
                );

                $this->assertStringContainsString(
                    'id="kerbcycle-pickup-exceptions-tbody"',
                    $html
                );
            }
        );
    }

    public function test_pickup_exceptions_page_renders_status_badges_and_retry_eligibility(): void
    {
        $this->runWithCleanPickupExceptions(
            function (): void {
                $pendingId = $this->insertPickupException(
                    [
                        'qr_code' => 'QR-PENDING',
                        'status' => 'pending',
                        'webhook_sent' => 0,
                    ]
                );

                $failedId = $this->insertPickupException(
                    [
                        'qr_code' => 'QR-FAILED',
                        'status' => 'failed',
                        'webhook_sent' => 0,
                        'retry_count' => 2,
                        'last_retry_at' => '2026-07-11 10:00:00',
                    ]
                );

                $sentId = $this->insertPickupException(
                    [
                        'qr_code' => 'QR-SENT',
                        'status' => 'sent',
                        'webhook_sent' => 1,
                    ]
                );

                $adminId = $this->create_admin_user();

                $html = $this->renderPickupExceptionsPage(
                    $adminId
                );

                $this->assertStringContainsString(
                    'QR-PENDING',
                    $html
                );

                $this->assertStringContainsString(
                    'QR-FAILED',
                    $html
                );

                $this->assertStringContainsString(
                    'QR-SENT',
                    $html
                );

                $this->assertStringContainsString(
                    'kerb-badge-pending">Pending',
                    $html
                );

                $this->assertStringContainsString(
                    'kerb-badge-error">Failed',
                    $html
                );

                $this->assertStringContainsString(
                    'kerb-badge-success">Sent',
                    $html
                );

                $this->assertStringContainsString(
                    'exception_id=' . $pendingId,
                    $html
                );

                $this->assertStringContainsString(
                    'exception_id=' . $failedId,
                    $html
                );

                $this->assertStringNotContainsString(
                    'exception_id=' . $sentId,
                    $html
                );

                $this->assertSame(
                    2,
                    substr_count(
                        $html,
                        'kerbcycle-retry-webhook'
                    )
                );

                $this->assertStringContainsString(
                    '2026-07-11 10:00:00',
                    $html
                );
            }
        );
    }

    public function test_failed_filter_only_renders_failed_records(): void
    {
        $this->runWithCleanPickupExceptions(
            function (): void {
                $this->insertPickupException(
                    [
                        'qr_code' => 'QR-FILTER-PENDING',
                        'status' => 'pending',
                        'webhook_sent' => 0,
                    ]
                );

                $this->insertPickupException(
                    [
                        'qr_code' => 'QR-FILTER-FAILED',
                        'status' => 'failed',
                        'webhook_sent' => 0,
                    ]
                );

                $this->insertPickupException(
                    [
                        'qr_code' => 'QR-FILTER-SENT',
                        'status' => 'sent',
                        'webhook_sent' => 1,
                    ]
                );

                $adminId = $this->create_admin_user();

                $html = $this->renderPickupExceptionsPage(
                    $adminId,
                    [
                        'status_filter' => 'failed',
                    ]
                );

                $this->assertStringContainsString(
                    'QR-FILTER-FAILED',
                    $html
                );

                $this->assertStringNotContainsString(
                    'QR-FILTER-PENDING',
                    $html
                );

                $this->assertStringNotContainsString(
                    'QR-FILTER-SENT',
                    $html
                );

                $this->assertStringContainsString(
                    'status_filter=failed',
                    $html
                );

                $this->assertStringContainsString(
                    'class="current">Failed Only',
                    $html
                );

                $this->assertStringContainsString(
                    'kerb-badge-error">Failed',
                    $html
                );
            }
        );
    }

    public function test_pickup_exception_details_escape_untrusted_content(): void
    {
        $this->runWithCleanPickupExceptions(
            function (): void {
                $this->insertPickupException(
                    [
                        'qr_code' => 'QR-<script>qrAttack()</script>',
                        'issue' => '<script>issueAttack()</script>',
                        'notes' => '<img src=x onerror=notesAttack()>',
                        'source' => '<strong>mobile</strong>',
                        'ai_summary' => '<script>summaryAttack()</script>',
                        'ai_recommended_action'
                            => '<em>Call customer</em>',
                        'webhook_response_body'
                            => '<script>webhookAttack()</script>',
                        'status' => 'failed',
                        'webhook_sent' => 0,
                        'webhook_status_code' => 503,
                    ]
                );

                $adminId = $this->create_admin_user();

                $html = $this->renderPickupExceptionsPage(
                    $adminId
                );

                $this->assertStringContainsString(
                    'QR-&lt;script&gt;qrAttack()&lt;/script&gt;',
                    $html
                );

                $this->assertStringContainsString(
                    '&lt;script&gt;issueAttack()&lt;/script&gt;',
                    $html
                );

                $this->assertStringContainsString(
                    '&lt;img src=x onerror=notesAttack()&gt;',
                    $html
                );

                $this->assertStringContainsString(
                    '&lt;strong&gt;mobile&lt;/strong&gt;',
                    $html
                );

                $this->assertStringContainsString(
                    '&lt;script&gt;summaryAttack()&lt;/script&gt;',
                    $html
                );

                $this->assertStringContainsString(
                    'Call customer',
                    $html
                );

                $this->assertStringContainsString(
                    '&lt;script&gt;webhookAttack()&lt;/script&gt;',
                    $html
                );

                $this->assertStringContainsString(
                    'Webhook Status Code',
                    $html
                );

                $this->assertStringContainsString(
                    '503',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<script>issueAttack()</script>',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<img src=x onerror=notesAttack()>',
                    $html
                );

                $this->assertStringNotContainsString(
                    '<script>webhookAttack()</script>',
                    $html
                );
            }
        );
    }

    public function test_pickup_exceptions_page_renders_success_and_error_notices_safely(): void
    {
        $this->runWithCleanPickupExceptions(
            function (): void {
                $adminId = $this->create_admin_user();

                $successHtml = $this->renderPickupExceptionsPage(
                    $adminId,
                    [
                        'retry_status' => 'success',
                        'retry_message'
                            => 'Pickup exception resent successfully.',
                    ]
                );

                $this->assertStringContainsString(
                    'notice notice-success',
                    $successHtml
                );

                $this->assertStringContainsString(
                    'Pickup exception resent successfully.',
                    $successHtml
                );

                $errorHtml = $this->renderPickupExceptionsPage(
                    $adminId,
                    [
                        'retry_status' => 'error',
                        'retry_message'
                            => '<script>noticeAttack()</script> Retry failed.',
                    ]
                );

                $this->assertStringContainsString(
                    'notice notice-error',
                    $errorHtml
                );

                $this->assertStringContainsString(
                    'Retry failed.',
                    $errorHtml
                );

                $this->assertStringNotContainsString(
                    '<script>noticeAttack()</script>',
                    $errorHtml
                );
            }
        );
    }
}
