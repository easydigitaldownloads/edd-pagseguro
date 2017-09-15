<?php
/*
Plugin Name: Easy Digital Downloads - PagSeguro Payment Gateway
Plugin URL: http://easydigitaldownloads.com/extension/pagseguro
Description: Adds the PagSeguro Gateway to Easy Digital Downloads
Version: 1.4.5
Author: Pippin Williamson
Author URI: http://www.mattvarone.com
*/

/**
* EDD PagSeguro Gateway
*
* @package PagSeguro Gateway
* @author Pippin Williamson
*/

if ( ! class_exists( 'EDD_PagSeguro_Gateway' ) )
{

	class EDD_PagSeguro_Gateway
	{

		/**
		* Path to the plugin dir
		*
		* @since    1.0
		*/

		private $plugin_path;


		/**
		* Sends emails for each step of the IPN notification.
		*
		* @since    1.3
		*/

		private $debug = false;


		/**
		* EDD PagSeguro Gateway
		*
		* Waits for plugins_loaded and launches first method.
		*
		* @return   void
		* @since    1.0
		*/

		function __construct()
		{
			// wait and fire plugins_loaded method
			add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
			add_action( 'init', array( &$this, 'listen_for_pagseguro_ipn' ) );
			if( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->debug = true;
			}
		}


		/**
		* EDD PagSeguro Gateway
		*
		* Internationalization, payment process and init method.
		*
		* @return   void
		* @since    1.0
		*/

		function plugins_loaded()
		{

			// set plugin path
			$this->plugin_path = plugin_dir_path( __FILE__ );

			// load internationalization
			load_plugin_textdomain( 'edd-pagseguro-gateway', false, $this->plugin_path . '/lan' );

			// process payments
			add_action( 'edd_gateway_pagseguro', array( &$this, 'process_payment' ) );
			add_filter( 'edd_purchase_form_required_fields', array( $this, 'require_last_name' ) );

			// fire init method
			add_action( 'init', array( &$this, 'init' ), -1 );

			if( class_exists( 'EDD_License' ) ) {
				$license = new EDD_License( __FILE__, 'PagSeguro Payment Gateway', '1.4.4', 'Pippin Williamson' );
			}
		}


		/**
		* Init
		*
		* Sets the necessary filters and action.
		*
		* @return   void
		* @since    1.0
		*/

		function init()
		{

			// set filters
			add_filter( 'edd_payment_gateways', array( &$this, 'register_gateway' ) );
			add_filter( 'edd_settings_gateways', array( &$this, 'add_settings' ) );
			add_filter( 'edd_payment_confirm_pagseguro', array( &$this, 'payment_confirm' ) );

			// set actions
			add_action( 'edd_pagseguro_cc_form', array( &$this, 'cc_form' ) );
		}


		/**
		* Register Gateway
		*
		* Registers the PagSeguro gateway.
		*
		* @return   array
		* @since    1.0
		*/

		function register_gateway( $gateways )
		{
			$gateways['pagseguro'] = array( 'admin_label' => 'PagSeguro', 'checkout_label' => 'PagSeguro' );
			return $gateways;
		}


		/**
		* Add Settings
		*
		* Adds the PagSeguro gateway settings.
		*
		* @return   array
		* @since    1.0
		*/

		function add_settings( $settings )
		{

			// Credentials Links
			$credentials_link = sprintf( '<a href="https://pagseguro.uol.com.br/integracao/token-de-seguranca.jhtml">%s</a>', __( 'get it here', 'edd-pagseguro-gateway' ) );

			// IPN settings links
			$ipn_settings_link = sprintf( '<a href="https://pagseguro.uol.com.br/integracao/notificacao-de-transacoes.jhtml">%s</a>', __( 'IPN settings', 'edd-pagseguro-gateway' ) );

			$gateway_settings = array(
				array(
					'id' => 'pagseguro_settings',
					'name' => '<strong>' . __( 'PagSeguro Settings', 'edd-pagseguro-gateway' ) . '</strong>',
					'desc' => __( 'Configure your PagSeguro Settings', 'edd-pagseguro-gateway' ),
					'type' => 'header'
				),
				array(
					'id' => 'pagseguro_currency',
					'name' => __( 'IMPORTANT', 'edd-pagseguro-gateway' ),
					'desc' => '<strong>' . __( 'PagSeguro only supports payments in Brazilian Reals ( BRL ). This Gateway does not support the test mode account, all payments will be processed live.', 'edd-pagseguro-gateway' ) . '</strong>',
					'type' => 'pagseguro_notes',
				),
				array(
					'id' => 'pagseguro_ipn',
					'name' => __( 'IPN', 'edd-pagseguro-gateway' ),
					'desc' => sprintf( __( 'Set your PagSeguro %s to notify this URL: %s', 'edd-pagseguro-gateway' ), $ipn_settings_link, '<br/> <textarea type="text" class="large-text" disabled="disabled">' . get_site_url() . '/</textarea>' ),
					'type' => 'pagseguro_notes',
				),
				array(
					'id' => 'pagseguro_client_email',
					'name' => __( 'Client Email', 'edd-pagseguro-gateway' ),
					'desc' => __( 'Enter your PagSeguro client email', 'edd-pagseguro-gateway' ),
					'type' => 'text',
					'size' => 'regular'
				),
				array(
					'id' => 'pagseguro_client_token',
					'name' => __( 'Client Token', 'edd-pagseguro-gateway' ),
					'desc' => sprintf( __( 'Enter your PagSeguro client token ( %s )', 'edd-pagseguro-gateway' ), $credentials_link ),
					'type' => 'text',
					'size' => 'regular'
				)
			);

			return array_merge( $settings, apply_filters( 'edd_pagseguro_gateway_settings', $gateway_settings ) );
		}


		/**
		* Credit Card Form
		*
		* Registers the PagSeguro gateway.
		*
		* @return   null
		* @since    1.0
		*/

		function cc_form() {
			// we only register the action so that the default CC form is not shown
		}


		/**
		* Get Credentials
		*
		* Gets the PagSeguro gateway credentials.
		*
		* @return   array
		* @since    1.0
		*/

		function get_credentials()
		{
			global $edd_options;

			return array(
				'email' => isset( $edd_options['pagseguro_client_email'] ) ? trim( $edd_options['pagseguro_client_email'] ) : null,
				'token' => isset( $edd_options['pagseguro_client_token'] ) ? trim( $edd_options['pagseguro_client_token'] ) : null
			 );

		}


		/**
		* Load PagSeguro SDK
		*
		* Loads the necessary PagSeguro SDK files.
		*
		* @return   void
		* @since    1.0
		*/

		function load_pagseguro_sdk()
		{
			require_once( $this->plugin_path . 'lib/PagSeguroLibrary/PagSeguroLibrary.php' );
		}


		/**
		* Payment Confirm
		*
		* Checks for payment response.
		*
		* @return   void
		* @since    1.0
		*/

		function payment_confirm( $content )
		{
			global $edd_options;

			// check if there is a confirmation arg
			if ( ! isset( $_GET['payment-confirmation'] ) || ( $_GET['payment-confirmation'] != 'pagseguro' ) ) {
				// return regular content
				return $content;
			}

			// check if it's pending mode
			if ( isset( $_GET['payment-pending'] ) && $_GET['payment-pending'] == 'true' ) {
				// generate pending mode output
				ob_start();
				do_action( 'edd_pagseguro_before_pending' );
				?>
				<p><?php _e( 'Thanks for completing the checkout. <strong>Your Payment is in pending mode</strong>. Please complete the process with PagSeguro to access your purchased files.', 'edd-pagseguro-gateway' ); ?></p>
				<?php
				do_action( 'edd_pagseguro_after_pending' );
				return ob_get_clean();
			}

			// return succesful confirmation
			return $content;
		}

		/**
		* Listen for PagSeguro IPN
		*
		* PagSeguro instant payment notifications.
		*
		* @return   void
		* @since    1.0
		*/

		function listen_for_pagseguro_ipn()
		{
			global $edd_options;

			// check for incoming order id
			$code = isset( $_POST['notificationCode'] ) && trim( $_POST['notificationCode'] ) !== "" ? trim( $_POST['notificationCode'] ) : null;
			$type = isset( $_POST['notificationType'] ) && trim( $_POST['notificationType'] ) !== "" ? trim( $_POST['notificationType'] ) : null;

			// check for the edd-listener in the URL request
			if ( is_null( $code ) || is_null( $type ) ) {
				return;
			}

			// debug notification
			if ( $this->debug === true ) {
				wp_mail(get_bloginfo( 'admin_email' ), __('PagSeguro Gateway Debug 1: Incoming Notification'), var_export($_POST, true));
			}

			// get credentials
			$credentials = $this->get_credentials();

			// check credentials have been set
			if ( is_null( $credentials['email'] ) || is_null( $credentials['token'] ) ) {
				return;
			}

			// debug credentials
			if ( $this->debug === true ) {
				wp_mail(get_bloginfo( 'admin_email' ), __('PagSeguro Gateway Debug 2: Credentials'), 'OK');
			}

			// require PagSeguro files
			$this->load_pagseguro_sdk();

			// verify classes exists
			if ( ! class_exists( 'PagSeguroNotificationType' ) ) {
			   return;
			}

			// debug sdk
			if ( $this->debug === true ) {
				wp_mail(get_bloginfo( 'admin_email' ), __('PagSeguro Gateway Debug 3: SDK'), 'OK');
			}

			// get notification
			$notificationType = new PagSeguroNotificationType( $type );
			$strType = $notificationType->getTypeFromValue();

			// debug type
			if ( $this->debug === true ) {
				wp_mail(get_bloginfo( 'admin_email' ), __('PagSeguro Gateway Debug 4: Notification Type'), var_export($strType, true));
			}

			// try to verify the notification
			try {

				// generate credentials
				$credentials = new PagSeguroAccountCredentials( $credentials['email'], $credentials['token'] );

				// notification service
				$transaction = PagSeguroNotificationService::checkTransaction( $credentials, $code );

				// debug check
				if ( $this->debug === true ) {
					wp_mail(get_bloginfo( 'admin_email' ), __('PagSeguro Gateway Debug 5: Transaction Check'), 'OK');
				}

				// get both values
				$reference = $transaction->getReference();
				$status = $transaction->getStatus();

				// check there is an external reference
				if ( isset( $reference ) && isset( $status ) ) {

					// check for succesful status
					if ( $status->getValue() == 3 ) {

						// debug status
						if ( $this->debug === true ) {
							wp_mail(get_bloginfo( 'admin_email' ), __('PagSeguro Gateway Debug 6: Status Check'), 'OK');
						}

						// update succesful payment
						edd_update_payment_status( $reference, 'publish' );

					} else {
						// debug status
						if ( $this->debug === true ) {
							wp_mail(get_bloginfo( 'admin_email' ), __('PagSeguro Gateway Debug 6: Status Check'), 'ERROR');
						}
					}

				} else {
					// debug reference/status error
					if ( $this->debug === true ) {
						wp_mail(get_bloginfo( 'admin_email' ), __('PagSeguro Gateway Debug 8: Reference/Status Check'), 'ERROR');
					}
				}

			} catch ( Exception $e ) {
				wp_mail( get_bloginfo( 'admin_email' ), __( 'PagSeguro IPN Service Error', 'edd-pagseguro-gateway' ), $e->getMessage() );
				return;
			}

		}


		/**
		* Process Payment
		*
		* Process payments trough the PagSeguro gateway.
		*
		* @return   void
		* @since    1.0
		*/

		function process_payment( $purchase_data )
		{
			global $edd_options;

			// check there is a gateway name
			if ( ! isset( $purchase_data['post_data']['edd-gateway'] ) )
			return;

			// get credentials
			$credentials = $this->get_credentials();

			// check credentials have been set
			if ( is_null( $credentials['email'] ) || is_null( $credentials['token'] ) ) {
			  edd_set_error( 0, __( 'Please enter your PagSeguro Client Email and Token in settings', 'edd-pagseguro-gateway' ) );
			  edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
			}

			// get payment
			$payment_data = array(
				'price'         => $purchase_data['price'],
				'date'          => $purchase_data['date'],
				'user_email'    => $purchase_data['user_email'],
				'purchase_key'  => $purchase_data['purchase_key'],
				'currency'      => edd_get_option( 'currency', 'BRL' ),
				'downloads'     => $purchase_data['downloads'],
				'user_info'     => $purchase_data['user_info'],
				'cart_details'  => $purchase_data['cart_details'],
				'status'        => 'pending'
			 );

			// insert pending payment
			$payment = edd_insert_payment( $payment_data );

			if ( ! $payment ) {
				// problems? send back
				edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
			} else {

				// require PagSeguro files
				$this->load_pagseguro_sdk();

				// verify classes exists
				if ( ! class_exists( 'PagSeguroPaymentRequest' ) )
				edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );

				// create payment request
				$paymentRequest = new PagSeguroPaymentRequest();

				// sets the currency
				$paymentRequest->setCurrency( 'BRL' );

				// cart summary
				$cart_summary = edd_get_purchase_summary( $purchase_data, false );

				// format total price
				$total_price = number_format( $purchase_data['price'], 2, '.', '' );

				// payment request details
				$paymentRequest->addItem( '01', sanitize_text_field( substr( $cart_summary, 0, 95 ) ), '1', strval( $total_price ) );

				// sets the reference code for this request
				$paymentRequest->setReference( $payment );

				// sets customer information
				$paymentRequest->setSender( trim( sanitize_text_field( $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'] ) ), $purchase_data['user_email'] );

				// redirect url
				$paymentRequest->setRedirectUrl( add_query_arg( 'payment-confirmation', 'pagseguro', edd_get_success_page_uri() ) );

				// IPN URL
				$paymentRequest->addParameter( 'notificationURL', get_site_url() );

				/* TRY CHECKOUT */

				try {

					// generate credentials
					$credentials = new PagSeguroAccountCredentials( $credentials['email'], $credentials['token'] );

					// register this payment request in PagSeguro, to obtain the payment URL for redirect your customer
					$checkout_uri = $paymentRequest->register( $credentials );

					if ( gettype( $checkout_uri ) != 'string' ) {
						throw new exception( $checkout_uri );
					}

					// empty cart
					edd_empty_cart();

					// send the user to PagSeguro
					wp_redirect( $checkout_uri );
					die();

				} catch ( Exception $e ) {
					//catch exception
					wp_mail( get_bloginfo( 'admin_email' ), __( 'PagSeguro Checkout Error', 'edd-pagseguro-gateway' ), $e->getMessage() );
					edd_set_error( 'pagseguro_exception', $e->getMessage() );
					edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
				}

			}

		}

		public function require_last_name( $required ) {

			$required['edd_last'] = array(
				'error_id' => 'invalid_last_name',
				'error_message' => __( 'Please enter your last name', 'edd-pagseguro-gateway' )
			);

			return $required;
		}

	} // EDD_PagSeguro_Gateway

	new EDD_PagSeguro_Gateway();

}

if ( ! function_exists( 'edd_pagseguro_notes_callback' ) ) {
	function edd_pagseguro_notes_callback( $args ) {
		echo $args['desc'];
	}
}