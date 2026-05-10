# مستندات فنی افزونه شخصی‌سازی کارنو

## اطلاعات کلی افزونه

- **نام افزونه**: Carno Customization Plugin
- **نسخه**: 2.0.7
- **نویسنده**: سپهر علیمحمدی
- **آدرس**: https://sepehralimohammadi.com/
- **توضیحات**: افزونه شخصی‌سازی برای وبسایت آکادمی کارنو (آموزشگاه برق خودرو)

---

## ساختار فایل‌های افزونه

```
carno-plugin/
├── carno-plugin.php          ← فایل اصلی - فقط header و require_once ها
└── includes/
    ├── performance.php       ← بهینه‌سازی وردپرس، حذف bloat
    ├── security.php          ← ریدایرکت 410، تشخیص VPN/ایران
    ├── users.php             ← ساخت کاربر، اتصال سفارشات، کش
    ├── content.php           ← شورتکدها، TOC، بازدید، کامنت
    ├── woo-pricing.php       ← تخفیف سبد، پکیج، ساعت ۱۶، نمایش رایگان
    ├── woo-orders.php        ← تکمیل خودکار، فیلتر، ستون ادمین
    ├── woo-checkout.php      ← فیلدهای چک‌اوت، نام کامل، کوپن
    ├── woo-campaign.php      ← کمپین special_buy (لینک‌های اسپات)
    ├── qr-discount.php       ← سیستم تخفیف QR کد / کتاب
    ├── integrations.php      ← Elementor، Gravity Forms، Rank Math، Voorodak
    └── ui.php                ← فاوآیکون داینامیک، CTA، URL تمیز
```

---

## ۱. performance.php — بهینه‌سازی وردپرس

### مسدود کردن درخواست‌های خارجی

```php
function BlockExternalHostRequests($false, $parsed_args, $url)
```

هاست‌های مسدود: `rankmath.com`, `googleapis.com`, `github.com`, `yoast.com`, `w.org`, `elementor.com`, `cloudflare.com`, `woocommerce.com`

### سایر بهینه‌سازی‌ها

- غیرفعال کردن Block Editor و Widget Block Editor
- حذف استایل‌های `wp-block-library` و `global-styles`
- غیرفعال کردن آپدیت خودکار ترجمه‌ها
- حذف هدرهای اضافی وردپرس (RSD, generator, shortlink, ...)
- غیرفعال کردن RSS و Atom feed
- حذف Dashicons برای کاربران غیر لاگین
- غیرفعال کردن Emoji
- حذف jquery-migrate

---

## ۲. security.php — امنیت و ریدایرکت‌ها

### ریدایرکت‌های ۴۱۰

- URL هایی که به `.html` ختم می‌شوند → `410 Gone`
- Query string های مشکوک با پارامتر `o=` یا `b=` → `410 Gone`

### تشخیص موقعیت جغرافیایی

```php
function isUserFromIran()   // از سرویس ip-api.com با کش 1 ساعته
function displayVPNAlertOnCheckout()   // غیرفعال — قابل فعال‌سازی
```

---

## ۳. users.php — مدیریت کاربران

### توابع اصلی

| تابع | عملکرد |
|---|---|
| `user_exists_by_phone($phone)` | جستجوی کاربر با شماره تلفن، کش 30 دقیقه |
| `create_user_from_guest_order_by_phone_v2($order_id)` | ساخت حساب یا اتصال سفارش مهمان |
| `connect_guest_orders_by_phone_to_user_account($user_id)` | اتصال سفارشات قدیمی بعد از لاگین |
| `mk_update_user_display_name($user_id)` | آپدیت نام نمایشی از first/last name |
| `sync_voorodak_phone_to_billing_phone($user_id)` | همگام‌سازی موبایل Voorodak با billing_phone |
| `keep_user_logged_in_for_1_year($expirein)` | تمدید session به ۱ سال |
| `clear_performance_cache()` | پاک کردن transient بعد از خرید |

**نکته نرمال‌سازی شماره:** `user_exists_by_phone` از ۱۰ رقم آخر استفاده می‌کند، `connect_guest_orders` از ۹ رقم آخر (با LIKE در دیتابیس).

---

## ۴. content.php — شورتکدها و محتوا

### شورتکدهای موجود

| شورتکد | تابع | توضیح |
|---|---|---|
| `[read_time]` | `kar_read_time_shortcode()` | زمان مطالعه — پارامترهای `wpm`, `label`, `icon`, `min` |
| `[nias_inventory_progress_bar]` | `nias_inventory_progress_bar_with_timer()` | نوار موجودی + تایمر معکوس |
| `[my_merged_comments ids="1,2"]` | `mk_merged_comments_shortcode()` | کامنت‌های ترکیبی چند صفحه |
| `[carno_toc]` | `carno_generate_toc()` | فهرست مطالب از H2 ها |
| `[box type="product" id="123"]` | `suggestion_box()` | باکس پیشنهاد محصول یا مقاله |
| `[carno_tip]` | `carno_tip_shortcode()` | باکس نکته (wrapper div) |

