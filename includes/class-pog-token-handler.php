<?php
/**
 * Token handler for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_Token_Handler
 *
 * Handles token operations including creation, verification, and redemption.
 */
class POG_Token_Handler {

    /**
     * The crypto service.
     *
     * @var POG_Crypto
     */
    private $crypto;

    /**
     * Constructor.
     *
     * @param POG_Crypto $crypto The crypto service.
     */
    public function __construct( $crypto ) {
        $this->crypto = $crypto;
    }

    /**
     * Create a new token with the given amount.
     *
     * @param int $amount The amount for the token.
     * @return string The generated token.
     */
    public function create_token( $amount ) {
        return $this->crypto->create_token( $amount );
    }

    /**
     * Create multiple tokens with the same amount.
     *
     * @param int $amount The amount for the tokens.
     * @param int $quantity The number of tokens to create.
     * @return array The generated tokens.
     */
    public function create_tokens_batch( $amount, $quantity ) {
        $tokens = array();
        
        for ( $i = 0; $i < $quantity; $i++ ) {
            $tokens[] = $this->create_token( $amount );
        }
        
        return $tokens;
    }

    /**
     * Verify a token.
     *
     * @param string $token The token to verify.
     * @param bool   $check_redemption Whether to check if the token has been redeemed.
     * @return array|false Token data if valid, false otherwise.
     */
    public function verify_token( $token, $check_redemption = true ) {
        // Verify the token cryptographically.
        $token_data = $this->crypto->verify_token( $token );
        
        if ( ! $token_data ) {
            return false;
        }
        
        // Check if the token has already been redeemed.
        if ( $check_redemption && $this->is_token_redeemed( $token ) ) {
            $token_data['valid'] = false;
            $token_data['redeemed'] = true;
            return $token_data;
        }
        
        return $token_data;
    }

    /**
     * Redeem a token.
     *
     * @param string $token The token to redeem.
     * @param int    $order_id Optional order ID to associate with the redemption.
     * @param int    $user_id Optional user ID to associate with the redemption.
     * @return array|false Token data if redeemed successfully, false otherwise.
     */
    public function redeem_token( $token, $order_id = null, $user_id = null ) {
        // Verify the token.
        $token_data = $this->verify_token( $token );
        
        if ( ! $token_data || ! $token_data['valid'] || isset( $token_data['redeemed'] ) ) {
            return false;
        }
        
        // Record the redemption.
        $redeemed = $this->record_redemption( $token, $token_data['amount'], $order_id, $user_id );
        
        if ( ! $redeemed ) {
            return false;
        }
        
        $token_data['redeemed'] = true;
        $token_data['redeemed_at'] = current_time( 'mysql' );
        $token_data['order_id'] = $order_id;
        
        return $token_data;
    }

    /**
     * Check if a token has been redeemed.
     *
     * @param string $token The token to check.
     * @return bool True if the token has been redeemed, false otherwise.
     */
    public function is_token_redeemed( $token ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pog_redemptions';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE token = %s", $token ) );
        
        return ! empty( $result );
    }

    /**
     * Record a token redemption.
     *
     * @param string $token The token that was redeemed.
     * @param int    $amount The amount of the token.
     * @param int    $order_id Optional order ID to associate with the redemption.
     * @param int    $user_id Optional user ID to associate with the redemption.
     * @return bool True if the redemption was recorded, false otherwise.
     */
    private function record_redemption( $token, $amount, $order_id = null, $user_id = null ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pog_redemptions';
        
        $data = array(
            'token'       => $token,
            'amount'      => $amount,
            'redeemed_at' => current_time( 'mysql' ),
            'order_id'    => $order_id,
            'user_id'     => $user_id,
        );
        
        $result = $wpdb->insert( $table_name, $data );
        
        return false !== $result;
    }

    /**
     * Get the redemption data for a token.
     *
     * @param string $token The token to get redemption data for.
     * @return array|false The redemption data if found, false otherwise.
     */
    public function get_redemption_data( $token ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pog_redemptions';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE token = %s", $token ), ARRAY_A );
        
        return $result;
    }

    /**
     * Generate a change token for the remaining amount.
     *
     * @param int $amount The amount for the change token.
     * @return string The generated change token.
     */
    public function generate_change_token( $amount ) {
        return $this->create_token( $amount );
    }

    /**
     * Export tokens as CSV.
     *
     * @param array $tokens The tokens to export.
     * @return string The CSV content.
     */
    public function export_tokens_csv( $tokens ) {
        $csv = "Token,Amount\n";
        
        foreach ( $tokens as $token_data ) {
            $csv .= $token_data['token'] . ',' . $token_data['amount'] . "\n";
        }
        
        return $csv;
    }

    /**
     * Get the current exchange rate from satoshis to store currency.
     *
     * @return float The exchange rate.
     */
    public function get_satoshi_exchange_rate() {
        // Get the settings.
        $settings = get_option( 'pog_settings', array() );
        $exchange_rate = isset( $settings['satoshi_exchange_rate'] ) ? (float) $settings['satoshi_exchange_rate'] : 0;
        
        // If the exchange rate is not set or is zero, try to get it from an API.
        if ( $exchange_rate <= 0 ) {
            $exchange_rate = $this->fetch_satoshi_exchange_rate();
            
            // Update the settings with the new exchange rate.
            $settings['satoshi_exchange_rate'] = $exchange_rate;
            $settings['satoshi_exchange_rate_updated'] = time();
            update_option( 'pog_settings', $settings );
        }
        
        return $exchange_rate;
    }

    /**
     * Fetch the current exchange rate from satoshis to store currency from an API.
     *
     * @return float The exchange rate.
     */
    private function fetch_satoshi_exchange_rate() {
        // Get the store currency.
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        
        // Make a request to the CoinGecko API.
        $response = wp_remote_get( 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=' . strtolower( $currency ) );
        
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            // Return a default value if the API request fails.
            return 0.0001; // 10000 satoshis per USD as a fallback.
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! isset( $data['bitcoin'][ strtolower( $currency ) ] ) ) {
            // Return a default value if the API response is not as expected.
            return 0.0001;
        }
        
        // Calculate the exchange rate (how many store currency units per satoshi).
        $btc_price = $data['bitcoin'][ strtolower( $currency ) ];
        $satoshi_price = $btc_price / 100000000; // 1 BTC = 100,000,000 satoshis.
        
        return $satoshi_price;
    }

    /**
     * Convert satoshis to store currency.
     *
     * @param int $satoshis The amount in satoshis.
     * @return float The amount in store currency.
     */
    public function convert_satoshis_to_currency( $satoshis ) {
        $exchange_rate = $this->get_satoshi_exchange_rate();
        return $satoshis * $exchange_rate;
    }

    /**
     * Convert store currency to satoshis.
     *
     * @param float $amount The amount in store currency.
     * @return int The amount in satoshis.
     */
    public function convert_currency_to_satoshis( $amount ) {
        $exchange_rate = $this->get_satoshi_exchange_rate();
        
        if ( $exchange_rate <= 0 ) {
            return 0;
        }
        
        return (int) ( $amount / $exchange_rate );
    }

    /**
     * Get the operational mode.
     *
     * @return string The operational mode.
     */
    public function get_operational_mode() {
        $settings = get_option( 'pog_settings', array() );
        return isset( $settings['operational_mode'] ) ? $settings['operational_mode'] : 'store_currency';
    }
}