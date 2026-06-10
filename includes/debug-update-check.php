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

    // 0. اطلاعات پایه
    global $wp_version;
    $out .= "=== Basic Info ===\n";
    $out .= 'WP version: ' . $wp_version . "\n";
    $out .= 'Site locale: ' . get_locale() . "\n";
    $out .= 'Object cache: ' . ( wp_using_ext_object_cache() ? 'YES (external object cache active)' : 'no' ) . "\n\n";

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

        $decoded = json_decode( $body, true );
        if ( ! empty( $decoded['offers'] ) ) {
            $out .= "Offers returned by API:\n";
            foreach ( $decoded['offers'] as $offer ) {
                $out .= '  - response: ' . ( $offer['response'] ?? '?' )
                      . ' | version: ' . ( $offer['current'] ?? '?' )
                      . ' | locale: ' . ( $offer['locale'] ?? '?' ) . "\n";
            }
        } else {
            $out .= "No offers in response.\n";
        }
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

    $out .= 'update_core transient: ' . ( $core_transient ? 'set' : 'FALSE (not set)' ) . "\n";
    if ( $core_transient && ! empty( $core_transient->updates ) ) {
        $out .= "Core updates/offers in transient:\n";
        foreach ( $core_transient->updates as $u ) {
            $out .= '  - ' . ( $u->response ?? '?' ) . ' -> ' . ( $u->version ?? '?' ) . ' (current: ' . ( $u->current ?? '?' ) . ")\n";
        }
        $out .= 'version_checked: ' . ( $core_transient->version_checked ?? '?' ) . "\n";
    } else {
        $out .= "Core updates: none / transient empty\n";
    }

    $out .= "\n";
    $out .= 'update_plugins transient: ' . ( $plugins_transient ? 'set' : 'FALSE (not set)' ) . "\n";
    if ( $plugins_transient ) {
        $out .= 'plugins checked (count): ' . ( isset( $plugins_transient->checked ) ? count( (array) $plugins_transient->checked ) : 0 ) . "\n";
        $out .= 'plugins with no_update (count): ' . ( isset( $plugins_transient->no_update ) ? count( (array) $plugins_transient->no_update ) : 0 ) . "\n";
        $out .= 'last_checked: ' . ( isset( $plugins_transient->last_checked ) ? date( 'Y-m-d H:i:s', $plugins_transient->last_checked ) : '?' ) . "\n";
    }
    if ( $plugins_transient && ! empty( $plugins_transient->response ) ) {
        $out .= "\nPlugin updates found:\n";
        foreach ( $plugins_transient->response as $file => $info ) {
            $out .= '  - ' . $file . ' -> ' . ( $info->new_version ?? '?' ) . "\n";
        }
    } else {
        $out .= "\nPlugin updates: none\n";
    }

    if ( $themes_transient && ! empty( $themes_transient->response ) ) {
        $out .= "\nTheme updates found:\n";
        foreach ( $themes_transient->response as $file => $info ) {
            $out .= '  - ' . $file . ' -> ' . ( $info['new_version'] ?? '?' ) . "\n";
        }
    } else {
        $out .= "\nTheme updates: none / transient empty\n";
    }

    // 5. تست مستقیم درخواست چک آپدیت افزونه‌ها (مثل وردپرس)
    $out .= "\n=== Manual plugin update-check request ===\n";
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();
    $out .= 'Installed plugins (get_plugins count): ' . count( $all_plugins ) . "\n";

    $plugin_info = [];
    foreach ( $all_plugins as $file => $p ) {
        $plugin_info[ $file ] = [
            'Name'    => $p['Name'],
            'Version' => $p['Version'],
        ];
    }

    $payload = [
        'plugins'      => [ 'plugins' => $plugin_info, 'active' => array_values( get_option( 'active_plugins', [] ) ) ],
        'translations' => [],
        'locale'       => [ get_locale() ],
        'all'          => true,
    ];

    $manual_resp = wp_remote_post( 'https://api.wordpress.org/plugins/update-check/1.1/', [
        'timeout' => 15,
        'body'    => [ 'plugins' => wp_json_encode( $payload['plugins'] ), 'translations' => wp_json_encode( $payload['translations'] ), 'locale' => wp_json_encode( $payload['locale'] ), 'all' => wp_json_encode( true ) ],
    ] );

    if ( is_wp_error( $manual_resp ) ) {
        $out .= 'ERROR: ' . $manual_resp->get_error_message() . "\n";
    } else {
        $code = wp_remote_retrieve_response_code( $manual_resp );
        $body = wp_remote_retrieve_body( $manual_resp );
        $out .= "HTTP code: $code\n";
        $out .= 'Body length: ' . strlen( $body ) . " bytes\n";
        $decoded = json_decode( $body, true );
        $out .= 'plugins in response: ' . ( isset( $decoded['plugins'] ) ? count( $decoded['plugins'] ) : 0 ) . "\n";
        $out .= 'no_update in response: ' . ( isset( $decoded['no_update'] ) ? count( $decoded['no_update'] ) : 0 ) . "\n";
        if ( empty( $decoded['plugins'] ) && empty( $decoded['no_update'] ) ) {
            $out .= 'Raw body preview: ' . esc_html( substr( $body, 0, 500 ) ) . "\n";
        }
    }

    $out .= '</div>';

    return $out;
});
