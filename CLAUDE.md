# CLAUDE.md - Repository Guide

## Project Summary
Proof Of Gift is a WordPress plugin that implements a cryptographic gift certificate system. It uses Ed25519 signatures to create secure, compact tokens that can be used for purchasing at stores with WooCommerce. The plugin supports three operational modes:

1. **Store Currency Mode**: Tokens denominated in store currency, applied directly to cart total
2. **Satoshi Conversion Mode**: Tokens denominated in Satoshis, converted to store currency at checkout
3. **Direct Satoshi Mode**: Tokens denominated in Satoshis, applied at BTCPay Server payment screen

## Completed Features
- ✅ Core cryptographic operations with Ed25519 signatures
- ✅ Token generation (single and batch)
- ✅ Token verification and redemption
- ✅ WooCommerce integration
- ✅ BTCPay Server integration with change tokens
- ✅ Dynamic exchange rate retrieval from BTCPay Server and CoinGecko
- ✅ Admin interface for all functionality
- ✅ CSV export of generated tokens
- ✅ PHPUnit test setup for crypto functions
- ✅ Translation support (.pot file)
- ✅ Multi-currency support

## Build Commands
- Lint: `composer run phpcs`
- Fix code style: `composer run phpcbf`
- Run tests: `composer run test`
- Run single test: `vendor/bin/phpunit --filter=testName`
- Build composer: `composer install`

## Code Style Guidelines
- Follow WordPress Coding Standards (WPCS)
- Use PHP namespaces with ProofOfGift prefix
- Class names: PascalCase (e.g., POG_Token_Handler)
- Method/function names: snake_case
- Variables: snake_case
- Constants: UPPERCASE_WITH_UNDERSCORES
- Files should use .php extension with class- prefix
- Validate and sanitize all user inputs
- Use WordPress nonces for form submissions
- Document functions with PHPDoc comments
- Follow WordPress hook naming conventions
- Error handling: use try/catch for exceptions
- Use strict typing where possible

## Project Structure
- /assets/ - CSS, JS, and images
- /includes/ - Core plugin classes
  - /admin/ - Admin interface functionality
  - /crypto/ - Cryptographic operations
  - /integrations/ - Third-party integrations (WooCommerce, BTCPay)
  - /public/ - Public-facing functionality
- /templates/ - Template files for frontend display
- /languages/ - Translation files
- /tests/ - PHPUnit test files
- /bin/ - Helper scripts for development

## Recent Improvements

### Token Application and Payment Integration
- ✅ Completely redesigned token application system using WooCommerce fees instead of coupons
- ✅ Added proper token persistence between page loads via WooCommerce's native hooks
- ✅ Fixed critical issue where tokens weren't properly deducted from payment totals
- ✅ Ensured token discounts are properly passed to payment processors (including BTCPay Server)
- ✅ Improved user experience by automatically applying tokens without extra clicks
- ✅ Enhanced token display to show values in the plugin's native currency (Sats for satoshi modes)
- ✅ Added a total tokens value section to summarize all applied discounts
- ✅ Implemented better error handling with detailed logging throughout token application flow
- ✅ Added no-redirect behavior for URL-based tokens so customers can continue shopping

### URL-Based Token Application
- ✅ Added reliable token URL generation utility for both admin and customers
- ✅ Added URL parameter support for applying tokens with `?pog_token=` parameter
- ✅ Enhanced token redemption emails to include clickable application URLs
- ✅ Improved verification page with better UI and "Continue Shopping" options
- ✅ Added token links in order confirmation emails and thank you pages

### BTCPay Server Integration Enhancement
- ✅ Improved BTCPay Server connection testing with better error handling
- ✅ Added robust version detection with multiple fallback methods for BTCPay Server
- ✅ Implemented dynamic exchange rate retrieval with intelligent source hierarchy:
  1. BTCPay Server (when available and configured)
  2. CoinGecko API (as fallback)
  3. Static rate (only if specifically configured)
