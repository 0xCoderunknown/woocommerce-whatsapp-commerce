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
 * Validates a retailer HMAC token against approved active retailers.
 *
 * @param string $token HMAC token.
 * @return bool True if valid active retailer, false otherwise.
 */
function swac_is_valid_retailer_token(string $token): bool
{
    if (empty($token)) {
        return false;
    }
    $retailers = function_exists('swac_get_approved_retailers') ? swac_get_approved_retailers() : [];
    if (empty($retailers)) {
        return false;
    }
    $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'swac-b2b-token-salt');
    foreach ($retailers as $r_phone => $r_data) {
        if (($r_data['status'] ?? 'active') !== 'active') {
            continue;
        }
        $clean_10 = substr((string)$r_phone, -10);
        $expected = hash_hmac('sha256', $clean_10 . '|' . (string)($r_data['pin'] ?? ''), $salt);
        if (hash_equals($expected, $token)) {
            return true;
        }
    }
    return false;
}

/**
 * Resolves the authenticated role for current request ('owner' | 'staff' | 'salesman' | 'retailer' | null).
 *
 * @param WP_REST_Request|null $request Optional REST request.
 * @return string|null 'owner', 'staff', 'salesman', 'retailer', or null if unauthenticated.
 */
function swac_desk_get_auth_role(?WP_REST_Request $request = null): ?string
{
    // 1. Logged in WordPress administrators / shop managers are full owners
    if (current_user_can('manage_woocommerce') || current_user_can('edit_products')) {
        return 'owner';
    }

    // 2. Token-based authorization for mobile access
    $token = '';
    $retailer_token = '';
    if ($request instanceof WP_REST_Request) {
        $token = (string)$request->get_header('X-SWAC-Master-Token');
        if (empty($token)) {
            $token = (string)($request->get_param('master_token') ?? '');
        }
        $retailer_token = (string)$request->get_header('X-SWAC-Retailer-Token');
        if (empty($retailer_token)) {
            $retailer_token = (string)($request->get_param('retailer_token') ?? '');
        }
    }

    if (!empty($token)) {
        $token_hash = hash('sha256', $token);

        // Check multi-session tokens map
        $tokens = get_option('swac_b2b_desk_tokens', []);
        if (is_array($tokens) && isset($tokens[$token_hash])) {
            $sess = $tokens[$token_hash];
            $expires = (int)($sess['expires'] ?? 0);
            if ($expires === 0 || time() <= $expires) {
                return (string)($sess['role'] ?? 'owner');
            }
        }

        // Backward compatibility fallback for legacy single token
        $stored = get_option('swac_b2b_master_hash', '');
        if (is_array($stored)) {
            $hash = (string)($stored['hash'] ?? '');
            $expires = (int)($stored['expires'] ?? 0);
            if (!empty($hash) && ($expires === 0 || time() <= $expires) && hash_equals($hash, $token_hash)) {
                return (string)($stored['role'] ?? 'owner');
            }
        } elseif (is_string($stored) && !empty($stored) && hash_equals($stored, $token_hash)) {
            return 'owner';
        }

        // If the token in X-SWAC-Master-Token happens to be a valid retailer token
        if (swac_is_valid_retailer_token($token)) {
            return 'retailer';
        }
    }

    if (!empty($retailer_token) && swac_is_valid_retailer_token($retailer_token)) {
        return 'retailer';
    }

    return null;
}

/**
 * Verifies if current request has distributor desk authorization (owner or staff).
 *
 * @param WP_REST_Request|null $request Optional REST request.
 * @return bool
 */
function swac_desk_is_authorized(?WP_REST_Request $request = null): bool
{
    return swac_desk_get_auth_role($request) !== null;
}

/**
 * Verifies if current request has distributor owner (full access) authorization.
 *
 * @param WP_REST_Request|null $request Optional REST request.
 * @return bool
 */
