<?php
/**
 * FSM Security Hardening
 *
 * Centralized security hardening mu-plugin:
 * - Persistent audit logging (fsm_audit_log table)
 * - REST API audit trail via rest_post_dispatch
 * - Auth rate limiting (JWT, Tracker, demo endpoints)
 * - Demo endpoint abuse guard
 *
 * @package FSM
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// ============================================================================
// Audit Log Table Setup
// ============================================================================

/**
 * Create or update the audit log table.
 */
function fsm_security_create_audit_table() {
    $version     = '1.0';
    $current     = get_option( 'fsm_audit_log_version', '0' );

    if ( version_compare( $current, $version, '>=' ) ) {
        return;
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'fsm_audit_log';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_id BIGINT UNSIGNED NULL,
        actor_role VARCHAR(50) NULL,
        actor_email VARCHAR(255) NULL,
        action VARCHAR(100) NOT NULL,
        resource_type VARCHAR(50) NOT NULL,
        resource_id VARCHAR(50) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        outcome ENUM('success','denied','error') DEFAULT 'success',
        metadata LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_actor_id (actor_id),
        KEY idx_action (action),
        KEY idx_resource_type (resource_type),
        KEY idx_created_at (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    update_option( 'fsm_audit_log_version', $version );
}
add_action( 'init', 'fsm_security_create_audit_table' );

// ============================================================================
// Persistent Audit Log Handler
// ============================================================================

/**
 * Persist every fsm_activity_logged action to the audit table.
 *
 * @param array $log_entry {
 *     @type string $action    Action name.
 *     @type int    $user_id   Actor user ID.
 *     @type array  $data      Arbitrary context data.
 *     @type string $timestamp MySQL datetime.
 *     @type string $ip        Client IP.
 * }
 */
function fsm_persist_audit_log( $log_entry ) {
    global $wpdb;
    $table = $wpdb->prefix . 'fsm_audit_log';

    $user_id = isset( $log_entry['user_id'] ) ? absint( $log_entry['user_id'] ) : null;
    $user    = $user_id ? get_user_by( 'id', $user_id ) : null;

    $wpdb->insert(
        $table,
        array(
            'actor_id'    => $user_id ?: null,
            'actor_role'  => $user ? implode( ',', $user->roles ) : null,
            'actor_email' => $user ? $user->user_email : null,
            'action'      => sanitize_text_field( $log_entry['action'] ?? 'unknown' ),
            'resource_type' => 'activity',
            'resource_id' => null,
            'ip_address'  => sanitize_text_field( $log_entry['ip'] ?? '' ),
            'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] )
                ? sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) )
                : null,
            'outcome'     => 'success',
            'metadata'    => isset( $log_entry['data'] ) ? wp_json_encode( $log_entry['data'] ) : null,
            'created_at'  => $log_entry['timestamp'] ?? current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
    );
}
add_action( 'fsm_activity_logged', 'fsm_persist_audit_log' );

// ============================================================================
// REST API Audit Logging
// ============================================================================

/**
 * Log authenticated FSM/Tracker REST API calls after dispatch.
 *
 * @param WP_REST_Response $response Response object.
 * @param WP_REST_Server   $server   Server instance.
 * @param WP_REST_Request  $request  Request object.
 * @return WP_REST_Response
 */
function fsm_rest_audit_log( $response, $server, $request ) {
    $route = $request->get_route();

    // Only log FSM and Tracker namespace routes.
    if ( strpos( $route, '/fsm/' ) === false && strpos( $route, '/tracker/' ) === false ) {
        return $response;
    }

    // Skip logging unauthenticated read-only info endpoints.
    if ( $route === '/fsm/v1/info' && $request->get_method() === 'GET' ) {
        return $response;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'fsm_audit_log';

    // Check table exists before inserting (avoids errors during install).
    $table_exists = $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
    );
    if ( ! $table_exists ) {
        return $response;
    }

    $user_id = get_current_user_id();
    $user    = $user_id ? get_user_by( 'id', $user_id ) : null;
    $status  = $response->get_status();

    if ( $status >= 400 && $status < 500 ) {
        $outcome = 'denied';
    } elseif ( $status >= 500 ) {
        $outcome = 'error';
    } else {
        $outcome = 'success';
    }

    $ip = '';
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
    } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $wpdb->insert(
        $table,
        array(
            'actor_id'      => $user_id ?: null,
            'actor_role'    => $user ? implode( ',', $user->roles ) : null,
            'actor_email'   => $user ? $user->user_email : null,
            'action'        => $request->get_method() . ' ' . $route,
            'resource_type' => 'rest_api',
            'resource_id'   => null,
            'ip_address'    => sanitize_text_field( trim( $ip ) ),
            'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] )
                ? sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) )
                : null,
            'outcome'       => $outcome,
            'metadata'      => wp_json_encode( array(
                'status' => $status,
                'method' => $request->get_method(),
                'route'  => $route,
            ) ),
            'created_at'    => current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    return $response;
}
add_filter( 'rest_post_dispatch', 'fsm_rest_audit_log', 999, 3 );

