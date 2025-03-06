<?php
/**
 * BTCPay Server integration for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_BTCPay_Integration
 *
 * Handles BTCPay Server integration functionality.
 */
class POG_BTCPay_Integration {

    /**
     * The token handler.
     *
     * @var POG_Token_Handler
     */
    private $token_handler;

    /**
     * Constructor.
     *
     * @param POG_Token_Handler $token_handler The token handler.
     */
    public function __construct( $token_handler ) {
        $this->token_handler = $token_handler;
    }

    /**
     * Initialize the BTCPay Server integration.
     *
     * @return void
     */
    public function initialize() {
        // Only proceed if we're in Direct Satoshi mode.
        if ( 'direct_satoshi' !== $this->token_handler->get_operational_mode() ) {
            return;
        }

        // Add hooks to integrate with BTCPay Server.
        add_filter( 'btcpay_payment_amount', array( $this, 'modify_btcpay_payment_amount' ), 10, 2 );
        add_action( 'btcpay_invoice_created', array( $this, 'process_tokens_on_invoice_created' ), 10, 3 );
        add_action( 'btcpay_invoice_paid', array( $this, 'process_tokens_on_invoice_paid' ), 10, 2 );

        // Add admin notice if BTCPay Server plugin is not properly configured.
        add_action( 'admin_notices', array( $this, 'maybe_display_btcpay_notice' ) );
    }

    /**
     * Modify the payment amount sent to BTCPay Server.
     *
     * @param float    $amount The original payment amount.
     * @param \WC_Order $order The order object.
     * @return float The modified payment amount.
     */
    public function modify_btcpay_payment_amount( $amount, $order ) {
        // Get any tokens associated with this order.
        $tokens = WC()->session->get( 'pog_tokens', array() );
        
        if ( empty( $tokens ) ) {
            return $amount;
        }
        
        // Calculate the total token value.
        $total_token_value = 0;
        
        foreach ( $tokens as $token ) {
            // Verify the token.
            $verification = $this->token_handler->verify_token( $token );
            
            if ( ! $verification || ! $verification['valid'] || isset( $verification['redeemed'] ) ) {
                continue;
            }
            
            // Add the token value.
            $total_token_value += $verification['amount'];
            
            // Store the token with the order.
            update_post_meta( $order->get_id(), '_pog_token_' . substr( md5( $token ), 0, 10 ), $token );
        }
        
        // Adjust the payment amount.
        $new_amount = max( 0, $amount - $total_token_value );
        
        // Store the original and adjusted amounts.
        update_post_meta( $order->get_id(), '_pog_btcpay_original_amount', $amount );
        update_post_meta( $order->get_id(), '_pog_btcpay_adjusted_amount', $new_amount );
        update_post_meta( $order->get_id(), '_pog_btcpay_token_value', $total_token_value );
        
        return $new_amount;
    }

    /**
     * Process tokens when a BTCPay Server invoice is created.
     *
     * @param string    $invoice_id The invoice ID.
     * @param \WC_Order $order The order object.
     * @param array     $invoice_data The invoice data.
     * @return void
     */
    public function process_tokens_on_invoice_created( $invoice_id, $order, $invoice_data ) {
        // Get the token value.
        $token_value = get_post_meta( $order->get_id(), '_pog_btcpay_token_value', true );
        
        if ( empty( $token_value ) ) {
            return;
        }
        
        // Store the invoice ID with the order.
        update_post_meta( $order->get_id(), '_pog_btcpay_invoice_id', $invoice_id );
        
        // Add a note to the order.
        $order->add_order_note(
            sprintf(
                /* translators: %1$s: token value, %2$s: invoice ID */
                __( 'Applied %1$s satoshis in tokens to BTCPay Server invoice %2$s', 'proof-of-gift' ),
                $token_value,
                $invoice_id
            )
        );
        
        // Send information to BTCPay Server via API.
        $this->send_token_info_to_btcpay( $invoice_id, $token_value );
    }

