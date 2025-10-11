<?php

/*
Plugin Name:  Carno Customization Plugin
Plugin URI:   https://sepehralimohammadi.com/
Description:  این افزونه جهت اعمال شخصی سازی های مورد نیاز بر روی وبسایت مهندس سپهر علیمحمدی توسعه داده شده است. لطفا از غیرفعال کردن این افزونه خودداری فرمایید!
Version:      1.13.9
Author:       سپهر علیمحمدی
Author URI:   https://sepehralimohammadi.com/
*/

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

    foreach ( $cart->get_cart() as $cart_item ) {
        $product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] > 0
            ? (int) $cart_item['variation_id']
            : (int) $cart_item['product_id'];

        if ( array_key_exists( $product_id, $discounts ) ) {
            $fee_label = $discounts[$product_id]['label'];

            $already_added = false;
            foreach ( $cart->get_fees() as $fee ) {
                if ( isset( $fee->name ) && $fee->name === $fee_label ) {
                    $already_added = true;
                    break;
                }
            }

            if ( ! $already_added ) {
                $cart->add_fee(
                    __( $fee_label, 'carno' ),
                    -1 * (float) $discounts[$product_id]['amount']
                );
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

    // چک روی username
    $user = get_user_by('login', '0' . $normalized_phone);
    if (!$user) {
        $user = get_user_by('login', $normalized_phone);
    }
    if ($user) {
        return $user->ID;
    }

    // چک روی usermeta
    $user_query = new WP_User_Query([
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'billing_phone', 'value' => $normalized_phone, 'compare' => 'LIKE'],
            ['key' => 'digits_phone_no', 'value' => $normalized_phone, 'compare' => 'LIKE'],
        ]
    ]);

    if (!empty($user_query->results)) {
        return $user_query->results[0]->ID;
    }

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

// add_action('admin_init', function() {
//     if (isset($_GET['check_guest_orders'])) {
//         global $wpdb;
//         $count = $wpdb->get_var("
//             SELECT COUNT(*) 
//             FROM {$wpdb->posts} p
//             LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_customer_user'
//             WHERE p.post_type = 'shop_order'
//               AND (m.meta_value = '0' OR m.meta_value IS NULL)
//         ");
//         exit("تعداد سفارش‌های مهمان باقی‌مانده: {$count}");
//     }
// });

// add_action('init', function() {
//     if (is_admin() && isset($_GET['run_guest_fix'])) {

//         global $wpdb;

//         // همه سفارش‌های مهمان واقعی (customer_id=0 یا متا خالی)
//         $order_ids = $wpdb->get_col("
//             SELECT p.ID
//             FROM {$wpdb->posts} p
//             LEFT JOIN {$wpdb->postmeta} m 
//                 ON p.ID = m.post_id AND m.meta_key = '_customer_user'
//             WHERE p.post_type = 'shop_order'
//               AND (m.meta_value = '0' OR m.meta_value IS NULL)
//         ");

//         foreach ($order_ids as $order_id) {
//             create_user_from_guest_order_by_phone_v2($order_id);
//         }

