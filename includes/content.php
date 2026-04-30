<?php
// ============================================================================
// محتوا - شورتکدها، فهرست مطالب، بازدید مقالات، کامنت‌های ترکیبی
// ============================================================================

// شورتکد زمان مطالعه
// Usage: [read_time] or [read_time wpm="220" label="%s دقیقه" icon="1"]
if ( ! function_exists( 'kar_read_time_shortcode' ) ) {

    function kar_read_time_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'wpm'   => 220,
            'label' => '%s دقیقه',
            'class' => 'read-time',
            'icon'  => '1',
            'min'   => 1,
        ), $atts, 'read_time' );

        global $post;
        if ( empty( $post ) || ! isset( $post->ID ) ) {
            return '';
        }
        $post_id = (int) $post->ID;

        $manual = get_post_meta( $post_id, 'read_time_manual', true );
        if ( $manual !== '' && is_numeric( $manual ) ) {
            $minutes = (int) $manual;
        } else {
            $cache_key_time  = '_kar_read_time_cached_time';
            $cache_key_value = '_kar_read_time_cached_value';
            $cached_time     = get_post_meta( $post_id, $cache_key_time, true );
            $cached_value    = get_post_meta( $post_id, $cache_key_value, true );
            $post_mod_time   = get_post_field( 'post_modified_gmt', $post_id );

            if ( $cached_value !== '' && $cached_time === $post_mod_time ) {
                $minutes = (int) $cached_value;
            } else {
                $content = isset( $post->post_content ) ? $post->post_content : '';
                $content = strip_shortcodes( $content );
                $content = wp_strip_all_tags( $content );
                $content = trim( preg_replace( '/\s+/u', ' ', $content ) );

                if ( $content === '' ) {
                    $word_count = 0;
                } else {
                    $words = preg_split( '/\s+/u', $content );
                    if ( is_array( $words ) ) {
                        $filtered = array_filter( $words, 'strlen' );
                        $word_count = count( $filtered );
                    } else {
                        $word_count = 0;
                    }
                }

                $wpm = intval( $atts['wpm'] );
                if ( $wpm <= 0 ) {
                    $wpm = 220;
                } elseif ( $wpm < 50 ) {
                    $wpm = 50;
                }

                $minutes = (int) ceil( $word_count / $wpm );
                if ( $minutes < intval( $atts['min'] ) ) {
                    $minutes = intval( $atts['min'] );
                }

                update_post_meta( $post_id, $cache_key_value, $minutes );
                update_post_meta( $post_id, $cache_key_time, $post_mod_time );
            }
        }

        if ( intval( $minutes ) === 0 ) {
            $label_text = 'کمتر از یک دقیقه';
        } else {
            if ( function_exists( 'number_format_i18n' ) ) {
                $num = number_format_i18n( $minutes );
            } else {
                $num = $minutes;
            }
            $label_text = sprintf( $atts['label'], $num );
        }

        $icon_html = '';
        if ( intval( $atts['icon'] ) ) {
            $icon_html = '<span class="read-time-icon" aria-hidden="true" style="display:inline-flex;align-items:center;margin-inline-end:6px;">'
                       . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false" role="img">'
                       . '<path d="M12 7V12L15 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>'
                       . '<path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>'
                       . '</svg></span>';
        }

        $aria_label = esc_attr( "زمان تقریبی مطالعه: {$minutes} دقیقه" );

        $html = sprintf(
            '<span class="%s" role="text" aria-label="%s" title="%s">%s<span class="read-time-text">%s</span></span>',
            esc_attr( $atts['class'] ),
            $aria_label,
            esc_attr( $label_text ),
            $icon_html,
            esc_html( $label_text )
        );

        return $html;
    }

    add_shortcode( 'read_time', 'kar_read_time_shortcode' );
}

