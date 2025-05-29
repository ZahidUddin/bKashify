<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Bkashify_Url_Checkout {

    protected $settings;
    protected $token_handler;
    protected $logger;
    protected $base_url;

    public function __construct( $settings ) {
        $this->settings      = $settings;
        $this->token_handler = new Bkashify_Token( $settings );
        $this->logger        = wc_get_logger();
        $this->base_url      = $settings['sandbox'] === 'yes'
            ? 'https://checkout.sandbox.bka.sh/v1.2.0-beta/checkout'  // Replace if bKash provides a different URL for hosted
            : 'https://checkout.pay.bka.sh/v1.2.0-beta/checkout';
    }

    public function create_payment( $order ) {
        $callback_url = add_query_arg( 'bkashify_callback', '1', WC()->api_request_url( 'Bkashify_Callback' ) );
        $invoice      = $order->get_order_number();
        $amount       = $order->get_total();
        $payer_ref    = $order->get_billing_phone();

        // Create payment session manually or redirect
        $payment_id = uniqid( 'bkash_', true );
        update_post_meta( $order->get_id(), '_bkash_payment_id', $payment_id );

        // Simulate redirect to external hosted page
        $bkash_url = add_query_arg([
            'invoice'    => $invoice,
            'amount'     => $amount,
            'callback'   => urlencode($callback_url),
            'payerRef'   => $payer_ref,
            'paymentID'  => $payment_id,
        ], $this->base_url . '/start' ); // Simulate endpoint; replace with actual if exists

        return [
            'result'   => 'success',
            'redirect' => $bkash_url
        ];
    }

    public function execute_payment( $payment_id ) {
        // In real hosted flow, execution is typically callback-based
        // Still, simulate an optional post-callback execution if required
        return [
            'statusCode' => '0000',
            'paymentID'  => $payment_id,
            'trxID'      => uniqid( 'trx_', true ),
        ];
    }

    protected function log( $message ) {
        $this->logger->log( 'info', $message, [ 'source' => 'bkashify-url' ] );
    }
}
