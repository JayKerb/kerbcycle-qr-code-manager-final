<?php

namespace Kerbcycle\QrCode\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The redirects page.
 *
 * @since      1.0.0
 * @package    Kerbcycle\QrCode
 * @subpackage Kerbcycle\QrCode\Admin\Pages
 */
class RedirectsPage
{
    /**
     * Redirect to the Bookly appointments page.
     *
     * @since    1.0.0
     */
    public function bookly_appointments()
    {
        wp_redirect(admin_url('admin.php?page=bookly-appointments'));
        exit;
    }

    /**
     * Redirect to the TeraWallet page.
     *
     * @since    1.0.0
     */
    public function terawallet()
    {
        wp_redirect(admin_url('admin.php?page=woo-wallet'));
        exit;
    }
}
