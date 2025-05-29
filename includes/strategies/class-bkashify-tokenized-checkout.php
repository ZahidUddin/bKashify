<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Bkashify_Tokenized_Checkout {

    protected $settings;
    protected $token_handler;
    protected $base_url;
    protected $logger;

    public function __construct( $settings ) {
        $this->settings      = $settings;
        $this->token_handler = new Bkashify_Token( $settings );
        $this->logger        = wc_get_logger();
        $this->base_url      = $settings['sandbox'] === 'yes'
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout';
    }

    public function create_payment( $order ) {
        $order_id = $order->get_id();
        $this->log("Starting create_payment for Order #{$order_id}");

        $agreement_id = get_post_meta( $order_id, '_bkash_agreement_id', true );

        if ( empty( $agreement_id ) ) {
            $this->log("No agreement ID found. Starting agreement creation...");

            $callback_url = add_query_arg([
                'bkashify_callback_agreement' => '1',
                'order_id' => $order_id,
            ], WC()->api_request_url( 'Bkashify_Callback' ));

            $payer_reference = $order->get_billing_phone();
            $amount = $order->get_total();
            $invoice = $order->get_order_number();

            $agreement_api = new Bkashify_Agreement( $this->settings );
            $response = $agreement_api->create_agreement( $payer_reference, $amount, $callback_url, $invoice );

            if ( isset( $response['bkashURL'], $response['paymentID'] ) ) {
                update_post_meta( $order_id, '_bkash_temp_agreement_id', $response['paymentID'] );
                $this->log("Agreement created. Redirecting user to approve it. PaymentID: " . $response['paymentID']);
                return [
                    'result' => 'success',
                    'redirect' => $response['bkashURL'],
                ];
            }


            $this->log("Agreement creation failed. Response: " . json_encode($response));
            wc_add_notice( __( 'Agreement creation failed.', 'bkashify' ), 'error' );
            return [ 'result' => 'fail' ];
        }

        $this->log("Agreement ID found: {$agreement_id}. Proceeding to create payment.");

        $callback_url = add_query_arg( 'bkashify_callback', '1', WC()->api_request_url( 'Bkashify_Callback' ) );
        $payer_reference = $order->get_billing_phone();
        $invoice = $order->get_order_number();
        $amount = $order->get_total();

        $body = [
            'mode'                   => '0001',
            'agreementID'            => $agreement_id,
            'payerReference'         => $payer_reference,
            'callbackURL'            => $callback_url,
            'amount'                 => $amount,
            'currency'               => 'BDT',
            'intent'                 => 'sale',
            'merchantInvoiceNumber' => $invoice,
        ];

        $response = $this->send_request( '/create', $body, 'Create Payment' );

        if ( $response && isset( $response['paymentID'], $response['bkashURL'] ) ) {
            update_post_meta( $order_id, '_bkash_payment_id', $response['paymentID'] );
            $this->log("Payment created. PaymentID: " . $response['paymentID']);
            return [
                'result'   => 'success',
                'redirect' => $response['bkashURL']
            ];
        }

        $this->log("Payment creation failed. Response: " . json_encode($response));
        wc_add_notice( __( 'bKash payment creation failed.', 'bkashify' ), 'error' );
        return [ 'result' => 'fail' ];
    }



    public function execute_payment( $payment_id ) {
        $body = [ 'paymentID' => $payment_id ];
        return $this->send_request( '/execute', $body, 'Execute Payment' );
    }

    protected function send_request( $endpoint, $body, $action_name = '' ) {
        $token = $this->token_handler->get_token();
        if ( ! $token ) {
            $this->log( "$action_name failed: Could not retrieve token." );
            return false;
        }

        $headers = [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => $token,
            'X-App-Key'     => $this->settings['app_key'],
        ];

        $url = trailingslashit( $this->base_url ) . ltrim( $endpoint, '/' );

        $response = wp_remote_post( $url, [
            'headers' => $headers,
            'body'    => json_encode( $body ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log( "$action_name error: " . $response->get_error_message() );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['statusCode'] ) && $data['statusCode'] !== '0000' ) {
            $this->log( "$action_name failed: " . json_encode( $data ) );
            return false;
        }

        return $data;
    }

    protected function log( $message ) {
        $this->logger->log( 'error', $message, [ 'source' => 'bkashify-tokenized' ] );
    }
}
