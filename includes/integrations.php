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
// Gravity Forms - مدیریت سفارش ووکامرس برای فرم‌های 42 (حضوری) و 43 (آنلاین)
//
// جریان کار:
//   ۱. فرم ارسال شد  → سفارش با وضعیت «در انتظار پرداخت» ساخته می‌شود
//   ۲. پرداخت موفق   → وضعیت سفارش به «تکمیل شده» تغییر می‌کند
//   ۳. پرداخت ناموفق → وضعیت سفارش به «لغو شده» تغییر می‌کند
// ============================================================================

// گام ۱ - ساخت سفارش pending هنگام submit فرم (قبل از رفتن به درگاه)
add_action( 'gform_after_submission', 'carno_create_pending_wc_order_on_submission', 10, 2 );
function carno_create_pending_wc_order_on_submission( $entry, $form ) {
    $online_form_id = 43;
    $onsite_form_id = 42;

    if ( ! in_array( (int) $form['id'], [ $online_form_id, $onsite_form_id ] ) ) return;

    // جلوگیری از ساخت سفارش تکراری برای همین entry
    if ( gform_get_meta( $entry['id'], 'carno_wc_order_id' ) ) return;

    $is_online   = ( (int) $form['id'] === $online_form_id );
    $target_slug = $is_online ? 'online-course' : 'onsite-course';
    $form_label  = $is_online ? 'دوره آنلاین' : 'دوره حضوری';

    // اطلاعات مشتری از فیلدهای فرم (فیلد ۹ = نام کامل، فیلد ۸ = موبایل)
    $full_name  = rgar( $entry, '9' );
    $raw_phone  = rgar( $entry, '8' );
    $parts      = explode( ' ', trim( $full_name ), 2 );
    $first_name = $parts[0];
    $last_name  = isset( $parts[1] ) ? $parts[1] : '';
    $phone      = ltrim( $raw_phone, '0' );

    // پیدا کردن محصول از URL صفحه‌ای که فرم در آن بوده
    $product_id = (int) rgar( $entry, 'post_id' );
    if ( ! $product_id ) {
        $product_id = url_to_postid( $entry['source_url'] );
    }
    if ( ! $product_id ) return;

    $product = wc_get_product( $product_id );
    if ( ! $product ) return;

    // پیدا کردن وارییشن صحیح و قیمت آن از ووکامرس
    $item_id_to_add = $product_id;
    $item_price     = (float) $product->get_price();

    if ( $product->is_type( 'variable' ) ) {
        foreach ( $product->get_available_variations() as $variation ) {
            if ( in_array( $target_slug, $variation['attributes'] ) ) {
                $item_id_to_add = $variation['variation_id'];
                $var_obj        = wc_get_product( $variation['variation_id'] );
                if ( $var_obj ) {
                    $item_price = (float) $var_obj->get_price();
                }
                break;
            }
        }
    }

    // ساخت سفارش با وضعیت در انتظار پرداخت
    $order         = wc_create_order();
    $order_item_id = $order->add_product( wc_get_product( $item_id_to_add ), 1 );

    if ( $item_price > 0 ) {
        $item = $order->get_item( $order_item_id );
        $item->set_subtotal( $item_price );
        $item->set_total( $item_price );
        $item->save();
    }

    $order->set_address( [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'phone'      => $phone,
    ], 'billing' );

    // ثبت منشأ سفارش + عنوان روش پرداخت برای نمایش در ستون billing ادمین
    $order->update_meta_data( '_created_via', 'gravity_forms' );
    $order->update_meta_data( '_gf_form_id', $form['id'] );
    $order->update_meta_data( '_gf_entry_id', $entry['id'] );
    $order->set_payment_method( 'gf_carno' );
    $order->set_payment_method_title( 'پرداخت از طریق فرم (آکادمی کارنو)' );

    // خواندن کوکی‌های WooCommerce Order Attribution برای تشخیص منبع واقعی ترافیک
    // این کوکی‌ها توسط JS ووکامرس ست می‌شوند و در همین request قابل خواندن هستند
    $attr_fields = [
        'source_type', 'referrer',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_id', 'utm_term',
        'session_entry', 'session_start_time', 'session_pages', 'session_count',
        'user_agent', 'device_type', 'gclid', 'fbclid',
    ];
    $cookie_prefix    = 'woocommerce_order_attribution_';
    $has_attribution  = false;
    foreach ( $attr_fields as $field ) {
        $cookie_val = isset( $_COOKIE[ $cookie_prefix . $field ] ) ? $_COOKIE[ $cookie_prefix . $field ] : '';
        if ( $cookie_val !== '' ) {
            $order->update_meta_data( '_wc_order_attribution_' . $field, sanitize_text_field( $cookie_val ) );
            if ( $field === 'utm_source' || $field === 'source_type' ) {
                $has_attribution = true;
            }
        }
    }
    // اگر هیچ کوکی attribution‌ای نبود، منبع را direct ثبت می‌کنیم
    if ( ! $has_attribution ) {
        $order->update_meta_data( '_wc_order_attribution_source_type', 'direct' );
    }

    /*
    // اعمال کد تخفیف (فیلد ۱۵ برای فرم ۴۲، فیلد ۱۹ برای فرم ۴۳)
    $coupon_field_id = $is_online ? '19' : '15';
    $coupon_code     = sanitize_text_field( trim( rgar( $entry, $coupon_field_id ) ) );
    $coupon_note     = '';
    if ( $coupon_code ) {
        $coupon_result = $order->apply_coupon( $coupon_code );
        $coupon_note   = is_wp_error( $coupon_result )
            ? "\nکد تخفیف «{$coupon_code}» اعمال نشد: " . $coupon_result->get_error_message()
            : "\nکد تخفیف «{$coupon_code}» اعمال شد";
    }
    */
    $coupon_note = '';

    $order->calculate_totals();
    $order->set_status( 'pending' );

    // یادداشت سفارش با جزئیات ثبت‌نام
    $order->add_order_note(
        sprintf(
            "ثبت‌نام از طریق فرم گرویتی — %s\nنام: %s\nموبایل: %s\nفرم: #%d (entry: #%d)%s",
            $form_label,
            $full_name,
            $raw_phone,
            $form['id'],
            $entry['id'],
            $coupon_note
        )
    );

    $order->save();

    // ذخیره آیدی سفارش در متادیتای entry برای مراحل بعدی
    gform_update_meta( $entry['id'], 'carno_wc_order_id', $order->get_id() );
}

