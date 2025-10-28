<?php
global $wpdb;
$table_name = $wpdb->prefix . 'kerbcycle_qr_codes';

$qr_codes = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $table_name ORDER BY assigned_at DESC LIMIT %d", 100)
);
?>
<div class="wrap">
    <h1>QR Code History</h1>
    <p class="description">Recent QR code assignments</p>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>QR Code</th>
                <th>User ID</th>
                <th>Status</th>
                <th>Assigned At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($qr_codes)) : ?>
                <?php foreach ($qr_codes as $qr) : ?>
                    <tr>
                        <td><?= esc_html($qr->id) ?></td>
                        <td><?= esc_html($qr->qr_code) ?></td>
                        <td><?= $qr->user_id ? esc_html($qr->user_id) : '—' ?></td>
                        <td><?= esc_html(ucfirst($qr->status)) ?></td>
                        <td><?= $qr->assigned_at ? esc_html($qr->assigned_at) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="5" class="description">No QR codes found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
