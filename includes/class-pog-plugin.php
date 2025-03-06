<?php
/**
 * Main plugin class.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_Plugin
 *
 * Main plugin class that initializes the plugin.
 */
class POG_Plugin {

    /**
     * The instance of the crypto handler.
     *
     * @var POG_Crypto
     */
    private $crypto;

    /**
     * The instance of the token handler.
     *
     * @var POG_Token_Handler
     */
    private $token_handler;

    /**
     * The instance of the admin handler.
     *
     * @var POG_Admin
     */
    private $admin;

    /**
     * Initialize the plugin.
     *
     * @return void
     */
    public function initialize() {
        // Initialize crypto service.
        $this->crypto = new POG_Crypto();
        
        // Initialize token handler.
        $this->token_handler = new POG_Token_Handler( $this->crypto );
        
        // Initialize admin interface if in admin area.
        if ( is_admin() ) {
            $this->admin = new POG_Admin( $this->crypto, $this->token_handler );
        }
        
        // Initialize public hooks.
        $this->init_public_hooks();
        
        // Check and initialize WooCommerce integration if available.
        if ( $this->is_woocommerce_active() ) {
            $this->init_woocommerce_integration();
        }
        
        // Check and initialize BTCPay Server integration if available.
        if ( $this->is_btcpay_active() ) {
            $this->init_btcpay_integration();
        }
        
        // Register shortcodes.
        $this->register_shortcodes();
        
        // Maybe add rewrite rules.
        $this->maybe_add_rewrite_rules();
    }

    /**
     * Initialize public hooks.
     *
     * @return void
     */
    private function init_public_hooks() {
        $public = new POG_Public( $this->token_handler );
        
        // Register scripts and styles.
        add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_styles' ) );
    }

    /**
     * Initialize WooCommerce integration.
     *
     * @return void
     */
    private function init_woocommerce_integration() {
        $wc_integration = new POG_WooCommerce_Integration( $this->token_handler );
        $wc_integration->initialize();
    }

    /**
     * Initialize BTCPay Server integration.
     *
     * @return void
     */
    private function init_btcpay_integration() {
        $btcpay_integration = new POG_BTCPay_Integration( $this->token_handler );
        $btcpay_integration->initialize();
    }

    /**
     * Register shortcodes.
     *
     * @return void
     */
    private function register_shortcodes() {
        $shortcodes = new POG_Shortcodes( $this->token_handler );
        $shortcodes->register();
    }

    /**
     * Maybe add rewrite rules.
     *
     * @return void
     */
    private function maybe_add_rewrite_rules() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
    }

    /**
     * Add rewrite rules.
     *
     * @return void
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            'pog-verify/([^/]+)/?$',
            'index.php?pog_token=$matches[1]',
            'top'
        );
        
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_token_verification' ) );
    }

    /**
     * Add query vars.
     *
     * @param array $vars The query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'pog_token';
        return $vars;
    }

    /**
     * Handle token verification.
     *
     * @return void
     */
    public function handle_token_verification() {
        $token = get_query_var( 'pog_token' );
        
        if ( ! empty( $token ) ) {
            $verification = $this->token_handler->verify_token( $token, false );
            
            // Include the verification template.
            include POG_PLUGIN_DIR . 'templates/token-verification.php';
            exit;
        }
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return in_array(
            'woocommerce/woocommerce.php',
            apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
            true
        );
    }

    /**
     * Check if BTCPay Server plugin is active.
     *
     * @return bool
     */
    private function is_btcpay_active() {
        return in_array(
            'btcpay-greenfield-for-woocommerce/btcpay-greenfield-for-woocommerce.php',
            apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
            true
        );
    }

    /**
     * Plugin activation hook.
     *
     * @return void
     */
    public static function activate() {
        // Create database tables.
        self::create_tables();
        
        // Generate keys if they don't exist.
        $crypto = new POG_Crypto();
        $crypto->maybe_generate_keys();
        
        // Add capability to administrator.
        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->add_cap( 'manage_proof_of_gift' );
        }
        
        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook.
     *
     * @return void
     */
    public static function deactivate() {
        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall hook.
     *
     * @return void
     */
    public static function uninstall() {
        // If uninstall not called from WordPress, exit.
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            exit;
        }
        
        // Delete options.
        delete_option( 'pog_private_key' );
        delete_option( 'pog_public_key' );
        delete_option( 'pog_settings' );
        delete_option( 'pog_db_version' );
        
        // Remove capability from administrator.
        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->remove_cap( 'manage_proof_of_gift' );
        }
        
        // Drop tables.
        self::drop_tables();
    }

    /**
     * Create database tables.
     *
     * @return void
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Token redemptions table.
        $table_name = $wpdb->prefix . 'pog_redemptions';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token varchar(255) NOT NULL,
            amount int(11) NOT NULL,
            redeemed_at datetime NOT NULL,
            order_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        update_option( 'pog_db_version', POG_DB_VERSION );
    }

    /**
     * Drop database tables.
     *
     * @return void
     */
    private static function drop_tables() {
        global $wpdb;
        
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pog_redemptions" );
    }
}