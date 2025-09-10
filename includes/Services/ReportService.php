<?php

namespace Kerbcycle\QrCode\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The report service.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Services
 */
class ReportService
{
    public function get_report_data()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'kerbcycle_qr_codes';

        // Weekly assignment counts
        $results = $wpdb->get_results("SELECT DATE(assigned_at) AS date, COUNT(*) AS count FROM $table WHERE assigned_at IS NOT NULL GROUP BY DATE(assigned_at) ORDER BY date DESC LIMIT 7");
        $labels  = [];
        $counts  = [];
        if ($results) {
            foreach (array_reverse($results) as $row) {
                $labels[] = $row->date;
                $counts[] = (int) $row->count;
            }
        }

        // Today's assignment counts by hour
        $hour_results = $wpdb->get_results("SELECT HOUR(assigned_at) AS hour, COUNT(*) AS count FROM $table WHERE assigned_at >= CURDATE() GROUP BY HOUR(assigned_at) ORDER BY hour");
        $daily_labels = [];
        $daily_counts = [];
        if ($hour_results) {
            foreach ($hour_results as $row) {
                $daily_labels[] = sprintf('%02d:00', $row->hour);
                $daily_counts[] = (int) $row->count;
            }
        }

        return [
            'labels'       => $labels,
            'counts'       => $counts,
            'daily_labels' => $daily_labels,
            'daily_counts' => $daily_counts,
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('kerbcycle_qr_report_nonce'),
        ];
    }
}