- ✅ Fixed API authorization headers by ensuring proper token format
- ✅ Reduced cache time to 15 minutes to ensure rates stay current
- ✅ Enhanced UI with better feedback on connection status and rate information

## Next Implementation Steps
1. ⏳ Implement PDF export functionality
   - Requirements: Add TCPDF or other PDF library to composer.json
   - Design printable PDF template for gift tokens
   - Implement the PDF generation and download functionality
2. Add more unit tests for token handling and integrations
3. Improve documentation with usage examples
4. Create admin help screen with video tutorials
5. Consider additional exchange rate sources for redundancy

## Development Notes
- When working with the codebase, always edit the files in the main directory structure, not in the release-zip folder
- The release-zip folder is only used for preparing the plugin for distribution and should not be edited directly
- The release-zip folder can be periodically deleted and regenerated when needed

## Code Optimization Tips
- Break up large code changes into smaller, focused edits to prevent Claude from freezing
- When modifying complex functions, consider refactoring them into multiple smaller functions
- Limit string replacements to 100 lines or less for better reliability
- For complex token parsing, handle different formats in separate helper methods
- Consider adding test cases for unusual token formats to verify backward compatibility

## Terminology
- Always use the token name plural form (configurable in plugin settings) in all public-facing areas
- All text references to "token" in public interfaces should use the setting-based name
- Admin interfaces may still use "token" terminology for consistency with developer documentation
- Error messages and notifications consistently use the plural form of the custom token name
- Default token name is "Gift Tokens" but can be configured in plugin settings

## Recent Improvements - March 2025

### Backup & Restore Functionality
- ✅ Added complete backup and restore system for cryptographic keys and token database
- ✅ Implemented secure export with password-based encryption using sodium_crypto_secretbox
- ✅ Created JSON export format with optional password protection
- ✅ Added import validation with proper error handling
- ✅ Added option to preserve data during plugin uninstallation
- ✅ Implemented selective import/export (keys, settings, redemption database)
- ✅ Created user-friendly backup interface in admin area

### URL-Based Token Application
- ✅ Added ability to apply tokens via URLs for easy QR code integration
- ✅ Created new endpoint `pog-apply/TOKEN` for direct token application
- ✅ Added URL parameter recognition for `?pog_token=TOKEN` in any page
- ✅ Enhanced admin interfaces to display and copy token URLs
- ✅ Included verification and application URLs in CSV exports

### Direct Satoshi Mode Soft Disable
- ✅ Temporarily soft-disabled Direct Satoshi Mode which requires additional testing
- ✅ Used conditional PHP blocks with `if (false)` to hide UI elements
- ✅ Added warning message for users who might still be using this mode
- ✅ Maintained all underlying code for future re-enabling
- ✅ Can be easily re-enabled by changing the conditionals to `if (true)`

### Token Double-Spending Vulnerability Fix
- ✅ Fixed critical security issue where tokens could be used in multiple orders
- ✅ Added redemption verification at multiple points in the token lifecycle
  - During cart calculation via `apply_stored_token_fees` 
  - During token application via early check in `apply_token`
  - During order processing for comprehensive protection
- ✅ Enhanced session tracking to identify and reject already-redeemed tokens
- ✅ Added redundant verification for improved security
- ✅ Implemented proper error handling for token redemption failures

### Change Token System Improvements
- ✅ Fixed incorrect change calculation for satoshi-mode tokens
- ✅ Now calculating proper change based on actual items purchased
- ✅ Enhanced change token display on order completion page
- ✅ Improved change token information in order emails
- ✅ Added notifications when orders will result in change token issuance
- ✅ Fixed $0 total order handling to ensure change tokens are properly generated
- ✅ Added better session state preservation for token data

