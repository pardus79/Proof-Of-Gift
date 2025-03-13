<?php
/**
 * Crypto functionality for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

namespace ProofOfGift;

/**
 * Class POG_Crypto
 *
 * Handles cryptographic operations for token generation and verification.
 */
class POG_Crypto {

    /**
     * The default prefix for gift tokens.
     *
     * @var string
     */
    const DEFAULT_TOKEN_PREFIX = 'POG';
    
    /**
     * Get the token prefix from settings or use default.
     *
     * @return string The token prefix.
     */
    public function get_token_prefix() {
        $settings = get_option('pog_settings', array());
        $prefix = isset($settings['token_prefix']) && !empty($settings['token_prefix']) 
            ? $settings['token_prefix'] 
            : self::DEFAULT_TOKEN_PREFIX;
        
        return $prefix;
    }

    /**
     * Separator for token parts.
     *
     * @var string
     */
    const TOKEN_SEPARATOR = '-';

    /**
     * Generate a new Ed25519 key pair if one doesn't exist.
     *
     * @return bool True if keys were generated, false if they already existed.
     */
    public function maybe_generate_keys() {
        $private_key = get_option( 'pog_private_key' );
        $public_key = get_option( 'pog_public_key' );

        if ( empty( $private_key ) || empty( $public_key ) ) {
            // Check if sodium extension is available.
            if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) {
                throw new \Exception( __( 'The sodium extension is required for cryptographic operations.', 'proof-of-gift' ) );
            }

            // Generate a new key pair.
            $keypair = sodium_crypto_sign_keypair();
            $private_key = sodium_crypto_sign_secretkey( $keypair );
            $public_key = sodium_crypto_sign_publickey( $keypair );

            // Encrypt the private key for more secure storage.
            $encrypted_private_key = $this->encrypt_private_key($private_key);

            // Store the keys.
            update_option( 'pog_private_key', $encrypted_private_key );
            update_option( 'pog_public_key', base64_encode( $public_key ) );

            return true;
        }

