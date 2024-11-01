<?php
/**
 * Customer tracking email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-tracking.php.
 *
 * @package WooCommerce\Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "Ciao " . esc_html( $order->get_billing_first_name() ) . ",\n\n";
echo "Le informazioni di tracciamento per il tuo ordine n. " . esc_html( $order->get_order_number() ) . " sono ora disponibili.\n\n";
echo "Di seguito trovi le informazioni di tracciamento:\n\n";
echo "Corriere: " . esc_html( get_post_meta( $order->get_id(), '_corriere', true ) ) . "\n";
echo "Codice Tracciamento: " . esc_html( get_post_meta( $order->get_id(), '_codice_tracciamento', true ) ) . "\n";
echo "Link Tracciamento: " . esc_url( get_post_meta( $order->get_id(), '_link_tracciamento', true ) ) . "\n";
