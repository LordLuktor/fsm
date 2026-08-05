<?php
/**
 * FSM SMTP Configuration
 *
 * Multi-layer SMTP routing:
 * 1. Per-org SMTP: If the current org has SMTP configured, use it (client-to-customer emails)
 * 2. Global SMTP: Fall back to platform SMTP from environment variables (platform-to-client emails)
 * 3. Default: WordPress default sendmail (won't deliver in Docker)
 *
 * Per-org SMTP credentials are encrypted at rest using WordPress AUTH_KEY.
 *
 * @package FSM
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// ============================================================================
// WP Mail SMTP Plugin — Constant-Based Configuration
// ============================================================================
// These constants configure the WP Mail SMTP plugin without needing the DB.
// The plugin reads these automatically and locks the fields in the admin UI.

if ( ! defined( 'WPMS_ON' ) ) {
    define( 'WPMS_ON', true );
    define( 'WPMS_MAILER', 'smtp' );
    define( 'WPMS_SMTP_HOST', getenv( 'SMTP_HOST' ) ?: 'mail.supremecluster.com' );
    define( 'WPMS_SMTP_PORT', (int) ( getenv( 'SMTP_PORT' ) ?: 465 ) );
    define( 'WPMS_SSL', getenv( 'SMTP_ENCRYPTION' ) ?: 'ssl' );
    define( 'WPMS_SMTP_AUTH', true );
    define( 'WPMS_SMTP_USER', getenv( 'SMTP_USER' ) ?: '' );
    define( 'WPMS_MAIL_FROM', getenv( 'SMTP_FROM_EMAIL' ) ?: 'admin@steinmetz.ltd' );
    define( 'WPMS_MAIL_FROM_FORCE', true );
    define( 'WPMS_MAIL_FROM_NAME', getenv( 'SMTP_FROM_NAME' ) ?: 'Steinmetz FSM' );
    define( 'WPMS_MAIL_FROM_NAME_FORCE', false );

    // Read SMTP password from Docker secret file
    $smtp_secret = getenv( 'SMTP_PASSWORD_FILE' ) ?: '/run/secrets/smtp_password';
    $smtp_pass   = file_exists( $smtp_secret ) ? trim( file_get_contents( $smtp_secret ) ) : '';
    define( 'WPMS_SMTP_PASS', $smtp_pass );
}

// ============================================================================
// PHPMailer Configuration
// ============================================================================

/**
 * Configure PHPMailer with org-specific or global SMTP settings.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
 */
function fsm_configure_smtp( $phpmailer ) {
    // Try per-org SMTP first
    $org_smtp = fsm_get_org_smtp_config();

    if ( $org_smtp && ! empty( $org_smtp['host'] ) && ! empty( $org_smtp['username'] ) ) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = $org_smtp['host'];
        $phpmailer->Port       = (int) ( $org_smtp['port'] ?: 465 );
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = $org_smtp['username'];
        $phpmailer->Password   = $org_smtp['password'];
        $phpmailer->SMTPSecure = $org_smtp['encryption'] ?: 'ssl';

        if ( ! empty( $org_smtp['from_email'] ) ) {
            $phpmailer->From     = $org_smtp['from_email'];
            $phpmailer->FromName = $org_smtp['from_name'] ?: $phpmailer->FromName;
        }

        return;
    }

    // Global SMTP fallback is handled by WP Mail SMTP plugin.
    // This mu-plugin only handles per-org SMTP overrides above.
}
add_action( 'phpmailer_init', 'fsm_configure_smtp', 10 );

/**
 * Override the "From" email via wp_mail_from filter.
 * This fires before phpmailer_init and ensures the From matches the SMTP account.
 */
function fsm_smtp_from_email( $from ) {
    $org_smtp = fsm_get_org_smtp_config();
    if ( $org_smtp && ! empty( $org_smtp['from_email'] ) ) {
        return $org_smtp['from_email'];
    }
    return $from; // Let WP Mail SMTP handle the global default
}
add_filter( 'wp_mail_from', 'fsm_smtp_from_email', 99 );

/**
 * Override the "From" name via wp_mail_from_name filter.
 */
function fsm_smtp_from_name( $name ) {
    $org_smtp = fsm_get_org_smtp_config();
    if ( $org_smtp && ! empty( $org_smtp['from_name'] ) ) {
        return $org_smtp['from_name'];
    }
    return $name; // Let WP Mail SMTP handle the global default
}
add_filter( 'wp_mail_from_name', 'fsm_smtp_from_name', 99 );

