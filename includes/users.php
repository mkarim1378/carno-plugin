<?php
// ============================================================================
// مدیریت کاربران - ساخت حساب، اتصال سفارشات، نام نمایشی، کش
// ============================================================================

// بررسی وجود کاربر بر اساس شماره تلفن
function user_exists_by_phone($phone) {
    $normalized_phone = substr(preg_replace('/[^0-9]/', '', $phone), -10);
    if (strlen($normalized_phone) < 10) {
        return false;
    }

    $cache_key = 'user_phone_' . md5($normalized_phone);
    $cached_user_id = get_transient($cache_key);

    if ($cached_user_id !== false) {
        return $cached_user_id;
    }

    $user = get_user_by('login', '0' . $normalized_phone);
    if (!$user) {
        $user = get_user_by('login', $normalized_phone);
    }
    if ($user) {
        set_transient($cache_key, $user->ID, 1800);
        return $user->ID;
    }

    $user_query = new WP_User_Query([
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'billing_phone', 'value' => $normalized_phone, 'compare' => 'LIKE'],
            ['key' => 'digits_phone_no', 'value' => $normalized_phone, 'compare' => 'LIKE'],
        ],
        'number' => 1
    ]);

    if (!empty($user_query->results)) {
        $user_id = $user_query->results[0]->ID;
        set_transient($cache_key, $user_id, 1800);
        return $user_id;
    }

    set_transient($cache_key, false, 1800);
    return false;
}

// ساخت کاربر از سفارش مهمان یا اتصال به کاربر موجود
function create_user_from_guest_order_by_phone_v2($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $customer_id = $order->get_customer_id();
    if (!empty($customer_id) && $customer_id != 0) {
        error_log("Skipping order #{$order_id}: Already linked to user ID {$customer_id}.");
        return;
    }

    $phone = $order->get_billing_phone();
    if (empty($phone)) {
        error_log("Skipping order #{$order_id}: No billing phone.");
        return;
    }

    $existing_user_id = user_exists_by_phone($phone);
    if ($existing_user_id) {
        $order->set_customer_id($existing_user_id);
        $order->add_order_note('این سفارش به کاربر موجود با شماره تلفن متصل شد.');
        $order->save();
        return;
    }

    $raw_phone  = preg_replace('/[^0-9]/', '', $phone);
    $normalized = substr($raw_phone, -10);
    $username   = '0' . $normalized;

    $domain     = wp_parse_url(home_url(), PHP_URL_HOST);
    $email      = "phone_{$username}@{$domain}";
    $password   = wp_generate_password();
    $first_name = $order->get_billing_first_name();
    $last_name  = $order->get_billing_last_name();

    $user_id = wc_create_new_customer($email, $username, $password);

    if (is_wp_error($user_id)) {
        error_log('[Carno] Failed to create customer for order #' . $order_id . ': ' . $user_id->get_error_message());
        return;
    }

    if (!empty($first_name)) update_user_meta($user_id, 'first_name', $first_name);
    if (!empty($last_name)) update_user_meta($user_id, 'last_name', $last_name);
    wp_update_user(['ID' => $user_id, 'display_name' => trim($first_name . ' ' . $last_name)]);

    $order->set_customer_id($user_id);
    $order->add_order_note('یک حساب کاربری جدید به صورت خودکار برای این مشتری مهمان ایجاد شد و سفارش به آن متصل گردید.');
    $order->save();
}

add_action('woocommerce_order_status_completed', 'create_user_from_guest_order_by_phone_v2');

// ============================================================================
// اتصال سفارشات مهمان به حساب کاربری بعد از لاگین
function connect_guest_orders_by_phone_to_user_account($user_id) {
    if ( !$user_id ) {
        return;
    }

    $user_phone = get_user_meta($user_id, 'billing_phone', true);

    if ( empty($user_phone) ) {
        return;
    }

    $normalized_phone = substr($user_phone, -9);
    if ( empty($normalized_phone) ) {
        return;
    }

    $guest_orders = wc_get_orders(array(
        'limit'        => -1,
        'customer_id'  => 0,
        'meta_key'     => '_billing_phone',
        'meta_value'   => '%' . $normalized_phone,
        'meta_compare' => 'LIKE',
    ));

    if ( $guest_orders ) {
        foreach ( $guest_orders as $order ) {
            $order_id = $order->get_id();

            update_post_meta($order_id, '_customer_user', $user_id);
            $order->set_customer_id($user_id);

            $order->add_order_note(
                sprintf(
                    'این سفارش به صورت خودکار به کاربر با شناسه %d متصل شد (بر اساس تطبیق شماره تلفن).',
                    $user_id
                )
            );

            $order->save();
        }
    }
}

add_action('voorodak_after_do_login', 'connect_guest_orders_by_phone_to_user_account', 10, 1);
add_action('voorodak_after_do_register', 'connect_guest_orders_by_phone_to_user_account', 10, 1);

// ============================================================================
// تغییر نام نمایشی پس از ثبت‌نام یا ویرایش پروفایل
add_action( 'profile_update', 'mk_format_user_display_name_on_profile_update', 10, 2 );

function mk_format_user_display_name_on_profile_update( $user_id, $old_user_data ) {
    mk_update_user_display_name( $user_id );
}

function mk_update_user_display_name( $user_id ) {
    $first_name = get_user_meta( $user_id, 'first_name', true );
    $last_name = get_user_meta( $user_id, 'last_name', true );
    $full_name = trim( $first_name . ' ' . $last_name );

    if ( ! empty( $full_name ) ) {
        $user = get_userdata( $user_id );
        if ( $user->display_name !== $full_name ) {
            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => $full_name,
            ) );
        }
    }
}

// ============================================================================
// شمارش کامنت‌های کاربر هنگام لاگین
function wpheart_update_comments_count($user_login, $user) {
    global $wpdb;
    $user_id = $user->ID;
    error_log("wpheart_update_comments_count called for user ID: {$user_id}");
    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 1 AND user_id = %d",
        $user_id
    );
    $cm_count = $wpdb->get_var($query);
    error_log("Query executed: {$query}");
    error_log("Comments count: {$cm_count}");
    update_user_meta($user_id, 'wpheart_comments_count', $cm_count);
    error_log("User meta updated: {$cm_count}");
}
add_action('wp_login', 'wpheart_update_comments_count', 10, 2);

// ============================================================================
// نگه داشتن کاربر لاگین برای یک سال
add_filter('auth_cookie_expiration', 'keep_user_logged_in_for_1_year');

function keep_user_logged_in_for_1_year($expirein) {
    return 31556926; // 1 year in seconds
}

// ============================================================================
// پاک‌سازی کش هنگام تغییرات مهم
function clear_performance_cache() {
    if (isset($_GET['order-received'])) {
        $user_id = get_current_user_id();
        if ($user_id) {
            delete_transient('user_products_' . $user_id);
        }
    }
}
add_action('init', 'clear_performance_cache');

function clear_user_cache_on_profile_update($user_id) {
    delete_transient('user_phone_' . md5($user_id));
    delete_transient('user_products_' . $user_id);
}
add_action('profile_update', 'clear_user_cache_on_profile_update');
