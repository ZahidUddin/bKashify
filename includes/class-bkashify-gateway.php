<?php

if ( ! defined( 'ABSPATH' ) ) exit;

require_once plugin_dir_path( __FILE__ ) . 'class-bkashify-token.php';

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

        $this->icon   = plugin_dir_url( __FILE__ ) . '../assets/bkash-logo.svg';
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

    public function is_available() {
        return 'yes' === $this->enabled && ! empty( $this->app_key ) && ! empty( $this->username );
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        $token_api = new Bkashify_Token([
            'app_key'    => $this->app_key,
            'app_secret' => $this->app_secret,
            'username'   => $this->username,
            'password'   => $this->password,
            'sandbox'    => $this->sandbox ? 'yes' : 'no',
        ]);

        $token = $token_api->get_token();

        if ( ! $token ) {
            wc_add_notice( __( 'bKash authentication failed. Please try again later.', 'bkashify' ), 'error' );
            return;
        }

        // Placeholder: Here you will implement create agreement + payment request

        $order->add_order_note( 'bKash payment initiated with token: ' . substr( $token, 0, 10 ) . '...' );
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
