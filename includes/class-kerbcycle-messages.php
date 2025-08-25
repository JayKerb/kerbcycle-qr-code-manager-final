<?php
if (!defined('ABSPATH')) exit;

class KerbCycle_Messages {

    const OPT = 'kerbcycle_messages'; // stores an array of message templates

    public static function boot() {
        // Load after the main QR Codes menu so we don't override the default page
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 20);
    }

    public static function defaults() {
        // Each type has: ['sms' => '', 'email' => '']
        return [
            'assigned'   => [
                'sms'   => 'KerbCycle: QR {code} has been assigned to your account.',
                'email' => "Hi {user},\n\nYour QR code {code} has been assigned to your account.\n\nThanks,\nKerbCycle",
            ],
            'released'   => [
                'sms'   => 'KerbCycle: QR {code} has been released from your account. Thank you!',
                'email' => "Hi {user},\n\nYour QR code {code} has been released.\n\nThanks,\nKerbCycle",
            ],
            'funds_to'   => [
                'sms'   => 'KerbCycle: {amount} was added to your {wallet} wallet.',
                'email' => "Hi {user},\n\nWe transferred {amount} to your {wallet} wallet.\n\nThanks,\nKerbCycle",
            ],
            'funds_from' => [
                'sms'   => 'KerbCycle: {amount} was deducted from your {wallet} wallet.',
                'email' => "Hi {user},\n\nWe deducted {amount} from your {wallet} wallet.\n\nThanks,\nKerbCycle",
            ],
        ];
    }

    public static function get_all() {
        $saved = get_option(self::OPT, []);
        $defaults = self::defaults();
        if (!is_array($saved)) $saved = [];
        // merge defaults with saved (saved wins if set)
        foreach ($defaults as $k => $pair) {
            if (!isset($saved[$k]) || !is_array($saved[$k])) {
                $saved[$k] = $pair;
            } else {
                foreach ($pair as $kk => $vv) {
                    if (!isset($saved[$k][$kk])) $saved[$k][$kk] = $vv;
                }
            }
        }
        return $saved;
    }

    /* ---------------- Admin page ---------------- */

    public static function admin_menu() {
        add_submenu_page(
            'kerbcycle-qr-manager',
            'Messages',
            'Messages',
            'manage_options',
            'kerbcycle-messages',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;

        $messages = self::get_all();

        // Handle save
        if (!empty($_POST['kc_msgs_save']) && check_admin_referer('kc_msgs_save_nonce', 'kc_msgs_nonce')) {
            $sel = sanitize_text_field($_POST['kc_msg_type'] ?? 'assigned');

            // sanitize incoming text boxes
            $sms   = isset($_POST['kc_sms'])   ? wp_unslash($_POST['kc_sms'])   : '';
            $email = isset($_POST['kc_email']) ? wp_unslash($_POST['kc_email']) : '';

            $sms   = is_string($sms)   ? trim($sms)   : '';
            $email = is_string($email) ? trim($email) : '';

            if (!isset($messages[$sel])) $messages[$sel] = ['sms'=>'','email'=>''];
            // Very light sanitization: keep plain text; allow basic punctuation and placeholders
            $messages[$sel]['sms']   = wp_strip_all_tags($sms, true);
            // For email, allow basic newlines; strip tags to keep it simple
            $messages[$sel]['email'] = wp_strip_all_tags($email, true);

            update_option(self::OPT, $messages, false);

            echo '<div class="notice notice-success is-dismissible"><p>Messages saved for <strong>'.esc_html(self::label_for($sel)).'</strong>.</p></div>';
        }

        // Active tab/message type (default assigned)
        $active = isset($_POST['kc_msg_type']) ? sanitize_text_field($_POST['kc_msg_type']) : 'assigned';
        if (!isset($messages[$active])) $active = 'assigned';

        // Labels
        $types = self::types_map();

        ?>
        <div class="wrap">
            <h1>KerbCycle Messages</h1>
            <p>Choose a message type, edit the SMS and Email text, then click <strong>Save</strong>.</p>
            <p><em>Placeholders supported:</em> <code>{user}</code>, <code>{code}</code>, <code>{amount}</code>, <code>{wallet}</code></p>

            <form method="post" action="">
                <?php wp_nonce_field('kc_msgs_save_nonce', 'kc_msgs_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="kc_msg_type">Message Type</label></th>
                            <td>
                                <select id="kc_msg_type" name="kc_msg_type">
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($active, $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description" id="kc_msg_desc"></p>
                            </td>
                        </tr>

                        <tr class="kc-sms-row">
                            <th scope="row"><label for="kc_sms">SMS Text</label></th>
                            <td>
                                <textarea id="kc_sms" name="kc_sms" rows="4" style="width: 100%; max-width: 800px;">
<?php echo esc_textarea($messages[$active]['sms'] ?? ''); ?>
                                </textarea>
                                <p class="description">Keep SMS concise (ideally &lt; 160 chars). Placeholders allowed.</p>
                            </td>
                        </tr>

                        <tr class="kc-email-row">
                            <th scope="row"><label for="kc_email">Email Text</label></th>
                            <td>
                                <textarea id="kc_email" name="kc_email" rows="8" style="width: 100%; max-width: 800px;">
<?php echo esc_textarea($messages[$active]['email'] ?? ''); ?>
                                </textarea>
                                <p class="description">Plain text email. Placeholders allowed. Newlines are preserved.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" name="kc_msgs_save" value="1">Save</button>
                </p>
            </form>
        </div>

        <script>
        (function(){
            const $type = document.getElementById('kc_msg_type');
            const $sms  = document.getElementById('kc_sms');
            const $email= document.getElementById('kc_email');
            const $desc = document.getElementById('kc_msg_desc');

            // All messages from PHP (so switching type updates fields without reload)
            const ALL = <?php echo wp_json_encode($messages); ?>;
            const DESCS = <?php echo wp_json_encode(self::descriptions_map()); ?>;

            function updateFields() {
                const key = $type.value;
                const data = ALL[key] || {sms:'', email:''};
                $sms.value   = (data.sms || '');
                $email.value = (data.email || '');
                $desc.textContent = DESCS[key] || '';
            }

            // When switching type, swap text boxes content
            $type.addEventListener('change', updateFields);

            // Initialize
            updateFields();
        })();
        </script>

        <style>
        .kc-sms-row textarea, .kc-email-row textarea {
            font-family: Menlo, Consolas, Monaco, monospace;
        }
        </style>
        <?php
    }

    private static function types_map() {
        return [
            'assigned'   => 'QR code is assigned',
            'released'   => 'QR code is released',
            'funds_to'   => 'Funds Transfer to customer account (TeraWallet/Woo Wallet)',
            'funds_from' => 'Funds Transfer from customer account (TeraWallet/Woo Wallet)',
        ];
    }

    private static function descriptions_map() {
        return [
            'assigned'   => 'Sent when a QR code is assigned to a customer.',
            'released'   => 'Sent when a QR code is released from a customer.',
            'funds_to'   => 'Sent when funds are added to the customer’s wallet.',
            'funds_from' => 'Sent when funds are deducted from the customer’s wallet.',
        ];
    }

    private static function label_for($key) {
        $map = self::types_map();
        return $map[$key] ?? $key;
    }

    /* -------- Helpers to fetch templates from elsewhere in your plugin -------- */

    /**
     * Get a message template pair by type.
     * @param string $type One of: assigned|released|funds_to|funds_from
     * @return array ['sms' => '...', 'email' => '...']
     */
    public static function get_template($type) {
        $all = self::get_all();
        return isset($all[$type]) ? $all[$type] : ['sms'=>'','email'=>''];
    }

    /**
     * Render a template with placeholders replaced.
     * Usage: KerbCycle_Messages::render('assigned', ['user'=>'Sam','code'=>'QR123'])
     */
    public static function render($type, array $vars) {
        $tpl = self::get_template($type);
        $replace = [];
        foreach ($vars as $k=>$v) {
            $replace['{'.trim($k).'}'] = (string)$v;
        }
        return [
            'sms'   => strtr($tpl['sms'] ?? '', $replace),
            'email' => strtr($tpl['email'] ?? '', $replace),
        ];
    }
}

// boot the page
KerbCycle_Messages::boot();
