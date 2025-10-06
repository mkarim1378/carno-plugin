# مستندات فنی افزونه شخصی‌سازی کارنو

## اطلاعات کلی افزونه

-   **نام افزونه**: Carno Customization Plugin
-   **نسخه**: 1.13.9
-   **نویسنده**: سپهر علیمحمدی
-   **آدرس**: https://sepehralimohammadi.com/
-   **توضیحات**: افزونه شخصی‌سازی برای وبسایت آکادمی کارنو (آموزشگاه برق خودرو مهندس سپهر علیمحمدی)

## ساختار کلی افزونه

این افزونه شامل 1336 خط کد PHP است که در یک فایل واحد (`carno-plugin.php`) قرار دارد و شامل قابلیت‌های زیر است:

## 1. مدیریت قیمت‌گذاری محصولات متغیر

### 1.1 شورتکد نمایش قیمت بر اساس ویژگی‌ها

```php
add_shortcode( 'variation_price_by_attr', function( $atts ) {
    // کد مربوط به نمایش قیمت وارییشن بر اساس ویژگی‌ها
});
```

**عملکرد**: نمایش قیمت وارییشن محصولات متغیر بر اساس ویژگی‌های انتخاب شده

### 1.2 شورتکد نمایش قیمت بر اساس ID

```php
add_shortcode( 'variation_price', 'show_variation_price_by_id' );
```

**عملکرد**: نمایش قیمت وارییشن خاص بر اساس شناسه وارییشن

## 2. سیستم تخفیف‌های ویژه

### 2.1 ذخیره فلگ تخفیف ویژه در سشن

```php
function carno_store_special_discount_flag_in_session()
```

**عملکرد**:

-   دریافت پارامتر `special` از URL
-   ذخیره وضعیت تخفیف ویژه در سشن ووکامرس
-   پشتیبانی از مقادیر `1` (فعال) و `0` (غیرفعال)

### 2.2 اعمال تخفیف ثابت برای محصولات خاص

```php
function carno_apply_fixed_discount_for_specific_product( $cart )
```

**محصولات تحت پوشش تخفیف**:

-   محصول ID: 13928 - تخفیف 2,020,000 تومان (تخفیف ویژه خریداران GDS)
-   محصول ID: 33429 - تخفیف 1,020,000 تومان (تخفیف ویژه)
-   محصول ID: 13534 - تخفیف 1,020,000 تومان (تخفیف ویژه دریافت کنندگان چک لیست پذیرش)

## 3. مدیریت کاربران و سفارشات مهمان

### 3.1 بررسی وجود کاربر بر اساس شماره تلفن

```php
function user_exists_by_phone($phone)
```

**عملکرد**:

-   نرمال‌سازی شماره تلفن (10 رقم آخر)
-   جستجو در username و usermeta
-   بررسی فیلدهای `billing_phone` و `digits_phone_no`

### 3.2 ساخت کاربر از سفارش مهمان

```php
function create_user_from_guest_order_by_phone_v2($order_id)
```

**عملکرد**:

-   بررسی سفارشات مهمان (customer_id = 0)
-   جستجوی کاربر موجود بر اساس شماره تلفن
-   اتصال سفارش به کاربر موجود یا ساخت کاربر جدید
-   تولید username بر اساس شماره تلفن
-   تولید ایمیل خودکار

### 3.3 اتصال سفارشات مهمان به حساب کاربری

```php
function connect_guest_orders_by_phone_to_user_account($user_id)
```

**عملکرد**:

-   اتصال خودکار سفارشات مهمان بعد از لاگین
-   نرمال‌سازی شماره تلفن برای تطبیق
-   اتصال به هوک‌های `voorodak_after_do_login` و `voorodak_after_do_register`

## 4. سیستم محافظت از محتوا

### 4.1 محافظت از صفحه محتوای دوره

```php
function km_protect_course_content_page()
```

**عملکرد**:

-   بررسی لاگین بودن کاربر
-   بررسی خرید محصولات دوره (IDs: 33429, 33496, 33497)
-   ریدایرکت به صفحه لاگین یا صفحه اصلی دوره

### 4.2 ریدایرکت بعد از خرید

```php
function redirect_to_course_contents_after_purchase($order_id)
```

**عملکرد**:

-   ریدایرکت خودکار به صفحه محتوای دوره بعد از خرید
-   تغییر لینک "مشاهده سفارش" در حساب کاربری
-   تغییر دکمه خرید به "مشاهده دوره" برای خریداران

## 5. سیستم نمایش محتوا

### 5.1 نمایش تمپلیت درخواست لایسنس

```php
function display_elementor_template_before_order_details_table( $order )
```