// ============================================================================
// شورتکد نوار پیشرفت موجودی با تایمر
function nias_inventory_progress_bar_with_timer($atts) {
    $atts = shortcode_atts(array(
        'product_id' => get_the_ID(),
        'end_time'   => '',
    ), $atts, 'nias_inventory_progress_bar');

    $product = wc_get_product($atts['product_id']);
    if (!$product) return '';

    $total_stock = get_post_meta($product->get_id(), '_original_stock', true);
    if (!$total_stock) {
        $total_stock = $product->get_stock_quantity();
        update_post_meta($product->get_id(), '_original_stock', $total_stock);
    }

    $current_stock = $product->get_stock_quantity();
    $sold_stock    = max(0, $total_stock - $current_stock);

    $percentage = $total_stock > 0 ? round(($sold_stock / $total_stock) * 100) : 0;
    $percentage = min(100, $percentage);

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
            height: 6px;
            background: #eee;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        .niasbar-fill {
            height: 100%;
            background: linear-gradient(90deg, #ed1c24, #ff0000);
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

function nias_save_original_stock($post_id) {
    $product = wc_get_product($post_id);
    if ($product && !get_post_meta($post_id, '_original_stock', true)) {
        update_post_meta($post_id, '_original_stock', $product->get_stock_quantity());
    }
}
add_action('woocommerce_new_product', 'nias_save_original_stock');

// ============================================================================
// شورتکد نمایش کامنت‌های ترکیبی از چند صفحه
function mk_merged_comments_shortcode( $atts ) {

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

    if ( ! class_exists( 'No_Link_Comment_Walker' ) ) {
    class No_Link_Comment_Walker extends Walker_Comment {
        protected function comment( $comment, $depth, $args ) {
            $tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
            ?>
            <<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( $this->has_children ? 'parent' : '', $comment ); ?>>
                <article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
                    <footer class="comment-meta">
                        <div class="comment-author vcard">
                            <?php echo get_avatar( $comment, 48 ); ?>
                            <b class="fn"><?php echo esc_html( get_comment_author( $comment ) ); ?></b>
                        </div>
                        <div class="comment-metadata">
                            <span class="comment-date"><?php echo get_comment_date( '', $comment ); ?></span>
                        </div>
                    </footer>

                    <div class="comment-content">
                        <?php comment_text(); ?>
                    </div>
                </article>
            <?php
        }

        protected function comment_reply_link( $comment, $depth, $args ) {
            // حذف لینک ریپلای
        }
    }
    }
    if ( $comments ) {
        echo '<div id="comments" class="comments-area">';
        wp_list_comments( [
            'echo'   => true,
            'per_page' => 0,
            'walker' => new No_Link_Comment_Walker(),
            'style' => 'ul',
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

// ============================================================================
// فهرست مطالب (TOC) از H2 ها
function carno_generate_toc($atts) {
    global $post;

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

// ============================================================================
// رهگیری بازدید صفحات
function increment_post_views($postID) {
    $views = get_post_meta($postID, 'post_views', true);

    if (!$views) {
        $views = 0;
    }

    $views++;
    update_post_meta($postID, 'post_views', $views);
}

function track_post_views() {
    if (!is_user_logged_in() && (is_single() || is_page())) {
        $postID = get_the_ID();
        if ($postID) {
            increment_post_views($postID);
        }
    }
}
add_action('wp_footer', 'track_post_views');

// ============================================================================
// شورتکد باکس پیشنهاد محصول یا مقاله
function suggestion_box($atts) {
    $atts = shortcode_atts([
        'type' => 'product',
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

    $title = method_exists($item, 'get_name') ? $item->get_name() : $item->post_title;
    $link = method_exists($item, 'get_permalink') ? $item->get_permalink() : get_permalink($item->ID);
    $excerpt = method_exists($item, 'get_short_description') ? $item->get_short_description() : $item->post_excerpt;

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
// شورتکد باکس نکته/تیپ
function carno_tip_shortcode($atts, $content = null) {
    return '<div class="carno-tip-box">' . do_shortcode($content) . '</div>';
}
add_shortcode('carno_tip', 'carno_tip_shortcode');
