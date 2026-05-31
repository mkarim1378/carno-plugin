<?php
// ============================================================================
// صفحه تنظیمات ادمین افزونه کارنو
// ============================================================================

// مقادیر پیش‌فرض (همان مقادیر hardcode شده قبلی)
function carno_settings_defaults() {
    return [
        'campaign_prices' => [
            ['pid' => 41078, 'price' => 16000000],
            ['pid' => 38427, 'price' => 15000000],
            ['pid' => 18535, 'price' =>  7500000],
            ['pid' => 16180, 'price' =>  3800000],
            ['pid' => 13928, 'price' =>  3800000],
            ['pid' => 13534, 'price' =>  1980000],
            ['pid' => 41462, 'price' => 12000000],
        ],
        'qr_prices' => [
            ['pid' => 41078, 'price' =>  9800000],
            ['pid' => 38427, 'price' =>  9800000],
            ['pid' => 18535, 'price' =>  7500000],
            ['pid' => 16180, 'price' =>  3800000],
            ['pid' => 13928, 'price' =>  3800000],
            ['pid' => 13534, 'price' =>  1900000],
            ['pid' => 41462, 'price' =>  9800000],
            ['pid' => 42096, 'price' =>  1900000],
        ],
        'session_discounts' => [
            ['pid' => 13928, 'amount' => 2020000, 'label' => 'تخفیف ویژه خریداران GDS'],
            ['pid' => 13534, 'amount' => 1020000, 'label' => 'تخفیف ویژه دریافت کنندگان چک لیست پذیرش'],
            ['pid' => 38427, 'amount' => 6600000, 'label' => 'تخفیف ویژه دریافت کنندگان چک لیست پذیرش'],
        ],
        'qr_message' => 'تبریک! چون شما از همراهان کتاب آکادمی Carno هستید، «بیشترین تخفیف» ممکن به صورت خودکار برای شما اعمال شد. این فرصت فقط تا ۳۰ دقیقه دیگر معتبر است.',
    ];
}

add_action( 'admin_menu', 'carno_register_settings_menu' );
function carno_register_settings_menu() {
    add_menu_page(
        'تنظیمات کارنو',
        'کارنو',
        'manage_options',
        'carno-settings',
        'carno_render_settings_page',
        'dashicons-store',
        58
    );
}

// ذخیره تنظیمات
add_action( 'admin_post_carno_save_settings', 'carno_handle_save_settings' );
function carno_handle_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز' );
    check_admin_referer( 'carno_save_settings', 'carno_nonce' );

    // کمپین ویژه
    $campaign_prices = [];
    foreach ( (array) ( $_POST['cp_pid'] ?? [] ) as $i => $pid ) {
        $pid = absint( $pid );
        if ( $pid > 0 ) {
            $campaign_prices[] = [ 'pid' => $pid, 'price' => absint( $_POST['cp_price'][ $i ] ?? 0 ) ];
        }
    }
    update_option( 'carno_campaign_prices', $campaign_prices );
    update_option( 'carno_campaign_redirect_ids', array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( $_POST['carno_campaign_redirect_ids'] ?? '' ) ) ) ) ) );

    // تخفیف QR
    $qr_prices = [];
    foreach ( (array) ( $_POST['qr_pid'] ?? [] ) as $i => $pid ) {
        $pid = absint( $pid );
        if ( $pid > 0 ) {
            $qr_prices[] = [ 'pid' => $pid, 'price' => absint( $_POST['qr_price'][ $i ] ?? 0 ) ];
        }
    }
    update_option( 'carno_qr_prices', $qr_prices );
    update_option( 'carno_qr_utm_source',       sanitize_text_field( $_POST['carno_qr_utm_source']       ?? 'book_qr' ) );
    update_option( 'carno_qr_cookie_minutes',   absint( $_POST['carno_qr_cookie_minutes']                ?? 30 ) );
    update_option( 'carno_qr_discount_message', sanitize_textarea_field( $_POST['carno_qr_discount_message'] ?? '' ) );

    // تخفیف‌های سبد
    $sd = [];
    foreach ( (array) ( $_POST['sd_pid'] ?? [] ) as $i => $pid ) {
        $pid = absint( $pid );
        if ( $pid > 0 ) {
            $sd[] = [
                'pid'    => $pid,
                'amount' => absint( $_POST['sd_amount'][ $i ] ?? 0 ),
                'label'  => sanitize_text_field( $_POST['sd_label'][ $i ] ?? '' ),
            ];
        }
    }
    update_option( 'carno_session_discounts', $sd );
    update_option( 'carno_package_product_ids', array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( $_POST['carno_package_product_ids'] ?? '' ) ) ) ) ) );
    update_option( 'carno_package_final_price', absint( $_POST['carno_package_final_price'] ?? 0 ) );
    update_option( 'carno_hide_price_hour',     absint( $_POST['carno_hide_price_hour']     ?? 16 ) );
    update_option( 'carno_timer_css_class',     sanitize_text_field( $_POST['carno_timer_css_class'] ?? 'daily-timer' ) );

    // محصولات و تمپلیت‌ها
    update_option( 'carno_template_license',     absint( $_POST['carno_template_license']     ?? 0 ) );
    update_option( 'carno_vip_product_id',       absint( $_POST['carno_vip_product_id']       ?? 0 ) );
    update_option( 'carno_vip_variation_id',     absint( $_POST['carno_vip_variation_id']     ?? 0 ) );
    update_option( 'carno_onsite_product_ids',   array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( $_POST['carno_onsite_product_ids'] ?? '' ) ) ) ) ) );
    update_option( 'carno_template_onsite_form', absint( $_POST['carno_template_onsite_form'] ?? 0 ) );
    update_option( 'carno_karamp_product_id',    absint( $_POST['carno_karamp_product_id']    ?? 0 ) );
    update_option( 'carno_template_karamp',      absint( $_POST['carno_template_karamp']      ?? 0 ) );

    // چک‌اوت
    update_option( 'carno_address_required_products', array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( $_POST['carno_address_required_products'] ?? '' ) ) ) ) ) );
    update_option( 'carno_coupon_label', sanitize_text_field( $_POST['carno_coupon_label'] ?? '' ) );

    $active_tab = sanitize_key( $_POST['_active_tab'] ?? 'campaign' );
    wp_redirect( add_query_arg( [ 'page' => 'carno-settings', 'saved' => '1', 'tab' => $active_tab ], admin_url( 'admin.php' ) ) );
    exit;
}

