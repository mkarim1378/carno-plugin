<?php

/*
Plugin Name:  Carno Customization Plugin
Plugin URI:   https://sepehralimohammadi.com/
Description:  این افزونه جهت اعمال شخصی سازی های مورد نیاز بر روی وبسایت مهندس سپهر علیمحمدی توسعه داده شده است. لطفا از غیرفعال کردن این افزونه خودداری فرمایید!
Version:      2.0.3
Author:       سپهر علیمحمدی
Author URI:   https://sepehralimohammadi.com/
*/

$carno_includes = plugin_dir_path( __FILE__ ) . 'includes/';

require_once $carno_includes . 'performance.php';    // بهینه‌سازی وردپرس و حذف bloat
require_once $carno_includes . 'security.php';       // امنیت، ریدایرکت‌ها، تشخیص VPN
require_once $carno_includes . 'users.php';          // مدیریت کاربران، ساخت حساب، کش
require_once $carno_includes . 'content.php';        // شورتکدها، TOC، بازدید مقالات
require_once $carno_includes . 'woo-pricing.php';    // قیمت‌گذاری، تخفیف‌های سبد، پکیج
require_once $carno_includes . 'woo-orders.php';     // مدیریت سفارشات، ستون‌های ادمین
require_once $carno_includes . 'woo-checkout.php';   // فیلدهای چک‌اوت، نام کامل، کوپن
require_once $carno_includes . 'woo-campaign.php';   // کمپین special_buy (لینک‌های اسپات)
require_once $carno_includes . 'qr-discount.php';    // سیستم تخفیف QR کد / کتاب
require_once $carno_includes . 'integrations.php';   // Elementor، Gravity Forms، Rank Math، Voorodak
require_once $carno_includes . 'ui.php';             // فاوآیکون داینامیک، اسکریپت‌های UI
