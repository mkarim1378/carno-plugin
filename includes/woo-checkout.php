<?php
// ============================================================================
// چک‌اوت ووکامرس - فیلدها، نام کامل، لیبل کوپن
// ============================================================================

// فیلدهای صورتحساب: فقط نام و موبایل (+ آدرس برای محصول 13534)
function customize_checkout_fields($fields) {
    $has_product_13534 = false;
    if (WC()->cart && !WC()->cart->is_empty()) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['product_id']) && $cart_item['product_id'] == 13534) {
                $has_product_13534 = true;
                break;
            }
        }
    }

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

    $fields['billing']['billing_full_name'] = array(
        'label'    => 'نام و نام خانوادگی',
        'required' => true,
        'class'    => array('form-row-wide'),
        'priority' => 10,
    );

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

// نمایش نام کامل در فرم هنگام ویرایش
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

// ============================================================================
// تغییر لیبل کوپن در سبد خرید
add_filter( 'woocommerce_cart_totals_coupon_label', 'change_coupon_label_text', 10, 2 );

function change_coupon_label_text( $label, $coupon ) {
    if ( $coupon->get_code() ) {
        $label = 'سود شما از این خرید';
    }
    return $label;
}
