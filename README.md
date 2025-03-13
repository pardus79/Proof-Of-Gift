# Proof Of Gift

A WordPress plugin implementing a self-contained gift certificate system using cryptographic tokens similar to Chaumian eCash.

## Description

Proof Of Gift is a powerful gift certificate solution for WordPress and WooCommerce. It uses Ed25519 signatures to create compact, secure tokens that function similarly to Chaumian eCash - they can be verified independently without database lookups and prevent double-spending through a redemption registry.

### Key Features

- **Cryptographically Secure Tokens**: Uses Ed25519 signatures for compact, unforgeable gift certificates
- **Multiple Currency Modes**:
  - Store Currency Mode: tokens denominated in store currency
  - Satoshi Conversion Mode: tokens denominated in Satoshis, converted to store currency
- **Token Management**:
  - Generate single tokens or batch generate multiple tokens
  - CSV export of generated tokens with application URLs
  - Built-in token verification system
  - Copy-paste ready token URLs for easy distribution
- **Shopping Integration**:
  - Apply tokens via URL or checkout form
  - Multiple tokens per order
  - "Change tokens" for unused balances
  - Token information in order emails with clickable application links

## How It Works

Proof Of Gift implements a gift certificate system with properties similar to Chaumian eCash:

1. **Token Structure**: Each token follows the format `PREFIX-NONCE-AMOUNT-SIGNATURE` and is encoded using base64url
2. **Cryptographic Verification**: Tokens are signed using Ed25519 digital signatures, making them unforgeable
3. **Independent Verification**: Tokens can be verified without a database lookup using the public key
4. **Double-Spend Prevention**: A redemption database tracks spent tokens to prevent double-spending
5. **Change Generation**: When a token is not fully spent, the system automatically creates a new token for the remaining value
6. **URL-Based Application**: Tokens can be applied via URL for easy sharing and distribution

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher with sodium extension enabled
- WooCommerce 5.0 or higher (optional, for store integration)

## Installation

1. Upload the plugin files to the `/wp-content/plugins/proof-of-gift` directory, or install the plugin through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings->Proof Of Gift to configure the plugin

## Configuration

1. **General Settings**:
   - Token Prefix: Set a unique prefix for your tokens (default: POG)
   - Token Name: Customize how tokens are referred to in the UI (e.g., "Gift Tokens", "Store Credits")
   - Data Preservation: Option to preserve data when plugin is uninstalled

2. **Operational Mode**:
   - Store Currency: Tokens denominated in your store's currency
   - Satoshi Conversion: Tokens in Satoshis, converted to store currency at checkout using current exchange rates

## Usage

### Generating Tokens

1. Go to Proof Of Gift > Generate Tokens
2. Enter the denomination amount for the token
3. Click "Generate Token" for a single token, or use Batch Generation for multiple tokens
4. Copy the generated tokens, application URLs, or export them as CSV

The tokens will be denominated in the currency set in your operational mode (store currency or satoshis).

### Applying Tokens

Tokens can be applied in multiple ways:

1. **URL Application**:
   - Use query parameter format: `https://your-site.com/any-page?pog_token=TOKEN_CODE`
   - These URLs can be shared via email, messages, or used to create custom QR codes

2. **Checkout Form**:
   - Customers enter the token code in the designated field during checkout
   - Multiple tokens can be applied to a single order

### Verifying Tokens

Tokens are automatically verified when a customer applies them at checkout. Administrators can also verify tokens in the Proof of Gift admin area under the "Verify Token" menu option.

### Token Management for Admins

1. **Batch Generation**:
   - Go to Proof Of Gift > Generate Tokens > Batch Generation
   - Set quantities and denominations for bulk token creation
   - Export tokens as CSV with token codes and application URLs
   
2. **Backup & Restore**:
   - Go to Proof Of Gift > Backup & Restore
   - Export cryptographic keys and redeemed token database for safekeeping
   - Import previously exported data when needed for migrations

## Understanding the Cryptography

The plugin uses Ed25519 digital signatures, which provide:

1. **Unforgeability**: Only the party with the private key can create valid tokens
2. **Compact Size**: Signatures are only 64 bytes, allowing for smaller tokens
3. **Fast Verification**: Ed25519 offers faster verification than many other signature schemes

Each token contains:
- Prefix: A configurable string to identify your tokens
- Nonce: A random value to prevent signature collisions
- Amount: The token's value in your chosen denomination
- Signature: An Ed25519 signature of the above components

## Chaumian eCash Similarities

Proof Of Gift shares several properties with Chaumian eCash systems:

1. **Unforgeability**: Cryptographically secured tokens that can't be counterfeit
2. **Offline Verification**: Tokens can be verified without database lookups
3. **Double-Spend Prevention**: Central registry of redeemed tokens
4. **Change Generation**: Automatic creation of new tokens for unused value
5. **Transferability**: Tokens can be passed between users without involving the issuer
6. **Application-Specific Denomination**: Value represented in domain-specific units

## Frequently Asked Questions

### Is this plugin secure?

Yes, the plugin uses Ed25519 signatures which are considered highly secure. The private key is stored securely in your WordPress database and is never exposed to clients.

### Can I use this without WooCommerce?

Yes, the plugin works in standalone mode without WooCommerce, but with limited functionality. WooCommerce enables the full shopping cart integration.

### How does token change work?

When a customer applies a token worth more than their purchase, the system automatically generates a new token for the difference. This "change token" is displayed to the customer and included in their order confirmation email.

## Changelog

### 1.0.0
* Initial release
* Token generation and verification
* WooCommerce integration
* URL-based token application
* Custom token naming

## Credits

Developed by BtcPins - [pardus79](https://github.com/pardus79)

## License

This plugin is licensed under The Unlicense.

See [LICENSE](LICENSE) for more information.