    /**
     * Process tokens when a BTCPay Server invoice is paid.
     *
     * @param \WC_Order $order The order object.
     * @param array     $invoice_data The invoice data.
     * @return void
     */
    public function process_tokens_on_invoice_paid( $order, $invoice_data ) {
        // Get all tokens associated with this order.
        $order_id = $order->get_id();
        $tokens = array();
        
        // Get the token meta data.
        $meta_keys = $this->get_token_meta_keys( $order_id );
        
        foreach ( $meta_keys as $meta_key ) {
            $token = get_post_meta( $order_id, $meta_key, true );
            
            if ( ! empty( $token ) ) {
                $tokens[] = $token;
            }
        }
        
        if ( empty( $tokens ) ) {
            return;
        }
        
        // Get the actual payment amount from BTCPay Server invoice data.
        $actual_payment_amount = 0;
        if ( isset( $invoice_data['amount'] ) ) {
            $actual_payment_amount = intval( $invoice_data['amount'] );
        } elseif ( isset( $invoice_data['btcPaid'] ) ) {
            // Try alternative field name that might be used in BTCPay Server API.
            $actual_payment_amount = $this->convert_btc_to_satoshis( floatval( $invoice_data['btcPaid'] ) );
        }
        
        // Get the original order total and token value.
        $original_amount = intval( get_post_meta( $order_id, '_pog_btcpay_original_amount', true ) );
        $token_value = intval( get_post_meta( $order_id, '_pog_btcpay_token_value', true ) );
        $adjusted_amount = intval( get_post_meta( $order_id, '_pog_btcpay_adjusted_amount', true ) );
        
        // Calculate the token amount actually used (based on actual payment)
        $token_amount_used = 0;
        
        if ( $actual_payment_amount > 0 ) {
            // If we have actual payment data, calculate the used token amount precisely.
            $token_amount_used = $original_amount - $actual_payment_amount;
        } else {
            // Fallback: if we don't have actual payment amount data, use the adjusted amount.
            $token_amount_used = $original_amount - $adjusted_amount;
        }
        
        // Make sure we don't use more than the token value.
        $token_amount_used = min( $token_amount_used, $token_value );
        
        // Calculate change amount: total token value minus actually used amount.
        $change_amount = $token_value - $token_amount_used;
        
        // Add a note about the token usage.
        $order->add_order_note(
            sprintf(
                /* translators: %1$d: token value, %2$d: token amount used */
                __( 'Token value: %1$d satoshis. Token amount used: %2$d satoshis.', 'proof-of-gift' ),
                $token_value,
                $token_amount_used
            )
        );
        
        // Redeem all tokens.
        foreach ( $tokens as $token ) {
            $this->token_handler->redeem_token( $token, $order_id, $order->get_customer_id() );
        }
        
        // Add a note to the order about token redemption.
        $order->add_order_note(
            sprintf(
                /* translators: %d: number of tokens */
                _n(
                    'Redeemed %d token after successful BTCPay Server payment',
                    'Redeemed %d tokens after successful BTCPay Server payment',
                    count( $tokens ),
                    'proof-of-gift'
                ),
                count( $tokens )
            )
        );
        
        // Generate change token if needed.
        if ( $change_amount > 0 ) {
            // Generate a change token.
            $change_token = $this->token_handler->generate_change_token( $change_amount );
            
            // Store the change token with the order.
            update_post_meta( $order_id, '_pog_change_token', $change_token );
            update_post_meta( $order_id, '_pog_change_amount', $change_amount );
            
            // Add a note to the order.
            $order->add_order_note(
                sprintf(
                    /* translators: %1$s: change amount, %2$s: change token */
                    __( 'Change token generated: %1$s satoshis (%2$s)', 'proof-of-gift' ),
                    $change_amount,
                    $change_token
                )
            );
            
            // Include the change token in the order confirmation email.
            add_action( 'woocommerce_email_after_order_table', array( $this, 'add_change_token_to_email' ), 10, 3 );
        }
    }
    
    /**
     * Convert BTC to satoshis.
     *
     * @param float $btc The amount in BTC.
     * @return int The amount in satoshis.
     */
    private function convert_btc_to_satoshis( $btc ) {
        return intval( $btc * 100000000 );
    }

