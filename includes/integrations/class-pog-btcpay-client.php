<?php
/**
 * BTCPay Server API client for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_BTCPay_Client
 *
 * Handles API communication with BTCPay Server.
 */
class POG_BTCPay_Client {
    /**
     * BTCPay Server API URL.
     *
     * @var string
     */
    private $api_url;

    /**
     * BTCPay Server API key.
     *
     * @var string
     */
    private $api_key;

    /**
     * BTCPay Server store ID.
     *
     * @var string
     */
    private $store_id;

    /**
     * BTCPay Server version (v2 or not).
     *
     * @var bool|null
     */
    private $is_v2 = null;

    /**
     * Cache expiration time in seconds.
     *
     * @var int
     */
    private $cache_expiration = 86400; // 24 hours

    /**
     * Maximum retry attempts for failed requests.
     *
     * @var int
     */
    private $max_retries = 3;

    /**
     * Constructor.
     *
     * @param string $api_url  The BTCPay Server API URL.
     * @param string $api_key  The BTCPay Server API key.
     * @param string $store_id The BTCPay Server store ID.
     */
    public function __construct( $api_url, $api_key, $store_id ) {
        // Ensure the API URL is properly formatted and has a trailing slash
        if (!empty($api_url)) {
            // Add schema if missing
            if (strpos($api_url, 'http') !== 0) {
                $api_url = 'https://' . $api_url;
            }
            $this->api_url = trailingslashit($api_url);
        } else {
            $this->api_url = '';
        }
        
        $this->api_key = $api_key;
        $this->store_id = $store_id;
        
        error_log('Proof Of Gift: BTCPay Client initialized with URL: ' . $this->api_url);
    }

    /**
     * Get the BTCPay Server version.
     *
     * @return bool|null True for v2+, false for v1, null if couldn't detect.
     */
    public function detect_btcpay_version() {
        // Check if we already detected the version
        if ( $this->is_v2 !== null ) {
            return $this->is_v2;
        }

        // Check cached version first
        $cached_version = get_transient( 'pog_btcpay_version' );
        if ( $cached_version !== false ) {
            $this->is_v2 = $cached_version === 'v2';
            return $this->is_v2;
        }

        // Try server info endpoint
        $endpoint = 'api/v1/server/info';
        $response = $this->make_request( 'GET', $endpoint );

        if ( ! empty( $response ) && isset( $response['version'] ) ) {
            // Check if the version starts with "2."
            $this->is_v2 = version_compare( $response['version'], '2.0.0', '>=' );
            
            // Cache the result
            set_transient( 'pog_btcpay_version', $this->is_v2 ? 'v2' : 'v1', $this->cache_expiration );
            
            error_log( 'Proof Of Gift: Successfully detected BTCPay Server version: ' . ( $this->is_v2 ? 'v2+' : 'v1' ) );
            return $this->is_v2;
        }

        // Fallback detection method for v1 servers
        $test_endpoint = 'api/v1/stores/' . $this->store_id . '/rates';
        $test_response = $this->make_request( 'GET', $test_endpoint );

        if ( ! empty( $test_response ) ) {
            // V2 returns an array directly, v1 might have a different structure
            $this->is_v2 = is_array( $test_response ) && ! isset( $test_response['data'] );
            
            // Cache the result
            set_transient( 'pog_btcpay_version', $this->is_v2 ? 'v2' : 'v1', $this->cache_expiration );
            
            error_log( 'Proof Of Gift: Detected BTCPay Server version using fallback method: ' . ( $this->is_v2 ? 'v2+' : 'v1' ) );
            return $this->is_v2;
        }

        // If all else fails, try one more method - check if api/rates endpoint works (v1)
        $alt_endpoint = 'api/rates/BTC/USD';
        $alt_response = $this->make_request( 'GET', $alt_endpoint, null, null, false );
        
        if ( ! empty( $alt_response ) ) {
            $this->is_v2 = false; // If this works, it's v1
            set_transient( 'pog_btcpay_version', 'v1', $this->cache_expiration );
            error_log( 'Proof Of Gift: Detected BTCPay Server v1 using api/rates endpoint' );
            return false;
        }

        error_log( 'Proof Of Gift: Could not detect BTCPay Server version' );
        return null;
    }