// ============================================================================
// Per-Org SMTP Settings
// ============================================================================

/**
 * Get SMTP configuration for the current organization.
 *
 * @return array|null SMTP config or null if not configured.
 */
function fsm_get_org_smtp_config() {
    if ( ! class_exists( 'FSM_Organizations' ) ) {
        return null;
    }

    $org_id = FSM_Organizations::get_current_context();
    if ( ! $org_id ) {
        // Try getting org from the current user
        $user_org = get_user_meta( get_current_user_id(), '_fsm_organization_id', true );
        if ( $user_org ) {
            $org_id = (int) $user_org;
        }
    }

    if ( ! $org_id ) {
        return null;
    }

    $config = get_post_meta( $org_id, '_fsm_smtp_config', true );
    if ( ! is_array( $config ) || empty( $config['host'] ) ) {
        return null;
    }

    // Decrypt password
    if ( ! empty( $config['password'] ) ) {
        $config['password'] = fsm_decrypt_smtp_password( $config['password'] );
    }

    return $config;
}

// ============================================================================
// Password Encryption
// ============================================================================

/**
 * Encrypt an SMTP password for storage.
 *
 * Uses AES-256-CBC with WordPress AUTH_KEY as the key.
 *
 * @param string $plaintext Password to encrypt.
 * @return string Base64-encoded encrypted string.
 */
function fsm_encrypt_smtp_password( $plaintext ) {
    if ( empty( $plaintext ) ) {
        return '';
    }

    $key    = hash( 'sha256', AUTH_KEY, true );
    $iv     = openssl_random_pseudo_bytes( 16 );
    $cipher = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

    if ( false === $cipher ) {
        return '';
    }

    return base64_encode( $iv . $cipher );
}

/**
 * Decrypt an SMTP password from storage.
 *
 * @param string $encrypted Base64-encoded encrypted string.
 * @return string Decrypted password.
 */
function fsm_decrypt_smtp_password( $encrypted ) {
    if ( empty( $encrypted ) ) {
        return '';
    }

    $data = base64_decode( $encrypted, true );
    if ( false === $data || strlen( $data ) < 17 ) {
        return '';
    }

    $key    = hash( 'sha256', AUTH_KEY, true );
    $iv     = substr( $data, 0, 16 );
    $cipher = substr( $data, 16 );

    $plaintext = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

    return ( false === $plaintext ) ? '' : $plaintext;
}

// ============================================================================
// REST API Endpoints for Org SMTP Configuration
// ============================================================================

/**
 * Register SMTP settings REST endpoints.
 */
