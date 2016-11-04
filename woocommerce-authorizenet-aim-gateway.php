<?php
/*
Plugin Name: Authorize.net AIM - WooCommerce Gateway
Plugin URI: http://www.sitepoint.com/
Description: Extends WooCommerce by Adding the Authorize.net AIM Gateway.
Version: 1.0
Author: Yojance Rabelo, SitePoint
Author URI: http://www.sitepoint.com/
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'spyr_authorizenet_aim_init', 0 );
function spyr_authorizenet_aim_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-authorizenet-aim.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'spyr_add_authorizenet_aim_gateway' );
	function spyr_add_authorizenet_aim_gateway( $methods ) {
		$methods[] = 'SPYR_AuthorizeNet_AIM';
		return $methods;
	}
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'spyr_authorizenet_aim_action_links' );
function spyr_authorizenet_aim_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'spyr-authorizenet-aim' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}
