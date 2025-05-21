<?php
/**
 * Plugin Name: bKashify – bKash Payment Gateway for WooCommerce
 * Description: Accept bKash payments through WooCommerce using bKashify.
 * Version: 1.0
 * Author: Zahid Uddin
 * Author URI: https://www.linkedin.com/in/zahid-uddin-4267b816b/
 * Text Domain: bkashify
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'bkashify' );
function bkashify() {
    load_plugin_textdomain( 'bkashify', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'bkashify_init_gateway_class' );
function bkashify_init_gateway_class() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-bkashify-gateway.php';
    add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
        $gateways[] = 'Bkashify_Gateway';
        return $gateways;
    } );
}