<?php

/*
Plugin Name:  Carno Customization Plugin
Plugin URI:   https://sepehralimohammadi.com/
Description:  این افزونه جهت اعمال شخصی سازی های مورد نیاز بر روی وبسایت مهندس سپهر علیمحمدی توسعه داده شده است. لطفا از غیرفعال کردن این افزونه خودداری فرمایید!
Version:      1.14.0
Author:       سپهر علیمحمدی
Author URI:   https://sepehralimohammadi.com/
*/

// Read time shortcode for WordPress
// Usage: [read_time] or [read_time wpm="220" label="%s دقیقه" icon="1"]

if ( ! function_exists( 'kar_read_time_shortcode' ) ) {

    function kar_read_time_shortcode( $atts ) {
        // Default attributes
        $atts = shortcode_atts( array(
            'wpm'   => 220,
            'label' => '%s دقیقه',
            'class' => 'read-time',
            'icon'  => '1',
            'min'   => 1,
        ), $atts, 'read_time' );

        // Ensure we have global post
        global $post;
        if ( empty( $post ) || ! isset( $post->ID ) ) {
            return '';
        }
        $post_id = (int) $post->ID;

        // 1) Manual override via custom field 'read_time_manual'
        $manual = get_post_meta( $post_id, 'read_time_manual', true );
        if ( $manual !== '' && is_numeric( $manual ) ) {
            $minutes = (int) $manual;
        } else {
            // 2) Cache keys
            $cache_key_time  = '_kar_read_time_cached_time';
            $cache_key_value = '_kar_read_time_cached_value';
            $cached_time     = get_post_meta( $post_id, $cache_key_time, true );
            $cached_value    = get_post_meta( $post_id, $cache_key_value, true );
            $post_mod_time   = get_post_field( 'post_modified_gmt', $post_id );

            if ( $cached_value !== '' && $cached_time === $post_mod_time ) {
                $minutes = (int) $cached_value;
            } else {
                // 3) Calculate word count safely
                $content = isset( $post->post_content ) ? $post->post_content : '';
                $content = strip_shortcodes( $content );
                $content = wp_strip_all_tags( $content );
                $content = trim( preg_replace( '/\s+/u', ' ', $content ) );

                if ( $content === '' ) {
                    $word_count = 0;
                } else {
                    // split into words. use preg_split which is robust for multibyte
                    $words = preg_split( '/\s+/u', $content );
                    if ( is_array( $words ) ) {
                        // filter out empty strings using 'strlen' to avoid closures (compatibility)
                        $filtered = array_filter( $words, 'strlen' );
                        $word_count = count( $filtered );
                    } else {
                        $word_count = 0;
                    }
                }

                $wpm = intval( $atts['wpm'] );
                if ( $wpm <= 0 ) {
                    $wpm = 220;
                } elseif ( $wpm < 50 ) {
                    $wpm = 50; // safeguard
                }

                $minutes = (int) ceil( $word_count / $wpm );
                if ( $minutes < intval( $atts['min'] ) ) {
                    $minutes = intval( $atts['min'] );
                }

                // store cache
                update_post_meta( $post_id, $cache_key_value, $minutes );
                update_post_meta( $post_id, $cache_key_time, $post_mod_time );
            }
        }

        // Prepare label text
        if ( intval( $minutes ) === 0 ) {
            $label_text = 'کمتر از یک دقیقه';
        } else {
            // Use number_format_i18n if available
            if ( function_exists( 'number_format_i18n' ) ) {
                $num = number_format_i18n( $minutes );
            } else {
                $num = $minutes;
            }
            $label_text = sprintf( $atts['label'], $num );
        }

        // Icon HTML (inline SVG)
        $icon_html = '';
        if ( intval( $atts['icon'] ) ) {
            $icon_html = '<span class="read-time-icon" aria-hidden="true" style="display:inline-flex;align-items:center;margin-inline-end:6px;">'
                       . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false" role="img">'
                       . '<path d="M12 7V12L15 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>'
                       . '<path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>'
                       . '</svg></span>';
        }

        $aria_label = esc_attr( "زمان تقریبی مطالعه: {$minutes} دقیقه" );

        $html = sprintf(
            '<span class="%s" role="text" aria-label="%s" title="%s">%s<span class="read-time-text">%s</span></span>',
            esc_attr( $atts['class'] ),
            $aria_label,
            esc_attr( $label_text ),
            $icon_html,
            esc_html( $label_text )
        );

        return $html;
    }

    add_shortcode( 'read_time', 'kar_read_time_shortcode' );
}


// جداسازی قیمت متغیر محصولات
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

// Shortcode برای نمایش قیمت یک وارییشن خاص بر اساس ID
function show_variation_price_by_id( $atts ) {
    $atts = shortcode_atts( array(
        'id' => '',
    ), $atts );

    if( empty( $atts['id'] ) ) return '';

    $variation = wc_get_product( $atts['id'] );

    if( $variation && $variation->is_type( 'variation' ) ) {
        return $variation->get_price_html(); // یا get_regular_price() / get_sale_price()
    }

    return '';
}
add_shortcode( 'variation_price', 'show_variation_price_by_id' );


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

    // محصولات با مبلغ تخفیف و لیبل اختصاصی
    $discounts = [
        13928 => [
            'amount' => 2020000,
            'label'  => 'تخفیف ویژه خریداران GDS'
        ],
        33429 => [
            'amount' => 1020000,
            'label'  => 'تخفیف ویژه'
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

    // بهینه‌سازی: بررسی فقط یک بار و ذخیره نتیجه
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

            // بررسی سریع‌تر با استفاده از آرایه
            if ( ! in_array( $fee_label, $existing_fee_names ) ) {
                $cart->add_fee(
                    __( $fee_label, 'carno' ),
                    -1 * (float) $discounts[$product_id]['amount']
                );
                $existing_fee_names[] = $fee_label; // اضافه کردن به لیست برای جلوگیری از تکرار
            }
        }
    }
}
add_action( 'woocommerce_cart_calculate_fees', 'carno_apply_fixed_discount_for_specific_product', 99 );




// بررسی وجود کاربر بر اساس شماره تلفن
function user_exists_by_phone($phone) {
    $normalized_phone = substr(preg_replace('/[^0-9]/', '', $phone), -10);
    if (strlen($normalized_phone) < 10) {
        return false;
    }

    // کش کردن نتیجه جستجو
    $cache_key = 'user_phone_' . md5($normalized_phone);
    $cached_user_id = get_transient($cache_key);
    
    if ($cached_user_id !== false) {
        return $cached_user_id;
    }

    // چک روی username
    $user = get_user_by('login', '0' . $normalized_phone);
    if (!$user) {
        $user = get_user_by('login', $normalized_phone);
    }
    if ($user) {
        set_transient($cache_key, $user->ID, 1800); // کش برای 30 دقیقه
        return $user->ID;
    }

    // چک روی usermeta
    $user_query = new WP_User_Query([
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'billing_phone', 'value' => $normalized_phone, 'compare' => 'LIKE'],
            ['key' => 'digits_phone_no', 'value' => $normalized_phone, 'compare' => 'LIKE'],
        ],
        'number' => 1 // محدود کردن به یک نتیجه
    ]);

    if (!empty($user_query->results)) {
        $user_id = $user_query->results[0]->ID;
        set_transient($cache_key, $user_id, 1800); // کش برای 30 دقیقه
        return $user_id;
    }

    set_transient($cache_key, false, 1800); // کش نتیجه منفی
    return false;
}

