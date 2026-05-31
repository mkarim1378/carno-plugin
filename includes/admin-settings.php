<?php
// ============================================================================
// صفحه تنظیمات ادمین افزونه کارنو
// ============================================================================

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
        'packages' => [
            ['products' => [16180, 13534], 'price' => 5000000],
        ],
        'qr_message' => 'تبریک! چون شما از همراهان کتاب آکادمی Carno هستید، «بیشترین تخفیف» ممکن به صورت خودکار برای شما اعمال شد. این فرصت فقط تا ۳۰ دقیقه دیگر معتبر است.',
        'template_rules' => [
            ['template_id' => 37026, 'mode' => 'blacklist', 'products' => []],
        ],
    ];
}

// ============================================================================
// بارگذاری اطلاعات محصولات (با کش static)
function carno_get_products_data() {
    static $data = null;
    if ( $data !== null ) return $data;

    $products = wc_get_products( [
        'status'  => 'publish',
        'limit'   => -1,
        'type'    => [ 'simple', 'variable' ],
        'orderby' => 'title',
        'order'   => 'ASC',
        'return'  => 'objects',
    ] );

    $data = [];
    foreach ( $products as $product ) {
        $item = [ 'id' => $product->get_id(), 'label' => $product->get_name(), 'vars' => [] ];
        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_available_variations() as $v ) {
                $attrs = implode( ' / ', array_filter( $v['attributes'] ) );
                $item['vars'][] = [
                    'id'    => $v['variation_id'],
                    'label' => $attrs ?: '#' . $v['variation_id'],
                ];
            }
        }
        $data[] = $item;
    }
    return $data;
}

// ============================================================================
// رندر select محصول
// $mode: 'products' | 'with_vars' | 'vars_only'
function carno_product_select( $name, $selected, $products, $mode = 'with_vars', $style = '' ) {
    $selected = (int) $selected;
    $extra    = $style ? ' style="' . esc_attr( $style ) . '"' : '';
    echo '<select name="' . esc_attr( $name ) . '" class="carno-product-select"' . $extra . '>';
    echo '<option value="">-- انتخاب محصول --</option>';

    foreach ( $products as $p ) {
        $has_vars = ! empty( $p['vars'] );

        if ( $mode === 'vars_only' ) {
            if ( ! $has_vars ) continue;
            echo '<optgroup label="' . esc_attr( $p['label'] ) . '">';
            foreach ( $p['vars'] as $v ) {
                printf(
                    '<option value="%d"%s>%s - %s</option>',
                    $v['id'], selected( $selected, $v['id'], false ), esc_html( $p['label'] ), esc_html( $v['label'] )
                );
            }
            echo '</optgroup>';

        } elseif ( $mode === 'with_vars' && $has_vars ) {
            echo '<optgroup label="' . esc_attr( $p['label'] ) . '">';
            printf(
                '<option value="%d"%s>%s</option>',
                $p['id'], selected( $selected, $p['id'], false ), esc_html( $p['label'] )
            );
            foreach ( $p['vars'] as $v ) {
                printf(
                    '<option value="%d"%s>%s - %s</option>',
                    $v['id'], selected( $selected, $v['id'], false ), esc_html( $p['label'] ), esc_html( $v['label'] )
                );
            }
            echo '</optgroup>';

        } else {
            printf(
                '<option value="%d"%s>%s</option>',
                $p['id'], selected( $selected, $p['id'], false ), esc_html( $p['label'] )
            );
        }
    }

    echo '</select>';
}

