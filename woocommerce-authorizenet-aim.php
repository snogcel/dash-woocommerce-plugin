<?php
/* Authorize.net AIM Payment Gateway Class */
class SPYR_AuthorizeNet_AIM extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
	function __construct() {

		// The global ID for this Payment method
		$this->id = "spyr_authorizenet_aim";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "Authorize.net AIM", 'spyr-authorizenet-aim' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "Authorize.net AIM Payment Gateway Plug-in for WooCommerce", 'spyr-authorizenet-aim' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "Authorize.net AIM", 'spyr-authorizenet-aim' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;

		// Bool. Can be set to true if you want payment fields to show on the checkout 
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

		// Supports the default credit card form
		// $this->supports = array( 'default_credit_card_form' );

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// Lets check for SSL
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );

		// Check for callback
		add_action( 'woocommerce_api_spyr_authorizenet_aim', array( $this, 'check_response' ) );

        // API Routes
        add_action( 'receiver_callback', array( $this, 'valid_response' ) );
		add_action( 'verify_receiver', array( $this, 'verify_response' ) );
		add_action( 'site_currency', array( $this, 'return_currency' ) );

        // get order_id from address
		add_action( 'get_order_id', array( $this, 'get_order_id' ) );




		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		
	} // End __construct()

	// Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'spyr-authorizenet-aim' ),
				'label'		=> __( 'Enable this payment gateway', 'spyr-authorizenet-aim' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'spyr-authorizenet-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'spyr-authorizenet-aim' ),
				'default'	=> __( 'Dash', 'spyr-authorizenet-aim' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'spyr-authorizenet-aim' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'spyr-authorizenet-aim' ),
				'default'	=> __( 'Pay securely using Dash.', 'spyr-authorizenet-aim' ),
				'css'		=> 'max-width:350px;'
			),
			'api_login' => array(
				'title'		=> __( 'Dashpay API Key', 'spyr-authorizenet-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the API Key provided by the Dash Payment Service when you signed up for an account.', 'spyr-authorizenet-aim' ),
			),
			'trans_key' => array(
				'title'		=> __( 'Dashpay User Account', 'spyr-authorizenet-aim' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'This is an arbitrary user account presently.', 'spyr-authorizenet-aim' ),
			),
			'environment' => array(
				'title'		=> __( 'Authorize.net Test Mode', 'spyr-authorizenet-aim' ),
				'label'		=> __( 'Enable Test Mode', 'spyr-authorizenet-aim' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'spyr-authorizenet-aim' ),
				'default'	=> 'no',
			)
		);		
	}

	// check callback response

    public function check_response() {

	    // get $_POST callback
	    $rest_json = file_get_contents("php://input");

	    if( empty( $rest_json ) )
                wp_die( 'PayPal Request Failure', 'PayPal IPN', array( 'response' => 500 ) );

	    // decode JSON
	    $_POST = json_decode($rest_json, true);

        error_log( print_r( $_POST, true ) );

        if ( isset( $_POST['receiver_status'] ) ) {
            // verify_response($_POST)
            do_action( 'verify_receiver', $_POST );
            exit;
        } else if ( isset( $_POST['get_order_id'] ) ) {
            do_action( 'get_order_id', $_POST );
            exit;
        } else if ( isset( $_POST['site_currency'] ) ) {
            // return_currency($_POST)
            do_action( 'site_currency', $_POST );
            exit;
        } else {
            // valid_response($_POST)
            do_action( 'receiver_callback', $_POST );
            exit;
        }

    }

    public function get_order_id( $post_data ) {
        global $wpdb;

        $querystr = "
            SELECT $wpdb->posts.id, $wpdb->postmeta.meta_key, $wpdb->postmeta.meta_value
            FROM $wpdb->posts, $wpdb->postmeta
            WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
            AND $wpdb->postmeta.meta_value = 'yTg2oym3oCQQAQfyHx5ELdp11vUA3NbYsc'
            AND ( $wpdb->postmeta.meta_key = 'receiver_id'
              OR $wpdb->postmeta.meta_key = 'username'
              OR $wpdb->postmeta.meta_key = 'dash_payment_address'
              OR $wpdb->postmeta.meta_key = 'amount_fiat'
              OR $wpdb->postmeta.meta_key = 'type_fiat'
              OR $wpdb->postmeta.meta_key = 'base_fiat'
              OR $wpdb->postmeta.meta_key = 'amount_duffs'
              OR $wpdb->postmeta.meta_key = 'description'
            ) ORDER BY $wpdb->posts.post_date DESC
         ";

         $querystr = "
                     SELECT $wpdb->posts.id, $wpdb->postmeta.meta_key, $wpdb->postmeta.meta_value
                     FROM $wpdb->posts, $wpdb->postmeta
                     WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
                     AND $wpdb->postmeta.meta_value = '" . $post_data['dash_payment_address'] . "'
                     ORDER BY $wpdb->posts.post_date DESC
                  ";

        $order_id = $wpdb->get_results($querystr, OBJECT);

         echo json_encode($order_id);

    }

    public function get_order( $post_data ) {
            global $wpdb;

            $querystr = "
                SELECT $wpdb->posts.id, $wpdb->postmeta.meta_key, $wpdb->postmeta.meta_value
                FROM $wpdb->posts, $wpdb->postmeta
                WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
                AND $wpdb->postmeta.meta_value = 'yTg2oym3oCQQAQfyHx5ELdp11vUA3NbYsc'
                AND ( $wpdb->postmeta.meta_key = 'receiver_id'
                  OR $wpdb->postmeta.meta_key = 'username'
                  OR $wpdb->postmeta.meta_key = 'dash_payment_address'
                  OR $wpdb->postmeta.meta_key = 'amount_fiat'
                  OR $wpdb->postmeta.meta_key = 'type_fiat'
                  OR $wpdb->postmeta.meta_key = 'base_fiat'
                  OR $wpdb->postmeta.meta_key = 'amount_duffs'
                  OR $wpdb->postmeta.meta_key = 'description'
                ) ORDER BY $wpdb->posts.post_date DESC
             ";

             $querystr = "
                         SELECT $wpdb->posts.id, $wpdb->postmeta.meta_key, $wpdb->postmeta.meta_value
                         FROM $wpdb->posts, $wpdb->postmeta
                         WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
                         AND $wpdb->postmeta.meta_value = '" . $post_data['dash_payment_address'] . "'
                         ORDER BY $wpdb->posts.post_date DESC
                      ";

            $order_id = $wpdb->get_results($querystr, OBJECT);

             echo json_encode($order_id);

    }



    public function return_currency( $request ) {

        $currency = get_woocommerce_currency();
	    $symbol = get_woocommerce_currency_symbol();
        echo json_encode(array("currency"=>$currency,"symbol"=>$symbol));

    }

    // verify order status of receiver

    Public function verify_response( $postData ) {

        // Get order by order_id
        $customer_order = new WC_Order( $postData['order_id'] );

        $status = $customer_order->status;

        $return_url = get_post_meta( $customer_order->id, 'return_url', true );

        $txid = get_post_meta( $customer_order->id, 'txid', true ); // txid
        $txlock = get_post_meta( $customer_order->id, 'txlock', true ); // txlock (InstantSend)
        $amount_duffs = get_post_meta( $customer_order->id, 'amount_duffs', true );


        echo json_encode(array("order_status"=>$status, "return_url"=>$return_url, "txid"=>$txid, "txlock"=>$txlock, "amount_duffs"=>$amount_duffs));

    }

    // received valid response

    public function valid_response( $receiverCallback ) {

        global $wpdb;


    // handle post response

    // lookup by address

        $querystr = "
                 SELECT $wpdb->posts.id, $wpdb->postmeta.meta_key, $wpdb->postmeta.meta_value
                 FROM $wpdb->posts, $wpdb->postmeta
                 WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
                 AND $wpdb->postmeta.meta_value = '" . $receiverCallback['dash_payment_address'] . "'
                 ORDER BY $wpdb->posts.post_date DESC
                 ";

        $order_id = $wpdb->get_row($querystr);
    
        $customer_order = new WC_Order( $order_id->id );

	update_post_meta( $customer_order->id, 'txid', $receiverCallback['txid'] );
	update_post_meta( $customer_order->id, 'txlock', var_export($receiverCallback['txlock'], true) ); // convert boolean to string

	// check if correct amount paid

	$amount_duffs = get_post_meta( $customer_order->id, 'amount_duffs', true );

//	if ($receiverCallback['payment_received_amount_duffs'] == $amount_duffs) {
		$customer_order->payment_complete(); // zero confirmation - marks payment as "processing"
//	} else {
//		$customer_order->update_status('on-hold', __('Incorrect Payment Amount', 'spyr_authorizenet_aim'));
//	}

    }

	// Submit payment and handle response
	public function process_payment( $order_id ) {
		global $woocommerce;

		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );

		// Are we testing right now or is it a real transaction
		$environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

		// Decide which URL to post to
		$environment_url = ( "FALSE" == $environment )
						   ? 'https://dev-test.dash.org/dash-payment-service/createReceiver'
						   : 'https://dev-test.dash.org/dash-payment-service/createReceiver';

		// This is where the fun stuff begins
		$payload = array(
		    // Dashpay API Key and Credentials
		    "api_key"              	=> $this->api_login,
		    "email"                	=> $this->trans_key,

            // TODO - description doesn't need to be included any more

		    // Order Details
		    "currency"              	=> "USD",
		    "amount"             	    => $customer_order->order_total,
		    "description"        	    => str_replace( "#", "", $customer_order->get_order_number() ),

		    // Callback URL
		    "callbackUrl"           	=> WC()->api_request_url( 'spyr_authorizenet_aim' )


			// Authorize.net Credentials and API Info
			//"x_tran_key"           	=> $this->trans_key,
			//"x_login"              	=> $this->api_login,
			//"x_version"            	=> "3.1",

			// Order total
			//"x_amount"             	=> $customer_order->order_total,

			// Credit Card Information
			//"x_card_num"           	=> str_replace( array(' ', '-' ), '', $_POST['spyr_authorizenet_aim-card-number'] ),
			//"x_card_code"          	=> ( isset( $_POST['spyr_authorizenet_aim-card-cvc'] ) ) ? $_POST['spyr_authorizenet_aim-card-cvc'] : '',
			//"x_exp_date"           	=> str_replace( array( '/', ' '), '', $_POST['spyr_authorizenet_aim-card-expiry'] ),

			//"x_type"               	=> 'AUTH_CAPTURE',
			//"x_invoice_num"        	=> str_replace( "#", "", $customer_order->get_order_number() ),
			//"x_test_request"       	=> $environment,
			//"x_delim_char"         	=> '|',
			//"x_encap_char"         	=> '',
			//"x_delim_data"         	=> "TRUE",
			//"x_relay_response"     	=> "FALSE",
			//"x_method"             	=> "CC",

			// Billing Information
			//"x_first_name"         	=> $customer_order->billing_first_name,
			//"x_last_name"          	=> $customer_order->billing_last_name,
			//"x_address"            	=> $customer_order->billing_address_1,
			//"x_city"              	=> $customer_order->billing_city,
			//"x_state"              	=> $customer_order->billing_state,
			//"x_zip"                	=> $customer_order->billing_postcode,
			//"x_country"            	=> $customer_order->billing_country,
			//"x_phone"              	=> $customer_order->billing_phone,
			//"x_email"              	=> $customer_order->billing_email,

			// Shipping Information
			//"x_ship_to_first_name" 	=> $customer_order->shipping_first_name,
			//"x_ship_to_last_name"  	=> $customer_order->shipping_last_name,
			//"x_ship_to_company"    	=> $customer_order->shipping_company,
			//"x_ship_to_address"    	=> $customer_order->shipping_address_1,
			//"x_ship_to_city"       	=> $customer_order->shipping_city,
			//"x_ship_to_country"    	=> $customer_order->shipping_country,
			//"x_ship_to_state"      	=> $customer_order->shipping_state,
			//"x_ship_to_zip"        	=> $customer_order->shipping_postcode,

			// Some Customer Information
			//"x_cust_id"            	=> $customer_order->user_id,
			//"x_customer_ip"        	=> $_SERVER['REMOTE_ADDR'],

		);


		// Send this payload to Authorize.net for processing
		$response = wp_remote_post( $environment_url, array(
			'method'    => 'POST',
			'body'      => http_build_query( $payload ),
			'timeout'   => 90,
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) )
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'spyr-authorizenet-aim' ) );

		if ( empty( $response['body'] ) )
			throw new Exception( __( 'Authorize.net\'s Response was empty.', 'spyr-authorizenet-aim' ) );

		// Retrieve the body's resopnse if no errors found
		$json = wp_remote_retrieve_body( $response );

		if( empty( $json ) )
			return false;

		$json = json_decode( $json, true );

	    // add order note with Payment Receiver metadata
    	update_post_meta( $customer_order->id, 'receiver_id', $json['receiver_id'] );
    	update_post_meta( $customer_order->id, 'username', $json['username'] );
    	update_post_meta( $customer_order->id, 'dash_payment_address', $json['dash_payment_address'] );
    	update_post_meta( $customer_order->id, 'amount_fiat', $json['amount_fiat'] );
    	update_post_meta( $customer_order->id, 'type_fiat', $json['type_fiat'] );
    	update_post_meta( $customer_order->id, 'base_fiat', $json['base_fiat'] );
    	update_post_meta( $customer_order->id, 'amount_duffs', $json['amount_duffs'] );
    	update_post_meta( $customer_order->id, 'description', $json['description'] );

    	update_post_meta( $customer_order->id, 'return_url', $this->get_return_url( $customer_order ) );


	    // Mark order as Paid
		// $customer_order->payment_complete();

	    // Empty the cart (Very important step)
		// $woocommerce->cart->empty_cart();

	    // Redirect to thank you page

		if (0) { // query internal API to see if payment receiver has been paid

        		return array(
            			'result'   => 'success',
            			'redirect' => $this->get_return_url( $customer_order ),
        		);

		} else { // waiting for payment receiver

            $response = '<span><strong>Payment Receiver Created</strong></span>';
            $response .= '<span class="hidden" id="order_id">' . $customer_order->get_order_number() . '</span>';

            $response .= '<span class="hidden" id="receiver_id">' . $json["receiver_id"] . '</span>';
            $response .= '<span class="hidden" id="username">' . $json["username"] . '</span>';
            $response .= '<span class="hidden" id="dash_payment_address">' . $json["dash_payment_address"] . '</span>';
            $response .= '<span class="hidden" id="amount_fiat">' . $json["amount_fiat"] . '</span>';
            $response .= '<span class="hidden" id="type_fiat">' . $json["type_fiat"] . '</span>';
            $response .= '<span class="hidden" id="base_fiat">' . $json["base_fiat"] . '</span>';
            $response .= '<span class="hidden" id="amount_duffs">' . $json["amount_duffs"] . '</span>';
            $response .= '<span class="hidden" id="description">' . $json["description"] . '</span>';

            $response .= '<span class="hidden" id="return_url">' . $this->get_return_url( $customer_order ) . '</span>';

            $amount_dash = round($json["amount_duffs"]/100000000, 2);
            $amount_dots = round($json["amount_duffs"]/100, 2);

            $dialog_box =  '<div id="modal">';
            $dialog_box .= '    <!-- Page content -->';
            $dialog_box .= '    <div class="row">';
            $dialog_box .= '        <div class="col-xs-12 col-md-6">';
            $dialog_box .= '            <div id="qrcode"></div>';
            $dialog_box .= '        </div>';
            $dialog_box .= '        <div class="col-xs-12 col-md-6">';
            $dialog_box .= '            <div class="form-group row">';
            $dialog_box .= '                <label class="col-form-label formLabel formLabel_amount" id="formatted_dash">' . $amount_dash . ' DASH</label>';
            $dialog_box .= '                <span class="formValue" id="formatted_duffs>' . $amount_dots . ' dots</span><br />';
            $dialog_box .= '            </div>';
            $dialog_box .= '            <div class="form-group row">';
            $dialog_box .= '                <label class="col-form-label formLabel">Status</label>';
            $dialog_box .= '                <span class="formValue" id="checkout_status">Pending</span>';
            $dialog_box .= '            </div>';
            $dialog_box .= '        </div>';
            $dialog_box .= '    </div>';
            $dialog_box .= '    <div class="row">';
            $dialog_box .= '        <div class="col-xs-12">';
            $dialog_box .= '            <div><strong>Address: </strong><span class="formLabel_address">' . $json["dash_payment_address"] . '</span></div>';
            $dialog_box .= '        </div>';
            $dialog_box .= '    </div>';
            $dialog_box .= '</div>';

            $response .= $dialog_box;

			wc_add_notice( $response, 'notice' );

			return array(
            			'result'   => 'success',
            			'refresh'   => 'true'
        		);

			$woocommerce->cart->empty_cart();

		}




		// Get the values we need
		// $r['response_code']             = $resp[0];
		// $r['response_sub_code']         = $resp[1];
		// $r['response_reason_code']      = $resp[2];
		// $r['response_reason_text']      = $resp[3];

		// Test the code to know if the transaction went through or not.
		// 1 or 4 means the transaction was a success
		// if ( ( $r['response_code'] == 1 ) || ( $r['response_code'] == 4 ) ) {
			// Payment has been successful
		//	$customer_order->add_order_note( __( 'Authorize.net payment completed.', 'spyr-authorizenet-aim' ) );

			// Mark order as Paid
		//	$customer_order->payment_complete();

			// Empty the cart (Very important step)
		//	$woocommerce->cart->empty_cart();

			// Redirect to thank you page
		//	return array(
		//		'result'   => 'success',
		//		'redirect' => $this->get_return_url( $customer_order ),
		//	);
		// } else {
			// Transaction was not succesful
			// Add notice to the cart
		//	wc_add_notice( $r['response_reason_text'], 'error' );
			// Add note to the order for your reference
		//	$customer_order->add_order_note( 'Error: '. $r['response_reason_text'] );
		// }

	}

	// Validate fields
	public function validate_fields() {
		return true;
	}

	// Check if we are forcing SSL on checkout pages
	// Custom function not required by the Gateway
	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";
			}
		}
	}

} // End of SPYR_AuthorizeNet_AIM



