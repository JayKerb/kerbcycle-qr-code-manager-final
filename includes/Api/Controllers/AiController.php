<?php

namespace Kerbcycle\QrCode\Api\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

/**
 * Option B AI endpoint controller.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Api\Controllers
 */
class AiController
{
    /**
     * Validate endpoint permissions for admin-only access.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return true|\WP_Error
     */
    public function permissions(WP_REST_Request $request)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('rest_forbidden', __('Unauthorized', 'kerbcycle'), ['status' => 403]);
        }

        $nonce = sanitize_text_field($request->get_header('X-WP-Nonce'));
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('rest_nonce_invalid', __('Security check failed', 'kerbcycle'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Dispatch Option B AI actions.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return WP_REST_Response|\WP_Error
     */
    public function dispatch_action(WP_REST_Request $request)
    {
        $action = sanitize_key($request->get_param('action'));

        if (empty($action)) {
            return new \WP_Error('kerbcycle_ai_action_missing', __('Missing action parameter.', 'kerbcycle'), ['status' => 400]);
        }

        switch ($action) {
            case 'pickup_summary':
                return new WP_REST_Response([
                    'success' => true,
                    'action'  => $action,
                    'data'    => [
                        'summary' => __('Mock pickup summary response.', 'kerbcycle'),
                    ],
                ], 200);
            case 'qr_exceptions':
                return new WP_REST_Response([
                    'success' => true,
                    'action'  => $action,
                    'data'    => $this->get_qr_exceptions_data($request),
                ], 200);
            case 'draft_template':
                return new WP_REST_Response([
                    'success'  => true,
                    'action'   => $action,
                    'data'     => [
                        'template' => __('Mock draft template response.', 'kerbcycle'),
                    ],
                ], 200);
            default:
                return new \WP_Error('kerbcycle_ai_action_invalid', __('Invalid action parameter.', 'kerbcycle'), ['status' => 400]);
        }
    }

    /**
     * Build structured QR exception data from available KerbCycle tables.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return array<string,mixed>
     */
    private function get_qr_exceptions_data(WP_REST_Request $request)
    {
        global $wpdb;

        $from_date = sanitize_text_field($request->get_param('from_date'));
        $to_date   = sanitize_text_field($request->get_param('to_date'));

        if (empty($from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
            $from_date = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if (empty($to_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            $to_date = gmdate('Y-m-d');
        }

        $qr_table      = $wpdb->prefix . 'kerbcycle_qr_codes';
        $history_table = $wpdb->prefix . 'kerbcycle_qr_code_history';
        $repo_table    = $wpdb->prefix . 'kerbcycle_qr_repo';

        $notes = [];
        $groups = [];
        $sample_records = [];

        $qr_table_exists = $this->table_exists($qr_table);
        $history_exists  = $this->table_exists($history_table);
        $repo_exists     = $this->table_exists($repo_table);

        if (!$qr_table_exists) {
            $notes[] = __('QR codes table is missing; core QR exception checks were skipped.', 'kerbcycle');
        }

        if ($qr_table_exists) {
            $duplicates = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM (SELECT qr_code FROM {$qr_table} WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY qr_code HAVING COUNT(*) > 1) d",
                    $from_date,
                    $to_date
                )
            );

            $duplicate_samples = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT qr_code, COUNT(*) AS duplicate_count
                    FROM {$qr_table}
                    WHERE DATE(created_at) BETWEEN %s AND %s
                    GROUP BY qr_code
                    HAVING COUNT(*) > 1
                    ORDER BY duplicate_count DESC, qr_code ASC
                    LIMIT 5",
                    $from_date,
                    $to_date
                ),
                ARRAY_A
            );

            $groups[] = [
                'type'  => 'duplicate_qr_codes',
                'count' => $duplicates,
            ];

            if (!empty($duplicate_samples)) {
                $sample_records['duplicate_qr_codes'] = $duplicate_samples;
            }

            $assigned_without_user = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$qr_table}
                    WHERE status = 'assigned'
                    AND (user_id IS NULL OR user_id = 0)
                    AND DATE(COALESCE(assigned_at, created_at)) BETWEEN %s AND %s",
                    $from_date,
                    $to_date
                )
            );

            $assigned_without_user_samples = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, qr_code, status, user_id, assigned_at
                    FROM {$qr_table}
                    WHERE status = 'assigned'
                    AND (user_id IS NULL OR user_id = 0)
                    AND DATE(COALESCE(assigned_at, created_at)) BETWEEN %s AND %s
                    ORDER BY id DESC
                    LIMIT 5",
                    $from_date,
                    $to_date
                ),
                ARRAY_A
            );

            $groups[] = [
                'type'  => 'assigned_without_user',
                'count' => $assigned_without_user,
            ];

            if (!empty($assigned_without_user_samples)) {
                $sample_records['assigned_without_user'] = $assigned_without_user_samples;
            }

            $available_with_assignment_data = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$qr_table}
                    WHERE status = 'available'
                    AND (user_id IS NOT NULL OR display_name IS NOT NULL OR assigned_at IS NOT NULL)
                    AND DATE(created_at) BETWEEN %s AND %s",
                    $from_date,
                    $to_date
                )
            );

            $available_with_assignment_samples = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, qr_code, status, user_id, assigned_at
                    FROM {$qr_table}
                    WHERE status = 'available'
                    AND (user_id IS NOT NULL OR display_name IS NOT NULL OR assigned_at IS NOT NULL)
                    AND DATE(created_at) BETWEEN %s AND %s
                    ORDER BY id DESC
                    LIMIT 5",
                    $from_date,
                    $to_date
                ),
                ARRAY_A
            );

            $groups[] = [
                'type'  => 'available_with_assignment_data',
                'count' => $available_with_assignment_data,
            ];

            if (!empty($available_with_assignment_samples)) {
                $sample_records['available_with_assignment_data'] = $available_with_assignment_samples;
            }
        }

        if ($history_exists && $qr_table_exists) {
            $orphan_history = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$history_table} h
                    LEFT JOIN {$qr_table} q ON q.qr_code = h.qr_code
                    WHERE q.id IS NULL
                    AND DATE(h.changed_at) BETWEEN %s AND %s",
                    $from_date,
                    $to_date
                )
            );

            $orphan_history_samples = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT h.qr_code, h.status, h.user_id, h.changed_at
                    FROM {$history_table} h
                    LEFT JOIN {$qr_table} q ON q.qr_code = h.qr_code
                    WHERE q.id IS NULL
                    AND DATE(h.changed_at) BETWEEN %s AND %s
                    ORDER BY h.changed_at DESC
                    LIMIT 5",
                    $from_date,
                    $to_date
                ),
                ARRAY_A
            );

            $groups[] = [
                'type'  => 'history_without_current_code',
                'count' => $orphan_history,
            ];

            if (!empty($orphan_history_samples)) {
                $sample_records['history_without_current_code'] = $orphan_history_samples;
            }
        } else {
            $notes[] = __('QR history table missing or unavailable; history consistency checks were skipped.', 'kerbcycle');
        }

        if ($repo_exists && $qr_table_exists) {
            $repo_status_mismatches = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$repo_table} r
                    INNER JOIN {$qr_table} q ON q.qr_code = r.code
                    WHERE r.status <> q.status
                    AND DATE(q.created_at) BETWEEN %s AND %s",
                    $from_date,
                    $to_date
                )
            );

            $repo_status_mismatch_samples = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT q.qr_code, q.status AS qr_status, r.status AS repo_status
                    FROM {$repo_table} r
                    INNER JOIN {$qr_table} q ON q.qr_code = r.code
                    WHERE r.status <> q.status
                    AND DATE(q.created_at) BETWEEN %s AND %s
                    ORDER BY q.id DESC
                    LIMIT 5",
                    $from_date,
                    $to_date
                ),
                ARRAY_A
            );

            $groups[] = [
                'type'  => 'repo_status_mismatch',
                'count' => $repo_status_mismatches,
            ];

            if (!empty($repo_status_mismatch_samples)) {
                $sample_records['repo_status_mismatch'] = $repo_status_mismatch_samples;
            }
        } else {
            $notes[] = __('QR repository table missing or unavailable; repository consistency checks were skipped.', 'kerbcycle');
        }

        $notes[] = __('No QR scan log table detected in current plugin schema; invalid/failed scan exceptions were not computed.', 'kerbcycle');

        usort($groups, static function ($a, $b) {
            return (int) $b['count'] <=> (int) $a['count'];
        });

        $total_exceptions = 0;
        foreach ($groups as $group) {
            $total_exceptions += (int) $group['count'];
        }

        return [
            'window' => [
                'from' => $from_date,
                'to'   => $to_date,
            ],
            'counts' => [
                'exception_groups' => count($groups),
                'total_exceptions' => $total_exceptions,
            ],
            'top_exception_groups' => array_slice($groups, 0, 5),
            'sample_records'       => $sample_records,
            'notes'                => array_values(array_unique($notes)),
        ];
    }

    /**
     * Check whether a database table exists.
     *
     * @param string $table_name Table name.
     *
     * @return bool
     */
    private function table_exists($table_name)
    {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $found === $table_name;
    }
}
