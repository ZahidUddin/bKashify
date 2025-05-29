<?php
/**
 * Plugin Name: bKashify – bKash Payment Gateway for WooCommerce
 * Description: Accept bKash payments through WooCommerce using bKashify.
 * Version: 1.1.0
 * Author: Zahid Uddin
 * Author URI: https://www.linkedin.com/in/zahid-uddin-4267b816b/
 * Text Domain: bkashify
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Load plugin text domain for internationalization
add_action( 'plugins_loaded', 'bkashify_load_textdomain' );
function bkashify_load_textdomain() {
    load_plugin_textdomain( 'bkashify', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Load bKashify Payment Gateway
add_action( 'plugins_loaded', 'bkashify_init_gateway_class', 20 );
function bkashify_init_gateway_class() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    // Autoload required classes
    $includes = [
        'includes/class-bkashify-gateway.php',
        'includes/class-bkashify-token.php',
        'includes/class-bkashify-agreement.php',
        'includes/class-bkashify-payment.php',
        'includes/class-bkashify-callback.php',
        'includes/strategies/class-bkashify-tokenized-checkout.php',
        'includes/strategies/class-bkashify-url-checkout.php',
    ];

    foreach ( $includes as $file ) {
        $path = plugin_dir_path( __FILE__ ) . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }

    // Register the gateway
    add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
        $gateways[] = 'Bkashify_Gateway';
        return $gateways;
    } );

    new Bkashify_Callback();
}