// ============================================================================
// Auth Rate Limiter
// ============================================================================

/**
 * Rate-limit authentication endpoints.
 *
 * 10 failed attempts per IP per 15 minutes on auth endpoints.
 * Only counts failed attempts (4xx responses are counted after dispatch).
 *
 * @param mixed           $result  Pre-dispatch result (null to continue).
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Request object.
 * @return mixed WP_Error on rate limit exceeded, otherwise passthrough.
 */
function fsm_auth_rate_limit_pre( $result, $server, $request ) {
    $route = $request->get_route();

    $rate_limited_routes = array(
        '/jwt-auth/v1/token'         => array( 'limit' => 10, 'window' => 900 ),
        '/tracker/v1/auth/login'     => array( 'limit' => 10, 'window' => 900 ),
        '/tracker/v1/auth/register'  => array( 'limit' => 10, 'window' => 900 ),
        '/fsm/v1/demo/login'         => array( 'limit' => 20, 'window' => 3600 ),
    );

    if ( ! isset( $rate_limited_routes[ $route ] ) ) {
        return $result;
    }

    // Only rate-limit POST requests (actual auth attempts).
    if ( $request->get_method() !== 'POST' ) {
        return $result;
    }

    $config = $rate_limited_routes[ $route ];
    $ip     = fsm_get_rate_limit_ip();
    $key    = 'fsm_rl_' . md5( $route . '_' . $ip );

    $attempts = (int) get_transient( $key );

    if ( $attempts >= $config['limit'] ) {
        $retry_after = fsm_get_rate_limit_ttl( $key );

        return new WP_Error(
            'rate_limit_exceeded',
            'Too many failed attempts. Please try again later.',
            array(
                'status' => 429,
                'headers' => array(
                    'Retry-After' => max( 1, $retry_after ),
                ),
            )
        );
    }

    return $result;
}
add_filter( 'rest_pre_dispatch', 'fsm_auth_rate_limit_pre', 5, 3 );

/**
 * After dispatch, count failed auth attempts for rate limiting.
 *
 * Only increments the counter on 4xx responses (skipSuccessfulRequests pattern).
 *
 * @param WP_REST_Response $response Response object.
 * @param WP_REST_Server   $server   Server instance.
 * @param WP_REST_Request  $request  Request object.
 * @return WP_REST_Response
 */
function fsm_auth_rate_limit_post( $response, $server, $request ) {
    $route = $request->get_route();

    $rate_limited_routes = array(
        '/jwt-auth/v1/token'         => array( 'limit' => 10, 'window' => 900 ),
        '/tracker/v1/auth/login'     => array( 'limit' => 10, 'window' => 900 ),
        '/tracker/v1/auth/register'  => array( 'limit' => 10, 'window' => 900 ),
        '/fsm/v1/demo/login'         => array( 'limit' => 20, 'window' => 3600 ),
    );

    if ( ! isset( $rate_limited_routes[ $route ] ) ) {
        return $response;
    }

    if ( $request->get_method() !== 'POST' ) {
        return $response;
    }

    $status = $response->get_status();

    // Count all attempts for demo/login (prevent abuse), only failures for auth routes.
    $count_all = ( $route === '/fsm/v1/demo/login' );
    if ( ! $count_all && ( $status < 400 || $status >= 500 ) ) {
        return $response;
    }

    $config = $rate_limited_routes[ $route ];
    $ip     = fsm_get_rate_limit_ip();
    $key    = 'fsm_rl_' . md5( $route . '_' . $ip );

    $attempts = (int) get_transient( $key );
    set_transient( $key, $attempts + 1, $config['window'] );

    // Add rate limit headers to response.
    $remaining = max( 0, $config['limit'] - $attempts - 1 );
    $response->header( 'X-RateLimit-Limit', $config['limit'] );
    $response->header( 'X-RateLimit-Remaining', $remaining );

    return $response;
}
add_filter( 'rest_post_dispatch', 'fsm_auth_rate_limit_post', 5, 3 );

/**
 * Get client IP for rate limiting.
 *
 * @return string Sanitized IP address.
 */
function fsm_get_rate_limit_ip() {
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
    } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = '0.0.0.0';
    }
    return sanitize_text_field( trim( $ip ) );
}

/**
 * Get remaining TTL for a transient (approximate).
 *
 * @param string $key Transient key.
 * @return int Seconds remaining.
 */
function fsm_get_rate_limit_ttl( $key ) {
    global $wpdb;

    $transient_timeout = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options}
            WHERE option_name = %s",
            '_transient_timeout_' . $key
        )
    );

    if ( $transient_timeout ) {
        return max( 0, (int) $transient_timeout - time() );
    }

    return 900; // Default 15 min if we can't determine.
}
