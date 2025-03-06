<?php
/**
 * Shortcodes for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_Shortcodes
 *
 * Registers and handles shortcodes for the plugin.
 */
class POG_Shortcodes {

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
     * Register shortcodes.
     *
     * @return void
     */
    public function register() {
        add_shortcode( 'pog_verify_token', array( $this, 'verify_token_shortcode' ) );
        add_shortcode( 'pog_token_form', array( $this, 'token_form_shortcode' ) );
    }

    /**
     * Verify token shortcode handler.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content.
     * @return string
     */
    public function verify_token_shortcode( $atts, $content = null ) {
        $atts = shortcode_atts(
            array(
                'token' => '',
            ),
            $atts,
            'pog_verify_token'
        );

        // If no token is provided, display a form.
        if ( empty( $atts['token'] ) ) {
            return $this->token_verification_form();
        }

        // Verify the token.
        $token = sanitize_text_field( $atts['token'] );
        $verification = $this->token_handler->verify_token( $token );

        return $this->token_verification_result( $verification, $token );
    }

    /**
     * Token form shortcode handler.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content.
     * @return string
     */
    public function token_form_shortcode( $atts, $content = null ) {
        $atts = shortcode_atts(
            array(
                'button_text' => __( 'Apply Token', 'proof-of-gift' ),
                'placeholder' => __( 'Enter your token here', 'proof-of-gift' ),
            ),
            $atts,
            'pog_token_form'
        );

        ob_start();
        ?>
        <div class="pog-token-form">
            <form method="post" action="">
                <div class="pog-token-form-field">
                    <input type="text" name="pog_token" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" />
                    <button type="submit" name="pog_apply_token" class="button"><?php echo esc_html( $atts['button_text'] ); ?></button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate the token verification form.
     *
     * @return string
     */
    private function token_verification_form() {
        ob_start();
        ?>
        <div class="pog-verify-token-form">
            <h2><?php esc_html_e( 'Verify Gift Token', 'proof-of-gift' ); ?></h2>
            <form method="post" action="">
                <div class="pog-verify-token-field">
                    <label for="pog-token"><?php esc_html_e( 'Token', 'proof-of-gift' ); ?></label>
                    <input type="text" id="pog-token" name="pog_token" />
                </div>
                <button type="submit" class="button" name="pog_verify_token"><?php esc_html_e( 'Verify Token', 'proof-of-gift' ); ?></button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate the token verification result.
     *
     * @param array|false $verification The verification result.
     * @param string      $token        The token that was verified.
     * @return string
     */
    private function token_verification_result( $verification, $token ) {
        ob_start();

        if ( false === $verification ) {
            ?>
            <div class="pog-verify-result pog-verify-invalid">
                <h2><?php esc_html_e( 'Invalid Token', 'proof-of-gift' ); ?></h2>
                <p><?php esc_html_e( 'The token you provided is not valid.', 'proof-of-gift' ); ?></p>
                <div class="pog-token"><?php echo esc_html( $token ); ?></div>
            </div>
            <?php
            return ob_get_clean();
        }

        // Get the operational mode.
        $mode = $this->token_handler->get_operational_mode();

        // Determine the amount to display.
        $amount = $verification['amount'];
        $currency = '';

        if ( 'store_currency' === $mode ) {
            $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        } elseif ( 'satoshi_conversion' === $mode ) {
            $currency_amount = $this->token_handler->convert_satoshis_to_currency( $amount );
            $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
            $amount_label = $amount . ' satoshis (' . $currency_symbol . $currency_amount . ')';
        } elseif ( 'direct_satoshi' === $mode ) {
            $amount_label = $amount . ' satoshis';
        } else {
            $amount_label = $currency . $amount;
        }

        if ( ! isset( $amount_label ) ) {
            $amount_label = $currency . $amount;
        }

        // Check if the token has been redeemed.
        $redeemed = isset( $verification['redeemed'] ) && $verification['redeemed'];

        ?>
        <div class="pog-verify-result <?php echo $verification['valid'] ? 'pog-verify-valid' : 'pog-verify-invalid'; ?>">
            <h2>
                <?php
                if ( $verification['valid'] ) {
                    esc_html_e( 'Valid Token', 'proof-of-gift' );
                } elseif ( $redeemed ) {
                    esc_html_e( 'Redeemed Token', 'proof-of-gift' );
                } else {
                    esc_html_e( 'Invalid Token', 'proof-of-gift' );
                }
                ?>
            </h2>
            
            <div class="pog-verify-token-details">
                <div class="pog-token-row">
                    <div class="pog-token-label"><?php esc_html_e( 'Token', 'proof-of-gift' ); ?></div>
                    <div class="pog-token-value"><?php echo esc_html( $token ); ?></div>
                </div>
                
                <div class="pog-token-row">
                    <div class="pog-token-label"><?php esc_html_e( 'Amount', 'proof-of-gift' ); ?></div>
                    <div class="pog-token-value"><?php echo esc_html( $amount_label ); ?></div>
                </div>
                
                <div class="pog-token-row">
                    <div class="pog-token-label"><?php esc_html_e( 'Status', 'proof-of-gift' ); ?></div>
                    <div class="pog-token-value">
                        <?php
                        if ( $verification['valid'] ) {
                            esc_html_e( 'Valid and ready to use', 'proof-of-gift' );
                        } elseif ( $redeemed ) {
                            esc_html_e( 'Already redeemed', 'proof-of-gift' );
                            
                            // Get redemption details.
                            $redemption_data = $this->token_handler->get_redemption_data( $token );
                            
                            if ( $redemption_data ) {
                                echo '<br>';
                                
                                // Format the date.
                                $date = date_i18n(
                                    get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                                    strtotime( $redemption_data['redeemed_at'] )
                                );
                                
                                printf(
                                    /* translators: %s: redemption date */
                                    esc_html__( 'Redeemed on: %s', 'proof-of-gift' ),
                                    esc_html( $date )
                                );
                                
                                // If it's associated with an order, display the order ID.
                                if ( ! empty( $redemption_data['order_id'] ) ) {
                                    echo '<br>';
                                    
                                    if ( function_exists( 'wc_get_order' ) ) {
                                        $order = wc_get_order( $redemption_data['order_id'] );
                                        
                                        if ( $order ) {
                                            printf(
                                                /* translators: %s: order number */
                                                esc_html__( 'Order: #%s', 'proof-of-gift' ),
                                                esc_html( $order->get_order_number() )
                                            );
                                        } else {
                                            printf(
                                                /* translators: %s: order ID */
                                                esc_html__( 'Order ID: %s', 'proof-of-gift' ),
                                                esc_html( $redemption_data['order_id'] )
                                            );
                                        }
                                    } else {
                                        printf(
                                            /* translators: %s: order ID */
                                            esc_html__( 'Order ID: %s', 'proof-of-gift' ),
                                            esc_html( $redemption_data['order_id'] )
                                        );
                                    }
                                }
                            }
                        } else {
                            esc_html_e( 'Invalid', 'proof-of-gift' );
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <?php if ( $verification['valid'] && function_exists( 'wc_get_page_id' ) ) : ?>
                <div class="pog-apply-token">
                    <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'checkout' ) ) ); ?>" class="button">
                        <?php esc_html_e( 'Apply Token at Checkout', 'proof-of-gift' ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}