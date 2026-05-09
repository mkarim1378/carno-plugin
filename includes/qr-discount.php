<?php
// ============================================================================
// سیستم تخفیف QR کد / کتاب (utm_source=book_qr)
// ============================================================================

// آرایه قیمت‌های ویژه برای خریداران از طریق QR کد
function carno_get_special_prices() {
    return array(
        // آنلاین کره ای
        41078 => 9800000,
        // آنلاین چینی
        38427 => 9800000,
        // داخلی
        18535 => 7500000,
        // زبان فنی
        16180 => 3800000,
        // GDS
        13928 => 3800000,
        // کتاب
        13534 => 1900000,
        // فرمان برقی
        41462 => 9800000,
        // همایش زنجان
        42096 => 1900000,
    );
}

// بررسی فعال بودن تخفیف (کوکی یا پارامتر URL)
function carno_is_discount_active() {
    if (isset($_COOKIE['carno_book_ids']) || (isset($_GET['utm_source']) && $_GET['utm_source'] === 'book_qr')) {
        return true;
    }
    return false;
}

// ست کردن کوکی هنگام ورود با UTM (بدون ریدایرکت)
add_action('init', 'carno_set_discount_cookie_logic');
function carno_set_discount_cookie_logic() {
    if (isset($_GET['utm_source']) && $_GET['utm_source'] === 'book_qr') {
        $product_id = url_to_postid(home_url($_SERVER['REQUEST_URI']));

        if ($product_id) {
            $saved_ids = isset($_COOKIE['carno_book_ids']) ? explode(',', $_COOKIE['carno_book_ids']) : array();

            if (!in_array($product_id, $saved_ids)) {
                $saved_ids[] = $product_id;
            }

            $updated_ids = implode(',', $saved_ids);

            setcookie('carno_book_ids', $updated_ids, time() + 1800, COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE['carno_book_ids'] = $updated_ids;
        }
    }
}

// بررسی اینکه آیا محصول خاصی مشمول تخفیف QR است
function carno_is_item_discounted($product_id) {
    if (isset($_GET['utm_source']) && $_GET['utm_source'] === 'book_qr') {
        $current_url_id = url_to_postid(home_url($_SERVER['REQUEST_URI']));
        if ((int)$current_url_id === (int)$product_id) return true;
    }

    if (!isset($_COOKIE['carno_book_ids'])) return false;
    $allowed_ids = explode(',', $_COOKIE['carno_book_ids']);
    return in_array((string)$product_id, $allowed_ids);
}

// اعمال قیمت ویژه روی محصولات مشمول تخفیف
add_filter('woocommerce_product_get_price', 'carno_final_price_logic', 999, 2);
add_filter('woocommerce_product_variation_get_price', 'carno_final_price_logic', 999, 2);
add_filter('woocommerce_product_get_sale_price', 'carno_final_price_logic', 999, 2);

function carno_final_price_logic($price, $product) {
    $p_id = $product->get_id();
    if (carno_is_item_discounted($p_id)) {
        $special_prices = carno_get_special_prices();
        if (array_key_exists($p_id, $special_prices)) {
            return $special_prices[$p_id];
        }
    }
    return $price;
}

// اجبار به نمایش قیمت خط خورده برای محصولات مشمول تخفیف
add_filter('woocommerce_product_is_on_sale', 'carno_force_sale_ui', 999, 2);
function carno_force_sale_ui($is_on_sale, $product) {
    return carno_is_item_discounted($product->get_id()) ? true : $is_on_sale;
}

// نمایش پیغام تبریک تخفیف (یکبار در session)
add_action('wp_footer', 'carno_show_discount_alert');
function carno_show_discount_alert() {
    if (is_product() && carno_is_discount_active()) {
        ?>
        <script>
            (function() {
                if (!sessionStorage.getItem('carno_alert_shown')) {
                    alert("تبریک! چون شما از همراهان کتاب آکادمی Carno هستید، «بیشترین تخفیف» ممکن به صورت خودکار برای شما اعمال شد. این فرصت فقط تا ۳۰ دقیقه دیگر معتبر است.");
                    sessionStorage.setItem('carno_alert_shown', 'true');
                }
            })();
        </script>
        <?php
    }
}

// ============================================================================
// قیمت داینامیک برای فرم محصول در لندینگ (پر کردن فیلد گرویتی فرم)
function carno_get_dynamic_price($product, $type_slugs) {
    $special_prices = carno_get_special_prices();
    $has_discount = isset($_COOKIE['carno_book_discount']);

    if ($product->is_type('simple')) {
        $p_id = $product->get_id();
        return ($has_discount && isset($special_prices[$p_id])) ? $special_prices[$p_id] : $product->get_price();
    }

    if ($product->is_type('variable')) {
        $variations = $product->get_available_variations();
        foreach ($variations as $variation) {
            $attributes_str = implode(' ', $variation['attributes']);
            $match = false;
            foreach ($type_slugs as $slug) {
                if (strpos($attributes_str, $slug) !== false) { $match = true; break; }
            }

            if ($match) {
                $v_id = $variation['variation_id'];
                return ($has_discount && isset($special_prices[$v_id])) ? $special_prices[$v_id] : $variation['display_price'];
            }
        }
    }
    return '';
}

// قیمت فیلد محصول فرم آنلاین (43) — parameter name فیلد Price در GF
add_filter('gform_field_value_carno_online_price', function($value) {
    if (!class_exists('WooCommerce') || !is_product()) return $value;
    $price = carno_get_dynamic_price(wc_get_product(get_the_ID()), ['آنلاین', 'online', 'online-course']);
    return $price !== '' ? $price : $value;
});

// قیمت فیلد محصول فرم حضوری (42) — parameter name فیلد Price در GF
add_filter('gform_field_value_carno_offline_price', function($value) {
    if (!class_exists('WooCommerce') || !is_product()) return $value;
    $price = carno_get_dynamic_price(wc_get_product(get_the_ID()), ['حضوری', 'offline', 'onsite-course']);
    return $price !== '' ? $price : $value;
});

// تریگر کردن محاسبه مجموع GF بعد از pre-populate قیمت محصول
// بدون این، فیلد مجموع روی ۰ می‌ماند و GF ارور می‌دهد
add_action('wp_footer', 'carno_trigger_gf_total_recalculation');
function carno_trigger_gf_total_recalculation() {
    if ( ! is_product() ) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            // trigger change روی فیلد قیمت محصول تا GF مجموع رو recalculate کنه
            // فرم آنلاین 43 فیلد 15 — فرم حضوری 42 فیلد 12
            var fields = [
                '#ginput_base_price_43_18',
                '#ginput_base_price_42_14'
            ];
            fields.forEach(function(sel) {
                var el = document.querySelector(sel);
                if (!el) return;
                ['change', 'keyup', 'input'].forEach(function(evt) {
                    el.dispatchEvent(new Event(evt, { bubbles: true }));
                });
            });
        }, 600);
    });
    </script>
    <?php
}

// نمایش بج تخفیف روی فرم‌های آنلاین (43) و حضوری (42)
add_filter('gform_form_tag', 'carno_add_discount_badge_to_form', 10, 2);
function carno_add_discount_badge_to_form($form_tag, $form) {
    if (in_array($form['id'], [42, 43]) && isset($_COOKIE['carno_book_discount'])) {
        $badge_html = '
        <div style="background: #ebffef; border: 1px solid #28a745; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold;">
            🎁 هدیه وفاداری آکادمی کارنو فعال شد!<br>
            <span style="font-size: 0.9em; font-weight: normal;">به پاس همراهی شما با کتاب، <span style="color: #d63384; font-weight: bold;">«بیشترین تخفیف اختصاصی»</span> روی قیمت این دوره برای شما اعمال شد.</span>
        </div>';
        $form_tag .= $badge_html;
    }
    return $form_tag;
}
