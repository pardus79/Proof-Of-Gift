<?php
/**
 * Plugin Name: Proof Of Gift
 * Plugin URI: https://example.com/proof-of-gift
 * Description: A gift certificate system using cryptographic tokens with optional BTCPay Server integration.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: proof-of-gift
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ProofOfGift
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'POG_VERSION', '1.0.0' );
define( 'POG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'POG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'POG_PLUGIN_FILE', __FILE__ );
define( 'POG_DB_VERSION', '1.0.0' );

// Include the autoloader
require_once POG_PLUGIN_DIR . 'includes/class-pog-autoloader.php';

// Include the utility functions
require_once POG_PLUGIN_DIR . 'includes/class-pog-utils.php';

// Initialize the autoloader
\ProofOfGift\POG_Autoloader::register();

// Initialize the main plugin class
function pog_initialize() {
    $plugin = new \ProofOfGift\POG_Plugin();
    $plugin->initialize();
}
add_action( 'plugins_loaded', 'pog_initialize' );

// Register activation, deactivation, and uninstall hooks
register_activation_hook( __FILE__, array( '\ProofOfGift\POG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\ProofOfGift\POG_Plugin', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( '\ProofOfGift\POG_Plugin', 'uninstall' ) );