// تزریق توضیحات تراکنش آقای پرداخت قبل از ارسال به درگاه
// gateway روی gform_confirmation با priority=1000 hook می‌کند؛ ما با 999 زودتر اجرا می‌شویم
// و مقدار فیلد customer_fields_desc را در $_POST جایگزین می‌کنیم
add_filter( 'gform_confirmation', 'carno_inject_aqayepardakht_desc', 999, 4 );
function carno_inject_aqayepardakht_desc( $confirmation, $form, $entry, $ajax ) {
    if ( ! in_array( (int) $form['id'], [ 42, 43 ] ) ) return $confirmation;

    // خواندن feed config درگاه از جدول اختصاصی آقای پرداخت
    global $wpdb;
    $table   = $wpdb->prefix . 'gf_aqayepardakht';
    $results = $wpdb->get_results(
        $wpdb->prepare( "SELECT meta FROM $table WHERE form_id = %d AND is_active = 1 LIMIT 1", $form['id'] ),
        ARRAY_A
    );
    if ( empty( $results ) ) return $confirmation;

    $meta       = maybe_unserialize( $results[0]['meta'] );
    $desc_field = isset( $meta['customer_fields_desc'] ) ? $meta['customer_fields_desc'] : '';
    if ( ! $desc_field ) return $confirmation;

    // ساخت توضیحات: نام مشتری + نام محصول
    $full_name    = trim( rgar( $entry, '9' ) );
    $product_name = '';

    $product_id = url_to_postid( $entry['source_url'] );
    if ( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( $product ) {
            $product_name = $product->get_name();
        }
    }

    $parts       = array_filter( [ $full_name, $product_name ] );
    $description = implode( ' — ', $parts );

    if ( $description ) {
        $field_key          = 'input_' . str_replace( '.', '_', $desc_field );
        $_POST[$field_key]  = $description;
    }

    return $confirmation;
}