    /**
     * Get exchange rates from BTCPay Server.
     *
     * @param string $base_currency The base currency code (default: USD).
     * @return array|null The exchange rates or null on failure.
     */
    public function get_exchange_rates( $base_currency = 'USD' ) {
        // Check for cached rate to avoid excessive API calls
        $transient_key = 'pog_btcpay_rates_' . strtolower( $base_currency );
        $cached_rates = get_transient( $transient_key );
        if ( $cached_rates !== false ) {
            return $cached_rates;
        }

        $is_v2 = $this->detect_btcpay_version();

        if ( $is_v2 ) {
            // BTCPay 2.0+ endpoint
            $endpoint = 'api/v1/stores/' . $this->store_id . '/rates';
            $params = array( 'currencyPair' => 'BTC_' . $base_currency );
            $response = $this->make_request( 'GET', $endpoint, null, $params );

            if ( ! empty( $response ) ) {
                foreach ( $response as $rate ) {
                    if ( isset( $rate['currencyPair'] ) && $rate['currencyPair'] === 'BTC_' . $base_currency ) {
                        $rates = array(
                            'BTC' => array(
                                $base_currency => floatval( $rate['rate'] ),
                            ),
                        );
                        
                        // Cache the rates for 15 minutes
                        set_transient( $transient_key, $rates, 15 * MINUTE_IN_SECONDS );
                        return $rates;
                    }
                }
            }
        } else {
            // BTCPay 1.x endpoint - first try with storeId
            $endpoint = 'api/rates';
            $params = array( 'storeId' => $this->store_id, 'cryptoCode' => 'BTC' );
            $response = $this->make_request( 'GET', $endpoint, null, $params );

            if ( ! empty( $response ) && isset( $response['data'] ) ) {
                foreach ( $response['data'] as $rate ) {
                    if ( $rate['code'] === $base_currency ) {
                        $rates = array(
                            'BTC' => array(
                                $base_currency => floatval( $rate['rate'] ),
                            ),
                        );
                        
                        // Cache the rates for 15 minutes
                        set_transient( $transient_key, $rates, 15 * MINUTE_IN_SECONDS );
                        return $rates;
                    }
                }
            }

            // Fallback to direct rate endpoint for v1
            $alt_endpoint = 'api/rates/BTC/' . $base_currency;
            $alt_response = $this->make_request( 'GET', $alt_endpoint, null, null, false );
            
            if ( ! empty( $alt_response ) && isset( $alt_response['rate'] ) ) {
                $rates = array(
                    'BTC' => array(
                        $base_currency => floatval( $alt_response['rate'] ),
                    ),
                );
                
                // Cache the rates for 15 minutes
                set_transient( $transient_key, $rates, 15 * MINUTE_IN_SECONDS );
                return $rates;
            }
        }

        return null;
    }

    /**
     * Get the satoshi exchange rate.
     *
     * @param string $currency The currency code.
     * @return float|null The exchange rate per satoshi or null on failure.
     */
    public function get_satoshi_rate( $currency = 'USD' ) {
        $rates = $this->get_exchange_rates( $currency );
        
        if ( ! empty( $rates ) && isset( $rates['BTC'][ $currency ] ) ) {
            // Convert BTC rate to satoshi rate (1 BTC = 100,000,000 satoshis)
            $btc_rate = $rates['BTC'][ $currency ];
            $satoshi_rate = $btc_rate / 100000000;
            
            error_log( 'Proof Of Gift: BTCPay Exchange rate: 1 satoshi = ' . $satoshi_rate . ' ' . $currency );
            return $satoshi_rate;
        }
        
        return null;
    }

    /**
     * Send a request to the BTCPay Server API.
     *
     * @param string  $method     The HTTP method (GET, POST, PUT, DELETE).
     * @param string  $endpoint   The API endpoint.
     * @param array   $body       The request body (optional).
     * @param array   $params     The query parameters (optional).
     * @param boolean $auth       Whether to include authentication (optional).
     * @param int     $retry      The current retry attempt (optional).
     * @return array|null The response data or null on failure.
     */
    private function make_request( $method, $endpoint, $body = null, $params = null, $auth = true, $retry = 0 ) {
        // Build full URL
        $url = $this->api_url . $endpoint;
        
        // Add query parameters if provided
        if ( $params ) {
            $url = add_query_arg( $params, $url );
        }
        
        // Prepare request arguments
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'timeout' => 30,
            'sslverify' => apply_filters('pog_btcpay_sslverify', true),
        );
        
        // Add authentication header if required
        if ( $auth && ! empty( $this->api_key ) ) {
            $args['headers']['Authorization'] = 'token ' . $this->api_key;
            error_log('Proof Of Gift: Adding Authorization header with API key to BTCPay request');
        } else {
            error_log('Proof Of Gift: No Authorization header added to BTCPay request. Auth: ' . ($auth ? 'true' : 'false') . ', API key empty: ' . (empty($this->api_key) ? 'true' : 'false'));
        }
        
        // Add request body if provided
        if ( $body ) {
            $args['body'] = wp_json_encode( $body );
        }
        
