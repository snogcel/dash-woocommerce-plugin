<?php
/*
Plugin Name: Dashpay - WooCommerce Gateway
Plugin URI: http://store.slayer.work/
Description: Extends WooCommerce with Dash Payment Functionality.
Version: 1.0
Author: Jon Kindel
Author URI: http://www.slayer.work
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'dash_checkout_init', 0 );
function dash_checkout_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once('woocommerce-dash-checkout.php');

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'dash_checkout_gateway' );
	function dash_checkout_gateway( $methods ) {
		$methods[] = 'DASH_Checkout';
		return $methods;
	}
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'dash_checkout_action_links' );
function dash_checkout_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'spyr-authorizenet-aim' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}
