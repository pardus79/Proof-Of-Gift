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
     * The prefix for gift tokens.
     *
     * @var string
     */
    const TOKEN_PREFIX = 'POG';

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

            // Store the keys.
            update_option( 'pog_private_key', base64_encode( $private_key ) );
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
        $private_key = get_option( 'pog_private_key' );

        if ( empty( $private_key ) ) {
            $this->maybe_generate_keys();
            $private_key = get_option( 'pog_private_key' );
        }

        return base64_decode( $private_key );
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
        // Validate amount.
        $amount = intval( $amount );
        if ( $amount <= 0 ) {
            throw new \InvalidArgumentException( __( 'Amount must be greater than zero.', 'proof-of-gift' ) );
        }

        // Generate a random nonce.
        $nonce = $this->generate_nonce();

        // Create the message to sign (nonce + amount).
        $message = $nonce . pack( 'N', $amount );

        // Sign the message.
        $signature = sodium_crypto_sign_detached( $message, $this->get_private_key() );

        // Encode the nonce and signature using base64url.
        $nonce_encoded = $this->base64url_encode( $nonce );
        $signature_encoded = $this->base64url_encode( $signature );

        // Combine the parts to create the token.
        $token = self::TOKEN_PREFIX . self::TOKEN_SEPARATOR . $nonce_encoded . self::TOKEN_SEPARATOR . $signature_encoded;

        return $token;
    }

    /**
     * Verify a token and extract its amount.
     *
     * @param string $token The token to verify.
     * @return array|false The token data if valid, false otherwise.
     */
    public function verify_token( $token ) {
        // Parse the token.
        $parts = explode( self::TOKEN_SEPARATOR, $token );

        if ( count( $parts ) !== 3 || $parts[0] !== self::TOKEN_PREFIX ) {
            return false;
        }

        // Decode the nonce and signature.
        $nonce = $this->base64url_decode( $parts[1] );
        $signature = $this->base64url_decode( $parts[2] );

        if ( false === $nonce || false === $signature ) {
            return false;
        }

        // Try all possible amounts until we find a valid signature.
        // This is a bit inefficient but allows us to not store the amount in the token directly.
        // For a production system with large amounts, we might want to use a different approach.
        for ( $amount = 1; $amount <= 1000000; $amount++ ) {
            $message = $nonce . pack( 'N', $amount );

            if ( sodium_crypto_sign_verify_detached( $signature, $message, $this->get_public_key() ) ) {
                return array(
                    'token'  => $token,
                    'amount' => $amount,
                    'nonce'  => $nonce,
                    'valid'  => true,
                );
            }
        }

        // No valid amount found.
        return false;
    }

    /**
     * Encode data using base64url.
     *
     * @param string $data The data to encode.
     * @return string The encoded data.
     */
    private function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Decode base64url encoded data.
     *
     * @param string $data The data to decode.
     * @return string|false The decoded data or false on failure.
     */
    private function base64url_decode( $data ) {
        $base64 = strtr( $data, '-_', '+/' );
        $padded = str_pad( $base64, strlen( $data ) + ( 4 - strlen( $data ) % 4 ) % 4, '=' );
        return base64_decode( $padded );
    }
}