// ============================================================================
// AJAX: خواندن اسم تمپلیت المنتور بر اساس ID
add_action( 'wp_ajax_carno_get_template_title', function() {
    check_ajax_referer( 'carno_tpl_title', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    $id   = absint( $_POST['id'] ?? 0 );
    $post = $id ? get_post( $id ) : null;
    $title = ( $post && $post->post_type === 'elementor_library' && $post->post_status === 'publish' )
        ? $post->post_title : '';
    wp_send_json_success( [ 'title' => $title ] );
} );

// ============================================================================
add_action( 'admin_menu', 'carno_register_settings_menu' );
function carno_register_settings_menu() {
    add_menu_page(
        'تنظیمات کارنو',
        'کارنو',
        'manage_options',
        'carno-settings',
        'carno_render_settings_page',
        'dashicons-store',
        3
    );
}

// ============================================================================
// ذخیره تنظیمات
add_action( 'admin_post_carno_save_settings', 'carno_handle_save_settings' );
function carno_handle_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز' );
    check_admin_referer( 'carno_save_settings', 'carno_nonce' );

    // کمپین ویژه — جدول قیمت
    $campaign_prices = [];
    foreach ( (array) ( $_POST['cp_pid'] ?? [] ) as $i => $pid ) {
        $pid = absint( $pid );
        if ( $pid > 0 ) {
            $campaign_prices[] = [ 'pid' => $pid, 'price' => absint( $_POST['cp_price'][ $i ] ?? 0 ) ];
        }
    }
    update_option( 'carno_campaign_prices', $campaign_prices );

    // کمپین ویژه — محصولات redirect-only
    $redir_ids = [];
    foreach ( (array) ( $_POST['redir_pid'] ?? [] ) as $pid ) {
        $pid = absint( $pid );
        if ( $pid > 0 ) $redir_ids[] = $pid;
    }
    update_option( 'carno_campaign_redirect_ids', $redir_ids );

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

    // تخفیف‌های جلسه‌ای
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

    // پکیج‌های تخفیف (چند پکیج مستقل)
    $packages = [];
    foreach ( (array) ( $_POST['pkg_price'] ?? [] ) as $i => $price ) {
        $products_raw = (array) ( $_POST['pkg_products'][ $i ] ?? [] );
        $prods        = array_values( array_filter( array_map( 'absint', $products_raw ) ) );
        if ( ! empty( $prods ) ) {
            $packages[] = [ 'products' => $prods, 'price' => absint( $price ) ];
        }
    }
    update_option( 'carno_packages', $packages );

    update_option( 'carno_hide_price_hour', absint( $_POST['carno_hide_price_hour'] ?? 16 ) );
    update_option( 'carno_timer_css_class', sanitize_text_field( $_POST['carno_timer_css_class'] ?? 'daily-timer' ) );

    // قوانین تمپلیت (بلک‌لیست / وایت‌لیست)
    $tpl_rules = [];
    foreach ( (array) ( $_POST['tpl_rule_tpl'] ?? [] ) as $i => $tpl ) {
        $tpl  = absint( $tpl );
        $mode = in_array( $_POST['tpl_rule_mode'][ $i ] ?? '', [ 'blacklist', 'whitelist' ] )
            ? sanitize_key( $_POST['tpl_rule_mode'][ $i ] )
            : 'blacklist';
        $prods = array_values( array_filter( array_map( 'absint', (array) ( $_POST['tpl_rule_products'][ $i ] ?? [] ) ) ) );
        if ( $tpl > 0 ) {
            $tpl_rules[] = [ 'template_id' => $tpl, 'mode' => $mode, 'products' => $prods ];
        }
    }
    update_option( 'carno_template_rules', $tpl_rules );

    // چک‌اوت
    $addr_ids = [];
    foreach ( (array) ( $_POST['addr_pid'] ?? [] ) as $pid ) {
        $pid = absint( $pid );
        if ( $pid > 0 ) $addr_ids[] = $pid;
    }
    update_option( 'carno_address_required_products', $addr_ids );
    update_option( 'carno_coupon_label', sanitize_text_field( $_POST['carno_coupon_label'] ?? '' ) );

    $active_tab = sanitize_key( $_POST['_active_tab'] ?? 'campaign' );
    wp_redirect( add_query_arg( [ 'page' => 'carno-settings', 'saved' => '1', 'tab' => $active_tab ], admin_url( 'admin.php' ) ) );
    exit;
}

