<?php
/**
 * Utility functions for the Proof of Gift plugin.
 *
 * @package    ProofOfGift
 * @subpackage ProofOfGift/includes
 */

namespace ProofOfGift;

/**
 * Utility functions for the Proof of Gift plugin.
 *
 * This class defines utility functions that are used across
 * multiple classes in the plugin.
 *
 * @package    ProofOfGift
 * @subpackage ProofOfGift/includes
 */
class POG_Utils {

	/**
	 * Get token name in singular form.
	 * 
	 * @return string Token name in singular form.
	 */
	public static function get_token_name_singular() {
		$settings = get_option( 'pog_settings', array() );
		return isset( $settings['token_name_singular'] ) && !empty( $settings['token_name_singular'] ) 
			? $settings['token_name_singular'] 
			: __( 'Gift Token', 'proof-of-gift' );
	}
	
	/**
	 * Get token name in plural form.
	 * 
	 * @return string Token name in plural form.
	 */
	public static function get_token_name_plural() {
		$settings = get_option( 'pog_settings', array() );
		return isset( $settings['token_name_plural'] ) && !empty( $settings['token_name_plural'] ) 
			? $settings['token_name_plural'] 
			: __( 'Gift Tokens', 'proof-of-gift' );
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return boolean True if WooCommerce is active, false otherwise.
	 */
	public static function is_woocommerce_active() {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
	}

	/**
	 * Check if BTCPay Server is active.
	 *
	 * @return boolean True if BTCPay Server is active, false otherwise.
	 */
	public static function is_btcpay_active() {
		$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
		
		// Check for known BTCPay plugins - add more patterns as needed
		$btcpay_patterns = array(
			'btcpay',
			'btcpayserver',
			'btc-pay',
			'btc-payserver'
		);
		
		foreach ($active_plugins as $plugin) {
			foreach ($btcpay_patterns as $pattern) {
				if (stripos($plugin, $pattern) !== false) {
					return true;
				}
			}
		}
		
		// For testing/development, you can force enable BTCPay integration with this filter
		if (apply_filters('pog_force_btcpay_active', false)) {
			return true;
		}
		
		return false;
	}

	/**
	 * Add change token to order confirmation email.
	 *
	 * @param \WC_Order $order The order object.
	 * @param bool      $sent_to_admin Whether the email is sent to admin.
	 * @param bool      $plain_text Whether the email is plain text.
	 * @param string    $mode The operational mode (store_currency, satoshi_conversion, direct_satoshi).
	 * @return void
	 */
	public static function add_change_token_to_email( $order, $sent_to_admin, $plain_text, $mode = 'store_currency' ) {
		if ( $sent_to_admin ) {
			return;
		}

		$change_token = get_post_meta( $order->get_id(), '_pog_change_token', true );
		$change_amount = get_post_meta( $order->get_id(), '_pog_change_amount', true );

		if ( ! $change_token || ! $change_amount ) {
			return;
		}

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