<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

if ( ! class_exists( 'HWIT_WC_STDDT_Shipped_Email' ) ) {

	class HWIT_WC_STDDT_Shipped_Email extends WC_Email {


		public $attachments;

		/**
		 * Constructor.
		 */
		public function __construct() {
			
			$this->id             = 'wc_shipped_email';
			$this->title          = __( 'Ordine Spedito (Tracking)', 'hw-shipment-tracking-ddt-for-woocommerce' );
			$this->description    = __( 'Questa email viene inviata quando sono disponibili le informazioni di tracciamento per l\'ordine.', 'hw-shipment-tracking-ddt-for-woocommerce' );
			$this->customer_email = true;
			$this->heading        = __( 'Informazioni di Tracciamento per l\'ordine #{order_id}', 'hw-shipment-tracking-ddt-for-woocommerce' );
			$this->subject        = __( 'Informazioni di Tracciamento per l\'ordine #{order_id}', 'hw-shipment-tracking-ddt-for-woocommerce' );
			$this->template_html  =  'emails/customer-tracking.php';
			$this->template_plain =  'emails/plain/customer-tracking.php';

			// Triggers for this email
				add_action( 'woocommerce_order_status_shipped_notification', array( $this, 'check_kind_of_trigger' ), 10, 2 );


			// Call parent constructor
			parent::__construct();
		}

		public function manual_trigger( $order ) {
			$order_id = $order->get_id();
			hwit_stddt_debug('Manual trigger started (class-wc-email-shipped.php)');
			$this->check_kind_of_trigger( $order_id);
		}

		public function check_kind_of_trigger( $order_id ) {
			$hwit_stddt_tracking_email_type = get_option('hwit_stddt_tracking_email_type');
			$hwit_stddt_enable_email_repeat = get_option('hwit_stddt_enable_email_repeat');
			$hwit_stddt_get_email_sent_status = hwit_stddt_get_email_sent_status($order_id);

			hwit_stddt_debug('Check kind of trigger (if there is not a "Manual trigger started" above this row, that means this was triggered by "woocommerce_order_status_shipped_notification" action');
			if ($hwit_stddt_tracking_email_type == 1) {
				//1
				hwit_stddt_debug('kind of trigger = 1 (full_email)');

				if ($hwit_stddt_get_email_sent_status['shipping'] == true && $hwit_stddt_get_email_sent_status['ddt'] == true) {
					hwit_stddt_debug('both email were already sent');
					if ($hwit_stddt_enable_email_repeat) {
						hwit_stddt_debug('email repeat is enabled');
						$this->trigger_full_mail($order_id);
						$email_classes = WC()->mailer()->get_emails();
						if ( isset( $email_classes['HWIT_WC_STDDT_DDT_Email'] ) ) {
							$email_classes['HWIT_WC_STDDT_DDT_Email']->trigger( $order_id );
						}
					} else {
						hwit_stddt_debug('exit code 08 (email repeat is disabled)');
						return;
					}

				} else {
					hwit_stddt_debug('one or both emails not sent yet');
					$this->trigger_full_mail($order_id);
				}

			} elseif ($hwit_stddt_tracking_email_type == 2) {

				// 2
				hwit_stddt_debug('kind of trigger = 2 (only_shipping_email + separate DDT)');
				
				if ($hwit_stddt_get_email_sent_status['shipping'] == true) {
					hwit_stddt_debug('shipping email was already sent');
					if ($hwit_stddt_enable_email_repeat) {
						hwit_stddt_debug('email repeat is enabled)');
						$this->trigger_only_shipping_mail($order_id);
					} else {
						hwit_stddt_debug('exit code 08 (email repeat is disabled)');
						return;
					}
				} else {
				
				hwit_stddt_debug('shipping email not sent yet');
				$this->trigger_only_shipping_mail($order_id);
				}

			}
		}

		/**
		 * Trigger the shipping email.
		 *
		 * @param int $order_id Order ID.
		 */
		public function trigger_full_mail( $order_id ) {

			hwit_stddt_debug("start trigger_full_mail");
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

			// Check if _corriere and _codice_tracciamento meta fields exist
			$corriere = $this->object->get_meta( '_corriere' );
			$codice_tracciamento = $this->object->get_meta( '_codice_tracciamento' );
			$link_tracciamento = $this->object->get_meta( '_link_tracciamento' );
			if ( empty( $codice_tracciamento ) && empty( $link_tracciamento ) ) {
				hwit_stddt_debug("exit code 02");
				return;
			}

			// Check if _ddt_pdf_url meta field exists and file exists
			$ddt_pdf_data = $this->object->get_meta( '_ddt_pdf_url' );
			if (is_array($ddt_pdf_data)) {
				if (array_key_exists('name', $ddt_pdf_data)) {
					$file_to_attach = WP_CONTENT_DIR . '/uploads/DDT/'. $order_id . '/' . $ddt_pdf_data['name'];
					if ( ! empty( $ddt_pdf_data['url'] ) ) {
						// Modify attachment URL to include absolute path
						if ( file_exists( $file_to_attach ) ) {
							$this->attachments[] = $file_to_attach;
						} else {
							hwit_stddt_debug( 'file to attach NOT EXISTS:' . $file_to_attach);
						}
					}
				} else {
					$this->attachments[] = false;
				}
			} else {
				$this->attachments[] = false;
			}

			// Send the email
			$sent = $this->send( $this->get_recipient(), $this->replace_placeholders( $this->get_subject(), $this->object ), $this->get_content(), $this->get_headers(), $this->attachments );
			if ($sent) {
				hwit_stddt_debug( 'Email sent successfully.' );
				set_transient('hwit_stddt_notice_mail_sent', true, 60);
				$this->object->update_meta_data('_tracking_mail_sent_timestamp', current_time('U'));
				$this->object->update_meta_data('_ddt_mail_sent_timestamp', current_time('U'));
				$this->object->save_meta_data();
			}

			if ( ! $sent ) {
				hwit_stddt_debug( 'Email sending failed.' );
			}
		}

		/**
		 * Trigger the separate email for shipping without DDT
		 *
		 * @param int $order_id Order ID.
		 */
		public function trigger_only_shipping_mail( $order_id ) {

			hwit_stddt_debug("start trigger_only_shipping_mail");
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

			// Check if _corriere and _codice_tracciamento meta fields exist
			$corriere = $this->object->get_meta( '_corriere' );
			$codice_tracciamento = $this->object->get_meta( '_codice_tracciamento' );
			if ( empty( $corriere ) || empty( $codice_tracciamento ) ) {
				return;
			}

			// Send the email
			$sent = $this->send( $this->get_recipient(), $this->replace_placeholders( $this->get_subject(), $this->object ), $this->get_content(), $this->get_headers(), $this->attachments);
			if ($sent) {
				hwit_stddt_debug("email sent");
				$this->object->update_meta_data('_tracking_mail_sent_timestamp', current_time('U'));
				$this->object->save_meta_data();
				hwit_stddt_debug("update order metadata");
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
					'default'     => __( 'Informazioni di Tracciamento per l\'ordine #{order_id}', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'heading' => array(
					'title'       => __( 'Intestazione', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'L\'intestazione dell\'email che il destinatario vedrà.', 'hw-shipment-tracking-ddt-for-woocommerce' ),
					'default'     => __( 'Informazioni di Tracciamento per l\'ordine #{order_id}', 'hw-shipment-tracking-ddt-for-woocommerce' ),
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
//return new HWIT_WC_STDDT_Shipped_Email();