// رندر صفحه
function carno_render_settings_page() {
    $d = carno_settings_defaults();

    $campaign_prices          = get_option( 'carno_campaign_prices',          $d['campaign_prices'] );
    $campaign_redirect_ids    = get_option( 'carno_campaign_redirect_ids',    [] );
    $qr_prices                = get_option( 'carno_qr_prices',                $d['qr_prices'] );
    $qr_utm                   = get_option( 'carno_qr_utm_source',            'book_qr' );
    $qr_cookie_minutes        = get_option( 'carno_qr_cookie_minutes',        30 );
    $qr_message               = get_option( 'carno_qr_discount_message',      $d['qr_message'] );
    $session_discounts        = get_option( 'carno_session_discounts',        $d['session_discounts'] );
    $package_product_ids      = get_option( 'carno_package_product_ids',      [ 16180, 13534 ] );
    $package_final_price      = get_option( 'carno_package_final_price',      5000000 );
    $hide_price_hour          = get_option( 'carno_hide_price_hour',          16 );
    $timer_css_class          = get_option( 'carno_timer_css_class',          'daily-timer' );
    $template_license         = get_option( 'carno_template_license',         37026 );
    $vip_product_id           = get_option( 'carno_vip_product_id',           41077 );
    $vip_variation_id         = get_option( 'carno_vip_variation_id',         41078 );
    $onsite_product_ids       = get_option( 'carno_onsite_product_ids',       [ 13832, 14259 ] );
    $template_onsite_form     = get_option( 'carno_template_onsite_form',     31944 );
    $karamp_product_id        = get_option( 'carno_karamp_product_id',        39576 );
    $template_karamp          = get_option( 'carno_template_karamp',          40177 );
    $address_required_prods   = get_option( 'carno_address_required_products', [ 13534 ] );
    $coupon_label             = get_option( 'carno_coupon_label',             'سود شما از این خرید' );

    $active_tab = sanitize_key( $_GET['tab'] ?? 'campaign' );
    $saved      = isset( $_GET['saved'] );

    $tabs = [
        'campaign' => 'کمپین ویژه',
        'qr'       => 'تخفیف QR / کتاب',
        'cart'     => 'تخفیف‌های سبد',
        'products' => 'محصولات و تمپلیت‌ها',
        'checkout' => 'چک‌اوت',
    ];
    ?>
    <div class="wrap carno-wrap" dir="rtl">
        <h1>تنظیمات افزونه کارنو</h1>

        <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible"><p>تنظیمات با موفقیت ذخیره شد.</p></div>
        <?php endif; ?>

        <nav class="carno-tab-nav">
            <?php foreach ( $tabs as $key => $label ) : ?>
                <button type="button" class="carno-tab-btn<?php echo $key === $active_tab ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
                    <?php echo esc_html( $label ); ?>
                </button>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'carno_save_settings', 'carno_nonce' ); ?>
            <input type="hidden" name="action" value="carno_save_settings">
            <input type="hidden" name="_active_tab" id="carno_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

            <?php // ── TAB: کمپین ویژه ── ?>
            <div class="carno-tab-panel<?php echo $active_tab === 'campaign' ? ' is-active' : ''; ?>" data-panel="campaign">
                <h2>قیمت‌های کمپین ویژه (لینک‌های اسپات)</h2>
                <p class="description">قیمت ثابتی که با پارامتر <code>?special_buy=1&amp;pid=X</code> برای هر محصول / وارییشن اعمال می‌شود.</p>

                <?php carno_render_price_table( 'campaign-price-table', 'cp_pid', 'cp_price', $campaign_prices ); ?>
                <button type="button" class="button carno-add-row" data-table="campaign-price-table"
                    data-cols='[{"name":"cp_pid[]","type":"number","cls":"small-text"},{"name":"cp_price[]","type":"number","cls":"regular-text"}]'>
                    + افزودن ردیف
                </button>

                <hr>
                <h3>محصولات Redirect-Only</h3>
                <p class="description">شناسه محصولاتی که به جای افزودن به سبد، به صفحه محصول ریدایرکت می‌شوند (با کاما جدا کنید).</p>
                <input type="text" name="carno_campaign_redirect_ids"
                    value="<?php echo esc_attr( implode( ', ', $campaign_redirect_ids ) ); ?>"
                    class="large-text" placeholder="مثال: 12345, 67890">
            </div>

            <?php // ── TAB: تخفیف QR ── ?>
            <div class="carno-tab-panel<?php echo $active_tab === 'qr' ? ' is-active' : ''; ?>" data-panel="qr">
                <h2>سیستم تخفیف QR کد / کتاب</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">UTM Source</th>
                        <td><input type="text" name="carno_qr_utm_source" value="<?php echo esc_attr( $qr_utm ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">مدت اعتبار کوکی (دقیقه)</th>
                        <td><input type="number" name="carno_qr_cookie_minutes" value="<?php echo esc_attr( $qr_cookie_minutes ); ?>" class="small-text" min="1"></td>
                    </tr>
                    <tr>
                        <th scope="row">متن پیغام تخفیف</th>
                        <td><textarea name="carno_qr_discount_message" rows="4" class="large-text"><?php echo esc_textarea( $qr_message ); ?></textarea></td>
                    </tr>
                </table>

                <h3>قیمت‌های ویژه QR</h3>
                <?php carno_render_price_table( 'qr-price-table', 'qr_pid', 'qr_price', $qr_prices ); ?>
                <button type="button" class="button carno-add-row" data-table="qr-price-table"
                    data-cols='[{"name":"qr_pid[]","type":"number","cls":"small-text"},{"name":"qr_price[]","type":"number","cls":"regular-text"}]'>
                    + افزودن ردیف
                </button>
            </div>

            <?php // ── TAB: تخفیف‌های سبد ── ?>
            <div class="carno-tab-panel<?php echo $active_tab === 'cart' ? ' is-active' : ''; ?>" data-panel="cart">
                <h2>تخفیف‌های جلسه‌ای (Session Discounts)</h2>
                <p class="description">با فعال شدن <code>?special=1</code>، این مبالغ به صورت منفی به سبد اضافه می‌شوند.</p>

                <table class="wp-list-table widefat fixed striped carno-repeater-table" id="sd-table">
                    <thead>
                        <tr>
                            <th style="width:130px">شناسه محصول / وارییشن</th>
                            <th>مبلغ تخفیف (تومان)</th>
                            <th>لیبل تخفیف</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $session_discounts as $row ) : ?>
                        <tr>
                            <td><input type="number" name="sd_pid[]"    value="<?php echo esc_attr( $row['pid'] ); ?>"    class="small-text"></td>
                            <td><input type="number" name="sd_amount[]" value="<?php echo esc_attr( $row['amount'] ); ?>" class="regular-text"></td>
                            <td><input type="text"   name="sd_label[]"  value="<?php echo esc_attr( $row['label'] ); ?>"  class="regular-text"></td>
                            <td><button type="button" class="button carno-remove-row">حذف</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="button carno-add-row" data-table="sd-table"
                    data-cols='[{"name":"sd_pid[]","type":"number","cls":"small-text"},{"name":"sd_amount[]","type":"number","cls":"regular-text"},{"name":"sd_label[]","type":"text","cls":"regular-text"}]'>
                    + افزودن ردیف
                </button>

                <hr>
                <h3>تخفیف پکیج</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">شناسه محصولات پکیج (با کاما جدا کنید)</th>
                        <td><input type="text" name="carno_package_product_ids" value="<?php echo esc_attr( implode( ', ', $package_product_ids ) ); ?>" class="regular-text" placeholder="مثال: 16180, 13534"></td>
                    </tr>
                    <tr>
                        <th scope="row">قیمت نهایی پکیج (تومان)</th>
                        <td><input type="number" name="carno_package_final_price" value="<?php echo esc_attr( $package_final_price ); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <hr>
                <h3>پنهان‌سازی قیمت تخفیف‌دار</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">ساعت پنهان‌سازی (۰ تا ۲۳)</th>
                        <td>
                            <input type="number" name="carno_hide_price_hour" value="<?php echo esc_attr( $hide_price_hour ); ?>" class="small-text" min="0" max="23">
                            <p class="description">در این ساعت قیمت تخفیف پنهان می‌شود و فقط قیمت اصلی نمایش داده می‌شود. (<code>-1</code> برای غیرفعال کردن)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">کلاس CSS تایمر</th>
                        <td>
                            <input type="text" name="carno_timer_css_class" value="<?php echo esc_attr( $timer_css_class ); ?>" class="regular-text" placeholder="daily-timer">
                            <p class="description">نام کلاس بدون نقطه — المانی با این کلاس در ساعت تعیین‌شده مخفی می‌شود.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php // ── TAB: محصولات و تمپلیت‌ها ── ?>
            <div class="carno-tab-panel<?php echo $active_tab === 'products' ? ' is-active' : ''; ?>" data-panel="products">
                <h2>محصولات و تمپلیت‌های Elementor</h2>

                <h3>تمپلیت درخواست لایسنس</h3>
                <p class="description">قبل از جدول سفارش در صفحه «مشاهده سفارش» نمایش داده می‌شود.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">شناسه تمپلیت Elementor</th>
                        <td><input type="number" name="carno_template_license" value="<?php echo esc_attr( $template_license ); ?>" class="small-text"></td>
                    </tr>
                </table>

                <hr>
                <h3>باکس VIP کرهای</h3>
                <p class="description">پس از خرید این محصول / وارییشن، باکس لینک‌های VIP اینستاگرام و تلگرام در صفحه تشکر نمایش داده می‌شود.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">شناسه محصول VIP</th>
                        <td><input type="number" name="carno_vip_product_id" value="<?php echo esc_attr( $vip_product_id ); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">شناسه وارییشن VIP</th>
                        <td><input type="number" name="carno_vip_variation_id" value="<?php echo esc_attr( $vip_variation_id ); ?>" class="small-text"></td>
                    </tr>
                </table>

                <hr>
                <h3>فرم ثبت‌نام دوره حضوری</h3>
                <p class="description">پس از خرید این محصولات (یا وارییشن با attribute «حضوری»)، فرم ثبت‌نام نمایش داده می‌شود.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">شناسه محصولات (با کاما جدا کنید)</th>
                        <td><input type="text" name="carno_onsite_product_ids" value="<?php echo esc_attr( implode( ', ', $onsite_product_ids ) ); ?>" class="regular-text" placeholder="مثال: 13832, 14259"></td>
                    </tr>
                    <tr>
                        <th scope="row">شناسه تمپلیت فرم حضوری</th>
                        <td><input type="number" name="carno_template_onsite_form" value="<?php echo esc_attr( $template_onsite_form ); ?>" class="small-text"></td>
                    </tr>
                </table>

                <hr>
                <h3>باکس پشتیبانی Karamp</h3>
                <p class="description">پس از خرید محصول Karamp، این تمپلیت در صفحه تشکر نمایش داده می‌شود.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">شناسه محصول Karamp</th>
                        <td><input type="number" name="carno_karamp_product_id" value="<?php echo esc_attr( $karamp_product_id ); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">شناسه تمپلیت Karamp</th>
                        <td><input type="number" name="carno_template_karamp" value="<?php echo esc_attr( $template_karamp ); ?>" class="small-text"></td>
                    </tr>
                </table>
            </div>

            <?php // ── TAB: چک‌اوت ── ?>
            <div class="carno-tab-panel<?php echo $active_tab === 'checkout' ? ' is-active' : ''; ?>" data-panel="checkout">
                <h2>تنظیمات چک‌اوت</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">محصولات نیازمند آدرس (با کاما جدا کنید)</th>
                        <td>
                            <input type="text" name="carno_address_required_products"
                                value="<?php echo esc_attr( implode( ', ', $address_required_prods ) ); ?>"
                                class="regular-text" placeholder="مثال: 13534">
                            <p class="description">وقتی هریک از این محصولات در سبد باشد، فیلدهای آدرس پستی و کد پستی اجباری می‌شوند.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">لیبل کوپن در سبد خرید</th>
                        <td><input type="text" name="carno_coupon_label" value="<?php echo esc_attr( $coupon_label ); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>

            <div class="carno-save-bar">
                <input type="submit" class="button button-primary button-large" value="ذخیره تنظیمات">
            </div>
        </form>
    </div>

    <style>
    .carno-wrap { max-width: 980px; font-family: inherit; }
    .carno-tab-nav { display: flex; gap: 2px; border-bottom: 1px solid #c3c4c7; margin-bottom: 0; }
    .carno-tab-btn {
        background: #f0f0f1; border: 1px solid #c3c4c7; border-bottom: none;
        padding: 8px 18px; cursor: pointer; font-size: 14px; font-family: inherit;
        border-radius: 3px 3px 0 0; color: #50575e; line-height: 1.4;
    }
    .carno-tab-btn:hover { background: #f6f7f7; }
    .carno-tab-btn.is-active {
        background: #fff; color: #1d2327; font-weight: 600;
        margin-bottom: -1px; padding-bottom: 9px;
    }
    .carno-tab-panel { display: none; background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 24px 28px; }
    .carno-tab-panel.is-active { display: block; }
    .carno-save-bar { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 14px 28px; }
    .carno-repeater-table { margin-bottom: 8px; }
    .carno-repeater-table td, .carno-repeater-table th { padding: 6px 8px; }
    .carno-repeater-table input { width: 100%; box-sizing: border-box; }
    .carno-wrap .button.carno-add-row { margin-top: 8px; }
    </style>

    <script>
    (function () {
        // تب‌ها
        var activeTab = document.getElementById('carno_active_tab').value;

        function switchTab(tab) {
            document.querySelectorAll('.carno-tab-btn').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.dataset.tab === tab);
            });
            document.querySelectorAll('.carno-tab-panel').forEach(function (panel) {
                panel.classList.toggle('is-active', panel.dataset.panel === tab);
            });
            document.getElementById('carno_active_tab').value = tab;
        }

        document.querySelectorAll('.carno-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { switchTab(this.dataset.tab); });
        });

        // حذف ردیف
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('carno-remove-row')) {
                e.target.closest('tr').remove();
            }
        });

        // افزودن ردیف
        document.querySelectorAll('.carno-add-row').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tableId = this.dataset.table;
                var cols    = JSON.parse(this.dataset.cols);
                var tbody   = document.querySelector('#' + tableId + ' tbody');
                var tr      = document.createElement('tr');

                cols.forEach(function (col) {
                    var td    = document.createElement('td');
                    var input = document.createElement('input');
                    input.type      = col.type;
                    input.name      = col.name;
                    input.className = col.cls;
                    input.style.width = '100%';
                    input.style.boxSizing = 'border-box';
                    td.appendChild(input);
                    tr.appendChild(td);
                });

                var tdDel  = document.createElement('td');
                var btnDel = document.createElement('button');
                btnDel.type      = 'button';
                btnDel.className = 'button carno-remove-row';
                btnDel.textContent = 'حذف';
                tdDel.appendChild(btnDel);
                tr.appendChild(tdDel);
                tbody.appendChild(tr);
                tr.querySelector('input').focus();
            });
        });
    })();
    </script>
    <?php
}

// تابع کمکی: رندر جدول قیمت دو ستونه (pid / price)
function carno_render_price_table( $table_id, $pid_field, $price_field, $rows ) {
    ?>
    <table class="wp-list-table widefat fixed striped carno-repeater-table" id="<?php echo esc_attr( $table_id ); ?>">
        <thead>
            <tr>
                <th style="width:150px">شناسه محصول / وارییشن</th>
                <th>قیمت (تومان)</th>
                <th style="width:60px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $rows as $row ) : ?>
            <tr>
                <td><input type="number" name="<?php echo esc_attr( $pid_field ); ?>[]"   value="<?php echo esc_attr( $row['pid'] ); ?>"   class="small-text"></td>
                <td><input type="number" name="<?php echo esc_attr( $price_field ); ?>[]" value="<?php echo esc_attr( $row['price'] ); ?>" class="regular-text"></td>
                <td><button type="button" class="button carno-remove-row">حذف</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