function fsm_register_smtp_routes() {
    register_rest_route( 'fsm/v1', '/settings/smtp', [
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'fsm_get_smtp_settings',
            'permission_callback' => function() {
                return current_user_can( 'fsm_manage_settings' ) || current_user_can( 'manage_options' );
            },
        ],
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'fsm_update_smtp_settings',
            'permission_callback' => function() {
                return current_user_can( 'fsm_manage_settings' ) || current_user_can( 'manage_options' );
            },
        ],
    ] );

    register_rest_route( 'fsm/v1', '/settings/smtp/test', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'fsm_test_smtp_settings',
        'permission_callback' => function() {
            return current_user_can( 'fsm_manage_settings' ) || current_user_can( 'manage_options' );
        },
        'args' => [
            'to' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'fsm_register_smtp_routes' );

/**
 * Get SMTP settings for the current org.
 */
function fsm_get_smtp_settings() {
    $org_id = FSM_Organizations::get_current_context();
    if ( ! $org_id ) {
        $org_id = get_user_meta( get_current_user_id(), '_fsm_organization_id', true );
    }

    if ( ! $org_id ) {
        return rest_ensure_response( fsm_smtp_defaults() );
    }

    $config = get_post_meta( (int) $org_id, '_fsm_smtp_config', true );
    if ( ! is_array( $config ) ) {
        $config = [];
    }

    $defaults = fsm_smtp_defaults();
    $merged   = wp_parse_args( $config, $defaults );

    // Never return the actual password — just indicate if one is set
    $merged['password_set'] = ! empty( $config['password'] );
    unset( $merged['password'] );

    return rest_ensure_response( $merged );
}

/**
 * Update SMTP settings for the current org.
 */
function fsm_update_smtp_settings( $request ) {
    $org_id = FSM_Organizations::get_current_context();
    if ( ! $org_id ) {
        $org_id = get_user_meta( get_current_user_id(), '_fsm_organization_id', true );
    }

    if ( ! $org_id ) {
        return new WP_Error( 'no_org', 'No organization context.', [ 'status' => 400 ] );
    }

    $existing = get_post_meta( (int) $org_id, '_fsm_smtp_config', true );
    if ( ! is_array( $existing ) ) {
        $existing = [];
    }

    $config = [
        'host'       => sanitize_text_field( $request->get_param( 'host' ) ?? '' ),
        'port'       => absint( $request->get_param( 'port' ) ?? 465 ),
        'encryption' => in_array( $request->get_param( 'encryption' ), [ 'ssl', 'tls', '' ], true )
            ? $request->get_param( 'encryption' )
            : 'ssl',
        'username'   => sanitize_text_field( $request->get_param( 'username' ) ?? '' ),
        'from_name'  => sanitize_text_field( $request->get_param( 'from_name' ) ?? '' ),
        'from_email' => sanitize_email( $request->get_param( 'from_email' ) ?? '' ),
    ];

    // Only update password if a new one is provided (don't clear existing)
    $new_password = $request->get_param( 'password' );
    if ( ! empty( $new_password ) ) {
        $config['password'] = fsm_encrypt_smtp_password( $new_password );
    } elseif ( ! empty( $existing['password'] ) ) {
        $config['password'] = $existing['password']; // Keep existing encrypted password
    } else {
        $config['password'] = '';
    }

    update_post_meta( (int) $org_id, '_fsm_smtp_config', $config );

    // Return sanitized config without password
    $response = $config;
    $response['password_set'] = ! empty( $config['password'] );
    unset( $response['password'] );

    return rest_ensure_response( [
        'success'  => true,
        'message'  => 'SMTP settings saved.',
        'settings' => $response,
    ] );
}

/**
 * Send a test email using the current org's SMTP configuration.
 */
function fsm_test_smtp_settings( $request ) {
    $to = $request->get_param( 'to' );

    if ( ! is_email( $to ) ) {
        return new WP_Error( 'invalid_email', 'Invalid email address.', [ 'status' => 400 ] );
    }

    $org_id = FSM_Organizations::get_current_context();
    if ( ! $org_id ) {
        $org_id = get_user_meta( get_current_user_id(), '_fsm_organization_id', true );
    }

    $org_name = $org_id ? get_the_title( $org_id ) : 'FSM Platform';

    $subject = "Test Email from {$org_name}";
    $body    = "<h2>SMTP Configuration Test</h2>"
             . "<p>This is a test email from your FSM platform.</p>"
             . "<p>If you received this, your SMTP settings are configured correctly.</p>"
             . "<p><small>Sent at " . esc_html( current_time( 'r' ) ) . "</small></p>";

    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    // Capture SMTP debug output
    $debug_log = '';
    add_action( 'phpmailer_init', function( $pm ) use ( &$debug_log ) {
        $pm->SMTPDebug = 2;
        $pm->Debugoutput = function( $str ) use ( &$debug_log ) {
            $debug_log .= $str;
        };
    }, 999 );

    $sent = wp_mail( $to, $subject, $body, $headers );

    // Strip debug for response but keep key info
    $debug_summary = '';
    if ( $debug_log ) {
        // Extract key lines
        preg_match_all( '/SERVER -> CLIENT: (.+)/i', $debug_log, $matches );
        $debug_summary = implode( ' | ', array_slice( $matches[1] ?? [], 0, 10 ) );
    }

    if ( $sent ) {
        return rest_ensure_response( [
            'success' => true,
            'message' => "Test email sent to {$to}.",
            'debug'   => $debug_summary ?: 'No SMTP debug output (may be using PHP mail())',
        ] );
    }

    global $phpmailer;
    $error_msg = '';
    if ( isset( $phpmailer ) && $phpmailer->ErrorInfo ) {
        $error_msg = $phpmailer->ErrorInfo;
    }

    return new WP_Error( 'send_failed', 'Failed to send test email. ' . $error_msg . ' Debug: ' . $debug_summary, [ 'status' => 500 ] );
}

/**
 * Default SMTP settings.
 */
function fsm_smtp_defaults() {
    return [
        'host'       => '',
        'port'       => 465,
        'encryption' => 'ssl',
        'username'   => '',
        'from_name'  => '',
        'from_email' => '',
    ];
}
