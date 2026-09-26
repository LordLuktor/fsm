<?php
/**
 * FSM Crypto
 *
 * At-rest encryption for stored secrets (payment processor API keys/secrets).
 * Uses AES-256-CBC with a key derived from the site's WordPress auth salt, so
 * the ciphertext in the database is useless without the salts in
 * wp-config.php. Defense-in-depth, not a substitute for keeping the DB and
 * wp-config.php secure. Mirrors the proven pattern from the
 * steinmetz-paypal-invoicing plugin (SPPI_Crypto).
 *
 * @package FSM\Core
 * @since 1.2.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Crypto class.
 */
class FSM_Crypto {

    /**
     * Cipher used for encryption.
     */
    const CIPHER = 'aes-256-cbc';

    /**
     * Marks an encrypted value and its scheme version.
     */
    const PREFIX = 'fsm_enc_v1:';

    /**
     * Derive a stable 32-byte key from the site's auth salt.
     *
     * @return string Raw 32-byte key.
     */
    private static function key() {
        // wp_salt() is unique per site and lives in wp-config.php, not the DB.
        return hash('sha256', wp_salt('auth') . '|fsm_crypto', true);
    }

    /**
     * Encrypt a plaintext secret. Returns a self-describing string safe to store.
     *
     * @param string $plaintext Secret to encrypt.
     * @return string Encrypted, prefixed, base64 payload — or '' for empty input.
     */
    public static function encrypt($plaintext) {
        $plaintext = (string) $plaintext;
        if ('' === $plaintext) {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            // Without OpenSSL we refuse to silently store plaintext; surface loudly.
            error_log('FSM_Crypto: OpenSSL unavailable — cannot encrypt secret at rest.');
            return '';
        }

        $iv_len = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($iv_len);
        $cipher = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);

        if (false === $cipher) {
            error_log('FSM_Crypto: openssl_encrypt failed.');
            return '';
        }

        return self::PREFIX . base64_encode($iv . $cipher);
    }

    /**
     * Decrypt a value produced by encrypt(). Passes through anything not
     * carrying our prefix (e.g. unencrypted legacy/manual entry) unchanged.
     *
     * @param string $stored Stored value.
     * @return string Plaintext secret.
     */
    public static function decrypt($stored) {
        $stored = (string) $stored;
        if ('' === $stored) {
            return '';
        }

        if (0 !== strpos($stored, self::PREFIX)) {
            return $stored;
        }

        if (!function_exists('openssl_decrypt')) {
            error_log('FSM_Crypto: OpenSSL unavailable — cannot decrypt secret.');
            return '';
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        $iv_len = openssl_cipher_iv_length(self::CIPHER);

        if (false === $raw || strlen($raw) <= $iv_len) {
            return '';
        }

        $iv = substr($raw, 0, $iv_len);
        $cipher = substr($raw, $iv_len);
        $plaintext = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);

        return false === $plaintext ? '' : $plaintext;
    }

    /**
     * Whether a stored value is one of our encrypted blobs.
     *
     * @param string $stored Stored value.
     * @return bool
     */
    public static function is_encrypted($stored) {
        return is_string($stored) && 0 === strpos($stored, self::PREFIX);
    }

    /**
     * Mask a secret for display: keep a few leading characters, blank the rest.
     * Used so a saved settings screen can confirm "something is set" without
     * ever re-echoing the actual secret back to the browser.
     *
     * @param string $plaintext Secret to mask.
     * @return string e.g. "sk_live_ab••••••••" or '' if empty.
     */
    public static function mask($plaintext) {
        $plaintext = (string) $plaintext;
        if ('' === $plaintext) {
            return '';
        }

        $visible = substr($plaintext, 0, min(8, strlen($plaintext)));
        return $visible . str_repeat('•', 8);
    }
}