// ============================================================================
// رندر صفحه تنظیمات
function carno_render_settings_page() {
    $d        = carno_settings_defaults();
    $products = carno_get_products_data();

    $campaign_prices        = get_option( 'carno_campaign_prices',           $d['campaign_prices'] );
    $campaign_redirect_ids  = get_option( 'carno_campaign_redirect_ids',     [] );
    $qr_prices              = get_option( 'carno_qr_prices',                 $d['qr_prices'] );
    $qr_utm                 = get_option( 'carno_qr_utm_source',             'book_qr' );
    $qr_cookie_minutes      = get_option( 'carno_qr_cookie_minutes',         30 );
    $qr_message             = get_option( 'carno_qr_discount_message',       $d['qr_message'] );
    $session_discounts      = get_option( 'carno_session_discounts',         $d['session_discounts'] );
    $packages               = get_option( 'carno_packages',                  $d['packages'] );
    $hide_price_hour        = get_option( 'carno_hide_price_hour',           16 );
    $timer_css_class        = get_option( 'carno_timer_css_class',           'daily-timer' );
    $tpl_rules              = get_option( 'carno_template_rules',             $d['template_rules'] );
    $address_required_prods = get_option( 'carno_address_required_products', [ 13534 ] );
    $coupon_label           = get_option( 'carno_coupon_label',              'سود شما از این خرید' );

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
                <p class="description">قیمت ثابتی که با پارامتر <code>?special_buy=1&amp;pid=X</code> اعمال می‌شود.</p>

                <?php carno_render_price_table( 'campaign-price-table', 'cp_pid', 'cp_price', $campaign_prices, $products, 'with_vars' ); ?>
                <button type="button" class="button carno-add-row"
                    data-table="campaign-price-table" data-row-type="price"
                    data-pid-name="cp_pid[]" data-price-name="cp_price[]" data-mode="with_vars">
                    + افزودن ردیف
                </button>

                <hr>
                <h3>محصولات Redirect-Only</h3>
                <p class="description">این محصولات با کلیک روی لینک اسپات به جای سبد خرید، به صفحه محصول ریدایرکت می‌شوند.</p>
                <?php carno_render_product_list_table( 'redir-table', 'redir_pid', $campaign_redirect_ids, $products, 'products' ); ?>
                <button type="button" class="button carno-add-row"
                    data-table="redir-table" data-row-type="product"
                    data-pid-name="redir_pid[]" data-mode="products">
                    + افزودن محصول
                </button>
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
                <?php carno_render_price_table( 'qr-price-table', 'qr_pid', 'qr_price', $qr_prices, $products, 'with_vars' ); ?>
                <button type="button" class="button carno-add-row"
                    data-table="qr-price-table" data-row-type="price"
                    data-pid-name="qr_pid[]" data-price-name="qr_price[]" data-mode="with_vars">
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
                            <th>محصول / وارییشن</th>
                            <th style="width:180px">مبلغ تخفیف (تومان)</th>
                            <th style="width:220px">لیبل تخفیف</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $session_discounts as $row ) : ?>
                        <tr>
                            <td><?php carno_product_select( 'sd_pid[]', $row['pid'], $products, 'with_vars', 'width:100%' ); ?></td>
                            <td><input type="number" name="sd_amount[]" value="<?php echo esc_attr( $row['amount'] ); ?>" style="width:100%;box-sizing:border-box"></td>
                            <td><input type="text"   name="sd_label[]"  value="<?php echo esc_attr( $row['label'] ); ?>"  style="width:100%;box-sizing:border-box"></td>
                            <td><button type="button" class="button carno-remove-row">حذف</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="button carno-add-row"
                    data-table="sd-table" data-row-type="discount"
                    data-pid-name="sd_pid[]" data-amount-name="sd_amount[]" data-label-name="sd_label[]" data-mode="with_vars">
                    + افزودن ردیف
                </button>

                <hr>
                <h3>پکیج‌های تخفیف</h3>
                <p class="description">اگر همه محصولات یک پکیج در سبد باشند، قیمت نهایی آن پکیج اعمال می‌شود. می‌توان چند پکیج مستقل تعریف کرد.</p>

                <div id="carno-packages">
                    <?php foreach ( $packages as $i => $pkg ) : ?>
                    <div class="carno-package-card" data-pkg-index="<?php echo $i; ?>">
                        <div class="carno-pkg-header">
                            <strong class="carno-pkg-label">پکیج <?php echo $i + 1; ?></strong>
                            <button type="button" class="button carno-remove-package">حذف پکیج</button>
                        </div>
                        <table class="wp-list-table widefat fixed striped carno-repeater-table" id="pkg-table-<?php echo $i; ?>">
                            <thead>
                                <tr>
                                    <th>محصول</th>
                                    <th style="width:60px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( (array) $pkg['products'] as $pid ) : ?>
                                <tr>
                                    <td><?php carno_product_select( "pkg_products[{$i}][]", $pid, $products, 'products', 'width:100%' ); ?></td>
                                    <td><button type="button" class="button carno-remove-row">حذف</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="button carno-add-pkg-product" data-pkg-index="<?php echo $i; ?>">+ افزودن محصول به پکیج</button>
                        <div class="carno-pkg-price">
                            <label>قیمت نهایی پکیج (تومان):</label>
                            <input type="number" name="pkg_price[<?php echo $i; ?>]" value="<?php echo esc_attr( $pkg['price'] ); ?>" class="regular-text">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button button-secondary" id="carno-add-package">+ افزودن پکیج جدید</button>

                <hr>
                <h3>پنهان‌سازی قیمت تخفیف‌دار</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">ساعت پنهان‌سازی (۰ تا ۲۳)</th>
                        <td>
                            <input type="number" name="carno_hide_price_hour" value="<?php echo esc_attr( $hide_price_hour ); ?>" class="small-text" min="-1" max="23">
                            <p class="description">در این ساعت قیمت تخفیف پنهان می‌شود. <code>-1</code> برای غیرفعال کردن.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">کلاس CSS تایمر</th>
                        <td>
                            <input type="text" name="carno_timer_css_class" value="<?php echo esc_attr( $timer_css_class ); ?>" class="regular-text" placeholder="daily-timer">
                            <p class="description">نام کلاس بدون نقطه — در ساعت تعیین‌شده مخفی می‌شود.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php // ── TAB: محصولات و تمپلیت‌ها ── ?>
            <div class="carno-tab-panel<?php echo $active_tab === 'products' ? ' is-active' : ''; ?>" data-panel="products">
                <h2>تمپلیت‌های نمایش پس از خرید</h2>
                <p class="description">
                    هر قانون یک تمپلیت Elementor را برای گروهی از سفارش‌ها نمایش می‌دهد.<br>
                    <strong>بلک‌لیست:</strong> تمپلیت برای همه سفارش‌ها نمایش داده می‌شود به جز سفارش‌هایی که حاوی محصولات لیست‌شده هستند.<br>
                    <strong>وایت‌لیست:</strong> تمپلیت فقط برای سفارش‌هایی نمایش داده می‌شود که حداقل یکی از محصولات لیست‌شده را دارند.
                </p>

                <div id="carno-tpl-rules">
                    <?php foreach ( $tpl_rules as $i => $rule ) : ?>
                    <div class="carno-package-card" data-rule-index="<?php echo $i; ?>">
                        <div class="carno-pkg-header">
                            <strong class="carno-tpl-rule-label">قانون <?php echo $i + 1; ?></strong>
                            <button type="button" class="button carno-remove-tpl-rule">حذف قانون</button>
                        </div>
                        <table class="form-table" style="margin-bottom:8px">
                            <tr>
                                <th scope="row" style="width:180px">شناسه تمپلیت Elementor</th>
                                <td><input type="number" name="tpl_rule_tpl[<?php echo $i; ?>]" value="<?php echo esc_attr( $rule['template_id'] ); ?>" class="small-text" min="1"></td>
                            </tr>
                            <tr>
                                <th scope="row">حالت</th>
                                <td>
                                    <select name="tpl_rule_mode[<?php echo $i; ?>]">
                                        <option value="blacklist"<?php selected( $rule['mode'] ?? 'blacklist', 'blacklist' ); ?>>بلک‌لیست — برای همه به جز این محصولات</option>
                                        <option value="whitelist"<?php selected( $rule['mode'] ?? 'blacklist', 'whitelist' ); ?>>وایت‌لیست — فقط برای این محصولات</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="description" style="margin-bottom:6px">لیست محصولات (برای بلک‌لیست خالی = همه، برای وایت‌لیست خالی = هیچ‌کس)</p>
                        <table class="wp-list-table widefat fixed striped carno-repeater-table" id="tpl-prod-table-<?php echo $i; ?>">
                            <thead><tr><th>محصول</th><th style="width:60px"></th></tr></thead>
                            <tbody>
                                <?php foreach ( (array) ( $rule['products'] ?? [] ) as $pid ) : ?>
                                <tr>
                                    <td><?php carno_product_select( "tpl_rule_products[{$i}][]", $pid, $products, 'products', 'width:100%' ); ?></td>
                                    <td><button type="button" class="button carno-remove-row">حذف</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="button carno-add-tpl-product" data-rule-index="<?php echo $i; ?>">+ افزودن محصول</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button button-secondary" id="carno-add-tpl-rule" style="margin-top:12px">+ افزودن قانون جدید</button>
            </div>

            <?php // ── TAB: چک‌اوت ── ?>
            <div class="carno-tab-panel<?php echo $active_tab === 'checkout' ? ' is-active' : ''; ?>" data-panel="checkout">
                <h2>تنظیمات چک‌اوت</h2>

                <h3>محصولات نیازمند آدرس</h3>
                <p class="description">وقتی هریک از این محصولات در سبد باشد، فیلدهای آدرس پستی و کد پستی اجباری می‌شوند.</p>
                <?php carno_render_product_list_table( 'addr-table', 'addr_pid', $address_required_prods, $products, 'products' ); ?>
                <button type="button" class="button carno-add-row"
                    data-table="addr-table" data-row-type="product"
                    data-pid-name="addr_pid[]" data-mode="products">
                    + افزودن محصول
                </button>

                <table class="form-table" style="margin-top:24px">
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
    .carno-tab-btn.is-active { background: #fff; color: #1d2327; font-weight: 600; margin-bottom: -1px; padding-bottom: 9px; }
    .carno-tab-panel { display: none; background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 24px 28px; }
    .carno-tab-panel.is-active { display: block; }
    .carno-save-bar { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 14px 28px; }
    .carno-repeater-table { margin-bottom: 8px; }
    .carno-repeater-table td, .carno-repeater-table th { padding: 6px 8px; vertical-align: middle; }
    .carno-product-select { width: 100%; box-sizing: border-box; }
    .carno-wrap .button.carno-add-row { margin-top: 8px; }
    #carno-packages { display: flex; flex-direction: column; gap: 16px; margin-bottom: 12px; }
    .carno-package-card { border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; background: #f9f9f9; }
    .carno-pkg-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .carno-pkg-header strong { font-size: 15px; }
    .carno-pkg-price { margin-top: 12px; display: flex; align-items: center; gap: 12px; }
    .carno-pkg-price label { font-weight: 600; white-space: nowrap; }
    .carno-add-pkg-product { margin-top: 8px !important; }
    .carno-tpl-name { color: #2271b1; font-style: italic; margin-right: 10px; font-size: 13px; vertical-align: middle; }
    .carno-tpl-name.is-loading { color: #aaa; }
    .carno-tpl-name.is-missing { color: #c00; }
    </style>

    <script>
    window.carnoProductsData  = <?php echo wp_json_encode( $products ); ?>;
    var carnoPackageCounter   = <?php echo count( $packages ); ?>;
    var carnoTplRuleCounter   = <?php echo count( $tpl_rules ); ?>;
    var carnoTplTitleNonce    = <?php echo wp_json_encode( wp_create_nonce( 'carno_tpl_title' ) ); ?>;

    (function () {
        // ── تب‌ها ──
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

        // ── ساخت select محصول ──
        function buildProductSelect(name, mode, excludedIds) {
            excludedIds = excludedIds || [];
            var sel = document.createElement('select');
            sel.name = name;
            sel.className = 'carno-product-select';
            sel.style.width = '100%';
            sel.style.boxSizing = 'border-box';

            var empty = document.createElement('option');
            empty.value = ''; empty.textContent = '-- انتخاب محصول --';
            sel.appendChild(empty);

            (window.carnoProductsData || []).forEach(function (p) {
                var hasVars = p.vars && p.vars.length > 0;

                if (mode === 'vars_only') {
                    if (!hasVars) return;
                    var filtered = p.vars.filter(function (v) { return excludedIds.indexOf(v.id) === -1; });
                    if (!filtered.length) return;
                    var grp = document.createElement('optgroup');
                    grp.label = p.label;
                    filtered.forEach(function (v) {
                        grp.appendChild(new Option(p.label + ' - ' + v.label, v.id));
                    });
                    sel.appendChild(grp);

                } else if (mode === 'with_vars' && hasVars) {
                    var filteredVars = p.vars.filter(function (v) { return excludedIds.indexOf(v.id) === -1; });
                    var parentExcl   = excludedIds.indexOf(p.id) !== -1;
                    if (parentExcl && !filteredVars.length) return;
                    var grp = document.createElement('optgroup');
                    grp.label = p.label;
                    if (!parentExcl) grp.appendChild(new Option(p.label, p.id));
                    filteredVars.forEach(function (v) {
                        grp.appendChild(new Option(p.label + ' - ' + v.label, v.id));
                    });
                    if (!grp.children.length) return;
                    sel.appendChild(grp);

                } else {
                    if (excludedIds.indexOf(p.id) !== -1) return;
                    sel.appendChild(new Option(p.label, p.id));
                }
            });
            return sel;
        }

        // ── جمع‌آوری آیدی‌های انتخاب‌شده در یک جدول ──
        function getSelectedIds(tableId, selectName) {
            var ids = [];
            document.querySelectorAll('#' + tableId + ' select[name="' + selectName + '"]').forEach(function (s) {
                var v = parseInt(s.value, 10);
                if (v > 0) ids.push(v);
            });
            return ids;
        }

        // ── حذف ردیف ──
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('carno-remove-row')) {
                e.target.closest('tr').remove();
            }
        });

        // ── افزودن ردیف (جداول معمولی) ──
        document.querySelectorAll('.carno-add-row').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tableId  = this.dataset.table;
                var rowType  = this.dataset.rowType;
                var pidName  = this.dataset.pidName;
                var mode     = this.dataset.mode || 'with_vars';
                var excluded = getSelectedIds(tableId, pidName);
                var tbody    = document.querySelector('#' + tableId + ' tbody');
                var tr       = document.createElement('tr');

                var tdPid = document.createElement('td');
                tdPid.appendChild(buildProductSelect(pidName, mode, excluded));
                tr.appendChild(tdPid);

                if (rowType === 'price') {
                    var tdPrice = document.createElement('td');
                    var inp = document.createElement('input');
                    inp.type = 'number'; inp.name = this.dataset.priceName;
                    inp.style.cssText = 'width:100%;box-sizing:border-box';
                    tdPrice.appendChild(inp);
                    tr.appendChild(tdPrice);

                } else if (rowType === 'discount') {
                    var tdAmt = document.createElement('td');
                    var inpAmt = document.createElement('input');
                    inpAmt.type = 'number'; inpAmt.name = this.dataset.amountName;
                    inpAmt.style.cssText = 'width:100%;box-sizing:border-box';
                    tdAmt.appendChild(inpAmt);
                    tr.appendChild(tdAmt);

                    var tdLbl = document.createElement('td');
                    var inpLbl = document.createElement('input');
                    inpLbl.type = 'text'; inpLbl.name = this.dataset.labelName;
                    inpLbl.style.cssText = 'width:100%;box-sizing:border-box';
                    tdLbl.appendChild(inpLbl);
                    tr.appendChild(tdLbl);

                } else if (rowType === 'template') {
                    var tdTpl = document.createElement('td');
                    var inpTpl = document.createElement('input');
                    inpTpl.type = 'number'; inpTpl.name = this.dataset.tplName;
                    inpTpl.style.cssText = 'width:100%;box-sizing:border-box';
                    tdTpl.appendChild(inpTpl);
                    tr.appendChild(tdTpl);
                }

                var tdDel = document.createElement('td');
                var btnDel = document.createElement('button');
                btnDel.type = 'button'; btnDel.className = 'button carno-remove-row'; btnDel.textContent = 'حذف';
                tdDel.appendChild(btnDel);
                tr.appendChild(tdDel);

                tbody.appendChild(tr);
            });
        });

        // ── پکیج‌ها ──
        function renumberPackages() {
            document.querySelectorAll('.carno-package-card .carno-pkg-label').forEach(function (el, i) {
                el.textContent = 'پکیج ' + (i + 1);
            });
        }

        // افزودن محصول به پکیج (event delegation)
        document.addEventListener('click', function (e) {
            if (!e.target.classList.contains('carno-add-pkg-product')) return;
            var pkgIdx  = e.target.dataset.pkgIndex;
            var tblId   = 'pkg-table-' + pkgIdx;
            var selName = 'pkg_products[' + pkgIdx + '][]';
            var excluded = getSelectedIds(tblId, selName);

            var tbody = document.querySelector('#' + tblId + ' tbody');
            var tr    = document.createElement('tr');

            var tdPid = document.createElement('td');
            tdPid.appendChild(buildProductSelect(selName, 'products', excluded));
            tr.appendChild(tdPid);

            var tdDel = document.createElement('td');
            var btnDel = document.createElement('button');
            btnDel.type = 'button'; btnDel.className = 'button carno-remove-row'; btnDel.textContent = 'حذف';
            tdDel.appendChild(btnDel);
            tr.appendChild(tdDel);

            tbody.appendChild(tr);
        });

        // حذف پکیج
        document.addEventListener('click', function (e) {
            if (!e.target.classList.contains('carno-remove-package')) return;
            e.target.closest('.carno-package-card').remove();
            renumberPackages();
        });

        // افزودن پکیج جدید
        document.getElementById('carno-add-package').addEventListener('click', function () {
            var idx  = carnoPackageCounter++;
            var card = document.createElement('div');
            card.className        = 'carno-package-card';
            card.dataset.pkgIndex = idx;

            var tblId   = 'pkg-table-' + idx;
            var selName = 'pkg_products[' + idx + '][]';

            card.innerHTML =
                '<div class="carno-pkg-header">' +
                    '<strong class="carno-pkg-label">پکیج جدید</strong>' +
                    '<button type="button" class="button carno-remove-package">حذف پکیج</button>' +
                '</div>' +
                '<table class="wp-list-table widefat fixed striped carno-repeater-table" id="' + tblId + '">' +
                    '<thead><tr><th>محصول</th><th style="width:60px"></th></tr></thead>' +
                    '<tbody></tbody>' +
                '</table>' +
                '<button type="button" class="button carno-add-pkg-product" data-pkg-index="' + idx + '">+ افزودن محصول به پکیج</button>' +
                '<div class="carno-pkg-price">' +
                    '<label>قیمت نهایی پکیج (تومان):</label>' +
                    '<input type="number" name="pkg_price[' + idx + ']" class="regular-text">' +
                '</div>';

            document.getElementById('carno-packages').appendChild(card);
            renumberPackages();
        });

        // ── قوانین تمپلیت ──
        function renumberTplRules() {
            document.querySelectorAll('#carno-tpl-rules .carno-tpl-rule-label').forEach(function (el, i) {
                el.textContent = 'قانون ' + (i + 1);
            });
        }

        // افزودن محصول به قانون
        document.addEventListener('click', function (e) {
            if (!e.target.classList.contains('carno-add-tpl-product')) return;
            var ruleIdx = e.target.dataset.ruleIndex;
            var tblId   = 'tpl-prod-table-' + ruleIdx;
            var selName = 'tpl_rule_products[' + ruleIdx + '][]';
            var excluded = getSelectedIds(tblId, selName);

            var tbody = document.querySelector('#' + tblId + ' tbody');
            var tr    = document.createElement('tr');
            var tdPid = document.createElement('td');
            tdPid.appendChild(buildProductSelect(selName, 'products', excluded));
            tr.appendChild(tdPid);
            var tdDel = document.createElement('td');
            var btnDel = document.createElement('button');
            btnDel.type = 'button'; btnDel.className = 'button carno-remove-row'; btnDel.textContent = 'حذف';
            tdDel.appendChild(btnDel);
            tr.appendChild(tdDel);
            tbody.appendChild(tr);
        });

        // حذف قانون
        document.addEventListener('click', function (e) {
            if (!e.target.classList.contains('carno-remove-tpl-rule')) return;
            e.target.closest('.carno-package-card').remove();
            renumberTplRules();
        });

        // افزودن قانون جدید
        document.getElementById('carno-add-tpl-rule').addEventListener('click', function () {
            var idx     = carnoTplRuleCounter++;
            var tblId   = 'tpl-prod-table-' + idx;
            var selName = 'tpl_rule_products[' + idx + '][]';
            var card    = document.createElement('div');
            card.className         = 'carno-package-card';
            card.dataset.ruleIndex = idx;

            card.innerHTML =
                '<div class="carno-pkg-header">' +
                    '<strong class="carno-tpl-rule-label">قانون جدید</strong>' +
                    '<button type="button" class="button carno-remove-tpl-rule">حذف قانون</button>' +
                '</div>' +
                '<table class="form-table" style="margin-bottom:8px">' +
                    '<tr>' +
                        '<th scope="row" style="width:180px">شناسه تمپلیت Elementor</th>' +
                        '<td><input type="number" name="tpl_rule_tpl[' + idx + ']" class="small-text" min="1"></td>' +
                    '</tr>' +
                    '<tr>' +
                        '<th scope="row">حالت</th>' +
                        '<td><select name="tpl_rule_mode[' + idx + ']">' +
                            '<option value="blacklist">بلک‌لیست — برای همه به جز این محصولات</option>' +
                            '<option value="whitelist">وایت‌لیست — فقط برای این محصولات</option>' +
                        '</select></td>' +
                    '</tr>' +
                '</table>' +
                '<p class="description" style="margin-bottom:6px">لیست محصولات (برای بلک‌لیست خالی = همه، برای وایت‌لیست خالی = هیچ‌کس)</p>' +
                '<table class="wp-list-table widefat fixed striped carno-repeater-table" id="' + tblId + '">' +
                    '<thead><tr><th>محصول</th><th style="width:60px"></th></tr></thead>' +
                    '<tbody></tbody>' +
                '</table>' +
                '<button type="button" class="button carno-add-tpl-product" data-rule-index="' + idx + '">+ افزودن محصول</button>';

            document.getElementById('carno-tpl-rules').appendChild(card);
            renumberTplRules();
        });

        // ── پیش‌نمایش اسم تمپلیت المنتور ──
        var tplTitleCache = {};
        var tplTitleTimer = null;

        function fetchTplTitle(input) {
            var id   = parseInt(input.value, 10);
            var span = input.parentNode.querySelector('.carno-tpl-name');
            if (!span) {
                span = document.createElement('span');
                span.className = 'carno-tpl-name';
                input.parentNode.appendChild(span);
            }
            if (!id) { span.textContent = ''; span.className = 'carno-tpl-name'; return; }
            if (tplTitleCache[id] !== undefined) {
                span.className   = 'carno-tpl-name' + (tplTitleCache[id] ? '' : ' is-missing');
                span.textContent = tplTitleCache[id] ? '— ' + tplTitleCache[id] : '— (یافت نشد)';
                return;
            }
            span.className   = 'carno-tpl-name is-loading';
            span.textContent = '...';
            var fd = new FormData();
            fd.append('action', 'carno_get_template_title');
            fd.append('nonce',  carnoTplTitleNonce);
            fd.append('id',     id);
            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r)   { return r.json(); })
                .then(function(res) {
                    var title = res.success ? res.data.title : '';
                    tplTitleCache[id] = title;
                    span.className   = 'carno-tpl-name' + (title ? '' : ' is-missing');
                    span.textContent = title ? '— ' + title : '— (یافت نشد)';
                })
                .catch(function() { span.className = 'carno-tpl-name is-missing'; span.textContent = '— (خطا)'; });
        }

        function attachTplTitleListeners(root) {
            (root || document).querySelectorAll('[name^="tpl_rule_tpl"]').forEach(function(inp) {
                if (inp.dataset.tplTitleBound) return;
                inp.dataset.tplTitleBound = '1';
                if (inp.value) fetchTplTitle(inp);
                inp.addEventListener('input', function() {
                    clearTimeout(tplTitleTimer);
                    var self = this;
                    tplTitleTimer = setTimeout(function() { fetchTplTitle(self); }, 600);
                });
            });
        }
        attachTplTitleListeners();

        new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) attachTplTitleListeners(node);
                });
            });
        }).observe(document.getElementById('carno-tpl-rules'), { childList: true, subtree: true });

    })();
    </script>
    <?php
}

