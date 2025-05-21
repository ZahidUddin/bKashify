<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Bkashify_Gateway extends WC_Payment_Gateway {

    private $logger;

    public function __construct() {
        $this->id                 = 'bkashify';
        $this->method_title       = __( 'bKashify', 'bkashify' );
        $this->method_description = __( 'bKash Payment Gateway for WooCommerce.', 'bkashify' );
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->sandbox     = $this->get_option( 'sandbox' ) === 'yes';
        $this->app_key     = sanitize_text_field( $this->get_option( 'app_key' ) );
        $this->app_secret  = sanitize_text_field( $this->get_option( 'app_secret' ) );
        $this->username    = sanitize_text_field( $this->get_option( 'username' ) );
        $this->password    = sanitize_text_field( $this->get_option( 'password' ) );

        $this->logger = wc_get_logger();

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'bkashify' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable bKashify Payment Gateway', 'bkashify' ),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __( 'Title', 'bkashify' ),
                'type'        => 'text',
                'description' => __( 'This controls the title visible to the customer.', 'bkashify' ),
                'default'     => __( 'bKash Payment', 'bkashify' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Description', 'bkashify' ),
                'type'        => 'textarea',
                'default'     => __( 'Pay securely using your bKash wallet.', 'bkashify' ),
            ],
            'sandbox' => [
                'title'   => __( 'Sandbox Mode', 'bkashify' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Sandbox Mode (for testing)', 'bkashify' ),
                'default' => 'yes',
            ],
            'app_key' => [
                'title' => __( 'App Key', 'bkashify' ),
                'type'  => 'text',
            ],
            'app_secret' => [
                'title' => __( 'App Secret', 'bkashify' ),
                'type'  => 'text',
            ],
            'username' => [
                'title' => __( 'Username', 'bkashify' ),
                'type'  => 'text',
            ],
            'password' => [
                'title' => __( 'Password', 'bkashify' ),
                'type'  => 'password',
            ],
        ];
    }

    public function process_admin_options() {
        parent::process_admin_options();
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $this->log( 'Simulated payment for order: ' . $order_id );
        $order->payment_complete();
        wc_reduce_stock_levels( $order_id );
        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        ];
    }

    private function log( $message ) {
        $this->logger->info( $message, [ 'source' => 'bkashify' ] );
    }
}
