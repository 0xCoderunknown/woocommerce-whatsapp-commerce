<?php
/**
 * 🔐 WHOLESALE REST: AUTH & SECURITY CONTROLLER
 *
 * Handles Master PIN authorization, token issuing, and session logout.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verifies if current request has distributor desk authorization.
 *
 * @param WP_REST_Request|null $request Optional REST request.
 * @return bool
 */
function swac_desk_is_authorized(?WP_REST_Request $request = null): bool
{
    // 1. Logged in WordPress administrators / shop managers
    if (current_user_can('manage_woocommerce') || current_user_can('edit_products')) {
        return true;
    }

    // 2. Token-based authorization for mobile access
    $token = '';
    if ($request instanceof WP_REST_Request) {
        $token = (string)$request->get_header('X-SWAC-Master-Token');
        if (empty($token)) {
            $token = (string)($request->get_param('master_token') ?? '');
        }
    }

    if (!empty($token)) {
        $stored = get_option('swac_b2b_master_hash', '');
        $token_hash = hash('sha256', $token);
        if (is_array($stored)) {
            $hash = (string)($stored['hash'] ?? '');
            $expires = (int)($stored['expires'] ?? 0);
            if (!empty($hash) && ($expires === 0 || time() <= $expires) && hash_equals($hash, $token_hash)) {
                return true;
            }
        } elseif (is_string($stored) && !empty($stored)) {
            if (hash_equals($stored, $token_hash)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Handles master passcode verification for distributor mobile login.
 */
function swac_rest_desk_auth(WP_REST_Request $request): WP_REST_Response
{
    $ip = sanitize_text_field(wp_unslash((string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')));
    $rate_key = 'swac_desk_rate_' . md5($ip);
    $attempts = (int)get_transient($rate_key);

    if ($attempts >= 5) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Too many failed login attempts. Please wait 15 minutes before trying again.', 'stateless-wa-commerce'),
        ], 429);
    }

    $params = $request->get_json_params() ?: $request->get_body_params();
    $pin = sanitize_text_field((string)($params['pin'] ?? ''));

    if (empty($pin)) {
        return new WP_REST_Response(['success' => false, 'message' => __('PIN required.', 'stateless-wa-commerce')], 400);
    }

    $configured_pin = (string)get_option('swac_b2b_master_pin', '');
    if (empty($configured_pin)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Distributor Master PIN is not configured. Please set the Master PIN in wp-admin.', 'stateless-wa-commerce'),
        ], 503);
    }

    if (!hash_equals($configured_pin, $pin)) {
        $attempts++;
        set_transient($rate_key, $attempts, 15 * MINUTE_IN_SECONDS);
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Incorrect Master PIN. Please try again.', 'stateless-wa-commerce'),
        ], 403);
    }

    delete_transient($rate_key);

    $token = wp_generate_password(32, false);
    update_option('swac_b2b_master_hash', [
        'hash' => hash('sha256', $token),
        'expires' => time() + (30 * DAY_IN_SECONDS),
    ]);

    return new WP_REST_Response([
        'success' => true,
        'token' => $token,
        'message' => __('Access Granted.', 'stateless-wa-commerce'),
    ], 200);
}

/**
 * Handles master session logout.
 */
function swac_rest_desk_logout(WP_REST_Request $request): WP_REST_Response
{
    delete_option('swac_b2b_master_hash');
    return new WP_REST_Response([
        'success' => true,
        'message' => __('Logged out successfully.', 'stateless-wa-commerce'),
    ], 200);
}