// ============================================================================
// AJAX - ولیدیشن کد تخفیف ووکامرس برای فرم‌های GF
/* -- غیرفعال شده --
add_action( 'wp_ajax_carno_validate_coupon', 'carno_ajax_validate_coupon' );
add_action( 'wp_ajax_nopriv_carno_validate_coupon', 'carno_ajax_validate_coupon' );
function carno_ajax_validate_coupon() {
    check_ajax_referer( 'carno_coupon_nonce', 'nonce' );

    $coupon_code   = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ?? '' ) );
    $product_id    = (int) ( $_POST['product_id'] ?? 0 );
    $current_price = (float) ( $_POST['current_price'] ?? 0 );

    if ( ! $coupon_code ) {
        wp_send_json_error( [ 'message' => 'کد تخفیف را وارد کنید' ] );
    }

    $coupon = new WC_Coupon( $coupon_code );
    if ( ! $coupon->get_id() ) {
        wp_send_json_error( [ 'message' => 'کد تخفیف معتبر نیست' ] );
    }

    $expiry = $coupon->get_date_expires();
    if ( $expiry && $expiry->getTimestamp() < time() ) {
        wp_send_json_error( [ 'message' => 'کد تخفیف منقضی شده است' ] );
    }

    $usage_limit = $coupon->get_usage_limit();
    if ( $usage_limit > 0 && $coupon->get_usage_count() >= $usage_limit ) {
        wp_send_json_error( [ 'message' => 'این کد تخفیف به حد مجاز استفاده رسیده است' ] );
    }

    $product_ids = $coupon->get_product_ids();
    if ( ! empty( $product_ids ) && ! in_array( $product_id, $product_ids ) ) {
        wp_send_json_error( [ 'message' => 'این کد تخفیف برای این محصول معتبر نیست' ] );
    }

    $excluded = $coupon->get_excluded_product_ids();
    if ( ! empty( $excluded ) && in_array( $product_id, $excluded ) ) {
        wp_send_json_error( [ 'message' => 'این کد تخفیف برای این محصول قابل استفاده نیست' ] );
    }

    $amount   = (float) $coupon->get_amount();
    $discount = ( $coupon->get_discount_type() === 'percent' )
        ? $current_price * ( $amount / 100 )
        : min( $amount, $current_price );
    $new_price = max( 0, $current_price - $discount );

    wp_send_json_success( [
        'message'            => 'کد تخفیف با موفقیت اعمال شد',
        'new_price'          => $new_price,
        'discount_formatted' => number_format( (int) $discount, 0, '.', ',' ),
        'new_price_formatted'=> number_format( (int) $new_price, 0, '.', ',' ),
    ] );
}
-- */

// UI - دکمه اعمال کد تخفیف در فرم‌های GF دوره حضوری (42) و آنلاین (43)
/* -- غیرفعال شده --
add_action( 'wp_footer', 'carno_gf_coupon_ui' );
function carno_gf_coupon_ui() {
    if ( ! is_product() ) return;
    $nonce      = wp_create_nonce( 'carno_coupon_nonce' );
    $product_id = get_the_ID();
    ?>
    <style>
    .carno-coupon-wrap { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:8px; }
    .carno-coupon-btn { background:#2271b1; color:#fff; border:none; padding:8px 18px; border-radius:6px; cursor:pointer; font-size:14px; font-family:inherit; transition:opacity .2s; }
    .carno-coupon-btn:disabled { opacity:.55; cursor:not-allowed; }
    .carno-coupon-msg { width:100%; font-size:13px; margin-top:4px; line-height:1.6; }
    </style>
    <script>
    (function() {
        var FORMS = {
            42: { couponField: 15, priceInput: '#ginput_base_price_42_14' },
            43: { couponField: 19, priceInput: '#ginput_base_price_43_18' }
        };
        var AJAX_URL   = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
        var NONCE      = '<?php echo esc_js( $nonce ); ?>';
        var PRODUCT_ID = <?php echo (int) $product_id; ?>;

        function initForm(formId, cfg) {
            var el = document.querySelector('#input_' + formId + '_' + cfg.couponField);
            if (!el) return;
            var li = el.closest('li');
            if (!li || li.querySelector('.carno-coupon-btn')) return;

            var wrap = document.createElement('div');
            wrap.className = 'carno-coupon-wrap';

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'carno-coupon-btn';
            btn.textContent = 'اعمال کد تخفیف';

            var msg = document.createElement('div');
            msg.className = 'carno-coupon-msg';

            wrap.appendChild(btn);
            wrap.appendChild(msg);
            li.appendChild(wrap);

            btn.addEventListener('click', function() {
                var code = el.value.trim();
                if (!code) { msg.innerHTML = '<span style="color:#c00">کد تخفیف را وارد کنید</span>'; return; }

                var priceEl = document.querySelector(cfg.priceInput);
                var price   = priceEl ? parseFloat(priceEl.value) || 0 : 0;

                btn.disabled = true;
                btn.textContent = 'در حال بررسی...';
                msg.innerHTML = '';

                var fd = new FormData();
                fd.append('action', 'carno_validate_coupon');
                fd.append('nonce', NONCE);
                fd.append('coupon_code', code);
                fd.append('product_id', PRODUCT_ID);
                fd.append('current_price', price);

                fetch(AJAX_URL, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            if (priceEl) {
                                priceEl.value = res.data.new_price;
                                ['change','keyup','input'].forEach(function(e) {
                                    priceEl.dispatchEvent(new Event(e, { bubbles: true }));
                                });
                            }
                            msg.innerHTML = '<span style="color:green">✓ ' + res.data.message + ' (' + res.data.discount_formatted + ' تومان تخفیف) — مبلغ قابل پرداخت: ' + res.data.new_price_formatted + ' تومان</span>';
                            btn.textContent = '✓ اعمال شد';
                            el.readOnly = true;
                        } else {
                            msg.innerHTML = '<span style="color:#c00">✗ ' + res.data.message + '</span>';
                            btn.disabled = false;
                            btn.textContent = 'اعمال کد تخفیف';
                        }
                    })
                    .catch(function() {
                        msg.innerHTML = '<span style="color:#c00">خطا در اتصال به سرور</span>';
                        btn.disabled = false;
                        btn.textContent = 'اعمال کد تخفیف';
                    });
            });
        }

        function initAll() {
            Object.keys(FORMS).forEach(function(id) { initForm(+id, FORMS[id]); });
        }

        document.addEventListener('DOMContentLoaded', initAll);
        document.addEventListener('gform_post_render', function(e) {
            var id = (e.detail || {}).formId;
            if (id && FORMS[id]) initForm(+id, FORMS[id]);
        });
    })();
    </script>
    <?php
}
-- */

