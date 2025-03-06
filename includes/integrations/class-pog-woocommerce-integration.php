<?php
/**
 * WooCommerce integration for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_WooCommerce_Integration
 *
 * Handles WooCommerce integration functionality.
 */
class POG_WooCommerce_Integration {

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
     * Initialize the WooCommerce integration.
     *
     * @return void
     */
    public function initialize() {
        // Add token field to the checkout.
        add_action( 'woocommerce_after_order_notes', array( $this, 'add_token_field' ) );

        // Process token after order is created.
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_token_after_order' ), 10, 3 );

        // Add token to the cart.
        add_action( 'wp_ajax_pog_apply_token_to_cart', array( $this, 'ajax_apply_token_to_cart' ) );
        add_action( 'wp_ajax_nopriv_pog_apply_token_to_cart', array( $this, 'ajax_apply_token_to_cart' ) );

        // Add the tokens field to the cart.
        add_action( 'woocommerce_before_cart_totals', array( $this, 'add_token_field_to_cart' ) );
        add_action( 'woocommerce_before_checkout_form', array( $this, 'add_token_field_to_checkout' ) );

        // Display applied tokens.
        add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'display_applied_tokens' ) );
        add_action( 'woocommerce_review_order_after_order_total', array( $this, 'display_applied_tokens' ) );

        // Add a cron job to clean up expired tokens.
        add_action( 'pog_cleanup_expired_tokens', array( $this, 'cleanup_expired_tokens' ) );
        if ( ! wp_next_scheduled( 'pog_cleanup_expired_tokens' ) ) {
            wp_schedule_event( time(), 'daily', 'pog_cleanup_expired_tokens' );
        }
    }

    /**
     * Add a token field to the checkout form.
     *
     * @param \WC_Checkout $checkout The checkout object.
     * @return void
     */
    public function add_token_field( $checkout ) {
        echo '<div id="pog-token-field">';
        echo '<h3>' . esc_html__( 'Have a Gift Token?', 'proof-of-gift' ) . '</h3>';
        
        woocommerce_form_field(
            'pog_token',
            array(
                'type'        => 'text',
                'class'       => array( 'form-row-wide' ),
                'label'       => __( 'Enter your token here', 'proof-of-gift' ),
                'placeholder' => __( 'e.g. POG-abc123-xyz789', 'proof-of-gift' ),
            ),
            $checkout->get_value( 'pog_token' )
        );
        
        echo '<button type="button" class="button" id="pog-apply-token">' . esc_html__( 'Apply Token', 'proof-of-gift' ) . '</button>';
        echo '<div id="pog-token-message"></div>';
        echo '<div id="pog-applied-tokens"></div>';
        echo '</div>';
    }

    /**
     * Add a token field to the cart page.
     *
     * @return void
     */
    public function add_token_field_to_cart() {
        ?>
        <div id="pog-token-field" class="pog-token-field-cart">
            <h3><?php esc_html_e( 'Have a Gift Token?', 'proof-of-gift' ); ?></h3>
            
            <div class="pog-token-input">
                <input type="text" id="pog_token" name="pog_token" placeholder="<?php esc_attr_e( 'e.g. POG-abc123-xyz789', 'proof-of-gift' ); ?>" />
                <button type="button" class="button" id="pog-apply-token"><?php esc_html_e( 'Apply Token', 'proof-of-gift' ); ?></button>
            </div>
            
            <div id="pog-token-message"></div>
            <div id="pog-applied-tokens"></div>
        </div>
        <?php
    }

    /**
     * Add a token field to the checkout page.
     *
     * @return void
     */
    public function add_token_field_to_checkout() {
        ?>
        <div id="pog-token-field" class="pog-token-field-checkout">
            <h3><?php esc_html_e( 'Have a Gift Token?', 'proof-of-gift' ); ?></h3>
            
            <div class="pog-token-input">
                <input type="text" id="pog_token" name="pog_token" placeholder="<?php esc_attr_e( 'e.g. POG-abc123-xyz789', 'proof-of-gift' ); ?>" />
                <button type="button" class="button" id="pog-apply-token"><?php esc_html_e( 'Apply Token', 'proof-of-gift' ); ?></button>
            </div>
            
            <div id="pog-token-message"></div>
            <div id="pog-applied-tokens"></div>
        </div>
        <?php
    }

    /**
     * Display applied tokens.
     *
     * @return void
     */
    public function display_applied_tokens() {
        // Check if any POG tokens are applied.
        $applied_tokens = $this->get_applied_tokens();
        
        if ( empty( $applied_tokens ) ) {
            return;
        }
        
        // Display the applied tokens.
        ?>
        <tr class="pog-applied-tokens">
            <th><?php esc_html_e( 'Applied Gift Tokens', 'proof-of-gift' ); ?></th>
            <td>
                <ul>
                    <?php foreach ( $applied_tokens as $token_code => $token_data ) : ?>
                        <li>
                            <?php
                            echo esc_html( $token_data['label'] );
                            ?>
                            <a href="#" class="pog-remove-token" data-token="<?php echo esc_attr( $token_code ); ?>"><?php esc_html_e( 'Remove', 'proof-of-gift' ); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
        <?php
    }

    /**
     * Get applied tokens.
     *
     * @return array The applied tokens.
     */
    private function get_applied_tokens() {
        $applied_tokens = array();
        
        // Check if any POG coupons are applied.
        $applied_coupons = WC()->cart->get_applied_coupons();
        
        if ( empty( $applied_coupons ) ) {
            return $applied_tokens;
        }
        
        // Get the operational mode.
        $mode = $this->token_handler->get_operational_mode();
        
        // Get the currency symbol.
        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        
        // Loop through the applied coupons.
        foreach ( $applied_coupons as $coupon_code ) {
            // Check if it's a POG token coupon.
            if ( 0 === strpos( $coupon_code, 'pog_' ) ) {
                $coupon = new \WC_Coupon( $coupon_code );
                $amount = $coupon->get_amount();
                
                // Get the token.
                $token = WC()->session->get( 'pog_token_' . $coupon_code );
                
                if ( ! $token ) {
                    continue;
                }
                
                // Add the token to the list.
                $applied_tokens[ $coupon_code ] = array(
                    'token'  => $token,
                    'amount' => $amount,
                    'label'  => 'store_currency' === $mode ? $currency_symbol . $amount : $amount . ' satoshis',
                );
            }
        }
        
        // Also check for direct satoshi tokens.
        $tokens = WC()->session->get( 'pog_tokens', array() );
        
        if ( ! empty( $tokens ) ) {
            foreach ( $tokens as $token ) {
                // Verify the token.
                $verification = $this->token_handler->verify_token( $token );
                
                if ( ! $verification || ! $verification['valid'] || isset( $verification['redeemed'] ) ) {
                    continue;
                }
                
                // Add the token to the list.
                $applied_tokens[ 'direct_' . substr( md5( $token ), 0, 10 ) ] = array(
                    'token'  => $token,
                    'amount' => $verification['amount'],
                    'label'  => $verification['amount'] . ' satoshis',
                );
            }
        }
        
        return $applied_tokens;
    }

    /**
     * Process token after an order is placed.
     *
     * @param int   $order_id The order ID.
     * @param array $posted_data The posted data.
     * @param \WC_Order $order The order object.
     * @return void
     */
    public function process_token_after_order( $order_id, $posted_data, $order ) {
        // Get a public instance to process the tokens.
        $public = new POG_Public( $this->token_handler );
        $public->process_token_after_order( $order_id );
    }

    /**
     * Ajax handler for applying a token to the cart.
     *
     * @return void
     */
    public function ajax_apply_token_to_cart() {
        // Check nonce.
        check_ajax_referer( 'pog_public_nonce', 'nonce' );
        
        // Get the token.
        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        
        if ( empty( $token ) ) {
            wp_send_json_error( array( 'message' => __( 'No token provided.', 'proof-of-gift' ) ) );
        }
        
        // Get a public instance to apply the token.
        $public = new POG_Public( $this->token_handler );
        $result = $public->apply_token( $token );
        
        if ( ! $result['success'] ) {
            wp_send_json_error( $result );
        }
        
        wp_send_json_success( $result );
    }

    /**
     * Clean up expired tokens.
     *
     * @return void
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        
        // Delete expired coupon post types.
        $expired_coupons = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_coupon'
                AND p.post_title LIKE %s
                AND pm.meta_key = 'date_expires'
                AND pm.meta_value < %d",
                'pog_%',
                time()
            )
        );
        
        if ( ! empty( $expired_coupons ) ) {
            foreach ( $expired_coupons as $coupon_id ) {
                wp_delete_post( $coupon_id, true );
            }
        }
    }
}