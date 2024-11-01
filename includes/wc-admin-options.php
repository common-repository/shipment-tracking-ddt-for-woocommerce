<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Aggiungi una nuova tab nelle impostazioni di WooCommerce
add_filter( 'woocommerce_settings_tabs_array', 'hwit_stddt_add_tracking_settings_tab', 50 );
function hwit_stddt_add_tracking_settings_tab( $tabs ) {
    $tabs['tracking'] = __( 'Tracking & DDT', 'hw-shipment-tracking-ddt-for-woocommerce' );
    return $tabs;
}

// Definisci il contenuto della nuova tab
add_action( 'woocommerce_settings_tabs_tracking', 'hwit_stddt_tracking_settings_tab_content' );
function hwit_stddt_tracking_settings_tab_content() {
	wp_nonce_field( 'hwit_stddt_tracking_settings', 'hwit_stddt_tracking_nonce' );
    woocommerce_admin_fields( hwit_stddt_get_tracking_settings_fields(), 1);
}

// Imposta i campi delle impostazioni
function hwit_stddt_get_tracking_settings_fields() {
    $fields = array(
        'tracking_heading' => array(
            'name'     => __( 'Stato Spedito', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'type'     => 'title',
            'desc'     => __( 'Quando l\'ordine passa sullo stato Spedito puoi decidere se inviare una email unica (compresa di DDT allegato), oppure se inviarne due separatamente (una con le informazioni di tracking e un\'altra con il DDT).', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'id'       => 'hwit_stddt_tracking_heading'
        ),
        'tracking_email_type' => array(
            'name'     => __( 'Tipo di Email', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'type'     => 'select',
            'desc'     => __( 'Scegli il tipo di email da inviare quando l\'ordine passa allo stato Spedito.', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'id'       => 'hwit_stddt_tracking_email_type',
            'options'  => array(
                '1' => __( 'Invia Email unica', 'hw-shipment-tracking-ddt-for-woocommerce' ),
                '2' => __( 'Invia Email separate', 'hw-shipment-tracking-ddt-for-woocommerce' )
            ),
            'default'  => '1'
        ),
        'include_tracking_info_with_ddt' => array(
            'name'     => __( 'Includere informazioni Tracking con DDT', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'type'     => 'select',
            'desc'     => __( 'Nella mail che contiene il DDT vuoi mostrare anche le informazioni sul Tracking?', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'id'       => 'hwit_stddt_include_tracking_info_with_ddt',
            'options'  => array(
                '0' => __( 'No, allega solo il DDT', 'hw-shipment-tracking-ddt-for-woocommerce' ),
                '1' => __( 'Si, includi anche le informazioni', 'hw-shipment-tracking-ddt-for-woocommerce' )
            ),
            'default'  => '1'
        ),
        'enable_email_repeat' => array(
            'name'     => __( 'Inviare nuovamente le email?', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'type'     => 'select',
            'desc'     => __( 'Quando l\'ordine passa allo status "spedito", desideri che le email vengano inviate lo stesso anche se erano già state inviate manualmente? Nel caso in cui il plugin rilevi che le email erano già state inviate, se selezioni "No", non verranno inviate nuovamente al cambio di status.', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'id'       => 'hwit_stddt_enable_email_repeat',
            'options'  => array(
                '0' => __( 'No, non inviare', 'hw-shipment-tracking-ddt-for-woocommerce' ),
                '1' => __( 'Si, invia comunque', 'hw-shipment-tracking-ddt-for-woocommerce' )
            ),
            'default'  => '1'
        ),		
        'enable_debug' => array(
            'name'     => __( 'Abilita il debug esteso', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'type'     => 'select',
            'desc'     => __( 'Abilita le informazioni di debug in modo esteso per tracciare le operazioni eseguite dal plugin e rendere più agevole la rilevazione degli errori negli script. Nota: la costante WP_DEBUG_LOG deve essere attiva', 'hw-shipment-tracking-ddt-for-woocommerce' ),
            'id'       => 'hwit_stddt_enable_debug',
            'options'  => array(
                '0' => __( 'Disabilitato', 'hw-shipment-tracking-ddt-for-woocommerce' ),
                '1' => __( 'Abilitato', 'hw-shipment-tracking-ddt-for-woocommerce' )
            ),
            'default'  => '0'
        ),
		array( 'type' => 'sectionend', 'id' => 'hwit_stddt_tracking_options' )
    );

    return apply_filters( 'hwit_stddt_get_tracking_settings_fields', $fields );
}

add_action( 'woocommerce_update_options_tracking', 'hwit_stddt_save_tracking_settings' );
// Salva i campi
function hwit_stddt_save_tracking_settings() {
    // Verifica il nonce
    if ( ! isset( $_POST['hwit_stddt_tracking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hwit_stddt_tracking_nonce'] ) ), 'hwit_stddt_tracking_settings' ) ) {
		error_log('admin settings save FAIL');
        return;
    }
    $options = hwit_stddt_get_tracking_settings_fields();

    foreach ( $options as $option ) {
        if ( isset( $_POST[ $option['id'] ] ) ) {
            update_option( $option['id'], sanitize_text_field( $_POST[ $option['id'] ] ) );
        }
    }
}

/**
 * Adds 'Tracking' column header to 'Orders' page immediately after 'Total' column.
 *
 * @param string[] $columns
 * @return string[] $new_columns
 */
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'hwit_stddt_add_order_tracking_column_header', 20 );
function hwit_stddt_add_order_tracking_column_header( $columns ) {

    $new_columns = array();

    foreach ( $columns as $column_name => $column_info ) {

        $new_columns[ $column_name ] = $column_info;

        if ( 'order_total' === $column_name ) {
            $new_columns['order_tracking'] = __( 'Tracking', 'hw-shipment-tracking-ddt-for-woocommerce' );
        }
    }

    return $new_columns;
}

/**
 * Adds 'Tracking' column content to 'Orders' page immediately after 'Total' column.
 *
 * @param string[] $column name of column being displayed
 */
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'hwit_stddt_add_order_tracking_column_content', 10, 2);
function hwit_stddt_add_order_tracking_column_content( $column, $order ) {

    if ( 'order_tracking' === $column ) {

		$tracking_info = "";
		$corriere = $order->get_meta('_corriere');
		$codice_tracciamento = $order->get_meta('_codice_tracciamento');
		$link_tracciamento = $order->get_meta('_link_tracciamento');
		$ddt_pdf_data = $order->get_meta('_ddt_pdf_url');
		$ddt_sent = $order->get_meta('_ddt_mail_sent_timestamp');
		$tracking_mail_sent = $order->get_meta('_tracking_mail_sent_timestamp');
		/* translator: datetime format to show DDT Sent date/time. */
		$date_format = __('d/m/Y H:i:s', 'hw-shipment-tracking-ddt-for-woocommerce');

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
			$ddt_pdf_url = (array_key_exists('url', $ddt_pdf_data)) ? $ddt_pdf_data['url'] : "";
			$ddt_pdf_filename = (array_key_exists('name', $ddt_pdf_data)) ? $ddt_pdf_data['name'] : "";
			if (!empty($ddt_pdf_url) && !empty($ddt_pdf_filename)) {
				$tracking_info .= '<span class="dashicons dashicons-pdf" style="color:red;font-size:11px;line-height:27px;width:11px;height:11px;" title="'.__('DDT', 'hw-shipment-tracking-ddt-for-woocommerce').'"></span><a href="'.$ddt_pdf_url.'" target="_blank" style="padding-left:3px;">'.$ddt_pdf_filename.'</a><br>';
			}
		}

		if (!empty($tracking_mail_sent)) {
			$tracking_info .= '<span class="dashicons dashicons-yes-alt" style="color:green;font-size:11px;line-height:27px;width:11px;height:11px;"></span><span class="hwit_stddt_tracking_sent_message_column" style="padding-left:5px;font-size:11px;">'. esc_html(__('Tracking inviato', 'hw-shipment-tracking-ddt-for-woocommerce')). '</span><br>';
		} else {

		}

		if (!empty($ddt_sent)) {
			$tracking_info .= '<span class="dashicons dashicons-yes-alt" style="color:green;font-size:11px;line-height:27px;width:11px;height:11px;"></span><span class="hwit_stddt_ddt_sent_message_column" style="padding-left:5px;font-size:11px;">'. esc_html(__('DDT inviato', 'hw-shipment-tracking-ddt-for-woocommerce')). '</span>';
		} else {

		}

		if (empty($tracking_info)) {
			$tracking_info = '<span class="dashicons dashicons-no-alt" style="color:#000;font-size:11px;line-height:27px;width:11px;height:11px;"></span><span class="hwit_stddt_no_ddt_tracking_info_column" style="padding-left:5px;font-size:11px;">'. esc_html(__('Nessun dato presente', 'hw-shipment-tracking-ddt-for-woocommerce')). '</span>';
		}

        echo wp_kses_post($tracking_info);
    }
}