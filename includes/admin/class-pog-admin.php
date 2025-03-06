<?php
/**
 * Admin functionality for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_Admin
 *
 * Handles admin interface and functionality.
 */
class POG_Admin {

    /**
     * The crypto service.
     *
     * @var POG_Crypto
     */
    private $crypto;

    /**
     * The token handler.
     *
     * @var POG_Token_Handler
     */
    private $token_handler;

    /**
     * Constructor.
     *
     * @param POG_Crypto       $crypto The crypto service.
     * @param POG_Token_Handler $token_handler The token handler.
     */
    public function __construct( $crypto, $token_handler ) {
        $this->crypto = $crypto;
        $this->token_handler = $token_handler;
        
        // Initialize admin hooks.
        $this->init_hooks();
    }

    /**
     * Initialize admin hooks.
     *
     * @return void
     */
    private function init_hooks() {
        // Add admin menu.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // Register settings.
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Admin scripts and styles.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        
        // Ajax handlers.
        add_action( 'wp_ajax_pog_generate_token', array( $this, 'ajax_generate_token' ) );
        add_action( 'wp_ajax_pog_generate_tokens_batch', array( $this, 'ajax_generate_tokens_batch' ) );
        add_action( 'wp_ajax_pog_export_tokens_csv', array( $this, 'ajax_export_tokens_csv' ) );
        add_action( 'wp_ajax_pog_export_tokens_pdf', array( $this, 'ajax_export_tokens_pdf' ) );
        add_action( 'wp_ajax_pog_download_pdf', array( $this, 'handle_pdf_download' ) );
        add_action( 'wp_ajax_pog_verify_token', array( $this, 'ajax_verify_token' ) );
        add_action( 'wp_ajax_pog_btcpay_test_connection', array( $this, 'ajax_btcpay_test_connection' ) );
    }

    /**
     * Add admin menu.
     *
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Proof Of Gift', 'proof-of-gift' ),
            __( 'Proof Of Gift', 'proof-of-gift' ),
            'manage_proof_of_gift',
            'proof-of-gift',
            array( $this, 'render_main_page' ),
            'dashicons-tickets-alt',
            26
        );
        
        add_submenu_page(
            'proof-of-gift',
            __( 'Generate Tokens', 'proof-of-gift' ),
            __( 'Generate Tokens', 'proof-of-gift' ),
            'manage_proof_of_gift',
            'proof-of-gift',
            array( $this, 'render_main_page' )
        );
        
        add_submenu_page(
            'proof-of-gift',
            __( 'Settings', 'proof-of-gift' ),
            __( 'Settings', 'proof-of-gift' ),
            'manage_proof_of_gift',
            'proof-of-gift-settings',
            array( $this, 'render_settings_page' )
        );
        
        add_submenu_page(
            'proof-of-gift',
            __( 'Verify Token', 'proof-of-gift' ),
            __( 'Verify Token', 'proof-of-gift' ),
            'manage_proof_of_gift',
            'proof-of-gift-verify',
            array( $this, 'render_verify_page' )
        );
        
        add_submenu_page(
            'proof-of-gift',
            __( 'Help', 'proof-of-gift' ),
            __( 'Help', 'proof-of-gift' ),
            'manage_proof_of_gift',
            'proof-of-gift-help',
            array( $this, 'render_help_page' )
        );
    }

    /**
     * Register settings.
     *
     * @return void
     */
    public function register_settings() {
        register_setting( 'pog_settings_group', 'pog_settings' );
        
        add_settings_section(
            'pog_general_settings',
            __( 'General Settings', 'proof-of-gift' ),
            array( $this, 'render_general_settings_section' ),
            'proof-of-gift-settings'
        );
        
        add_settings_field(
            'pog_operational_mode',
            __( 'Operational Mode', 'proof-of-gift' ),
            array( $this, 'render_operational_mode_field' ),
            'proof-of-gift-settings',
            'pog_general_settings'
        );
        
        add_settings_field(
            'pog_satoshi_exchange_rate',
            __( 'Satoshi Exchange Rate', 'proof-of-gift' ),
            array( $this, 'render_satoshi_exchange_rate_field' ),
            'proof-of-gift-settings',
            'pog_general_settings'
        );
        
        // BTCPay Server settings section (only if WooCommerce and BTCPay Server plugin are active).
        if ( $this->is_btcpay_active() ) {
            add_settings_section(
                'pog_btcpay_settings',
                __( 'BTCPay Server Integration', 'proof-of-gift' ),
                array( $this, 'render_btcpay_settings_section' ),
                'proof-of-gift-settings'
            );
            
            add_settings_field(
                'pog_btcpay_api_key',
                __( 'BTCPay API Key', 'proof-of-gift' ),
                array( $this, 'render_btcpay_api_key_field' ),
                'proof-of-gift-settings',
                'pog_btcpay_settings'
            );
            
            add_settings_field(
                'pog_btcpay_store_id',
                __( 'BTCPay Store ID', 'proof-of-gift' ),
                array( $this, 'render_btcpay_store_id_field' ),
                'proof-of-gift-settings',
                'pog_btcpay_settings'
            );
            
            add_settings_field(
                'pog_btcpay_server_url',
                __( 'BTCPay Server URL', 'proof-of-gift' ),
                array( $this, 'render_btcpay_server_url_field' ),
                'proof-of-gift-settings',
                'pog_btcpay_settings'
            );
            
            add_settings_field(
                'pog_btcpay_test_connection',
                __( 'Test Connection', 'proof-of-gift' ),
                array( $this, 'render_btcpay_test_connection_field' ),
                'proof-of-gift-settings',
                'pog_btcpay_settings'
            );
        }
    }

