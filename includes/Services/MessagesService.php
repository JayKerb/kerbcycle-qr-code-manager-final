<?php

namespace Kerbcycle\QrCode\Services;

use Kerbcycle\QrCode\Helpers\Nonces;

if (!defined('ABSPATH')) exit;

class MessagesService {

    const OPT = 'kerbcycle_messages'; // stores an array of message templates

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
            'KerbCycle Message Settings',
            'Message Settings',
            'manage_options',
            'kerbcycle-messages',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;

        $messages = self::get_all();
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'edit';

        /** =========================
         *  SAVE HANDLER (Templates)
         *  ========================= */
        if (!empty($_POST['kc_msgs_save'])) {
            Nonces::verify('kc_msgs_save_nonce', 'kc_msgs_nonce');
            $sel = sanitize_text_field($_POST['kc_msg_type'] ?? 'assigned');

            $sms   = isset($_POST['kc_sms'])   ? wp_unslash($_POST['kc_sms'])   : '';
            $email = isset($_POST['kc_email']) ? wp_unslash($_POST['kc_email']) : '';

            $sms   = is_string($sms)   ? trim($sms)   : '';
            $email = is_string($email) ? trim($email) : '';

            if (!isset($messages[$sel])) $messages[$sel] = ['sms'=>'','email'=>''];
            $messages[$sel]['sms']   = wp_strip_all_tags($sms, true);
            $messages[$sel]['email'] = wp_strip_all_tags($email, true);

            update_option(self::OPT, $messages, false);
            echo '<div class="notice notice-success is-dismissible"><p>Messages saved for <strong>'.esc_html(self::label_for($sel)).'</strong>.</p></div>';
            $tab = 'edit';
        }

        /** =========================
         *  TEST HANDLER (Preview/Send)
         *  ========================= */
        $test_preview_sms   = '';
        $test_preview_email = '';
        $test_type          = 'assigned';
        $t_user = $t_code = $t_amount = $t_wallet = '';
        $t_to_sms = $t_to_email = '';
        $send_sms_checked = $send_email_checked = false;
        $do_send_sms = $do_send_email = false;
        if (!empty($_POST['kc_msgs_render']) || !empty($_POST['kc_msgs_send'])) {
            Nonces::verify('kc_msgs_test_nonce', 'kc_msgs_test_nonce_f');
            $t_type   = sanitize_text_field($_POST['kc_test_type'] ?? 'assigned');
            $test_type = $t_type;
            $t_user   = sanitize_text_field($_POST['kc_test_user'] ?? '');
            $t_code   = sanitize_text_field($_POST['kc_test_code'] ?? '');
            $t_amount = sanitize_text_field($_POST['kc_test_amount'] ?? '');
            $t_wallet = sanitize_text_field($_POST['kc_test_wallet'] ?? '');
            $t_to_sms = sanitize_text_field($_POST['kc_test_to_sms'] ?? '');
            $t_to_email = sanitize_email($_POST['kc_test_to_email'] ?? '');
            $send_sms_checked   = !empty($_POST['kc_test_send_sms']);
            $send_email_checked = !empty($_POST['kc_test_send_email']);
            $do_send_sms   = !empty($_POST['kc_msgs_send']) && $send_sms_checked;
            $do_send_email = !empty($_POST['kc_msgs_send']) && $send_email_checked;

            $rendered = self::render($t_type, [
                'user'   => $t_user,
                'code'   => $t_code,
                'amount' => $t_amount,
                'wallet' => $t_wallet,
            ]);

            $test_preview_sms   = $rendered['sms'];
            $test_preview_email = $rendered['email'];

            // SMS send (optional)
            if ($do_send_sms && $t_to_sms) {
                $r = \Kerbcycle\QrCode\Services\SmsService::send($t_to_sms, $test_preview_sms);
                if (is_wp_error($r)) {
                    $d = $r->get_error_data();
                    $http = (is_array($d) && isset($d['http'])) ? ' HTTP='.$d['http'] : '';
                    $body = (is_array($d) && isset($d['body'])) ? ' Body='.substr(is_string($d['body'])?$d['body']:json_encode($d['body']),0,300) : '';
                    echo '<div class="notice notice-error"><p><strong>Test SMS failed:</strong> '.esc_html($r->get_error_message().$http.$body).'</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>Test SMS sent.</strong> '.esc_html(json_encode($r)).'</p></div>';
                }
            }

            // Email send (optional)
            if ($do_send_email && $t_to_email) {
                $sent = wp_mail($t_to_email, 'KerbCycle Test: '.self::label_for($t_type), $test_preview_email);
                if ($sent) {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>Test Email sent</strong> to '.esc_html($t_to_email).'.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p><strong>Test Email failed</strong> (check site mail configuration).</p></div>';
                }
            }
            $tab = 'test';
        }

        // Active type for editor (default assigned)
        $active = isset($_POST['kc_msg_type']) ? sanitize_text_field($_POST['kc_msg_type']) : 'assigned';
        if (!isset($messages[$active])) $active = 'assigned';

