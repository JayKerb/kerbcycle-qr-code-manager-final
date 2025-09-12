<?php

namespace Kerbcycle\QrCode\Services;

// Handle third-party plugin integrations and summaries
if (!defined('ABSPATH')) {
    exit;
}

class IntegrationsService
{
    public static function get_summaries()
    {
        $user_id   = get_current_user_id();
        $summaries = array();

        // Bookly - scheduling pickups
        $bookly_active  = class_exists('Bookly\\Lib\\Plugin') || defined('BOOKLY_VERSION');
        $bookly_summary = $bookly_active ? __('Bookly active. Schedule pickups through Bookly.', 'kerbcycle') : __('Bookly inactive.', 'kerbcycle');
        $summaries[]    = array(
            'name'    => 'Bookly',
            'active'  => $bookly_active,
            'summary' => $bookly_summary,
        );

        // Ultimate Member - customer portal
        $um_active  = function_exists('um_user') || class_exists('UM');
        $um_summary = $um_active ? __('Ultimate Member active. Customer portal available.', 'kerbcycle') : __('Ultimate Member inactive.', 'kerbcycle');
        $summaries[] = array(
            'name'    => 'Ultimate Member',
            'active'  => $um_active,
            'summary' => $um_summary,
        );

        // WooCommerce - payments
        $wc_active  = class_exists('WooCommerce');
        $wc_summary = $wc_active ? __('WooCommerce active. Payments enabled.', 'kerbcycle') : __('WooCommerce inactive.', 'kerbcycle');
        $summaries[] = array(
            'name'    => 'WooCommerce',
            'active'  => $wc_active,
            'summary' => $wc_summary,
        );

        // TeraWallet - customer balance
        $wallet_active = function_exists('woo_wallet') || class_exists('Woo_Wallet');
        $balance       = '';
        if ($wallet_active && $wc_active && function_exists('woo_wallet')) {
            $wallet = woo_wallet();
            if (isset($wallet->wallet) && method_exists($wallet->wallet, 'get_wallet_balance')) {
                $balance_raw = $wallet->wallet->get_wallet_balance($user_id, 'edit');
                if (function_exists('wc_price')) {
                    $balance = wc_price($balance_raw);
                } else {
                    $balance = $balance_raw;
                }
            }
        }
        $wallet_summary = $wallet_active ? sprintf(__('Wallet balance: %s', 'kerbcycle'), $balance !== '' ? $balance : __('N/A', 'kerbcycle')) : __('TeraWallet inactive.', 'kerbcycle');
        $summaries[]    = array(
            'name'    => 'TeraWallet',
            'active'  => $wallet_active,
            'summary' => $wallet_summary,
        );

        // WP SMS - messaging
        $sms_active  = class_exists('WP_SMS') || defined('WP_SMS_VERSION');
        $sms_summary = $sms_active ? __('WP SMS active. SMS notifications available.', 'kerbcycle') : __('WP SMS inactive.', 'kerbcycle');
        $summaries[] = array(
            'name'    => 'WP SMS',
            'active'  => $sms_active,
            'summary' => $sms_summary,
        );

        return $summaries;
    }
}