    /**
     * Render the general settings section.
     *
     * @return void
     */
    public function render_general_settings_section() {
        echo '<p>' . esc_html__( 'Configure the general settings for the Proof Of Gift plugin.', 'proof-of-gift' ) . '</p>';
    }

    /**
     * Render the operational mode field.
     *
     * @return void
     */
    public function render_operational_mode_field() {
        $settings = get_option( 'pog_settings', array() );
        $mode = isset( $settings['operational_mode'] ) ? $settings['operational_mode'] : 'store_currency';
        
        $btcpay_active = $this->is_btcpay_active();
        $wc_active = $this->is_woocommerce_active();
        
        ?>
        <select name="pog_settings[operational_mode]" id="pog_operational_mode">
            <option value="store_currency" <?php selected( $mode, 'store_currency' ); ?>>
                <?php esc_html_e( 'Store Currency Mode', 'proof-of-gift' ); ?>
            </option>
            <option value="satoshi_conversion" <?php selected( $mode, 'satoshi_conversion' ); ?>>
                <?php esc_html_e( 'Satoshi Conversion Mode', 'proof-of-gift' ); ?>
            </option>
            <option value="direct_satoshi" <?php selected( $mode, 'direct_satoshi' ); ?> <?php disabled( ! $btcpay_active ); ?>>
                <?php esc_html_e( 'Direct Satoshi Mode', 'proof-of-gift' ); ?>
            </option>
        </select>
        
        <div class="pog-mode-description store-currency-mode" <?php echo 'store_currency' !== $mode ? 'style="display:none;"' : ''; ?>>
            <p class="description">
                <?php esc_html_e( 'Tokens are denominated in store currency. Applied directly to cart total before payment.', 'proof-of-gift' ); ?>
            </p>
        </div>
        
        <div class="pog-mode-description satoshi-conversion-mode" <?php echo 'satoshi_conversion' !== $mode ? 'style="display:none;"' : ''; ?>>
            <p class="description">
                <?php esc_html_e( 'Tokens are denominated in Satoshis. Converted to store currency at current exchange rate and applied to cart total.', 'proof-of-gift' ); ?>
            </p>
        </div>
        
        <div class="pog-mode-description direct-satoshi-mode" <?php echo 'direct_satoshi' !== $mode ? 'style="display:none;"' : ''; ?>>
            <p class="description">
                <?php esc_html_e( 'Tokens are denominated in Satoshis. Applied directly at BTCPay Server payment screen.', 'proof-of-gift' ); ?>
            </p>
            <?php if ( ! $btcpay_active ) : ?>
                <p class="description error">
                    <?php esc_html_e( 'BTCPay Server plugin is required for this mode. Please install and activate it first.', 'proof-of-gift' ); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <?php if ( ! $wc_active ) : ?>
            <p class="description notice">
                <?php esc_html_e( 'WooCommerce is not active. The plugin will operate in standalone mode with limited functionality.', 'proof-of-gift' ); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the satoshi exchange rate field.
     *
     * @return void
     */
    public function render_satoshi_exchange_rate_field() {
        $settings = get_option( 'pog_settings', array() );
        $exchange_rate = isset( $settings['satoshi_exchange_rate'] ) ? $settings['satoshi_exchange_rate'] : '';
        $last_updated = isset( $settings['satoshi_exchange_rate_updated'] ) ? $settings['satoshi_exchange_rate_updated'] : 0;
        
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        $symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
        
        ?>
        <input type="text" name="pog_settings[satoshi_exchange_rate]" value="<?php echo esc_attr( $exchange_rate ); ?>" class="regular-text" />
        <p class="description">
            <?php
            if ( $last_updated > 0 ) {
                $date_format = get_option( 'date_format' );
                $time_format = get_option( 'time_format' );
                $date = date_i18n( $date_format . ' ' . $time_format, $last_updated );
                printf(
                    /* translators: %1$s: Currency symbol, %2$s: Date and time */
                    esc_html__( 'Current rate: 1 Satoshi = %1$s%3$s (Last updated: %2$s)', 'proof-of-gift' ),
                    esc_html( $symbol ),
                    esc_html( $date ),
                    esc_html( $exchange_rate )
                );
            } else {
                esc_html_e( 'Leave empty to fetch automatically from CoinGecko API.', 'proof-of-gift' );
            }
            ?>
        </p>
        <button type="button" class="button" id="pog-refresh-exchange-rate">
            <?php esc_html_e( 'Refresh Rate', 'proof-of-gift' ); ?>
        </button>
        <?php
    }

    /**
     * Render the BTCPay settings section.
     *
     * @return void
     */
    public function render_btcpay_settings_section() {
        echo '<p>' . esc_html__( 'Configure the BTCPay Server integration settings.', 'proof-of-gift' ) . '</p>';
    }

    /**
     * Render the BTCPay API key field.
     *
     * @return void
     */
    public function render_btcpay_api_key_field() {
        $settings = get_option( 'pog_settings', array() );
        $api_key = isset( $settings['btcpay_api_key'] ) ? $settings['btcpay_api_key'] : '';
        
        ?>
        <input type="password" name="pog_settings[btcpay_api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Enter your BTCPay Server API key.', 'proof-of-gift' ); ?>
        </p>
        <?php
    }

    /**
     * Render the BTCPay store ID field.
     *
     * @return void
     */
    public function render_btcpay_store_id_field() {
        $settings = get_option( 'pog_settings', array() );
        $store_id = isset( $settings['btcpay_store_id'] ) ? $settings['btcpay_store_id'] : '';
        
        ?>
        <input type="text" name="pog_settings[btcpay_store_id]" value="<?php echo esc_attr( $store_id ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Enter your BTCPay Server store ID.', 'proof-of-gift' ); ?>
        </p>
        <?php
    }

    /**
     * Render the BTCPay server URL field.
     *
     * @return void
     */
    public function render_btcpay_server_url_field() {
        $settings = get_option( 'pog_settings', array() );
        $server_url = isset( $settings['btcpay_server_url'] ) ? $settings['btcpay_server_url'] : '';
        
        ?>
        <input type="url" name="pog_settings[btcpay_server_url]" value="<?php echo esc_attr( $server_url ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Enter your BTCPay Server URL.', 'proof-of-gift' ); ?>
        </p>
        <?php
    }

    /**
     * Render the BTCPay test connection field.
     *
     * @return void
     */
    public function render_btcpay_test_connection_field() {
        ?>
        <button type="button" class="button" id="pog-test-btcpay-connection">
            <?php esc_html_e( 'Test Connection', 'proof-of-gift' ); ?>
        </button>
        <span id="pog-btcpay-connection-result"></span>
        <?php
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( false === strpos( $hook, 'proof-of-gift' ) ) {
            return;
        }
        
        wp_enqueue_script(
            'pog-admin',
            POG_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            POG_VERSION,
            true
        );
        
        wp_localize_script(
            'pog-admin',
            'pog_admin_vars',
            array(
                'ajax_url'  => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'pog_admin_nonce' ),
                'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
                'symbol'    => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
                'strings'   => array(
                    'token_generated'    => __( 'Token generated successfully', 'proof-of-gift' ),
                    'tokens_generated'   => __( 'Tokens generated successfully', 'proof-of-gift' ),
                    'error'              => __( 'An error occurred', 'proof-of-gift' ),
                    'connection_success' => __( 'Connection successful', 'proof-of-gift' ),
                    'connection_error'   => __( 'Connection failed', 'proof-of-gift' ),
                ),
            )
        );
        
        // If on the main page, enqueue the clipboard.js library.
        if ( 'toplevel_page_proof-of-gift' === $hook ) {
            wp_enqueue_script(
                'clipboard',
                POG_PLUGIN_URL . 'assets/js/clipboard.min.js',
                array(),
                POG_VERSION,
                true
            );
        }
    }