//         exit('همه سفارش‌های مهمان بررسی شدند ✅ (تعداد: ' . count($order_ids) . ')');
//     }
// });
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
    if ( $user_id ) {
        $field_id = 8;
        foreach ( $form['fields'] as &$field ) {
            if ( $field->id == $field_id ) {
                $customer_orders = get_posts( array(
                    'numberposts' => -1,
                    'meta_key'    => '_customer_user',
                    'meta_value'  => $user_id,
                    'post_type'   => wc_get_order_types(),
                    'post_status' => 'wc-completed', // You can change this to 'any' for all orders
                ) );
                $purchased_products = array();
                foreach ( $customer_orders as $order ) {
                    $order = wc_get_order( $order->ID );
                    $items = $order->get_items();
                    foreach ( $items as $item ) {
                        $product = $item->get_product();
                        if ( $product ) {
                            $purchased_products[ $product->get_id() ] = $product->get_name();
                        }
                    }
                }
                $choices = array();
                foreach ( $purchased_products as $product_id => $product_name ) {
                    $choices[] = array(
                        'text' => $product_name,
                        'value' => $product_name,
                    );
                }
                $field->choices = $choices;
            }
        }
    }
    return $form;
}
// ==========================================================================
add_action('wp_head', function() {
    ?>
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
    <?php
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
            background: linear-gradient(90deg, #ff6a00, #ff0000);
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


add_action('template_redirect', 'km_protect_course_content_page');
function km_protect_course_content_page() {
    $course_content_slug = 'tamirkare-milyarder/contents';
    if (is_page($course_content_slug)) {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/auth/'));
            exit;
        }
        $user_id = get_current_user_id();
        $course_product_ids = [33429, 33496, 33497];
        $has_purchased = false;
        foreach ($course_product_ids as $product_id) {
            if (wc_customer_bought_product('', $user_id, $product_id)) {
                $has_purchased = true;
                break;
            }
        }
        if (!$has_purchased) {
            wp_redirect(home_url('/tamirkare-milyarder/'));
            exit;
        }
    }
}

function redirect_to_course_contents_after_purchase($order_id) {
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    if (!$order->has_status(['processing', 'completed'])) {
        return;
    }

    $allowed_product_ids = [33429, 33496, 33497];

    foreach ($order->get_items() as $item) {
        if (in_array($item->get_product_id(), $allowed_product_ids)) {
            wp_safe_redirect(home_url('/tamirkare-milyarder/contents/'));
            exit;
        }
    }
}

add_action('woocommerce_thankyou', 'redirect_to_course_contents_after_purchase');
add_action('woocommerce_order_status_completed', 'redirect_to_course_contents_after_purchase');

add_filter('woocommerce_my_account_my_orders_actions', 'custom_change_view_order_link', 10, 2);
function custom_change_view_order_link($actions, $order) {
    $course_product_ids = [33429, 33496, 33497];
    foreach ($order->get_items() as $item) {
        if (in_array($item->get_product_id(), $course_product_ids)) {
            if (isset($actions['view'])) {
                $actions['view']['url'] = home_url('/tamirkare-milyarder/contents/');
            }
            break;
        }
    }
    return $actions;
}

add_action('wp_footer', 'change_static_course_button_if_bought');
function change_static_course_button_if_bought() {
    if (!is_page('tamirkare-milyarder')) return;
    $product_ids = [33429, 33496, 33497];
    $content_url = home_url('/tamirkare-milyarder/contents/');
    if (!is_user_logged_in()) return;
    $user_id = get_current_user_id();
    $has_bought = false;
    foreach ($product_ids as $product_id) {
        if (wc_customer_bought_product('', $user_id, $product_id)) {
            $has_bought = true;
            break;
        }
    }
    if ($has_bought) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.cta-buy-two a');
            buttons.forEach(function(btn) {
                btn.textContent = 'مشاهده دوره';
                btn.setAttribute('href', '<?php echo esc_url($content_url); ?>');
            });
        });
        </script>
        <?php
    }
}


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

    // ✅ ایجاد Walker اختصاصی برای حذف لینک تاریخ
    class No_Link_Comment_Walker extends Walker_Comment {
        protected function comment_date( $comment ) {
            echo '<span class="comment-date">' . get_comment_date( '', $comment ) . '</span>';
        }

        protected function comment_reply_link( $comment, $depth, $args ) {
            // اگر ریپلای هم نمی‌خوای نمایش داده شه، این بخش خالی می‌مونه
        }
    }

    if ( $comments ) {
        echo '<div id="comments" class="comments-area">';
        wp_list_comments( [
            'echo'   => true,
            'per_page' => 0,
            'walker' => new No_Link_Comment_Walker(), // ✅ استفاده از Walker جدید
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
// تغییر فیلدهای صورتحساب در صورت وجود یا عدم وجود محصول فیزیکی

function customize_checkout_fields($fields) {
    $has_physical_products = false;

    // بررسی محصولات موجود در سبد خرید
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        if (!$product->is_virtual()) {
            $has_physical_products = true;
            break;
        }
    }

    // حذف فیلدهایی که همیشه نمی‌خواهید (نام شرکت و آدرس ۲ و ایمیل)
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_email']);

    // ترکیب فیلدهای نام و نام خانوادگی
    $fields['billing']['billing_full_name'] = array(
        'label'    => 'نام و نام خانوادگی',
        'required' => true,
        'class'    => array('form-row-wide'),
        'priority' => 10,
    );

    // حذف فیلدهای جداگانه نام و نام خانوادگی
    unset($fields['billing']['billing_first_name']);
    unset($fields['billing']['billing_last_name']);

    // حذف فیلدهای مربوط به آدرس در صورت نبودن محصول فیزیکی
    if (!$has_physical_products) {
        unset($fields['billing']['billing_address_1']);
        unset($fields['billing']['billing_city']);
        unset($fields['billing']['billing_postcode']);
        unset($fields['billing']['billing_state']);
        unset($fields['billing']['billing_country']);
    }
    return $fields;
}
add_filter('woocommerce_checkout_fields', 'customize_checkout_fields');

function customize_default_address_fields($address_fields) {
    // اصلاح فیلدها
    $address_fields['address_1']['label'] = 'آدرس پستی';
    $address_fields['address_1']['placeholder'] = '';
    $address_fields['postcode']['label'] = 'کد پستی';
    return $address_fields;
}
add_filter('woocommerce_default_address_fields', 'customize_default_address_fields');

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
    if (is_single() || is_page()) {
        $postID = get_the_ID();
        increment_post_views($postID);
    }
}
add_action('wp_head', 'track_post_views');

// =============================================================================================================
// Detect User IP in Woo Checkout Page

function isUserFromIran() {

    $userIP = $_SERVER['REMOTE_ADDR'];

    // ارسال درخواست به سرویس موقعیت‌یابی
    $ch = curl_init();
    $geolocationAPI = "http://ip-api.com/json/$userIP";
    curl_setopt($ch, CURLOPT_URL, $geolocationAPI);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);

    // بستن cURL در صورت بروز خطا
    if ($response === false) {
        curl_close($ch);
        return null; // ارسال مقدار null به جای false که می‌تواند به معنای خطا در موقعیت‌یابی باشد
    }

    curl_close($ch);

    $data = json_decode($response);

    // اگر پاسخ معتبر نبود
    if (empty($data) || empty($data->country)) {
        return null; // ارسال مقدار null در صورتی که موقعیت‌یابی نتواهد نتیجه بدهد
    }

    // بررسی کشور
    return $data->country == "Iran" ? true : false;
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
        
        // بررسی متغیر خاص برای باکس VIP (متغیر ID: 38420 از محصول 34894)
        $variation_id = $item->get_variation_id();
        if ($variation_id == 38420) {
            $show_vip_box = true;
        }
        
        // بررسی محصولات ساده (اختیاری - برای سازگاری با کد قبلی)
        $product_id = $product->get_id();
        $vip_product_id = 34894;
        $form_product_ids = [13832, 14259];
        
        if ($product_id == $vip_product_id) $show_vip_box = true;
        if (in_array($product_id, $form_product_ids)) $show_form = true;
    }

    if ($show_vip_box) : ?>
        <style>
        .vip-box {margin:30px auto;padding:25px;border-radius:15px;border:1px solid #e0e0e0;background:#f9f9f9;box-shadow:0 4px 12px rgba(0,0,0,0.08);text-align:center;}
        .vip-box h2 {font-size:22px;margin-bottom:15px;color:#00004c;}
        .vip-box p {font-size:16px;margin-bottom:20px;color:#00004c;line-height:1.9em;}
        .vip-box .btn-group {display:flex;justify-content:center;gap:15px;flex-wrap:wrap;}
        .vip-box .btn-link {padding:12px 20px;border-radius:8px;text-decoration:none;font-size:16px;font-weight:600;color:#fff;transition:all .3s ease;}
        .vip-box .btn-vip {background:#ff6d00}
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
add_action( 'woocommerce_thankyou', 'auto_complete_all_orders' );
function auto_complete_all_orders( $order_id ) {
    if ( ! $order_id ) {
        return;
    }

    $order = wc_get_order( $order_id );

    if ( $order->has_status( 'processing' ) ) {
        $order->update_status( 'completed' );
    }
}