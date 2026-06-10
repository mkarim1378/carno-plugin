<?php
// ============================================================================
// بهینه‌سازی عملکرد - حذف bloat وردپرس و مسدود کردن درخواست‌های خارجی
// ============================================================================

function TextHasString($text, $string) {
    return strpos($text, $string) !== false;
}

function BlockExternalHostRequests($false, $parsed_args, $url) {
    $blockedHosts = [
        'rankmath.com',
        'googleapis.com',
        'fonts.googleapis.com',
        'github.com',
        'yoast.com',
        // 'api.wordpress.org',
        'w.org',
        'yoa.st',
        'unyson.io',
        'siteorigin.com',
        'elementor.com',
        'cdnjs.cloudflare.com',
        'cloudflare.com',
        'woocommerce.com'
    ];

    foreach ( $blockedHosts as $host ) {
        if ( !empty($host) && TextHasString($url, $host) ) {
            return [
                'headers'  => '',
                'body'     => '',
                'response' => '',
                'cookies'  => '',
                'filename' => ''
            ];
        }
    }
    return $false;
}

// add_filter('pre_http_request', 'BlockExternalHostRequests', 10, 3);
add_filter( 'use_block_editor_for_post', '__return_false' );
add_filter( 'use_widgets_block_editor', '__return_false' );
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'global-styles' );
} );

// غیر فعال کردن آپدیت ترجمه‌ها
add_filter( 'auto_update_translation', '__return_false' );
add_filter( 'async_update_translation', '__return_false' );

// ============================================================================
// حذف هدرهای اضافی وردپرس
add_action('init', 'nias_remove_wp_headers');
function nias_remove_wp_headers() {
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'index_rel_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'parent_post_rel_link', 10, 0);
    remove_action('wp_head', 'start_post_rel_link', 10, 0);
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
    remove_action('wp_head', 'wp_shortlink_header', 10, 0);
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
}

// غیرفعال کردن RSS و فیدها
function nias_disable_feed() {
    wp_die( __( 'فید غیرفعال است، لطفاً به <a href="'. esc_url( home_url( '/' ) ) .'">صفحه اصلی</a> برگردید.' ) );
}
add_action('do_feed', 'nias_disable_feed', 1);
add_action('do_feed_rdf', 'nias_disable_feed', 1);
add_action('do_feed_rss', 'nias_disable_feed', 1);
add_action('do_feed_rss2', 'nias_disable_feed', 1);
add_action('do_feed_atom', 'nias_disable_feed', 1);
add_action('do_feed_rss2_comments', 'nias_disable_feed', 1);
add_action('do_feed_atom_comments', 'nias_disable_feed', 1);

remove_action('wp_head', 'feed_links_extra', 3);
remove_action('wp_head', 'feed_links', 2);

// حذف Dashicons برای کاربران غیر لاگین
add_action('wp_print_styles', 'nias_remove_dashicons', 100);
function nias_remove_dashicons() {
    if (!is_admin_bar_showing() && !is_customize_preview()) {
        wp_dequeue_style('dashicons');
        wp_deregister_style('dashicons');
    }
}

// غیرفعال کردن Emoji
add_action('init', 'nias_disable_emojis');
function nias_disable_emojis() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', 'nias_disable_emojis_tinymce');
}

function nias_disable_emojis_tinymce($plugins) {
    if (is_array($plugins)) {
        return array_diff($plugins, array('wpemoji'));
    } else {
        return array();
    }
}

// حذف همه استایل‌ها و اسکریپت‌ها در صفحه eps (به جز jQuery و Gravity Forms)
add_action('wp_enqueue_scripts', function() {
    if ( ! is_page(['eps', 'eps1']) ) return;

    global $wp_scripts, $wp_styles;

    $keep_scripts = ['jquery', 'jquery-core', 'jquery-migrate'];
    $keep_styles  = [];

    foreach ( $wp_scripts->registered as $handle => $script ) {
        if ( strpos($handle, 'gform') === 0 || strpos($handle, 'gravityforms') === 0 ) {
            $keep_scripts[] = $handle;
        }
    }
    foreach ( $wp_styles->registered as $handle => $style ) {
        if ( strpos($handle, 'gform') === 0 || strpos($handle, 'gravityforms') === 0 ) {
            $keep_styles[] = $handle;
        }
    }

    foreach ( $wp_scripts->queue as $handle ) {
        if ( ! in_array($handle, $keep_scripts) ) wp_dequeue_script($handle);
    }
    foreach ( $wp_styles->queue as $handle ) {
        if ( ! in_array($handle, $keep_styles) ) wp_dequeue_style($handle);
    }
}, PHP_INT_MAX );

// حذف jquery-migrate
add_action('wp_enqueue_scripts', function() {
    wp_deregister_script('jquery-migrate');
    wp_dequeue_script('jquery-migrate');
}, 100);

add_filter('wp_default_scripts', function($scripts) {
    if (isset($scripts->registered['jquery'])) {
        $scripts->registered['jquery']->deps = array_diff(
            $scripts->registered['jquery']->deps,
            ['jquery-migrate']
        );
    }
});
