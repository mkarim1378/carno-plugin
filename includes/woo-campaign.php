<?php
// ============================================================================
// کمپین خرید ویژه (لینک‌های اسپات) - سیستم special_buy
// ============================================================================

// لیست قیمت‌های نهایی محصولات برای کمپین
function get_sepehr_final_prices() {
    $d    = carno_settings_defaults();
    $rows = get_option( 'carno_campaign_prices', $d['campaign_prices'] );
    $out  = [];
    foreach ( $rows as $row ) {
        $out[ (int) $row['pid'] ] = (int) $row['price'];
    }
    return $out;
}

function get_sepehr_redirect_only_ids() {
    return (array) get_option( 'carno_campaign_redirect_ids', [] );
}

// هندل کردن لینک مستقیم خرید (?special_buy=1&pid=X&vid=Y)
add_action('template_redirect', 'handle_direct_purchase_link');
function handle_direct_purchase_link() {
    if ( !isset($_GET['special_buy']) || is_admin() ) return;

    $variation_id = isset($_GET['vid']) ? absint($_GET['vid']) : 0;
    $product_id   = isset($_GET['pid']) ? absint($_GET['pid']) : 0;
    $target_id    = $variation_id > 0 ? $variation_id : $product_id;

    if ( $target_id > 0 ) {
        $redirect_only_list = get_sepehr_redirect_only_ids();

        if ( in_array( $target_id, $redirect_only_list ) ) {
            wp_safe_redirect( get_permalink( $target_id ) );
            exit;
        }

        if ( $_GET['special_buy'] === '1' ) {
            $defined_prices = get_sepehr_final_prices();

            if ( array_key_exists( $target_id, $defined_prices ) ) {
                if ( function_exists('WC') && WC()->cart ) {
                    WC()->cart->empty_cart();

                    $passed_id = $product_id;
                    $passed_vid = 0;
                    $attributes = array();

                    if ( $variation_id > 0 ) {
                        $product_obj = wc_get_product($variation_id);
                        if ( $product_obj && $product_obj->is_type('variation') ) {
                            $passed_vid = $variation_id;
                            $passed_id  = $product_obj->get_parent_id();
                            $attributes = $product_obj->get_variation_attributes();
                        }
                    }

                    $was_added = WC()->cart->add_to_cart( $passed_id, 1, $passed_vid, $attributes, ['is_fixed_price' => true] );

                    if ( $was_added ) {
                        wp_safe_redirect( wc_get_checkout_url() );
                        exit;
                    }
                }
            }
        }
    }
}

// اعمال قیمت ثابت در سبد خرید
add_action( 'woocommerce_before_calculate_totals', 'apply_fixed_price_logic', 20, 1 );
function apply_fixed_price_logic( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    $defined_prices = get_sepehr_final_prices();
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['is_fixed_price'] ) ) {
            $product = $cart_item['data'];
            $current_id = $product->get_id();
            if ( array_key_exists( $current_id, $defined_prices ) ) {
                $product->set_price( $defined_prices[$current_id] );
            }
        }
    }
}

// حفظ فلگ is_fixed_price در session
add_filter( 'woocommerce_get_cart_item_from_session', function( $cart_item, $values ) {
    if ( isset( $values['is_fixed_price'] ) ) {
        $cart_item['is_fixed_price'] = $values['is_fixed_price'];
    }
    return $cart_item;
}, 10, 2 );

// جلوگیری از اعمال کوپن روی محصولات با قیمت ثابت
add_filter( 'woocommerce_coupon_get_discount_amount', 'block_coupons_for_fixed_price', 10, 5 );
function block_coupons_for_fixed_price( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
    if ( isset( $cart_item['is_fixed_price'] ) ) return 0;
    return $discount;
}

// ============================================================================
// ستون و آمار کمپین در پنل مدیریت سفارشات

