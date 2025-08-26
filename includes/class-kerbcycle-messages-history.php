<?php
/**
 * KerbCycle Messages History
 *
 * - Creates (if missing) a logs table: {\$wpdb->prefix}kerbcycle_message_logs
 * - Adds an admin "Messages History" page with two tabs (SMS / Email)
 * - Shows paginated table with search, date filter, bulk delete, and clear-all
 * - Provides KerbCycle_Messages_History::log_message() helper to record logs
 */
if (!defined('ABSPATH')) exit;

class KerbCycle_Messages_History {

    /** @var string Filterable parent menu slug (fallbacks to Tools if missing) */
    protected $parent_slug;

    /** @var string Submenu slug for this page */
    protected $page_slug = 'kerbcycle-messages-history';

    /** @var string DB table name */
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'kerbcycle_message_logs';

        // Allow host plugin to override where this submenu lives
        $this->parent_slug = apply_filters('kerbcycle/admin_parent_slug', 'kerbcycle-qr-manager');

        add_action('admin_init', [$this, 'maybe_create_table']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_post_kerbcycle_clear_logs', [$this, 'handle_clear_logs']);
        add_action('admin_post_kerbcycle_delete_logs', [$this, 'handle_bulk_delete']);
    }

    /**
     * Public helper: record a message log.
     * Usage:
     * KerbCycle_Messages_History::log_message([
     *   'type' => 'sms'|'email',
     *   'to' => '+15551234567' or 'name@example.com',
     *   'subject' => 'Optional for email',
     *   'body' => 'Message text',
     *   'status' => 'sent'|'failed',
     *   'provider' => 'twilio|textbelt|wp_mail|...'(optional),
     *   'response' => 'raw gateway response or error (optional)'
     * ]);
     */
    public static function log_message($args) {
        global $wpdb;

        $defaults = [
            'type'     => '',
            'to'       => '',
            'subject'  => '',
            'body'     => '',
            'status'   => '',
            'provider' => '',
            'response' => '',
        ];
        $data = wp_parse_args($args, $defaults);

        // Basic sanitation
        $row = [
            'type'      => in_array($data['type'], ['sms','email'], true) ? $data['type'] : 'sms',
            'recipient' => sanitize_text_field($data['to']),
            'subject'   => sanitize_text_field($data['subject']),
            'body'      => wp_kses_post($data['body']),
            'status'    => sanitize_text_field($data['status']),
            'provider'  => sanitize_text_field($data['provider']),
            'response'  => is_scalar($data['response']) ? wp_kses_post($data['response']) : wp_json_encode($data['response']),
            'created_at'=> current_time('mysql', true), // UTC
        ];

        $table = $wpdb->prefix . 'kerbcycle_message_logs';
        $wpdb->insert($table, $row, ['%s','%s','%s','%s','%s','%s','%s','%s']);
    }

    /** Create logs table if missing */
    public function maybe_create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(10) NOT NULL,                -- sms | email
            recipient VARCHAR(190) NOT NULL,
            subject VARCHAR(255) DEFAULT '',
            body LONGTEXT,
            status VARCHAR(30) DEFAULT '',
            provider VARCHAR(100) DEFAULT '',
            response LONGTEXT,
            created_at DATETIME NOT NULL,            -- stored in UTC
            PRIMARY KEY (id),
            KEY type_idx (type),
            KEY created_idx (created_at),
            KEY recipient_idx (recipient)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /** Register submenu (falls back to Tools if parent is absent) */
    public function register_admin_menu() {
        // Try to add under host plugin menu
        $hook = add_submenu_page(
            $this->parent_slug,
            __('Messages History', 'kerbcycle'),
            __('Messages History', 'kerbcycle'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_page']
        );

        // If parent menu doesn't exist, fallback to Tools
        if (!$hook) {
            add_management_page(
                __('Messages History', 'kerbcycle'),
                __('Messages History', 'kerbcycle'),
                'manage_options',
                $this->page_slug,
                [$this, 'render_page']
            );
        }
    }

    /** Handle clear-all logs action */
    public function handle_clear_logs() {
        if (!current_user_can('manage_options')) wp_die(__('Access denied.', 'kerbcycle'));
        check_admin_referer('kerbcycle_clear_logs');

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->table}");

        wp_redirect(add_query_arg(['page' => $this->page_slug, 'cleared' => 1], admin_url('tools.php')));
        exit;
    }