### سایر

- `carno_add_heading_ids()` — تزریق `id` به تگ‌های H2 برای لینک TOC
- `track_post_views()` — شمارش بازدید در `wp_footer` (فقط کاربران لاگین نشده)

---

## ۵. woo-pricing.php — قیمت‌گذاری ووکامرس

### شورتکدهای قیمت

| شورتکد | توضیح |
|---|---|
| `[variation_price_by_attr attr="val"]` | قیمت وارییشن بر اساس ویژگی |
| `[variation_price id="123"]` | قیمت وارییشن بر اساس ID |

### تخفیف session-based (`?special=1`)

```php
function carno_store_special_discount_flag_in_session()
function carno_apply_fixed_discount_for_specific_product($cart)
```

محصولات: ۱۳۹۲۸ (GDS) → ۲,۰۲۰,۰۰۰ تومان | ۱۳۵۳۴ (چک‌لیست) → ۱,۰۲۰,۰۰۰ | ۳۸۴۲۷ → ۶,۶۰۰,۰۰۰

### تخفیف پکیج

```php
function custom_package_fixed_price($cart)
```
خرید همزمان محصولات ۱۶۱۸۰ + ۱۳۵۳۴ → قیمت پکیج ۵,۰۰۰,۰۰۰ تومان

### پنهان کردن تخفیف در ساعت ۱۶–۱۷ تهران

```php
function carno_dynamic_fixed_price($price, $product)   // بازگشت قیمت عادی
function carno_hide_sale_flash($is_on_sale, $product)  // حذف لیبل حراج
function carno_hide_timer_css()                         // مخفی کردن ویجت تایمر
```

### نمایش رایگان

```php
function carno_show_free_when_zero_price($price, $product)  // نمایش "💥رایگان💥"
```

---

## ۶. woo-orders.php — مدیریت سفارشات

- `auto_complete_all_orders($order_id)` — تبدیل خودکار `processing` به `completed`
- `filter_canceled_orders_from_my_account($args)` — پنهان کردن سفارشات لغو شده
- `disable_my_account_orders_pagination($args)` — نمایش همه سفارشات بدون صفحه‌بندی
- `customize_my_orders_columns($columns)` — حذف ستون "مجموع"، اضافه کردن ستون "محصولات"
- `add_order_products_column_woo($columns)` — ستون محصولات در پنل ادمین (سازگار با HPOS)

---

## ۷. woo-checkout.php — چک‌اوت

- `customize_checkout_fields($fields)` — فقط نام کامل + موبایل (+ آدرس/کدپستی اگر محصول ۱۳۵۳۴ در سبد باشد)
- `split_full_name_before_save($posted_data)` — تقسیم نام کامل به first/last name
- `populate_full_name_field($value, $input)` — پر کردن فیلد نام در ویرایش
- `change_coupon_label_text($label, $coupon)` — برچسب کوپن → «سود شما از این خرید»

---

## ۸. woo-campaign.php — کمپین special_buy

سیستم لینک‌های اسپات برای فروش مستقیم با قیمت ثابت.

### URL ورودی
```
yoursite.com/product/?special_buy=1&pid=PRODUCT_ID&vid=VARIATION_ID
```

### توابع

| تابع | عملکرد |
|---|---|
| `get_sepehr_final_prices()` | آرایه قیمت‌های ثابت محصولات |
| `handle_direct_purchase_link()` | خالی کردن سبد، افزودن محصول، ریدایرکت به checkout |
| `apply_fixed_price_logic($cart)` | اعمال قیمت ثابت در سبد |
| `block_coupons_for_fixed_price(...)` | جلوگیری از اعمال کوپن |
| `carno_save_special_buy_to_order(...)` | ذخیره متادیتای کمپین در سفارش |
| `carno_add_order_special_column(...)` | ستون «کمپین ویژه» در ادمین |
| `carno_apply_special_buy_filter_legacy/hpos(...)` | فیلتر سفارشات کمپین |

**محصولات کمپین:** ۴۱۰۷۸ (کره آنلاین) | ۳۸۴۲۷ (چینی آنلاین) | ۱۸۵۳۵ (داخلی) | ۱۶۱۸۰ (زبان فنی) | ۱۳۹۲۸ (GDS) | ۱۳۵۳۴ (کتاب) | ۴۱۴۶۲ (فرمان برقی)

---

## ۹. qr-discount.php — سیستم تخفیف QR کد/کتاب

کاربرانی که از QR کد کتاب وارد می‌شوند (`?utm_source=book_qr`) تخفیف ۳۰ دقیقه‌ای دریافت می‌کنند.