// ============================================================================
// تابع کمکی: جدول قیمت با select محصول
function carno_render_price_table( $table_id, $pid_field, $price_field, $rows, $products, $mode = 'with_vars' ) {
    ?>
    <table class="wp-list-table widefat fixed striped carno-repeater-table" id="<?php echo esc_attr( $table_id ); ?>">
        <thead>
            <tr>
                <th>محصول / وارییشن</th>
                <th style="width:200px">قیمت (تومان)</th>
                <th style="width:60px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $rows as $row ) : ?>
            <tr>
                <td><?php carno_product_select( $pid_field . '[]', $row['pid'], $products, $mode, 'width:100%' ); ?></td>
                <td><input type="number" name="<?php echo esc_attr( $price_field ); ?>[]" value="<?php echo esc_attr( $row['price'] ); ?>" style="width:100%;box-sizing:border-box"></td>
                <td><button type="button" class="button carno-remove-row">حذف</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// تابع کمکی: جدول لیست محصولات (بدون قیمت)
function carno_render_product_list_table( $table_id, $pid_field, $selected_ids, $products, $mode = 'products' ) {
    ?>
    <table class="wp-list-table widefat fixed striped carno-repeater-table" id="<?php echo esc_attr( $table_id ); ?>">
        <thead>
            <tr>
                <th>محصول</th>
                <th style="width:60px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( (array) $selected_ids as $pid ) : ?>
            <tr>
                <td><?php carno_product_select( $pid_field . '[]', $pid, $products, $mode, 'width:100%' ); ?></td>
                <td><button type="button" class="button carno-remove-row">حذف</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
