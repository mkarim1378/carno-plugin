<?php
// ============================================================================
// قیمت‌گذاری ووکامرس - تخفیف‌های سبد، پکیج، قیمت متغیر، نمایش رایگان
// ============================================================================

// شورتکد نمایش قیمت وارییشن بر اساس ویژگی‌ها
add_shortcode( 'variation_price_by_attr', function( $atts ) {
    global $product;
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        $product_id = get_the_ID();
        $product    = wc_get_product( $product_id );
    }
    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return '';
    }
    $attributes = $atts;
    if ( empty( $attributes ) ) return '';
    $data_store   = WC_Data_Store::load( 'product' );
    $variation_id = $data_store->find_matching_product_variation( $product, $attributes );
    if ( $variation_id ) {
        $variation = wc_get_product( $variation_id );
        if ( $variation ) {
            return $variation->get_price_html();
        }
    }
    return '';
});

// شورتکد نمایش قیمت وارییشن خاص بر اساس ID
function show_variation_price_by_id( $atts ) {
    $atts = shortcode_atts( array(
        'id' => '',
    ), $atts );

    if( empty( $atts['id'] ) ) return '';

    $variation = wc_get_product( $atts['id'] );

    if( $variation && $variation->is_type( 'variation' ) ) {
        return $variation->get_price_html();
    }

    return '';
}
add_shortcode( 'variation_price', 'show_variation_price_by_id' );

// ============================================================================
// تخفیف ویژه از طریق session (پارامتر ?special=1)
function carno_store_special_discount_flag_in_session() {
    if ( isset( $_GET['special'] ) ) {
        if ( $_GET['special'] === '1' ) {
            if ( function_exists('WC') && WC()->session ) {
                WC()->session->set( 'carno_special_discount_active', true );
            }
        } elseif ( $_GET['special'] === '0' ) {
            if ( function_exists('WC') && WC()->session ) {
                WC()->session->set( 'carno_special_discount_active', false );
            }
        }
    }
}
add_action( 'init', 'carno_store_special_discount_flag_in_session' );

// اعمال تخفیف ثابت برای محصولات خاص (از طریق session)
function carno_apply_fixed_discount_for_specific_product( $cart ) {
    if ( is_admin() && ! defined('DOING_AJAX') ) {
        return;
    }

    if ( ! function_exists('WC') || ! WC()->session ) {
        return;
    }

    $active = WC()->session->get( 'carno_special_discount_active' );
    if ( ! $active ) {
        return;
    }

    $discounts = [
        13928 => [
            'amount' => 2020000,
            'label'  => 'تخفیف ویژه خریداران GDS'
        ],
        13534 => [
            'amount' => 1020000,
            'label'  => 'تخفیف ویژه دریافت کنندگان چک لیست پذیرش'
        ],
        38427 => [
            'amount' => 6600000,
            'label'  => 'تخفیف ویژه دریافت کنندگان چک لیست پذیرش'
        ],
    ];

    $cart_items = $cart->get_cart();
    $existing_fees = $cart->get_fees();
    $existing_fee_names = array();

    foreach ($existing_fees as $fee) {
        if (isset($fee->name)) {
            $existing_fee_names[] = $fee->name;
        }
    }

    foreach ( $cart_items as $cart_item ) {
        $product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0
            ? (int) $cart_item['variation_id']
            : (int) $cart_item['product_id'];

        if ( array_key_exists( $product_id, $discounts ) ) {
            $fee_label = $discounts[$product_id]['label'];

            if ( ! in_array( $fee_label, $existing_fee_names ) ) {
                $cart->add_fee(
                    __( $fee_label, 'carno' ),
                    -1 * (float) $discounts[$product_id]['amount']
                );
                $existing_fee_names[] = $fee_label;
            }
        }
    }
}
add_action( 'woocommerce_cart_calculate_fees', 'carno_apply_fixed_discount_for_specific_product', 99 );

// ============================================================================
// ذخیره درصد تخفیف در متای محصول
add_action( 'woocommerce_process_product_meta', 'save_discount_percentage_meta' );
function save_discount_percentage_meta( $post_id ) {
    $product = wc_get_product( $post_id );
    $regular_price = (float) $product->get_regular_price();
    $sale_price = (float) $product->get_sale_price();

    if ( $sale_price && $regular_price && $regular_price > $sale_price ) {
        $discount_percentage = ( ( $regular_price - $sale_price ) / $regular_price ) * 100;
        $discount_percentage = round( $discount_percentage );
        update_post_meta( $post_id, '_discount_percentage', $discount_percentage );
    } else {
        if ( get_post_meta( $post_id, '_discount_percentage', true ) ) {
            delete_post_meta( $post_id, '_discount_percentage' );
        }
    }
}

// ============================================================================
// تخفیف پکیج زبان فنی (محصولات 16180 + 13534)
add_action( 'woocommerce_cart_calculate_fees', 'custom_package_fixed_price', 20, 1 );
function custom_package_fixed_price( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $required_products = array( 16180, 13534 );
    $found_products = array();
    $package_total = 0;
    $final_price = 5000000;

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( in_array( $cart_item['product_id'], $required_products ) ) {
            $found_products[] = $cart_item['product_id'];
            $package_total += $cart_item['line_total'];
        }
    }

    if ( count( array_unique( $found_products ) ) === count( $required_products ) ) {
        $discount = $package_total - $final_price;
        if ( $discount > 0 ) {
            $cart->add_fee( 'تخفیف پکیج زبان فنی', -$discount );
        }
    }
}

// ============================================================================
// پنهان کردن قیمت تخفیف‌دار در ساعت ۱۶ الی ۱۷ (تهران)
add_filter('woocommerce_product_get_price', 'carno_dynamic_fixed_price', 99, 2);
add_filter('woocommerce_product_variation_get_price', 'carno_dynamic_fixed_price', 99, 2);
function carno_dynamic_fixed_price($price, $product) {
    if (is_admin()) return $price;
    date_default_timezone_set('Asia/Tehran');
    $current_hour = (int)date('G');
    if ($current_hour == 16) {
        return $product->get_regular_price();
    }
    return $price;
}

// پنهان کردن لیبل "حراج" در ساعت ۱۶ الی ۱۷
add_filter('woocommerce_product_is_on_sale', 'carno_hide_sale_flash', 99, 2);
function carno_hide_sale_flash($is_on_sale, $product) {
    date_default_timezone_set('Asia/Tehran');
    $current_hour = (int)date('G');
    if ($current_hour == 16) {
        return false;
    }
    return $is_on_sale;
}

// مخفی کردن ویجت تایمر در ساعت ۱۶ الی ۱۷
add_action('wp_head', 'carno_hide_timer_css');
function carno_hide_timer_css() {
    date_default_timezone_set('Asia/Tehran');
    $current_hour = (int)date('G');
    if ($current_hour == 16) {
        echo '<style>.daily-timer { display: none !important; }</style>';
    }
}

// ============================================================================
// نمایش کلمه "رایگان" به جای قیمت ۰ تومان
add_filter( 'woocommerce_get_price_html', 'carno_show_free_when_zero_price', 10, 2 );
function carno_show_free_when_zero_price( $price, $product ) {

    $regular_price = $product->get_regular_price();

    if ( $regular_price !== '' && floatval($regular_price) === 0.0 ) {
        return '<span class="price free-price">💥رایگان💥</span>';
    }

    return $price;
}
