<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

if ( ! class_exists( 'HWIT_WC_STDDT_DDT_Email' ) ) {

	class HWIT_WC_STDDT_DDT_Email extends WC_Email {


		public $attachments;

		/**
		 * Constructor.
		 */
		public function __construct() {

			$this->id             = 'wc_send_ddt_only';
			$this->title          = __( 'Invio DDT al cliente', 'hw-shipment-tracking-ddt-for-woocommerce' );
			$this->description    = __( 'Questa email viene inviata quando sono disponibili le informazioni di tracciamento per l\'ordine.', 'hw-shipment-tracking-ddt-for-woocommerce' );
			$this->customer_email = true;
			$this->heading        = __( 'Il DDT per l\'ordine #{order_id} si trova in allegato', 'hw-shipment-tracking-ddt-for-woocommerce' );
			$this->subject        = __( 'Il DDT per l\'ordine #{order_id}', 'hw-shipment-tracking-ddt-for-woocommerce' );
			$this->template_html  =  'emails/customer-ddt.php';
			$this->template_plain =  'emails/plain/customer-ddt.php';

			// Call parent constructor
			parent::__construct();
		}

		public function manual_trigger( $order ) {
			$order_id = $order->get_id();
			hwit_stddt_debug('Manual trigger started (class-wc-email-ddt.php)');
			$this->trigger( $order_id);
		}

		/**
		 * Trigger the email.
		 *
		 * @param int $order_id Order ID.
		 */
		public function trigger( $order_id ) {
			hwit_stddt_debug("start trigger");
			$this->object = wc_get_order( $order_id );

			if ( version_compare( '3.0.0', WC()->version, '>' ) ) {
				$order_email = $this->object->billing_email;
			} else {
				$order_email = $this->object->get_billing_email();
			}

			$this->recipient = $order_email;

			if ( ! $this->is_enabled() || ! $this->object || ! $this->get_recipient()) {
				hwit_stddt_debug("exit code 01");
				return;
			}

			// Check if _ddt_pdf_url meta field exists and file exists
			$ddt_pdf_data = ($this->object->get_meta( '_ddt_pdf_url' )) ?? false;
			if (is_array($ddt_pdf_data)) {
				if (array_key_exists('name', $ddt_pdf_data)) {
					$file_to_attach =  WP_CONTENT_DIR . '/uploads/DDT/'. $order_id . '/' . $ddt_pdf_data['name'];
					if ( ! empty( $ddt_pdf_data['url'] ) ) {
						// Modify attachment URL to include absolute path
						//error_log( 'file to attach:' . $file_to_attach);
						if ( file_exists( $file_to_attach ) ) {
							$this->attachments[] = $file_to_attach;
						} else {
						hwit_stddt_debug( 'exit code 06 - file to attach NOT EXISTS:' . $file_to_attach);
						set_transient('hwit_stddt_notice_ddt_missing', true, 60);
						return;
						}
					} else {
						hwit_stddt_debug("exit code 03 (mail not sent since there is no DDT file to attach)");
						set_transient('hwit_stddt_notice_ddt_missing', true, 60);
						return;
					}
				} else {
					hwit_stddt_debug("exit code 04 (mail not sent since there is no DDT file to attach)");
					set_transient('hwit_stddt_notice_ddt_missing', true, 60);
					return;
				}
			} else {
				hwit_stddt_debug("exit code 05 (mail not sent since there is no DDT file to attach) ");
				set_transient('hwit_stddt_notice_ddt_missing', true, 60);
				return;
			}

			$hwit_stddt_enable_email_repeat = get_option('hwit_stddt_enable_email_repeat');
			$hwit_stddt_get_email_sent_status = hwit_stddt_get_email_sent_status($order_id);
			$sent = false;
				if ($hwit_stddt_get_email_sent_status['ddt'] == true) {
					hwit_stddt_debug('ddt email was already sent');
					if ($hwit_stddt_enable_email_repeat) {
						hwit_stddt_debug('email repeat is enabled');
						// Send the email
						$sent = $this->send( $this->get_recipient(), $this->replace_placeholders( $this->get_subject(), $this->object ), $this->get_content(), $this->get_headers(), $this->attachments );
					} else {
						hwit_stddt_debug('exit code 08 (email repeat is disabled)');
						return;
					}

				} else {
					hwit_stddt_debug('ddt email not sent yet');
					// Send the email
					$sent = $this->send( $this->get_recipient(), $this->replace_placeholders( $this->get_subject(), $this->object ), $this->get_content(), $this->get_headers(), $this->attachments );
				}


			if ($sent) {
				hwit_stddt_debug( 'Email sent successfully.' );
				$_SESSION['hwit_stddt_notice_mail_sent'] = true;
				hwit_stddt_debug( 'Email sent successfully (DDT).' );
				$this->object->update_meta_data('_ddt_mail_sent_timestamp', current_time('U'));
				$this->object->save_meta_data();
			}

			if ( ! $sent ) {
				hwit_stddt_debug( 'Email sending failed.' );
			}
		}

		/**
		 * Replace placeholders in subject and heading.
		 *
		 * @param string $string String containing placeholders.
		 * @param WC_Order $order Order object.
		 * @return string
		 */
		public function replace_placeholders( $string, $order ) {
			$replacements = array(
				'{order_id}' => $order->get_order_number(),
				'{order_heading}' => $this->heading,
				'{order_subject}' => $this->subject,
			);

			return str_replace( array_keys( $replacements ), array_values( $replacements ), $string );
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html() {
			ob_start();
			wc_get_template( $this->template_html, array(
				'order'         => $this->object,
				'email_heading' => $this->replace_placeholders( $this->get_heading(), $this->object),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			), '', HWIT_WC_STDDT_PLUGIN_EMAIL_PATH );
			return ob_get_clean();
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			ob_start();
			wc_get_template( $this->template_plain, array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			), '', HWIT_WC_STDDT_PLUGIN_EMAIL_PATH );
			return ob_get_clean();
		}

		/**
		 * Initialize settings form fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Abilita/Disabilita', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Abilita questa email', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'default' => 'yes',
				),
				'subject' => array(
					'title'       => __( 'Oggetto', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'L\'oggetto dell\'email che il destinatario vedrà.', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'default'     => __( 'Il DDT per l\'ordine {order_id}', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'heading' => array(
					'title'       => __( 'Intestazione', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'L\'intestazione dell\'email che il destinatario vedrà.', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'default'     => __( 'Il DDT per l\'ordine {order_id} si trova in allegato', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'email_type' => array(
					'title'       => __( 'Tipo email', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Scegli se inviare questa email come HTML o testo semplice.', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
				),
			);
		}

	}

}
//return new HWIT_WC_STDDT_DDT_Email();