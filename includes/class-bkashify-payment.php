<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Bkashify_Payment {

    private $settings;
    private $token;
    private $base_url;
    private $logger;

    public function __construct( $settings ) {
        $this->settings = $settings;
        $this->token    = new Bkashify_Token( $settings );
        $this->logger   = wc_get_logger();
        $this->base_url = $settings['sandbox'] === 'yes'
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout';
    }

    public function create_payment( $agreement_id, $payer_reference, $amount, $invoice, $callback_url, $association_info = '' ) {
        $url = $this->base_url . '/create';

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

        if ( ! empty( $association_info ) ) {
            $body['merchantAssociationInfo'] = $association_info;
        }

        return $this->send_request( $url, $body, 'Create Payment' );
    }

    public function execute_payment( $payment_id ) {
        $url  = $this->base_url . '/execute';
        $body = [ 'paymentID' => $payment_id ];

        return $this->send_request( $url, $body, 'Execute Payment' );
    }

    public function query_payment( $payment_id ) {
        $url  = $this->base_url . '/payment/status';
        $body = [ 'paymentID' => $payment_id ];

        return $this->send_request( $url, $body, 'Query Payment' );
    }

    public function search_transaction( $trx_id ) {
        $url  = $this->base_url . '/general/searchTransaction';
        $body = [ 'trxID' => $trx_id ];

        return $this->send_request( $url, $body, 'Search Transaction' );
    }

    private function send_request( $url, $body, $action_name = '' ) {
        $token = $this->token->get_token();
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

        $response = wp_remote_post( $url, [
            'headers' => $headers,
            'body'    => json_encode( $body ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log( "$action_name Error: " . $response->get_error_message() );
            return false;
        }

        $json = wp_remote_retrieve_body( $response );
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->log( "$action_name JSON Parse Error: " . json_last_error_msg() );
            return false;
        }

        if ( isset( $data['statusCode'] ) && $data['statusCode'] !== '0000' ) {
            $this->log( "$action_name Failed: " . json_encode( $data ) );
            return false;
        }

        return $data;
    }

    private function log( $message ) {
        $this->logger->log( 'error', $message, [ 'source' => 'bkashify-payment' ] );
    }
}
