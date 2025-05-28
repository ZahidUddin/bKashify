<?php

if ( ! defined( 'ABSPATH' ) ) exit;

// require_once plugin_dir_path( __FILE__ ) . 'class-bkashify-token.php';
// require_once plugin_dir_path( __FILE__ ) . 'class-bkashify-payment.php';

class Bkashify_Gateway extends WC_Payment_Gateway {

    public $sandbox;
    public $app_key;
    public $app_secret;
    public $username;
    public $password;
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
        $this->password = html_entity_decode( sanitize_text_field( $this->get_option( 'password' ) ) );

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

        $agreement_api = new Bkashify_Agreement([
            'app_key'    => $this->app_key,
            'app_secret' => $this->app_secret,
            'username'   => $this->username,
            'password'   => $this->password,
            'sandbox'    => $this->sandbox ? 'yes' : 'no',
        ]);

        $callback_url = add_query_arg('wc-api', 'bkashify_callback', home_url('/'));

        // 1. Create Agreement
        $create = $agreement_api->create_agreement(
            $order->get_billing_phone(),
            $order->get_total(),
            $callback_url,
            $order->get_order_number()
        );

        if (empty($create['paymentID'])) {
            wc_add_notice(__('Could not initiate bKash agreement.', 'bkashify'), 'error');
            return ['result' => 'fail'];
        }

        // 2. Execute Agreement
        $execute = $agreement_api->execute_agreement($create['paymentID']);

        if (empty($execute['agreementID'])) {
            wc_add_notice(__('Failed to execute bKash agreement.', 'bkashify'), 'error');
            return ['result' => 'fail'];
        }

        // 3. Create Payment
        $payment_api = new Bkashify_Payment([
            'app_key'    => $this->app_key,
            'app_secret' => $this->app_secret,
            'username'   => $this->username,
            'password'   => $this->password,
            'sandbox'    => $this->sandbox ? 'yes' : 'no',
        ]);

        $response = $payment_api->create_payment(
            $execute['agreementID'],
            $order->get_billing_phone(),
            $order->get_total(),
            $order->get_order_number(),
            $callback_url
        );

        if ( empty( $response['paymentID'] ) || empty( $response['bkashURL'] ) ) {
            $order->add_order_note( 'bKash create_payment failed: ' . json_encode( $response ) );
            wc_add_notice( __( 'bKash payment failed. Please try again.', 'bkashify' ), 'error' );
            return [ 'result' => 'fail' ];
        }

        $order->update_meta_data( '_bkash_payment_id', $response['paymentID'] );
        $order->update_meta_data( '_bkash_transaction_status', $response['transactionStatus'] ?? 'Initiated' );
        $order->save();

        return [
            'result'   => 'success',
            'redirect' => $response['bkashURL'],
        ];
    }

    private function log( $message ) {
        $this->logger->info( $message, [ 'source' => 'bkashify' ] );
    }
}
