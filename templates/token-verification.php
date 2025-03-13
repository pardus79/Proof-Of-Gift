<?php
/**
 * Template for token verification
 *
 * @package ProofOfGift
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get the verification data from the variable.
// $verification and $token are set in POG_Plugin::handle_token_verification()
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <title><?php esc_html_e( 'Token Verification', 'proof-of-gift' ); ?> - <?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
    <style>
        /* Basic styles for the token verification page */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #444;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .pog-verification-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 40px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .pog-verification-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .pog-verification-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .pog-token-details {
            margin-top: 30px;
        }
        .pog-token-row {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid #f5f5f5;
            padding-bottom: 15px;
        }
        .pog-token-label {
            width: 120px;
            font-weight: 600;
        }
        .pog-token-value {
            flex: 1;
        }
        .pog-token-value code {
            background-color: #f5f5f5;
            padding: 3px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        .pog-status {
            margin-top: 30px;
            padding: 15px;
            border-radius: 5px;
        }
        .pog-status-valid {
            background-color: #e9f9e9;
            border: 1px solid #c3e6c3;
            color: #2e7d32;
        }
        .pog-status-invalid {
            background-color: #fbe9e7;
            border: 1px solid #ffccbc;
            color: #c62828;
        }
        .pog-status-redeemed {
            background-color: #fff8e1;
            border: 1px solid #ffecb3;
            color: #f57f17;
        }
        .pog-actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        .pog-action-button {
            display: inline-block;
            background-color: #0073aa;
            color: #fff;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 3px;
            font-weight: 500;
        }
        .pog-action-button:hover {
            background-color: #005d8c;
            color: #fff;
        }
        .pog-action-secondary {
            background-color: #f5f5f5;
            color: #333;
        }
        .pog-action-secondary:hover {
            background-color: #e5e5e5;
            color: #333;
        }
    </style>
</head>
<body <?php body_class(); ?>>
    <div class="pog-verification-container">
        <div class="pog-verification-header">
            <?php $token_name_plural = \ProofOfGift\POG_Utils::get_token_name_plural(); ?>
            <h1><?php echo sprintf( esc_html__( '%s Verification', 'proof-of-gift' ), $token_name_plural ); ?></h1>
            <p><?php echo sprintf( esc_html__( 'Verify the details of your %s below.', 'proof-of-gift' ), strtolower($token_name_plural) ); ?></p>
        </div>
        
        <?php if ( false === $verification ) : ?>
            
            <div class="pog-status pog-status-invalid">
                <strong><?php echo sprintf( esc_html__( 'Invalid %s', 'proof-of-gift' ), $token_name_plural ); ?></strong>
                <p><?php echo sprintf( esc_html__( 'The %s you provided are not valid.', 'proof-of-gift' ), strtolower($token_name_plural) ); ?></p>
            </div>
            
            <div class="pog-token-details">
                <div class="pog-token-row">
                    <div class="pog-token-label"><?php echo esc_html( $token_name_plural ); ?></div>
                    <div class="pog-token-value"><code><?php echo esc_html( $token ); ?></code></div>
                </div>
            </div>
            
        <?php else : ?>
            
            <?php
            // Check if the token has been redeemed.
            $redeemed = isset( $verification['redeemed'] ) && $verification['redeemed'];
            
            // Determine the status class.
            $status_class = $verification['valid'] ? 'pog-status-valid' : ( $redeemed ? 'pog-status-redeemed' : 'pog-status-invalid' );
            ?>
            
            <div class="pog-status <?php echo esc_attr( $status_class ); ?>">
                <strong>
                    <?php
                    if ( $verification['valid'] ) {
                        if (isset($_GET['pog_apply']) && $_GET['pog_apply'] == '1') {
                            echo sprintf( esc_html__( '%s Applied!', 'proof-of-gift' ), $token_name_plural );
                        } else {
                            echo sprintf( esc_html__( 'Valid %s', 'proof-of-gift' ), $token_name_plural );
                        }
                    } elseif ( $redeemed ) {
                        echo sprintf( esc_html__( 'Redeemed %s', 'proof-of-gift' ), $token_name_plural );
                    } else {
                        echo sprintf( esc_html__( 'Invalid %s', 'proof-of-gift' ), $token_name_plural );
                    }
                    ?>
                </strong>
                <p>
                    <?php
                    if ( $verification['valid'] ) {
                        if (isset($_GET['pog_apply']) && $_GET['pog_apply'] == '1') {
                            echo sprintf(
                                esc_html__( 'These %1$s for %2$s have been successfully applied to your cart! You can continue shopping or proceed to checkout when ready.', 'proof-of-gift' ),
                                strtolower($token_name_plural),
                                esc_html( $currency . $amount )
                            );
                        } else {
                            echo sprintf(
                                esc_html__( 'These %1$s are valid for %2$s and can be used for payment. You can apply them during checkout.', 'proof-of-gift' ),
                                strtolower($token_name_plural),
                                esc_html( $currency . $amount )
                            );
                        }
                    } elseif ( $redeemed ) {
                        echo sprintf(
                            esc_html__( 'These %s have already been redeemed and cannot be used again.', 'proof-of-gift' ),
                            strtolower($token_name_plural)
                        );
                    } else {
                        echo sprintf(
                            esc_html__( 'These %s are not valid and cannot be used for payment.', 'proof-of-gift' ),
                            strtolower($token_name_plural)
                        );
                    }
                    ?>
                </p>
            </div>
            
            <div class="pog-token-details">
                <div class="pog-token-row">
                    <div class="pog-token-label"><?php echo esc_html( $token_name_plural ); ?></div>
                    <div class="pog-token-value"><code><?php echo esc_html( $token ); ?></code></div>
                </div>
                
                <div class="pog-token-row">
                    <div class="pog-token-label"><?php esc_html_e( 'Amount', 'proof-of-gift' ); ?></div>
                    <div class="pog-token-value">
                        <?php
                        echo esc_html( $currency . $amount );
                        ?>
                    </div>
                </div>
                
                <?php if ( $redeemed ) : ?>
                    <?php
                    // Get redemption details.
                    $redemption_data = $GLOBALS['pog_token_handler']->get_redemption_data( $token );
                    
                    if ( $redemption_data ) :
                        // Format the date.
                        $date = date_i18n(
                            get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                            strtotime( $redemption_data['redeemed_at'] )
                        );
                        ?>
                        
                        <div class="pog-token-row">
                            <div class="pog-token-label"><?php esc_html_e( 'Redeemed On', 'proof-of-gift' ); ?></div>
                            <div class="pog-token-value"><?php echo esc_html( $date ); ?></div>
                        </div>
                        
                        <?php if ( ! empty( $redemption_data['order_id'] ) ) : ?>
                            <div class="pog-token-row">
                                <div class="pog-token-label"><?php esc_html_e( 'Order', 'proof-of-gift' ); ?></div>
                                <div class="pog-token-value">
                                    <?php
                                    if ( function_exists( 'wc_get_order' ) ) {
                                        $order = wc_get_order( $redemption_data['order_id'] );
                                        
                                        if ( $order ) {
                                            echo '#' . esc_html( $order->get_order_number() );
                                        } else {
                                            echo esc_html( $redemption_data['order_id'] );
                                        }
                                    } else {
                                        echo esc_html( $redemption_data['order_id'] );
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="pog-actions">
                <?php if ( $verification['valid'] ) : ?>
                    <?php
                    // Check if we have the shop page
                    $shop_page_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '';
                    if (empty($shop_page_url)) {
                        $shop_page_url = home_url('/shop/');
                    }
                    ?>
                    <a href="<?php echo esc_url($shop_page_url); ?>" class="pog-action-button">
                        <?php esc_html_e('Continue Shopping', 'proof-of-gift'); ?>
                    </a>
                    
                    <?php if (function_exists('wc_get_cart_url')) : ?>
                        <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="pog-action-button pog-action-secondary">
                            <?php esc_html_e('View Cart', 'proof-of-gift'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (function_exists('wc_get_checkout_url')) : ?>
                        <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="pog-action-button pog-action-secondary">
                            <?php esc_html_e('Checkout', 'proof-of-gift'); ?>
                        </a>
                    <?php endif; ?>
                <?php else : ?>
                    <?php
                    // Check if we have the shop page
                    $shop_page_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '';
                    if (empty($shop_page_url)) {
                        $shop_page_url = home_url('/shop/');
                    }
                    ?>
                    <a href="<?php echo esc_url($shop_page_url); ?>" class="pog-action-button">
                        <?php esc_html_e('Continue Shopping', 'proof-of-gift'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url(home_url()); ?>" class="pog-action-button pog-action-secondary">
                        <?php esc_html_e('Return to Home', 'proof-of-gift'); ?>
                    </a>
                <?php endif; ?>
            </div>
            
        <?php endif; ?>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>