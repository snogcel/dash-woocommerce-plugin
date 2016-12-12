<?php
/* Authorize.net AIM Payment Gateway Class */
class DASH_Checkout extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
	function __construct() {

		// The global ID for this Payment method
		$this->id = "dash_checkout";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "DASH Checkout", 'dash-checkout' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "DASH Checkout Plug-in for WooCommerce", 'dash-checkout' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "DASH Checkout", 'dash-checkout' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;

		// Bool. Can be set to true if you want payment fields to show on the checkout 
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

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
		add_action( 'woocommerce_api_dash_checkout', array( $this, 'check_response' ) );

        // API Routes
        add_action( 'receiver_callback', array( $this, 'receiver_callback' ) );
		add_action( 'receiver_status', array( $this, 'receiver_status' ) );
		add_action( 'site_currency', array( $this, 'site_currency' ) ); // return site currency to checkout js
		add_action( 'get_order_id', array( $this, 'get_order_id' ) ); // get order id from address
		add_action( 'confirm_transaction', array( $this, 'confirm_transaction' ) ); // confirm tx confirmation

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
				'title'		=> __( 'Enable / Disable', 'dash-checkout' ),
				'label'		=> __( 'Enable this payment gateway', 'dash-checkout' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'dash-checkout' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'dash-checkout' ),
				'default'	=> __( 'DASH', 'dash-checkout' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'dash-checkout' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'dash-checkout' ),
				'default'	=> __( 'Pay privately and securely with DASH.', 'dash-checkout' ),
				'css'		=> 'max-width:350px;'
			),
			'api_key' => array(
				'title'		=> __( 'API Key', 'dash-checkout' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the API Key provided by the Dash Payment Service when you signed up for an account.', 'dash-checkout' ),
			),
			'username' => array(
				'title'		=> __( 'API Username', 'dash-checkout' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is an arbitrary user account presently.', 'dash-checkout' ),
			),
            'provider' => array(
                'title'		=> __( 'API Endpoint', 'dash-checkout' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'This is the API Key provided by the Dash Payment Service when you signed up for an account.', 'dash-checkout' ),
                'default'	=> __( 'https://dev-test.dash.org/dash-payment-service/createReceiver', 'dash-checkout' ),
            ),
			'environment' => array(
				'title'		=> __( 'Test Mode', 'dash-checkout' ),
				'label'		=> __( 'Enable Test Mode', 'dash-checkout' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'dash-checkout' ),
				'default'	=> 'no',
			)
		);		
	}

	// check callback response

    public function check_response() {

	    // get $_POST callback
	    $rest_json = file_get_contents("php://input");

	    if( empty( $rest_json ) )
                wp_die( 'DASH Payment Receiver Callback Failure', 'Payment Receiver Callback', array( 'response' => 500 ) );

	    // decode JSON
	    $_POST = json_decode($rest_json, true);

        if ( isset( $_POST['receiver_status'] ) ) {
            do_action( 'receiver_status', $_POST );
            exit;

        } else if ( isset( $_POST['get_order_id'] ) ) {
            do_action( 'get_order_id', $_POST );
            exit;

        } else if ( isset( $_POST['confirm_transaction'] ) ) {
			do_action( 'confirm_transaction', $_POST );
			exit;

        } else if ( isset( $_POST['site_currency'] ) ) {
            do_action( 'site_currency', $_POST );
            exit;

        } else {
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
                 AND $wpdb->postmeta.meta_value = '" . $post_data['dash_payment_address'] . "'
                 ORDER BY $wpdb->posts.post_date DESC
              ";

        $order_id = $wpdb->get_results($querystr, OBJECT);

        echo json_encode($order_id);

    }

    public function confirm_transaction( $post_data ) {
        global $wpdb;

        // TODO - grab from plugin settings

        // get order_id by receiver_id
        $querystr = "
                 SELECT $wpdb->posts.id, $wpdb->postmeta.meta_key, $wpdb->postmeta.meta_value
                 FROM $wpdb->posts, $wpdb->postmeta
                 WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
                 AND $wpdb->postmeta.meta_value = '" . $post_data['receiver_id'] . "'
                 ORDER BY $wpdb->posts.post_date DESC
              ";

        $result = $wpdb->get_results($querystr, OBJECT);
        $order_id = $result[0]->id;

        // Get order by order_id
        $customer_order = new WC_Order( $order_id );

        $status = $customer_order->status;
        $return_url = get_post_meta( $customer_order->id, 'return_url', true );

        $response = wp_remote_get( 'https://dev-test.dash.org/insight-api-dash/tx/' . $post_data['txid'] );

        try {

            // Note that we decode the body's response since it's the actual JSON feed
            $json = json_decode( $response['body'], true );

            $confirmations = $json['confirmations'];

            if ($confirmations > 0) { // TODO - configurable number of confirmations

                $customer_order->update_status( 'completed' );

                echo json_encode(array("order_id"=>$order_id, "return_url"=>$return_url, "confirmations"=>$confirmations, "status"=>$customer_order->status));

            } else {

                echo json_encode(array("order_id"=>$order_id, "confirmations"=>$confirmations, "status"=>$customer_order->status));

			}

        } catch ( Exception $ex ) {

            echo json_encode(array("err"=>$ex));

        }

    }

    public function site_currency( $request ) {

        $currency = get_woocommerce_currency();
	    $symbol = get_woocommerce_currency_symbol();
        echo json_encode(array("currency"=>$currency,"symbol"=>$symbol));

    }

    // retrieve order status by order id
    Public function receiver_status( $postData ) {

        // Get order by order_id
        $customer_order = new WC_Order( $postData['order_id'] );

        $status = $customer_order->status;

        $return_url = get_post_meta( $customer_order->id, 'return_url', true );

        $txid = get_post_meta( $customer_order->id, 'txid', true ); // txid
        $txlock = get_post_meta( $customer_order->id, 'txlock', true ); // txlock (InstantSend)
        $amount_duffs = get_post_meta( $customer_order->id, 'amount_duffs', true );

		if ($txlock == 'true') {
            $customer_order->update_status( 'completed' );
            echo json_encode(array("order_status"=>$status, "return_url"=>$return_url, "txid"=>$txid, "txlock"=>$txlock, "amount_duffs"=>$amount_duffs));
		} else {
            echo json_encode(array("order_status"=>$status, "txid"=>$txid, "txlock"=>$txlock, "amount_duffs"=>$amount_duffs));
		}

    }

    // process Payment Receiver Callback
    public function receiver_callback( $receiverCallback ) {

        global $wpdb;

        // lookup order by payment receiver address
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

        if ($receiverCallback['payment_received_amount_duffs'] == $amount_duffs) {

		    $customer_order->update_status( 'processing' );

	    } else {

            $customer_order->update_status( 'on-hold' );

    	}

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
						   ? $this->get_option( 'provider' )
						   : 'https://dev-test.dash.org/dash-payment-service/createReceiver';

		// This is where the fun stuff begins
		$payload = array(
		    // Dashpay API Key and Credentials
		    "api_key"              	=> $this->api_key,
		    "email"                	=> $this->username,

		    // Order Details
		    "currency"              	=> "USD",
		    "amount"             	    => $customer_order->order_total,

		    // Callback URL
		    "callbackUrl"           	=> WC()->api_request_url( 'dash_checkout' )
		);


		// Send this payload to Dash Payment Service for processing
		$response = wp_remote_post( $environment_url, array(
			'method'    => 'POST',
			'body'      => http_build_query( $payload ),
			'timeout'   => 90,
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) )
			throw new Exception( __( 'We are currently experiencing problems trying to connect to Dash Payment Service. Sorry for the inconvenience.', 'dash-checkout' ) );

		if ( empty( $response['body'] ) )
			throw new Exception( __( 'Dash Payment Service Response was empty.', 'dash-checkout' ) );

		// Retrieve the body's resopnse if no errors found
		$json = wp_remote_retrieve_body( $response );

		if( empty( $json ) )
			return false;

		$json = json_decode( $json, true );

	    // store Payment Receiver into Order Meta

    	update_post_meta( $customer_order->id, 'receiver_id', $json['receiver_id'] );
    	update_post_meta( $customer_order->id, 'username', $json['username'] );
    	update_post_meta( $customer_order->id, 'dash_payment_address', $json['dash_payment_address'] );
    	update_post_meta( $customer_order->id, 'amount_fiat', $json['amount_fiat'] );
    	update_post_meta( $customer_order->id, 'type_fiat', $json['type_fiat'] );
    	update_post_meta( $customer_order->id, 'base_fiat', $json['base_fiat'] );
    	update_post_meta( $customer_order->id, 'amount_duffs', $json['amount_duffs'] );

    	update_post_meta( $customer_order->id, 'return_url', $this->get_return_url( $customer_order ) );


        // inject Payment Receiver into checkout page

        $response = '<span><strong>Payment Receiver Created</strong></span>';
        $response .= '<span class="hidden" id="order_id">' . $customer_order->get_order_number() . '</span>';

        $response .= '<span class="hidden" id="receiver_id">' . $json["receiver_id"] . '</span>';
        $response .= '<span class="hidden" id="username">' . $json["username"] . '</span>';
        $response .= '<span class="hidden" id="dash_payment_address">' . $json["dash_payment_address"] . '</span>';
        $response .= '<span class="hidden" id="amount_fiat">' . $json["amount_fiat"] . '</span>';
        $response .= '<span class="hidden" id="type_fiat">' . $json["type_fiat"] . '</span>';
        $response .= '<span class="hidden" id="base_fiat">' . $json["base_fiat"] . '</span>';
        $response .= '<span class="hidden" id="amount_duffs">' . $json["amount_duffs"] . '</span>';

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

}



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
    wp_register_script('receiver', plugins_url('js/checkout.js', __FILE__), array('jquery'), 9.4, true);
	wp_enqueue_script('receiver');
}
add_action( 'wp', 'dash_payment_service' );

?>
