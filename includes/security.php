<?php
// ============================================================================
// امنیت و ریدایرکت‌ها
// ============================================================================

// بازگشت 410 برای URL های ختم شده با .html
add_action('template_redirect', function () {
    if (preg_match('/\.html$/i', $_SERVER['REQUEST_URI'])) {
        status_header(410);
        nocache_headers();
        exit;
    }
});

// بازگشت 410 برای query string های مشکوک
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
// تشخیص موقعیت جغرافیایی کاربر (ایران)
// ============================================================================

function isUserFromIran() {
    $userIP = $_SERVER['REMOTE_ADDR'];
    $cache_key = 'iran_check_' . md5($userIP);
    $cached_result = get_transient($cache_key);

    if ($cached_result !== false) {
        return $cached_result;
    }

    $ch = curl_init();
    $geolocationAPI = "http://ip-api.com/json/$userIP";
    curl_setopt($ch, CURLOPT_URL, $geolocationAPI);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        set_transient($cache_key, null, 3600);
        return null;
    }

    curl_close($ch);

    $data = json_decode($response);

    if (empty($data) || empty($data->country)) {
        set_transient($cache_key, null, 3600);
        return null;
    }

    $result = $data->country == "Iran" ? true : false;
    set_transient($cache_key, $result, 3600);

    return $result;
}

function displayVPNAlertOnCheckout() {
    $isIranianUser = isUserFromIran();

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

// add_action('woocommerce_before_checkout_form', 'displayVPNAlertOnCheckout');