    /**
     * Enqueue admin styles.
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_styles( $hook ) {
        if ( false === strpos( $hook, 'proof-of-gift' ) ) {
            return;
        }
        
        wp_enqueue_style(
            'pog-admin',
            POG_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            POG_VERSION
        );
    }

    /**
     * Render the main admin page.
     *
     * @return void
     */
    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Proof Of Gift - Generate Tokens', 'proof-of-gift' ); ?></h1>
            
            <h2><?php esc_html_e( 'Single Token', 'proof-of-gift' ); ?></h2>
            <div class="pog-single-token-form">
                <label for="pog-single-token-amount">
                    <?php esc_html_e( 'Amount', 'proof-of-gift' ); ?>
                </label>
                <input type="number" id="pog-single-token-amount" min="1" step="1" value="100" />
                
                <button type="button" class="button button-primary" id="pog-generate-single-token">
                    <?php esc_html_e( 'Generate Token', 'proof-of-gift' ); ?>
                </button>
                
                <div class="pog-token-result" style="display:none;">
                    <h3><?php esc_html_e( 'Generated Token', 'proof-of-gift' ); ?></h3>
                    <div class="pog-token-display">
                        <code id="pog-single-token-result"></code>
                        <button type="button" class="button pog-copy-token" data-clipboard-target="#pog-single-token-result">
                            <?php esc_html_e( 'Copy', 'proof-of-gift' ); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <h2><?php esc_html_e( 'Batch Generation', 'proof-of-gift' ); ?></h2>
            <div class="pog-batch-tokens-form">
                <div class="pog-batch-denomination">
                    <label for="pog-batch-token-amount">
                        <?php esc_html_e( 'Amount', 'proof-of-gift' ); ?>
                    </label>
                    <input type="number" id="pog-batch-token-amount" min="1" step="1" value="100" />
                    
