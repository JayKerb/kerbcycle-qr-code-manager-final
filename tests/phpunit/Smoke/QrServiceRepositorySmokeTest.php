<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Data\Repositories\QrCodeRepository;
use Kerbcycle\QrCode\Services\QrService;

final class QrServiceRepositorySmokeTest extends TestCase
{
    private function create_customer(string $displayName = 'QR Customer'): int
    {
        return self::factory()->user->create([
            'role' => 'subscriber',
            'display_name' => $displayName,
        ]);
    }

    public function test_service_add_creates_available_qr_and_rejects_duplicate(): void
    {
        $service = new QrService();
        $qrCode = 'SMOKE-SERVICE-ADD-001';

        $created = $service->add($qrCode);

        $this->assertNotInstanceOf(\WP_Error::class, $created);
        $this->assertIsObject($created);
        $this->assertSame($qrCode, $created->qr_code);
        $this->assertSame('available', $created->status);

        $duplicate = $service->add($qrCode);

        $this->assertInstanceOf(\WP_Error::class, $duplicate);
        $this->assertSame('duplicate_qr_code', $duplicate->get_error_code());
    }

    public function test_repository_lists_available_and_assigned_codes_by_user(): void
    {
        $repository = new QrCodeRepository();

        $availableQr = 'SMOKE-REPO-LIST-AVAILABLE-001';
        $assignedQr = 'SMOKE-REPO-LIST-ASSIGNED-001';
        $customerId = $this->create_customer('Repository List Customer');

        $this->assertNotFalse($repository->insert_available($availableQr));
        $this->assertNotFalse($repository->insert_assigned($assignedQr, $customerId, 'Repository List Customer'));

        $availableCodes = array_map(
            static function ($row): string {
                return (string) $row->qr_code;
            },
            $repository->list_available()
        );

        $assignedCodes = $repository->list_assigned_by_user($customerId);

        $this->assertContains($availableQr, $availableCodes);
        $this->assertNotContains($assignedQr, $availableCodes);
        $this->assertContains($assignedQr, $assignedCodes);
    }

    public function test_repository_bulk_delete_available_only_deletes_available_codes(): void
    {
        $repository = new QrCodeRepository();

        $availableToDelete = 'SMOKE-REPO-BULK-DELETE-AVAILABLE-001';
        $assignedMustRemain = 'SMOKE-REPO-BULK-DELETE-ASSIGNED-001';
        $customerId = $this->create_customer('Bulk Delete Customer');

        $this->assertNotFalse($repository->insert_available($availableToDelete));
        $this->assertNotFalse($repository->insert_assigned($assignedMustRemain, $customerId, 'Bulk Delete Customer'));

        $deleted = $repository->bulk_delete_available([
            $availableToDelete,
            $assignedMustRemain,
            'SMOKE-REPO-BULK-DELETE-UNKNOWN-001',
        ]);

        $this->assertSame(1, $deleted);
        $this->assertNull($repository->find_by_qr_code($availableToDelete));

        $assignedRow = $repository->find_by_qr_code($assignedMustRemain);
        $this->assertNotNull($assignedRow);
        $this->assertSame('assigned', $assignedRow->status);
        $this->assertSame($customerId, (int) $assignedRow->user_id);
    }

    public function test_service_bulk_release_releases_assigned_qr_and_leaves_unknown_codes_alone(): void
    {
        $repository = new QrCodeRepository();
        $service = new QrService();

        $assignedQr = 'SMOKE-SERVICE-BULK-RELEASE-001';
        $customerId = $this->create_customer('Bulk Release Customer');

        $this->assertNotFalse($repository->insert_assigned($assignedQr, $customerId, 'Bulk Release Customer'));

        $released = $service->bulk_release([
            $assignedQr,
            'SMOKE-SERVICE-BULK-RELEASE-UNKNOWN-001',
        ]);

        $this->assertSame(1, $released);

        $releasedRow = $repository->find_by_qr_code($assignedQr);
        $this->assertNotNull($releasedRow);
        $this->assertSame('available', $releasedRow->status);
        $this->assertTrue(empty($releasedRow->user_id) || (int) $releasedRow->user_id === 0);
    }

    public function test_service_update_rejects_duplicate_target_and_updates_unique_code(): void
    {
        $repository = new QrCodeRepository();
        $service = new QrService();

        $originalQr = 'SMOKE-SERVICE-UPDATE-ORIGINAL-001';
        $existingQr = 'SMOKE-SERVICE-UPDATE-EXISTING-001';
        $renamedQr = 'SMOKE-SERVICE-UPDATE-RENAMED-001';

        $this->assertNotFalse($repository->insert_available($originalQr));
        $this->assertNotFalse($repository->insert_available($existingQr));

        $duplicateUpdate = $service->update($originalQr, $existingQr);

        $this->assertInstanceOf(\WP_Error::class, $duplicateUpdate);
        $this->assertSame('duplicate_qr_code', $duplicateUpdate->get_error_code());

        $updated = $service->update($originalQr, $renamedQr);

        $this->assertSame(1, $updated);
        $this->assertNull($repository->find_by_qr_code($originalQr));

        $renamedRow = $repository->find_by_qr_code($renamedQr);
        $this->assertNotNull($renamedRow);
        $this->assertSame('available', $renamedRow->status);
    }

    public function test_service_get_assigned_by_user_returns_only_that_users_codes(): void
    {
        $repository = new QrCodeRepository();
        $service = new QrService();

        $firstCustomerId = $this->create_customer('First Assigned Customer');
        $secondCustomerId = $this->create_customer('Second Assigned Customer');

        $firstQr = 'SMOKE-SERVICE-ASSIGNED-BY-USER-001';
        $secondQr = 'SMOKE-SERVICE-ASSIGNED-BY-USER-002';

        $this->assertNotFalse($repository->insert_assigned($firstQr, $firstCustomerId, 'First Assigned Customer'));
        $this->assertNotFalse($repository->insert_assigned($secondQr, $secondCustomerId, 'Second Assigned Customer'));

        $firstCustomerCodes = $service->get_assigned_by_user($firstCustomerId);

        $this->assertContains($firstQr, $firstCustomerCodes);
        $this->assertNotContains($secondQr, $firstCustomerCodes);
    }
}
