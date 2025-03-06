# Proof Of Gift

A WordPress plugin implementing a self-contained gift certificate system using cryptographic tokens.

## Description

Proof Of Gift is a powerful gift certificate solution for WordPress and WooCommerce. It uses Ed25519 signatures to create compact, secure tokens that can be used as gift certificates or store credits.

### Key Features

- **Secure Tokens**: Uses Ed25519 signatures for compact, cryptographically secure tokens
- **Multiple Currency Modes**:
  - Store Currency Mode: tokens denominated in store currency
  - Satoshi Conversion Mode: tokens denominated in Satoshis, converted to store currency
  - Direct Satoshi Mode: tokens denominated in Satoshis, applied at BTCPay Server
- **Token Management**:
  - Generate single tokens or batch generate multiple tokens
  - CSV export of generated tokens
  - Public token verification
  - PDF export (coming in future release)
- **Shopping Integration**:
  - Apply tokens at checkout
  - Multiple tokens per order
  - "Change tokens" for unused balances
  - Token information in order emails
- **BTCPay Server Integration**:
  - Apply tokens directly in the BTCPay payment flow
  - Secure communication between WordPress and BTCPay

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- WooCommerce 5.0 or higher (optional, for store integration)
- BTCPay Server plugin (optional, for Direct Satoshi Mode)

## Installation

1. Upload the plugin files to the `/wp-content/plugins/proof-of-gift` directory, or install the plugin through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Proof Of Gift screen to configure the plugin

## Usage

### Generating Tokens

1. Go to Proof Of Gift > Generate Tokens
2. Enter the denomination amount for the token
3. Click "Generate Token" to create a single token, or use Batch Generation for multiple tokens
4. Copy the generated tokens or export them as CSV or PDF

### Applying Tokens

Tokens can be applied at checkout in your store. Customers simply enter the token code in the designated field.

### Verifying Tokens

Use the shortcode `[pog_verify_token]` on any page to add a token verification form, or link directly to the verification URL:

```
https://your-site.com/pog-verify/TOKEN_CODE
```

## Frequently Asked Questions

### Is this plugin secure?

Yes, the plugin uses Ed25519 signatures which are considered highly secure. The private key is stored securely in your WordPress database and is never exposed to clients.

### Can I use this without WooCommerce?

Yes, the plugin works in standalone mode without WooCommerce, but with limited functionality. WooCommerce enables the full shopping cart integration.

### How does the BTCPay Server integration work?

The plugin can communicate with BTCPay Server to apply token discounts directly in the Bitcoin payment flow. This requires the companion [BTCPay Server Proof Of Gift Plugin](https://github.com/pardus79/Proof-Of-Gift-BTCPayServer-Plugin) to be installed and configured on your BTCPay Server instance.

1. Tokens are created in WordPress
2. During checkout with BTCPay Server, token information is passed to the BTCPay Server
3. The BTCPay Server plugin applies the token discount
4. Customer pays only the remaining amount
5. Any change is automatically calculated and a new token is generated

## Changelog

### 1.0.0
* Initial release

## Credits

Developed by [pardus79](https://github.com/pardus79)

## License

This plugin is licensed under the GPL v2 or later.

See [LICENSE](LICENSE) for more information.