### جریان کار

1. ورود با `?utm_source=book_qr` → کوکی `carno_book_ids` ست می‌شود (۳۰ دقیقه)
2. قیمت‌های ویژه روی محصول نمایش داده می‌شوند (`carno_final_price_logic`)
3. پیغام تبریک در `wp_footer` نمایش داده می‌شود (یکبار در session)

### توابع

```php
function carno_get_special_prices()       // آرایه قیمت‌های ویژه
function carno_is_discount_active()       // چک کوکی یا UTM
function carno_is_item_discounted($id)    // چک محصول خاص
function carno_final_price_logic(...)     // فیلتر قیمت (priority 999)
function carno_force_sale_ui(...)         // نمایش قیمت خط‌خورده
function carno_get_dynamic_price(...)     // قیمت داینامیک برای GF
```

**نکته:** آرایه `carno_get_special_prices()` و `get_sepehr_final_prices()` در `woo-campaign.php` هر دو قیمت یکسانی دارند اما سیستم‌های مجزا هستند.

---

## ۱۰. integrations.php — یکپارچه‌سازی‌ها

### Elementor

- تمپلیت `37026` قبل از جدول سفارش (در صفحه view-order)
- تمپلیت `31944` بعد از خرید دوره‌های حضوری
- تمپلیت `40177` بعد از خرید محصول `39576`
- باکس VIP برای وارییشن `41078` بعد از خرید

### Gravity Forms — محاسبه مجموع

`carno_trigger_gf_total_recalculation()` — بعد از لود صفحه محصول، change event روی price input فیلد محصول trigger می‌کنه تا GF مجموع رو recalculate کنه.

فیلد محصول باید نوع **Single Product** باشه. Parameter Name برای sub-field قیمت:
- فرم ۴۳ (آنلاین): `carno_online_price`
- فرم ۴۲ (حضوری): `carno_offline_price`

### Gravity Forms — سفارش ووکامرس از فرم‌های ۴۲ و ۴۳

جریان کار:

| مرحله | Hook | نتیجه |
|---|---|---|
| ارسال فرم | `gform_after_submission` | سفارش با وضعیت `pending` ساخته می‌شود |
| پرداخت موفق | `gform_post_payment_completed` | وضعیت → `completed` |
| پرداخت ناموفق | `gform_post_payment_failed` | وضعیت → `cancelled` |

آیدی سفارش با `gform_update_meta` در entry ذخیره می‌شود تا hook های بعدی بتوانند آن را پیدا کنند.

- فرم `21` — پر کردن چک‌باکس با محصولات خریداری شده کاربر (کش 1 ساعته)
- فرم `22` — تغییر متن دکمه «بعدی» به «دانلود فیلم وبینار»

### Rank Math

- `rank_math/frontend/breadcrumb/items` — مسیر نان مقالات: آکادمی کارنو > مقالات > عنوان
- `rank_math/snippet/rich_snippet_product_entity` — واحد پول IRT → IRR، ضرب قیمت در ۱۰

### Voorodak

- `sync_voorodak_phone_to_billing_phone()` — همگام‌سازی موبایل هنگام login/register/profile_update

---

## ۱۱. ui.php — رابط کاربری

- `carno_ultimate_favicon_switcher()` — فاوآیکون داینامیک بر اساس dark/light mode مرورگر
- `remove_add_to_cart_parameter_after_redirect()` — حذف `?add-to-cart=` از URL با JS
- Floating CTA script — نمایش باکس CTA لندینگ بعد از اسکرول از hero section (فقط دسکتاپ)

---

## افزونه‌های مورد نیاز

| افزونه | کاربرد |
|---|---|
| WooCommerce | تمام قابلیت‌های فروشگاهی |
| Elementor | رندر تمپلیت‌ها در صفحه سفارش |
| Gravity Forms | فرم‌های ۲۱، ۲۲، ۴۲، ۴۳ |
| Rank Math SEO | breadcrumb و schema |
| Voorodak | ورود با موبایل |

## تمپلیت‌های Elementor

| ID | محل استفاده |
|---|---|
| `37026` | صفحه مشاهده سفارش (درخواست لایسنس) |
| `31944` | صفحه تشکر — دوره‌های حضوری |
| `40177` | صفحه تشکر — محصول ۳۹۵۷۶ |

## فرم‌های Gravity Forms

| ID | کاربرد |
|---|---|
| `21` | درخواست لایسنس — فیلد ۸ با محصولات خریداری شده پر می‌شود |
| `22` | فرم چندمرحله‌ای — دکمه «بعدی» تغییر نام دارد |
| `42` | ثبت‌نام دوره حضوری — ایجاد سفارش WooCommerce |
| `43` | ثبت‌نام دوره آنلاین — ایجاد سفارش WooCommerce |