                    <label for="pog-batch-token-quantity">
                        <?php esc_html_e( 'Quantity', 'proof-of-gift' ); ?>
                    </label>
                    <input type="number" id="pog-batch-token-quantity" min="1" step="1" value="10" />
                    
                    <button type="button" class="button" id="pog-add-denomination">
                        <?php esc_html_e( 'Add Denomination', 'proof-of-gift' ); ?>
                    </button>
                </div>
                
                <div id="pog-denominations-list"></div>
                
                <p>
                    <button type="button" class="button button-primary" id="pog-generate-batch-tokens">
                        <?php esc_html_e( 'Generate Tokens', 'proof-of-gift' ); ?>
                    </button>
                </p>
                
                <div class="pog-batch-result" style="display:none;">
                    <h3><?php esc_html_e( 'Generated Tokens', 'proof-of-gift' ); ?></h3>
                    <div class="pog-batch-tokens-list">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Token', 'proof-of-gift' ); ?></th>
                                    <th><?php esc_html_e( 'Amount', 'proof-of-gift' ); ?></th>
                                </tr>
                            </thead>
                            <tbody id="pog-batch-tokens-result"></tbody>
                        </table>
                    </div>
                    
                    <p>
                        <button type="button" class="button button-primary" id="pog-export-csv">
                            <?php esc_html_e( 'Export as CSV', 'proof-of-gift' ); ?>
                        </button>
                        <button type="button" class="button" id="pog-export-pdf" title="<?php esc_attr_e( 'Coming in a future release', 'proof-of-gift' ); ?>">
                            <?php esc_html_e( 'Export as PDF', 'proof-of-gift' ); ?> (<?php esc_html_e( 'Coming Soon', 'proof-of-gift' ); ?>)
                        </button>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Proof Of Gift - Settings', 'proof-of-gift' ); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'pog_settings_group' );
                do_settings_sections( 'proof-of-gift-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the verify page.
     *
     * @return void
     */
    public function render_verify_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Proof Of Gift - Verify Token', 'proof-of-gift' ); ?></h1>
            
            <div class="pog-verify-token-form">
                <label for="pog-verify-token">
                    <?php esc_html_e( 'Token', 'proof-of-gift' ); ?>
                </label>
                <input type="text" id="pog-verify-token" class="regular-text" />
                
                <button type="button" class="button button-primary" id="pog-verify-token-btn">
                    <?php esc_html_e( 'Verify Token', 'proof-of-gift' ); ?>
                </button>
                
                <div id="pog-verify-result" style="display:none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the help page.
     *
     * @return void
     */
    public function render_help_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Proof Of Gift - Help', 'proof-of-gift' ); ?></h1>
            
            <div class="pog-help-content">
                <h2><?php esc_html_e( 'About Proof Of Gift', 'proof-of-gift' ); ?></h2>
                <p>
                    <?php esc_html_e( 'Proof Of Gift is a gift certificate system using cryptographic tokens.', 'proof-of-gift' ); ?>
                </p>
                
                <h2><?php esc_html_e( 'Operational Modes', 'proof-of-gift' ); ?></h2>
                <h3><?php esc_html_e( 'Store Currency Mode', 'proof-of-gift' ); ?></h3>
                <p>
                    <?php esc_html_e( 'In this mode, tokens are denominated in your store currency. When a customer applies a token at checkout, the token value is deducted from the cart total before payment processing.', 'proof-of-gift' ); ?>
                </p>
                
                <h3><?php esc_html_e( 'Satoshi Conversion Mode', 'proof-of-gift' ); ?></h3>
                <p>
                    <?php esc_html_e( 'In this mode, tokens are denominated in Satoshis (the smallest unit of Bitcoin). When a customer applies a token at checkout, the Satoshi value is converted to your store currency at the current exchange rate and then deducted from the cart total.', 'proof-of-gift' ); ?>
                </p>
                
                <h3><?php esc_html_e( 'Direct Satoshi Mode', 'proof-of-gift' ); ?></h3>
                <p>
                    <?php esc_html_e( 'In this mode, tokens are denominated in Satoshis and applied directly at the BTCPay Server payment screen. This mode requires the BTCPay Server plugin to be installed and configured.', 'proof-of-gift' ); ?>
                </p>
                
                <h2><?php esc_html_e( 'Token Structure', 'proof-of-gift' ); ?></h2>
                <p>
                    <?php esc_html_e( 'Each token consists of three parts separated by hyphens:', 'proof-of-gift' ); ?>
                </p>
                <ul>
                    <li><?php esc_html_e( 'Prefix: "POG"', 'proof-of-gift' ); ?></li>
                    <li><?php esc_html_e( 'Nonce: A random value to ensure uniqueness', 'proof-of-gift' ); ?></li>
                    <li><?php esc_html_e( 'Signature: A cryptographic signature that encodes the token value', 'proof-of-gift' ); ?></li>
                </ul>
                
                <h2><?php esc_html_e( 'Security', 'proof-of-gift' ); ?></h2>
                <p>
                    <?php esc_html_e( 'Tokens are secured using Ed25519 signatures, a modern cryptographic algorithm. Each token is unique and cannot be forged or altered without the private key, which is securely stored in your WordPress database.', 'proof-of-gift' ); ?>
                </p>
                
                <h2><?php esc_html_e( 'Need Help?', 'proof-of-gift' ); ?></h2>
                <p>
                    <?php
                    printf(
                        /* translators: %s: Plugin URI */
                        esc_html__( 'For more information, please visit the plugin website: %s', 'proof-of-gift' ),
                        '<a href="https://example.com/proof-of-gift" target="_blank">https://example.com/proof-of-gift</a>'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Ajax handler for generating a single token.
     *
     * @return void
     */
    public function ajax_generate_token() {
        // Check nonce.
        check_ajax_referer( 'pog_admin_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! current_user_can( 'manage_proof_of_gift' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'proof-of-gift' ) ) );
        }
        
        // Get the amount.
        $amount = isset( $_POST['amount'] ) ? intval( $_POST['amount'] ) : 0;
        
        if ( $amount <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Amount must be greater than zero.', 'proof-of-gift' ) ) );
        }
        
        try {
            // Generate the token.
            $token = $this->token_handler->create_token( $amount );
            
            // Return the token.
            wp_send_json_success( array(
                'token'  => $token,
                'amount' => $amount,
            ) );
        } catch ( \Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    /**
     * Ajax handler for generating a batch of tokens.
     *
     * @return void
     */
    public function ajax_generate_tokens_batch() {
        // Check nonce.
        check_ajax_referer( 'pog_admin_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! current_user_can( 'manage_proof_of_gift' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'proof-of-gift' ) ) );
        }
        
        // Get the denominations.
        $denominations = isset( $_POST['denominations'] ) ? json_decode( stripslashes( $_POST['denominations'] ), true ) : array();
        
        if ( empty( $denominations ) ) {
            wp_send_json_error( array( 'message' => __( 'No denominations specified.', 'proof-of-gift' ) ) );
        }
        
        try {
            $tokens = array();
            
            // Generate tokens for each denomination.
            foreach ( $denominations as $denomination ) {
                $amount = intval( $denomination['amount'] );
                $quantity = intval( $denomination['quantity'] );
                
                if ( $amount <= 0 || $quantity <= 0 ) {
                    continue;
                }
                
                $batch_tokens = $this->token_handler->create_tokens_batch( $amount, $quantity );
                
                foreach ( $batch_tokens as $token ) {
                    $tokens[] = array(
                        'token'  => $token,
                        'amount' => $amount,
                    );
                }
            }
            
            // Return the tokens.
            wp_send_json_success( array(
                'tokens' => $tokens,
            ) );
        } catch ( \Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    /**
     * Ajax handler for exporting tokens as CSV.
     *
     * @return void
     */
    public function ajax_export_tokens_csv() {
        // Check nonce.
        check_ajax_referer( 'pog_admin_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! current_user_can( 'manage_proof_of_gift' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'proof-of-gift' ) ) );
        }
        
        // Get the tokens.
        $tokens = isset( $_POST['tokens'] ) ? json_decode( stripslashes( $_POST['tokens'] ), true ) : array();
        
        if ( empty( $tokens ) ) {
            wp_send_json_error( array( 'message' => __( 'No tokens to export.', 'proof-of-gift' ) ) );
        }
        
        // Generate the CSV.
        $csv = $this->token_handler->export_tokens_csv( $tokens );
        
        // Return the CSV.
        wp_send_json_success( array(
            'csv' => $csv,
        ) );
    }

    /**
     * Ajax handler for exporting tokens as PDF.
     *
     * @return void
     */
    public function ajax_export_tokens_pdf() {
        // Check nonce.
        check_ajax_referer( 'pog_admin_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! current_user_can( 'manage_proof_of_gift' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'proof-of-gift' ) ) );
        }
        
        // Get the tokens.
        $tokens = isset( $_POST['tokens'] ) ? json_decode( stripslashes( $_POST['tokens'] ), true ) : array();
        
        if ( empty( $tokens ) ) {
            wp_send_json_error( array( 'message' => __( 'No tokens to export.', 'proof-of-gift' ) ) );
        }
        
        // Placeholder response for PDF functionality that will be implemented in a future release
        wp_send_json_success( array(
            'message' => __( 'PDF export will be available in a future release. Please use CSV export for now.', 'proof-of-gift' ),
            'pdf_url' => '#', // Dummy URL to keep JS happy
        ) );
    }
    
    /**
     * Handles PDF download requests.
     *
     * @return void
     */
    public function handle_pdf_download() {
        // Check nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'pog_download_pdf')) {
            wp_die(__('Security verification failed.', 'proof-of-gift'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_proof_of_gift')) {
            wp_die(__('You do not have permission to download this file.', 'proof-of-gift'));
        }
        
        // Get tokens from transient
        if (!isset($_GET['token_id'])) {
            wp_die(__('No token data specified.', 'proof-of-gift'));
        }
        
        $transient_id = sanitize_text_field($_GET['token_id']);
        $tokens = get_transient($transient_id);
        
        if (empty($tokens)) {
            wp_die(__('Token data expired or not found. Please generate new tokens.', 'proof-of-gift'));
        }
        
        // Generate PDF using WordPress's built-in libraries
        if (!$this->generate_pdf($tokens)) {
            wp_die(__('Failed to generate PDF.', 'proof-of-gift'));
        }
        
        // Execution ends in generate_pdf with file download
        exit;
    }
    
    /**
     * Generate PDF with token data.
     *
     * @param array $tokens The tokens to include in the PDF.
     * @return bool Whether the PDF was generated and sent.
     */
    private function generate_pdf($tokens) {
        // Get site info for the PDF
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $date = date_i18n(get_option('date_format'));
        
        // Set the operational mode
        $mode = $this->token_handler->get_operational_mode();
        $mode_text = '';
        
        switch ($mode) {
            case 'store_currency':
                $mode_text = __('Store Currency', 'proof-of-gift');
                $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
                break;
            case 'satoshi_conversion':
                $mode_text = __('Satoshi Conversion', 'proof-of-gift');
                $currency_symbol = 'sats';
                break;
            case 'direct_satoshi':
                $mode_text = __('Direct Satoshi', 'proof-of-gift');
                $currency_symbol = 'sats';
                break;
            default:
                $mode_text = __('Unknown Mode', 'proof-of-gift');
                $currency_symbol = '';
        }
        
        // Start output buffering to capture the HTML
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <title><?php echo esc_html__('Gift Tokens', 'proof-of-gift'); ?></title>
            <style>
                body {
                    font-family: 'Helvetica', Arial, sans-serif;
                    font-size: 12pt;
                    color: #333;
                    line-height: 1.4;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #ddd;
                }
                h1 {
                    font-size: 24pt;
                    margin: 0;
                    padding: 0;
                    color: #222;
                }
                .site-info {
                    font-size: 10pt;
                    color: #666;
                    margin-top: 5px;
                }
                .mode-info {
                    font-size: 12pt;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .token-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                .token-table th {
                    background-color: #f5f5f5;
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                .token-table td {
                    border: 1px solid #ddd;
                    padding: 8px;
                }
                .token-code {
                    font-family: 'Courier New', monospace;
                    font-size: 11pt;
                }
                .token-item {
                    page-break-inside: avoid;
                    margin-bottom: 15px;
                    padding: 10px;
                    border: 1px solid #ddd;
                    background-color: #f9f9f9;
                }
                .token-value {
                    font-size: 14pt;
                    font-weight: bold;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 10pt;
                    color: #999;
                    border-top: 1px solid #ddd;
                    padding-top: 10px;
                }
                .verification-url {
                    font-size: 10pt;
                    color: #666;
                }
                @media print {
                    body {
                        font-size: 10pt;
                    }
                    .token-item {
                        page-break-inside: avoid;
                    }
                    .token-table {
                        page-break-inside: auto;
                    }
                    .token-table tr {
                        page-break-inside: avoid;
                        page-break-after: auto;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo esc_html__('Gift Tokens', 'proof-of-gift'); ?></h1>
                <div class="site-info">
                    <?php echo esc_html($site_name); ?> | <?php echo esc_html($site_url); ?><br>
                    <?php echo esc_html__('Generated on', 'proof-of-gift'); ?>: <?php echo esc_html($date); ?>
                </div>
                <div class="mode-info">
                    <?php echo esc_html__('Mode', 'proof-of-gift'); ?>: <?php echo esc_html($mode_text); ?>
                </div>
            </div>
            
            <table class="token-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Token', 'proof-of-gift'); ?></th>
                        <th><?php echo esc_html__('Value', 'proof-of-gift'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tokens as $token_data): ?>
                    <tr>
                        <td class="token-code"><?php echo esc_html($token_data['token']); ?></td>
                        <td class="token-value">
                            <?php 
                            if ('store_currency' === $mode) {
                                echo esc_html($currency_symbol . $token_data['amount']);
                            } else {
                                echo esc_html($token_data['amount'] . ' ' . $currency_symbol);
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2><?php echo esc_html__('Individual Tokens', 'proof-of-gift'); ?></h2>
            
            <?php foreach ($tokens as $token_data): ?>
            <div class="token-item">
                <h3><?php 
                if ('store_currency' === $mode) {
                    echo esc_html($currency_symbol . $token_data['amount']);
                } else {
                    echo esc_html($token_data['amount'] . ' ' . $currency_symbol);
                }
                ?></h3>
                <div class="token-code"><?php echo esc_html($token_data['token']); ?></div>
                <div class="verification-url">
                    <?php echo esc_html__('Verify at', 'proof-of-gift'); ?>: <?php echo esc_html(home_url('/pog-verify/' . $token_data['token'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="footer">
                <?php echo esc_html__('Generated by Proof Of Gift plugin', 'proof-of-gift'); ?><br>
                <?php echo esc_html__('Tokens can be verified at', 'proof-of-gift'); ?>: <?php echo esc_html(home_url('/pog-verify/')); ?>
            </div>
            
            <script>
                // Auto print dialog
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        
        // Since we're in WordPress environment and may not have access to PDF generation libraries,
        // we'll serve a print-optimized HTML that browsers can convert to PDF or print
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        
        return true;
        }
        
        $transient_id = sanitize_text_field($_GET['token_id']);
        $tokens = get_transient($transient_id);
        
        if (empty($tokens)) {
            wp_die(__('Token data expired or not found. Please generate new tokens.', 'proof-of-gift'));
        }
        
        // Generate PDF using WordPress's built-in libraries
        if (!$this->generate_pdf($tokens)) {
            wp_die(__('Failed to generate PDF.', 'proof-of-gift'));
        }
        
        // Execution ends in generate_pdf with file download
        exit;
    }
    
    /**
     * Generate PDF with token data.
     *
     * @param array $tokens The tokens to include in the PDF.
     * @return bool Whether the PDF was generated and sent.
     */
    private function generate_pdf($tokens) {
        // Get site info for the PDF
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $date = date_i18n(get_option('date_format'));
        
        // Set the operational mode
        $mode = $this->token_handler->get_operational_mode();
        $mode_text = '';
        
        switch ($mode) {
            case 'store_currency':
                $mode_text = __('Store Currency', 'proof-of-gift');
                $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
                break;
            case 'satoshi_conversion':
                $mode_text = __('Satoshi Conversion', 'proof-of-gift');
                $currency_symbol = 'sats';
                break;
            case 'direct_satoshi':
                $mode_text = __('Direct Satoshi', 'proof-of-gift');
                $currency_symbol = 'sats';
                break;
            default:
                $mode_text = __('Unknown Mode', 'proof-of-gift');
                $currency_symbol = '';
        }
        
        // Start output buffering to capture the HTML
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <title><?php echo esc_html__('Gift Tokens', 'proof-of-gift'); ?></title>
            <style>
                body {
                    font-family: 'Helvetica', Arial, sans-serif;
                    font-size: 12pt;
                    color: #333;
                    line-height: 1.4;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #ddd;
                }
                h1 {
                    font-size: 24pt;
                    margin: 0;
                    padding: 0;
                    color: #222;
                }
                .site-info {
                    font-size: 10pt;
                    color: #666;
                    margin-top: 5px;
                }
                .mode-info {
                    font-size: 12pt;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .token-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                .token-table th {
                    background-color: #f5f5f5;
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                .token-table td {
                    border: 1px solid #ddd;
                    padding: 8px;
                }
                .token-code {
                    font-family: 'Courier New', monospace;
                    font-size: 11pt;
                }
                .token-item {
                    page-break-inside: avoid;
                    margin-bottom: 15px;
                    padding: 10px;
                    border: 1px solid #ddd;
                    background-color: #f9f9f9;
                }
                .token-value {
                    font-size: 14pt;
                    font-weight: bold;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 10pt;
                    color: #999;
                    border-top: 1px solid #ddd;
                    padding-top: 10px;
                }
                .verification-url {
                    font-size: 10pt;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo esc_html__('Gift Tokens', 'proof-of-gift'); ?></h1>
                <div class="site-info">
                    <?php echo esc_html($site_name); ?> | <?php echo esc_html($site_url); ?><br>
                    <?php echo esc_html__('Generated on', 'proof-of-gift'); ?>: <?php echo esc_html($date); ?>
                </div>
                <div class="mode-info">
                    <?php echo esc_html__('Mode', 'proof-of-gift'); ?>: <?php echo esc_html($mode_text); ?>
                </div>
            </div>
            
            <table class="token-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Token', 'proof-of-gift'); ?></th>
                        <th><?php echo esc_html__('Value', 'proof-of-gift'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tokens as $token_data): ?>
                    <tr>
                        <td class="token-code"><?php echo esc_html($token_data['token']); ?></td>
                        <td class="token-value">
                            <?php 
                            if ('store_currency' === $mode) {
                                echo esc_html($currency_symbol . $token_data['amount']);
                            } else {
                                echo esc_html($token_data['amount'] . ' ' . $currency_symbol);
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2><?php echo esc_html__('Individual Tokens', 'proof-of-gift'); ?></h2>
            
            <?php foreach ($tokens as $token_data): ?>
            <div class="token-item">
                <h3><?php 
                if ('store_currency' === $mode) {
                    echo esc_html($currency_symbol . $token_data['amount']);
                } else {
                    echo esc_html($token_data['amount'] . ' ' . $currency_symbol);
                }
                ?></h3>
                <div class="token-code"><?php echo esc_html($token_data['token']); ?></div>
                <div class="verification-url">
                    <?php echo esc_html__('Verify at', 'proof-of-gift'); ?>: <?php echo esc_html(home_url('/pog-verify/' . $token_data['token'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="footer">
                <?php echo esc_html__('Generated by Proof Of Gift plugin', 'proof-of-gift'); ?><br>
                <?php echo esc_html__('Tokens can be verified at', 'proof-of-gift'); ?>: <?php echo esc_html(home_url('/pog-verify/')); ?>
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        
        // Set appropriate headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="gift-tokens-' . date('Y-m-d') . '.pdf"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Use WordPress's built-in DOMDocument for HTML to PDF conversion through a print-friendly HTML
        // Since we can't rely on external PDF libraries, we'll serve a print-optimized HTML that browsers
        // can convert to PDF
        echo $html;
        
        return true;
    }

    /**
     * Ajax handler for verifying a token.
     *
     * @return void
     */
    public function ajax_verify_token() {
        // Check nonce.
        check_ajax_referer( 'pog_admin_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! current_user_can( 'manage_proof_of_gift' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'proof-of-gift' ) ) );
        }
        
        // Get the token.
        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        
        if ( empty( $token ) ) {
            wp_send_json_error( array( 'message' => __( 'No token provided.', 'proof-of-gift' ) ) );
        }
        
        // Verify the token.
        $verification = $this->token_handler->verify_token( $token );
        
        if ( false === $verification ) {
            wp_send_json_error( array( 'message' => __( 'Invalid token.', 'proof-of-gift' ) ) );
        }
        
        // Get the operational mode.
        $mode = $this->token_handler->get_operational_mode();
        
        // Prepare the response based on the operational mode.
        $response = array(
            'valid'      => $verification['valid'],
            'token'      => $token,
            'amount'     => $verification['amount'],
            'redeemed'   => isset( $verification['redeemed'] ) ? $verification['redeemed'] : false,
            'mode'       => $mode,
        );
        
        // If the token has been redeemed, get the redemption data.
        if ( isset( $verification['redeemed'] ) && $verification['redeemed'] ) {
            $redemption_data = $this->token_handler->get_redemption_data( $token );
            
            if ( $redemption_data ) {
                $response['redeemed_at'] = $redemption_data['redeemed_at'];
                $response['order_id'] = $redemption_data['order_id'];
                
                // If an order ID is available, get the order details.
                if ( $redemption_data['order_id'] && function_exists( 'wc_get_order' ) ) {
                    $order = wc_get_order( $redemption_data['order_id'] );
                    
                    if ( $order ) {
                        $response['order_number'] = $order->get_order_number();
                        $response['order_status'] = $order->get_status();
                        $response['order_date'] = $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
                    }
                }
            }
        }
        
        // If in Satoshi conversion or direct Satoshi mode, include the store currency equivalent.
        if ( 'satoshi_conversion' === $mode || 'direct_satoshi' === $mode ) {
            $response['store_currency_amount'] = $this->token_handler->convert_satoshis_to_currency( $verification['amount'] );
            $response['store_currency'] = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        }
        
        wp_send_json_success( $response );
    }

    /**
     * Ajax handler for testing the BTCPay Server connection.
     *
     * @return void
     */
    public function ajax_btcpay_test_connection() {
        // Check nonce.
        check_ajax_referer( 'pog_admin_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! current_user_can( 'manage_proof_of_gift' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'proof-of-gift' ) ) );
        }
        
        // Get the BTCPay Server settings.
        $settings = get_option( 'pog_settings', array() );
        $api_key = isset( $settings['btcpay_api_key'] ) ? $settings['btcpay_api_key'] : '';
        $store_id = isset( $settings['btcpay_store_id'] ) ? $settings['btcpay_store_id'] : '';
        $server_url = isset( $settings['btcpay_server_url'] ) ? $settings['btcpay_server_url'] : '';
        
        if ( empty( $api_key ) || empty( $store_id ) || empty( $server_url ) ) {
            wp_send_json_error( array( 'message' => __( 'Please configure the BTCPay Server settings first.', 'proof-of-gift' ) ) );
        }
        
        // Test the connection to BTCPay Server.
        $response = wp_remote_get( trailingslashit( $server_url ) . 'api/v1/stores/' . $store_id, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'token ' . $api_key,
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        
        if ( 200 !== $status_code ) {
            wp_send_json_error( array( 'message' => sprintf( __( 'Error connecting to BTCPay Server: %s', 'proof-of-gift' ), $status_code ) ) );
        }
        
        wp_send_json_success( array( 'message' => __( 'Connection successful!', 'proof-of-gift' ) ) );
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
}