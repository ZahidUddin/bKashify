<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Bkashify_Token {

    const TRANSIENT_KEY = 'bkashify_token_data';

    private $settings;
    private $sandbox_url;
    private $live_url;
    private $refresh_sandbox_url;
    private $refresh_live_url;
    private $logger;

    public function __construct( $settings ) {
        $this->settings = $settings;
        $this->sandbox_url         = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant';
        $this->live_url            = 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant';
        $this->refresh_sandbox_url = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/refresh';
        $this->refresh_live_url    = 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/token/refresh';
        $this->logger              = wc_get_logger();
    }

    public function get_token() {
        $stored = get_transient( self::TRANSIENT_KEY );

        if ( $stored && isset( $stored['token'], $stored['expires_at'] ) ) {
            if ( time() < $stored['expires_at'] ) {
                $this->log( 'Using cached token, valid until: ' . date( 'Y-m-d H:i:s', $stored['expires_at'] ) );
                return $stored['token'];
            }
            $this->log( 'Cached token expired at: ' . date( 'Y-m-d H:i:s', $stored['expires_at'] ) );
        }

        $token = $this->request_new_token();

        if ( $token ) {
            $this->log( 'Token successfully fetched.' );
            return $token;
        }

        $this->log( 'Token fetch failed. No token returned.' );
        return false;
    }

    public function refresh_token() {
        $stored = get_transient( self::TRANSIENT_KEY );

        if ( ! $stored || ! isset( $stored['refresh_token'] ) ) {
            $this->log( 'Refresh token missing, falling back to new token request.' );
            return $this->request_new_token();
        }

        $url = $this->settings['sandbox'] === 'yes' ? $this->refresh_sandbox_url : $this->refresh_live_url;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'username'     => $this->settings['username'],
            'password'     => $this->settings['password'],
        ];

        $body = json_encode([
            'app_key'       => $this->settings['app_key'],
            'app_secret'    => $this->settings['app_secret'],
            'refresh_token' => $stored['refresh_token'],
        ]);

        $this->log( 'Refresh Token DEBUG (credentials masked).' );

        $response = wp_remote_post( $url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 20,
        ]);

        return $this->handle_token_response( $response, 'Refresh' );
    }

    private function request_new_token() {
        $url = $this->settings['sandbox'] === 'yes' ? $this->sandbox_url : $this->live_url;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'username'     => $this->settings['username'],
            'password'     => $this->settings['password'],
        ];

        $body = json_encode([
            'app_key'    => $this->settings['app_key'],
            'app_secret' => $this->settings['app_secret'],
        ]);

        $this->log( 'Grant Token DEBUG (credentials masked).' );

        $response = wp_remote_post( $url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 20,
        ]);

        return $this->handle_token_response( $response, 'Grant' );
    }

    private function handle_token_response( $response, $type ) {
        if ( is_wp_error( $response ) ) {
            $this->log( "$type Token Error: " . $response->get_error_message() );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $data['id_token'], $data['refresh_token'], $data['expires_in'] ) ) {
            $this->log( "$type Token Failure: " . json_encode( $data ) );
            return false;
        }

        $token_data = [
            'token'         => $data['id_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at'    => time() + intval( $data['expires_in'] ) - 60,
        ];

        set_transient( self::TRANSIENT_KEY, $token_data, $data['expires_in'] - 60 );

        return $token_data['token'];
    }

    private function log( $message ) {
        $this->logger->log( 'info', $message, [ 'source' => 'bkashify-token' ] );
    }
}
