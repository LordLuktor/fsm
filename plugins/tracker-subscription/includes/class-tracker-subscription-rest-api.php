<?php
/**
 * Tracker Subscription REST API - Checkout, portal, and feature endpoints via PMPro.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Subscription_REST_API {

    public static function register_routes() {
        $ns = TRACKER_API_NAMESPACE;

        // Get current subscription status
        register_rest_route( $ns, '/subscription', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_subscription' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_access' );
            },
        ) );

        // Create Stripe Checkout session
        register_rest_route( $ns, '/subscription/checkout', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'create_checkout' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_manage_subscription' );
            },
        ) );

        // Create Stripe Customer Portal session
        register_rest_route( $ns, '/subscription/portal', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'create_portal' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_manage_subscription' );
            },
        ) );

        // Feature status
        register_rest_route( $ns, '/subscription/features', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_features' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_access' );
            },
        ) );
    }

    /**
     * GET /subscription - Current subscription status via PMPro.
     */
    public static function get_subscription( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $owner_id = (int) get_post_meta( $family_id, '_tracker_owner_user_id', true );
        $features = Tracker_Feature_Gate::get_feature_status( $family_id );

        // Get PMPro membership for family owner
        $subscription_data = array(
            'plan'   => 'free',
            'status' => 'active',
        );

        if ( $owner_id && function_exists( 'pmpro_getMembershipLevelForUser' ) ) {
            $level = pmpro_getMembershipLevelForUser( $owner_id );

            $ultimate_level_id = Tracker_PMPro_Hooks::get_ultimate_level_id();
            $premium_level_id  = Tracker_PMPro_Hooks::get_premium_level_id();

            if ( $level && $ultimate_level_id && $level->id == $ultimate_level_id ) {
                $subscription_data = array(
                    'plan'               => 'ultimate',
                    'status'             => 'active',
                    'current_period_end' => ! empty( $level->enddate ) ? gmdate( 'Y-m-d H:i:s', $level->enddate ) : null,
                );
            } elseif ( $level && $premium_level_id && $level->id == $premium_level_id ) {
                $subscription_data = array(
                    'plan'               => 'premium',
                    'status'             => 'active',
                    'current_period_end' => ! empty( $level->enddate ) ? gmdate( 'Y-m-d H:i:s', $level->enddate ) : null,
                );
            }
        }

        return rest_ensure_response( array(
            'subscription' => $subscription_data,
            'features'     => $features,
        ) );
    }

    /**
     * POST /subscription/checkout - Create Stripe Checkout session via PMPro.
     * Accepts a 'plan' parameter: 'premium' (default) or 'ultimate'.
     */
    public static function create_checkout( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );
        $plan      = sanitize_text_field( $request->get_param( 'plan' ) ?: 'premium' );

        if ( ! in_array( $plan, array( 'premium', 'ultimate' ), true ) ) {
            return new WP_Error( 'invalid_plan', 'Plan must be premium or ultimate.', array( 'status' => 400 ) );
        }

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        if ( ! Tracker_Core::is_family_owner( $user_id, $family_id ) ) {
            return new WP_Error( 'not_owner', 'Only the family owner can manage the subscription.', array( 'status' => 403 ) );
        }

        if ( ! function_exists( 'pmpro_getOption' ) ) {
            return new WP_Error( 'pmpro_not_active', 'PMPro is not active.', array( 'status' => 500 ) );
        }

        $stripe_secret = pmpro_getOption( 'stripe_secretkey' );
        if ( empty( $stripe_secret ) ) {
            return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.', array( 'status' => 500 ) );
        }

        // Determine level and price based on plan
        if ( 'ultimate' === $plan ) {
            $level_id = Tracker_PMPro_Hooks::get_ultimate_level_id();
            if ( ! $level_id ) {
                return new WP_Error( 'no_ultimate_level', 'Ultimate membership level not found.', array( 'status' => 500 ) );
            }
            $price_id = self::get_or_create_stripe_price( $stripe_secret, 'ultimate' );
        } else {
            $level_id = Tracker_PMPro_Hooks::get_premium_level_id();
            if ( ! $level_id ) {
                return new WP_Error( 'no_premium_level', 'Premium membership level not found.', array( 'status' => 500 ) );
            }
            $price_id = self::get_or_create_stripe_price( $stripe_secret, 'premium' );
        }

        if ( is_wp_error( $price_id ) ) {
            return $price_id;
        }

        $success_url = defined( 'TRACKER_FRONTEND_URL' ) ? TRACKER_FRONTEND_URL . '/settings?checkout=success' : home_url( '/settings?checkout=success' );
        $cancel_url  = defined( 'TRACKER_FRONTEND_URL' ) ? TRACKER_FRONTEND_URL . '/settings?checkout=canceled' : home_url( '/settings?checkout=canceled' );

        $user  = get_user_by( 'id', $user_id );
        $email = $user->user_email;

        // Check for existing Stripe customer
        $customer_id = get_user_meta( $user_id, 'pmpro_stripe_customerid', true );

        // Create PMPro MemberOrder in token status
        if ( class_exists( 'MemberOrder' ) ) {
            $morder                  = new MemberOrder();
            $morder->user_id         = $user_id;
            $morder->membership_id   = $level_id;
            $morder->status          = 'token';
            $morder->gateway         = 'stripe';
            $morder->saveOrder();
        }

        // Build Stripe Checkout Session
        $session_data = array(
            'mode'                => 'subscription',
            'success_url'         => $success_url,
            'cancel_url'          => $cancel_url,
            'line_items[0][price]'    => $price_id,
            'line_items[0][quantity]' => 1,
            'metadata[family_id]'     => $family_id,
            'metadata[user_id]'       => $user_id,
            'metadata[plan]'          => $plan,
            'metadata[pmpro_level_id]' => $level_id,
            'subscription_data[metadata][family_id]' => $family_id,
            'subscription_data[metadata][user_id]'   => $user_id,
            'subscription_data[metadata][plan]'      => $plan,
        );

        if ( $customer_id ) {
            $session_data['customer'] = $customer_id;
        } else {
            $session_data['customer_email'] = $email;
        }

        $response = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $stripe_secret,
            ),
            'body' => $session_data,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'stripe_error', $response->get_error_message(), array( 'status' => 500 ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new WP_Error( 'stripe_error', 'Payment processing failed. Please try again.', array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'checkout_url' => $body['url'],
            'session_id'   => $body['id'],
        ) );
    }

    /**
     * POST /subscription/portal - Create Stripe Customer Portal session.
     */
    public static function create_portal( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $customer_id = get_user_meta( $user_id, 'pmpro_stripe_customerid', true );
        if ( ! $customer_id ) {
            return new WP_Error( 'no_subscription', 'No active subscription found.', array( 'status' => 404 ) );
        }

        if ( ! function_exists( 'pmpro_getOption' ) ) {
            return new WP_Error( 'pmpro_not_active', 'PMPro is not active.', array( 'status' => 500 ) );
        }

        $stripe_secret = pmpro_getOption( 'stripe_secretkey' );
        $return_url    = defined( 'TRACKER_FRONTEND_URL' ) ? TRACKER_FRONTEND_URL . '/settings' : home_url( '/settings' );

        $response = wp_remote_post( 'https://api.stripe.com/v1/billing_portal/sessions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $stripe_secret,
            ),
            'body' => array(
                'customer'   => $customer_id,
                'return_url' => $return_url,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'stripe_error', $response->get_error_message(), array( 'status' => 500 ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new WP_Error( 'stripe_error', 'Payment processing failed. Please try again.', array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'portal_url' => $body['url'],
        ) );
    }

    /**
     * GET /subscription/features - Feature availability.
     */
    public static function get_features( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return rest_ensure_response( Tracker_Feature_Gate::get_feature_status( 0 ) );
        }

        return rest_ensure_response( Tracker_Feature_Gate::get_feature_status( $family_id ) );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Get or create a Stripe Price for the given plan.
     * Creates a Product + Price on first use and caches the price ID in wp_options.
     *
     * @param string $stripe_secret Stripe secret key.
     * @param string $plan          'premium' or 'ultimate'.
     * @return string|WP_Error Price ID or error.
     */
    private static function get_or_create_stripe_price( $stripe_secret, $plan = 'premium' ) {
        $plan_config = array(
            'premium' => array(
                'option_key'  => 'tracker_stripe_premium_price_id',
                'env_key'     => 'TRACKER_STRIPE_PREMIUM_PRICE_ID',
                'name'        => 'Tracker Premium',
                'description' => 'Premium GPS Tracker membership - geofences, history, sharing, up to 8 members.',
                'amount'      => 999, // $9.99
            ),
            'ultimate' => array(
                'option_key'  => 'tracker_stripe_ultimate_price_id',
                'env_key'     => 'TRACKER_STRIPE_ULTIMATE_PRICE_ID',
                'name'        => 'Tracker Ultimate',
                'description' => 'Ultimate GPS Tracker membership - all Premium features plus Push-to-Talk, unlimited members.',
                'amount'      => 2495, // $24.95
            ),
        );

        $config = $plan_config[ $plan ] ?? $plan_config['premium'];

        // Check for env var / defined constant first
        if ( defined( $config['env_key'] ) && constant( $config['env_key'] ) && 'price_placeholder' !== constant( $config['env_key'] ) ) {
            return constant( $config['env_key'] );
        }

        // Check cached option
        $cached_price_id = get_option( $config['option_key'] );
        if ( $cached_price_id ) {
            return $cached_price_id;
        }

        // Create Product
        $product_response = wp_remote_post( 'https://api.stripe.com/v1/products', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $stripe_secret,
            ),
            'body' => array(
                'name'        => $config['name'],
                'description' => $config['description'],
            ),
        ) );

        if ( is_wp_error( $product_response ) ) {
            return new WP_Error( 'stripe_error', $product_response->get_error_message(), array( 'status' => 500 ) );
        }

        $product = json_decode( wp_remote_retrieve_body( $product_response ), true );
        if ( isset( $product['error'] ) ) {
            return new WP_Error( 'stripe_error', $product['error']['message'], array( 'status' => 400 ) );
        }

        // Create Price
        $price_response = wp_remote_post( 'https://api.stripe.com/v1/prices', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $stripe_secret,
            ),
            'body' => array(
                'product'              => $product['id'],
                'unit_amount'          => $config['amount'],
                'currency'             => 'usd',
                'recurring[interval]'  => 'month',
            ),
        ) );

        if ( is_wp_error( $price_response ) ) {
            return new WP_Error( 'stripe_error', $price_response->get_error_message(), array( 'status' => 500 ) );
        }

        $price = json_decode( wp_remote_retrieve_body( $price_response ), true );
        if ( isset( $price['error'] ) ) {
            return new WP_Error( 'stripe_error', $price['error']['message'], array( 'status' => 400 ) );
        }

        update_option( $config['option_key'], $price['id'] );

        return $price['id'];
    }
}
