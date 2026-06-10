<?php
// ============================================================================
// ابزار موقت دیباگ - بررسی وضعیت چک آپدیت‌های وردپرس
// شورتکد: [carno_update_debug] - فقط برای مدیر سایت نمایش داده می‌شود
// بعد از رفع مشکل این فایل و require آن در carno-plugin.php حذف شود
// ============================================================================

add_shortcode( 'carno_update_debug', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }

    $out = '<div style="direction:ltr; text-align:left; font-family:monospace; font-size:13px; background:#f5f5f5; border:1px solid #ccc; padding:15px; line-height:1.8; white-space:pre-wrap;">';

    // 1. وضعیت کرون
    $out .= "=== WP Cron ===\n";
    $out .= 'DISABLE_WP_CRON: ' . ( defined('DISABLE_WP_CRON') ? var_export( DISABLE_WP_CRON, true ) : 'not defined (false)' ) . "\n";
    $out .= 'ALTERNATE_WP_CRON: ' . ( defined('ALTERNATE_WP_CRON') ? var_export( ALTERNATE_WP_CRON, true ) : 'not defined (false)' ) . "\n";

    $next_version_check = wp_next_scheduled( 'wp_version_check' );
    $next_update_plugins = wp_next_scheduled( 'wp_update_plugins' );
    $next_update_themes  = wp_next_scheduled( 'wp_update_themes' );

    $out .= 'next wp_version_check: ' . ( $next_version_check ? date( 'Y-m-d H:i:s', $next_version_check ) . ' (in ' . human_time_diff( time(), $next_version_check ) . ')' : 'NOT SCHEDULED' ) . "\n";
    $out .= 'next wp_update_plugins: ' . ( $next_update_plugins ? date( 'Y-m-d H:i:s', $next_update_plugins ) . ' (in ' . human_time_diff( time(), $next_update_plugins ) . ')' : 'NOT SCHEDULED' ) . "\n";
    $out .= 'next wp_update_themes: ' . ( $next_update_themes ? date( 'Y-m-d H:i:s', $next_update_themes ) . ' (in ' . human_time_diff( time(), $next_update_themes ) . ')' : 'NOT SCHEDULED' ) . "\n\n";

    // 2. ثابت‌های مرتبط با آپدیت
    $out .= "=== Update Constants ===\n";
    $out .= 'AUTOMATIC_UPDATER_DISABLED: ' . ( defined('AUTOMATIC_UPDATER_DISABLED') ? var_export( AUTOMATIC_UPDATER_DISABLED, true ) : 'not defined (false)' ) . "\n";
    $out .= 'DISALLOW_FILE_MODS: ' . ( defined('DISALLOW_FILE_MODS') ? var_export( DISALLOW_FILE_MODS, true ) : 'not defined (false)' ) . "\n";
    $out .= 'WP_AUTO_UPDATE_CORE: ' . ( defined('WP_AUTO_UPDATE_CORE') ? var_export( WP_AUTO_UPDATE_CORE, true ) : 'not defined' ) . "\n\n";

    // 3. تست مستقیم اتصال به api.wordpress.org
    $out .= "=== Connection test to api.wordpress.org ===\n";
    $resp = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/', [ 'timeout' => 15 ] );
    if ( is_wp_error( $resp ) ) {
        $out .= 'ERROR: ' . $resp->get_error_message() . "\n";
    } else {
        $code = wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp );
        $out .= "HTTP code: $code\n";
        $out .= 'Body length: ' . strlen( $body ) . " bytes\n";
        $out .= 'Body preview: ' . esc_html( substr( $body, 0, 200 ) ) . "\n";
    }
    $out .= "\n";

    // 4. اجبار به اجرای چک آپدیت همین الان
    $out .= "=== Forcing update checks now ===\n";
    delete_site_transient( 'update_core' );
    delete_site_transient( 'update_plugins' );
    delete_site_transient( 'update_themes' );
    wp_version_check( [], true );
    wp_update_plugins();
    wp_update_themes();

    $core_transient    = get_site_transient( 'update_core' );
    $plugins_transient = get_site_transient( 'update_plugins' );
    $themes_transient  = get_site_transient( 'update_themes' );

    if ( $core_transient && ! empty( $core_transient->updates ) ) {
        $out .= "Core updates found:\n";
        foreach ( $core_transient->updates as $u ) {
            $out .= '  - ' . ( $u->response ?? '?' ) . ' -> ' . ( $u->version ?? '?' ) . "\n";
        }
    } else {
        $out .= "Core updates: none / transient empty\n";
    }

    if ( $plugins_transient && ! empty( $plugins_transient->response ) ) {
        $out .= "\nPlugin updates found:\n";
        foreach ( $plugins_transient->response as $file => $info ) {
            $out .= '  - ' . $file . ' -> ' . ( $info->new_version ?? '?' ) . "\n";
        }
    } else {
        $out .= "\nPlugin updates: none / transient empty\n";
    }

    if ( $themes_transient && ! empty( $themes_transient->response ) ) {
        $out .= "\nTheme updates found:\n";
        foreach ( $themes_transient->response as $file => $info ) {
            $out .= '  - ' . $file . ' -> ' . ( $info['new_version'] ?? '?' ) . "\n";
        }
    } else {
        $out .= "\nTheme updates: none / transient empty\n";
    }

    $out .= '</div>';

    return $out;
});