// تابع کمکی: پیدا کردن WC order مرتبط با GF entry
function carno_get_wc_order_for_gf_entry( $entry_id ) {
    // اول از GF meta می‌خوانیم
    $order_id = gform_get_meta( $entry_id, 'carno_wc_order_id' );

    // fallback: اگه meta نبود، از meta خود WC می‌خوانیم
    if ( ! $order_id ) {
        $orders = wc_get_orders( [
            'limit'      => 1,
            'meta_key'   => '_gf_entry_id',
            'meta_value' => $entry_id,
        ] );
        if ( ! empty( $orders ) ) {
            $order_id = $orders[0]->get_id();
        }
    }

    return $order_id ? wc_get_order( $order_id ) : null;
}

// گام ۲ - پرداخت موفق: hook اولیه gateway — به عنوان early handler
// اگر این hook به هر دلیلی order را آپدیت نکند، گام ۳ به عنوان backup عمل می‌کند
add_action( 'gform_aqayepardakht_fulfillment', 'carno_handle_aqayepardakht_success', 10, 4 );
function carno_handle_aqayepardakht_success( $entry, $config, $transaction_id, $total ) {
    if ( ! in_array( (int) rgar( $entry, 'form_id' ), [ 42, 43 ] ) ) return;
    carno_set_gf_order_status( $entry['id'], 'completed', $transaction_id );
}

// گام ۳ - handler یکپارچه برای همه وضعیت‌های پرداخت
// این hook همیشه fire می‌شود (موفق، لغو، ناموفق) و به عنوان backup موفق نیز عمل می‌کند
add_action( 'gform_post_payment_status', 'carno_handle_gf_payment_status', 10, 8 );
function carno_handle_gf_payment_status( $config, $entry, $status, $transaction_id, $p5, $total, $p7, $p8 ) {
    if ( ! in_array( (int) rgar( $entry, 'form_id' ), [ 42, 43 ] ) ) return;
    carno_set_gf_order_status( $entry['id'], $status, $transaction_id );
}

// تابع کمکی: تنظیم وضعیت سفارش WC بر اساس نتیجه پرداخت GF
function carno_set_gf_order_status( $entry_id, $status, $transaction_id ) {
    $order = carno_get_wc_order_for_gf_entry( $entry_id );
    if ( ! $order ) return;

    if ( $status === 'completed' ) {
        if ( $order->has_status( 'completed' ) ) return;
        if ( $transaction_id ) {
            $order->set_transaction_id( $transaction_id );
        }
        $order->update_status( 'completed', 'پرداخت موفق از طریق آقای پرداخت — شناسه تراکنش: ' . $transaction_id );
        return;
    }

    // لغو یا ناموفق — فقط اگر سفارش هنوز تکمیل نشده باشد
    if ( $order->has_status( 'completed' ) ) return;
    $label = ( $status === 'cancelled' ) ? 'منصرف شده' : 'ناموفق';
    $order->update_status( 'cancelled', 'پرداخت ' . $label . ' از طریق آقای پرداخت' );
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
