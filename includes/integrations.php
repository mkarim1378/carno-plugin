<?php
// ============================================================================
// یکپارچه‌سازی‌ها - Elementor، Gravity Forms، Rank Math، Voorodak
// ============================================================================

// نمایش تمپلیت درخواست لایسنس در صفحه مشاهده سفارش
add_action( 'woocommerce_order_details_before_order_table', 'display_elementor_template_before_order_details_table' );
function display_elementor_template_before_order_details_table( $order ) {
    echo do_shortcode( '[elementor-template id="37026"]' );
}

// ============================================================================
// نمایش محتوای سفارش بعد از خرید دوره‌های حضوری یا VIP
function display_custom_order_content($order) {
    if (!$order) return;

    $show_vip_box = $show_form = false;

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        if ($product->is_type('variable')) {
            $variation_id = $item->get_variation_id();
            if ($variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $course_type = $variation->get_attribute('pa_course_type');

                    if ($course_type && strpos(strtolower($course_type), 'حضوری') !== false) {
                        $show_form = true;
                    }
                }
            }
        }

        $variation_id = $item->get_variation_id();
        if ($variation_id == 41078) {
            $show_vip_box = true;
        }

        $product_id = $product->get_id();
        $vip_product_id = 41077;
        $form_product_ids = [13832, 14259];

        if ($product_id == $vip_product_id) $show_vip_box = true;
        if (in_array($product_id, $form_product_ids)) $show_form = true;
    }

    if ($show_vip_box) : ?>
        <style>
        .vip-box {margin:30px auto;padding:25px;border-radius:15px;border:1px solid #e0e0e0;background:#f9f9f9;box-shadow:0 4px 12px rgba(0,0,0,0.08);text-align:center;}
        .vip-box h2 {font-size:22px;margin-bottom:15px;color:#2d2c74;}
        .vip-box p {font-size:16px;margin-bottom:20px;color:#2d2c74;line-height:1.9em;}
        .vip-box .btn-group {display:flex;justify-content:center;gap:15px;flex-wrap:wrap;}
        .vip-box .btn-link {padding:12px 20px;border-radius:8px;text-decoration:none;font-size:16px;font-weight:600;color:#fff;transition:all .3s ease;}
        .vip-box .btn-vip {background:#ed1c24}
        .vip-box .btn-vip:hover {background:#e4571b;}
        </style>
        <div class="vip-box">
            <h2>🎉 خرید شما با موفقیت انجام شد</h2>
            <p>از اینکه به جمع کارآموزان دوره <b>برق، انژکتور و آپشنال خودروهای کره‌ای</b> پیوستید، به شما تبریک می‌گوییم!</p>
            <p>اکنون می‌توانید از طریق لینک‌های زیر به محتوای ویژه آموزشی دسترسی پیدا کنید و در کانال تلگرام دوره عضو شوید.</p>
            <div class="btn-group">
                <a href="https://www.instagram.com/carno_koreancar_vip" target="_blank" class="btn-link btn-vip">ورود به پیج VIP</a>
                <a href="https://t.me/+NvZGndsi6mRjYTM0" target="_blank" class="btn-link btn-vip">عضویت در کانال تلگرام</a>
            </div>
        </div>
    <?php endif;

    if ($show_form) {
        echo '<div class="custom-form" style="margin-top:30px;">';
        echo do_shortcode('[elementor-template id="31944"]');
        echo '</div>';
    }
}

add_action('woocommerce_thankyou', function($order_id) {
    if (!$order_id) return;
    if ($order = wc_get_order($order_id)) display_custom_order_content($order);
}, 5);

add_action('woocommerce_view_order', function($order_id) {
    if (!$order_id) return;
    if ($order = wc_get_order($order_id)) display_custom_order_content($order);
}, 5);

// ============================================================================
// نمایش باکس پشتیبانی کارمپ بعد از خرید محصول 39576
add_action( 'woocommerce_thankyou', 'show_elementor_template_after_specific_product_purchase', 10, 1 );
function show_elementor_template_after_specific_product_purchase( $order_id ) {
    if ( ! $order_id ) return;
    $target_product_id = 39576;
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    foreach ( $order->get_items() as $item ) {
        if ( $item->get_product_id() == $target_product_id ) {
            echo do_shortcode( '[elementor-template id="40177"]' );
            break;
        }
    }
}

// ============================================================================
// Gravity Forms - پر کردن فیلد محصولات خریداری شده
add_filter( 'gform_pre_render_21', 'populate_products_checkbox' );
function populate_products_checkbox( $form ) {
    $user_id = get_current_user_id();
    if ( !$user_id ) {
        return $form;
    }

    $cache_key = 'user_products_' . $user_id;
    $purchased_products = get_transient($cache_key);

    if ($purchased_products === false) {
        $field_id = 8;
        foreach ( $form['fields'] as &$field ) {
            if ( $field->id == $field_id ) {
                $customer_orders = get_posts( array(
                    'numberposts' => 50,
                    'meta_key'    => '_customer_user',
                    'meta_value'  => $user_id,
                    'post_type'   => wc_get_order_types(),
                    'post_status' => 'wc-completed',
                    'orderby'     => 'date',
                    'order'       => 'DESC'
                ) );

                $purchased_products = array();
                foreach ( $customer_orders as $order ) {
                    $order_obj = wc_get_order( $order->ID );
                    if (!$order_obj) continue;

                    $items = $order_obj->get_items();
                    foreach ( $items as $item ) {
                        $product = $item->get_product();
                        if ( $product ) {
                            $purchased_products[ $product->get_id() ] = $product->get_name();
                        }
                    }
                }

                set_transient($cache_key, $purchased_products, 3600);
                break;
            }
        }
    }

    if (!empty($purchased_products)) {
        $field_id = 8;
        foreach ( $form['fields'] as &$field ) {
            if ( $field->id == $field_id ) {
                $choices = array();
                foreach ( $purchased_products as $product_id => $product_name ) {
                    $choices[] = array(
                        'text' => $product_name,
                        'value' => $product_name,
                    );
                }
                $field->choices = $choices;
                break;
            }
        }
    }

    return $form;
}

// Gravity Forms - تغییر متن دکمه بعدی برای فرم 22
add_filter( 'gform_next_button', 'change_next_button_for_specific_form', 10, 2 );
function change_next_button_for_specific_form( $button, $form ) {
    if ( $form['id'] == 22 ) {
        return "<button class='gform_next_button button' id='gform_next_button_{$form['id']}'>دانلود فیلم وبینار</button>";
    }
    return $button;
}

// ============================================================================
// Gravity Forms - ساخت سفارش ووکامرس بعد از پرداخت فرم (فرم‌های 42 و 43)
add_action( 'gform_post_payment_completed', 'carno_create_wc_order_after_payment_v2', 10, 2 );

function carno_create_wc_order_after_payment_v2( $entry, $action ) {

    $online_form_id  = 43;
    $onsite_form_id  = 42;

    $online_fields = array(
        'name'    => 9,
        'phone'   => 8,
        'product' => 15
    );

    $onsite_fields = array(
        'name'    => 9,
        'phone'   => 8,
        'product' => 12
    );

    $current_form_id = rgar( $entry, 'form_id' );
    $target_slug     = '';
    $fields_map      = array();

    if ( $current_form_id == $online_form_id ) {
        $target_slug = 'online-course';
        $fields_map  = $online_fields;
    } elseif ( $current_form_id == $onsite_form_id ) {
        $target_slug = 'onsite-course';
        $fields_map  = $onsite_fields;
    } else {
        return;
    }

    $full_name = rgar( $entry, $fields_map['name'] );
    $raw_phone = rgar( $entry, $fields_map['phone'] );

    $parts = explode( ' ', trim( $full_name ), 2 );
    $first_name = $parts[0];
    $last_name  = isset( $parts[1] ) ? $parts[1] : '';

    $phone = ltrim( $raw_phone, '0' );

    $product_id = rgar( $entry, 'post_id' );

    if ( empty( $product_id ) ) {
        $product_id = url_to_postid( $entry['source_url'] );
    }

    if ( ! $product_id ) return;

    $product = wc_get_product( $product_id );
    $item_id_to_add = $product_id;

    if ( $product->is_type( 'variable' ) ) {

        $variations = $product->get_available_variations();

        foreach ( $variations as $variation ) {
            if ( in_array( $target_slug, $variation['attributes'] ) ) {
                $item_id_to_add = $variation['variation_id'];
                break;
            }
        }
    }

    if ( $item_id_to_add ) {

        $order = wc_create_order();

        $order_item_id = $order->add_product( wc_get_product( $item_id_to_add ), 1 );
        $item = $order->get_item( $order_item_id );
        $paid_amount = 0;
        $product_field_id = $fields_map['product'] . '.2';
        $paid_amount = floatval( rgar( $entry, $product_field_id ) );

        if ( $paid_amount > 0 ) {
            $item->set_subtotal( $paid_amount );
            $item->set_total( $paid_amount );
            $item->save();
        }

        $address = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'phone'      => $phone,
        );
        $order->set_address( $address, 'billing' );

        $order->set_payment_method( 'Gravity Forms Direct' );
        $order->set_payment_method_title( 'پرداخت سریع (آکادمی کارنو)' );

        $order->calculate_totals();

        $order->update_status( 'completed', 'سفارش ثبت شده توسط فرم شماره ' . $current_form_id . ' - شناسه تراکنش: ' . rgar($entry, 'transaction_id') );

        $order->save();
    }
}

// ============================================================================
// Rank Math - شخصی‌سازی breadcrumb برای مقالات
add_filter( 'rank_math/frontend/breadcrumb/items', function( $crumbs, $class ) {
    if ( is_single() && get_post_type() === 'post' ) {
        $crumbs = [
            [ 'آکادمی کارنو', home_url( '/' ) ],
            [ 'مقالات', home_url( '/blog/' ) ],
            [ get_the_title() ]
        ];
    }
    return $crumbs;
}, 10, 2 );

// Rank Math - اصلاح واحد پول به IRR در schema محصولات
add_filter( 'rank_math/snippet/rich_snippet_product_entity', function( $entity ) {
    if ( isset( $entity['offers'] ) ) {

        if ( is_array( $entity['offers'] ) && isset( $entity['offers'][0] ) ) {
            foreach ( $entity['offers'] as &$offer ) {
                if ( isset( $offer['priceCurrency'] ) ) {
                    $offer['priceCurrency'] = 'IRR';
                }
                if ( isset( $offer['price'] ) && is_numeric( $offer['price'] ) ) {
                    $offer['price'] = $offer['price'] * 10;
                }
            }
        }

        if ( is_array( $entity['offers'] ) && isset( $entity['offers']['priceCurrency'] ) ) {
            $entity['offers']['priceCurrency'] = 'IRR';
            if ( isset( $entity['offers']['price'] ) && is_numeric( $entity['offers']['price'] ) ) {
                $entity['offers']['price'] = $entity['offers']['price'] * 10;
            }
        }
    }
    return $entity;
}, 20, 1 );

// ============================================================================
// Voorodak - همسان‌سازی شماره موبایل در billing_phone
function sync_voorodak_phone_to_billing_phone($user_id) {
    if (!$user_id) return;

    $voorodak_phone = get_user_meta($user_id, 'voorodak_phone', true);
    $digits_phone   = get_user_meta($user_id, 'digits_phone', true);
    $billing_phone  = get_user_meta($user_id, 'billing_phone', true);

    if (!empty($voorodak_phone)) {
        update_user_meta($user_id, 'billing_phone', $voorodak_phone);
    } elseif (!empty($digits_phone) && empty($billing_phone)) {
        update_user_meta($user_id, 'billing_phone', $digits_phone);
    }
}
add_action('voorodak_after_do_register', 'sync_voorodak_phone_to_billing_phone', 20);
add_action('voorodak_after_do_login', 'sync_voorodak_phone_to_billing_phone', 20);
add_action('profile_update', 'sync_voorodak_phone_to_billing_phone', 20);