    /** Handle bulk delete */
    public function handle_bulk_delete() {
        if (!current_user_can('manage_options')) wp_die(__('Access denied.', 'kerbcycle'));
        check_admin_referer('kerbcycle_delete_logs');

        $ids = isset($_POST['log_ids']) && is_array($_POST['log_ids']) ? array_map('absint', $_POST['log_ids']) : [];
        if ($ids) {
            global $wpdb;
            $in = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$this->table} WHERE id IN ($in)", $ids));
        }

        $redirect = add_query_arg(['page' => $this->page_slug, 'deleted' => count($ids)], admin_url('tools.php'));
        wp_redirect($redirect);
        exit;
    }

    /** Render admin page with tabs */
    public function render_page() {
        if (!current_user_can('manage_options')) return;

        $active_tab = isset($_GET['tab']) && $_GET['tab'] === 'email' ? 'email' : 'sms';
        $search     = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $from       = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to         = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
        $paged      = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $per_page   = 25;

        $results = $this->get_logs($active_tab, $search, $from, $to, $paged, $per_page);
        $total   = $this->count_logs($active_tab, $search, $from, $to);
        $pages   = max(1, (int)ceil($total / $per_page));

        $base_url = remove_query_arg(['paged','deleted','cleared'], menu_page_url($this->page_slug, false));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Messages History', 'kerbcycle'); ?></h1>

            <?php if (!empty($_GET['deleted'])): ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php printf(esc_html__('%d log(s) deleted.', 'kerbcycle'), absint($_GET['deleted'])); ?>
                </p></div>
            <?php endif; ?>

            <?php if (!empty($_GET['cleared'])): ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e('All logs cleared.', 'kerbcycle'); ?>
                </p></div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper" style="margin-top:12px;">
                <a href="<?php echo esc_url(add_query_arg(['tab'=>'sms','paged'=>1], $base_url)); ?>"
                   class="nav-tab <?php echo $active_tab==='sms' ? 'nav-tab-active' : ''; ?>">
                   <?php esc_html_e('SMS', 'kerbcycle'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['tab'=>'email','paged'=>1], $base_url)); ?>"
                   class="nav-tab <?php echo $active_tab==='email' ? 'nav-tab-active' : ''; ?>">
                   <?php esc_html_e('Email', 'kerbcycle'); ?>
                </a>
            </h2>

            <form method="get" style="margin:12px 0;">
                <input type="hidden" name="page" value="<?php echo esc_attr($this->page_slug); ?>" />
                <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>" />
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search recipient, subject, body, status…', 'kerbcycle'); ?>" style="min-width:260px;" />
                <input type="date" name="from" value="<?php echo esc_attr($from); ?>" />
                <input type="date" name="to" value="<?php echo esc_attr($to); ?>" />
                <button class="button"><?php esc_html_e('Filter', 'kerbcycle'); ?></button>
                <a class="button" href="<?php echo esc_url(add_query_arg(['s'=>null,'from'=>null,'to'=>null,'paged'=>1], $base_url)); ?>">
                    <?php esc_html_e('Reset', 'kerbcycle'); ?>
                </a>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('kerbcycle_delete_logs'); ?>
                <input type="hidden" name="action" value="kerbcycle_delete_logs" />

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <td style="width:24px;"><input type="checkbox" id="kc-select-all" /></td>
                            <th style="width:80px;"><?php esc_html_e('ID', 'kerbcycle'); ?></th>
                            <th style="width:140px;"><?php esc_html_e('Date (UTC)', 'kerbcycle'); ?></th>
                            <th style="width:80px;"><?php esc_html_e('Type', 'kerbcycle'); ?></th>
                            <th style="width:220px;"><?php esc_html_e('Recipient', 'kerbcycle'); ?></th>
                            <?php if ($active_tab==='email'): ?>
                                <th><?php esc_html_e('Subject', 'kerbcycle'); ?></th>
                            <?php endif; ?>
                            <th><?php esc_html_e('Body', 'kerbcycle'); ?></th>
                            <th style="width:110px;"><?php esc_html_e('Status', 'kerbcycle'); ?></th>
                            <th style="width:130px;"><?php esc_html_e('Provider', 'kerbcycle'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr><td colspan="<?php echo $active_tab==='email' ? 9 : 8; ?>"><?php esc_html_e('No logs found.', 'kerbcycle'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td><input type="checkbox" name="log_ids[]" value="<?php echo (int)$row->id; ?>" /></td>
                                    <td><?php echo (int)$row->id; ?></td>
                                    <td><?php echo esc_html($row->created_at); ?></td>
                                    <td><?php echo esc_html(strtoupper($row->type)); ?></td>
                                    <td><?php echo esc_html($row->recipient); ?></td>
                                    <?php if ($active_tab==='email'): ?>
                                        <td><?php echo esc_html($row->subject); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo wp_kses_post(wp_trim_words($row->body, 24, '…')); ?></td>
                                    <td><?php echo esc_html($row->status); ?></td>
                                    <td title="<?php echo esc_attr(wp_strip_all_tags($row->response)); ?>">
                                        <?php echo esc_html($row->provider); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top:10px; display:flex; gap:8px; align-items:center;">
                    <button class="button button-secondary" <?php disabled(empty($results)); ?>>
                        <?php esc_html_e('Delete Selected', 'kerbcycle'); ?>
                    </button>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                        <?php wp_nonce_field('kerbcycle_clear_logs'); ?>
                        <input type="hidden" name="action" value="kerbcycle_clear_logs" />
                        <button class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Clear ALL logs? This cannot be undone.', 'kerbcycle')); ?>')">
                            <?php esc_html_e('Clear All', 'kerbcycle'); ?>
                        </button>
                    </form>
                </div>
            </form>

            <?php if ($pages > 1): ?>
                <div class="tablenav" style="margin-top:12px;">
                    <div class="tablenav-pages">
                        <?php
                        $current = $paged;
                        $base    = add_query_arg(['paged'=>'%#%'], $base_url);
                        echo paginate_links([
                            'base'      => esc_url($base . '&tab=' . $active_tab . '&s=' . urlencode($search) . '&from=' . urlencode($from) . '&to=' . urlencode($to)),
                            'format'    => '',
                            'current'   => $current,
                            'total'     => $pages,
                            'prev_text' => __('« Prev', 'kerbcycle'),
                            'next_text' => __('Next »', 'kerbcycle'),
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        </div><!-- .wrap -->

        <script>
        (function(){
            const all = document.getElementById('kc-select-all');
            if (all) {
                all.addEventListener('change', function(){
                    document.querySelectorAll('input[name="log_ids[]"]').forEach(cb => cb.checked = all.checked);
                });
            }
        }());
        </script>
        <?php
    }

    /** Fetch logs */
    protected function get_logs($type, $search, $from, $to, $paged, $per_page) {
        global $wpdb;

        $where   = ['type = %s'];
        $params  = [$type];

        if ($search !== '') {
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(recipient LIKE %s OR subject LIKE %s OR body LIKE %s OR status LIKE %s OR provider LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($from) {
            $where[] = 'DATE(created_at) >= %s';
            $params[] = $from;
        }
        if ($to) {
            $where[] = 'DATE(created_at) <= %s';
            $params[] = $to;
        }

        $offset = ($paged - 1) * $per_page;
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /** Count logs for pagination */
    protected function count_logs($type, $search, $from, $to) {
        global $wpdb;

        $where   = ['type = %s'];
        $params  = [$type];

        if ($search !== '') {
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(recipient LIKE %s OR subject LIKE %s OR body LIKE %s OR status LIKE %s OR provider LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($from) {
            $where[] = 'DATE(created_at) >= %s';
            $params[] = $from;
        }
        if ($to) {
            $where[] = 'DATE(created_at) <= %s';
            $params[] = $to;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $where);
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }
}

new KerbCycle_Messages_History();

