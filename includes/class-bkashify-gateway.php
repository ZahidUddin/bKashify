<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Bkashify_Gateway extends WC_Payment_Gateway {

    private $logger;

    public function __construct() {
        $this->id                 = 'bkashify';
        $this->method_title       = __( 'bKashify', 'bkashify' );
        $this->method_description = __( 'bKash Payment Gateway for WooCommerce.', 'bkashify' );
        $this->has_fields         = true;

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
        $this->icon   = plugin_dir_url( __FILE__ ) . '../assets/bkash-logo.svg';

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
        return 'yes' === $this->enabled;
    }

    public function payment_fields() {
        echo '<p>' . esc_html( $this->description ) . '</p>';
        echo '<p><label>' . esc_html__( 'bKash Transaction ID (for demo)', 'bkashify' ) . '</label><input type="text" name="bkash_transaction_id" required /></p>';
    }

    public function validate_fields() {
        if ( empty( $_POST['bkash_transaction_id'] ) ) {
            wc_add_notice( __( 'Please enter your bKash transaction ID.', 'bkashify' ), 'error' );
            return false;
        }
        return true;
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Example logic: In a real integration, use API token, create payment, execute
        $transaction_id = sanitize_text_field( $_POST['bkash_transaction_id'] );

        if ( empty( $transaction_id ) || strlen( $transaction_id ) < 8 ) {
            wc_add_notice( __( 'Invalid bKash transaction ID.', 'bkashify' ), 'error' );
            return;
        }

        $order->add_order_note( 'bKash Transaction ID: ' . $transaction_id );
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