// ذخیره متادیتای کمپین در سفارش
add_action( 'woocommerce_checkout_create_order', 'carno_save_special_buy_to_order', 10, 2 );
function carno_save_special_buy_to_order( $order, $data ) {
    if ( WC()->cart ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['is_fixed_price'] ) ) {
                $product_id = $cart_item['data']->get_id();
                $order->update_meta_data( '_is_sepehr_special_buy', $product_id );
                break;
            }
        }
    }
}

// اضافه کردن ستون کمپین به مدیریت سفارشات (سازگار با HPOS و قدیمی)
add_filter( 'manage_edit-shop_order_columns', 'carno_add_order_special_column' );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'carno_add_order_special_column' );
function carno_add_order_special_column( $columns ) {
    $columns['special_buy_status'] = 'کمپین ویژه';
    return $columns;
}

// نمایش محتوا در ستون - نسخه قدیمی
add_action( 'manage_shop_order_posts_custom_column', 'carno_display_order_special_column', 10, 2 );
function carno_display_order_special_column( $column, $post_id ) {
    if ( $column === 'special_buy_status' ) {
        $is_special = get_post_meta( $post_id, '_is_sepehr_special_buy', true );
        carno_render_special_label( $is_special );
    }
}

// نمایش محتوا در ستون - HPOS
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'carno_display_order_hpos_special_column', 10, 2 );
function carno_display_order_hpos_special_column( $column, $order ) {
    if ( $column === 'special_buy_status' ) {
        $is_special = $order->get_meta( '_is_sepehr_special_buy' );
        carno_render_special_label( $is_special );
    }
}

// رندر لیبل کمپین
function carno_render_special_label( $is_special ) {
    if ( empty($is_special) ) {
        echo '<span style="color: #ccc;">—</span>';
        return;
    }

    $label = 'لینک‌های اسپات';
    $color = '#555d66';

    echo '<span style="background: ' . $color . '; color: #fff; padding: 6px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; white-space: nowrap;">' . $label . '</span>';
}

// منوی فیلتر بالای جدول سفارشات
add_action( 'restrict_manage_posts', 'carno_filter_orders_by_special_buy_legacy' );
add_action( 'woocommerce_order_list_table_restrict_manage_orders', 'carno_filter_orders_by_special_buy_hpos' );

function carno_filter_orders_by_special_buy_dropdown() {
    $current_v = isset( $_GET['carno_special_filter'] ) ? $_GET['carno_special_filter'] : '';
    ?>
    <select name="carno_special_filter" id="carno_special_filter">
        <option value=""><?php _e( 'همه نوع خریدها', 'woocommerce' ); ?></option>
        <option value="yes" <?php selected( $current_v, 'yes' ); ?>>فقط لینک‌های اسپات</option>
    </select>
    <?php
}

function carno_filter_orders_by_special_buy_legacy() {
    global $typenow;
    if ( 'shop_order' === $typenow ) { carno_filter_orders_by_special_buy_dropdown(); }
}

function carno_filter_orders_by_special_buy_hpos() {
    carno_filter_orders_by_special_buy_dropdown();
}

// اعمال فیلتر روی دیتابیس - نسخه قدیمی
add_action( 'pre_get_posts', 'carno_apply_special_buy_filter_legacy' );
function carno_apply_special_buy_filter_legacy( $query ) {
    global $pagenow;
    if ( is_admin() && 'edit.php' === $pagenow && (isset($_GET['post_type']) && 'shop_order' === $_GET['post_type']) && ! empty( $_GET['carno_special_filter'] ) ) {
        $query->set( 'meta_query', array( array( 'key' => '_is_sepehr_special_buy', 'compare' => 'EXISTS' ) ) );
    }
}

// اعمال فیلتر روی دیتابیس - HPOS
add_filter( 'woocommerce_order_query_args', 'carno_apply_special_buy_filter_hpos' );
function carno_apply_special_buy_filter_hpos( $query_args ) {
    if ( is_admin() && ! empty( $_GET['carno_special_filter'] ) ) {
        $query_args['meta_query'][] = array( 'key' => '_is_sepehr_special_buy', 'compare' => 'EXISTS' );
    }
    return $query_args;
}
