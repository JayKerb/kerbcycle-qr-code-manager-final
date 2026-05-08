<?php

namespace Kerbcycle\QrCode\Services;

// Handle third-party plugin integrations and summaries
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IntegrationsService {
    public static function get_summaries() {
        $user_id   = get_current_user_id();
        $summaries = array();

        // Bookly - scheduling pickups
        $bookly_active  = class_exists( 'Bookly\\Lib\\Plugin' ) || defined( 'BOOKLY_VERSION' );
        $bookly_summary = $bookly_active ? __( 'Bookly active. Schedule pickups through Bookly.', 'kerbcycle-qr-code-manager' ) : __( 'Bookly inactive.', 'kerbcycle-qr-code-manager' );
        $summaries[]    = array(
            'name'    => 'Bookly',
            'active'  => $bookly_active,
            'summary' => $bookly_summary,
        );

        // Ultimate Member - customer portal
        $um_active   = function_exists( 'um_user' ) || class_exists( 'UM' );
        $um_summary  = $um_active ? __( 'Ultimate Member active. Customer portal available.', 'kerbcycle-qr-code-manager' ) : __( 'Ultimate Member inactive.', 'kerbcycle-qr-code-manager' );
        $summaries[] = array(
            'name'    => 'Ultimate Member',
            'active'  => $um_active,
            'summary' => $um_summary,
        );

        // WooCommerce - payments
        $wc_active   = class_exists( 'WooCommerce' );
        $wc_summary  = $wc_active ? __( 'WooCommerce active. Payments enabled.', 'kerbcycle-qr-code-manager' ) : __( 'WooCommerce inactive.', 'kerbcycle-qr-code-manager' );
        $summaries[] = array(
            'name'    => 'WooCommerce',
            'active'  => $wc_active,
            'summary' => $wc_summary,
        );

        // TeraWallet - customer balance
        $wallet_active = function_exists( 'woo_wallet' ) || class_exists( 'Woo_Wallet' );
        $balance       = '';
        if ( $wallet_active && $wc_active && function_exists( 'woo_wallet' ) ) {
            $wallet = woo_wallet();
            if ( isset( $wallet->wallet ) && method_exists( $wallet->wallet, 'get_wallet_balance' ) ) {
                $balance_raw = $wallet->wallet->get_wallet_balance( $user_id, 'edit' );
                if ( function_exists( 'wc_price' ) ) {
                    $balance = wc_price( $balance_raw );
                } else {
                    $balance = $balance_raw;
                }
            }
        }
        /* translators: %s: wallet balance amount or N/A fallback text. */
        $wallet_summary = $wallet_active ? sprintf( __( 'Wallet balance: %s', 'kerbcycle-qr-code-manager' ), $balance !== '' ? $balance : __( 'N/A', 'kerbcycle-qr-code-manager' ) ) : __( 'TeraWallet inactive.', 'kerbcycle-qr-code-manager' );
        $summaries[]    = array(
            'name'    => 'TeraWallet',
            'active'  => $wallet_active,
            'summary' => $wallet_summary,
        );

        // WP SMS - messaging
        $sms_active  = class_exists( 'WP_SMS' ) || defined( 'WP_SMS_VERSION' );
        $sms_summary = $sms_active ? __( 'WP SMS active. SMS notifications available.', 'kerbcycle-qr-code-manager' ) : __( 'WP SMS inactive.', 'kerbcycle-qr-code-manager' );
        $summaries[] = array(
            'name'    => 'WP SMS',
            'active'  => $sms_active,
            'summary' => $sms_summary,
        );

        return $summaries;
    }
}
