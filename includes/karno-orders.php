<?php
// ============================================================================
// سفارش‌های فرم خرید مستقیم (فرم گرویتی #70 + درگاه آقای پرداخت)
// ============================================================================

define( 'KARNO_ORDER_FORM_ID', 70 );

// شناسه‌ی فیلدهای فرم خرید
define( 'KARNO_FIELD_NAME', 1 );        // نام و نام‌خانوادگی
define( 'KARNO_FIELD_PHONE', 2 );       // شماره موبایل
define( 'KARNO_FIELD_PRODUCT_ID', 3 );  // فیلد مخفی شناسه محصول — باید برابر BUY_FORM.productIdField باشد

// عنوان دوره‌ها بر اساس data-id کارت‌ها در choose-widget.html
define(
	'KARNO_PRODUCT_TITLES',
	array(
		1 => 'کتاب زبان فنی',
		2 => 'دوره زبان فنی',
		3 => 'دوره تنظیم موتور',
		4 => 'دوره برق و انژکتور داخلی',
		5 => 'دوره خودروهای چینی',
		6 => 'دوره خودروهای کره‌ای',
	)
);

/**
 * ثبت Custom Post Type سفارش
 */
add_action( 'init', function () {
	register_post_type( 'karno_order', array(
		'label'        => 'سفارش‌ها',
		'labels'       => array(
			'name'          => 'سفارش‌ها',
			'singular_name' => 'سفارش',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-cart',
		'supports'     => array( 'title' ),
		'capability_type' => 'post',
	) );
} );

/**
 * ساخت سفارش با وضعیت «در انتظار پرداخت» بعد از سابمیت فرم خرید
 */
add_action( 'gform_after_submission', function ( $entry, $form ) {
	if ( (int) $form['id'] !== KARNO_ORDER_FORM_ID ) {
		return;
	}

	$name       = rgar( $entry, KARNO_FIELD_NAME );
	$phone      = rgar( $entry, KARNO_FIELD_PHONE );
	$product_id = (int) rgar( $entry, KARNO_FIELD_PRODUCT_ID );
	$product_title = isset( KARNO_PRODUCT_TITLES[ $product_id ] ) ? KARNO_PRODUCT_TITLES[ $product_id ] : 'دوره نامشخص';
	$price      = (float) GFCommon::get_order_total( $form, $entry );

	$order_id = wp_insert_post( array(
		'post_type'   => 'karno_order',
		'post_title'  => sprintf( 'سفارش #%d - %s - %s', $entry['id'], $name, $product_title ),
		'post_status' => 'publish',
	) );

	if ( is_wp_error( $order_id ) || ! $order_id ) {
		return;
	}

	update_post_meta( $order_id, '_karno_entry_id', $entry['id'] );
	update_post_meta( $order_id, '_karno_form_id', $form['id'] );
	update_post_meta( $order_id, '_karno_customer_name', $name );
	update_post_meta( $order_id, '_karno_phone', $phone );
	update_post_meta( $order_id, '_karno_product_id', $product_id );
	update_post_meta( $order_id, '_karno_product_title', $product_title );
	update_post_meta( $order_id, '_karno_price', $price );
	update_post_meta( $order_id, '_karno_status', 'pending' ); // در انتظار پرداخت
}, 10, 2 );

/**
 * بعد از تایید پرداخت توسط افزونه پرداخت گرویتی فرم، سفارش مرتبط را «تکمیل‌شده» می‌کند
 */
add_action( 'gform_post_payment_completed', function ( $entry, $action ) {
	if ( (int) $entry['form_id'] !== KARNO_ORDER_FORM_ID ) {
		return;
	}

	$orders = get_posts( array(
		'post_type'   => 'karno_order',
		'post_status' => 'publish',
		'numberposts' => 1,
		'meta_query'  => array(
			array(
				'key'   => '_karno_entry_id',
				'value' => $entry['id'],
			),
			array(
				'key'   => '_karno_form_id',
				'value' => $entry['form_id'],
			),
		),
	) );

	if ( empty( $orders ) ) {
		return;
	}

	update_post_meta( $orders[0]->ID, '_karno_status', 'completed' ); // تکمیل‌شده
}, 10, 2 );

/**
 * نمایش وضعیت، مشتری، تلفن، محصول و قیمت در لیست سفارش‌ها (پنل مدیریت)
 */
add_filter( 'manage_karno_order_posts_columns', function ( $columns ) {
	$columns['karno_status']  = 'وضعیت';
	$columns['karno_customer'] = 'مشتری';
	$columns['karno_phone']   = 'موبایل';
	$columns['karno_product'] = 'محصول';
	$columns['karno_price']   = 'قیمت (تومان)';
	return $columns;
} );

add_action( 'manage_karno_order_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'karno_status':
			$status = get_post_meta( $post_id, '_karno_status', true );
			echo $status === 'completed' ? 'تکمیل‌شده' : 'در انتظار پرداخت';
			break;
		case 'karno_customer':
			echo esc_html( get_post_meta( $post_id, '_karno_customer_name', true ) );
			break;
		case 'karno_phone':
			echo esc_html( get_post_meta( $post_id, '_karno_phone', true ) );
			break;
		case 'karno_product':
			echo esc_html( get_post_meta( $post_id, '_karno_product_title', true ) );
			break;
		case 'karno_price':
			echo esc_html( number_format( (float) get_post_meta( $post_id, '_karno_price', true ) ) );
			break;
	}
}, 10, 2 );
