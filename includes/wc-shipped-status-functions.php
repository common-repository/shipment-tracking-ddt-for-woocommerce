<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Add new status to order CPT
add_action('init', 'hwit_stddt_add_order_status_core', 9);
function hwit_stddt_add_order_status_core(){
	register_post_status( 'wc-shipped', array(
		'label'                     => _x( 'Spedito', 'Order status', 'hw-shipment-tracking-ddt-for-woocommerce' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		/*
		 * translators: Order status Shipped
		 */
		'label_count'               => _n_noop( 'Spedito <span class="count">(%s)</span>', 'Spediti <span class="count">(%s)</span>', 'hw-shipment-tracking-ddt-for-woocommerce' ),
	) );
}

// Register order status in wc_order_statuses
add_filter( 'wc_order_statuses', 'hwit_stddt_register_shipped_status_to_wc', PHP_INT_MAX - 1 );
function hwit_stddt_register_shipped_status_to_wc( $order_statuses ) {

   $new_order_statuses = array(
        'wc-shipped' => _x( 'Spedito', 'Order status', 'woocommerce' ),
    );

return array_merge( $order_statuses, $new_order_statuses );
}

// Set Shipped orders as editable
add_filter( 'wc_order_is_editable', 'hwit_stddt_shipped_orders_editable', 10, 2 );
function hwit_stddt_shipped_orders_editable( $is_editable, $order ) {
	// $order is WC_Order object
	if ( $order->get_status() == 'wc-shipped' ) {
		return true;
	}

return $is_editable;
}