        return false;
    }

    /**
     * Get the private key.
     *
     * @return string The private key.
     */
    public function get_private_key() {
        $encrypted_private_key = get_option( 'pog_private_key' );

        if ( empty( $encrypted_private_key ) ) {
            $this->maybe_generate_keys();
            $encrypted_private_key = get_option( 'pog_private_key' );
        }

        // Decrypt the private key if it's encrypted, otherwise decode it (for backward compatibility)
        if (strpos($encrypted_private_key, 'encrypted:') === 0) {
            return $this->decrypt_private_key($encrypted_private_key);
        } else {
            // For backward compatibility
            return base64_decode($encrypted_private_key);
        }
    }
    
    /**
     * Encrypt the private key for more secure storage.
     *
     * @param string $private_key The private key to encrypt.
     * @return string The encrypted private key with prefix.
     */
    private function encrypt_private_key($private_key) {
        if (!function_exists('sodium_crypto_secretbox')) {
            // If no sodium encryption available, store with base64 encoding
            return base64_encode($private_key);
        }
        
        // Generate a key from WordPress auth keys and salts
        $encryption_key = $this->generate_encryption_key();
        
        // Generate a random nonce
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        // Encrypt the private key
        $encrypted = sodium_crypto_secretbox($private_key, $nonce, $encryption_key);
        
        // Combine nonce and ciphertext and encode
        $stored_value = 'encrypted:' . base64_encode($nonce . $encrypted);
        
        return $stored_value;
    }
    
    /**
     * Decrypt the private key.
     *
     * @param string $encrypted_data The encrypted private key with prefix.
     * @return string The decrypted private key.
     */
    private function decrypt_private_key($encrypted_data) {
        // Remove the prefix
        $data = substr($encrypted_data, 10); // 'encrypted:' is 10 chars
        
        // Decode the data
        $decoded = base64_decode($data);
        
        if ($decoded === false) {
            throw new \Exception(__('Failed to decode encrypted private key.', 'proof-of-gift'));
        }
        
        // Extract nonce and ciphertext
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        // Generate the encryption key (same method as during encryption)
        $encryption_key = $this->generate_encryption_key();
        
        // Decrypt the private key
        $private_key = sodium_crypto_secretbox_open($ciphertext, $nonce, $encryption_key);
        
        if ($private_key === false) {
            throw new \Exception(__('Failed to decrypt private key.', 'proof-of-gift'));
        }
        
        return $private_key;
    }
    
    /**
     * Generate an encryption key based on WordPress constants.
     *
     * @return string A 32-byte encryption key.
     */
    private function generate_encryption_key() {
        // Use WordPress auth keys and salts to create a unique key
        $salt = '';
        
        if (defined('AUTH_KEY')) $salt .= AUTH_KEY;
        if (defined('SECURE_AUTH_KEY')) $salt .= SECURE_AUTH_KEY;
        if (defined('LOGGED_IN_KEY')) $salt .= LOGGED_IN_KEY;
        if (defined('NONCE_KEY')) $salt .= NONCE_KEY;
        if (defined('AUTH_SALT')) $salt .= AUTH_SALT;
        if (defined('SECURE_AUTH_SALT')) $salt .= SECURE_AUTH_SALT;
        if (defined('LOGGED_IN_SALT')) $salt .= LOGGED_IN_SALT;
        if (defined('NONCE_SALT')) $salt .= NONCE_SALT;
        
        // Make sure we have at least some data
        if (empty($salt)) {
            $salt = DB_NAME . DB_USER . DB_PASSWORD . DB_HOST . site_url();
        }
        
        // Add additional WP-specific data to make the key more unique to this site
        $salt .= get_option('siteurl') . get_option('admin_email');
        
        // Generate a key suitable for secretbox (must be SODIUM_CRYPTO_SECRETBOX_KEYBYTES bytes)
        return sodium_crypto_generichash($salt, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    /**
     * Get the public key.
     *
     * @return string The public key.
     */
    public function get_public_key() {
        $public_key = get_option( 'pog_public_key' );

        if ( empty( $public_key ) ) {
            $this->maybe_generate_keys();
            $public_key = get_option( 'pog_public_key' );
        }

        return base64_decode( $public_key );
    }

    /**
     * Generate a cryptographically secure random nonce.
     *
     * @param int $length The length of the nonce in bytes.
     * @return string The generated nonce.
     */
    public function generate_nonce( $length = 16 ) {
        return random_bytes( $length );
    }

    /**
     * Create a signed token with the given amount.
     *
     * @param int $amount The amount to encode in the token.
     * @return string The generated token.
     */
    public function create_token( $amount ) {
        // Validate input is numeric before casting
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException( __( 'Amount must be a valid number.', 'proof-of-gift' ) );
        }
        
        // Validate amount
        $amount = intval( $amount );
        if ( $amount <= 0 ) {
            throw new \InvalidArgumentException( __( 'Amount must be greater than zero.', 'proof-of-gift' ) );
        }

        // Generate a random nonce.
        $nonce = $this->generate_nonce();
        
        // Include the amount directly in the message
        $amount_bytes = pack( 'N', $amount );
        
        // Create the message to sign (nonce + amount).
        $message = $nonce . $amount_bytes;

        // Sign the message.
        $signature = sodium_crypto_sign_detached( $message, $this->get_private_key() );

        // Encode the nonce, amount and signature using base64url.
        $nonce_encoded = $this->base64url_encode( $nonce );
        $amount_encoded = $this->base64url_encode( $amount_bytes );
        $signature_encoded = $this->base64url_encode( $signature );

        // Combine the parts to create the token with amount included.
        $token = $this->get_token_prefix() . self::TOKEN_SEPARATOR . 
                 $nonce_encoded . self::TOKEN_SEPARATOR . 
                 $amount_encoded . self::TOKEN_SEPARATOR . 
                 $signature_encoded;

        return $token;
    }

    /**
     * Verify a token and extract its amount.
     *
     * @param string $token The token to verify.
     * @return array|false The token data if valid, false otherwise.
     */
    public function verify_token( $token ) {
        // Basic validation
        if (empty($token) || !is_string($token)) {
            error_log('Proof Of Gift: Token is empty or not a string');
            return false;
        }
        
        // Get parts from token
        $parts = explode(self::TOKEN_SEPARATOR, $token);
        
        // Must have exactly 4 parts: PREFIX-NONCE-AMOUNT-SIGNATURE
        if (count($parts) !== 4) {
            error_log('Proof Of Gift: Token must have exactly 4 parts, has ' . count($parts));
            return false;
        }
        
        // Get our configured prefix
        $prefix = $this->get_token_prefix();
        
        // Check if token starts with our prefix
        if ($parts[0] !== $prefix) {
            error_log('Proof Of Gift: Token has incorrect prefix: ' . $parts[0] . ', expected: ' . $prefix);
            return false;
        }
        
        // Verify the token using the standard 4-part format
        return $this->verify_four_part_token($parts);
    }
    
    /**
     * Verify a token in the standard format (PREFIX-NONCE-AMOUNT-SIGNATURE).
     *
     * @param array $parts The token parts.
     * @return array|false The token data if valid, false otherwise.
     */
    private function verify_four_part_token($parts) {
        try {
            // Extract parts
            $prefix = $parts[0];
            $nonce_part = $parts[1];
            $amount_part = $parts[2];
            $signature_part = $parts[3];
            
            // Decode the parts
            $nonce = $this->base64url_decode($nonce_part);
            $amount_bytes = $this->base64url_decode($amount_part);
            $signature = $this->base64url_decode($signature_part);
            
            // Check if decoding was successful
            if (false === $nonce || false === $amount_bytes || false === $signature) {
                error_log('Proof Of Gift: Failed to decode token parts');
                return false;
            }
            
            // Verify signature has correct length (64 bytes for Ed25519)
            if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
                error_log('Proof Of Gift: Invalid signature length: ' . strlen($signature) . ' bytes, expected ' . SODIUM_CRYPTO_SIGN_BYTES);
                return false;
            }
            
            // Extract amount from the binary data
            if (strlen($amount_bytes) !== 4) {
                error_log('Proof Of Gift: Invalid amount length: ' . strlen($amount_bytes) . ' bytes, expected 4');
                return false;
            }
            
            // Unpack amount from network byte order
            $amount = unpack('N', $amount_bytes)[1];
            error_log('Proof Of Gift: Token amount decoded as: ' . $amount);
            
            // Create the message to verify (nonce + amount)
            $message = $nonce . $amount_bytes;
            
            // Verify the signature
            if (sodium_crypto_sign_verify_detached($signature, $message, $this->get_public_key())) {
                error_log('Proof Of Gift: Token verified successfully');
                return array(
                    'token'  => implode(self::TOKEN_SEPARATOR, $parts),
                    'amount' => $amount,
                    'nonce'  => $nonce,
                    'valid'  => true,
                );
            } else {
                error_log('Proof Of Gift: Token signature verification failed');
            }
        } catch (\Exception $e) {
            error_log('Proof Of Gift: Exception in token verification: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Encode data using alphanumeric characters only, avoiding hyphens and periods
     * since we use hyphens as token separators and want to avoid URL confusion.
     *
     * @param string $data The data to encode.
     * @return string The encoded data.
     */
    private function base64url_encode( $data ) {
        // Standard base64 encoding
        $base64 = base64_encode( $data );
        
        // Convert to alphanumeric characters only
        // Replace +/ with alternative characters that are alphanumeric
        $encoded = rtrim(strtr($base64, '+/', 'Aa'), '=');
        
        return $encoded;
    }

    /**
     * Decode our custom alphanumeric encoded data.
     *
     * @param string $data The data to decode.
     * @return string|false The decoded data or false on failure.
     */
    private function base64url_decode( $data ) {
        if (empty($data)) {
            return false;
        }
        
        try {
            // Clean the input, allowing only alphanumeric chars (no hyphens or periods)
            $cleaned_data = preg_replace('/[^A-Za-z0-9]/', '', $data);
            
            // Convert from our encoding to standard base64
            $base64 = strtr($cleaned_data, 'Aa', '+/');
            
            // Add padding if necessary
            $padded = str_pad($base64, strlen($cleaned_data) + (4 - strlen($cleaned_data) % 4) % 4, '=');
            
            // Decode
            $decoded = base64_decode($padded);
            
            return $decoded;
        } catch (\Exception $e) {
            error_log('Proof Of Gift: Exception in base64url_decode: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert a legacy token to the new format.
     * This is useful for upgrading existing tokens to the new format that includes the amount.
     *
     * @param string $token The legacy token.
     * @param int $amount The known amount of the token.
     * @return string|false The new format token, or false if conversion fails.
     */
    public function convert_token_to_new_format($token, $amount) {
        // Verify it's a valid legacy token first
        $verification = $this->verify_token($token);
        
        if (!$verification || !$verification['valid']) {
            return false;
        }
        
        // Get the parts
        $parts = explode(self::TOKEN_SEPARATOR, $token);
        
        // If it's already in the new format
        if (count($parts) == 4) {
            return $token;
        }
        
        // For legacy 3-part tokens (PREFIX-NONCE-SIGNATURE)
        if (count($parts) == 3) {
            $prefix = $parts[0];
            $nonce_part = $parts[1];
            $signature_part = $parts[2];
            
            // Decode the nonce
            $nonce = $this->base64url_decode($nonce_part);
            
            if (false === $nonce) {
                return false;
            }
            
            // Create amount bytes
            $amount_bytes = pack('N', $amount);
            
            // Encode amount in base64url
            $amount_encoded = $this->base64url_encode($amount_bytes);
            
            // Construct the new token
            $new_token = $prefix . self::TOKEN_SEPARATOR . 
                        $nonce_part . self::TOKEN_SEPARATOR . 
                        $amount_encoded . self::TOKEN_SEPARATOR . 
                        $signature_part;
            
            // Verify the new token works
            $new_verification = $this->verify_token($new_token);
            
            if ($new_verification && $new_verification['valid'] && $new_verification['amount'] == $amount) {
                return $new_token;
            }
        }
        
        return false;
    }
}