### User Experience Enhancements
- ✅ Improved JavaScript for token application and removal across all pages
- ✅ Refactored token application into a reusable, context-aware function
- ✅ Fixed edge cases in token display and application
- ✅ Added better error handling and user feedback
- ✅ Improved notification messages for token application status
- ✅ Enhanced token removal with proper session state updates

### Debugging and Logging Improvements
- ✅ Added extensive logging during change token calculations
- ✅ Improved error logging for troubleshooting redemption issues
- ✅ Added tracking of token value and exchange rate calculations
- ✅ Enhanced session state debugging during token application
- ✅ Added detailed logging for token redemption process

## Key Implementation Learnings

### Cart and Checkout Integration
- Use WooCommerce's `woocommerce_cart_calculate_fees` hook to apply persistent fees from session
- Avoid relying solely on coupons for gift card-like functionality as they have limitations
- Store token information in both session and order meta for proper tracking
- For custom displays in cart/checkout, attach to `woocommerce_cart_totals_after_order_total`
- When using negative fees, ensure proper display with currency symbols and formatting

### Session and State Management
- Always format token data consistently when storing in session
- Use unique identifiers for tokens to prevent conflicts
- Always validate token data retrieved from session before using
- Include detailed error logging at each step of token processing
- For better debugging, log cart totals after applying token discounts
- Use try/catch blocks around WooCommerce API interactions to gracefully handle errors

### Currency and Exchange Rates
- For currency display, respect the operational mode (Satoshi vs store currency)
- Group tokens by currency type in UI to avoid confusion
- Always include the unit (e.g., "Sats") in Satoshi mode displays
- When showing totals, use the plugin's native currency format rather than hybrid formats
- Format currency values with appropriate decimal places and symbols

### Token Security Best Practices
- Verify token redemption status at every stage: application, calculation, checkout
- Store redeemed tokens in database immediately upon order completion
- Check redemption status before applying any token to cart/checkout
- Never allow the same token to be used in multiple orders
- When handling zero-total orders, ensure token metadata is preserved
- Add redundant verification hooks to ensure security even if one check fails

## IMPORTANT - DO NOT MODIFY THESE CORE IMPLEMENTATION DECISIONS:

1. **Base64 Encoding**: NEVER use hyphens in base64 encoding since they are used as token separators
   - The `base64url_encode()` function must use '._' instead of '+/' in its character replacement
   - The encoding must avoid characters that could cause URL parsing issues
   - The corresponding `base64url_decode()` function must handle both the original ('._') encoding

2. **Token Format**: Maintain exactly 4-part token structure: PREFIX-NONCE-AMOUNT-SIGNATURE
   - Do not attempt to support tokens with more than 3 separators
   - Do not add "smart" parsing that tries to handle malformed tokens

3. **Token Verification**: 
   - Always enforce the configured prefix from plugin settings
   - Only validate tokens from this plugin with its configured prefix
   - Don't add support for foreign tokens or alternative formats

4. **Error Handling**:
   - Use detailed error logging in token verification functions
   - Always validate that input data can be properly decoded
   - Check signature length before attempting verification

5. **API Authorization**:
   - Always include proper Authorization headers for BTCPay Server API requests
   - Use 'token ' prefix format for BTCPay Server API key in Authorization header
   - Ensure API URLs have proper protocol and trailing slashes
   - Validate API permissions with btcpay.store.canviewstoresettings permission for rate access

6. **WooCommerce Integration**:
   - Use fees instead of coupons for gift card functionality
   - Always hook into woocommerce_cart_calculate_fees for persistent fee application
   - Store token data in session to maintain state between page loads
   - Implement apply_stored_token_fees function to apply fees on every page load
   - Follow the currency display conventions for the operational mode

# ProofOfGift WordPress Plugin Project Brief

I need a complete WordPress plugin that implements a self-contained gift certificate system using cryptographic tokens. The plugin should be named "ProofOfGift" and have the following functionality:

