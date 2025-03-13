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
        // No longer using this hook - we're only using the specific token field below
        // add_action( 'woocommerce_after_order_notes', array( $this, 'add_token_field' ) );

        // Process token after order is created.
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_token_after_order' ), 10, 3 );

        // Add token to the cart.
        add_action( 'wp_ajax_pog_apply_token_to_cart', array( $this, 'ajax_apply_token_to_cart' ) );
        add_action( 'wp_ajax_nopriv_pog_apply_token_to_cart', array( $this, 'ajax_apply_token_to_cart' ) );
        
        // Remove token from the cart.
        add_action( 'wp_ajax_pog_remove_token_from_cart', array( $this, 'ajax_remove_token_from_cart' ) );
        add_action( 'wp_ajax_nopriv_pog_remove_token_from_cart', array( $this, 'ajax_remove_token_from_cart' ) );
        
        // Refresh exchange rate when "Place Order" button is clicked.
        add_action( 'woocommerce_checkout_process', array( $this, 'refresh_exchange_rate_on_checkout' ) );
        
        // Ensure discount is applied at final payment calculation
        add_action( 'woocommerce_checkout_create_order', array( $this, 'sync_order_discounts' ), 20, 2 );
        
        // CRITICAL: This is the hook that actually applies fees from session
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_stored_token_fees' ), 20 );
        
        // Ensure fees are consistently applied during checkout
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'ensure_token_fees_applied' ), 20 );
        add_action( 'woocommerce_after_calculate_totals', array( $this, 'ensure_token_fees_applied' ), 20 );

        // Add the token field to the cart page before totals
        add_action( 'woocommerce_before_cart_totals', array( $this, 'add_token_field_to_cart' ) );
        
        // For checkout page, add token field after the order review (in right column)
        add_action( 'woocommerce_review_order_after_payment', array( $this, 'add_token_field_to_checkout' ) );
        
        // Custom display for coupons - change label for our token coupons
        add_filter( 'woocommerce_cart_totals_coupon_label', array( $this, 'custom_coupon_label' ), 10, 2 );
        add_filter( 'woocommerce_coupon_message', array( $this, 'custom_coupon_message' ), 10, 3 );
        
        // Don't automatically show our tokens in the coupon display
        add_filter( 'woocommerce_cart_totals_coupon_html', array( $this, 'maybe_hide_token_coupon' ), 10, 3 );
        
        // Modify the default fee display to show the proper format
        add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'format_token_fee_display' ), 10, 2 );
        
        // Show the applied tokens list before the cart totals
        add_action( 'woocommerce_before_cart_totals', array( $this, 'display_applied_tokens' ) );
        
        // For checkout page, show applied tokens after payment methods (in right column)
        add_action( 'woocommerce_review_order_after_payment', array( $this, 'display_applied_tokens' ), 15 );
        
        // Add a hook to directly display change tokens on the thank you page
        // Using a high priority number (50) to ensure this runs after any other hooks
        add_action( 'woocommerce_thankyou', array( $this, 'display_change_token_on_thankyou' ), 50 );

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
        // Get the token name from settings.
        $token_name = POG_Utils::get_token_name_singular();
        ?>
        <div id="gift-token-field" class="pog-token-field">
            <h3><?php echo esc_html( sprintf( __( 'Apply %s', 'proof-of-gift' ), $token_name ) ); ?></h3>
            <p class="form-row form-row-wide">
                <label for="pog-token"><?php echo esc_html( sprintf( __( 'Enter %s code', 'proof-of-gift' ), $token_name ) ); ?></label>
                <input type="text" class="input-text" id="pog-token" name="pog_token" placeholder="<?php echo esc_attr( sprintf( __( 'Enter %s code', 'proof-of-gift' ), $token_name ) ); ?>">
                <button type="button" class="button" id="pog-apply-token"><?php esc_html_e( 'Apply', 'proof-of-gift' ); ?></button>
            </p>
            <div id="pog-token-message"></div>
            <div id="pog-applied-tokens">
                <ul class="pog-token-list"></ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add token field to the cart page.
     *
     * @return void
     */
    public function add_token_field_to_cart() {
        // Get the token name from settings.
        $token_name = POG_Utils::get_token_name_singular();
        ?>
        <div class="pog-token-field">
            <h4><?php echo esc_html( sprintf( __( 'Apply %s', 'proof-of-gift' ), $token_name ) ); ?></h4>
            <div class="pog-token-input">
                <input type="text" id="pog-token-cart" name="pog_token" placeholder="<?php echo esc_attr( sprintf( __( 'Enter %s code', 'proof-of-gift' ), $token_name ) ); ?>">
                <button type="button" class="button" id="pog-apply-token-cart"><?php esc_html_e( 'Apply', 'proof-of-gift' ); ?></button>
            </div>
            <div id="pog-token-message-cart"></div>
        </div>
        <?php
    }
    
    /**
     * Add token field to the checkout page.
     *
     * @return void
     */
    public function add_token_field_to_checkout() {
        // Get the token name from settings.
        $token_name = POG_Utils::get_token_name_singular();
        ?>
        <div class="pog-token-field pog-token-field-checkout">
            <h4><?php echo esc_html( sprintf( __( 'Apply %s', 'proof-of-gift' ), $token_name ) ); ?></h4>
            <div class="pog-token-input">
                <input type="text" id="pog-token-checkout" name="pog_token" placeholder="<?php echo esc_attr( sprintf( __( 'Enter %s code', 'proof-of-gift' ), $token_name ) ); ?>">
                <button type="button" class="button" id="pog-apply-token-checkout"><?php esc_html_e( 'Apply', 'proof-of-gift' ); ?></button>
            </div>
            <div id="pog-token-message-checkout"></div>
        </div>
        <?php
    }
    
    /**
     * Display applied tokens in a standardized format.
     *
     * @return void
     */
    public function display_applied_tokens() {
        // Get all applied token fees
        $token_fees = WC()->session->get('pog_token_fees', array());
        
        // Also check for direct satoshi tokens
        $direct_tokens = WC()->session->get('pog_tokens', array());
        
        if (empty($token_fees) && empty($direct_tokens)) {
            return;
        }
        
        // Get the operational mode for formatting
        $mode = $this->token_handler->get_operational_mode();
        
        // Track if we have any tokens to display
        $has_tokens = false;
        
        // Start the container
        ?>
        <div class="pog-applied-tokens-container">
            <h3><?php esc_html_e('Applied Tokens', 'proof-of-gift'); ?></h3>
            <div class="pog-applied-tokens-list">
        <?php
        // Display all token fees
        foreach ($token_fees as $fee_id => $fee_data):
            $has_tokens = true;
            $amount_display = $this->get_token_amount_display($fee_data, $mode);
        ?>
        <div class="pog-applied-token">
            <div class="token-info">
                <span class="token-name"><?php echo esc_html(\ProofOfGift\POG_Utils::get_token_name_plural()); ?></span><br>
                <span class="token-amount"><?php echo esc_html($amount_display); ?></span>
            </div>
            <a href="#" class="pog-remove-token" data-token="<?php echo esc_attr($fee_data['token_code']); ?>"><?php esc_html_e( 'Remove', 'proof-of-gift' ); ?></a>
        </div>
        <?php endforeach; ?>
        
        <?php 
        // Display direct satoshi tokens
        foreach ($direct_tokens as $token): 
            $verification = $this->token_handler->verify_token($token);
            if (!$verification || !$verification['valid']) {
                continue;
            }
            
            $has_tokens = true;
            $token_code = 'direct_' . substr(md5($token), 0, 10);
            $amount_display = $verification['amount'] . ' Sats';
        ?>
        <div class="pog-applied-token">
            <div class="token-info">
                <span class="token-name"><?php echo esc_html(\ProofOfGift\POG_Utils::get_token_name_plural()); ?></span><br>
                <span class="token-amount"><?php echo esc_html($amount_display); ?></span>
            </div>
            <a href="#" class="pog-remove-token" data-token="<?php echo esc_attr($token_code); ?>"><?php esc_html_e( 'Remove', 'proof-of-gift' ); ?></a>
        </div>
        <?php endforeach; ?>
        
        <?php
        // Close the container if we displayed any tokens
        if ($has_tokens) :
        ?>
            </div>
        </div>
        <?php
        endif;
        
        // Add notification for negative balance on cart/checkout page
        $cart_total = WC()->cart->get_total('edit');
        $is_checkout = function_exists('is_checkout') && is_checkout();
        $will_have_change = WC()->session->get('pog_will_have_change', false);
        $mode = $this->token_handler->get_operational_mode();
        $token_name = \ProofOfGift\POG_Utils::get_token_name_singular();
        
        // Negative cart total, guaranteed to get change
        if ($cart_total <= 0) {
        ?>
        <div class="pog-token-notification">
            <div class="pog-info-message">
                <?php echo sprintf(
                    esc_html__('Your order has a credit balance. You will receive a %s for any overpayment in your order confirmation email and on the order completion page.', 'proof-of-gift'), 
                    $token_name
                ); ?>
            </div>
        </div>
        <?php
        } 
        // Token value exceeds subtotal but shipping/taxes make the total positive
        elseif ($will_have_change && $cart_total > 0) {
        ?>
        <div class="pog-token-notification">
            <div class="pog-info-message">
                <?php echo sprintf(
                    esc_html__('Your token value exceeds the cost of your items. After paying shipping/taxes, you will receive a %s for the remaining balance in your order confirmation.', 'proof-of-gift'),
                    $token_name
                ); ?>
            </div>
        </div>
        <?php
        } 
        // Small positive balance in satoshi conversion mode
        elseif ($is_checkout && 'satoshi_conversion' === $mode && $cart_total > 0 && $cart_total < 1) {
        ?>
        <div class="pog-token-notification">
            <div class="pog-info-message">
                <?php esc_html_e('Final conversion rates will be calculated when you click to pay, determining if payment is required. Any credit balance will be issued as a token in your confirmation email.', 'proof-of-gift'); ?>
            </div>
        </div>
        <?php
        }
    }
    
    /**
     * Format token amount for display based on operational mode
     *
     * @param array $fee_data The token fee data
     * @param string $mode The operational mode
     * @return string Formatted amount
     */
    private function get_token_amount_display($fee_data, $mode) {
        // Always prioritize displaying in the plugin's native currency based on mode
        if ('direct_satoshi' === $mode || 'satoshi_conversion' === $mode) {
            // For satoshi modes, show the original satoshi amount
            if (isset($fee_data['original_amount'])) {
                return $fee_data['original_amount'] . ' Sats';
            }
        } else {
            // For store currency mode
            if (isset($fee_data['amount'])) {
                $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? 
                    get_woocommerce_currency_symbol() : '$';
                return $currency_symbol . number_format($fee_data['amount'], 2);
            }
        }
        
        // Fallback display if we don't have the preferred currency format
        if (isset($fee_data['original_amount'])) {
            return $fee_data['original_amount'] . ' Sats';
        } else if (isset($fee_data['amount'])) {
            $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? 
                get_woocommerce_currency_symbol() : '$';
            return $currency_symbol . number_format($fee_data['amount'], 2);
        }
        
        return '';
    }
    
    /**
     * Calculate the total value of all applied tokens in the plugin's native currency
     *
     * @param string $mode The operational mode
     * @return string Formatted total amount
     */
    private function get_total_tokens_amount($mode) {
        $token_fees = WC()->session->get('pog_token_fees', array());
        $direct_tokens = WC()->session->get('pog_tokens', array());
        $total = 0;
        
        // Calculate token fees total based on mode
        if ('direct_satoshi' === $mode || 'satoshi_conversion' === $mode) {
            // For satoshi modes, sum the original amounts
            foreach ($token_fees as $fee_data) {
                if (isset($fee_data['original_amount'])) {
                    $total += $fee_data['original_amount'];
                }
            }
            
            // Add direct satoshi tokens
            foreach ($direct_tokens as $token) {
                $verification = $this->token_handler->verify_token($token);
                if ($verification && $verification['valid']) {
                    $total += $verification['amount'];
                }
            }
            
            return $total . ' Sats';
        } else {
            // For store currency mode, sum the converted amounts
            foreach ($token_fees as $fee_data) {
                if (isset($fee_data['amount'])) {
                    $total += $fee_data['amount'];
                }
            }
            
            $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? 
                get_woocommerce_currency_symbol() : '$';
            return $currency_symbol . number_format($total, 2);
        }
    }

    /**
     * Get applied tokens.
     *
     * @return array The applied tokens.
     */
    private function get_applied_tokens() {
        $applied_tokens = array();
        
        // Get satoshi tokens from session
        $satoshi_tokens = WC()->session->get('pog_tokens', array());
        
        if (!empty($satoshi_tokens)) {
            foreach ($satoshi_tokens as $token) {
                // Verify the token.
                $verification = $this->token_handler->verify_token($token);
                
                if ($verification && $verification['valid']) {
                    $applied_tokens[] = $token;
                }
            }
        }
        
        // Add tokens from coupons
        $coupons = WC()->cart->get_coupons();
        
        if ($coupons) {
            foreach ($coupons as $code => $coupon) {
                if (strpos($code, 'pog_') === 0) {
                    // Get the token from coupon meta.
                    $token = get_post_meta($coupon->get_id(), '_pog_token', true);
                    
                    if ($token) {
                        $applied_tokens[] = $token;
                    }
                }
            }
        }
        
        return $applied_tokens;
    }

    /**
     * AJAX handle for applying a token to cart.
     *
     * @return void
     */
    public function ajax_apply_token_to_cart() {
        // Verify nonce.
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pog_public_nonce')) {
            wp_send_json_error('Invalid nonce.');
            wp_die();
        }
        
        // Get token.
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        
        // Load Public class to use apply_token
        $public = new POG_Public($this->token_handler);
        
        // Apply token to cart.
        $result = $public->apply_token($token);
        
        // Send response.
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
        
        wp_die();
    }
    
    /**
     * AJAX handler for removing a token from the cart
     *
     * @return void
     */
    public function ajax_remove_token_from_cart() {
        // Verify nonce.
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pog_public_nonce')) {
            wp_send_json_error('Invalid nonce.');
            wp_die();
        }
        
        // Get token code to remove
        $token_code = isset($_POST['token_code']) ? sanitize_text_field(wp_unslash($_POST['token_code'])) : '';
        
        if (empty($token_code)) {
            wp_send_json_error(array(
                'message' => __('Invalid token code.', 'proof-of-gift')
            ));
            wp_die();
        }
        
        // Check if it's a direct satoshi token (these are stored differently)
        if (strpos($token_code, 'direct_') === 0) {
            $direct_tokens = WC()->session->get('pog_tokens', array());
            $new_tokens = array();
            $removed = false;
            
            // Loop through existing tokens and include all except the target
            foreach ($direct_tokens as $idx => $token) {
                $current_code = 'direct_' . substr(md5($token), 0, 10);
                if ($current_code !== $token_code) {
                    $new_tokens[] = $token;
                } else {
                    $removed = true;
                }
            }
            
            // Update session
            WC()->session->set('pog_tokens', $new_tokens);
            
            if ($removed) {
                // Force cart recalculation
                WC()->cart->calculate_totals();
                
                wp_send_json_success(array(
                    'message' => __('Token removed successfully.', 'proof-of-gift'),
                    'token_code' => $token_code
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Token not found.', 'proof-of-gift')
                ));
            }
            
            wp_die();
        }
        
        // Standard token removal - mark removed in session and force recalculation
        $token_fees = WC()->session->get('pog_token_fees', array());
        $removed_fees = WC()->session->get('pog_removed_fees', array());
        $found = false;
        
        // Check if the token exists in our session
        foreach ($token_fees as $fee_id => $fee_data) {
            if (isset($fee_data['token_code']) && $fee_data['token_code'] === $token_code) {
                $removed_fees[$fee_id] = true;
                $found = true;
                error_log('Proof Of Gift: Marking fee for removal: ' . $fee_id);
                break;
            }
        }
        
        if ($found) {
            // Update session with removed fees
            WC()->session->set('pog_removed_fees', $removed_fees);
            
            // Force cart recalculation
            WC()->cart->calculate_totals();
            
            wp_send_json_success(array(
                'message' => __('Token removed successfully.', 'proof-of-gift'),
                'token_code' => $token_code
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Token not found.', 'proof-of-gift')
            ));
        }
        
        wp_die();
    }
    
    /**
     * Refresh exchange rate on checkout
     * This ensures we have the most updated rates.
     *
     * @return void
     */
    public function refresh_exchange_rate_on_checkout() {
        $mode = $this->token_handler->get_operational_mode();
        
        // Only refresh if in satoshi conversion mode
        if ('satoshi_conversion' === $mode) {
            // Force a refresh of the exchange rate
            $this->token_handler->get_satoshi_exchange_rate(true);
        }
    }
    
    /**
     * Process token after order is created.
     *
     * @param int      $order_id  The order ID.
     * @param array    $posted_data Posted data.
     * @param \WC_Order $order     The order object.
     * @return void
     */
    public function process_token_after_order($order_id, $posted_data, $order) {
        // Load Public class to process tokens
        $public = new POG_Public($this->token_handler);
        
        // Process tokens for this order
        $public->process_token_after_order($order_id);
    }
    
    /**
     * Format the token fee display in the cart
     * WooCommerce hooks into this with 'woocommerce_cart_totals_fee_html'
     *
     * @param string $fee_html The default fee HTML
     * @param object $fee The fee object
     * @return string Modified fee HTML
     */
    public function format_token_fee_display($fee_html, $fee) {
        // Check if this is our token fee
        $fee_name = \ProofOfGift\POG_Utils::get_token_name_plural();
        
        if ($fee->name === $fee_name) {
            // Get the operational mode
            $mode = $this->token_handler->get_operational_mode();
            
            // Get the total tokens amount in the preferred format
            $amount = $this->get_total_tokens_amount($mode);
            
            // Style negative amounts using WooCommerce price display style
            if ($fee->amount < 0) {
                // Negative fee is a discount, style it with dash
                $fee_html = '<span class="woocommerce-Price-amount amount">' . $amount . '</span>';
            }
        }
        
        return $fee_html;
    }
    
    /**
     * Custom label for our coupon
     *
     * @param string $label The default coupon label
     * @param object $coupon The coupon object
     * @return string The modified label
     */
    public function custom_coupon_label($label, $coupon) {
        $coupon_code = $coupon->get_code();
        
        // Check if this is our token coupon
        if (strpos($coupon_code, 'pog_') === 0) {
            // Set label to our setting value
            $label = \ProofOfGift\POG_Utils::get_token_name_plural();
        }
        
        return $label;
    }
    
    /**
     * Modify the coupon applied message
     *
     * @param string $msg The success message
     * @param string $msg_code The message code
     * @param object $coupon The coupon object
     * @return string The modified message
     */
    public function custom_coupon_message($msg, $msg_code, $coupon) {
        $coupon_code = $coupon->get_code();
        
        // Check if this is our token coupon
        if (strpos($coupon_code, 'pog_') === 0) {
            if ($msg_code === 'coupon_success') {
                $token_name = \ProofOfGift\POG_Utils::get_token_name_singular();
                $msg = sprintf(__('%s applied successfully.', 'proof-of-gift'), $token_name);
            }
        }
        
        return $msg;
    }
    
    /**
     * Maybe hide our token coupons from the default display
     *
     * @param string $coupon_html The coupon HTML
     * @param object $coupon The coupon object
     * @param string $discount_amount_html The discount amount HTML
     * @return string The filtered HTML
     */
    public function maybe_hide_token_coupon($coupon_html, $coupon, $discount_amount_html) {
        $coupon_code = $coupon->get_code();
        
        // Check if this is our token coupon
        if (strpos($coupon_code, 'pog_') === 0) {
            // Hide it - we display our own custom token list
            //$coupon_html = '';
        }
        
        return $coupon_html;
    }
    
    /**
     * Sync orders with token discounts to ensure they're recorded properly
     *
     * @param \WC_Order $order The order object being created
     * @param array $data The data sent to the API
     * @return void
     */
    public function sync_order_discounts($order, $data) {
        // Get any token fees from the session
        $token_fees = WC()->session->get('pog_token_fees', array());
        $cart_tokens = WC()->session->get('pog_cart_tokens', array());
        
        if (empty($token_fees) && empty($cart_tokens)) {
            return;
        }
        
        // Get the operational mode
        $mode = $this->token_handler->get_operational_mode();
        
        // Process any token fees from session
        foreach ($token_fees as $fee_id => $fee_data) {
            if (isset($fee_data['token']) && isset($fee_data['amount'])) {
                error_log('Proof Of Gift: Adding token to order meta: ' . $fee_data['token']);
                
                // Calculate the discount amount
                $amount = $fee_data['amount'];
                
                // Store token information in order meta
                $order->add_meta_data('_pog_applied_token_' . $fee_id, $fee_data['token'], true);
                $order->add_meta_data('_pog_token_amount_' . $fee_id, $amount, true);
                
                if ('satoshi_conversion' === $mode && isset($fee_data['original_amount'])) {
                    $order->add_meta_data('_pog_token_original_amount_' . $fee_id, $fee_data['original_amount'], true);
                }
                
                // Create fee label - use the plural form for consistency
                $token_label = \ProofOfGift\POG_Utils::get_token_name_plural();
                
                // Check if the fee is already in the order
                $fee_found = false;
                foreach ($order->get_fees() as $fee) {
                    if ($fee->get_name() === $token_label) {
                        $fee_found = true;
                        break;
                    }
                }
                
                // Add the fee to the order if not already present
                if (!$fee_found) {
                    $item_fee = new \WC_Order_Item_Fee();
                    $item_fee->set_name($token_label);
                    $item_fee->set_amount(-$amount);
                    $item_fee->set_total(-$amount);
                    $item_fee->set_tax_status('none');
                    $order->add_item($item_fee);
                    error_log('Proof Of Gift: Added fee to order: ' . $token_label . ', amount: -' . $amount);
                }
            }
        }
        
        // For backward compatibility, also process old cart_tokens
        foreach ($cart_tokens as $token_code => $token_data) {
            if (isset($token_data['token'])) {
                // Check if we've already processed this token via token_fees
                $already_processed = false;
                foreach ($token_fees as $fee_data) {
                    if (isset($fee_data['token_code']) && $fee_data['token_code'] === $token_code) {
                        $already_processed = true;
                        break;
                    }
                }
                
                if (!$already_processed) {
                    error_log('Proof Of Gift: Processing legacy token: ' . $token_code);
                    
                    // Calculate the discount amount
                    $amount = $token_data['amount'];
                    
                    // Store token information in order meta
                    $order->add_meta_data('_pog_applied_token_' . $token_code, $token_data['token'], true);
                    $order->add_meta_data('_pog_token_amount_' . $token_code, $amount, true);
                    
                    if ('satoshi_conversion' === $mode && isset($token_data['original_amount'])) {
                        $order->add_meta_data('_pog_token_original_amount_' . $token_code, $token_data['original_amount'], true);
                    }
                    
                    // Create fee label - use the plural form for consistency
                    $token_label = \ProofOfGift\POG_Utils::get_token_name_plural();
                    
                    // Check if the fee is already in the order
                    $fee_found = false;
                    foreach ($order->get_fees() as $fee) {
                        if ($fee->get_name() === $token_label) {
                            $fee_found = true;
                            break;
                        }
                    }
                    
                    // Add the fee to the order if not already present
                    if (!$fee_found) {
                        $item_fee = new \WC_Order_Item_Fee();
                        $item_fee->set_name($token_label);
                        $item_fee->set_amount(-$amount);
                        $item_fee->set_total(-$amount);
                        $item_fee->set_tax_status('none');
                        $order->add_item($item_fee);
                        error_log('Proof Of Gift: Added legacy fee to order: ' . $token_label . ', amount: -' . $amount);
                    }
                }
            }
        }
        
        // Recalculate order totals to ensure discounts are applied
        $order->calculate_totals();
        error_log('Proof Of Gift: Order totals recalculated. Final total: ' . $order->get_total());
    }
    
    /**
     * Ensure token fees are properly applied before payment.
     * This is a safety check to make sure the discounts are included in the final total.
     *
     * @return void
     */
    public function ensure_token_fees_applied() {
        // Use a static flag to prevent infinite recursion
        static $is_running = false;
        
        if ($is_running) {
            return;
        }
        
        $is_running = true;
        error_log('Proof Of Gift: Running ensure_token_fees_applied');
        
        try {
            // Get token fees from session
            $token_fees = WC()->session->get('pog_token_fees', array());
            
            if (empty($token_fees)) {
                error_log('Proof Of Gift: No token fees in session, nothing to apply');
                $is_running = false;
                return;
            }
            
            error_log('Proof Of Gift: Found ' . count($token_fees) . ' token fees in session');
            
            // Get current fees 
            $current_fees = array();
            if (isset(WC()->cart->fees_api) && method_exists(WC()->cart->fees_api, 'get_fees')) {
                $current_fees = WC()->cart->fees_api->get_fees();
            }
            
            $current_fee_names = array();
            foreach ($current_fees as $fee) {
                $current_fee_names[] = $fee->name;
            }
            
            // Calculate expected fee name
            $expected_fee_name = \ProofOfGift\POG_Utils::get_token_name_plural();
            
            // Check if our fee is already applied
            if (!in_array($expected_fee_name, $current_fee_names)) {
                error_log('Proof Of Gift: Token fee not found in current fees, triggering recalculation');
                WC()->cart->calculate_totals();
            } else {
                error_log('Proof Of Gift: Token fees already applied');
            }
        } catch (\Exception $e) {
            error_log('Proof Of Gift: Exception in ensure_token_fees_applied: ' . $e->getMessage());
        }
        
        $is_running = false;
    }
    
    /**
     * Apply stored token fees to the cart during cart calculation
     * This is the key function that ensures fees are applied on every page load
     *
     * @param WC_Cart $cart The cart object
     * @return void
     */
    public function apply_stored_token_fees($cart) {
        // Get token fees from session
        $token_fees = WC()->session->get('pog_token_fees', array());
        
        if (empty($token_fees)) {
            return;
        }
        
        // Get any removed fees
        $removed_fees = WC()->session->get('pog_removed_fees', array());
        
        error_log('Proof Of Gift: Applying ' . count($token_fees) . ' stored token fees to cart');
        
        // Calculate the total of all token fees
        $total_fee_amount = 0;
        $active_fees = array();
        $token_handler = new \ProofOfGift\POG_Token_Handler(new \ProofOfGift\POG_Crypto());
        
        // First determine the total amount and which fees are actually active
        foreach ($token_fees as $fee_id => $fee_data) {
            if (isset($fee_data['amount']) && isset($fee_data['token'])) {
                // Verify token hasn't been redeemed before applying
                $token = $fee_data['token'];
                
                // Skip if this fee has been marked for removal
                if (isset($removed_fees[$fee_id]) && $removed_fees[$fee_id]) {
                    error_log('Proof Of Gift: Skipping removed fee: ' . $fee_id);
                    continue;
                }
                
                // Check if token has been redeemed
                if ($token_handler->is_token_redeemed($token)) {
                    error_log('Proof Of Gift: Token already redeemed, skipping: ' . $fee_id);
                    // Add to removed fees so it's not applied in future
                    $removed_fees[$fee_id] = true;
                    continue;
                }
                
                // Use the standard token name for all fees
                $token_label = \ProofOfGift\POG_Utils::get_token_name_plural();
                
                // Store the label in the fee data
                $token_fees[$fee_id]['label'] = $token_label;
                
                // Add this fee to the total
                $total_fee_amount += $fee_data['amount'];
                
                // Keep track of active fees
                $active_fees[$fee_id] = $fee_data;
                
                error_log('Proof Of Gift: Adding fee to total: ' . $fee_id . ', amount: ' . $fee_data['amount']);
            }
        }
        
        // Only apply a fee if we have an amount
        if ($total_fee_amount > 0) {
            // Check if token total exceeds cart subtotal 
            $cart_subtotal = $cart->get_subtotal();
            $negative_total = ($total_fee_amount > $cart_subtotal);
            
            // Apply a single fee with the total amount
            $token_label = \ProofOfGift\POG_Utils::get_token_name_plural();
            $cart->add_fee($token_label, -$total_fee_amount, false);
            error_log('Proof Of Gift: Applied combined token fee: ' . $token_label . ', total amount: -' . $total_fee_amount . 
                      ($negative_total ? ' (exceeds cart subtotal of ' . $cart_subtotal . ')' : ''));
            
            // If we're applying more than the subtotal, make a note that there will be change
            if ($negative_total) {
                WC()->session->set('pog_will_have_change', true);
            } else {
                WC()->session->set('pog_will_have_change', false);
            }
        }
        
        // Update the session with the refined list of fees (only active ones)
        WC()->session->set('pog_token_fees', $active_fees);
        
        // Save the updated removed fees list
        WC()->session->set('pog_removed_fees', $removed_fees);
        
        error_log('Proof Of Gift: All stored token fees applied. Cart total now: ' . $cart->get_total());
    }
    
    /**
     * Display change token on the thank you page.
     *
     * @param int $order_id The order ID.
     * @return void
     */
    public function display_change_token_on_thankyou($order_id) {
        if (!$order_id) {
            return;
        }
        
        // Get the change token from order meta
        $change_token = get_post_meta($order_id, '_pog_change_token', true);
        $change_amount = get_post_meta($order_id, '_pog_change_amount', true);
        
        if (!$change_token || !$change_amount) {
            return;
        }
        
        $mode = $this->token_handler->get_operational_mode();
        $stored_mode = get_post_meta($order_id, '_pog_change_currency_mode', true);
        if (!empty($stored_mode)) {
            $mode = $stored_mode;
        }
        
        // Determine the proper currency/unit
        $currency = '';
        if ('store_currency' === $mode) {
            $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
        } elseif ('satoshi_conversion' === $mode || 'direct_satoshi' === $mode) {
            $currency = 'satoshis';
        }
        
        ?>
        <div class="woocommerce-order-overview woocommerce-change-token">
            <h2><?php esc_html_e('Change Token', 'proof-of-gift'); ?></h2>
            <p><?php esc_html_e('You have received credit for the excess token value.', 'proof-of-gift'); ?></p>
            <p>
                <strong><?php esc_html_e('Amount:', 'proof-of-gift'); ?></strong> 
                <?php echo esc_html($change_amount); ?> <?php echo esc_html($currency); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Token:', 'proof-of-gift'); ?></strong><br>
                <code class="pog-token-code"><?php echo esc_html($change_token); ?></code>
            </p>
        </div>
        <?php
    }

    /**
     * Clean up expired tokens.
     *
     * @return void
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        
        // Delete expired coupon post types in a single query for better performance
        $affected = $wpdb->query(
            $wpdb->prepare(
                "DELETE p, pm
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_coupon'
                AND p.post_title LIKE %s
                AND pm.meta_key = 'date_expires'
                AND pm.meta_value < %d",
                'pog_%',
                time()
            )
        );
        
        if ($affected && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Proof Of Gift: Cleaned up ' . intval($affected/2) . ' expired token coupons');
        }
    }
}