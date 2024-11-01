<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://hardweb.it/
 * @since             1.0
 * @package           hw-shipment-tracking-ddt-for-woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Shipment Tracking DDT for WooCommerce
 * Description:       Aggiungi il codice di tracciamento e allega i DDT ai tuoi ordini WooCommerce!
 * Version:           1.5.2
 * Requires at least: 6.0.1
 * Tested up to:      6.6.2
 * Requires PHP:      8.0
 * WC requires at least: 9.0
 * WC tested up to:   9.3.3
 * Requires Plugins:  woocommerce
 * Author:            Hardweb.it
 * Author URI:        https://hardweb.it/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       hw-shipment-tracking-ddt-for-woocommerce
 * Domain Path:       /languages
 */
define( 'HWIT_WC_STDDT_PLUGIN_VERSION', '1.5.2' );

use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

//plugin constants
define( 'HWIT_WC_STDDT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HWIT_WC_STDDT_PLUGIN_EMAIL_PATH', plugin_dir_path( __FILE__ ) . 'templates/');
define( 'HWIT_WC_STDDT_PLUGIN_INC_PATH', plugin_dir_path( __FILE__ ) . 'includes/');
define( 'HWIT_WC_STDDT_DEBUG', get_option('hwit_stddt_enable_debug'));
//Load Classes
require_once('includes/wc-shipped-status-functions.php');
require_once('includes/wc-admin-options.php');
require_once('includes/wc-frontend-functions.php');

function hwit_stddt_debug($message) {
	if (HWIT_WC_STDDT_DEBUG) { error_log('HWIT_WC_STDDT_DEBUG: '.$message); }
}

function hwit_stddt_get_email_sent_status($order_id) {
	$order = wc_get_order( $order_id );
	
	$ddt_sent = $order->get_meta('_ddt_mail_sent_timestamp');
	$tracking_mail_sent = $order->get_meta('_tracking_mail_sent_timestamp');
	$status_email_sent = array('shipping'=>false, 'ddt'=>false);
	if (!empty($ddt_sent)) { $status_email_sent['ddt'] = true; }
	if (!empty($tracking_mail_sent)) { $status_email_sent['shipping'] = true; }
	
	return $status_email_sent;
}

add_action('admin_notices', 'hwit_stddt_wc_admin_notice');
function hwit_stddt_wc_admin_notice(){
	
	if (get_transient('hwit_stddt_notice_ddt_missing')) {
		echo '<div class="notice notice-error is-dismissible"><p>'.esc_html(__('La mail non è stata inviata perché manca il file del DDT', 'hw-shipment-tracking-ddt-for-woocommerce')).'</p></div>';
		delete_transient('hwit_stddt_notice_ddt_missing');
	}	
	
	if (get_transient('hwit_stddt_notice_mail_sent')) {
		echo '<div class="notice notice-success is-dismissible"><p>'.esc_html(__('Email inviata con successo', 'hw-shipment-tracking-ddt-for-woocommerce')).'</p></div>';
		delete_transient('hwit_stddt_notice_mail_sent');
	}

}

add_action( 'admin_enqueue_scripts', 'hwit_stddt_load_css_orders_list' );
function hwit_stddt_load_css_orders_list() {
    // Verifica se sei nella sezione amministrativa e sulla schermata degli ordini di WooCommerce
    if ( is_admin() ) {
        // Carica il file CSS solo sulla pagina degli ordini
        wp_enqueue_style( 'hw-shipment-tracking-ddt-for-woocommerce', HWIT_WC_STDDT_PLUGIN_URL . '/assets/css/hw-shipment-tracking-ddt-for-woocommerce.css', null, HWIT_WC_STDDT_PLUGIN_VERSION, 'all' );
    }
}

 // If HPOS is active then use custom meta box as CMB2 does not yet support HPOS.
add_action( 'woocommerce_loaded', 'hwit_stddt_check_hpos_active' );
function hwit_stddt_check_hpos_active() {

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		// If HPOS is active then use custom meta box as CMB2 does not yet support HPOS.
		add_action( 'add_meta_boxes', 'hwit_stddt_add_meta_box' );
		add_action( 'woocommerce_process_shop_order_meta', 'hwit_stddt_save_tracking_info_meta_box_data', 10, 2 );
	} else {
		// If HPOS not active then use CMB2.
		add_action( 'admin_notices', 'hwit_stddt_verify_hpos_active' );
	}
}

