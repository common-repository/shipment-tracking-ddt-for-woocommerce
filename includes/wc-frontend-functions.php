<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_filter( 'woocommerce_account_orders_columns', 'hwit_stddt_add_account_orders_column', 10, 1 );
function hwit_stddt_add_account_orders_column( $columns ){
    $order_actions  = $columns['order-actions']; // Save Order actions
    unset($columns['order-actions']); // Remove Order actions

    // Add your custom column key / label
    $columns['tracking-ddt'] = __( 'Tracking & DDT', 'hw-shipment-tracking-ddt-for-woocommerce' );

    // Add back previously saved "Order actions"
    $columns['order-actions'] = $order_actions;

    return $columns;
}

// Display a custom field value from order metadata
add_action( 'woocommerce_my_account_my_orders_column_tracking-ddt', 'hwit_stddt_add_account_orders_column_rows' );
function hwit_stddt_add_account_orders_column_rows( $order ) {

	$corriere = $order->get_meta('_corriere');
	$codice_tracciamento = $order->get_meta('_codice_tracciamento');
	$link_tracciamento = $order->get_meta('_link_tracciamento');
	$ddt_pdf_data = $order->get_meta('_ddt_pdf_url');
	$ddt_sent = $order->get_meta('_ddt_mail_sent_timestamp');
	$tracking_mail_sent = $order->get_meta('_tracking_mail_sent_timestamp');
	/* translator: datetime format to show DDT Sent date/time. */
	$date_format = __('d/m/Y H:i:s', 'hw-shipment-tracking-ddt-for-woocommerce');
	
	$tracking_info = "";

		if (!empty($corriere)) {
			$tracking_info .= "<strong>$corriere</strong>";
		}
		if (!empty($codice_tracciamento)) {
			if (!empty($link_tracciamento)) {
				$tracking_info .= " (COD: <a href=\"$link_tracciamento\" target=\"_blank\">$codice_tracciamento</a>)<br>";
			} else {
				$tracking_info .= " (COD: $codice_tracciamento)<br>";
			}
		}
		if (empty($codice_tracciamento) && !empty($link_tracciamento)) {
			$tracking_info .= "(<a href=\"$link_tracciamento\" target=\"_blank\">Link tracking</a>)<br>";
		}

		if (is_array($ddt_pdf_data)) {
			$ddt_pdf_url = $ddt_pdf_data['url'];
			$ddt_pdf_filename = $ddt_pdf_data['name'];
			$tracking_info .= '<span class="dashicons dashicons-pdf" style="color:red;font-size:11px;line-height:27px;width:11px;height:11px;" title="'.__('DDT', 'hw-shipment-tracking-ddt-for-woocommerce').'"></span><a href="'.$ddt_pdf_url.'" target="_blank" style="padding-left:3px;">'.$ddt_pdf_filename.'</a><br>';
		}

		if (!empty($tracking_mail_sent)) {
			$tracking_info .= '<span class="dashicons dashicons-yes-alt" style="color:green;font-size:11px;line-height:27px;width:11px;height:11px;"></span><span class="hwit_stddt_tracking_sent_message_frontend_order_row" style="padding-left:5px;font-size:11px;">'. esc_html(__('Tracking ricevuto', 'hw-shipment-tracking-ddt-for-woocommerce')). '</span>';
		} else {

		}

		if (!empty($ddt_sent)) {
			$tracking_info .= '<span class="dashicons dashicons-yes-alt" style="color:green;font-size:11px;line-height:27px;width:11px;height:11px;margin-left:15px;display:block;"></span><span class="hwit_stddt_ddt_sent_message_frontend_order_row" style="padding-left:5px;font-size:11px;">'. esc_html(__('DDT ricevuto', 'hw-shipment-tracking-ddt-for-woocommerce')). '</span>';
		} else {

		}

		if (empty($tracking_info)) {
			$tracking_info = '<span class="dashicons dashicons-no-alt" style="color:#000;font-size:11px;line-height:27px;width:11px;height:11px;"></span><span class="hwit_stddt_no_ddt_tracking_info_column" style="padding-left:5px;font-size:11px;">'. esc_html(__('Nessun dato presente', 'hw-shipment-tracking-ddt-for-woocommerce')). '</span>';
		}

        echo wp_kses_post($tracking_info);
}