// ساخت کاربر از سفارش مهمان
function create_user_from_guest_order_by_phone_v2($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    // تغییر مهم: customer_id خالی یا صفر هم مهمان محسوب میشه
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
        // وصل کردن سفارش به کاربر موجود
        $order->set_customer_id($existing_user_id);
        update_post_meta($order_id, '_customer_user', $existing_user_id);
        $order->add_order_note('این سفارش به کاربر موجود با شماره تلفن متصل شد.');
        $order->save();

        echo "<p style='color:blue;'>اطلاع: سفارش #{$order_id} به کاربر موجود (ID: {$existing_user_id}) وصل شد.</p>";
        return;
    }

    // ساخت کاربر جدید
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
        echo "<p style='color:red;'>خطا در ساخت کاربر برای سفارش #{$order_id}: " . $user_id->get_error_message() . "</p>";
        return;
    }

    if (!empty($first_name)) update_user_meta($user_id, 'first_name', $first_name);
    if (!empty($last_name)) update_user_meta($user_id, 'last_name', $last_name);
    wp_update_user(['ID' => $user_id, 'display_name' => trim($first_name . ' ' . $last_name)]);
    
    $order->set_customer_id($user_id);
    update_post_meta($order_id, '_customer_user', $user_id);
    $order->add_order_note('یک حساب کاربری جدید به صورت خودکار برای این مشتری مهمان ایجاد شد و سفارش به آن متصل گردید.');
    $order->save();

    echo "<p style='color:green;'>موفقیت: کاربر جدید '{$first_name} {$last_name}' (شناسه: {$user_id}) برای سفارش #{$order_id} ساخته شد.</p>";
}

add_action('woocommerce_new_order', 'create_user_from_guest_order_by_phone_v2');
add_action('woocommerce_order_status_processing', 'create_user_from_guest_order_by_phone_v2');
add_action('woocommerce_order_status_completed', 'create_user_from_guest_order_by_phone_v2');

