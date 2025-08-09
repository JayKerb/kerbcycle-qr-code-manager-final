<div class="wrap">
    <h1><?php esc_html_e('KerbCycle QR Settings', 'kerbcycle'); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('kerbcycle_qr_settings');
        do_settings_sections('kerbcycle_qr_settings');
        submit_button();
        ?>
    </form>
</div>