add_action('woocommerce_order_details_after_order_table', 'hwit_stddt_add_order_tracking_details');
function hwit_stddt_add_order_tracking_details($order){

	$corriere = $order->get_meta('_corriere');
	$codice_tracciamento = $order->get_meta('_codice_tracciamento');
	$link_tracciamento = $order->get_meta('_link_tracciamento');
	$ddt_pdf_data = $order->get_meta('_ddt_pdf_url');
	$ddt_sent = $order->get_meta('_ddt_mail_sent_timestamp');
	$tracking_mail_sent = $order->get_meta('_tracking_mail_sent_timestamp');
	/* translator: datetime format to show DDT Sent date/time. */
	$date_format = __('d/m/Y H:i:s', 'hw-shipment-tracking-ddt-for-woocommerce');

		if (!empty($corriere)) {
			$tracking_info = __('<h3>Informazioni Tracking & DDT</h3>', 'hw-shipment-tracking-ddt-for-woocommerce');
			$tracking_info .= __('Spedito con: ', 'hw-shipment-tracking-ddt-for-woocommerce') . "<strong>$corriere</strong><br>";
		}
		if (!empty($codice_tracciamento)) {
			if (!empty($link_tracciamento)) {
				$tracking_info .= __('Codice spedizione: ', 'hw-shipment-tracking-ddt-for-woocommerce') . "<a href=\"$link_tracciamento\" target=\"_blank\">$codice_tracciamento</a><br>";
			} else {
				$tracking_info .= __('Codice spedizione: ', 'hw-shipment-tracking-ddt-for-woocommerce') . "$codice_tracciamento<br>";
			}
		}
		if (empty($codice_tracciamento) && !empty($link_tracciamento)) {
			$tracking_info .= "<a href=\"$link_tracciamento\" target=\"_blank\">Link tracking</a><br>";
		}

		if (is_array($ddt_pdf_data)) {
			$tracking_info .= '<h4 style="padding-top:15px;">'. __('DDT Allegato', 'hw-shipment-tracking-ddt-for-woocommerce') . '</h4>';
			$ddt_pdf_url = $ddt_pdf_data['url'];
			$ddt_pdf_filename = $ddt_pdf_data['name'];
			$tracking_info .= '<span class="dashicons dashicons-pdf" style="color:red;font-size:11px;line-height:27px;width:11px;height:11px;" title="'.__('DDT', 'hw-shipment-tracking-ddt-for-woocommerce').'"></span><a href="'.$ddt_pdf_url.'" target="_blank" style="padding-left:3px;">'.$ddt_pdf_filename.'</a><br>';
		}

		if (!empty($tracking_mail_sent)) {
			$tracking_info .= '<span class="dashicons dashicons-yes-alt" style="color:green;font-size:11px;line-height:27px;width:11px;height:11px;"></span><span class="hwit_stddt_tracking_sent_message_frontend_order_row" style="padding-left:5px;font-size:11px;">'. esc_html(__('Tracking ricevuto', 'hw-shipment-tracking-ddt-for-woocommerce')). '</span>';
		} else {

		}

		if (!empty($ddt_sent)) {
			$tracking_info .= '<span class="dashicons dashicons-yes-alt" style="color:green;font-size:11px;line-height:27px;width:11px;height:11px;margin-left:15px;display:block;"></span><span class="hwit_stddt_ddt_sent_message_frontend_order_row" style="padding-left:5px;font-size:11px;">'. esc_html(__('DDT ricevuto', 'hw-shipment-tracking-ddt-for-woocommerce')). '</span>';
		} else {

		}

		if (empty($tracking_info)) {
			$tracking_info = '<span class="dashicons dashicons-no-alt" style="color:#000;font-size:11px;line-height:27px;width:11px;height:11px;"></span><span class="hwit_stddt_no_ddt_tracking_info_column" style="padding-left:5px;font-size:11px;">'. esc_html(__('Nessun dato presente', 'hw-shipment-tracking-ddt-for-woocommerce')). '</span>';
		}

		$tracking_info .= '<div style="height:25px;"></div>';

        echo wp_kses_post($tracking_info);

}