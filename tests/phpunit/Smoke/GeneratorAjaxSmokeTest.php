<?php

declare(strict_types=1);

namespace KerbCycle\Tests\PhpUnit\Smoke;

require_once __DIR__ . '/../TestCase.php';

use KerbCycle\Tests\PhpUnit\AjaxDieException;
use KerbCycle\Tests\PhpUnit\TestCase;
use Kerbcycle\QrCode\Admin\Pages\GeneratorPage;

final class GeneratorAjaxSmokeTest extends TestCase
{
    public function set_up(): void
    {
        parent::set_up();

        $this->resetGeneratorState();
    }

    public function tear_down(): void
    {
        $this->resetGeneratorState();

        parent::tear_down();
    }

    private function repoTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'kerbcycle_qr_repo';
    }

    private function resetGeneratorState(): void
    {
        global $wpdb;

        $wpdb->query(
            'DELETE FROM ' . $this->repoTableName()
        );

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        wp_set_current_user(0);
    }

    private function insertRepoCode(
        string $code,
        int $userId
    ): int {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->repoTableName(),
            [
                'code' => $code,
                'status' => 'available',
                'created_by' => $userId,
            ],
            [
                '%s',
                '%s',
                '%d',
            ]
        );

        $this->assertSame(1, $inserted);

        return (int) $wpdb->insert_id;
    }

    private function repoCount(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            'SELECT COUNT(*) FROM '
                . $this->repoTableName()
        );
    }

    private function repoRow(
        string $code
    ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, code, status, created_by '
                    . 'FROM '
                    . $this->repoTableName()
                    . ' WHERE code = %s '
                    . 'ORDER BY id DESC LIMIT 1',
                $code
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    private function repoRows(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT id, code, status, created_by '
                . 'FROM '
                . $this->repoTableName()
                . ' ORDER BY id ASC',
            ARRAY_A
        );
    }

    private function callGeneratorAjax(
        int $userId,
        array $post
    ): array {
        wp_set_current_user($userId);

        $_POST = array_merge(
            [
                'nonce' => wp_create_nonce(
                    'kerbcycle_generate_qr'
                ),
            ],
            $post
        );

        $_REQUEST = $_POST;

        add_filter(
            'wp_die_ajax_handler',
            [$this, 'ajax_die_handler']
        );

        add_filter(
            'wp_die_handler',
            [$this, 'ajax_die_handler']
        );

        add_filter(
            'wp_doing_ajax',
            '__return_true'
        );

        $json = '';
        $bufferLevel = ob_get_level();

        ob_start();

        try {
            GeneratorPage::instance()
                ->ajax_generate_qr();
        } catch (AjaxDieException $exception) {
            /*
             * Expected when wp_send_json_success() or
             * wp_send_json_error() terminates the request.
             */
        } finally {
            while (ob_get_level() > $bufferLevel) {
                $json .= (string) ob_get_clean();
            }

            remove_filter(
                'wp_die_ajax_handler',
                [$this, 'ajax_die_handler']
            );

            remove_filter(
                'wp_die_handler',
                [$this, 'ajax_die_handler']
            );

            remove_filter(
                'wp_doing_ajax',
                '__return_true'
            );
        }

        $decoded = json_decode($json, true);

        $this->assertIsArray(
            $decoded,
            'Expected a JSON response from Generator AJAX.'
        );

        $this->assertArrayHasKey(
            'success',
            $decoded
        );

        $this->assertArrayHasKey(
            'data',
            $decoded
        );

        return $decoded;
    }

    public function test_generator_ajax_requires_manage_options(): void
    {
        $subscriberId = $this->create_subscriber_user();

        $response = $this->callGeneratorAjax(
            $subscriberId,
            [
                'genType' => 'single',
                'code' => 'KC-NO-PERMISSION',
            ]
        );

        $this->assertFalse(
            $response['success']
        );

        $this->assertSame(
            'No permission',
            $response['data']['message']
        );

        $this->assertSame(
            0,
            $this->repoCount()
        );
    }

    public function test_single_generation_rejects_empty_code(): void
    {
        $adminId = $this->create_admin_user();

        $response = $this->callGeneratorAjax(
            $adminId,
            [
                'genType' => 'single',
                'code' => '   ',
            ]
        );

        $this->assertFalse(
            $response['success']
        );

        $this->assertSame(
            'Code required.',
            $response['data']['message']
        );

        $this->assertSame(
            0,
            $this->repoCount()
        );
    }

    public function test_single_generation_saves_trimmed_unique_code(): void
    {
        $adminId = $this->create_admin_user();

        $response = $this->callGeneratorAjax(
            $adminId,
            [
                'genType' => 'single',
                'code' => '  KC-SINGLE-001  ',
            ]
        );

        $this->assertTrue(
            $response['success']
        );

        $this->assertSame(
            ['KC-SINGLE-001'],
            $response['data']['saved']
        );

        $this->assertSame(
            [],
            $response['data']['skipped']
        );

        $this->assertSame(
            1,
            $this->repoCount()
        );

        $row = $this->repoRow(
            'KC-SINGLE-001'
        );

        $this->assertIsArray($row);

        $this->assertSame(
            'KC-SINGLE-001',
            $row['code']
        );

        $this->assertSame(
            'available',
            $row['status']
        );

        $this->assertSame(
            $adminId,
            (int) $row['created_by']
        );
    }

    public function test_single_generation_skips_existing_duplicate(): void
    {
        $adminId = $this->create_admin_user();

        $this->insertRepoCode(
            'KC-DUPLICATE-001',
            $adminId
        );

        $response = $this->callGeneratorAjax(
            $adminId,
            [
                'genType' => 'single',
                'code' => 'KC-DUPLICATE-001',
            ]
        );

        $this->assertTrue(
            $response['success']
        );

        $this->assertSame(
            [],
            $response['data']['saved']
        );

        $this->assertSame(
            ['KC-DUPLICATE-001'],
            $response['data']['skipped']
        );

        $this->assertSame(
            1,
            $this->repoCount()
        );
    }

    public function test_batch_generation_rejects_invalid_prefix(): void
    {
        $adminId = $this->create_admin_user();

        $response = $this->callGeneratorAjax(
            $adminId,
            [
                'genType' => 'batch',
                'count' => '2',
                'prefix' => 'KC prefix!',
                'length' => '8',
            ]
        );

        $this->assertFalse(
            $response['success']
        );

        $this->assertSame(
            'Invalid prefix.',
            $response['data']['message']
        );

        $this->assertSame(
            0,
            $this->repoCount()
        );
    }

    public function test_batch_generation_saves_unique_uppercase_codes(): void
    {
        $adminId = $this->create_admin_user();

        $response = $this->callGeneratorAjax(
            $adminId,
            [
                'genType' => 'batch',
                'count' => '3',
                'prefix' => 'KC-',
                'length' => '6',
            ]
        );

        $this->assertTrue(
            $response['success']
        );

        $this->assertCount(
            3,
            $response['data']['saved']
        );

        $this->assertSame(
            [],
            $response['data']['skipped']
        );

        $this->assertCount(
            3,
            array_unique(
                $response['data']['saved']
            )
        );

        foreach (
            $response['data']['saved']
            as $code
        ) {
            $this->assertMatchesRegularExpression(
                '/^KC-[A-Z0-9]{6}$/',
                $code
            );
        }

        $rows = $this->repoRows();

        $this->assertCount(
            3,
            $rows
        );

        $storedCodes = array_column(
            $rows,
            'code'
        );

        sort($storedCodes);

        $responseCodes =
            $response['data']['saved'];

        sort($responseCodes);

        $this->assertSame(
            $responseCodes,
            $storedCodes
        );

        foreach ($rows as $row) {
            $this->assertSame(
                'available',
                $row['status']
            );

            $this->assertSame(
                $adminId,
                (int) $row['created_by']
            );
        }
    }

    public function test_batch_generation_clamps_count_and_length(): void
    {
        $adminId = $this->create_admin_user();

        $minimumResponse =
            $this->callGeneratorAjax(
                $adminId,
                [
                    'genType' => 'batch',
                    'count' => '0',
                    'prefix' => 'MIN-',
                    'length' => '1',
                ]
            );

        $this->assertTrue(
            $minimumResponse['success']
        );

        $this->assertCount(
            1,
            $minimumResponse['data']['saved']
        );

        $minimumCode =
            $minimumResponse['data']['saved'][0];

        $this->assertMatchesRegularExpression(
            '/^MIN-[A-Z0-9]{4}$/',
            $minimumCode
        );

        $maximumLengthResponse =
            $this->callGeneratorAjax(
                $adminId,
                [
                    'genType' => 'batch',
                    'count' => '1',
                    'prefix' => 'MAX-',
                    'length' => '99',
                ]
            );

        $this->assertTrue(
            $maximumLengthResponse['success']
        );

        $this->assertCount(
            1,
            $maximumLengthResponse['data']['saved']
        );

        $maximumCode =
            $maximumLengthResponse['data']['saved'][0];

        $this->assertMatchesRegularExpression(
            '/^MAX-[A-Z0-9]{16}$/',
            $maximumCode
        );

        $this->assertSame(
            2,
            $this->repoCount()
        );
    }
}
