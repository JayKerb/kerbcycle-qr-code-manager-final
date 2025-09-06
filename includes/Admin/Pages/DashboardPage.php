<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The dashboard page.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Pages
 */
class DashboardPage
{
    /**
     * Render the dashboard page.
     *
     * @since    1.0.0
     */
    public function render()
    {
        global $wpdb;
        $table        = $wpdb->prefix . 'kerbcycle_qr_codes';
        $per_page     = (int) get_option('kerbcycle_qr_codes_per_page', 20);
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset       = ($current_page - 1) * $per_page;

        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field(wp_unslash($_GET['status_filter'])) : '';
        $start_date    = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : '';
        $end_date      = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : '';
        $search        = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $where  = '1=1';
        $params = [];

        if ($status_filter) {
            $where   .= ' AND status = %s';
            $params[] = $status_filter;
        }

        if ($start_date) {
            $where   .= ' AND DATE(assigned_at) >= %s';
            $params[] = $start_date;
        }

        if ($end_date) {
            $where   .= ' AND DATE(assigned_at) <= %s';
            $params[] = $end_date;
        }

        if ($search) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where   .= ' AND (CAST(id AS CHAR) LIKE %s OR qr_code LIKE %s OR CAST(user_id AS CHAR) LIKE %s OR display_name LIKE %s OR CAST(assigned_at AS CHAR) LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $count_sql   = "SELECT COUNT(*) FROM $table WHERE $where";
        $total_items = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));
        $total_pages = (int) ceil($total_items / $per_page);

        $pagination_links = $total_pages > 1 ? paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total'     => $total_pages,
            'current'   => $current_page,
        ]) : '';

        $available_codes = $wpdb->get_results("SELECT qr_code FROM $table WHERE status = 'available' ORDER BY id DESC");

        $select_sql = "SELECT id, qr_code, user_id, display_name, status, assigned_at FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d";
        $query_args = array_merge($params, [$per_page, $offset]);
        $all_codes  = $wpdb->get_results($wpdb->prepare($select_sql, $query_args));
        ?>
        <div class="wrap">
            <h1>KerbCycle QR Code Manager</h1>
            <div class="notice notice-info">
                <p><?php esc_html_e('Select a customer and scan or choose a QR code to assign.', 'kerbcycle'); ?></p>
            </div>
            <div id="qr-scanner-container">
                <h2><?php esc_html_e('Manual QR Code Tasks', 'kerbcycle'); ?></h2>
                <?php
                $email_enabled    = (bool) get_option('kerbcycle_qr_enable_email', 1);
                $sms_enabled      = (bool) get_option('kerbcycle_qr_enable_sms', 0);
                $reminder_enabled = (bool) get_option('kerbcycle_qr_enable_reminders', 0);
                $scanner_enabled  = (bool) get_option('kerbcycle_qr_enable_scanner', 1);
                ?>
                <div id="qr-task-options">
                    <label><input type="checkbox" id="send-email" <?php checked($email_enabled); ?> <?php disabled(!$email_enabled); ?>> <?php esc_html_e('Send notification email', 'kerbcycle'); ?></label>
                    <label><input type="checkbox" id="send-sms" <?php checked($sms_enabled); ?> <?php disabled(!$sms_enabled); ?>> <?php esc_html_e('Send SMS', 'kerbcycle'); ?></label>
                    <label><input type="checkbox" id="send-reminder" <?php checked($reminder_enabled); ?> <?php disabled(!$reminder_enabled); ?>> <?php esc_html_e('Schedule reminder', 'kerbcycle'); ?></label>
                </div>
                <div id="qr-selects">
                    <?php
                    wp_dropdown_users(array(
                        'name'             => 'customer_id',
                        'id'               => 'customer-id',
                        'show_option_none' => __('Select Customer', 'kerbcycle')
                    ));
                    ?>
                    <div class="qr-select-group">
                        <select id="qr-code-select">
                            <option value=""><?php esc_html_e('Select QR Code', 'kerbcycle'); ?></option>
                            <?php foreach ($available_codes as $code) : ?>
                                <option value="<?= esc_attr($code->qr_code); ?>"><?= esc_html($code->qr_code); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button id="assign-qr-btn" class="button button-primary"><?php esc_html_e('Assign QR Code', 'kerbcycle'); ?></button>
                    </div>
                    <div class="qr-select-group">
                        <select id="assigned-qr-code-select">
                            <option value=""><?php esc_html_e('Select Assigned QR Code', 'kerbcycle'); ?></option>
                        </select>
                        <button id="release-qr-btn" class="button"><?php esc_html_e('Release QR Code', 'kerbcycle'); ?></button>
                    </div>
                </div>
                <div class="qr-select-group">
                    <input type="text" id="new-qr-code" placeholder="<?php esc_attr_e('Enter QR Code', 'kerbcycle'); ?>" />
                    <button id="add-qr-btn" class="button"><?php esc_html_e('Add QR Code', 'kerbcycle'); ?></button>
                </div>
                <div class="qr-select-group">
                    <input type="file" id="import-qr-file" accept=".csv" />
                    <button id="import-qr-btn" class="button"><?php esc_html_e('Import QR Codes', 'kerbcycle'); ?></button>
                </div>
                <p class="description"><?php esc_html_e('Import QR Codes from a selected CSV File.', 'kerbcycle'); ?></p>
                <?php if ($scanner_enabled) : ?>
                    <div id="reader" class="qr-reader"></div>
                <?php else : ?>
                    <div class="notice notice-warning qr-warning">
                        <p><?php esc_html_e('QR code scanner camera is disabled in settings.', 'kerbcycle'); ?></p>
                    </div>
                <?php endif; ?>
                <div id="scan-result" class="updated"></div>
            </div>

            <h2><?php esc_html_e('Manage QR Codes', 'kerbcycle'); ?></h2>
            <form method="get" class="qr-filters">
                <input type="hidden" name="page" value="kerbcycle-qr-manager" />
                <select name="status_filter">
                    <option value=""><?php esc_html_e('All Statuses', 'kerbcycle'); ?></option>
                    <option value="assigned" <?php selected($status_filter, 'assigned'); ?>><?php esc_html_e('Assigned', 'kerbcycle'); ?></option>
                    <option value="available" <?php selected($status_filter, 'available'); ?>><?php esc_html_e('Available', 'kerbcycle'); ?></option>
                </select>
                <input type="date" name="start_date" value="<?= esc_attr($start_date); ?>" />
                <input type="date" name="end_date" value="<?= esc_attr($end_date); ?>" />
                <input type="search" name="s" value="<?= esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search', 'kerbcycle'); ?>" />
                <button class="button"><?php esc_html_e('Filter', 'kerbcycle'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=kerbcycle-qr-manager')); ?>" class="button"><?php esc_html_e('Reset', 'kerbcycle'); ?></a>
            </form>
            <p class="description"><?php esc_html_e('Drag and drop to reorder, select multiple codes for bulk actions, or click a code to edit.', 'kerbcycle'); ?></p>
            <form id="qr-code-bulk-form">
                <select id="bulk-action-top">
                    <option value=""><?php esc_html_e('Bulk actions', 'kerbcycle'); ?></option>
                    <option value="release"><?php esc_html_e('Release', 'kerbcycle'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'kerbcycle'); ?></option>
                </select>
                <button id="apply-bulk-top" class="button" data-target="bulk-action-top"><?php esc_html_e('Apply', 'kerbcycle'); ?></button>
                <?php if ($pagination_links) : ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?= $pagination_links; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <ul id="qr-code-list">
                    <li class="qr-header">
                        <input type="checkbox" class="qr-select" id="qr-select-all" title="<?php esc_attr_e('Select all', 'kerbcycle'); ?>" />
                        <span class="qr-id"><?php esc_html_e('ID', 'kerbcycle'); ?></span>
                        <span class="qr-text"><?php esc_html_e('QR Code', 'kerbcycle'); ?></span>
                        <span class="qr-user"><?php esc_html_e('User ID', 'kerbcycle'); ?></span>
                        <span class="qr-name"><?php esc_html_e('Customer', 'kerbcycle'); ?></span>
                        <span class="qr-status"><?php esc_html_e('Status', 'kerbcycle'); ?></span>
                        <span class="qr-assigned"><?php esc_html_e('Assigned At', 'kerbcycle'); ?></span>
                    </li>
                    <?php foreach ($all_codes as $code) : ?>
                        <li class="qr-item" data-code="<?= esc_attr($code->qr_code); ?>" data-id="<?= esc_attr($code->id); ?>">
                            <input type="checkbox" class="qr-select" />
                            <span class="qr-id"><?= esc_html($code->id); ?></span>
                            <span class="qr-text" contenteditable="true"><?= esc_html($code->qr_code); ?></span>
                            <span class="qr-user"><?= $code->user_id ? esc_html($code->user_id) : '—'; ?></span>
                            <span class="qr-name"><?= $code->display_name ? esc_html($code->display_name) : '—'; ?></span>
                            <span class="qr-status"><?= esc_html(ucfirst($code->status)); ?></span>
                            <span class="qr-assigned"><?= $code->assigned_at ? esc_html($code->assigned_at) : '—'; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($pagination_links) : ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?= $pagination_links; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <select id="bulk-action">
                    <option value=""><?php esc_html_e('Bulk actions', 'kerbcycle'); ?></option>
                    <option value="release"><?php esc_html_e('Release', 'kerbcycle'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'kerbcycle'); ?></option>
                </select>
                <button id="apply-bulk" class="button" data-target="bulk-action"><?php esc_html_e('Apply', 'kerbcycle'); ?></button>
            </form>
        </div>
        <?php
    }
}
