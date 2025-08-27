<?php
/**
 * KerbCycle Messages History (with self-repair + activation setup)
 *
 * - Creates/repairs table {$wpdb->prefix}kerbcycle_message_logs using dbDelta()
 * - Activation hook support to ensure schema exists
 * - “Repair Table” button on the admin page if structure is missing
 * - Helpful error notices (shows last DB error)
 * - SMS/Email tabs; paginated, filterable logs; bulk delete & clear-all
 */
if (!defined('ABSPATH')) exit;

class KerbCycle_Messages_History {

    const SCHEMA_VERSION = '1.0.0';
    protected $parent_slug;
    protected $page_slug = 'kerbcycle-messages-history';
    protected $table;
    protected $last_error = '';

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'kerbcycle_message_logs';
        $this->parent_slug = apply_filters('kerbcycle/admin_parent_slug', 'kerbcycle-qr-manager');

        add_action('admin_init',  [$this, 'maybe_create_or_upgrade']);

        add_action('admin_post_kerbcycle_clear_logs',  [$this, 'handle_clear_logs']);
        add_action('admin_post_kerbcycle_delete_logs', [$this, 'handle_bulk_delete']);
        add_action('admin_post_kerbcycle_repair_logs', [$this, 'handle_repair_logs']);
    }

    /** Call from main plugin with register_activation_hook */
    public static function activate() {
        $self = new self();
        $self->maybe_create_or_upgrade(true);
        update_option('kerbcycle_msg_schema_version', self::SCHEMA_VERSION);
    }

    /** Public helper to record a message log */
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

        $row = [
            'type'       => in_array($data['type'], ['sms','email'], true) ? $data['type'] : 'sms',
            'recipient'  => sanitize_text_field($data['to']),
            'subject'    => sanitize_text_field($data['subject']),
            'body'       => wp_kses_post($data['body']),
            'status'     => sanitize_text_field($data['status']),
            'provider'   => sanitize_text_field($data['provider']),
            'response'   => is_scalar($data['response']) ? wp_kses_post((string)$data['response']) : wp_json_encode($data['response']),
            'created_at' => current_time('mysql', true), // UTC
        ];

        $table = $wpdb->prefix . 'kerbcycle_message_logs';
        $wpdb->insert($table, $row, ['%s','%s','%s','%s','%s','%s','%s','%s']);
    }

    /** Build the canonical CREATE TABLE statement for dbDelta */
    protected function schema_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table = $this->table;

        // NOTE: dbDelta is picky: field lines, indexes, PRIMARY KEY format, and spacing matter.
        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(10) NOT NULL,
            recipient VARCHAR(190) NOT NULL,
            subject VARCHAR(255) DEFAULT '',
            body LONGTEXT,
            status VARCHAR(30) DEFAULT '',
            provider VARCHAR(100) DEFAULT '',
            response LONGTEXT,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY type_idx (type),
            KEY created_idx (created_at),
            KEY recipient_idx (recipient)
        ) {$charset_collate};";
    }

    /** Ensure table exists & is up to date. Optionally force (on activation/repair). */
    public function maybe_create_or_upgrade($force = false) {
        global $wpdb;

        $needs_upgrade = $force || (get_option('kerbcycle_msg_schema_version') !== self::SCHEMA_VERSION);

        // If table missing or schema outdated, run dbDelta
        if ($needs_upgrade || !$this->table_is_valid()) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $sql = $this->schema_sql();
            dbDelta($sql);

            // Re-check validity; record any DB error for display
            if (!$this->table_is_valid()) {
                $this->last_error = $wpdb->last_error ?: 'Unknown error running dbDelta().';
            } else {
                update_option('kerbcycle_msg_schema_version', self::SCHEMA_VERSION);
            }
        }
    }

    /** Quick structural validation (are the expected columns present?) */
    protected function table_is_valid() {
        global $wpdb;
        $expected = [
            'id','type','recipient','subject','body','status','provider','response','created_at'
        ];
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$this->table}", 0);
        if (empty($cols) || !is_array($cols)) return false;

        foreach ($expected as $c) {
            if (!in_array($c, $cols, true)) return false;
        }
        return true;
    }

    /** Admin Menu */
    public function register_admin_menu() {
        $hook = add_submenu_page(
            $this->parent_slug,
            __('Messages History', 'kerbcycle'),
            __('Messages History', 'kerbcycle'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_page']
        );

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

    /** Actions */
    public function handle_clear_logs() {
        if (!current_user_can('manage_options')) wp_die(__('Access denied.', 'kerbcycle'));
        check_admin_referer('kerbcycle_clear_logs');

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->table}");

        wp_redirect(add_query_arg(['page'=>$this->page_slug,'cleared'=>1], admin_url('admin.php')));
        exit;
    }

    public function handle_bulk_delete() {
        if (!current_user_can('manage_options')) wp_die(__('Access denied.', 'kerbcycle'));
        check_admin_referer('kerbcycle_delete_logs');

        $ids = isset($_POST['log_ids']) && is_array($_POST['log_ids']) ? array_map('absint', $_POST['log_ids']) : [];
        $deleted = 0;
        if ($ids) {
            global $wpdb;
            $in = implode(',', array_fill(0, count($ids), '%d'));
            $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$this->table} WHERE id IN ($in)", $ids));
        }

        wp_redirect(add_query_arg(['page'=>$this->page_slug,'deleted'=>(int)$deleted], admin_url('admin.php')));
        exit;
    }

    public function handle_repair_logs() {
        if (!current_user_can('manage_options')) wp_die(__('Access denied.', 'kerbcycle'));
        check_admin_referer('kerbcycle_repair_logs');

        $this->maybe_create_or_upgrade(true);

        $args = ['page'=>$this->page_slug];
        if ($this->table_is_valid()) {
            $args['repaired'] = 1;
        } else {
            $args['repair_failed'] = 1;
        }
        wp_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /** Page UI */
    public function render_page() {
        if (!current_user_can('manage_options')) return;

        $active_tab = isset($_GET['tab']) && $_GET['tab'] === 'email' ? 'email' : 'sms';
        $search     = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $from       = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to         = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
        $paged      = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $per_page   = 25;

        // Validate table; provide repair notice if needed
        $table_ok = $this->table_is_valid();
        if (!$table_ok) {
            $this->maybe_create_or_upgrade(); // one more try during render
        }

        $results = $table_ok ? $this->get_logs($active_tab, $search, $from, $to, $paged, $per_page) : [];
        $total   = $table_ok ? $this->count_logs($active_tab, $search, $from, $to) : 0;
        $pages   = max(1, (int)ceil($total / $per_page));
        $base_url = remove_query_arg(['paged','deleted','cleared','repaired','repair_failed'], admin_url('admin.php?page=' . $this->page_slug));
        ?>
        <div class="wrap">
            <style>
            .kc-msg-history .widefat { table-layout: fixed; width: 100%; }
            .kc-msg-history th, .kc-msg-history td {
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: top;
            }
            .kc-msg-history th { writing-mode: horizontal-tb !important; transform: none !important; }
            .kc-msg-history .col-cb{ width:24px; } .kc-msg-history .col-id{ width:80px; }
            .kc-msg-history .col-date{ width:160px; } .kc-msg-history .col-type{ width:80px; }
            .kc-msg-history .col-recipient{ width:240px; } .kc-msg-history .col-status{ width:120px; }
            .kc-msg-history .col-provider{ width:150px; }
            .kc-msg-history .col-subject, .kc-msg-history .col-body {
                white-space: normal; overflow: hidden; max-height: 4.8em; line-height: 1.6em;
            }
            .kc-msg-history .filters input[type="search"] { min-width: 260px; }
            .kc-msg-history .actions-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:10px; }
            </style>

            <div class="kc-msg-history">
                <h1><?php esc_html_e('Messages History', 'kerbcycle'); ?></h1>

                <?php if (!$table_ok): ?>
                    <div class="notice notice-error"><p>
                        <?php esc_html_e('The message logs table is missing or incomplete. Click “Repair Table” to (re)create the correct structure.', 'kerbcycle'); ?>
                        <?php if (!empty($this->last_error)) : ?>
                            <br><strong><?php esc_html_e('Last DB error:', 'kerbcycle'); ?></strong> <?php echo esc_html($this->last_error); ?>
                        <?php endif; ?>
                    </p></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:8px 0;">
                        <?php wp_nonce_field('kerbcycle_repair_logs'); ?>
                        <input type="hidden" name="action" value="kerbcycle_repair_logs" />
                        <button class="button button-primary"><?php esc_html_e('Repair Table', 'kerbcycle'); ?></button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($_GET['repaired'])): ?>
                    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Logs table repaired.', 'kerbcycle'); ?></p></div>
                <?php endif; ?>

                <?php if (!empty($_GET['repair_failed'])): ?>
                    <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Repair failed. Check server error logs or DB permissions.', 'kerbcycle'); ?></p></div>
                <?php endif; ?>

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

                <form class="filters" method="get" style="margin:12px 0;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($this->page_slug); ?>" />
                    <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>" />
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search recipient, subject, body, status…', 'kerbcycle'); ?>" />
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
                                <td class="col-cb"><input type="checkbox" id="kc-select-all" /></td>
                                <th class="col-id"><?php esc_html_e('ID', 'kerbcycle'); ?></th>
                                <th class="col-date"><?php esc_html_e('Date (UTC)', 'kerbcycle'); ?></th>
                                <th class="col-type"><?php esc_html_e('Type', 'kerbcycle'); ?></th>
                                <th class="col-recipient"><?php esc_html_e('Recipient', 'kerbcycle'); ?></th>
                                <?php if ($active_tab==='email'): ?>
                                    <th class="col-subject"><?php esc_html_e('Subject', 'kerbcycle'); ?></th>
                                <?php endif; ?>
                                <th class="col-body"><?php esc_html_e('Body', 'kerbcycle'); ?></th>
                                <th class="col-status"><?php esc_html_e('Status', 'kerbcycle'); ?></th>
                                <th class="col-provider"><?php esc_html_e('Provider', 'kerbcycle'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($results)): ?>
                                <tr><td colspan="<?php echo $active_tab==='email' ? 9 : 8; ?>"><?php esc_html_e('No logs found.', 'kerbcycle'); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($results as $row): ?>
                                    <tr>
                                        <td class="col-cb"><input type="checkbox" name="log_ids[]" value="<?php echo (int)$row->id; ?>" /></td>
                                        <td class="col-id"><?php echo (int)$row->id; ?></td>
                                        <td class="col-date"><?php echo esc_html($row->created_at); ?></td>
                                        <td class="col-type"><?php echo esc_html(strtoupper($row->type)); ?></td>
                                        <td class="col-recipient"><?php echo esc_html($row->recipient); ?></td>
                                        <?php if ($active_tab==='email'): ?>
                                            <td class="col-subject"><?php echo esc_html($row->subject); ?></td>
                                        <?php endif; ?>
                                        <td class="col-body"><?php echo wp_kses_post(wp_trim_words($row->body, 24, '…')); ?></td>
                                        <td class="col-status"><?php echo esc_html($row->status); ?></td>
                                        <td class="col-provider" title="<?php echo esc_attr(wp_strip_all_tags((string)$row->response)); ?>">
                                            <?php echo esc_html($row->provider); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="actions-row">
                        <button type="submit" class="button button-secondary" <?php disabled(empty($results)); ?>>
                            <?php esc_html_e('Delete Selected', 'kerbcycle'); ?>
                        </button>
                    </div>
                </form>

                <!-- Clear All logs (separate form) -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline; margin-top:8px;">
                    <?php wp_nonce_field('kerbcycle_clear_logs'); ?>
                    <input type="hidden" name="action" value="kerbcycle_clear_logs" />
                    <button class="button button-link-delete"
                        onclick="return confirm('<?php echo esc_js(__('Clear ALL logs? This cannot be undone.', 'kerbcycle')); ?>')">
                        <?php esc_html_e('Clear All', 'kerbcycle'); ?>
                    </button>
                </form>

                <?php
                if ($pages > 1): ?>
                    <div class="tablenav" style="margin-top:12px;">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base'      => esc_url(add_query_arg(['paged'=>'%#%','tab'=>$active_tab,'s'=>$search,'from'=>$from,'to'=>$to], $base_url)),
                                'format'    => '',
                                'current'   => $paged,
                                'total'     => $pages,
                                'prev_text' => __('« Prev', 'kerbcycle'),
                                'next_text' => __('Next »', 'kerbcycle'),
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <script>
            (function(){
                const all = document.getElementById('kc-select-all');
                if (all) {
                    all.addEventListener('change', function(){
                        document.querySelectorAll('input[name="log_ids[]"]').forEach(function(cb){
                            cb.checked = all.checked;
                        });
                    });
                }
            }());
            </script>
        </div>
        <?php
    }

    /** Data access */
    protected function get_logs($type, $search, $from, $to, $paged, $per_page) {
        global $wpdb;

        $where   = ['type = %s'];
        $params  = [$type];

        if ($search !== '') {
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(recipient LIKE %s OR subject LIKE %s OR body LIKE %s OR status LIKE %s OR provider LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($from) { $where[] = 'DATE(created_at) >= %s'; $params[] = $from; }
        if ($to)   { $where[] = 'DATE(created_at) <= %s'; $params[] = $to;   }

        $offset = ($paged - 1) * $per_page;
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page; $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    protected function count_logs($type, $search, $from, $to) {
        global $wpdb;

        $where   = ['type = %s'];
        $params  = [$type];

        if ($search !== '') {
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(recipient LIKE %s OR subject LIKE %s OR body LIKE %s OR status LIKE %s OR provider LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($from) { $where[] = 'DATE(created_at) >= %s'; $params[] = $from; }
        if ($to)   { $where[] = 'DATE(created_at) <= %s'; $params[] = $to;   }

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $where);
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }
}

