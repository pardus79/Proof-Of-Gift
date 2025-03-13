<?php
/**
 * Public functionality for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_Public
 *
 * Handles public-facing functionality.
 */
class POG_Public {

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
     * Enqueue public scripts.
     *
     * @return void
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'pog-public',
            POG_PLUGIN_URL . 'assets/js/public.js',
            array( 'jquery' ),
            POG_VERSION,
            true
        );

        wp_localize_script(
            'pog-public',
            'pog_public_vars',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'pog_public_nonce' ),
                'strings'  => array(
                    'token_invalid'  => __( 'Invalid token.', 'proof-of-gift' ),
                    'token_redeemed' => __( 'This token has already been redeemed.', 'proof-of-gift' ),
                    'error'          => __( 'An error occurred.', 'proof-of-gift' ),
                ),
            )
        );
    }

    /**
     * Enqueue public styles.
     *
     * @return void
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'pog-public',
            POG_PLUGIN_URL . 'assets/css/public.css',
            array(),
            POG_VERSION
        );
    }

    /**
     * Handle the verification template.
     *
     * @param array $verification The verification data.
     * @return void
     */
    public function render_verification_template( $verification ) {
        // Get the operational mode.
        $mode = $this->token_handler->get_operational_mode();

        // Determine the amount to display.
        $amount = $verification['amount'];
        $currency = '';

        if ( 'store_currency' === $mode ) {
            $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        } elseif ( 'satoshi_conversion' === $mode ) {
            $amount = $this->token_handler->convert_satoshis_to_currency( $amount );
            $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        } elseif ( 'direct_satoshi' === $mode ) {
            $currency = 'sats';
        }

        // Include the verification template.
        include POG_PLUGIN_DIR . 'templates/token-verification.php';
    }

    /**
     * Check if the token is valid.
     *
     * @param string $token The token to check.
     * @param bool   $check_redemption Whether to check if the token has been redeemed.
     * @return array|false The verification data if valid, false otherwise.
     */
    public function check_token( $token, $check_redemption = true ) {
        return $this->token_handler->verify_token( $token, $check_redemption );
    }

    /**
     * Apply a token to the cart.
     *
     * @param string $token The token to apply.
     * @return array The result of applying the token.
     */
    public function apply_token( $token ) {
        error_log('Proof Of Gift: Attempting to apply token: ' . $token);
        
        // Check if token has already been redeemed before doing full verification
        if ($this->token_handler->is_token_redeemed( $token )) {
            error_log('Proof Of Gift: Token already redeemed, cannot apply: ' . $token);
            return array(
                'success' => false,
                'message' => __( 'This token has already been redeemed.', 'proof-of-gift' ),
            );
        }
        
        // Verify the token.
        $verification = $this->token_handler->verify_token( $token );

        if ( ! $verification || ! $verification['valid'] || isset( $verification['redeemed'] ) ) {
            $error_msg = isset( $verification['redeemed'] ) ? 'Token already redeemed' : 'Invalid token';
            error_log('Proof Of Gift: Token verification failed: ' . $error_msg);
            return array(
                'success' => false,
                'message' => isset( $verification['redeemed'] ) ? __( 'This token has already been redeemed.', 'proof-of-gift' ) : __( 'Invalid token.', 'proof-of-gift' ),
            );
        }

        error_log('Proof Of Gift: Token verified successfully, amount: ' . $verification['amount']);

        // Get the operational mode.
        $mode = $this->token_handler->get_operational_mode();
        error_log('Proof Of Gift: Operational mode: ' . $mode);

        // Determine the amount to apply.
        $amount = $verification['amount'];

        if ( 'satoshi_conversion' === $mode ) {
            // Convert satoshis to store currency. Force refresh of exchange rate when token is added.
            $amount = $this->token_handler->convert_satoshis_to_currency( $amount, true );
            error_log('Proof Of Gift: Converted satoshi amount to currency: ' . $amount);
        }

        // If WooCommerce is active, apply the token to the cart.
        if ( function_exists( 'WC' ) ) {
            try {
                // Add the token as a coupon or fee (depending on the operational mode).
                if ( 'direct_satoshi' === $mode ) {
                    // For direct satoshi mode, store the token in a session for BTCPay Server checkout.
                    $existing_tokens = WC()->session->get( 'pog_tokens', array() );
                    $existing_tokens[] = $token;
                    WC()->session->set( 'pog_tokens', $existing_tokens );
                    error_log('Proof Of Gift: Token stored in session for direct satoshi mode');
                } else {
                    // For store currency and satoshi conversion modes, use WooCommerce coupon system
                    $token_code = 'pog_' . substr( md5( $token ), 0, 10 );
                    error_log('Proof Of Gift: Creating coupon with code: ' . $token_code . ' for amount: ' . $amount);
                    
                    // We no longer create a coupon since we're using fees only
                    // Store metadata but don't create the coupon object
                    $token_label = \ProofOfGift\POG_Utils::get_token_name_plural();
                    
                    // Store the token in session too for tracking/redemption
                    $token_data = array(
                        'token' => $token,
                        'amount' => $amount,
                        'original_amount' => $verification['amount'],
                        'coupon_code' => $token_code
                    );
                    
                    // Get existing tokens from session
                    $existing_tokens = WC()->session->get('pog_cart_tokens', array());
                    $existing_tokens[$token_code] = $token_data;
                    
                    // Save back to session
                    WC()->session->set('pog_cart_tokens', $existing_tokens);
                    error_log('Proof Of Gift: Token stored in session: ' . json_encode($token_data));
                    
                    // Apply token as a fee - more reliable and works better as a gift card
                    try {
                        // First, remove any existing fee for this token to prevent duplicates
                        $this->remove_existing_token_fee($token_code);
                        
                        // Get the token label for display - use only the token name
                        $token_label = \ProofOfGift\POG_Utils::get_token_name_plural();
                        
                        // Create a unique fee ID for this token
                        $fee_id = 'pog_token_' . substr(md5($token), 0, 8);
                        
                        // Store token data in session for later use in payment and order processing
                        $token_fees = WC()->session->get('pog_token_fees', array());
                        $token_fees[$fee_id] = array(
                            'token' => $token,
                            'token_code' => $token_code,
                            'amount' => $amount,
                            'original_amount' => $verification['amount'],
                            'label' => $token_label,
                            'mode' => $mode
                        );
                        WC()->session->set('pog_token_fees', $token_fees);
                        error_log('Proof Of Gift: Token stored in session as fee: ' . $fee_id . ', amount: ' . $amount);
                        
                        // Apply the fee to the cart
                        WC()->cart->add_fee($token_label, -$amount, false);
                        error_log('Proof Of Gift: Added fee to cart with label: ' . $token_label . ', amount: -' . $amount);
                        
                        // Force cart recalculation
                        WC()->cart->calculate_totals();
                        error_log('Proof Of Gift: Cart totals recalculated. Cart total: ' . WC()->cart->get_total());
                    } catch (\Exception $e) {
                        error_log('Proof Of Gift: Exception applying token as fee: ' . $e->getMessage());
                    }
                }

                // Format the amount display based on mode
                $amount_display = '';
                if ('satoshi_conversion' === $mode || 'direct_satoshi' === $mode) {
                    $amount_display = sprintf(__('%d Sats', 'proof-of-gift'), $verification['amount']);
                } else {
                    if (function_exists('get_woocommerce_currency_symbol')) {
                        $amount_display = get_woocommerce_currency_symbol() . $amount;
                    } else {
                        $amount_display = '$' . $amount;
                    }
                }

                error_log('Proof Of Gift: Token applied successfully: ' . $amount_display);
                return array(
                    'success' => true,
                    'message' => sprintf(
                        /* translators: %s: amount with currency */
                        __( 'Token applied: %s', 'proof-of-gift' ),
                        $amount_display
                    ),
                    'token'   => $token,
                    'amount'  => $amount,
                    'mode'    => $mode,
                );
            } catch (\Exception $e) {
                error_log('Proof Of Gift: Exception applying token: ' . $e->getMessage());
                return array(
                    'success' => false,
                    'message' => __( 'Error applying token: ', 'proof-of-gift' ) . $e->getMessage(),
                );
            }
        }

        // If WooCommerce is not active, return an error.
        error_log('Proof Of Gift: WooCommerce not active, cannot apply token');
        return array(
            'success' => false,
            'message' => __( 'WooCommerce is required to apply tokens.', 'proof-of-gift' ),
        );
    }

    /**
     * Process the token after an order is placed.
     *
     * @param int $order_id The order ID.
     * @return void
     */
    public function process_token_after_order( $order_id ) {
        // Get the order.
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Get the operational mode
        $mode = $this->token_handler->get_operational_mode();
        
        // If we're in satoshi conversion mode, force refresh the exchange rate
        if ( 'satoshi_conversion' === $mode ) {
            $this->token_handler->get_satoshi_exchange_rate( true );
        }
        
        // Track the total value of all applied tokens
        $total_token_value = 0;
        $total_satoshi_value = 0;
        $order_total = $order->get_total('edit');
        $redeemed_tokens = array();
        $tokens_metadata = array();
        
        error_log('Proof Of Gift: Processing tokens for order #' . $order_id . ' with total: ' . $order_total);
        
        // Process token fees from session
        $token_fees = WC()->session->get('pog_token_fees', array());
        
        if (!empty($token_fees)) {
            foreach ($token_fees as $fee_id => $fee_data) {
                if (isset($fee_data['token'])) {
                    $token = $fee_data['token'];
                    
                    // Skip if token has already been redeemed
                    if ($this->token_handler->is_token_redeemed($token)) {
                        error_log('Proof Of Gift: Token already redeemed, skipping: ' . $token);
                        continue;
                    }
                    
                    // Store the token value before conversion for accurate tracking
                    $original_satoshi_value = isset($fee_data['original_amount']) ? (int)$fee_data['original_amount'] : 0;
                    $token_currency_value = isset($fee_data['amount']) ? (float)$fee_data['amount'] : 0;
                    
                    // If in satoshi conversion mode, recalculate with the latest exchange rate
                    if ('satoshi_conversion' === $mode && $original_satoshi_value > 0) {
                        $latest_amount = $this->token_handler->convert_satoshis_to_currency(
                            $original_satoshi_value,
                            false  // Don't force refresh again
                        );
                        $token_currency_value = $latest_amount;
                        $fee_data['amount'] = $latest_amount;
                    }
                    
                    // Store metadata about the token
                    $tokens_metadata[] = array(
                        'token' => $token,
                        'original_amount' => $original_satoshi_value,
                        'currency_amount' => $token_currency_value,
                        'mode' => $mode
                    );
                    
                    // Add to running totals
                    if ($original_satoshi_value > 0) {
                        $total_satoshi_value += $original_satoshi_value;
                    }
                    $total_token_value += $token_currency_value;
                    
                    error_log('Proof Of Gift: Token ' . $token . ' value: ' . $token_currency_value . 
                             ($original_satoshi_value > 0 ? ' (' . $original_satoshi_value . ' sats)' : ''));
                    
                    // Redeem the token and track it to avoid duplicates
                    $redemption = $this->token_handler->redeem_token($token, $order_id, get_current_user_id());
                    if (!is_wp_error($redemption)) {
                        $redeemed_tokens[] = $token;
                        
                        // Add note to order for reference
                        if ('satoshi_conversion' === $mode && $original_satoshi_value > 0) {
                            $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
                            $order->add_order_note(
                                sprintf(
                                    __('Gift token redeemed: %s (%d satoshis = %s%s at current exchange rate)', 'proof-of-gift'),
                                    $token,
                                    $original_satoshi_value,
                                    $currency,
                                    number_format($token_currency_value, 2)
                                )
                            );
                        } else {
                            $order->add_order_note(
                                sprintf(
                                    __('Gift token redeemed: %s (Amount: %s)', 'proof-of-gift'),
                                    $token,
                                    $original_satoshi_value > 0 ? 
                                        $original_satoshi_value . ' satoshis' : 
                                        (function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$') . number_format($token_currency_value, 2)
                                )
                            );
                        }
                    } else {
                        error_log('Proof Of Gift: Failed to redeem token: ' . $token . ' - ' . $redemption->get_error_message());
                    }
                }
            }
            
            // Store the total token value with the order
            update_post_meta($order_id, '_pog_total_token_value', $total_token_value);
            if ($total_satoshi_value > 0) {
                update_post_meta($order_id, '_pog_total_satoshi_value', $total_satoshi_value);
            }
            update_post_meta($order_id, '_pog_tokens_metadata', $tokens_metadata);
            
            // Process change token calculation for satoshi conversion mode
            $this->process_satoshi_token_change($order_id);
            
            // Clear token fees after processing
            WC()->session->set('pog_token_fees', array());
            WC()->session->set('pog_removed_fees', array());
        }
        
        // Also process legacy cart tokens for backward compatibility
        $session_tokens = WC()->session->get('pog_cart_tokens', array());
        
        if (!empty($session_tokens)) {
            foreach ($session_tokens as $token_code => $token_data) {
                if (isset($token_data['token'])) {
                    $token = $token_data['token'];
                    
                    // Skip if already redeemed in this order or already in database
                    if (in_array($token, $redeemed_tokens) || $this->token_handler->is_token_redeemed($token)) {
                        continue;
                    }
                    
                    // If in satoshi conversion mode, recalculate with the latest exchange rate
                    if ('satoshi_conversion' === $mode && isset($token_data['original_amount'])) {
                        $latest_amount = $this->token_handler->convert_satoshis_to_currency(
                            $token_data['original_amount'],
                            false  // Don't force refresh again
                        );
                        $token_data['amount'] = $latest_amount;
                    }
                    
                    // Redeem the token
                    $redemption = $this->token_handler->redeem_token($token, $order_id, get_current_user_id());
                    if (!is_wp_error($redemption)) {
                        // Add note to order for reference
                        if ('satoshi_conversion' === $mode && isset($token_data['original_amount'])) {
                            $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
                            $order->add_order_note(
                                sprintf(
                                    __('Gift token redeemed: %s (%d satoshis = %s%s at current exchange rate)', 'proof-of-gift'),
                                    $token_data['token'],
                                    $token_data['original_amount'],
                                    $currency,
                                    $token_data['amount']
                                )
                            );
                        } else {
                            $order->add_order_note(
                                sprintf(
                                    __('Gift token redeemed: %s (Amount: %s)', 'proof-of-gift'),
                                    $token_data['token'],
                                    isset($token_data['original_amount']) ? $token_data['original_amount'] . ' satoshis' : $token_data['amount']
                                )
                            );
                        }
                    }
                }
            }
            
            // Clear session tokens after processing
            WC()->session->set('pog_cart_tokens', array());
        }

        // Process direct satoshi tokens.
        $tokens = WC()->session->get( 'pog_tokens', array() );

        if ( ! empty( $tokens ) ) {
            // Get the payment method.
            $payment_method = $order->get_payment_method();

            // Check if the payment method is BTCPay Server.
            if ( 'btcpay' === $payment_method ) {
                // The tokens will be processed by the BTCPay Server integration.
                // Just store the tokens with the order.
                update_post_meta( $order_id, '_pog_tokens', $tokens );
            } else {
                // For other payment methods, issue change tokens if needed.
                $this->process_change_tokens( $order_id, $tokens );
            }

            // Remove the session data.
            WC()->session->__unset( 'pog_tokens' );
        }
        
        // Final check to ensure change tokens are generated
        // This catches cases where the order total is 0 but token value > 0
        if ($order->get_total() == 0) {
            error_log('Proof Of Gift: Zero total order detected, ensuring change token is generated');
            
            // Force the token fees data to be stored with the order for later recovery
            $token_fees = WC()->session->get('pog_token_fees', array());
            if (!empty($token_fees)) {
                update_post_meta($order_id, '_pog_order_token_fees', $token_fees);
                error_log('Proof Of Gift: Stored token fees with order for recovery');
            }
            
            $this->process_satoshi_token_change($order_id);
        }
        
        // Always check for change tokens and add them to the thank you page
        // This ensures the token will display even if it was generated after initial processing
        add_action('woocommerce_thankyou', array($this, 'add_change_token_to_thankyou'), 10, 1);
    }

    /**
     * Process change tokens.
     *
     * @param int   $order_id The order ID.
     * @param array $tokens The tokens to process.
     * @return void
     */
    private function process_change_tokens( $order_id, $tokens ) {
        // Get the order.
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Get the order total.
        $order_total = $order->get_total();

        // Get the operational mode.
        $mode = $this->token_handler->get_operational_mode();

        // Calculate the total token value.
        $total_token_value = 0;

        foreach ( $tokens as $token ) {
            // Verify the token.
            $verification = $this->token_handler->verify_token( $token );

            if ( ! $verification || ! $verification['valid'] || isset( $verification['redeemed'] ) ) {
                continue;
            }

            // Add the token value.
            if ( 'direct_satoshi' === $mode ) {
                $total_token_value += $verification['amount'];
            } elseif ( 'satoshi_conversion' === $mode ) {
                $total_token_value += $this->token_handler->convert_satoshis_to_currency( $verification['amount'] );
            } else {
                $total_token_value += $verification['amount'];
            }

            // Redeem the token.
            $this->token_handler->redeem_token( $token, $order_id, get_current_user_id() );
        }

        // Ensure order total and token value calculations are accurate
        error_log('Proof Of Gift: Change calculation - Order total: ' . $order_total . ', Token value: ' . $total_token_value);
        
        // If the total token value is greater than the order total, issue a change token.
        if ( $total_token_value > $order_total ) {
            $change_amount = $total_token_value - $order_total;
            
            $amount_formatted = number_format($change_amount, 2);
            error_log('Proof Of Gift: Generating change token for excess amount: ' . $amount_formatted);

            // For satoshi conversion mode, convert the change amount back to satoshis.
            if ( 'satoshi_conversion' === $mode ) {
                $original_change = $change_amount;
                $change_amount = $this->token_handler->convert_currency_to_satoshis( $change_amount );
                error_log('Proof Of Gift: Converted change amount from ' . $original_change . ' to ' . $change_amount . ' satoshis');
            }

            // Generate a change token.
            $change_token = $this->token_handler->generate_change_token( $change_amount );

            // Format amount for display
            $amount_display = 'satoshi_conversion' === $mode || 'direct_satoshi' === $mode ? 
                $change_amount . ' satoshis' : 
                (function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$') . $amount_formatted;

            // Store the change token with the order.
            update_post_meta( $order_id, '_pog_change_token', $change_token );
            update_post_meta( $order_id, '_pog_change_amount', $change_amount );
            update_post_meta( $order_id, '_pog_change_currency_mode', $mode );

            // Add a note to the order.
            $order->add_order_note(
                sprintf(
                    /* translators: %1$s: change amount, %2$s: change token */
                    __( 'Change token generated: %1$s (%2$s)', 'proof-of-gift' ),
                    $amount_display,
                    $change_token
                )
            );

            // Include the change token in the order confirmation email.
            add_action( 'woocommerce_email_after_order_table', array( $this, 'add_change_token_to_email' ), 10, 3 );
            
            // Also add to the thank you page
            // Let the WooCommerce integration handle this display instead
            // add_action( 'woocommerce_thankyou', array( $this, 'add_change_token_to_thankyou' ), 10, 1 );
        }
    }
    
    /**
     * Display change token on the order thank you page.
     *
     * @param int $order_id The order ID.
     * @return void
     */
    public function add_change_token_to_thankyou( $order_id ) {
        // Only proceed if this is the same order
        if ( ! $order_id ) {
            return;
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        $change_token = get_post_meta( $order_id, '_pog_change_token', true );
        $change_amount = get_post_meta( $order_id, '_pog_change_amount', true );
        
        if ( ! $change_token || ! $change_amount ) {
            return;
        }
        
        // Get the operational mode
        $mode = $this->token_handler->get_operational_mode();
        
        // Determine the amount to display
        $amount = $change_amount;
        $currency = '';
        
        if ( 'store_currency' === $mode ) {
            $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        } elseif ( 'satoshi_conversion' === $mode || 'direct_satoshi' === $mode ) {
            $currency = 'satoshis';
        }
        
        // Output the change token in a styled box
        ?>
        <div class="woocommerce-order-overview woocommerce-change-token">
            <h2><?php esc_html_e( 'Change Token', 'proof-of-gift' ); ?></h2>
            <p><?php esc_html_e( 'You have received credit for the excess token value.', 'proof-of-gift' ); ?></p>
            <p>
                <strong><?php esc_html_e( 'Amount:', 'proof-of-gift' ); ?></strong> 
                <?php echo esc_html( $amount ); ?> <?php echo esc_html( $currency ); ?>
            </p>
            <p>
                <strong><?php esc_html_e( 'Token:', 'proof-of-gift' ); ?></strong><br>
                <code class="pog-token-code"><?php echo esc_html( $change_token ); ?></code>
            </p>
        </div>
        <?php
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
        $mode = $this->token_handler->get_operational_mode();
        POG_Utils::add_change_token_to_email( $order, $sent_to_admin, $plain_text, $mode );
    }
    
    /**
     * Remove existing token fee if it exists to prevent duplicates
     *
     * @param string $token_code The token code to check
     * @return void
     */
    private function remove_existing_token_fee($token_code) {
        if (!function_exists('WC') || !isset(WC()->cart) || !isset(WC()->cart->fees_api) || !method_exists(WC()->cart->fees_api, 'get_fees')) {
            return;
        }
        
        // Get current fees
        $fees = WC()->cart->fees_api->get_fees();
        $token_fees = WC()->session->get('pog_token_fees', array());
        
        // Check if we have this token code in existing fees
        foreach ($token_fees as $fee_id => $fee_data) {
            if (isset($fee_data['token_code']) && $fee_data['token_code'] === $token_code) {
                error_log('Proof Of Gift: Found existing fee for token: ' . $token_code . ', removing it');
                
                // Remove from session
                unset($token_fees[$fee_id]);
                WC()->session->set('pog_token_fees', $token_fees);
                
                // Remove any fee with matching label if found
                foreach ($fees as $fee_key => $fee) {
                    if (isset($fee_data['label']) && $fee->name === $fee_data['label']) {
                        // Remove this fee (will be recreated with updated amount if needed)
                        WC()->cart->remove_fee($fee->name);
                        error_log('Proof Of Gift: Removed fee with name: ' . $fee->name);
                        break;
                    }
                }
                
                break;
            }
        }
    }
    
    /**
     * Calculate and generate a change token for satoshi conversion mode
     * This function is called directly from process_token_after_order
     *
     * @param int   $order_id   The order ID
     * @param array $token_fees The token fees from session
     * @return void
     */
    public function process_satoshi_token_change($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $mode = $this->token_handler->get_operational_mode();
        $order_total = $order->get_total();
        
        error_log('Proof Of Gift: Checking for satoshi token change on order #' . $order_id . ', total: ' . $order_total);
        
        // Get all the tokens used in this order from order meta
        $order_tokens = array();
        $total_token_value = 0;
        $total_satoshi_value = 0;
        
        // Get stored token fees from WC session
        $token_fees = WC()->session->get('pog_token_fees', array());
        
        foreach ($token_fees as $fee_id => $fee_data) {
            if (isset($fee_data['token']) && isset($fee_data['amount'])) {
                $token = $fee_data['token'];
                $original_amount = isset($fee_data['original_amount']) ? (int)$fee_data['original_amount'] : 0;
                $token_amount = isset($fee_data['amount']) ? (float)$fee_data['amount'] : 0;
                
                $order_tokens[$token] = array(
                    'original_amount' => $original_amount,
                    'amount' => $token_amount
                );
                
                if ($original_amount > 0) {
                    $total_satoshi_value += $original_amount;
                }
                $total_token_value += $token_amount;
                
                error_log('Proof Of Gift: Adding token to order totals: ' . $token . ', amount: ' . $token_amount);
            }
        }
        
        // If we're in satoshi conversion mode and tokens exceed the order total, create a change token
        if ('satoshi_conversion' === $mode && $total_satoshi_value > 0) {
            // Get the order subtotal (items only, no shipping/taxes/fees)
            $subtotal = $order->get_subtotal();
            $shipping = $order->get_shipping_total();
            
            // Items subtotal plus shipping in currency
            $amount_spent_in_currency = $subtotal + $shipping;
            
            // Convert the amount spent to satoshis
            $amount_spent_in_satoshis = $this->token_handler->convert_currency_to_satoshis($amount_spent_in_currency);
            
            // Calculate change as original satoshis minus spent satoshis
            $satoshi_change = $total_satoshi_value;
            if (!is_wp_error($amount_spent_in_satoshis)) {
                $satoshi_change = $total_satoshi_value - $amount_spent_in_satoshis;
            }
            
            error_log('Proof Of Gift: Generating satoshi change token. Original satoshis: ' . $total_satoshi_value . 
                     ', Amount spent: ' . $amount_spent_in_currency . ' (' . (is_wp_error($amount_spent_in_satoshis) ? 'error' : $amount_spent_in_satoshis . ' sats') . ')' .
                     ', Change in satoshis: ' . $satoshi_change);
            
            // Only proceed if we have positive change to give back
            if ($satoshi_change > 0) {
                // Generate a change token
                $change_token = $this->token_handler->generate_change_token($satoshi_change);
                
                // Store with the order
                update_post_meta($order_id, '_pog_change_token', $change_token);
                update_post_meta($order_id, '_pog_change_amount', $satoshi_change);
                update_post_meta($order_id, '_pog_change_currency_mode', $mode);
                
                // Add order note
                $order->add_order_note(sprintf(
                    __('Satoshi change token generated: %1$s satoshis (%2$s)', 'proof-of-gift'),
                    $satoshi_change,
                    $change_token
                ));
                
                // Add to thank you page and emails
                add_action('woocommerce_email_after_order_table', array($this, 'add_change_token_to_email'), 10, 3);
                // Let the WooCommerce integration handle this display instead
                // add_action('woocommerce_thankyou', array($this, 'add_change_token_to_thankyou'), 10, 1);
                
                error_log('Proof Of Gift: Change token generated successfully: ' . $change_token);
            } else {
                error_log('Proof Of Gift: Error converting change to satoshis: ' . $satoshi_change->get_error_message());
            }
        }
    }
}