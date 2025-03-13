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
     * @return array|\WP_Error Token data if valid, WP_Error on failure.
     */
    public function verify_token( $token, $check_redemption = true ) {
        // Basic validation
        if (empty($token) || !is_string($token)) {
            return new \WP_Error(
                'invalid_token',
                __('Invalid token format.', 'proof-of-gift')
            );
        }
        
        // Verify the token cryptographically.
        $token_data = $this->crypto->verify_token( $token );
        
        if ( ! $token_data ) {
            return new \WP_Error(
                'invalid_signature',
                __('Token signature verification failed.', 'proof-of-gift')
            );
        }
        
        // Check if the token has already been redeemed.
        if ( $check_redemption && $this->is_token_redeemed( $token ) ) {
            $token_data['valid'] = false;
            $token_data['redeemed'] = true;
            $token_data['error'] = 'already_redeemed';
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
     * @return array|\WP_Error Token data if redeemed successfully, WP_Error on failure.
     */
    public function redeem_token( $token, $order_id = null, $user_id = null ) {
        // Verify the token.
        $token_data = $this->verify_token( $token );
        
        // Check if verification returned an error
        if ( is_wp_error( $token_data ) ) {
            return $token_data;
        }
        
        // Check if token is valid and not already redeemed
        if ( ! $token_data['valid'] || isset( $token_data['redeemed'] ) ) {
            if ( isset( $token_data['redeemed'] ) ) {
                return new \WP_Error(
                    'already_redeemed',
                    __( 'This token has already been redeemed.', 'proof-of-gift' )
                );
            } else {
                return new \WP_Error(
                    'invalid_token',
                    __( 'Invalid token.', 'proof-of-gift' )
                );
            }
        }
        
        // Record the redemption.
        $redeemed = $this->record_redemption( $token, $token_data['amount'], $order_id, $user_id );
        
        if ( ! $redeemed ) {
            return new \WP_Error(
                'redemption_failed',
                __( 'Failed to record token redemption.', 'proof-of-gift' )
            );
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
     * Record a token redemption with proper transaction handling.
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
        
        // Start a transaction to ensure data consistency
        $wpdb->query('START TRANSACTION');
        
        try {
            // Double-check that the token hasn't been redeemed (prevents race conditions)
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM $table_name WHERE token = %s", $token)
            );
            
            if (!empty($existing)) {
                // Token was already redeemed, roll back and return false
                $wpdb->query('ROLLBACK');
                return false;
            }
            
            // Record the redemption
            $data = array(
                'token'       => $token,
                'amount'      => $amount,
                'redeemed_at' => current_time( 'mysql' ),
                'order_id'    => $order_id,
                'user_id'     => $user_id,
            );
            
            $result = $wpdb->insert( $table_name, $data );
            
            if (false === $result) {
                // Insert failed, roll back and return false
                $wpdb->query('ROLLBACK');
                return false;
            }
            
            // Commit the transaction if all operations succeeded
            $wpdb->query('COMMIT');
            return true;
            
        } catch (\Exception $e) {
            // Handle any exceptions by rolling back the transaction
            $wpdb->query('ROLLBACK');
            error_log('Proof Of Gift: Error recording token redemption: ' . $e->getMessage());
            return false;
        }
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
        $csv = "Token,Amount,Customer URL\n";
        
        foreach ( $tokens as $token_data ) {
            $application_url = home_url( '?pog_token=' . urlencode($token_data['token']) . '&pog_apply=1' );
            
            $csv .= $token_data['token'] . ',' . 
                    $token_data['amount'] . ',' . 
                    $application_url . "\n";
        }
        
        return $csv;
    }

    /**
     * Get the current exchange rate from satoshis to store currency.
     *
     * @param bool $force_refresh Whether to force a refresh of the exchange rate.
     * @return float|WP_Error The exchange rate or WP_Error if unavailable.
     */
    public function get_satoshi_exchange_rate( $force_refresh = false ) {
        // Try to get rate from cache first
        $cache_key = 'pog_satoshi_exchange_rate';
        $cached_rate = get_transient($cache_key);
        
        // Get the settings (used if cache is empty or forced refresh)
        $settings = get_option( 'pog_settings', array() );
        $stored_rate = isset( $settings['satoshi_exchange_rate'] ) ? (float) $settings['satoshi_exchange_rate'] : 0;
        $last_update = isset( $settings['satoshi_exchange_rate_updated'] ) ? (int) $settings['satoshi_exchange_rate_updated'] : 0;
        
        // Maximum age for stored rate (24 hours)
        $max_rate_age = 24 * HOUR_IN_SECONDS;
        
        // Check if we need to refresh due to age
        $refresh_due_to_age = ($last_update > 0 && (time() - $last_update > $max_rate_age));
        
        // Use cached rate if available and not forcing refresh and not too old
        if (!$force_refresh && !$refresh_due_to_age && $cached_rate !== false) {
            return (float)$cached_rate;
        }
        
        // If we have a stored rate less than 3 days old, use it while we try to get a fresh one
        $have_valid_stored_rate = ($stored_rate > 0 && $last_update > 0 && (time() - $last_update < 3 * DAY_IN_SECONDS));
        
        // Use a more robust locking mechanism with a unique process identifier
        $lock_key = 'pog_exchange_rate_lock';
        $process_id = uniqid('pog_', true);
        $current_lock = get_transient($lock_key);
        
        // If another process is already fetching the rate, use stored rate or return error
        if ($current_lock !== false) {
            if ($have_valid_stored_rate) {
                return $stored_rate;
            } else {
                return new \WP_Error('rate_unavailable', 
                    __('Exchange rate temporarily unavailable. Please try again in a few minutes.', 'proof-of-gift'));
            }
        }
        
        // Attempt to acquire the lock with our process ID
        // The 15 second timeout ensures the lock will eventually expire even if the process crashes
        $lock_acquired = set_transient($lock_key, $process_id, 15);
        
        // Double-check that we actually got the lock (prevents race conditions)
        if (!$lock_acquired || get_transient($lock_key) !== $process_id) {
            // Another process beat us to it
            if ($have_valid_stored_rate) {
                return $stored_rate;
            } else {
                return new \WP_Error('rate_unavailable', 
                    __('Exchange rate temporarily unavailable. Please try again in a few minutes.', 'proof-of-gift'));
            }
        }
        
        try {
            // Fetch fresh rate
            $exchange_rate = $this->fetch_satoshi_exchange_rate();
            
            // Release the lock only if it's still ours
            if (get_transient($lock_key) === $process_id) {
                delete_transient($lock_key);
            }
            
            // If we got a valid rate
            if ($exchange_rate !== null) {
                // Update the cache (1 hour)
                set_transient($cache_key, $exchange_rate, HOUR_IN_SECONDS);
                
                // Update the settings with the new exchange rate
                $settings['satoshi_exchange_rate'] = $exchange_rate;
                $settings['satoshi_exchange_rate_updated'] = time();
                update_option('pog_settings', $settings);
                
                return $exchange_rate;
            } else {
                // We couldn't get a fresh rate
                // Check if we're in a recent 403 error state with BTCPay
                $settings = get_option('pog_settings', array());
                $store_id = isset($settings['btcpay_store_id']) ? $settings['btcpay_store_id'] : '';
                $btcpay_403_key = 'pog_btcpay_' . $store_id . '_403_error';
                $btcpay_403_mode = get_transient($btcpay_403_key) !== false;
                
                if ($have_valid_stored_rate) {
                    // Use the stored rate if it's not too old, but don't log during high-frequency usage
                    // Only log this once per minute maximum
                    $log_key = 'pog_rate_fallback_logged';
                    if (!get_transient($log_key) && !$btcpay_403_mode) {
                        error_log('Proof Of Gift: Using stored exchange rate from ' . 
                            human_time_diff($last_update, time()) . ' ago');
                        // Prevent excessive logging
                        set_transient($log_key, true, MINUTE_IN_SECONDS);
                    }
                    return $stored_rate;
                } else {
                    // No valid rate available - only log this once per 5 minutes
                    $error_log_key = 'pog_rate_error_logged';
                    if (!get_transient($error_log_key) && !$btcpay_403_mode) {
                        error_log('Proof Of Gift: Exchange rate unavailable and no recent stored rate');
                        set_transient($error_log_key, true, 5 * MINUTE_IN_SECONDS);
                    }
                    return new \WP_Error('rate_unavailable', 
                        __('Exchange rate unavailable. Please check your internet connection and try again later.', 'proof-of-gift'));
                }
            }
        } catch (\Exception $e) {
            // If fetching fails, release lock if it's still ours
            if (get_transient($lock_key) === $process_id) {
                delete_transient($lock_key);
            }
            
            // Only log exceptions once per 15 minutes
            $exception_log_key = 'pog_rate_exception_logged';
            if (!get_transient($exception_log_key)) {
                error_log('Proof Of Gift: Exception in exchange rate fetch: ' . $e->getMessage());
                set_transient($exception_log_key, true, 15 * MINUTE_IN_SECONDS);
            }
            
            // Use stored rate or return error
            if ($have_valid_stored_rate) {
                return $stored_rate;
            } else {
                return new \WP_Error('rate_unavailable', 
                    __('Exchange rate service error. Please try again later.', 'proof-of-gift'));
            }
        }
    }

    /**
     * Fetch the current exchange rate from satoshis to store currency from preferred sources.
     *
     * Priority:
     * 1. BTCPay Server (if available and configured)
     * 2. CoinGecko API
     *
     * @return float|null The exchange rate or null if unavailable.
     */
    private function fetch_satoshi_exchange_rate() {
        // Get the store currency.
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        
        // Try to get rate from BTCPay Server first (if configured)
        $btcpay_rate = $this->get_btcpay_exchange_rate();
        if ($btcpay_rate) {
            // Only log successful rate fetch, not every attempt
            error_log('Proof Of Gift: Successfully retrieved exchange rate from BTCPay Server');
            return $btcpay_rate;
        }
        
        // Check if we've recently hit CoinGecko API rate limits
        $rate_limit_key = 'pog_coingecko_rate_limited';
        if (get_transient($rate_limit_key)) {
            // If rate limited, don't attempt another call
            return null;
        }
        
        // Make a request to the CoinGecko API with proper rate limiting
        $response = wp_remote_get( 
            'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=' . strtolower( $currency ),
            array(
                'timeout' => 15, // Longer timeout for more reliability
                'headers' => array(
                    'Accept' => 'application/json',
                    'User-Agent' => 'WordPress/ProofOfGift-Plugin'
                )
            )
        );
        
        // Handle API errors
        if (is_wp_error($response)) {
            error_log('Proof Of Gift: CoinGecko API request failed: ' . $response->get_error_message());
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        // Handle rate limiting specially
        if ($response_code === 429) {
            // Set a long rate limit cooldown (1 hour)
            set_transient($rate_limit_key, true, HOUR_IN_SECONDS);
            error_log('Proof Of Gift: CoinGecko API rate limited (429). Rate requests paused for 1 hour.');
            return null;
        }
        
        // Handle other HTTP errors
        if ($response_code !== 200) {
            error_log('Proof Of Gift: CoinGecko API request failed with HTTP code: ' . $response_code);
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Validate the response data
        if (!isset($data['bitcoin'][strtolower($currency)])) {
            error_log('Proof Of Gift: CoinGecko API response did not contain expected data');
            return null;
        }
        
        // Calculate the exchange rate (how many store currency units per satoshi)
        $btc_price = $data['bitcoin'][strtolower($currency)];
        $satoshi_price = $btc_price / 100000000; // 1 BTC = 100,000,000 satoshis
        
        // Only log a successful rate fetch, not every check
        error_log('Proof Of Gift: Successfully retrieved exchange rate from CoinGecko API');
        
        return $satoshi_price;
    }
    
    /**
     * Get exchange rate from BTCPay Server if available
     *
     * @return float|null Exchange rate or null if not available
     */
    private function get_btcpay_exchange_rate() {
        // Check if BTCPay Server integration is available and we're in the right mode
        if (!class_exists('\\ProofOfGift\\POG_BTCPay_Integration') || 
            !POG_Utils::is_btcpay_active()) {
            return null;
        }
        
        // Check if we're in a mode that would use BTCPay Server
        $mode = $this->get_operational_mode();
        if ($mode !== 'direct_satoshi' && $mode !== 'satoshi_conversion') {
            return null;
        }
        
        // Get the settings
        $settings = get_option( 'pog_settings', array() );
        $api_url = isset( $settings['btcpay_server_url'] ) ? trailingslashit( $settings['btcpay_server_url'] ) : '';
        $api_key = isset( $settings['btcpay_api_key'] ) ? $settings['btcpay_api_key'] : '';
        $store_id = isset( $settings['btcpay_store_id'] ) ? $settings['btcpay_store_id'] : '';
        
        // If settings are not properly configured, return null
        if (empty($api_url) || empty($api_key) || empty($store_id)) {
            return null;
        }
        
        // Use BTCPay Client instead of direct integration to avoid circular dependency
        $btcpay_client = new POG_BTCPay_Client($api_url, $api_key, $store_id);
        
        // Get store currency
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
        
        // Get exchange rate from BTCPay Server
        return $btcpay_client->get_satoshi_rate($currency);
    }

    /**
     * Convert satoshis to store currency.
     *
     * @param int  $satoshis     The amount in satoshis.
     * @param bool $force_refresh Whether to force a refresh of the exchange rate.
     * @return float|WP_Error The amount in store currency or WP_Error if rate unavailable.
     */
    public function convert_satoshis_to_currency( $satoshis, $force_refresh = false ) {
        $exchange_rate = $this->get_satoshi_exchange_rate( $force_refresh );
        
        // Check if we got a WP_Error
        if ( is_wp_error( $exchange_rate ) ) {
            return $exchange_rate; // Return the error
        }
        
        return $satoshis * $exchange_rate;
    }

    /**
     * Convert store currency to satoshis.
     *
     * @param float $amount        The amount in store currency.
     * @param bool  $force_refresh Whether to force a refresh of the exchange rate.
     * @return int|WP_Error The amount in satoshis or WP_Error if rate unavailable.
     */
    public function convert_currency_to_satoshis( $amount, $force_refresh = false ) {
        // Validate amount
        if (!is_numeric($amount)) {
            return new \WP_Error(
                'invalid_amount',
                __( 'Amount must be a valid number.', 'proof-of-gift' )
            );
        }
        
        $amount = floatval($amount);
        if ($amount <= 0) {
            return new \WP_Error(
                'invalid_amount',
                __( 'Amount must be greater than zero.', 'proof-of-gift' )
            );
        }
        
        $exchange_rate = $this->get_satoshi_exchange_rate( $force_refresh );
        
        // Check if we got a WP_Error
        if ( is_wp_error( $exchange_rate ) ) {
            return $exchange_rate; // Return the error
        }
        
        if ( $exchange_rate <= 0 ) {
            return new \WP_Error(
                'invalid_rate',
                __( 'Invalid exchange rate. Please try again later.', 'proof-of-gift' )
            );
        }
        
        // Calculate satoshi amount
        $satoshis = $amount / $exchange_rate;
        
        // Check for extremely small amounts that would round to zero
        if ($satoshis < 1) {
            return new \WP_Error(
                'amount_too_small',
                __( 'Amount is too small to convert to satoshis. Minimum amount required.', 'proof-of-gift' )
            );
        }
        
        return (int) $satoshis;
    }

    /**
     * Get the operational mode.
     *
     * @return string The operational mode.
     */
    public function get_operational_mode() {
        $settings = get_option( 'pog_settings', array() );
        $mode = isset( $settings['operational_mode'] ) ? $settings['operational_mode'] : 'store_currency';
        
        // Direct Satoshi Mode is temporarily disabled 
        // If it's currently set, default to satoshi_conversion mode
        if ($mode === 'direct_satoshi') {
            error_log('Proof Of Gift: Direct Satoshi Mode is temporarily disabled. Falling back to Satoshi Conversion Mode.');
            $mode = 'satoshi_conversion';
        }
        
        return $mode;
    }
}