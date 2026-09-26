<?php
/**
 * FSM Billing Payment Settings
 *
 * Per-organization payment processor credentials (BYO Stripe/PayPal — each
 * org connects their own account, money goes straight to them, no platform
 * cut). Secrets are encrypted at rest via FSM_Crypto and are deliberately
 * NOT exposed through register_post_meta/show_in_rest (a generic REST meta
 * read would otherwise leak the ciphertext to anyone with edit access to the
 * org) — every read/write goes through this class so encryption is applied
 * exactly once, explicitly, rather than relying on a sanitize-callback hook
 * that can double-fire (the exact bug class steinmetz-paypal-invoicing hit).
 *
 * @package FSM\Billing
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Billing Payment Settings class.
 */
class FSM_Billing_Payment_Settings {

    /**
     * Meta keys that hold secrets and must be encrypted at rest / never echoed back.
     *
     * @var array
     */
    const SECRET_KEYS = [
        '_fsm_org_stripe_secret_key',
        '_fsm_org_stripe_webhook_secret',
        '_fsm_org_paypal_client_secret',
    ];

    /**
     * All payment-settings meta keys (secret + non-secret).
     *
     * @var array
     */
    const ALL_KEYS = [
        '_fsm_org_default_processor',
        '_fsm_org_stripe_mode',
        '_fsm_org_stripe_publishable_key',
        '_fsm_org_stripe_secret_key',
        '_fsm_org_stripe_webhook_secret',
        '_fsm_org_paypal_mode',
        '_fsm_org_paypal_client_id',
        '_fsm_org_paypal_client_secret',
        '_fsm_org_paypal_webhook_id',
    ];

    /**
     * Register the meta fields. Deliberately show_in_rest = false — these must
     * only ever be read/written through this class's own capability-gated
     * methods, never via the generic /wp/v2/fsm_organization meta collection.
     */
    public static function register_meta() {
        foreach (self::ALL_KEYS as $key) {
            register_post_meta('fsm_organization', $key, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function() {
                    return current_user_can('fsm_manage_billing');
                },
            ]);
        }
    }

    /**
     * Get an org's payment settings, decrypted, for internal use by the gateway classes.
     * Never expose this array's secret fields directly in an API response.
     *
     * @param int $org_id Organization ID.
     * @return array
     */
    public static function get($org_id) {
        $settings = [
            'default_processor' => get_post_meta($org_id, '_fsm_org_default_processor', true) ?: '',
            'stripe_mode' => get_post_meta($org_id, '_fsm_org_stripe_mode', true) ?: 'test',
            'stripe_publishable_key' => get_post_meta($org_id, '_fsm_org_stripe_publishable_key', true) ?: '',
            'stripe_secret_key' => FSM_Crypto::decrypt(get_post_meta($org_id, '_fsm_org_stripe_secret_key', true)),
            'stripe_webhook_secret' => FSM_Crypto::decrypt(get_post_meta($org_id, '_fsm_org_stripe_webhook_secret', true)),
            'paypal_mode' => get_post_meta($org_id, '_fsm_org_paypal_mode', true) ?: 'sandbox',
            'paypal_client_id' => get_post_meta($org_id, '_fsm_org_paypal_client_id', true) ?: '',
            'paypal_client_secret' => FSM_Crypto::decrypt(get_post_meta($org_id, '_fsm_org_paypal_client_secret', true)),
            'paypal_webhook_id' => get_post_meta($org_id, '_fsm_org_paypal_webhook_id', true) ?: '',
        ];

        return $settings;
    }

    /**
     * Get a masked/safe-to-display view of an org's payment settings — booleans
     * for whether each secret is configured, plus the non-secret fields as-is.
     * This is what any REST/admin response should return, never get()'s output.
     *
     * @param int $org_id Organization ID.
     * @return array
     */
    public static function get_display($org_id) {
        $settings = self::get($org_id);

        return [
            'default_processor' => $settings['default_processor'],
            'stripe_mode' => $settings['stripe_mode'],
            'stripe_publishable_key' => $settings['stripe_publishable_key'],
            'stripe_secret_key_configured' => $settings['stripe_secret_key'] !== '',
            'stripe_webhook_secret_configured' => $settings['stripe_webhook_secret'] !== '',
            'paypal_mode' => $settings['paypal_mode'],
            'paypal_client_id' => $settings['paypal_client_id'],
            'paypal_client_secret_configured' => $settings['paypal_client_secret'] !== '',
            'paypal_webhook_id' => $settings['paypal_webhook_id'],
        ];
    }

    /**
     * Save payment settings for an org. Only overwrites secret fields when a
     * new value is actually submitted (empty string means "leave unchanged"),
     * mirroring the steinmetz-paypal-invoicing settings-page pattern so a
     * masked/blank field on save never wipes out an already-stored secret.
     *
     * @param int   $org_id Organization ID.
     * @param array $data   Raw (unencrypted) submitted values, keyed like the array from get().
     */
    public static function save($org_id, $data) {
        $processor = sanitize_key($data['default_processor'] ?? '');
        if (in_array($processor, ['stripe', 'paypal'], true)) {
            update_post_meta($org_id, '_fsm_org_default_processor', $processor);
        }

        $stripe_mode = sanitize_key($data['stripe_mode'] ?? 'test');
        update_post_meta($org_id, '_fsm_org_stripe_mode', in_array($stripe_mode, ['test', 'live'], true) ? $stripe_mode : 'test');

        if (isset($data['stripe_publishable_key'])) {
            update_post_meta($org_id, '_fsm_org_stripe_publishable_key', sanitize_text_field($data['stripe_publishable_key']));
        }

        self::save_secret_if_present($org_id, '_fsm_org_stripe_secret_key', $data['stripe_secret_key'] ?? null);
        self::save_secret_if_present($org_id, '_fsm_org_stripe_webhook_secret', $data['stripe_webhook_secret'] ?? null);

        $paypal_mode = sanitize_key($data['paypal_mode'] ?? 'sandbox');
        update_post_meta($org_id, '_fsm_org_paypal_mode', in_array($paypal_mode, ['sandbox', 'live'], true) ? $paypal_mode : 'sandbox');

        if (isset($data['paypal_client_id'])) {
            update_post_meta($org_id, '_fsm_org_paypal_client_id', sanitize_text_field($data['paypal_client_id']));
        }
        if (isset($data['paypal_webhook_id'])) {
            update_post_meta($org_id, '_fsm_org_paypal_webhook_id', sanitize_text_field($data['paypal_webhook_id']));
        }

        self::save_secret_if_present($org_id, '_fsm_org_paypal_client_secret', $data['paypal_client_secret'] ?? null);
    }

    /**
     * Encrypt and store a secret field, but only if a non-empty new value was submitted.
     *
     * @param int         $org_id Organization ID.
     * @param string      $meta_key Meta key.
     * @param string|null $value  Submitted plaintext value, or null/'' to leave unchanged.
     */
    private static function save_secret_if_present($org_id, $meta_key, $value) {
        if (null === $value || '' === $value) {
            return;
        }

        update_post_meta($org_id, $meta_key, FSM_Crypto::encrypt(sanitize_text_field($value)));
    }

    /**
     * Whether an org has at least one usable processor configured.
     *
     * @param int $org_id Organization ID.
     * @return bool
     */
    public static function has_any_processor($org_id) {
        $settings = self::get($org_id);
        return ($settings['stripe_secret_key'] !== '') || ($settings['paypal_client_secret'] !== '');
    }
}