// ==========================================================================
$request_uri = $_SERVER['REQUEST_URI'];
// if URL ends with .html then 410
add_action('template_redirect', function () {
    if (preg_match('/\.html$/i', $_SERVER['REQUEST_URI'])) {
        status_header(410);
        nocache_headers();
        exit;
    }
});
// ==========================================================================
// نمایش تمپلیت درخواست لایسنس در صفحه مشاهده سفارش، قبل از جدول اطلاعات سفارش
add_action( 'woocommerce_order_details_before_order_table', 'display_elementor_template_before_order_details_table' );
function display_elementor_template_before_order_details_table( $order ) {
    echo do_shortcode( '[elementor-template id="37026"]' );
}
// رندر نام محصولات در فیلد انتخابی فرم درخواست لایسنس گرویتی فرم
add_filter( 'gform_pre_render_21', 'populate_products_checkbox' );
function populate_products_checkbox( $form ) {
    $user_id = get_current_user_id();
    if ( !$user_id ) {
        return $form;
    }
    
    // کش کردن محصولات خریداری شده
    $cache_key = 'user_products_' . $user_id;
    $purchased_products = get_transient($cache_key);
    
    if ($purchased_products === false) {
        $field_id = 8;
        foreach ( $form['fields'] as &$field ) {
            if ( $field->id == $field_id ) {
                // بهینه‌سازی کوئری با محدود کردن تعداد نتایج
                $customer_orders = get_posts( array(
                    'numberposts' => 50, // محدود کردن به 50 سفارش آخر
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
                
                // کش کردن برای 1 ساعت
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
// ==========================================================================
// بارگذاری اسکریپت‌های آنالیتیکس به صورت بهینه
add_action('wp_head', function() {
    // فقط در صفحات مهم و نه در صفحات ادمین
    if (!is_admin() && (is_front_page() || is_product() || is_shop() || is_cart() || is_checkout() || is_page())) {
        ?>
        <!-- Microsoft Clarity -->
        <script type="text/javascript">
            (function(c,l,a,r,i,t,y){
                c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
            })(window, document, "clarity", "script", "oa9a86dfw7");
        </script>
        
        <!-- Yektanet Analytics (فقط در صفحات تجاری) -->
        <?php if (is_front_page() || is_product() || is_shop() || is_cart() || is_checkout()): ?>
        <script>
            (function (t, e, n) {
                t.yektanetAnalyticsObject = n;
                t[n] = t[n] || function () {
                    t[n].q.push(arguments);
                };
                t[n].q = t[n].q || [];
                var a = new Date(),
                    r = a.getFullYear().toString() + "0" + a.getMonth() + "0" + a.getDate() + "0" + a.getHours(),
                    c = e.getElementsByTagName("script")[0],
                    s = e.createElement("script");
                s.id = "ua-script-oJRDjt94";
                s.dataset.analyticsobject = n;
                s.async = true;
                s.type = "text/javascript";
                s.src = "https://cdn.yektanet.com/rg_woebegone/scripts_v3/oJRDjt94/rg.complete.js?v=" + r;
                c.parentNode.insertBefore(s, c);
            })(window, document, "yektanet");
        </script>
        <?php endif; ?>
        <?php
    }
});


// Shortcode to display inventory progress bar with countdown
function nias_inventory_progress_bar_with_timer($atts) {
    $atts = shortcode_atts(array(
        'product_id' => get_the_ID(),
        'end_time'   => '', // مثل "2025-08-20 23:59:59"
    ), $atts, 'nias_inventory_progress_bar');

    $product = wc_get_product($atts['product_id']);
    if (!$product) return '';

    // دریافت موجودی اولیه
    $total_stock = get_post_meta($product->get_id(), '_original_stock', true);
    if (!$total_stock) {
        $total_stock = $product->get_stock_quantity();
        update_post_meta($product->get_id(), '_original_stock', $total_stock);
    }

    $current_stock = $product->get_stock_quantity();
    $sold_stock    = max(0, $total_stock - $current_stock);

    // محاسبه درصد پر شدن (بر اساس فروش رفته)
    $percentage = $total_stock > 0 ? round(($sold_stock / $total_stock) * 100) : 0;
    $percentage = min(100, $percentage);

    // ظرفیت باقی مانده
    $remaining = $current_stock;

    ob_start(); ?>
    <div class="niasbar-container">
        <div class="niasbar-progress">
            <div class="niasbar-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
        </div>
        <div class="niasbar-footer">
            <span class="niasbar-remaining">ظرفیت: <?php echo esc_html($remaining); ?></span>
            <span class="niasbar-timer" data-endtime="<?php echo esc_attr($atts['end_time']); ?>"></span>
        </div>
    </div>

    <style>
        .niasbar-container {
            font-family: inherit;
        }
        .niasbar-progress {
            width: 100%;
            height: 6px; /* خیلی باریک */
            background: #eee;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        .niasbar-fill {
            height: 100%;
            background: linear-gradient(90deg, #ed1c24, #ff0000);
            transition: width 0.5s ease-in-out;
            float: left;
        }
        .niasbar-footer {
            margin-top: 6px;
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #333;
        }
        .niasbar-remaining {
            font-weight: bold;
        }
        .niasbar-timer {
            color: #d00;
            font-weight: bold;
        }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const timers = document.querySelectorAll(".niasbar-timer");
        timers.forEach(timer => {
            const endTime = timer.dataset.endtime;
            if (!endTime) return;

            function updateTimer() {
                const now = new Date().getTime();
                const countDownDate = new Date(endTime).getTime();
                const distance = countDownDate - now;

                if (distance <= 0) {
                    timer.textContent = "زمان به پایان رسید";
                    return;
                }

                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                timer.textContent = `${hours.toString().padStart(2,'0')}:${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;
            }

            updateTimer();
            setInterval(updateTimer, 1000);
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('nias_inventory_progress_bar', 'nias_inventory_progress_bar_with_timer');

// ذخیره موجودی اولیه فقط بار اول
function nias_save_original_stock($post_id) {
    $product = wc_get_product($post_id);
    if ($product && !get_post_meta($post_id, '_original_stock', true)) {
        update_post_meta($post_id, '_original_stock', $product->get_stock_quantity());
    }
}
add_action('woocommerce_new_product', 'nias_save_original_stock');


//=============================================================================




// =============================================================================================================
// اتصال سفارشات مهمان به حساب کاربری بعد از لاگین
function connect_guest_orders_by_phone_to_user_account($user_id) {
    // 1. اطمینان از معتبر بودن شناسه کاربر
    if ( !$user_id ) {
        return;
    }

    // 2. دریافت شماره موبایل کاربر از اطلاعات کاربری (user meta)
    $user_phone = get_user_meta($user_id, 'billing_phone', true);

    // اگر شماره تلفن اصلی کاربر در فیلد دیگری بود، این خط را فعال کنید و کلید را جایگزین کنید
    // if ( empty($user_phone) ) {
    //     $user_phone = get_user_meta($user_id, 'digits_phone_no', true); // مثال برای افزونه دیجیتس
    // }

    if ( empty($user_phone) ) {
        return;
    }

    // *** تغییر جدید: نرمال‌سازی شماره تلفن ***
    // فقط 9 رقم آخر شماره را برای مقایسه نگه می‌داریم تا از مشکلات فرمت (با صفر یا بی‌صفر، با کد کشور و...) جلوگیری شود
    $normalized_phone = substr($user_phone, -9);
    if ( empty($normalized_phone) ) {
        return;
    }


    // 3. جستجو برای یافتن سفارشات مهمان که شماره تلفن آن‌ها شامل شماره نرمال‌شده ما باشد
    $guest_orders = wc_get_orders(array(
        'limit'        => -1,
        'customer_id'  => 0,
        'meta_key'     => '_billing_phone',
        // *** تغییر جدید: استفاده از LIKE برای مقایسه ***
        'meta_value'   => '%' . $normalized_phone, // علامت % یعنی هر کاراکتری قبل از این 9 رقم می‌تواند وجود داشته باشد
        'meta_compare' => 'LIKE',
    ));

    // 4. اگر سفارشی پیدا شد، آن را به کاربر فعلی متصل کن
    if ( $guest_orders ) {
        foreach ( $guest_orders as $order ) {
            $order_id = $order->get_id();
            
            // اتصال سفارش به کاربر با آپدیت کردن متا دیتا
            update_post_meta($order_id, '_customer_user', $user_id);
            $order->set_customer_id($user_id);
            
            // اضافه کردن یک یادداشت به سفارش برای ثبت این عملیاتب
            $order->add_order_note(
                sprintf(
                    'این سفارش به صورت خودکار به کاربر با شناسه %d متصل شد (بر اساس تطبیق شماره تلفن).',
                    $user_id
                )
            );
            
            // ذخیره تغییرات
            $order->save();
        }
    }
}

// 5. اتصال تابع بالا به هر دو هوک ورود و ثبت نام افزونه ورودک
add_action('voorodak_after_do_login', 'connect_guest_orders_by_phone_to_user_account', 10, 1);
add_action('voorodak_after_do_register', 'connect_guest_orders_by_phone_to_user_account', 10, 1);

// =============================================================================================================

function mk_merged_comments_shortcode( $atts ) {

    // دریافت ids از شورتکد
    $atts = shortcode_atts( [
        'ids' => ''
    ], $atts );

    if ( ! empty( $atts['ids'] ) ) {
        $source_page_ids = array_map( 'intval', explode( ',', $atts['ids'] ) );
        $show_comment_form = false;
    } else {
        $source_page_ids = [ get_the_ID() ];
        $show_comment_form = true;
    }

    $args = [
        'post__in' => $source_page_ids,
        'status'   => 'approve',
        'orderby'  => 'comment_date',
        'order'    => 'DESC',
    ];

    $comments = get_comments( $args );

    ob_start();

    // ✅ Walker اختصاصی برای حذف لینک‌ها و ریپلای
    class No_Link_Comment_Walker extends Walker_Comment {
        protected function comment( $comment, $depth, $args ) {
            $tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
            ?>
            <<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( $this->has_children ? 'parent' : '', $comment ); ?>>
                <article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
                    <footer class="comment-meta">
                        <div class="comment-author vcard">
                            <?php echo get_avatar( $comment, 48 ); ?>
                            <b class="fn"><?php echo esc_html( get_comment_author( $comment ) ); ?></b>
                        </div>
                        <div class="comment-metadata">
                            <span class="comment-date"><?php echo get_comment_date( '', $comment ); ?></span>
                        </div>
                    </footer>

                    <div class="comment-content">
                        <?php comment_text(); ?>
                    </div>
                </article>
            <?php
        }

        protected function comment_reply_link( $comment, $depth, $args ) {
            // ❌ حذف لینک ریپلای
        }
    }

    if ( $comments ) {
        echo '<div id="comments" class="comments-area">';
        wp_list_comments( [
            'echo'   => true,
            'per_page' => 0,
            'walker' => new No_Link_Comment_Walker(), // ✅ استفاده از Walker بدون لینک
            'style' => 'ul',
        ], $comments );
        echo '</div>';
    } else {
        echo '<p class="no-comments">در حال حاضر دیدگاهی برای نمایش وجود ندارد.</p>';
    }

    if ( $show_comment_form ) {
        comment_form();
    }

    return ob_get_clean();
}

add_shortcode( 'my_merged_comments', 'mk_merged_comments_shortcode' );

// =============================================================================================================
// محاسبه و ذخیره درصد تخفیف برای محصولات تخفیف دار
add_action( 'woocommerce_process_product_meta', 'save_discount_percentage_meta' );
function save_discount_percentage_meta( $post_id ) {
    $product = wc_get_product( $post_id );
    $regular_price = (float) $product->get_regular_price();
    $sale_price = (float) $product->get_sale_price();

    // محاسبه درصد تخفیف اگر محصول دارای تخفیف است
    if ( $sale_price && $regular_price && $regular_price > $sale_price ) {
        $discount_percentage = ( ( $regular_price - $sale_price ) / $regular_price ) * 100;
        $discount_percentage = round( $discount_percentage );
        update_post_meta( $post_id, '_discount_percentage', $discount_percentage );
    } else {
        // فقط در صورتی که متا داده تخفیف قبلاً وجود داشته باشد، آن را حذف می‌کنیم
        if ( get_post_meta( $post_id, '_discount_percentage', true ) ) {
            delete_post_meta( $post_id, '_discount_percentage' );
        }
    }
}

// =============================================================================================================
// تغییر نام نمایشی پس از ثبت‌نام یا ویرایش پروفایل

add_action( 'profile_update', 'mk_format_user_display_name_on_profile_update', 10, 2 );

function mk_format_user_display_name_on_profile_update( $user_id, $old_user_data ) {
    mk_update_user_display_name( $user_id );
}

// تابع مشترک برای بروزرسانی display_name
function mk_update_user_display_name( $user_id ) {
    $first_name = get_user_meta( $user_id, 'first_name', true );
    $last_name = get_user_meta( $user_id, 'last_name', true );
    $full_name = trim( $first_name . ' ' . $last_name );
    
    if ( ! empty( $full_name ) ) {
        // بروزرسانی display_name
        $user = get_userdata( $user_id );
        if ( $user->display_name !== $full_name ) {
            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => $full_name,
            ) );
        }
    }
}

// =============================================================================================================
// Optimizing Loading Speed by removing extra connections
function TextHasString($text, $string) {
	return strpos($text, $string) !== false;
}
function BlockExternalHostRequests ($false, $parsed_args, $url) {
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

add_filter('pre_http_request', 'BlockExternalHostRequests', 10, 3);
add_filter( 'use_block_editor_for_post', '__return_false' );
add_filter( 'use_widgets_block_editor', '__return_false' );
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'global-styles' );
} );

add_action( 'wp_enqueue_scripts', 'dequeue_woocommerce_cart_fragments', 11);

function dequeue_woocommerce_cart_fragments() {
    if (is_front_page()) wp_dequeue_script('wc-cart-fragments');
}

// =============================================================================================================
// غیر فعال کردن آپدیت ترجمه ها
add_filter( 'auto_update_translation', '__return_false' );
add_filter( 'async_update_translation', '__return_false' );

// =============================================================================================================
// تغییر فیلدهای صورتحساب - پیش‌فرض: فقط نام و موبایل، در صورت وجود محصول 13534: آدرس و کد پستی اضافه می‌شود

function customize_checkout_fields($fields) {
    // بررسی وجود محصول با آیدی 13534 در سبد خرید
    $has_product_13534 = false;
    if (WC()->cart && !WC()->cart->is_empty()) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['product_id']) && $cart_item['product_id'] == 13534) {
                $has_product_13534 = true;
                break;
            }
        }
    }

    // حذف همه فیلدها غیر از نام و موبایل
    unset($fields['billing']['billing_first_name']);
    unset($fields['billing']['billing_last_name']);
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_1']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_email']);

    // اضافه کردن فیلد نام کامل
    $fields['billing']['billing_full_name'] = array(
        'label'    => 'نام و نام خانوادگی',
        'required' => true,
        'class'    => array('form-row-wide'),
        'priority' => 10,
    );

    // اضافه کردن فیلدهای آدرس فقط در صورت وجود محصول 13534
    if ($has_product_13534) {
        $fields['billing']['billing_address_1'] = array(
            'label'       => 'آدرس پستی',
            'required'    => true,
            'class'       => array('form-row-wide'),
            'priority'    => 50,
        );
        
        $fields['billing']['billing_postcode'] = array(
            'label'       => 'کد پستی',
            'required'    => true,
            'class'       => array('form-row-wide'),
            'priority'    => 60,
        );
    }

    return $fields;
}
add_filter('woocommerce_checkout_fields', 'customize_checkout_fields');

// تبدیل نام کامل به نام و نام خانوادگی هنگام ذخیره سفارش
function split_full_name_before_save($posted_data) {
    if (isset($posted_data['billing_full_name'])) {
        $full_name = trim($posted_data['billing_full_name']);
        $name_parts = explode(' ', $full_name);
        
        if (count($name_parts) > 1) {
            $last_name = array_pop($name_parts);
            $first_name = implode(' ', $name_parts);
        } else {
            $first_name = $full_name;
            $last_name = '';
        }
        
        $posted_data['billing_first_name'] = $first_name;
        $posted_data['billing_last_name'] = $last_name;
    }
    return $posted_data;
}
add_filter('woocommerce_checkout_posted_data', 'split_full_name_before_save');

// نمایش نام کامل در فرم در صورت ویرایش سفارش
function populate_full_name_field($value, $input) {
    if ($input === 'billing_full_name') {
        $customer = WC()->customer;
        if ($customer) {
            return trim($customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name());
        }
    }
    return $value;
}
add_filter('woocommerce_checkout_get_value', 'populate_full_name_field', 10, 2);

// =============================================================================================================
// Generate User Comments Count Meta

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

// =============================================================================================================
// Persistent Login for Users
add_filter('auth_cookie_expiration', 'keep_user_logged_in_for_1_year');

function keep_user_logged_in_for_1_year($expirein) {
    return 31556926; // 1 year in seconds
}

// =============================================================================================================
// Table of Contents 
function carno_generate_toc($atts) {
    global $post;

        // همه h2 ها
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/', $post->post_content, $matches, PREG_SET_ORDER);

        if (!empty($matches)) {
            $list_icon_svg = '<svg class="carno-icon" style="margin-left:10px; vertical-align: middle;" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 19.5H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 12.5H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 5.5H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 5.5L4 6.5L7 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 12.5L4 13.5L7 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 19.5L4 20.5L7 17.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            
            $arrow_icon_svg = '<svg class="carno-icon" style="vertical-align: middle;" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17.9999 12.0001V14.6701C17.9999 17.9801 15.6499 19.3401 12.7799 17.6801L10.4699 16.3401L8.15995 15.0001C5.28995 13.3401 5.28995 10.6301 8.15995 8.97005L10.4699 7.63005L12.7799 6.29005C15.6499 4.66005 17.9999 6.01005 17.9999 9.33005V12.0001Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/></svg>';

            $headings_html = '<div class="carno_headings"><div>' . $list_icon_svg . ' فهرست مطالب</div><nav><ul>';

            foreach ($matches as $index => $heading) {
                $heading_text = esc_html(strip_tags($heading[1]));
                $heading_id = 'carno_heading-' . $index;
                $headings_html .= '<li class="carno_toc-h2"><a href="#' . $heading_id . '">' . $arrow_icon_svg . ' ' . $heading_text . '</a></li>';
            }

            $headings_html .= '</ul></nav></div>';
            return $headings_html;
        }
    return '';
}
add_shortcode('carno_toc', 'carno_generate_toc');

// تزریق ID به هدینگ‌ها موقع رندر شدن محتوا
function carno_add_heading_ids($content) {
    if (is_single() && get_post_type() === 'post') {
        $i = 0;
        $content = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/', function($matches) use (&$i) {
            $id = 'carno_heading-' . $i++;
            return '<h2 id="' . $id . '"' . $matches[1] . '>' . $matches[2] . '</h2>';
        }, $content);
    }
    return $content;
}
add_filter('the_content', 'carno_add_heading_ids');


// =============================================================================================================
// رهگیری بازدید در هنگام باز شدن صفحه
function increment_post_views($postID) {
    $views = get_post_meta($postID, 'post_views', true);

    if (!$views) {
        $views = 0;
    }

    $views++;
    update_post_meta($postID, 'post_views', $views);
}

function track_post_views() {
    // فقط برای کاربران غیر لاگین و صفحات مهم
    if (!is_user_logged_in() && (is_single() || is_page())) {
        $postID = get_the_ID();
        if ($postID) {
            increment_post_views($postID);
        }
    }
}
// اجرا در wp_footer به جای wp_head برای کاهش تأثیر روی سرعت بارگذاری
add_action('wp_footer', 'track_post_views');

// =============================================================================================================
// Detect User IP in Woo Checkout Page

function isUserFromIran() {
    // کش کردن نتیجه برای جلوگیری از درخواست‌های مکرر
    $userIP = $_SERVER['REMOTE_ADDR'];
    $cache_key = 'iran_check_' . md5($userIP);
    $cached_result = get_transient($cache_key);
    
    if ($cached_result !== false) {
        return $cached_result;
    }

    // ارسال درخواست به سرویس موقعیت‌یابی
    $ch = curl_init();
    $geolocationAPI = "http://ip-api.com/json/$userIP";
    curl_setopt($ch, CURLOPT_URL, $geolocationAPI);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // کاهش تایم‌اوت
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

    $response = curl_exec($ch);

    // بستن cURL در صورت بروز خطا
    if ($response === false) {
        curl_close($ch);
        set_transient($cache_key, null, 3600); // کش برای 1 ساعت
        return null;
    }

    curl_close($ch);

    $data = json_decode($response);

    // اگر پاسخ معتبر نبود
    if (empty($data) || empty($data->country)) {
        set_transient($cache_key, null, 3600);
        return null;
    }

    // بررسی کشور و کش کردن نتیجه
    $result = $data->country == "Iran" ? true : false;
    set_transient($cache_key, $result, 3600); // کش برای 1 ساعت
    
    return $result;
}

function displayVPNAlertOnCheckout() {
    $isIranianUser = isUserFromIran();

    // اگر موقعیت‌یابی نتواهد شناسایی کند یا کاربر ایرانی نباشد، پیغام نمایش داده می‌شود
    if ($isIranianUser === null) {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                alert("جهت جلوگیری از بروز خطا در روند پرداخت، لطفا از خاموش بودن فیلترشکن خود اطمینان حاصل کنید.");
            });
        </script>';
    } elseif (!$isIranianUser) {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                alert("به نظر می‌رسد فیلترشکن شما روشن است. لطفاً برای جلوگیری از خطا در فرآیند پرداخت فیلترشکن خود را خاموش کنید و سپس پرداخت را انجام دهید.");
            });
        </script>';
    }
}

add_action('woocommerce_before_checkout_form', 'displayVPNAlertOnCheckout');


// =============================================================================================================
// Exclude Cancelled Orders in Orders Page
add_filter('woocommerce_my_account_my_orders_query', 'filter_canceled_orders_from_my_account');
function filter_canceled_orders_from_my_account($args) {
    $args['status'] = array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-refunded', 'wc-failed');
    return $args;
}

// =============================================================================================================
// Disable MyAccount Orders Pagination
add_filter('woocommerce_my_account_my_orders_query', 'disable_my_account_orders_pagination');
function disable_my_account_orders_pagination($args) {
    $args['posts_per_page'] = -1;
    return $args;
}

// =============================================================================================================
// تغییر ترتیب ستون‌ها و حذف ستون "مجموع"
function customize_my_orders_columns($columns) {
    unset($columns['order-total']);
    $order_actions = $columns['order-actions'];
    unset($columns['order-actions']);
    $columns['product_names'] = __('محصولات', 'woocommerce');
    $columns['order-actions'] = $order_actions;
    return $columns;
}
add_filter('woocommerce_my_account_my_orders_columns', 'customize_my_orders_columns');

// نمایش نام محصولات در ستون جدید
function display_product_names_in_my_orders($order) {
    $items = $order->get_items();
    $product_names = [];
    
    // گرفتن نام محصولات و اضافه کردن به آرایه
    foreach ($items as $item) {
        $product = $item->get_product();
        if ($product) {
            $product_names[] = $product->get_name(); // نام محصول
        }
    }
    
    echo implode(' - ', $product_names);
}
add_action('woocommerce_my_account_my_orders_column_product_names', 'display_product_names_in_my_orders');
// =============================================================================================================
add_filter( 'woocommerce_cart_totals_coupon_label', 'change_coupon_label_text', 10, 2 );

function change_coupon_label_text( $label, $coupon ) {
    if ( $coupon->get_code() ) {
        $label = 'سود شما از این خرید';
    }
    return $label;
}

// =============================================================================================================
// حذف پارامتی add-to-cart پس از اضافه کردن محصول به سبد خرید
function remove_add_to_cart_parameter_after_redirect() {
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
            // بررسی وجود پارامتر add-to-cart در URL
            if (window.location.search.includes('add-to-cart')) {
                // حذف پارامتر add-to-cart از URL بدون رفرش کردن صفحه
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'remove_add_to_cart_parameter_after_redirect');

// ============================================================================
// نمایش فرم دریافت اطلاعات بعد از خرید دوره های حضوری

function display_custom_order_content($order) {
    if (!$order) return;

    $show_vip_box = $show_form = false;

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        // بررسی محصول متغیر ووکامرسی
        if ($product->is_type('variable')) {
            $variation_id = $item->get_variation_id();
            if ($variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    // بررسی متغیر خاص (مثال: attribute_pa_course_type)
                    $course_type = $variation->get_attribute('pa_course_type');
                    
                    // اگر متغیر "حضوری" باشد، فرم نمایش داده شود
                    if ($course_type && strpos(strtolower($course_type), 'حضوری') !== false) {
                        $show_form = true;
                    }
                }
            }
        }
        
        // بررسی متغیر خاص برای باکس VIP (متغیر ID: 41078 از محصول 41077)
        $variation_id = $item->get_variation_id();
        if ($variation_id == 41078) {
            $show_vip_box = true;
        }
        
        // بررسی محصولات ساده (اختیاری - برای سازگاری با کد قبلی)
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

// =============================================================================================================
// Product suggestion with shortcode
function suggestion_box($atts) {
    $atts = shortcode_atts([
        'type' => 'product', // می‌تواند 'product' یا 'post' باشد
        'id' => ''
    ], $atts);

    if (!$atts['id']) {
        return '<p>لطفاً یک شناسه معتبر وارد کنید.</p>';
    }

    if ($atts['type'] === 'product') {
        $item = wc_get_product($atts['id']);
        if (!$item) {
            return '<p>محصول پیدا نشد.</p>';
        }
    } elseif ($atts['type'] === 'post') {
        $item = get_post($atts['id']);
        if (!$item || $item->post_status !== 'publish') {
            return '<p>مقاله پیدا نشد یا منتشر نشده است.</p>';
        }
    } else {
        return '<p>نوع مورد نظر معتبر نیست. لطفاً "product" یا "post" را انتخاب کنید.</p>';
    }

    // اطلاعات محصول یا مقالهی
    $title = method_exists($item, 'get_name') ? $item->get_name() : $item->post_title;
    $link = method_exists($item, 'get_permalink') ? $item->get_permalink() : get_permalink($item->ID);
    $excerpt = method_exists($item, 'get_short_description') ? $item->get_short_description() : $item->post_excerpt;

    // برای محصولات
    $price = '';
    $sale_percentage = '';
    $featured_image = '';

    if ($atts['type'] === 'product') {
        $price = $item->get_price_html();
        if ($item->is_on_sale()) {
            $regular_price = $item->get_regular_price();
            $sale_price = $item->get_sale_price();
            if ($regular_price && $sale_price) {
                $discount = round((($regular_price - $sale_price) / $regular_price) * 100);
                $sale_percentage = '<div class="product-discount">' . $discount . '% تخفیف</div>';
            }
        }
        $featured_image = $item->get_image('large');
    }

    // برای مقالات
    if ($atts['type'] === 'post') {
        if (has_post_thumbnail($item->ID)) {
            $featured_image = get_the_post_thumbnail($item->ID, 'large');
        }
    }

    $html = '<div class="suggestion-box">';
    $html .= '<div class="gallery-container">';
    $html .= '<a href="' . esc_url($link) . '">' . $featured_image . '</a>';
    $html .= '</div>';
    $html .= '<div class="item-info">';
    $html .= '<span class="item-title"><a href="' . esc_url($link) . '">' . esc_html($title) . '</a></h3>';
    if ($atts['type'] === 'product') {
        $html .= '<div class="price-discount-container">';
        $html .= '<p class="item-price">' . $price . '</p>';
        if ($sale_percentage) {
            $html .= $sale_percentage;
        }
        $html .= '</div>';
    }
    $html .= '<p class="item-excerpt">' . wp_kses_post($excerpt) . '</p>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

add_shortcode('box', 'suggestion_box');

// ============================================================================
// اصلاح واحد پول به IRR به جای IRT در اسکیمای محصولات
add_filter( 'rank_math/snippet/rich_snippet_product_entity', function( $entity ) {
    if ( isset( $entity['offers'] ) ) {
        
        // اگر offers یک آرایه از پیشنهادهاست
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
        
        // اگر offers یک شیء تکی هست
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
// ریدایرکت صفحات غیرمجاز ایندکس شده در سایت
add_action('template_redirect', function () {
    if (!empty($_SERVER['QUERY_STRING']) && preg_match('/^(?:o|b)(?:=|%3D)/i', $_SERVER['QUERY_STRING'])) {
        if (function_exists('status_header')) status_header(410);
        nocache_headers();
        header('Content-Type: text/plain; charset=UTF-8', true, 410);
        echo '410 Gone';
        exit;
    }
});

// ============================================================================
// همسان سازی شماره موبایل در متای billing_phone
function sync_voorodak_phone_to_billing_phone($user_id) {
    if (!$user_id) return;

    $voorodak_phone = get_user_meta($user_id, 'voorodak_phone', true);
    $digits_phone   = get_user_meta($user_id, 'digits_phone', true);
    $billing_phone  = get_user_meta($user_id, 'billing_phone', true);

    // اولویت: Voorodak > Digits > billing_phone
    if (!empty($voorodak_phone)) {
        update_user_meta($user_id, 'billing_phone', $voorodak_phone);
    } elseif (!empty($digits_phone) && empty($billing_phone)) {
        update_user_meta($user_id, 'billing_phone', $digits_phone);
    }
}
add_action('voorodak_after_do_register', 'sync_voorodak_phone_to_billing_phone', 20); 
add_action('voorodak_after_do_login', 'sync_voorodak_phone_to_billing_phone', 20);
add_action('profile_update', 'sync_voorodak_phone_to_billing_phone', 20);

// ============================================================================
// شخصی‌سازی مسیر بردکرامب برای مقالات
add_filter( 'rank_math/frontend/breadcrumb/items', function( $crumbs, $class ) {
    if ( is_single() && get_post_type() === 'post' ) {

        $crumbs = [
            [ 'آکادمی کارنو', home_url( '/' ) ],
            [ 'مقالات', home_url( '/blog/' ) ],
            [ get_the_title() ] // صفحه جاری بدون لینک
        ];
    }
    return $crumbs;
}, 10, 2 );

// ============================================================================
// تغییر متن دکمه بعدی برای فرم خاص
add_filter( 'gform_next_button', 'change_next_button_for_specific_form', 10, 2 );
function change_next_button_for_specific_form( $button, $form ) {
    if ( $form['id'] == 22 ) {
        return "<button class='gform_next_button button' id='gform_next_button_{$form['id']}'>دانلود فیلم وبینار</button>";
    }
    return $button;
}

// ============================================================================
// تکمیل سفارشات به حالت completed به صورت خودکار
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

    // تبدیل به completed فقط اگر در حالت processing باشد
    if ( $order->has_status( 'processing' ) ) {
        $order->update_status( 'completed', 'تغییر خودکار به حالت تکمیل شده' );
    }
}

// ============================================================================
// تخفیف پکیج زبان فنی
add_action( 'woocommerce_cart_calculate_fees', 'custom_package_discount', 20, 1 );
function custom_package_discount( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    // آیدی محصولات مورد نظر
    $required_products = array( 16180, 13534 );
    $found_products = array();

    // بررسی محصولات داخل سبد خرید
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( in_array( $cart_item['product_id'], $required_products ) ) {
            $found_products[] = $cart_item['product_id'];
        }
    }

    // اگر هر دو محصول توی سبد باشن، تخفیف اعمال بشه
    if ( count( array_unique($found_products) ) === count($required_products) ) {
        $discount_amount = 1320000; // مبلغ تخفیف به تومان
        $cart->add_fee( 'تخفیف پکیج زبان فنی', -$discount_amount );
    }
}

// ============================================================================
// پاک‌سازی کش هنگام تغییرات مهم
function clear_performance_cache() {
    // پاک کردن کش محصولات کاربر هنگام خرید جدید
    if (isset($_GET['order-received'])) {
        $user_id = get_current_user_id();
        if ($user_id) {
            delete_transient('user_products_' . $user_id);
        }
    }
}
add_action('init', 'clear_performance_cache');

// پاک کردن کش هنگام تغییر پروفایل کاربر
function clear_user_cache_on_profile_update($user_id) {
    delete_transient('user_phone_' . md5($user_id));
    delete_transient('user_products_' . $user_id);
}
add_action('profile_update', 'clear_user_cache_on_profile_update');

// ============================================================================
// بهینه‌سازی اسکریپت‌های آنالیتیکس با Lazy Loading
function optimize_analytics_scripts() {
    // فقط برای کاربران غیر ادمین و صفحات مهم
    if (is_admin() || (!is_front_page() && !is_product() && !is_shop() && !is_cart() && !is_checkout() && !is_page())) {
        return;
    }
    
    // بارگذاری تاخیری اسکریپت‌ها
    ?>
    <script>
    // Lazy loading برای GA4
    function loadGA4Script() {
        if (window.gtag) return; // جلوگیری از بارگذاری تکراری
        
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=G-3PNMHPF870';
        document.head.appendChild(script);
        
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-3PNMHPF870', {
            'anonymize_ip': true,
            'cookie_flags': 'SameSite=None;Secure',
            'send_page_view': false // جلوگیری از ارسال خودکار page_view
        });
        
        // ارسال page_view دستی پس از بارگذاری کامل
        gtag('event', 'page_view', {
            'page_title': document.title,
            'page_location': window.location.href
        });
    }
    
    // بارگذاری هوشمند: پس از تعامل کاربر یا 3 ثانیه
    var analyticsLoaded = false;
    
    function loadAnalytics() {
        if (analyticsLoaded) return;
        analyticsLoaded = true;
        loadGA4Script();
    }
    
    // بارگذاری فوری در صورت تعامل کاربر
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(function(event) {
        document.addEventListener(event, loadAnalytics, { once: true, passive: true });
    });
    
    // بارگذاری تاخیری
    if ('requestIdleCallback' in window) {
        requestIdleCallback(loadAnalytics, { timeout: 3000 });
    } else {
        setTimeout(loadAnalytics, 3000);
    }
    
    // بارگذاری فوری در صفحات مهم
    <?php if (is_front_page() || is_cart() || is_checkout()): ?>
    loadAnalytics();
    <?php endif; ?>
    </script>
    <?php
}

// اجرای بهینه‌سازی فقط در صفحات فرانت‌اند
add_action('wp_footer', 'optimize_analytics_scripts');

// ============================================================================
// ردیابی رویدادهای مهم WooCommerce در GA4
function track_woocommerce_events() {
    if (is_admin()) return;
    ?>
    <script>
    // ردیابی رویدادهای خرید
    document.addEventListener('DOMContentLoaded', function() {
        // ردیابی اضافه کردن به سبد خرید
        document.addEventListener('click', function(e) {
            if (e.target.matches('a[href*="add-to-cart"]') || e.target.closest('a[href*="add-to-cart"]')) {
                if (window.gtag) {
                    gtag('event', 'add_to_cart', {
                        'currency': 'IRR',
                        'value': 0 // مقدار از المنت گرفته شود
                    });
                }
            }
        });
        
        // ردیابی شروع چکاوت
        if (document.body.classList.contains('woocommerce-checkout')) {
            if (window.gtag) {
                gtag('event', 'begin_checkout', {
                    'currency': 'IRR'
                });
            }
        }
        
        // ردیابی تکمیل خرید
        if (document.body.classList.contains('woocommerce-order-received')) {
            if (window.gtag) {
                gtag('event', 'purchase', {
                    'currency': 'IRR',
                    'transaction_id': '<?php echo isset($_GET['key']) ? sanitize_text_field($_GET['key']) : ''; ?>'
                });
            }
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'track_woocommerce_events');

// ============================================================================
// اضافه کردن اسکریپت چت بات آیدا به صفحه خاص
function add_aida_chatbot_script() {
    // فقط در صفحه خاص نمایش داده شود (می‌توانید slug صفحه را تغییر دهید)
    if (is_page('test-aida-chatbot')) {
        ?>
        <script src="https://cdn.aidasales.ir/chatbox/aida-chatbot.min.fa.js" 
                data-aida-api-key="1L93YMKEC9" 
                data-position-chatbox="left" 
                data-initial-state="closed">
        </script>
        <?php
    }
}
add_action('wp_footer', 'add_aida_chatbot_script');

// ============================================================================
// اضافه کردن باکس پشتیبانی کارمپ بعد از خرید کارمپ
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

// ===================================================================
// 🧹 حذف Bloat پیشفرض وردپرس
// Author: Nias
// ===================================================================

// حذف هدرهای اضافی وردپرس
add_action('init', 'nias_remove_wp_headers');
function nias_remove_wp_headers() {
    remove_action('wp_head', 'rsd_link');                         // حذف لینک RSD
    remove_action('wp_head', 'wp_generator');                     // حذف نسخه وردپرس
    remove_action('wp_head', 'index_rel_link');                   // حذف لینک Index
    remove_action('wp_head', 'wlwmanifest_link');                 // حذف WLW
    remove_action('wp_head', 'parent_post_rel_link', 10, 0);      // حذف لینک والد
    remove_action('wp_head', 'start_post_rel_link', 10, 0);       // حذف لینک شروع
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);      // حذف شورت‌لینک
    remove_action('wp_head', 'wp_shortlink_header', 10, 0);       // حذف شورت‌لینک هدر
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0); // حذف لینک‌های مجاور
}

// ===================================================================
// 🚫 غیرفعال کردن RSS و فیدها
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

// حذف فید از هدر
remove_action('wp_head', 'feed_links_extra', 3);
remove_action('wp_head', 'feed_links', 2);

// ===================================================================
// 🎨 حذف Dashicons برای کاربران غیر لاگین
add_action('wp_print_styles', 'nias_remove_dashicons', 100);
function nias_remove_dashicons() {
    if (!is_admin_bar_showing() && !is_customize_preview()) {
        wp_dequeue_style('dashicons');
        wp_deregister_style('dashicons');
    }
}

// ===================================================================
// 😀 غیرفعال کردن Emoji
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





/**
 * لیست قیمت‌های نهایی محصولات
 */
/**
 * ۱. لیست قیمت‌های نهایی محصولات (قیمت مقطوع)
 */
function get_sepehr_final_prices() {
    return array(
        41078 => 9800000, 
        38427 => 9800000, 
        18535 => 7500000, 
        16180 => 3800000, 
        13928 => 3800000, 
        13534 => 1900000, 
        41462 => 9800000,
    );
}
function get_sepehr_redirect_only_ids() {
    return array(); //محصولاتی که باید صفحه محصولش باز بشه
}
add_action('template_redirect', 'handle_direct_purchase_link');
function handle_direct_purchase_link() {
    if ( !isset($_GET['special_buy']) || is_admin() ) return;

    $variation_id = isset($_GET['vid']) ? absint($_GET['vid']) : 0;
    $product_id   = isset($_GET['pid']) ? absint($_GET['pid']) : 0;
    $target_id    = $variation_id > 0 ? $variation_id : $product_id;

    if ( $target_id > 0 ) {
        $redirect_only_list = get_sepehr_redirect_only_ids();

        // --- سناریو ۱: محصول در لیست "فقط ریدایرکت" است ---
        if ( in_array( $target_id, $redirect_only_list ) ) {
            // کاربر را مستقیماً به صفحه محصول بفرست (بدون افزودن به سبد و بدون تخفیف)
            wp_safe_redirect( get_permalink( $target_id ) );
            exit;
        }

        // --- سناریو ۲: محصول در لیست "تخفیف ویژه" است ---
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

/**
 * ۳. تغییر قیمت در سبد خرید و حفظ داده در سشن
 */
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

add_filter( 'woocommerce_get_cart_item_from_session', function( $cart_item, $values ) {
    if ( isset( $values['is_fixed_price'] ) ) {
        $cart_item['is_fixed_price'] = $values['is_fixed_price'];
    }
    return $cart_item;
}, 10, 2 );

/**
 * ۴. جلوگیری از اعمال کوپن روی این محصولات
 */
add_filter( 'woocommerce_coupon_get_discount_amount', 'block_coupons_for_fixed_price', 10, 5 );
function block_coupons_for_fixed_price( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
    if ( isset( $cart_item['is_fixed_price'] ) ) return 0;
    return $discount;
}

// ==========================================
// بخش جدید: آمار و نمایش در پنل مدیریت
// ==========================================

/**
 * ۵. ذخیره متادیتا در سفارش (ثبت آیدی محصول برای آمار)
 */
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
/**
 * ۶. اضافه کردن ستون به مدیریت (سازگار با همه نسخه‌ها)
 */
add_filter( 'manage_edit-shop_order_columns', 'carno_add_order_special_column' );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'carno_add_order_special_column' );
function carno_add_order_special_column( $columns ) {
    $columns['special_buy_status'] = 'کمپین ویژه';
    return $columns;
}

/**
 * ۷. نمایش محتوا در ستون (فراخوانی برای نسخه قدیم و HPOS)
 */
add_action( 'manage_shop_order_posts_custom_column', 'carno_display_order_special_column', 10, 2 );
function carno_display_order_special_column( $column, $post_id ) {
    if ( $column === 'special_buy_status' ) {
        $is_special = get_post_meta( $post_id, '_is_sepehr_special_buy', true );
        carno_render_special_label( $is_special );
    }
}

add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'carno_display_order_hpos_special_column', 10, 2 );
function carno_display_order_hpos_special_column( $column, $order ) {
    if ( $column === 'special_buy_status' ) {
        $is_special = $order->get_meta( '_is_sepehr_special_buy' );
        carno_render_special_label( $is_special );
    }
}

/**
 * ۸. رندر کردن نهایی لیبل (تک‌رنگ و با متن ثابت)
 */
function carno_render_special_label( $is_special ) {
    if ( empty($is_special) ) {
        echo '<span style="color: #ccc;">—</span>';
        return;
    }

    // تنظیمات ظاهر یکپارچه
    $label = 'لینک‌های اسپات';
    $color = '#555d66'; // طوسی پررنگ استاندارد

    echo '<span style="background: ' . $color . '; color: #fff; padding: 6px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; white-space: nowrap;">' . $label . '</span>';
}

/**
 * ۹. منوی فیلتر بالای جدول سفارشات
 */
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

/**
 * ۱۰. اعمال فیلتر روی دیتابیس
 */
add_action( 'pre_get_posts', 'carno_apply_special_buy_filter_legacy' );
add_filter( 'woocommerce_order_query_args', 'carno_apply_special_buy_filter_hpos' );

function carno_apply_special_buy_filter_legacy( $query ) {
    global $pagenow;
    if ( is_admin() && 'edit.php' === $pagenow && (isset($_GET['post_type']) && 'shop_order' === $_GET['post_type']) && ! empty( $_GET['carno_special_filter'] ) ) {
        $query->set( 'meta_query', array( array( 'key' => '_is_sepehr_special_buy', 'compare' => 'EXISTS' ) ) );
    }
}

function carno_apply_special_buy_filter_hpos( $query_args ) {
    if ( is_admin() && ! empty( $_GET['carno_special_filter'] ) ) {
        $query_args['meta_query'][] = array( 'key' => '_is_sepehr_special_buy', 'compare' => 'EXISTS' );
    }
    return $query_args;
}

/**
 * Zero-Conflict Dynamic Favicon for WoodMart
 * This version uses a single-tag approach to prevent browser confusion.
 */
add_action('init', function() {
    add_filter('site_icon_meta_tags', '__return_empty_array', 999);
    remove_action('wp_head', 'wp_site_icon', 99);
}, 999);

add_action('wp_head', 'carno_ultimate_favicon_switcher', 0);
add_action('admin_head', 'carno_ultimate_favicon_switcher', 0);

function carno_ultimate_favicon_switcher() {
    $white_logo = 'https://sepehralimohammadi.com/wp-content/uploads/2026/01/carno-logo-dark.webp'; 
    $dark_logo  = 'https://sepehralimohammadi.com/wp-content/uploads/2026/01/carno-logo-light.webp';
    $version    = '3.0.1';
    ?>
    <link rel="icon" id="carno-favicon" href="<?php echo $dark_logo; ?>?v=<?php echo $version; ?>" type="image/webp">
    <script>
    (function() {
        const whiteIcon = "<?php echo $white_logo; ?>?v=<?php echo $version; ?>";
        const darkIcon  = "<?php echo $dark_logo; ?>?v=<?php echo $version; ?>";
        const favElem   = document.getElementById('carno-favicon');
        
        function applyFavicon() {
            const isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (isDarkMode) {
                favElem.href = whiteIcon;
            } else {
                favElem.href = darkIcon;
            }
        }
        applyFavicon();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyFavicon);
    })();
    </script>
    <?php
}

function carno_tip_shortcode($atts, $content = null) {
    return '<div class="carno-tip-box">' . do_shortcode($content) . '</div>';
}
add_shortcode('carno_tip', 'carno_tip_shortcode');

// نمایش داینامیک باکس کال تو اکشن لندینگ جدید دوره ها بعد از اسکرول - ریدیزاین 1404
add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function () {

  if (window.innerWidth <= 1024) return;

  var hero = document.getElementById("hero-section");
  var cta = document.querySelector(".floating-cta");

  if (!hero || !cta) return;

  var heroBottom = hero.offsetTop + hero.offsetHeight;

  window.addEventListener("scroll", function () {
    if (window.scrollY > heroBottom) {
      cta.classList.add("show");
    } else {
      cta.classList.remove("show");
    }
  });

});
</script>
<?php
});

// قیمت داینامیک برای فرم محصول تو لندینگ ریدیزاین 1404
// 1. پر کردن قیمت آنلاین (carno_online)
add_filter( 'gform_field_value_carno_online', 'populate_online_price' );
function populate_online_price( $value ) {
    if ( ! class_exists( 'WooCommerce' ) || ! is_product() ) return $value;

    $product = wc_get_product( get_the_ID() );

    // سناریو الف: محصول ساده است (همون قیمت اصلی رو برگردون)
    if ( $product->is_type( 'simple' ) ) {
        return $product->get_price();
    }

    // سناریو ب: محصول متغیر است (دنبال وریشن آنلاین بگرد)
    if ( $product->is_type( 'variable' ) ) {
        $variations = $product->get_available_variations();
        foreach ( $variations as $variation ) {
            // چک میکنیم توی ویژگی‌های این وریشن کلمه "آنلاین" یا "online" هست یا نه
            $attributes_str = implode( ' ', $variation['attributes'] );
            if ( strpos( $attributes_str, 'آنلاین' ) !== false || strpos( $attributes_str, 'online' ) !== false ) {
                return $variation['display_price'];
            }
        }
    }
    return $value;
}

// 2. پر کردن قیمت حضوری (carno_offline)
add_filter( 'gform_field_value_carno_offline', 'populate_offline_price' );
function populate_offline_price( $value ) {
    if ( ! class_exists( 'WooCommerce' ) || ! is_product() ) return $value;

    $product = wc_get_product( get_the_ID() );

    // محصول ساده معمولا حضوری نداره (طبق سناریوی تو)، پس خالی برگردون یا صفر
    if ( $product->is_type( 'simple' ) ) {
        return ''; 
    }

    // محصول متغیر: دنبال وریشن حضوری بگرد
    if ( $product->is_type( 'variable' ) ) {
        $variations = $product->get_available_variations();
        foreach ( $variations as $variation ) {
            // چک میکنیم توی ویژگی‌های این وریشن کلمه "حضوری" یا "offline" هست یا نه
            $attributes_str = implode( ' ', $variation['attributes'] );
            if ( strpos( $attributes_str, 'حضوری' ) !== false || strpos( $attributes_str, 'onsite-course' ) !== false ) {
                return $variation['display_price'];
            }
        }
    }
    return $value;
}

add_action( 'gform_post_payment_completed', 'carno_create_wc_order_after_payment_v2', 10, 2 );

function carno_create_wc_order_after_payment_v2( $entry, $action ) {

    // ---------------- تنظیمات دقیق (بر اساس گفته‌های تو) ----------------

    $online_form_id  = 43; // آیدی فرم آنلاین
    $onsite_form_id  = 42; // آیدی فرم حضوری

    // نقشه فیلدها برای فرم آنلاین (ID 43)
    $online_fields = array(
        'name'  => 9,
        'phone' => 8
    );

    // نقشه فیلدها برای فرم حضوری (ID 42)
    $onsite_fields = array(
        'name'  => 9,
        'phone' => 8
    );

    // ------------------------------------------------------------------

    // 1. تشخیص فرم و تنظیم متغیرها
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
        return; // اگر فرم دیگری بود، کاری نکن
    }

    // 2. دریافت و پردازش اطلاعات کاربر
    $full_name = rgar( $entry, $fields_map['name'] );
    $raw_phone = rgar( $entry, $fields_map['phone'] );

    // اصلاح نام
    $parts = explode( ' ', trim( $full_name ), 2 );
    $first_name = $parts[0];
    $last_name  = isset( $parts[1] ) ? $parts[1] : '';

    // اصلاح شماره موبایل (حذف صفر اول)
    $phone = ltrim( $raw_phone, '0' );

    // 3. پیدا کردن محصول
    // ابتدا سعی میکنیم از post_id خود گرویتی استفاده کنیم
    $product_id = rgar( $entry, 'post_id' );
    
    // اگر گرویتی post_id رو ذخیره نکرده بود، از url درمیاریم
    if ( empty( $product_id ) ) {
        $product_id = url_to_postid( $entry['source_url'] );
    }

    if ( ! $product_id ) return;

    $product = wc_get_product( $product_id );
    $item_id_to_add = $product_id; // پیش‌فرض برای محصولات ساده

    // 4. لاجیک تشخیص محصول متغیر (دقیق با اسلاگ)
    if ( $product->is_type( 'variable' ) ) {
        
        $variations = $product->get_available_variations();
        
        foreach ( $variations as $variation ) {
            // ویژگی‌های وریشن رو میگیریم (که شامل اسلاگ‌هاست)
            // خروجی attributes یه آرایه است مثل: ['attribute_pa_type' => 'online-course']
            
            // چک میکنیم آیا اسلاگ مد نظر ما توی مقادیر ویژگی‌های این وریشن هست یا نه
            if ( in_array( $target_slug, $variation['attributes'] ) ) {
                $item_id_to_add = $variation['variation_id'];
                break; // پیدا شد، حلقه رو بشکن
            }
        }
    }

    // 5. ایجاد سفارش ووکامرس
    if ( $item_id_to_add ) {
        
        $order = wc_create_order();
        
        // افزودن محصول
        $order->add_product( wc_get_product( $item_id_to_add ), 1 );
        
        // آدرس صورتحساب
        $address = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'phone'      => $phone,
        );
        $order->set_address( $address, 'billing' );
        
        // متادیتای پرداخت
        $order->set_payment_method( 'Gravity Forms Direct' );
        $order->set_payment_method_title( 'پرداخت سریع (آکادمی کارنو)' );
        
        $order->calculate_totals();
        
        // تکمیل سفارش
        $order->update_status( 'completed', 'سفارش ثبت شده توسط فرم شماره ' . $current_form_id . ' - شناسه تراکنش: ' . rgar($entry, 'transaction_id') );
        $order->save();
    }
}