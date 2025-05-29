<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Bkashify_Gateway extends WC_Payment_Gateway {

    public $sandbox;
    public $app_key;
    public $app_secret;
    public $username;
    public $password;
    public $checkout_mode;
    public $logger;
    protected $strategy;

    public function __construct() {
        $this->id                 = 'bkashify';
        $this->method_title       = __( 'bKashify', 'bkashify' );
        $this->method_description = __( 'bKash Payment Gateway for WooCommerce.', 'bkashify' );
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title         = $this->get_option( 'title' );
        $this->description   = $this->get_option( 'description' );
        $this->sandbox       = $this->get_option( 'sandbox' ) === 'yes';
        $this->app_key       = sanitize_text_field( $this->get_option( 'app_key' ) );
        $this->app_secret    = sanitize_text_field( $this->get_option( 'app_secret' ) );
        $this->username      = sanitize_text_field( $this->get_option( 'username' ) );
        $this->password      = html_entity_decode( sanitize_text_field( $this->get_option( 'password' ) ) );
        $this->checkout_mode = $this->get_option( 'checkout_mode', 'tokenized' );
        $this->icon          = plugin_dir_url( __FILE__ ) . '../assets/bkash-logo.svg';
        $this->logger        = wc_get_logger();

        // Strategy handler
        $this->strategy = $this->load_checkout_strategy();

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
            'checkout_mode' => [
                'title'       => __( 'Checkout Mode', 'bkashify' ),
                'type'        => 'select',
                'description' => __( 'Choose between Tokenized or URL-Based Checkout.', 'bkashify' ),
                'default'     => 'tokenized',
                'options'     => [
                    'tokenized' => __( 'Tokenized Checkout', 'bkashify' ),
                    'url'       => __( 'URL-Based Checkout', 'bkashify' ),
                ],
            ],
        ];
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wc_add_notice( __( 'Invalid order.', 'bkashify' ), 'error' );
            return [ 'result' => 'fail' ];
        }

        return $this->strategy->create_payment( $order );
    }

    protected function load_checkout_strategy() {
        $strategy = null;

        $settings = [
            'sandbox'    => $this->sandbox ? 'yes' : 'no',
            'app_key'    => $this->app_key,
            'app_secret' => $this->app_secret,
            'username'   => $this->username,
            'password'   => $this->password,
        ];

        if ( $this->checkout_mode === 'url' ) {
            $strategy = new Bkashify_Url_Checkout( $settings );
        } else {
            $strategy = new Bkashify_Tokenized_Checkout( $settings );
        }

        return $strategy;
    }
}
