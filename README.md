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
  - CSV export of generated tokens with URLs for easy redemption
  - Public token verification without redemption
  - QR code support for easy token application
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
6. **URL-Based Application**: Tokens can be applied via URL for easy QR code integration

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
   - Generate a new key pair or import an existing one

2. **Operational Mode**:
   - Store Currency: Tokens denominated in your store's currency
   - Satoshi Conversion: Tokens in Satoshis, converted to store currency at checkout using current exchange rates

## Usage

### Generating Tokens

1. Go to Proof Of Gift > Generate Tokens
2. Enter the denomination amount for the token
3. Select the token currency (store currency or Satoshis depending on your operational mode)
4. Click "Generate Token" for a single token, or use Batch Generation for multiple tokens
5. Copy the generated tokens, application URLs, or export them as CSV

### Applying Tokens

Tokens can be applied in multiple ways:

1. **URL Application**:
   - Share a token application URL: `https://your-site.com/pog-apply/TOKEN_CODE`
   - OR use a query parameter: `https://your-site.com/any-page?pog_token=TOKEN_CODE`
   - Generate QR codes linking to these URLs for physical gift cards

2. **Checkout Form**:
   - Customers enter the token code in the designated field during checkout
   - Multiple tokens can be applied to a single order

### Verifying Tokens

Use the shortcode `[pog_verify_token]` on any page to add a token verification form, or link directly to the verification URL:

```
https://your-site.com/pog-verify/TOKEN_CODE
```

### Token Management for Admins

1. **View Redeemed Tokens**:
   - Go to Proof Of Gift > Redeemed Tokens
   - See all tokens that have been redeemed, along with order details

2. **Batch Generation**:
   - Go to Proof Of Gift > Generate Tokens > Batch Generation
   - Set quantities and denominations for bulk token creation
   - Export tokens as CSV with token codes and application URLs

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

Developed by [pardus79](https://github.com/pardus79)

## License

This plugin is licensed under the GPL v2 or later.

See [LICENSE](LICENSE) for more information.