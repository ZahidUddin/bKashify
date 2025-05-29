<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Bkashify_Callback {

    private $logger;

    public function __construct() {
        $this->logger = wc_get_logger();

        // Hook into WooCommerce API endpoint
        add_action( 'woocommerce_api_Bkashify_Callback', [ $this, 'register_callback_handler' ] );
        add_action( 'init', [ $this, 'register_callback_handler' ] );
    }

    public function register_callback_handler() {
        if ( isset( $_GET['bkashify_callback_agreement'], $_GET['order_id'], $_GET['paymentID'] ) ) {
            $this->log("🔥 Triggered: handle_agreement_callback()");
            $this->handle_agreement_callback();
            exit;
        }

        if ( isset( $_GET['bkashify_callback'], $_GET['paymentID'], $_GET['status'] ) ) {
            $this->log("🔥 Triggered: handle_callback()");
            $this->handle_callback();
            exit;
        }
    }

    public function handle_agreement_callback() {
        $payment_id = sanitize_text_field( $_GET['paymentID'] ?? '' );
        $order_id   = absint( $_GET['order_id'] ?? 0 );
        $status     = sanitize_text_field( $_GET['status'] ?? '' );

        $this->log("Agreement callback: Order {$order_id}, PaymentID {$payment_id}, Status: {$status}");

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->log("❌ Invalid order.");
            wp_die( 'Invalid order.', 'bKashify Error', [ 'response' => 404 ] );
        }

        if ( strtolower( $status ) !== 'success' ) {
            $this->log("❌ Agreement approval failed or cancelled.");
            $order->update_status( 'cancelled', 'bKash agreement was not approved.' );
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        $settings  = $this->get_gateway_settings();
        $agreement = new Bkashify_Agreement( $settings );
        $response  = $agreement->execute_agreement( $payment_id );

        if ( isset( $response['agreementID'] ) ) {
            update_post_meta( $order_id, '_bkash_agreement_id', $response['agreementID'] );
            delete_post_meta( $order_id, '_bkash_temp_agreement_id' );
            $this->log("✅ Agreement executed. AgreementID: " . $response['agreementID']);

            // ✅ Redirect to WooCommerce order payment screen
            $redirect_url = add_query_arg([
                'order-pay' => $order_id,
                'key'       => $order->get_order_key()
            ], wc_get_checkout_url());

            if ( headers_sent() ) {
                echo "<script>window.location.href='" . esc_url( $redirect_url ) . "';</script>";
            } else {
                wp_redirect( $redirect_url );
            }
            exit;
        }

        $this->log("❌ Agreement execution failed: " . json_encode($response));
        wc_add_notice( __( 'Agreement execution failed.', 'bkashify' ), 'error' );
        wp_redirect( wc_get_checkout_url() );
        exit;
    }

    public function handle_callback() {
        $payment_id = sanitize_text_field( $_GET['paymentID'] );
        $status     = sanitize_text_field( $_GET['status'] );

        $order_id = $this->get_order_id_by_payment_id( $payment_id );
        $this->log("Payment callback: OrderID={$order_id}, PaymentID={$payment_id}, Status={$status}");

        if ( ! $order_id ) {
            wp_die( 'Order not found for this payment ID.', 'bKashify Error', [ 'response' => 404 ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( 'Invalid order.', 'bKashify Error', [ 'response' => 404 ] );
        }

        switch ( strtolower( $status ) ) {
            case 'success':
                $this->process_success( $order, $payment_id );
                break;
            case 'failure':
                $this->process_failure( $order, $payment_id );
                break;
            case 'cancel':
                $this->process_cancellation( $order, $payment_id );
                break;
            default:
                wp_die( 'Invalid payment status.', 'bKashify Error', [ 'response' => 400 ] );
        }

        // ✅ Redirect to WooCommerce thank you page
        wp_redirect( $order->get_checkout_order_received_url() );
        exit;
    }

    private function process_success( $order, $payment_id ) {
        $settings = $this->get_gateway_settings();
        $bkash_payment = new Bkashify_Payment( $settings );
        $response = $bkash_payment->execute_payment( $payment_id );

        if ( isset( $response['statusCode'] ) && $response['statusCode'] === '0000' ) {
            $trx_id = $response['trxID'];
            $order->payment_complete( $trx_id );
            $order->add_order_note( 'bKash payment successful. Transaction ID: ' . $trx_id );
            update_post_meta( $order->get_id(), '_bkash_payment_id', $payment_id );
            update_post_meta( $order->get_id(), '_bkash_transaction_id', $trx_id );
            $order->save();
            $this->log("✅ Payment executed. TrxID: {$trx_id}");
        } else {
            $this->log("❌ Payment execution failed: " . json_encode( $response ));
            wc_add_notice( __( 'Payment execution failed.', 'bkashify' ), 'error' );
        }
    }

    private function process_failure( $order, $payment_id ) {
        $order->update_status( 'failed', 'bKash payment failed. Payment ID: ' . $payment_id );
        $this->log("❌ Payment failed. Payment ID: {$payment_id}");
    }

    private function process_cancellation( $order, $payment_id ) {
        $order->update_status( 'cancelled', 'bKash payment cancelled. Payment ID: ' . $payment_id );
        $this->log("❌ Payment cancelled. Payment ID: {$payment_id}");
    }

    private function get_order_id_by_payment_id( $payment_id ) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_bkash_payment_id' AND meta_value = %s LIMIT 1",
                $payment_id
            )
        );
    }

    private function get_gateway_settings() {
        $gateway = new Bkashify_Gateway();
        return [
            'sandbox'    => $gateway->sandbox ? 'yes' : 'no',
            'app_key'    => $gateway->app_key,
            'app_secret' => $gateway->app_secret,
            'username'   => $gateway->username,
            'password'   => $gateway->password,
        ];
    }

    private function log( $message ) {
        if ( ! $this->logger ) {
            $this->logger = wc_get_logger();
        }
        $this->logger->log( 'info', $message, [ 'source' => 'bkashify-callback' ] );
    }
}