## 1. TOKEN STRUCTURE
- Create compact tokens using Ed25519 signatures with base64url encoding
- Structure: PREFIX-NONCE-AMOUNT-SIGNATURE
- Tokens should be currency-agnostic, containing only generic units
- Use cryptographically secure random nonces (16 bytes) without maintaining a database of issued nonces
- For this low-volume store, we can accept the astronomically small risk of nonce collision

## 2. TOKEN CREATION
- Admin interface to generate tokens with specified unit amounts
- Generate single tokens or batch generation
- Bulk creation feature with:
  - Input for quantity and denomination
  - Option to create multiple denominations in one batch
  - CSV export of all generated tokens
  - Option to download as a formatted, printable PDF
- Secure key management (store private key in WordPress securely)

## 3. TOKEN VERIFICATION
- Public verification mechanism using the plugin's public key
- Prevent double-spending using WordPress database tracking of redeemed tokens only
- Allow verification without redemption (for checking validity)

## 4. CURRENCY HANDLING & PAYMENT INTEGRATION
- Support three distinct operational modes:
  1. **Store Currency Mode**: Tokens denominated in store currency (default)
     - Deduct from cart total before any payment processor
  2. **Satoshi Conversion Mode**: Tokens denominated in Satoshis
     - Convert to store currency at current exchange rate
     - Deduct converted amount from cart total before payment
  3. **Direct Satoshi Mode**: Tokens denominated in Satoshis
     - Applied directly at BTCPay Server payment screen
     - Requires BTCPay Server plugin integration

- Include settings to configure operational mode:
  - Detect available payment gateways
  - Check if BTCPay Server plugin is installed and activated
  - Display appropriate options based on detected plugins
  - Provide clear explanations of each mode's functionality
  - Display warnings if selecting Direct Satoshi Mode without BTCPay Server plugin

- Handle token change in Direct Satoshi Mode:
  - WordPress validates and sends full token value to BTCPay Server
  - BTCPay Server applies exact discount at payment time
  - After payment, BTCPay Server sends callback with exact satoshis used
  - WordPress generates change token based on actual usage reported by BTCPay Server
  - Display change token to customer and include in order confirmation

## 5. CHECKOUT INTEGRATION
- Allow customers to apply multiple tokens at checkout
- Display applied token values and remaining cart total
- If tokens aren't fully used, generate "change tokens" for remaining balance
- Display change token to customer at checkout completion
- Include change token in order confirmation email

## 6. WOOCOMMERCE COMPATIBILITY
- Integrate with WooCommerce if available
- Work as standalone for non-WooCommerce WordPress stores

## 7. SECURITY
- Implement proper nonce verification for all admin actions
- Sanitize all inputs and validate data
- Support WordPress multisite installations
- No external dependencies beyond WordPress core and PHP's cryptographic extensions

## 8. CODE ORGANIZATION
- Follow WordPress coding standards
- Use OOP approach with proper namespacing
- Include clear documentation and code comments
- Create uninstall routine that cleans up all database entries

## 9. BTCPAY SERVER INTEGRATION
- Create complementary code for a BTCPay Server plugin that:
  - Receives validated token information from WordPress
  - Applies the discount directly in the BTCPay payment flow
  - Handles token redemption status updates
  - Includes installation instructions for the BTCPay Server component
  - Provides secure communication between WordPress and BTCPay Server
  - Offers fallback behavior for tokens if the BTCPay plugin is not installed
- Add detection mechanisms in the WordPress plugin:
  - Check if BTCPay Server plugin is installed and activated
  - Provide configuration warnings if "Direct Satoshi Mode" is enabled without BTCPay Server plugin
  - Allow automatic fallback to "Satoshi Conversion Mode" if BTCPay Server becomes unavailable
- Handle cross-plugin dependencies:
  - Gracefully handle errors if communication between plugins fails
  - Store pending token redemptions for retry if necessary
  - Provide debugging tools for troubleshooting integration issues