// Aggiunge la metabox nella gestione dell'ordine
function hwit_stddt_add_meta_box() {

	$screen = wc_get_container()->get( CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

    add_meta_box(
        'hwit_stddt_tracking_info',
        __('Tracking ordine', 'hw-shipment-tracking-ddt-for-woocommerce'),
        'hwit_stddt_render_meta_box',
		$screen,
		'side',
		'high'
    );
}

function hwit_stddt_render_meta_box($post) {

	$order = ( $post instanceof WP_Post ) ? wc_get_order( $post->ID ) : $post;

	if ( ! $order ) { return; }

	wp_nonce_field( plugin_basename(__FILE__), 'hw_shipment_tracking_ddt_info_nonce' );
    $corriere = $order->get_meta('_corriere');
    $codice_tracciamento = $order->get_meta('_codice_tracciamento');
    $link_tracciamento = $order->get_meta('_link_tracciamento');
    $ddt_pdf_data = $order->get_meta('_ddt_pdf_url');
	$ddt_sent = $order->get_meta('_ddt_mail_sent_timestamp');
	$tracking_mail_sent = $order->get_meta('_tracking_mail_sent_timestamp');

	$file_exists = false;
	if (is_array($ddt_pdf_data)) {
		if (!array_key_exists('url', $ddt_pdf_data)) {
			$ddt_pdf_url = "#";
			$ddt_pdf_filename = "nessun file caricato";
		} else {
			$file_exists = true;
			$ddt_pdf_url = $ddt_pdf_data['url'];
			$ddt_pdf_filename = $ddt_pdf_data['name'];
		}
	} else {
		$ddt_pdf_url = "#";
		$ddt_pdf_filename = "nessun file caricato";
	}

	/* translator: datetime format to show DDT Sent date/time. */
	$date_format = __('d/m/Y H:i:s', 'hw-shipment-tracking-ddt-for-woocommerce');

    ?>
	 <form id="hwit_stddt_render_meta_box_form" method="post" enctype="multipart/form-data">
		<div class="hwit_stddt_ddt_tracking_sent_wrapper" style="border-bottom: 1px solid #eee;">
			<p>
				<?php if (!empty($tracking_mail_sent)) { ?>
				<div class="hwit_stddt_tracking_sent_wrapper" style="padding:5px;border-radius:5px;border: 1px solid green;background-color:#f0fff6;">
					<span class="dashicons dashicons-yes-alt" style="color:green;"></span><span class="hwit_stddt_tracking_sent_message" style="padding-left:10px;font-size:11px;"><?php esc_html_e('Tracking inviato il', 'hw-shipment-tracking-ddt-for-woocommerce'); ?>&nbsp;<?php echo esc_attr(date_i18n($date_format, $tracking_mail_sent)); ?></span>
				</div>
				<?php } else { ?>
				<div class="hwit_stddt_tracking_not_sent_wrapper" style="padding:5px;border-radius:5px;border: 1px solid grey;background-color:#d7cad2;">
					<span class="dashicons dashicons-no-alt" style="color:#000;"></span><span class="hwit_stddt_tracking_not_sent_message" style="padding-left:10px;"><?php esc_html_e('Tracking non ancora inviato', 'hw-shipment-tracking-ddt-for-woocommerce'); ?></span>
				</div>
				<?php } ?>
			</p>
			<p>
				<?php if (!empty($ddt_sent)) { ?>
				<div class="hwit_stddt_ddt_sent_wrapper" style="padding:5px;border-radius:5px;border: 1px solid green;background-color:#f0fff6;">
					<span class="dashicons dashicons-yes-alt" style="color:green;"></span><span class="hwit_stddt_ddt_sent_message" style="padding-left:10px;font-size:11px;"><?php esc_html_e('DDT inviato il', 'hw-shipment-tracking-ddt-for-woocommerce'); ?>&nbsp;<?php echo esc_attr(date_i18n($date_format, $ddt_sent)); ?></span>
				</div>
				<?php } else { ?>
				<div class="hwit_stddt_ddt_not_sent_wrapper" style="padding:5px;border-radius:5px;border: 1px solid grey;background-color:#d7cad2;">
					<span class="dashicons dashicons-no-alt" style="color:#000;"></span><span class="hwit_stddt_ddt_not_sent_message" style="padding-left:10px;"><?php esc_html_e('DDT non ancora inviato', 'hw-shipment-tracking-ddt-for-woocommerce'); ?></span>
				</div>
				<?php } ?>
			</p>
		</div>
		<p>
			<label for="corriere"><?php esc_html_e('Corriere:', 'hw-shipment-tracking-ddt-for-woocommerce'); ?></label><br>
			<select name="corriere" id="corriere">
				<option value="" selected>- <?php esc_html_e('Seleziona', 'hw-shipment-tracking-ddt-for-woocommerce'); ?> -</option>
				<option value="Bartolini" <?php selected($corriere, 'Bartolini'); ?>>Bartolini</option>
				<option value="SDA" <?php selected($corriere, 'SDA'); ?>>SDA</option>
				<option value="TNT" <?php selected($corriere, 'TNT'); ?>>TNT</option>
				<option value="UPS" <?php selected($corriere, 'UPS'); ?>>UPS</option>
				<option value="DHL" <?php selected($corriere, 'DHL'); ?>>DHL</option>
				<option value="InPost" <?php selected($corriere, 'InPost'); ?>>InPost</option>
				<option value="GLS" <?php selected($corriere, 'GLS'); ?>>GLS</option>
				<option value="FEDEX" <?php selected($corriere, 'FEDEX'); ?>>FEDEX</option>
				<option value="Poste Italiane" <?php selected($corriere, 'Poste Italiane'); ?>>Poste Italiane</option>
				<option value="Altro" <?php selected($corriere, 'Altro'); ?>>Altro</option>
			</select>
		</p>
		<p>
			<label for="codice_tracciamento"><?php esc_html_e('Codice Tracking:', 'hw-shipment-tracking-ddt-for-woocommerce'); ?></label><br>
			<input type="text" name="codice_tracciamento" id="codice_tracciamento" value="<?php echo esc_attr($codice_tracciamento); ?>">
		</p>
		<p>
			<label for="link_tracciamento"><?php esc_html_e('Link Tracking:', 'hw-shipment-tracking-ddt-for-woocommerce'); ?></label><br>
			<input type="url" name="link_tracciamento" id="link_tracciamento" value="<?php echo esc_url($link_tracciamento); ?>">
		</p>
		<h6><?php esc_html_e('La mail con le informazioni di tracking viene inviata se almeno uno dei due campi (codice o link) è stato compilato.', 'hw-shipment-tracking-ddt-for-woocommerce'); ?></h6>
		<div class="upload-ddt_pdf" style="border-top: 1px solid #eee;">
			<p class="file_uploaded">
				<p>
					<label for="ddt_pdf"><?php esc_html_e('Carica DDT:', 'hw-shipment-tracking-ddt-for-woocommerce'); ?></label><br>
					<input type="file" name="ddt_pdf" id="ddt_pdf" data-order_id="<?php echo esc_attr($order->get_id()); ?>" accept=".pdf" maxlength="5000000">
				</p>
			</p>
			<span class="hwit_stddt_upload_message"></span>
		</div>
	<?php $class_no_file = (!$file_exists) ? "ddt_no_file" : ""; ?>
		<p class="ddt_pdf_link_container <?php echo $class_no_file; ?>">
			<label for="ddt_pdf_link"><?php esc_html_e('DDT PDF Link:', 'hw-shipment-tracking-ddt-for-woocommerce'); ?></label><br>
			<a id="ddt_pdf_link" name="ddt_pdf_link" href="<?php echo esc_attr($ddt_pdf_url); ?>" target="_blank"><?php echo esc_attr($ddt_pdf_filename); ?></a> | <a id="hwit_stddt_delete_ddt" data-order_id="<?php echo esc_attr($order->get_id()); ?>" style="color:red;cursor:pointer;"><?php echo esc_attr('Elimina file', 'hw-shipment-tracking-ddt-for-woocommerce'); ?></a>
		</p>
	</form>
    <?php
}

// Verify the nonce and that this is not a post revision or autosave.
function hwit_stddt_user_can_save( $post_id ) {
	$is_autosave = wp_is_post_autosave( $post_id );
	$is_revision = wp_is_post_revision( $post_id );

	return ! ( $is_autosave || $is_revision );
}

// Sanitize and store the updated tracking info.
function hwit_stddt_save_tracking_info_meta_box_data( $order_id, $order ) {
	$is_valid_nonce = ( isset( $_POST[ 'hw_shipment_tracking_ddt_info_nonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST [ 'hw_shipment_tracking_ddt_info_nonce' ] ) ), plugin_basename( __FILE__ ) ) );
	if (!$is_valid_nonce) { return; }
	if ( hwit_stddt_user_can_save( $order_id ) ) {
		if ( $order ) {

			if (isset($_POST['corriere'])) {
				$order->update_meta_data('_corriere', sanitize_text_field($_POST['corriere']));
				$order->save();
			}
			if (isset($_POST['codice_tracciamento'])) {
				 $order->update_meta_data('_codice_tracciamento', sanitize_text_field($_POST['codice_tracciamento']));
				 $order->save();
			}
			if (isset($_POST['link_tracciamento'])) {
				$order->update_meta_data('_link_tracciamento', esc_url_raw($_POST['link_tracciamento']));
				$order->save();
			}

		}
	}
}

// jQuery Ajax sender
add_shortcode('hwit_stddt_admin_footer_script', 'hwit_stddt_do_admin_footer_script');
function hwit_stddt_do_admin_footer_script() {
	ob_start();
    $script = '
	<script>
    jQuery(function($){
        $(document.body).on("change", "input[name=ddt_pdf]", function() {
            const files = $(this).prop("files"),
                  order_id = $(this).data("order_id");

            if (files.length) {
                const file = files[0];

                const ghostformData = new FormData();
                ghostformData.append("ddt_pdf", file);
                ghostformData.append("order_id", $(this).data("order_id"));
                ghostformData.append("security", "' . esc_js(wp_create_nonce('ddt_pdf_upload')) . '");

                $.ajax({
                    url: "' . esc_js(admin_url('admin-ajax.php?action=ddt_pdf_upload')) . '",
                    type: "POST",
                    data: ghostformData,
                    contentType: false,
                    enctype: "multipart/form-data",
                    processData: false,
                    beforeSend: function() {
                        $(".hwit_stddt_upload_message").html("<span style=\"color:#000;\">' . esc_js(__('Caricamento file in corso...', 'hw-shipment-tracking-ddt-for-woocommerce')) . '</span>");
                    },
                    success: function(response_data) {
                        $(".hwit_stddt_upload_message").html(response_data.message);
                        if (response_data.success == "true") {
                            $(".ddt_pdf_link_container").show();
							$(".ddt_pdf_link_container").removeClass("ddt_no_file");
                            $("#hwit_stddt_delete_ddt").show();
                            $("#ddt_pdf_link").show();
                            $("#ddt_pdf_link").attr("href", response_data.fileurl);
                            $("#ddt_pdf_link").text(response_data.filename);
                        } else {
                            $("#ddt_pdf").val(null);
                        }
                    },
                    error: function(error) {
                        console.log(error);
                        $(".hwit_stddt_upload_message").html("<span style=\"color:red\">' . esc_js(__('Errore: Caricamento fallito', 'hw-shipment-tracking-ddt-for-woocommerce')) . '</span>");
                        $("#ddt_pdf").val(null);
                    }
                });
            }
        });

        $("body").on("click", "#ddt_pdf_retry", function() {
            $(".hwit_stddt_error-size").hide();
            $("#ddt_pdf").trigger("click");
        });

        $(document).ready(function() {
            $("#hwit_stddt_delete_ddt").click(function() {
                var answer = window.confirm("Eliminare il file?");
                if (answer) {
                    const deleteFile = new FormData();
                    deleteFile.append("order_id", $(this).data("order_id"));
                    deleteFile.append("security", "' . esc_js(wp_create_nonce('delete_file')) . '");

                    $.ajax({
                        url: "' . esc_js(admin_url('admin-ajax.php?action=delete_file')) . '",
                        type: "POST",
                        data: deleteFile,
                        contentType: false,
                        processData: false,
                        beforeSend: function() {
                            $(".hwit_stddt_upload_message").html("<span style=\"color:red\">' . esc_js(__('Eliminazione del file in corso...', 'hw-shipment-tracking-ddt-for-woocommerce')) . '</span>");
                        },
                        success: function(response) {
                            $(".hwit_stddt_upload_message").html(response);
                            $(".ddt_pdf_link_container").hide();
							$(".ddt_pdf_link_container").addClass("ddt_no_file");
                            $("#hwit_stddt_delete_ddt").hide();
                            $("#ddt_pdf").val(null);
                        },
                        error: function(error) {
                            $(".hwit_stddt_upload_message").html("<span style=\"color:red\">' . esc_js(__('Errore durante l\'eliminazione del file', 'hw-shipment-tracking-ddt-for-woocommerce')) . '</span>");
                            $("#ddt_pdf").val(null);
                        }
                    });
                }
            });

            function set_Courier_required() {
                let tracking_code = $("#codice_tracciamento").val();
                let tracking_link = $("#link_tracciamento").val();
                if (tracking_code || tracking_link) {
                    $("#corriere").prop("required", true);
                } else {
                    $("#corriere").prop("required", false);
                }
            }

            $(document).ready(function() {
                set_Courier_required();

                $("#codice_tracciamento").on("input", function(e) {
                    set_Courier_required();
                });

                $("#link_tracciamento").on("input", function(e) {
                    set_Courier_required();
                });
            });
        });
    });
    </script>';
    echo $script;
	return ob_get_clean();
}

add_action('admin_footer', 'hwit_stddt_do_shortcode_admin_footer_script');
function hwit_stddt_do_shortcode_admin_footer_script() {
	echo do_shortcode('[hwit_stddt_admin_footer_script]');
}

// PHP Ajax responder
add_action( 'wp_ajax_ddt_pdf_upload', 'hwit_stddt_order_edit_ajax_ddt_pdf_upload' );
function hwit_stddt_order_edit_ajax_ddt_pdf_upload(){

check_ajax_referer('ddt_pdf_upload', 'security');

// Inizializza WP_Filesystem
global $wp_filesystem;
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

	$response = array('success'=>'false', 'message'=>'null', 'url'=>'');
	/* check filetype */
    if ( isset($_FILES['ddt_pdf']) && current_user_can('upload_files')) {
		/* check filetype */
		$file_name = sanitize_file_name($_FILES['ddt_pdf']['name']);
		$file_info = wp_check_filetype($file_name);
		/* 
		$file_size = wp_filesize($_FILES['ddt_pdf']['tmp_name']);
		$check_filesize = $file_size / 1024;
		*/
		
		if ($file_info['ext'] != 'pdf') {
			$response['success'] = 'false';
            $response['message'] = '<span style="color:red">' . __('Errore: Solo file PDF accettati!', 'hw-shipment-tracking-ddt-for-woocommerce') . '</span>';
			wp_send_json($response);
		}
		/*
		if ($check_filesize > 5120) {
			$response['success'] = 'false';
			/* translators: %s is filesize */
			/*
            $response['message'] = '<span style="color:red" class="hwit_stddt_error-size">' . sprintf(__('Errore: Dimensione file %s!<br>Massimo consentito 5 MB.', 'hw-shipment-tracking-ddt-for-woocommerce'), size_format($file_size, 2)) .'&nbsp;<span id="ddt_pdf_retry" style="cursor:pointer;color:#000;">'.__('Riprova', 'hw-shipment-tracking-ddt-for-woocommerce').'</span></span>';
			wp_send_json($response);
		}
		*/

        $wp_upload_dir    = wp_upload_dir();
		$order_id = (isset( $_POST['order_id'])) ? sanitize_text_field($_POST['order_id']) : false;
		if (!$order_id) {
			$response['success'] = 'false';
            $response['message'] = '<span style="color:red">' . __('Errore: Caricamento file interrotto.', 'hw-shipment-tracking-ddt-for-woocommerce') . '</span>';
			wp_send_json($response);
		}
        $upload_path   = '/DDT/' . $order_id; // <== HERE set your file path
        $upload_folder = $wp_upload_dir['basedir']  . $upload_path;
        $upload_url    = $wp_upload_dir['baseurl']  . $upload_path;

        if ( ! is_dir( $upload_folder ) ) {
            $makedir = wp_mkdir_p( $upload_folder );
			if ($makedir == true) {
				$wp_filesystem->chmod( $upload_folder, 0755 );
			} else {
				$response['success'] = 'false';
				/* translators: %s is upload_path */
				$response['message'] = '<span style="color:red">' . sprintf(__('Errore: Impossibile creare la cartella %s', 'hw-shipment-tracking-ddt-for-woocommerce'), $upload_path) . '</span>';
				wp_send_json($response);
			}
        }
        $file_path = $upload_folder . '/' . basename( $file_name );
        $file_url  = $upload_url . '/' . basename( $file_name );

		//move uploaded file to custom path
		add_filter( 'upload_dir', function($upload) use ($upload_folder, $upload_url) {
			$upload['path'] = $upload_folder;
			$upload['url'] = $upload_url;
			return $upload;
			}
		);
		$move_uploaded_file = wp_handle_upload($_FILES['ddt_pdf'], array( 'test_form' => false) );
        if( $move_uploaded_file && !array_key_exists('error', $move_uploaded_file)) {

            $order = wc_get_order(intval($_POST['order_id']));
            $order->update_meta_data('_ddt_pdf_url', array(
                'url' => $file_url,
                'name' => esc_attr( $file_name ),
            ));
            $order->save();

			$response['success'] = 'true';
            $response['message'] = '<span style="color:green">' . __('File caricato con successo!', 'hw-shipment-tracking-ddt-for-woocommerce') . '</span><br>';
			$response['fileurl'] = $file_url;
			$response['filename'] = esc_attr( $file_name );
			wp_send_json($response);
        } else {
			/* translators: %s is upload_path */
            $response['message'] = '<span style="color:red">' . sprintf(__('Errore: Upload fallito. Impossibile caricare il file nella cartella %s', 'hw-shipment-tracking-ddt-for-woocommerce'), $upload_path) . '</span>';
			wp_send_json($response);
        }
    } else {
			$response['success'] = 'false';
            $response['message'] = '<span style="color:red">' . __('Errore sconosciuto', 'hw-shipment-tracking-ddt-for-woocommerce') . '</span>';
			wp_send_json($response);
	}
}

// PHP Ajax responder
add_action( 'wp_ajax_delete_file', 'hwit_stddt_order_delete_file' );
function hwit_stddt_order_delete_file(){

check_ajax_referer('delete_file', 'security');

	$order_id = (isset($_POST['order_id'])) ? sanitize_text_field($_POST['order_id']) : false;
	if (!$order_id) {
            echo '<span style="color:red">' . esc_html(__('Errore: ordine sconosciuto.', 'hw-shipment-tracking-ddt-for-woocommerce')) . '</span>';
	} else {

		$order       = wc_get_order(intval($order_id));
	    $upload_dir    = wp_upload_dir();
        $upload_path   = '/DDT/' . $order_id . '/';
        $upload_folder = $upload_dir['basedir']  . $upload_path;

        if ( is_dir( $upload_folder ) ) {
            $files = glob($upload_folder . '*');
			foreach($files as $file){
				// Verifica se il percorso è un file e non una directory
				if(is_file($file)){
					// Elimina il file
					wp_delete_file($file);
				}
			}
        }

		//save
		$order->update_meta_data('_ddt_pdf_url', array());
		$order->save();

            echo '<span style="color:green">' . esc_html(__('File Eliminato!', 'hw-shipment-tracking-ddt-for-woocommerce')) . '</span><br>';
    }
wp_die();
}

// Aggiunge una nuova email di tracciamento
add_filter('woocommerce_email_classes', 'hwit_stddt_add_custom_emails');
function hwit_stddt_add_custom_emails($email_classes) {
    require_once('includes/class-wc-email-shipped.php');
    require_once('includes/class-wc-email-ddt.php');
    $email_classes['HWIT_WC_STDDT_Shipped_Email'] = new HWIT_WC_STDDT_Shipped_Email();
    $email_classes['HWIT_WC_STDDT_DDT_Email'] = new HWIT_WC_STDDT_DDT_Email();
return $email_classes;
}

// Add new hook for shipped notification
/*
Example for standard woocommerce_order_status_completed_notification
The hook woocommerce_order_status_completed_notification is a multi composite hook that allows to trigger the email sent to the customer when order status is changed to "completed".
It's triggered in WC_Emails send_transactional_email() method on line 170:

do_action_ref_array( current_filter() . '_notification', $args );
where current_filter() refer to woocommerce_email_actions filter hook arguments

So to have a notification working you need to add woocommerce_order_status_{custom_status} and then trigger the notification inside class by add_action('woocommerce_order_status_{custom_status}_notification', 'trigger'); which include the WC()->mailer()
Note that WC()->mailer() is just an instance object of the WC_Emails Class, that load the mailer class, similar to new WC_Emails()
*/
add_filter( 'woocommerce_email_actions', 'hwit_stddt_register_notification_hook' );
function hwit_stddt_register_notification_hook( $email_actions ) {
    $email_actions[] = 'woocommerce_order_status_shipped';
return $email_actions;
}

/**
 * Aggiungi un'opzione al menu a tendina "Azioni ordine" per inviare il DDT.
 */
add_filter( 'woocommerce_order_actions', 'hwit_stddt_add_manual_order_actions' );
function hwit_stddt_add_manual_order_actions( $actions ) {
    $actions['send_ddt_email'] = __( 'Invia DDT', 'hw-shipment-tracking-ddt-for-woocommerce' );
	$actions['send_shipped_email'] = __( 'Invia Email Tracking', 'hw-shipment-tracking-ddt-for-woocommerce' );

    return $actions;
}

/**
 * Gestisci l'azione "Invia DDT".
 */
add_action( 'woocommerce_order_action_send_ddt_email', 'hwit_stddt_action_send_ddt_email' );
function hwit_stddt_action_send_ddt_email( $order ) {
  $email_classes = WC()->mailer()->get_emails();

  if ( isset( $email_classes['HWIT_WC_STDDT_DDT_Email'] ) ) {
    $email_classes['HWIT_WC_STDDT_DDT_Email']->manual_trigger( $order );
  }
}

/**
 * Gestisci l'azione "Invia Email Tracking".
 */
add_action( 'woocommerce_order_action_send_shipped_email', 'hwit_stddt_action_send_shipped_email' );
function hwit_stddt_action_send_shipped_email( $order ) {
  $email_classes = WC()->mailer()->get_emails();

  if ( isset( $email_classes['HWIT_WC_STDDT_Shipped_Email'] ) ) {
    $email_classes['HWIT_WC_STDDT_Shipped_Email']->manual_trigger( $order );
  }
}


/* WC HPOS compatible */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Verify that CMB2 plugin is active.
function hwit_stddt_verify_hpos_active() {
	$current_screen = get_current_screen();
	if ( $current_screen->id == 'shop_order' ) {
		$plugin_data = get_plugin_data( __FILE__ );
		$plugin_name = $plugin_data['Name'];
?>
<div class="notice notice-warning is-dismissible">
	<p>
	<?php
	/* translators: %s is the Plugin name */
	echo esc_html(sprintf(__('Il plugin <strong>%s</strong> richiede che la funzione <a href="https://woocommerce.com/document/high-performance-order-storage/">HPOS</a> di WooCommerce sia attiva per poter funzionare correttamente.', 'hw-shipment-tracking-ddt-for-woocommerce'), $plugin_name)); ?>
	</p>
</div>
<?php
	}
}