**عملکرد**: نمایش تمپلیت Elementor با ID 37026 در صفحه مشاهده سفارش

### 5.2 پر کردن فرم گرویتی با محصولات خریداری شده

```php
function populate_products_checkbox( $form )
```

**عملکرد**:

-   پر کردن فیلد چک‌باکس با محصولات خریداری شده کاربر
-   فیلتر کردن سفارشات تکمیل شده
-   استفاده در فرم گرویتی با ID 21

## 6. سیستم آنالیتیکس و رهگیری

### 6.1 اسکریپت یکتانت

```php
add_action('wp_head', function() {
    // اسکریپت یکتانت برای رهگیری
});
```

**عملکرد**: اضافه کردن اسکریپت رهگیری یکتانت به هدر سایت

### 6.2 رهگیری بازدید صفحات

```php
function track_post_views()
```

**عملکرد**:

-   شمارش بازدید صفحات و پست‌ها
-   ذخیره در متای `post_views`

## 7. سیستم موجودی و پیشرفت فروش

### 7.1 نوار پیشرفت موجودی با تایمر

```php
function nias_inventory_progress_bar_with_timer($atts)
```

**عملکرد**:

-   نمایش نوار پیشرفت فروش محصول
-   نمایش تایمر معکوس
-   محاسبه درصد فروش بر اساس موجودی اولیه
-   شورتکد: `[nias_inventory_progress_bar]`

### 7.2 ذخیره موجودی اولیه

```php
function nias_save_original_stock($post_id)
```

**عملکرد**: ذخیره موجودی اولیه محصول در متای `_original_stock`

## 8. سیستم کامنت‌ها

### 8.1 شورتکد کامنت‌های ادغام شده

```php
function mk_merged_comments_shortcode( $atts )
```

**عملکرد**:

-   نمایش کامنت‌های چندین صفحه در یک مکان
-   حذف لینک تاریخ و ریپلای
-   شورتکد: `[my_merged_comments ids="1,2,3"]`

## 9. سیستم تخفیف و قیمت‌گذاری

### 9.1 محاسبه درصد تخفیف

```php
function save_discount_percentage_meta( $post_id )
```

**عملکرد**:

-   محاسبه و ذخیره درصد تخفیف محصولات
-   ذخیره در متای `_discount_percentage`

### 9.2 تغییر برچسب کوپن

```php
function change_coupon_label_text( $label, $coupon )
```

**عملکرد**: تغییر برچسب کوپن به "سود شما از این خرید"

## 10. سیستم فرم‌ها و چک‌اوت

### 10.1 شخصی‌سازی فیلدهای چک‌اوت

```php
function customize_checkout_fields($fields)
```

**عملکرد**:

-   حذف فیلدهای غیرضروری (شرکت، آدرس 2، ایمیل)
-   ترکیب نام و نام خانوادگی
-   حذف فیلدهای آدرس برای محصولات مجازی

### 10.2 تقسیم نام کامل

```php
function split_full_name_before_save($posted_data)
```

**عملکرد**: تقسیم نام کامل به نام و نام خانوادگی هنگام ذخیره

## 11. سیستم امنیتی و بهینه‌سازی

### 11.1 مسدود کردن درخواست‌های خارجی

```php
function BlockExternalHostRequests ($false, $parsed_args, $url)
```

**عملکرد**:

-   مسدود کردن درخواست‌های به هاست‌های خارجی
-   بهبود سرعت بارگذاری
-   لیست هاست‌های مسدود شده شامل: rankmath.com, googleapis.com, github.com, و غیره

### 11.2 غیرفعال کردن بلاک ادیتور

```php
add_filter( 'use_block_editor_for_post', '__return_false' );
add_filter( 'use_widgets_block_editor', '__return_false' );
```

### 11.3 حذف استایل‌های غیرضروری

```php
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'global-styles' );
});
```

## 12. سیستم تشخیص موقعیت جغرافیایی

### 12.1 تشخیص کاربران ایرانی

```php
function isUserFromIran()
```

**عملکرد**:

-   تشخیص IP کاربر
-   استفاده از سرویس ip-api.com
-   نمایش هشدار برای کاربران غیرایرانی یا با VPN

## 13. سیستم سفارشات

### 13.1 فیلتر سفارشات لغو شده

```php
function filter_canceled_orders_from_my_account($args)
```

**عملکرد**: حذف سفارشات لغو شده از صفحه سفارشات کاربر

### 13.2 غیرفعال کردن صفحه‌بندی سفارشات

```php
function disable_my_account_orders_pagination($args)
```

**عملکرد**: نمایش تمام سفارشات بدون صفحه‌بندی

### 13.3 شخصی‌سازی ستون‌های سفارشات