        error_log( 'Proof Of Gift: Making BTCPay Server API request to ' . $url );
        
        // Make the request
        $response = wp_remote_request( $url, $args );
        
        // Handle errors
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            error_log( 'Proof Of Gift: Error in BTCPay Server API request: ' . $error_message );
            
            // Retry on connection errors with exponential backoff
            if ( $retry < $this->max_retries ) {
                $delay = pow( 2, $retry ) * 1000000; // Microseconds: 2, 4, 8 seconds
                usleep( $delay );
                return $this->make_request( $method, $endpoint, $body, $params, $auth, $retry + 1 );
            }
            
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $decoded_body = ! empty( $response_body ) ? json_decode( $response_body, true ) : array();
        
        error_log( 'Proof Of Gift: BTCPay Server API response code: ' . $status_code );
        
        // Handle HTTP errors
        if ( $status_code >= 400 ) {
            $error_message = isset( $decoded_body['message'] ) ? $decoded_body['message'] : 'Unknown error';
            error_log( 'Proof Of Gift: BTCPay Server API error: ' . $error_message . ' (Status: ' . $status_code . ')' );
            
            // Retry on server errors (5xx) with exponential backoff
            if ( $status_code >= 500 && $retry < $this->max_retries ) {
                $delay = pow( 2, $retry ) * 1000000; // Microseconds: 2, 4, 8 seconds
                usleep( $delay );
                return $this->make_request( $method, $endpoint, $body, $params, $auth, $retry + 1 );
            }
            
            return null;
        }
        
        return $decoded_body;
    }

    /**
     * Test the connection to BTCPay Server.
     *
     * @return array The test results with success/failure status and message.
     */
    public function test_connection() {
        $result = array(
            'success' => false,
            'message' => '',
            'version' => 'Unknown',
        );
        
        // First ensure we have required credentials
        if ( empty( $this->api_url ) || empty( $this->api_key ) || empty( $this->store_id ) ) {
            $result['message'] = __( 'Missing configuration: Please provide API URL, API key, and Store ID.', 'proof-of-gift' );
            return $result;
        }
        
        // Clear any cached version info to ensure a fresh check
        delete_transient( 'pog_btcpay_version' );
        $this->is_v2 = null;
        
        // Test 1: Try to get server info to detect version
        $version = $this->detect_btcpay_version();
        
        // Is it a valid authentication error?
        if ( is_wp_error( $version ) && $version->get_error_code() === 'unauthorized' ) {
            $result['message'] = __( 'Authentication failed: Please check your API key.', 'proof-of-gift' );
            return $result;
        }
        
        // Test 2: Try to access the store
        $store_endpoint = 'api/v1/stores/' . $this->store_id;
        $store_response = $this->make_request( 'GET', $store_endpoint );
        
        if ( empty( $store_response ) ) {
            // Store access failed, try one more endpoint
            $rates_endpoint = 'api/v1/stores/' . $this->store_id . '/rates';
            $rates_response = $this->make_request( 'GET', $rates_endpoint );
            
            if ( empty( $rates_response ) ) {
                $result['message'] = __( 'Cannot access store: Please check your Store ID.', 'proof-of-gift' );
                return $result;
            }
        }
        
        // If we get here, we have a working connection
        $result['success'] = true;
        
        // Determine version if possible
        if ( $version === true ) {
            $result['version'] = 'BTCPay Server 2.0+';
        } elseif ( $version === false ) {
            $result['version'] = 'BTCPay Server 1.x';
        } else {
            $result['version'] = 'BTCPay Server (version unknown)';
        }
        
        $result['message'] = __( 'Connection successful!', 'proof-of-gift' ) . ' ' . $result['version'];
        
        // Check exchange rate as part of connection test
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        $rate = $this->get_satoshi_rate( $currency );
        
        if ( $rate !== null ) {
            $result['rate'] = $rate;
            $result['currency'] = $currency;
            $result['message'] .= sprintf( __( ' Current rate: 1 satoshi = %1$s %2$s', 'proof-of-gift' ), $rate, $currency );
        }
        
        return $result;
    }

    /**
     * Clear cached data.
     *
     * @return void
     */
    public function clear_cache() {
        delete_transient( 'pog_btcpay_version' );
        $this->is_v2 = null;
        
        // Also clear any cached rates
        $currencies = array( 'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CNY' );
        if ( function_exists( 'get_woocommerce_currency' ) ) {
            $currencies[] = get_woocommerce_currency();
        }
        
        foreach ( $currencies as $currency ) {
            delete_transient( 'pog_btcpay_rates_' . strtolower( $currency ) );
        }
    }
}