        $types = self::types_map();
        ?>
        <div class="wrap">
            <h1>KerbCycle Message Settings</h1>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=kerbcycle-messages&tab=edit')); ?>" class="nav-tab <?php echo $tab === 'edit' ? 'nav-tab-active' : ''; ?>">Edit Messages</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=kerbcycle-messages&tab=test')); ?>" class="nav-tab <?php echo $tab === 'test' ? 'nav-tab-active' : ''; ?>">Test messages</a>
            </h2>

            <?php if ($tab === 'edit'): ?>
            <p>Edit the SMS and Email templates for each message type. Use placeholders:
                <code>{user}</code>, <code>{code}</code>, <code>{amount}</code>, <code>{wallet}</code>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=kerbcycle-messages&tab=edit')); ?>">
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
                                <textarea id="kc_sms" name="kc_sms" rows="4" style="width: 100%; max-width: 800px;"><?php echo esc_textarea($messages[$active]['sms'] ?? ''); ?></textarea>
                                <p class="description">Keep SMS concise (ideally &lt; 160 chars). Placeholders allowed.</p>
                            </td>
                        </tr>

                        <tr class="kc-email-row">
                            <th scope="row"><label for="kc_email">Email Text</label></th>
                            <td>
                                <textarea id="kc_email" name="kc_email" rows="8" style="width: 100%; max-width: 800px;"><?php echo esc_textarea($messages[$active]['email'] ?? ''); ?></textarea>
                                <p class="description">Plain text email. Placeholders allowed. Newlines are preserved.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" name="kc_msgs_save" value="1">Save</button>
                </p>
            </form>
            <?php else: ?>
            <p>Render a template with sample variables and optionally send a test SMS and/or Email.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=kerbcycle-messages&tab=test')); ?>">
                <?php wp_nonce_field('kc_msgs_test_nonce', 'kc_msgs_test_nonce_f'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="kc_test_type">Message Type</label></th>
                            <td>
                                <select id="kc_test_type" name="kc_test_type">
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($test_type, $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Variables</th>
                            <td>
                                <input type="text" name="kc_test_user"   placeholder="user e.g. Sam"   style="width:200px" value="<?php echo esc_attr($t_user); ?>" />
                                <input type="text" name="kc_test_code"   placeholder="code e.g. QR123" style="width:200px" value="<?php echo esc_attr($t_code); ?>" />
                                <input type="text" name="kc_test_amount" placeholder="amount e.g. $10"  style="width:200px" value="<?php echo esc_attr($t_amount); ?>" />
                                <input type="text" name="kc_test_wallet" placeholder="wallet e.g. TeraWallet" style="width:220px" value="<?php echo esc_attr($t_wallet); ?>" />
                                <p class="description">Only variables used in the selected template are needed.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Send Options</th>
                            <td>
                                <label><input type="checkbox" name="kc_test_send_sms" value="1" <?php checked($send_sms_checked); ?> /> Send SMS to:</label>
                                <input type="text" name="kc_test_to_sms" placeholder="+15551234567" style="width:200px; margin-right:20px;" value="<?php echo esc_attr($t_to_sms); ?>" />
                                <label><input type="checkbox" name="kc_test_send_email" value="1" <?php checked($send_email_checked); ?> /> Send Email to:</label>
                                <input type="email" name="kc_test_to_email" placeholder="you@example.com" style="width:240px" value="<?php echo esc_attr($t_to_email); ?>" />
                                <p class="description">Leave unchecked to just preview below.</p>
                            </td>
                        </tr>

                        <?php if ($test_preview_sms !== '' || $test_preview_email !== ''): ?>
                        <tr>
                            <th scope="row">Preview</th>
                            <td>
                                <?php if ($test_preview_sms !== ''): ?>
                                <p><strong>SMS:</strong></p>
                                <pre style="background:#f6f7f7;padding:10px;max-width:800px;white-space:pre-wrap;"><?php echo esc_html($test_preview_sms); ?></pre>
                                <?php endif; ?>

                                <?php if ($test_preview_email !== ''): ?>
                                <p><strong>Email:</strong></p>
                                <pre style="background:#f6f7f7;padding:10px;max-width:800px;white-space:pre-wrap;"><?php echo esc_html($test_preview_email); ?></pre>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button" name="kc_msgs_render" value="1">Render Test</button>
                    <button type="submit" class="button button-primary" name="kc_msgs_send" value="1">Send Test</button>
                </p>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($tab === 'edit'): ?>
        <script>
        (function(){
            const $type = document.getElementById('kc_msg_type');
            const $sms  = document.getElementById('kc_sms');
            const $email= document.getElementById('kc_email');
            const $desc = document.getElementById('kc_msg_desc');

            const ALL   = <?php echo wp_json_encode($messages); ?>;
            const DESCS = <?php echo wp_json_encode(self::descriptions_map()); ?>;

            function updateFields() {
                const key = $type.value;
                const data = ALL[key] || {sms:'', email:''};
                $sms.value   = (data.sms || '');
                $email.value = (data.email || '');
                $desc.textContent = DESCS[key] || '';
            }

            $type && $type.addEventListener('change', updateFields);
            $type && updateFields();
        })();
        </script>
        <?php endif; ?>

        <style>
        .kc-sms-row textarea, .kc-email-row textarea, pre {
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

// No boot needed, admin menu is registered in Admin class.