function swac_desk_is_owner_authorized(?WP_REST_Request $request = null): bool
{
    return swac_desk_get_auth_role($request) === 'owner';
}

/**
 * Verifies if current request has retailer directory read access (Owner or Salesman).
 *
 * @param WP_REST_Request|null $request Optional REST request.
 * @return bool
 */
function swac_desk_can_read_retailers(?WP_REST_Request $request = null): bool
{
    $role = swac_desk_get_auth_role($request);
    return $role === 'owner' || $role === 'salesman';
}

/**
 * Handles passcode verification for distributor mobile login (Owner or Staff).
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

    $master_pin = (string)get_option('swac_b2b_master_pin', '');
    $staff_pin = (string)get_option('swac_b2b_staff_pin', '');
    $salesman_pin = (string)get_option('swac_b2b_salesman_pin', '3344');

    if (empty($master_pin) && empty($staff_pin) && empty($salesman_pin)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Distributor Desk PIN is not configured. Please set the Master or Staff PIN in wp-admin.', 'stateless-wa-commerce'),
        ], 503);
    }

    $role = null;
    if (!empty($master_pin) && hash_equals($master_pin, $pin)) {
        $role = 'owner';
    } elseif (!empty($staff_pin) && hash_equals($staff_pin, $pin)) {
        $role = 'staff';
    } elseif (!empty($salesman_pin) && hash_equals($salesman_pin, $pin)) {
        $role = 'salesman';
    }

    if (!$role) {
        $attempts++;
        set_transient($rate_key, $attempts, 15 * MINUTE_IN_SECONDS);
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Incorrect PIN. Please try again.', 'stateless-wa-commerce'),
        ], 403);
    }

    delete_transient($rate_key);

    $token = wp_generate_password(32, false);
    $token_hash = hash('sha256', $token);
    $now = time();
    $expires = $now + (30 * DAY_IN_SECONDS);

    // Multi-session token store
    $tokens = get_option('swac_b2b_desk_tokens', []);
    if (!is_array($tokens)) {
        $tokens = [];
    }

    // Clean up expired tokens
    foreach ($tokens as $h => $sess) {
        if (!empty($sess['expires']) && $sess['expires'] < $now) {
            unset($tokens[$h]);
        }
    }

    $tokens[$token_hash] = [
        'role' => $role,
        'expires' => $expires,
        'created_at' => $now,
    ];
    update_option('swac_b2b_desk_tokens', $tokens, false);

    // Also update legacy single token for backward compatibility
    if ($role === 'owner') {
        update_option('swac_b2b_master_hash', [
            'hash' => $token_hash,
            'expires' => $expires,
            'role' => 'owner',
        ], false);
    }

    return new WP_REST_Response([
        'success' => true,
        'token' => $token,
        'role' => $role,
        'message' => $role === 'owner'
            ? __('Owner Access Granted.', 'stateless-wa-commerce')
            : ($role === 'salesman'
                ? __('Salesman Access Granted (Route Booking).', 'stateless-wa-commerce')
                : __('Staff Access Granted (Stock Control).', 'stateless-wa-commerce')),
    ], 200);
}

/**
 * Handles master session logout.
 */
function swac_rest_desk_logout(WP_REST_Request $request): WP_REST_Response
{
    $token = (string)$request->get_header('X-SWAC-Master-Token');
    if (empty($token)) {
        $token = (string)($request->get_param('master_token') ?? '');
    }

    if (!empty($token)) {
        $token_hash = hash('sha256', $token);
        $tokens = get_option('swac_b2b_desk_tokens', []);
        if (is_array($tokens) && isset($tokens[$token_hash])) {
            unset($tokens[$token_hash]);
            update_option('swac_b2b_desk_tokens', $tokens, false);
        }
    }

    delete_option('swac_b2b_master_hash');

    return new WP_REST_Response([
        'success' => true,
        'message' => __('Logged out successfully.', 'stateless-wa-commerce'),
    ], 200);
}

