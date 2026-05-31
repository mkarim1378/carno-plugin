<?php
// ============================================================================
// مدیریت سفارشات ووکامرس - تکمیل خودکار، فیلتر، ستون‌های ادمین
// ============================================================================

// تکمیل خودکار سفارشات هنگام پردازش
add_action( 'woocommerce_order_status_processing', 'auto_complete_all_orders', 10, 1 );
add_action( 'woocommerce_thankyou', 'auto_complete_all_orders', 10, 1 );
function auto_complete_all_orders( $order_id ) {
    if ( ! $order_id ) {
        return;
    }

    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }

    if ( $order->has_status( 'processing' ) ) {
        $order->update_status( 'completed', 'تغییر خودکار به حالت تکمیل شده' );
    }
}

// ============================================================================
// حذف سفارشات لغو شده از صفحه حساب کاربری
add_filter('woocommerce_my_account_my_orders_query', 'filter_canceled_orders_from_my_account');
function filter_canceled_orders_from_my_account($args) {
    $args['status'] = array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-refunded', 'wc-failed');
    return $args;
}

// حذف صفحه‌بندی از لیست سفارشات حساب کاربری
add_filter('woocommerce_my_account_my_orders_query', 'disable_my_account_orders_pagination');
function disable_my_account_orders_pagination($args) {
    $args['posts_per_page'] = -1;
    return $args;
}

// ============================================================================
// سفارشی‌سازی ستون‌های جدول سفارشات در حساب کاربری
function customize_my_orders_columns($columns) {
    unset($columns['order-total']);
    $order_actions = $columns['order-actions'];
    unset($columns['order-actions']);
    $columns['product_names'] = __('محصولات', 'woocommerce');
    $columns['order-actions'] = $order_actions;
    return $columns;
}
add_filter('woocommerce_my_account_my_orders_columns', 'customize_my_orders_columns');

function display_product_names_in_my_orders($order) {
    $items = $order->get_items();
    $product_names = [];

    foreach ($items as $item) {
        $product = $item->get_product();
        if ($product) {
            $product_names[] = $product->get_name();
        }
    }

    echo implode(' - ', $product_names);
}
add_action('woocommerce_my_account_my_orders_column_product_names', 'display_product_names_in_my_orders');

// ============================================================================
// ستون محصولات در پنل مدیریت سفارشات (سازگار با HPOS و قدیمی)
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'add_order_products_column_woo' );
add_filter( 'manage_edit-shop_order_columns', 'add_order_products_column_woo' );
function add_order_products_column_woo( $columns ) {
    $columns['order_products'] = 'محصولات';
    return $columns;
}

add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'show_order_products_column_content_woo', 10, 2 );
add_action( 'manage_shop_order_posts_custom_column', 'show_order_products_column_content_woo', 10, 2 );
function show_order_products_column_content_woo( $column, $order_id ) {
    if ( 'order_products' === $column ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $items = $order->get_items();
        $product_list = array();
        foreach ( $items as $item ) {
            $product_list[] = $item->get_name();
        }
        echo implode( '<br>', $product_list );
    }
}

// ============================================================================
// نمایش شماره موبایل + دکمه فیلتر سفارشات در ستون صورتحساب (سازگار با HPOS و قدیمی)
add_action( 'manage_shop_order_posts_custom_column', 'carno_billing_phone_in_billing_column_legacy', 20, 2 );
function carno_billing_phone_in_billing_column_legacy( $column, $post_id ) {
    if ( 'billing_address' !== $column ) return;
    $order = wc_get_order( $post_id );
    if ( ! $order ) return;
    $url = carno_customer_orders_url( $order, false );
    carno_render_billing_phone_row( $order->get_billing_phone(), $url );
}

add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'carno_billing_phone_in_billing_column_hpos', 20, 2 );
function carno_billing_phone_in_billing_column_hpos( $column, $order ) {
    if ( 'billing_address' !== $column ) return;
    $url = carno_customer_orders_url( $order, true );
    carno_render_billing_phone_row( $order->get_billing_phone(), $url );
}

function carno_customer_orders_url( $order, $hpos ) {
    $user_id = $order->get_customer_id();
    $base    = $hpos ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' );
    if ( $user_id ) {
        return $base . '&_customer_user=' . $user_id;
    }
    return $base . '&s=' . rawurlencode( $order->get_billing_email() );
}

function carno_render_billing_phone_row( $phone, $url ) {
    if ( ! $phone ) return;
    echo '<br><small style="color:#888; display:inline-flex; align-items:center; gap:5px;">';
    echo esc_html( $phone );
    echo ' <a href="' . esc_url( $url ) . '" target="_blank" style="font-size:10px; background:#f0f0f1; border:1px solid #c3c4c7; padding:1px 6px; border-radius:3px; color:#50575e; text-decoration:none; white-space:nowrap;">همه سفارشات</a>';
    echo '</small>';
}