// custom js and css

function jquery_qrcode() {
    wp_register_script('qrcode', plugins_url('js/jquery-qrcode.min.js', __FILE__), array('jquery'), 0.1, true);
    wp_enqueue_script('qrcode');
}
add_action( 'wp', 'jquery_qrcode' );

function jquery_shorten() {
    wp_register_script('shorten', plugins_url('js/jquery.shorten.min.js', __FILE__), array('jquery'), 0.1, true);
    wp_enqueue_script('shorten');
}
add_action( 'wp', 'jquery_shorten' );

function izi_modal() {
    wp_register_script('modal', plugins_url('js/iziModal.min.js', __FILE__), array('jquery'), 0.1, true);
    wp_enqueue_script('modal');
}
add_action( 'wp', 'izi_modal' );

// popup css
function iziz_modal_style()
{
    wp_register_style( 'modal_style', plugins_url( '/css/iziModal.min.css', __FILE__ ), array(), '20120208', 'all' );
    wp_enqueue_style( 'modal_style' );
}
add_action( 'wp_enqueue_scripts', 'iziz_modal_style' );

// other css
function custom_style()
{
    wp_register_style( 'custom', plugins_url( '/css/style.css', __FILE__ ), array(), '20120208', 'all' );
    wp_enqueue_style( 'custom' );
}
add_action( 'wp_enqueue_scripts', 'custom_style' );


function dash_payment_service() {
    wp_register_script('receiver', plugins_url('js/checkout.js', __FILE__), array('jquery'), 8.1, true);
	wp_enqueue_script('receiver');
}
add_action( 'wp', 'dash_payment_service' );

?>
