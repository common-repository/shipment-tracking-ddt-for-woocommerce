<?php
/**
 * Customer tracking email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-tracking.php.
 *
 * @package WooCommerce\Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

?>

<p><?php 
/* translators: %s Customer name */
printf( esc_html__( 'Ciao %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) ); ?></p>

<p><?php 
/* translators: %s Order ID */
printf( esc_html__( 'Le informazioni di tracciamento per il tuo ordine #%s sono ora disponibili.', 'woocommerce' ), esc_html( $order->get_id() ) ); ?></p>

<p><?php esc_html_e( 'Di seguito trovi le informazioni di tracciamento:', 'woocommerce' ); ?></p>

<ul>
<?php
$corriere = $order->get_meta( '_corriere' );
$codice_tracciamento = $order->get_meta( '_codice_tracciamento' );
$link_tracciamento = $order->get_meta( '_link_tracciamento' );
if ( ! empty( $corriere ) ) {
    echo "<li>" . wp_kses_post( apply_filters( 'woocommerce_email_order_tracking_meta', sprintf(
        /* translators: %s: Order tracking number */
        __( 'Corriere: %s', 'woocommerce' ), esc_html( $corriere )
    ), $order ) ) . "</li>";
}

if ( ! empty( $codice_tracciamento ) ) {
    echo "<li>" . wp_kses_post( apply_filters( 'woocommerce_email_order_tracking_meta', sprintf(
        /* translators: %s: Order tracking number */
        __( 'Codice Tracciamento: %s', 'woocommerce' ), esc_html( $codice_tracciamento )
    ), $order ) ) . "</li>";
}

if ( ! empty( $link_tracciamento ) ) {
    echo "<li>" . wp_kses_post( apply_filters( 'woocommerce_email_order_tracking_meta', sprintf(
        /* translators: %1$s and %2$s are the same: Order tracking link */
        __( 'Link Tracciamento: <a href="%1$s">%2$s</a>', 'woocommerce' ), esc_url( $link_tracciamento ), esc_url( $link_tracciamento )
    ), $order ) ) . "</li>";
}
?>

<?php

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