    /**
     * Get token meta keys for an order.
     *
     * @param int $order_id The order ID.
     * @return array The token meta keys.
     */
    private function get_token_meta_keys( $order_id ) {
        global $wpdb;
        
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_key FROM {$wpdb->postmeta}
                WHERE post_id = %d
                AND meta_key LIKE %s",
                $order_id,
                '_pog_token_%'
            )
        );
    }

    /**
     * Send token information to BTCPay Server.
     *
     * @param string $invoice_id The invoice ID.
     * @param int    $token_value The token value in satoshis.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private function send_token_info_to_btcpay( $invoice_id, $token_value ) {
        // Get the BTCPay Server settings.
        $settings = get_option( 'pog_settings', array() );
        $api_key = isset( $settings['btcpay_api_key'] ) ? $settings['btcpay_api_key'] : '';
        $store_id = isset( $settings['btcpay_store_id'] ) ? $settings['btcpay_store_id'] : '';
        $server_url = isset( $settings['btcpay_server_url'] ) ? $settings['btcpay_server_url'] : '';
        
        if ( empty( $api_key ) || empty( $store_id ) || empty( $server_url ) ) {
            return new \WP_Error( 'btcpay_config', __( 'BTCPay Server is not properly configured.', 'proof-of-gift' ) );
        }
        
        // Build the API endpoint URL.
        $endpoint = trailingslashit( $server_url ) . 'api/v1/stores/' . $store_id . '/invoices/' . $invoice_id;
        
        // Get the current public site URL for token verification.
        $verification_url = home_url( '/pog-verify/' );
        
        // Build the request body.
        $body = array(
            'metadata' => array(
                'pog_token_value' => $token_value,
                'pog_plugin_version' => POG_VERSION,
                'pog_token_verification_url' => $verification_url,
                'pog_token_type' => 'direct_satoshi',
                'pog_requires_change_calculation' => true
            ),
        );
        
        // Send the request.
        $response = wp_remote_post(
            $endpoint,
            array(
                'method'  => 'PUT',
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'token ' . $api_key,
                ),
                'body'    => wp_json_encode( $body ),
            )
        );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        
        if ( 200 !== $status_code ) {
            return new \WP_Error(
                'btcpay_api',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'BTCPay Server API error: %d', 'proof-of-gift' ),
                    $status_code
                )
            );
        }
        
        // Log the successful token integration
        error_log(
            sprintf(
                'Proof Of Gift: Successfully sent token information to BTCPay Server. Invoice: %s, Token Value: %d satoshis',
                $invoice_id,
                $token_value
            )
        );
        
        return true;
    }

    /**
     * Display an admin notice if BTCPay Server is not properly configured.
     *
     * @return void
     */
    public function maybe_display_btcpay_notice() {
        // Only display in admin area.
        if ( ! is_admin() ) {
            return;
        }
        
        // Only display if we're in Direct Satoshi mode.
        if ( 'direct_satoshi' !== $this->token_handler->get_operational_mode() ) {
            return;
        }
        
        // Check if BTCPay Server is properly configured.
        $settings = get_option( 'pog_settings', array() );
        $api_key = isset( $settings['btcpay_api_key'] ) ? $settings['btcpay_api_key'] : '';
        $store_id = isset( $settings['btcpay_store_id'] ) ? $settings['btcpay_store_id'] : '';
        $server_url = isset( $settings['btcpay_server_url'] ) ? $settings['btcpay_server_url'] : '';
        
        if ( empty( $api_key ) || empty( $store_id ) || empty( $server_url ) ) {
            // Display the notice.
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        /* translators: %1$s: plugin name, %2$s: settings link */
                        esc_html__( 'The %1$s plugin is configured in Direct Satoshi Mode, but BTCPay Server settings are not properly configured. Please configure your BTCPay Server settings in the %2$s.', 'proof-of-gift' ),
                        '<strong>Proof Of Gift</strong>',
                        '<a href="' . esc_url( admin_url( 'admin.php?page=proof-of-gift-settings' ) ) . '">' . esc_html__( 'plugin settings', 'proof-of-gift' ) . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Add change token to order confirmation email.
     *
     * @param \WC_Order $order The order object.
     * @param bool      $sent_to_admin Whether the email is sent to admin.
     * @param bool      $plain_text Whether the email is plain text.
     * @return void
     */
    public function add_change_token_to_email( $order, $sent_to_admin, $plain_text ) {
        if ( $sent_to_admin ) {
            return;
        }
        
        $change_token = get_post_meta( $order->get_id(), '_pog_change_token', true );
        $change_amount = get_post_meta( $order->get_id(), '_pog_change_amount', true );
        
        if ( ! $change_token || ! $change_amount ) {
            return;
        }
        
        // Output the change token.
        if ( $plain_text ) {
            echo "\n\n" . esc_html__( 'Change Token', 'proof-of-gift' ) . "\n";
            echo esc_html__( 'Amount:', 'proof-of-gift' ) . ' ' . esc_html( $change_amount ) . ' satoshis' . "\n";
            echo esc_html__( 'Token:', 'proof-of-gift' ) . ' ' . esc_html( $change_token ) . "\n\n";
        } else {
            ?>
            <h2><?php esc_html_e( 'Change Token', 'proof-of-gift' ); ?></h2>
            <p>
                <?php esc_html_e( 'Amount:', 'proof-of-gift' ); ?> <?php echo esc_html( $change_amount ); ?> satoshis<br>
                <?php esc_html_e( 'Token:', 'proof-of-gift' ); ?> <code><?php echo esc_html( $change_token ); ?></code>
            </p>
            <?php
        }
    }
}