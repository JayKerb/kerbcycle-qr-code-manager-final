<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

use Kerbcycle\QrCode\Admin\Notices;

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

        $request_args = [
            'status_filter' => isset($_GET['status_filter']) ? wp_unslash($_GET['status_filter']) : '',
            'start_date'    => isset($_GET['start_date']) ? wp_unslash($_GET['start_date']) : '',
            'end_date'      => isset($_GET['end_date']) ? wp_unslash($_GET['end_date']) : '',
            'search'        => isset($_GET['s']) ? wp_unslash($_GET['s']) : '',
            'paged'         => isset($_GET['paged']) ? wp_unslash($_GET['paged']) : 1,
        ];

        $listing_data = self::get_listing_data($request_args);

        $status_filter   = $listing_data['filters']['status_filter'];
        $start_date      = $listing_data['filters']['start_date'];
        $end_date        = $listing_data['filters']['end_date'];
        $search          = $listing_data['filters']['search'];
        $per_page        = $listing_data['per_page'];
        $current_page    = $listing_data['current_page'];
        $total_pages     = $listing_data['total_pages'];
        $total_items     = $listing_data['total_items'];
        $available_count = $listing_data['available_count'];
        $assigned_count  = $listing_data['assigned_count'];
        $all_codes       = $listing_data['codes'];

        $pagination_links = self::build_pagination_links($current_page, $total_pages, $listing_data['filters']);

        $table           = $wpdb->prefix . 'kerbcycle_qr_codes';
        $available_codes = $wpdb->get_results("SELECT qr_code FROM $table WHERE status = 'available' ORDER BY id DESC");
        ?>
        <div class="wrap">
            <h1>KerbCycle QR Code Manager</h1>
            <?php
            Notices::add(
                'info',
                esc_html__('Select a customer and scan or choose a QR code to assign.', 'kerbcycle'),
                [
                    'log_type' => 'dashboard_instruction',
                    'page'     => 'kerbcycle-qr-manager',
                    'status'   => 'success',
                ]
            );
            ?>
            <div id="qr-scanner-container">
                <?php
                $email_enabled    = (bool) get_option('kerbcycle_qr_enable_email', 1);
        $sms_enabled      = (bool) get_option('kerbcycle_qr_enable_sms', 0);
        $reminder_enabled = (bool) get_option('kerbcycle_qr_enable_reminders', 0);
        $scanner_enabled  = (bool) get_option('kerbcycle_qr_enable_scanner', 1);
        ?>
                <?php if ($scanner_enabled) : ?>
                    <div class="qr-scanner-actions">
                        <button id="dashboard-add-qr-btn" class="button button-primary"><?php esc_html_e('Add QR Code', 'kerbcycle'); ?></button>
                        <button id="dashboard-reset-scan-btn" class="button"><?php esc_html_e('Scan Reset', 'kerbcycle'); ?></button>
                    </div>
                    <div class="qr-scanner-customer">
                        <div class="qr-scanner-customer-select">
                            <?php
                            wp_dropdown_users(array(
                                'name'              => 'dashboard_customer_id',
                                'id'                => 'dashboard-customer-id',
                                'class'             => 'kc-searchable',
                                'show_option_none'  => __('Select Customer', 'kerbcycle'),
                                'option_none_value' => ''
                            ));
                    ?>
                            <p class="description"><?php esc_html_e('Customer Search', 'kerbcycle'); ?></p>
                        </div>
                        <button id="dashboard-assign-qr-btn" class="button button-primary"><?php esc_html_e('Assign QR Code', 'kerbcycle'); ?></button>
                    </div>
                    <div id="reader" class="qr-reader"></div>
                <?php else : ?>
                    <?php
                    Notices::add(
                        'warning',
                        esc_html__('QR code scanner camera is disabled in settings.', 'kerbcycle'),
                        [
                            'extra_classes' => 'qr-warning',
                            'log_type'      => 'dashboard_scanner_disabled',
                            'page'          => 'kerbcycle-qr-manager',
                            'status'        => 'failure',
                        ]
                    );
                    ?>
                <?php endif; ?>
                <div id="scan-result" class="updated"></div>
                <h2><?php esc_html_e('Manual QR Code Tasks', 'kerbcycle'); ?></h2>
                <div id="qr-task-options">
                    <label><input type="checkbox" id="send-email" <?php checked($email_enabled); ?> <?php disabled(!$email_enabled); ?>> <?php esc_html_e('Send notification email', 'kerbcycle'); ?></label>
                    <label><input type="checkbox" id="send-sms" <?php checked($sms_enabled); ?> <?php disabled(!$sms_enabled); ?>> <?php esc_html_e('Send SMS', 'kerbcycle'); ?></label>
                    <label><input type="checkbox" id="send-reminder" <?php checked($reminder_enabled); ?> <?php disabled(!$reminder_enabled); ?>> <?php esc_html_e('Schedule reminder', 'kerbcycle'); ?></label>
                </div>
                <div id="qr-selects">
                    <div class="qr-select-group">
                        <div>
                            <?php
            wp_dropdown_users(array(
                'name'             => 'customer_id',
                'id'               => 'customer-id',
                'class'            => 'kc-searchable',
                'show_option_none' => __('Select Customer', 'kerbcycle')
            ));
        ?>
                            <p class="description"><?php esc_html_e('Customer Search', 'kerbcycle'); ?></p>
                        </div>
                    </div>
                    <div class="qr-select-group">
                        <div>
                            <select id="qr-code-select" class="kc-searchable">
                                <option value=""><?php esc_html_e('Select QR Code', 'kerbcycle'); ?></option>
                                <?php foreach ($available_codes as $code) : ?>
                                    <option value="<?= esc_attr($code->qr_code); ?>"><?= esc_html($code->qr_code); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Available QR Code Search', 'kerbcycle'); ?></p>
                        </div>
                        <button id="assign-qr-btn" class="button button-primary"><?php esc_html_e('Assign QR Code', 'kerbcycle'); ?></button>
                    </div>
                    <div class="qr-select-group">
                        <div>
                            <select id="assigned-qr-code-select" class="kc-searchable">
                                <option value=""><?php esc_html_e('Select Assigned QR Code', 'kerbcycle'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Assigned QR Code Search', 'kerbcycle'); ?></p>
                        </div>
                        <button id="release-qr-btn" class="button"><?php esc_html_e('Release QR Code', 'kerbcycle'); ?></button>
                    </div>
                </div>
                <div class="qr-select-group">
                    <input type="text" id="new-qr-code" placeholder="<?php esc_attr_e('Enter QR Code', 'kerbcycle'); ?>" />
                    <button id="add-qr-btn" class="button"><?php esc_html_e('Add QR Code', 'kerbcycle'); ?></button>
                </div>
                <p class="description" style="margin-bottom: 15px;">
                    <?php esc_html_e('Manually add a QR Code.', 'kerbcycle'); ?>
                </p>
                <div class="qr-select-group">
                    <input type="file" id="import-qr-file" accept=".csv" />
                </div>
                <p class="description"><?php esc_html_e('Import QR Codes from a selected CSV File.', 'kerbcycle'); ?></p>
                <div class="qr-select-group">
                    <button id="import-qr-btn" class="button"><?php esc_html_e('Import QR Codes', 'kerbcycle'); ?></button>
                </div>
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
                <p class="qr-code-counts">QR Codes: <span class="qr-available-count"><?= esc_html($available_count); ?></span> Available <span class="qr-assigned-count"><?= esc_html($assigned_count); ?></span> Assigned</p>
                <div
                    id="qr-listing"
                    class="qr-listing"
                    data-current-page="<?= esc_attr($current_page); ?>"
                    data-total-pages="<?= esc_attr($total_pages); ?>"
                    data-total-items="<?= esc_attr($total_items); ?>"
                    data-per-page="<?= esc_attr($per_page); ?>"
                    data-status-filter="<?= esc_attr($status_filter); ?>"
                    data-start-date="<?= esc_attr($start_date); ?>"
                    data-end-date="<?= esc_attr($end_date); ?>"
                    data-search="<?= esc_attr($search); ?>"
                >
                    <?php if ($pagination_links) : ?>
                        <div class="qr-pagination" data-pagination-position="top">
                            <div class="qr-pagination-controls" aria-live="polite"></div>
                            <div class="tablenav qr-pagination-fallback">
                                <div class="tablenav-pages">
                                    <?= $pagination_links; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <ul id="qr-code-list">
                        <li class="qr-header">
                            <input type="checkbox" class="qr-select" id="qr-select-all" title="<?php esc_attr_e('Select all', 'kerbcycle'); ?>" />
                            <?php
                            $id_label        = __('ID', 'kerbcycle');
                            $code_label      = __('QR Code', 'kerbcycle');
                            $user_label      = __('User ID', 'kerbcycle');
                            $customer_label  = __('Customer', 'kerbcycle');
                            $status_label    = __('Status', 'kerbcycle');
                            $assigned_label  = __('Assigned At', 'kerbcycle');
                            $sort_label_text = __('Sort by %s', 'kerbcycle');
                            ?>
                            <span class="qr-id">
                                <button type="button" class="qr-sort-control" data-sort-key="id" data-sort-type="number" data-sort-label="<?= esc_attr($id_label); ?>" aria-pressed="false" title="<?= esc_attr(sprintf($sort_label_text, $id_label)); ?>">
                                    <span class="qr-sort-label"><?= esc_html($id_label); ?></span>
                                    <span class="sort-indicator" aria-hidden="true">
                                        <span class="sort-arrow sort-arrow-asc"></span>
                                        <span class="sort-arrow sort-arrow-desc"></span>
                                    </span>
                                </button>
                            </span>
                            <span class="qr-text">
                                <button type="button" class="qr-sort-control" data-sort-key="code" data-sort-type="text" data-sort-label="<?= esc_attr($code_label); ?>" aria-pressed="false" title="<?= esc_attr(sprintf($sort_label_text, $code_label)); ?>">
                                    <span class="qr-sort-label"><?= esc_html($code_label); ?></span>
                                    <span class="sort-indicator" aria-hidden="true">
                                        <span class="sort-arrow sort-arrow-asc"></span>
                                        <span class="sort-arrow sort-arrow-desc"></span>
                                    </span>
                                </button>
                            </span>
                            <span class="qr-user">
                                <button type="button" class="qr-sort-control" data-sort-key="userId" data-sort-type="number" data-sort-label="<?= esc_attr($user_label); ?>" aria-pressed="false" title="<?= esc_attr(sprintf($sort_label_text, $user_label)); ?>">
                                    <span class="qr-sort-label"><?= esc_html($user_label); ?></span>
                                    <span class="sort-indicator" aria-hidden="true">
                                        <span class="sort-arrow sort-arrow-asc"></span>
                                        <span class="sort-arrow sort-arrow-desc"></span>
                                    </span>
                                </button>
                            </span>
                            <span class="qr-name">
                                <button type="button" class="qr-sort-control" data-sort-key="displayName" data-sort-type="text" data-sort-label="<?= esc_attr($customer_label); ?>" aria-pressed="false" title="<?= esc_attr(sprintf($sort_label_text, $customer_label)); ?>">
                                    <span class="qr-sort-label"><?= esc_html($customer_label); ?></span>
                                    <span class="sort-indicator" aria-hidden="true">
                                        <span class="sort-arrow sort-arrow-asc"></span>
                                        <span class="sort-arrow sort-arrow-desc"></span>
                                    </span>
                                </button>
                            </span>
                            <span class="qr-status">
                                <button type="button" class="qr-sort-control" data-sort-key="status" data-sort-type="text" data-sort-label="<?= esc_attr($status_label); ?>" aria-pressed="false" title="<?= esc_attr(sprintf($sort_label_text, $status_label)); ?>">
                                    <span class="qr-sort-label"><?= esc_html($status_label); ?></span>
                                    <span class="sort-indicator" aria-hidden="true">
                                        <span class="sort-arrow sort-arrow-asc"></span>
                                        <span class="sort-arrow sort-arrow-desc"></span>
                                    </span>
                                </button>
                            </span>
                            <span class="qr-assigned">
                                <button type="button" class="qr-sort-control" data-sort-key="assignedAt" data-sort-type="date" data-sort-label="<?= esc_attr($assigned_label); ?>" aria-pressed="false" title="<?= esc_attr(sprintf($sort_label_text, $assigned_label)); ?>">
                                    <span class="qr-sort-label"><?= esc_html($assigned_label); ?></span>
                                    <span class="sort-indicator" aria-hidden="true">
                                        <span class="sort-arrow sort-arrow-asc"></span>
                                        <span class="sort-arrow sort-arrow-desc"></span>
                                    </span>
                                </button>
                            </span>
                        </li>
                        <?= self::render_qr_items($all_codes); ?>
                    </ul>
                    <?php if ($pagination_links) : ?>
                        <div class="qr-pagination" data-pagination-position="bottom">
                            <div class="qr-pagination-controls" aria-live="polite"></div>
                            <div class="tablenav qr-pagination-fallback">
                                <div class="tablenav-pages">
                                    <?= $pagination_links; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="qr-code-counts">QR Codes: <span class="qr-available-count"><?= esc_html($available_count); ?></span> Available <span class="qr-assigned-count"><?= esc_html($assigned_count); ?></span> Assigned</p>
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

    /**
     * Retrieve QR code listing data for the dashboard.
     *
     * @param array $args Request arguments.
     *
     * @return array
     */
    public static function get_listing_data(array $args = [])
    {
        global $wpdb;

        $table            = $wpdb->prefix . 'kerbcycle_qr_codes';
        $per_page_default = (int) get_option('kerbcycle_qr_codes_per_page', 20);
        $per_page         = isset($args['per_page']) ? absint($args['per_page']) : $per_page_default;

        if ($per_page < 1) {
            $per_page = $per_page_default > 0 ? $per_page_default : 20;
        }

        $raw_page     = isset($args['paged']) ? $args['paged'] : (isset($args['page']) ? $args['page'] : 1);
        $current_page = max(1, absint($raw_page));
        $offset       = ($current_page - 1) * $per_page;

        $status_filter = '';
        if (isset($args['status_filter'])) {
            $status_filter = sanitize_text_field((string) $args['status_filter']);
        }
        if ($status_filter && !in_array($status_filter, ['assigned', 'available'], true)) {
            $status_filter = '';
        }

        $start_date = '';
        if (isset($args['start_date'])) {
            $start_date = sanitize_text_field((string) $args['start_date']);
        }

        $end_date = '';
        if (isset($args['end_date'])) {
            $end_date = sanitize_text_field((string) $args['end_date']);
        }

        $search = '';
        if (isset($args['search'])) {
            $search = sanitize_text_field((string) $args['search']);
        } elseif (isset($args['s'])) {
            $search = sanitize_text_field((string) $args['s']);
        }

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

        $select_sql = "SELECT id, qr_code, user_id, display_name, status, assigned_at FROM $table WHERE $where ORDER BY assigned_at DESC, id DESC LIMIT %d OFFSET %d";
        $query_args = array_merge($params, [$per_page, $offset]);
        $codes      = $wpdb->get_results($wpdb->prepare($select_sql, $query_args));

        $available_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'available'");
        $assigned_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'assigned'");

        return [
            'codes'          => $codes,
            'per_page'       => $per_page,
            'current_page'   => $current_page,
            'total_pages'    => $total_pages,
            'total_items'    => $total_items,
            'available_count' => $available_count,
            'assigned_count' => $assigned_count,
            'filters'        => [
                'status_filter' => $status_filter,
                'start_date'    => $start_date,
                'end_date'      => $end_date,
                'search'        => $search,
            ],
        ];
    }

    /**
     * Render QR code list items.
     *
     * @param iterable $codes Codes to render.
     *
     * @return string
     */
    public static function render_qr_items($codes)
    {
        if (empty($codes)) {
            return '';
        }

        ob_start();

        foreach ($codes as $code) {
            $id_value      = isset($code->id) ? (int) $code->id : 0;
            $qr_code_value = isset($code->qr_code) ? (string) $code->qr_code : '';
            $user_id_value = isset($code->user_id) && $code->user_id !== null ? (string) $code->user_id : '';
            $name_value    = isset($code->display_name) && $code->display_name !== null ? (string) $code->display_name : '';
            $status_raw    = isset($code->status) && $code->status !== null ? (string) $code->status : '';
            $status_value  = $status_raw !== '' ? strtolower($status_raw) : '';
            $assigned_at   = isset($code->assigned_at) && $code->assigned_at !== null ? (string) $code->assigned_at : '';
            $status_label  = $status_raw !== '' ? ucfirst($status_raw) : '';
            ?>
            <li class="qr-item"
                data-code="<?= esc_attr($qr_code_value); ?>"
                data-id="<?= esc_attr($id_value); ?>"
                data-user-id="<?= esc_attr($user_id_value); ?>"
                data-display-name="<?= esc_attr($name_value); ?>"
                data-status="<?= esc_attr($status_value); ?>"
                data-assigned-at="<?= esc_attr($assigned_at); ?>">
                <input type="checkbox" class="qr-select" />
                <span class="qr-id"><?= esc_html($id_value); ?></span>
                <span class="qr-text" contenteditable="true"><?= esc_html($qr_code_value); ?></span>
                <span class="qr-user"><?= $user_id_value !== '' ? esc_html($user_id_value) : '—'; ?></span>
                <span class="qr-name"><?= $name_value !== '' ? esc_html($name_value) : '—'; ?></span>
                <span class="qr-status"><?= esc_html($status_label); ?></span>
                <span class="qr-assigned"><?= $assigned_at !== '' ? esc_html($assigned_at) : '—'; ?></span>
            </li>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Build pagination links for the dashboard list.
     *
     * @param int   $current_page Current page number.
     * @param int   $total_pages  Total number of pages.
     * @param array $filters      Active filters.
     *
     * @return string
     */
    public static function build_pagination_links($current_page, $total_pages, array $filters = [])
    {
        $total_pages = (int) $total_pages;

        if ($total_pages <= 1) {
            return '';
        }

        $current_page = max(1, (int) $current_page);

        $query_args = ['page' => 'kerbcycle-qr-manager'];

        if (!empty($filters['status_filter'])) {
            $query_args['status_filter'] = $filters['status_filter'];
        }

        if (!empty($filters['start_date'])) {
            $query_args['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $query_args['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $query_args['s'] = $filters['search'];
        }

        $base_url = add_query_arg($query_args, admin_url('admin.php'));
        $base     = add_query_arg('paged', '%#%', $base_url);

        return paginate_links([
            'base'      => $base,
            'format'    => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total'     => $total_pages,
            'current'   => $current_page,
        ]);
    }
}