```php
function customize_my_orders_columns($columns)
```

**عملکرد**:

-   حذف ستون "مجموع"
-   اضافه کردن ستون "محصولات"
-   نمایش نام محصولات در ستون جدید

## 14. سیستم پیشنهاد محصولات

### 14.1 شورتکد پیشنهاد محصول/مقاله

```php
function suggestion_box($atts)
```

**عملکرد**:

-   نمایش جعبه پیشنهاد محصول یا مقاله
-   پشتیبانی از تخفیف و تصویر
-   شورتکد: `[box type="product" id="123"]`

## 15. سیستم SEO و Schema

### 15.1 اصلاح واحد پول در Schema

```php
add_filter( 'rank_math/snippet/rich_snippet_product_entity', function( $entity )
```

**عملکرد**: تغییر واحد پول از IRT به IRR در Schema محصولات

### 15.2 شخصی‌سازی Breadcrumb

```php
add_filter( 'rank_math/frontend/breadcrumb/items', function( $crumbs, $class )
```

**عملکرد**: شخصی‌سازی مسیر نان برای مقالات

## 16. سیستم همگام‌سازی شماره تلفن

### 16.1 همگام‌سازی شماره تلفن

```php
function sync_voorodak_phone_to_billing_phone($user_id)
```

**عملکرد**:

-   همگام‌سازی شماره تلفن بین افزونه‌های مختلف
-   اولویت: Voorodak > Digits > billing_phone

## 17. سیستم مدیریت محتوا

### 17.1 فهرست مطالب خودکار

```php
function carno_generate_toc($atts)
```

**عملکرد**:

-   تولید فهرست مطالب از تگ‌های H2
-   شورتکد: `[carno_toc]`
-   اضافه کردن ID به هدینگ‌ها

### 17.2 شمارش کامنت‌های کاربر

```php
function wpheart_update_comments_count($user_login, $user)
```

**عملکرد**: شمارش و ذخیره تعداد کامنت‌های تایید شده کاربر

## 18. سیستم لاگین پایدار

### 18.1 تمدید زمان لاگین

```php
function keep_user_logged_in_for_1_year($expirein)
```

**عملکرد**: تمدید زمان لاگین کاربران به 1 سال

## 19. سیستم مدیریت URL

### 19.1 مسدود کردن URL های غیرمجاز

```php
add_action('template_redirect', function () {
    if (preg_match('/\.html$/i', $_SERVER['REQUEST_URI'])) {
        status_header(410);
        exit;
    }
});
```

**عملکرد**: مسدود کردن URL های با پسوند .html

### 19.2 مسدود کردن پارامترهای خاص

```php
add_action('template_redirect', function () {
    if (!empty($_SERVER['QUERY_STRING']) && preg_match('/^(?:o|b)(?:=|%3D)/i', $_SERVER['QUERY_STRING'])) {
        status_header(410);
        exit;
    }
});
```

## 20. سیستم نمایش محتوای سفارش

### 20.1 نمایش محتوای سفارش سفارشی

```php
function display_custom_order_content($order)
```

**عملکرد**:

-   نمایش باکس VIP برای محصولات خاص
-   نمایش فرم برای دوره‌های حضوری
-   تشخیص محصولات بر اساس ID و ویژگی‌ها

## 21. سیستم مدیریت فرم‌ها

### 21.1 تغییر دکمه فرم گرویتی

```php
function change_next_button_for_specific_form( $button, $form )
```

**عملکرد**: تغییر متن دکمه "بعدی" در فرم گرویتی ID 22

## نکات مهم برای توسعه‌دهندگان

### امنیت

-   تمام ورودی‌های کاربر باید sanitize شوند
-   استفاده از `esc_html()` و `esc_url()` برای خروجی
-   بررسی وجود توابع قبل از استفاده

### بهینه‌سازی

-   افزونه شامل سیستم مسدود کردن درخواست‌های خارجی است
-   حذف استایل‌ها و اسکریپت‌های غیرضروری
-   غیرفعال کردن بلاک ادیتور برای بهبود عملکرد

### سازگاری

-   سازگار با ووکامرس
-   سازگار با Elementor
-   سازگار با افزونه‌های ورودک و دیجیتس
-   سازگار با Rank Math SEO

### نگهداری

-   لاگ‌های خطا در `error_log` ذخیره می‌شوند
-   متا داده‌های سفارشی برای ذخیره اطلاعات اضافی
-   استفاده از هوک‌های وردپرس برای یکپارچگی

## فایل‌های وابسته

-   `carno-plugin.php` - فایل اصلی افزونه
-   تمپلیت‌های Elementor (ID: 37026, 31944)
-   فرم‌های گرویتی (ID: 21, 22)
