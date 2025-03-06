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
        // Verify the token.
        $verification = $this->token_handler->verify_token( $token );

        if ( ! $verification || ! $verification['valid'] || isset( $verification['redeemed'] ) ) {
            return array(
                'success' => false,
                'message' => isset( $verification['redeemed'] ) ? __( 'This token has already been redeemed.', 'proof-of-gift' ) : __( 'Invalid token.', 'proof-of-gift' ),
            );
        }

        // Get the operational mode.
        $mode = $this->token_handler->get_operational_mode();

        // Determine the amount to apply.
        $amount = $verification['amount'];

        if ( 'satoshi_conversion' === $mode ) {
            // Convert satoshis to store currency.
            $amount = $this->token_handler->convert_satoshis_to_currency( $amount );
        }

        // If WooCommerce is active, apply the token to the cart.
        if ( function_exists( 'WC' ) ) {
            // Add the token as a coupon or fee (depending on the operational mode).
            if ( 'direct_satoshi' === $mode ) {
                // For direct satoshi mode, store the token in a session for BTCPay Server checkout.
                WC()->session->set( 'pog_tokens', array_merge( WC()->session->get( 'pog_tokens', array() ), array( $token ) ) );
            } else {
                // For store currency and satoshi conversion modes, apply as a fee or coupon.
                // Create a unique coupon code for this token.
                $coupon_code = 'pog_' . substr( md5( $token ), 0, 10 );

                // Create a custom coupon.
                $coupon = new \WC_Coupon();
                $coupon->set_code( $coupon_code );
                $coupon->set_discount_type( 'fixed_cart' );
                $coupon->set_amount( $amount );
                $coupon->set_individual_use( false );
                $coupon->set_usage_limit( 1 );
                $coupon->save();

                // Apply the coupon to the cart.
                WC()->cart->apply_coupon( $coupon_code );

                // Store the token with the coupon code for later redemption.
                WC()->session->set( 'pog_token_' . $coupon_code, $token );
            }

            return array(
                'success' => true,
                'message' => sprintf(
                    /* translators: %1$s: amount, %2$s: currency */
                    __( 'Token applied: %1$s%2$s', 'proof-of-gift' ),
                    'direct_satoshi' === $mode ? '' : ( function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$' ),
                    'direct_satoshi' === $mode ? sprintf( __( '%d satoshis', 'proof-of-gift' ), $verification['amount'] ) : $amount
                ),
                'token'   => $token,
                'amount'  => $amount,
                'mode'    => $mode,
            );
        }

        // If WooCommerce is not active, return an error.
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

        // Get the applied coupons.
        $coupons = $order->get_coupon_codes();

        if ( empty( $coupons ) ) {
            return;
        }

        // Process each coupon.
        foreach ( $coupons as $coupon_code ) {
            // Check if it's a POG token coupon.
            if ( 0 === strpos( $coupon_code, 'pog_' ) ) {
                // Get the token.
                $token = WC()->session->get( 'pog_token_' . $coupon_code );

                if ( ! $token ) {
                    continue;
                }

                // Redeem the token.
                $this->token_handler->redeem_token( $token, $order_id, get_current_user_id() );

                // Remove the session data.
                WC()->session->__unset( 'pog_token_' . $coupon_code );

                // Delete the coupon.
                $coupon = new \WC_Coupon( $coupon_code );
                $coupon->delete( true );
            }
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

        // If the total token value is greater than the order total, issue a change token.
        if ( $total_token_value > $order_total ) {
            $change_amount = $total_token_value - $order_total;

            // For satoshi conversion mode, convert the change amount back to satoshis.
            if ( 'satoshi_conversion' === $mode ) {
                $change_amount = $this->token_handler->convert_currency_to_satoshis( $change_amount );
            }

            // Generate a change token.
            $change_token = $this->token_handler->generate_change_token( $change_amount );

            // Store the change token with the order.
            update_post_meta( $order_id, '_pog_change_token', $change_token );
            update_post_meta( $order_id, '_pog_change_amount', $change_amount );

            // Add a note to the order.
            $order->add_order_note(
                sprintf(
                    /* translators: %1$s: change amount, %2$s: change token */
                    __( 'Change token generated: %1$s (%2$s)', 'proof-of-gift' ),
                    'satoshi_conversion' === $mode || 'direct_satoshi' === $mode ? $change_amount . ' satoshis' : $change_amount,
                    $change_token
                )
            );

            // Include the change token in the order confirmation email.
            add_action( 'woocommerce_email_after_order_table', array( $this, 'add_change_token_to_email' ), 10, 3 );
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

        // Get the operational mode.
        $mode = $this->token_handler->get_operational_mode();

        // Determine the amount to display.
        $amount = $change_amount;
        $currency = '';

        if ( 'store_currency' === $mode ) {
            $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        } elseif ( 'satoshi_conversion' === $mode || 'direct_satoshi' === $mode ) {
            $currency = 'satoshis';
        }

        // Output the change token.
        if ( $plain_text ) {
            echo "\n\n" . esc_html__( 'Change Token', 'proof-of-gift' ) . "\n";
            echo esc_html__( 'Amount:', 'proof-of-gift' ) . ' ' . esc_html( $amount ) . ' ' . esc_html( $currency ) . "\n";
            echo esc_html__( 'Token:', 'proof-of-gift' ) . ' ' . esc_html( $change_token ) . "\n\n";
        } else {
            ?>
            <h2><?php esc_html_e( 'Change Token', 'proof-of-gift' ); ?></h2>
            <p>
                <?php esc_html_e( 'Amount:', 'proof-of-gift' ); ?> <?php echo esc_html( $amount ); ?> <?php echo esc_html( $currency ); ?><br>
                <?php esc_html_e( 'Token:', 'proof-of-gift' ); ?> <code><?php echo esc_html( $change_token ); ?></code>
            </p>
            <?php
        }
    }
}