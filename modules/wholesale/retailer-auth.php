<?php
/**
 * 🔐 WHOLESALE / B2B MODULE: RETAILER AUTHENTICATION & DIRECTORY
 *
 * Provides zero-cost phone + 4-digit PIN authentication, REST API login verification,
 * and wp-admin retailer management directory for approved B2B accounts.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('wholesale')) {
    return;
}

/**
 * Option key used for storing the approved retailer registry.
 */
define('SWAC_RETAILER_OPTION', 'swac_b2b_approved_retailers');

/**
 * Retrieves the full registry of approved retailers.
 *
 * @return array<string, array{phone: string, pin: string, store_name: string, owner_name: string, area: string, status: string, created_at: string}>
 */
function swac_get_approved_retailers(): array
{
    $retailers = get_option(SWAC_RETAILER_OPTION, []);
    return is_array($retailers) ? $retailers : [];
}

/**
 * Saves a retailer record to the registry.
 *
 * @param array $data Retailer details.
 * @return bool True on success, false on validation failure.
 */
function swac_save_approved_retailer(array $data): bool
{
    $phone = function_exists('swac_sanitize_phone') ? swac_sanitize_phone($data['phone'] ?? '') : preg_replace('/[^0-9]/', '', (string)($data['phone'] ?? ''));
    if (strlen($phone) < 10) {
        return false;
    }

    $pin = sanitize_text_field((string)($data['pin'] ?? ''));
    if ($pin === '') {
        $pin = substr($phone, -4); // Default to last 4 digits of phone
    }

    $store_name = sanitize_text_field((string)($data['store_name'] ?? ''));
    if ($store_name === '') {
        $store_name = sprintf(__('Retailer %s', 'stateless-wa-commerce'), substr($phone, -4));
    }

    $owner_name = sanitize_text_field((string)($data['owner_name'] ?? ''));
    $area = sanitize_text_field((string)($data['area'] ?? ''));
    $status = in_array($data['status'] ?? 'active', ['active', 'suspended'], true) ? $data['status'] : 'active';

    $retailers = swac_get_approved_retailers();
    $retailers[$phone] = [
        'phone' => $phone,
        'pin' => $pin,
        'store_name' => $store_name,
        'owner_name' => $owner_name,
        'area' => $area,
        'status' => $status,
        'created_at' => $retailers[$phone]['created_at'] ?? gmdate('d-m-Y'),
    ];

    return update_option(SWAC_RETAILER_OPTION, $retailers, false);
}

/**
 * Removes a retailer record by phone number.
 *
 * @param string $phone Retailer phone number.
 * @return bool
 */
function swac_delete_approved_retailer(string $phone): bool
{
    $phone = function_exists('swac_sanitize_phone') ? swac_sanitize_phone($phone) : preg_replace('/[^0-9]/', '', $phone);
    $retailers = swac_get_approved_retailers();
    if (isset($retailers[$phone])) {
        unset($retailers[$phone]);
        return update_option(SWAC_RETAILER_OPTION, $retailers, false);
    }
    return false;
}

if (!function_exists('swac_b2b_get_registration_whatsapp_url')) {
    /**
     * Generates a pre-filled WhatsApp registration application URL for new retail stores.
     *
     * @return string
     */
    function swac_b2b_get_registration_whatsapp_url(): string
    {
        $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
        $store_name = $cfg['name'] ?? (!empty($cfg['store_name']) ? $cfg['store_name'] : get_bloginfo('name'));
        $base_wa = $cfg['base_url'] ?? '';

        $message = sprintf(
            /* translators: %s: store name */
            __("Hello %s, I want to register our retail store for wholesale ordering. Here are our shop details:\n\n• Shop / Store Name:\n• Owner / Contact Person:\n• Delivery Route / Area:\n• Mobile Number:\n\nPlease approve our account and issue our 4-digit PIN.", 'stateless-wa-commerce'),
            $store_name
        );

        return function_exists('swac_build_whatsapp_url')
            ? swac_build_whatsapp_url($message, $base_wa)
            : '';
    }
}

/**
 * Registers REST API endpoint for retailer PIN verification: POST /wp-json/swac/v1/retailer-login
 */
add_action('rest_api_init', function () {
    register_rest_route('swac/v1', '/retailer-login', [
        'methods' => 'POST',
        'callback' => 'swac_rest_retailer_login',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Handles the retailer login verification request.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function swac_rest_retailer_login(WP_REST_Request $request): WP_REST_Response
{
    $ip = sanitize_text_field(wp_unslash((string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')));
    $rate_key = 'swac_ret_rate_' . md5($ip);
    $attempts = (int)get_transient($rate_key);

    if ($attempts >= 10) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Too many failed login attempts. Please wait 15 minutes before trying again.', 'stateless-wa-commerce'),
        ], 429);
    }

    $params = $request->get_json_params();
    if (empty($params)) {
        $params = $request->get_body_params();
    }

    $raw_phone = $params['phone'] ?? '';
    $raw_pin = (string)($params['pin'] ?? '');

    $phone = function_exists('swac_sanitize_phone') ? swac_sanitize_phone($raw_phone) : preg_replace('/[^0-9]/', '', (string)$raw_phone);
    if (strlen($phone) < 10 || $raw_pin === '') {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Please enter a valid 10-digit mobile number and PIN.', 'stateless-wa-commerce'),
        ], 400);
    }

    // Match against standard 10 digits (handling +91 or 91 prefixes)
    $clean_10 = substr($phone, -10);
    $retailers = swac_get_approved_retailers();
    $matched_retailer = null;

    foreach ($retailers as $r_phone => $r_data) {
        if (substr($r_phone, -10) === $clean_10) {
            $matched_retailer = $r_data;
            break;
        }
    }

    if (!$matched_retailer) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Retailer number not registered. Please tap "Register via WhatsApp" to request approval.', 'stateless-wa-commerce'),
        ], 403);
    }

    if (($matched_retailer['status'] ?? 'active') !== 'active') {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Your wholesale account is currently suspended. Please contact distributor.', 'stateless-wa-commerce'),
        ], 403);
    }

    if (!hash_equals((string)$matched_retailer['pin'], (string)$raw_pin)) {
        $attempts++;
        set_transient($rate_key, $attempts, 15 * MINUTE_IN_SECONDS);
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Incorrect 4-digit PIN. Please re-enter or request PIN from distributor.', 'stateless-wa-commerce'),
        ], 403);
    }

    delete_transient($rate_key);

    // Generate lightweight authentication token
    $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'swac-b2b-token-salt');
    $token = hash_hmac('sha256', $clean_10 . '|' . $raw_pin, $salt);

    return new WP_REST_Response([
        'success' => true,
        'retailer' => [
            'phone' => $matched_retailer['phone'],
            'store_name' => $matched_retailer['store_name'],
            'owner_name' => $matched_retailer['owner_name'],
            'area' => $matched_retailer['area'],
            'token' => $token,
        ],
    ], 200);
}

/**
 * Adds "Approved Retailers" admin management screen under Products.
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        __('Approved Retailers', 'stateless-wa-commerce'),
        __('Approved Retailers', 'stateless-wa-commerce'),
        'manage_woocommerce',
        'swac-retailers',
        'swac_render_retailers_admin_page'
    );
});

/**
 * Renders the Approved Retailers wp-admin management page.
 */
function swac_render_retailers_admin_page()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Access Denied: Insufficient privileges.', 'stateless-wa-commerce'));
    }

    $message = '';
    $error = '';

    // Handle form submissions
    if (isset($_POST['swac_retailer_action']) && check_admin_referer('swac_retailer_admin_nonce')) {
        $action = sanitize_key($_POST['swac_retailer_action']);

        if ($action === 'add' || $action === 'edit') {
            $saved = swac_save_approved_retailer([
                'phone' => $_POST['phone'] ?? '',
                'pin' => $_POST['pin'] ?? '',
                'store_name' => $_POST['store_name'] ?? '',
                'owner_name' => $_POST['owner_name'] ?? '',
                'area' => $_POST['area'] ?? '',
                'status' => $_POST['status'] ?? 'active',
            ]);

            if ($saved) {
                $message = __('Retailer details successfully saved.', 'stateless-wa-commerce');
            } else {
                $error = __('Failed to save retailer. Please check mobile number.', 'stateless-wa-commerce');
            }
        } elseif ($action === 'delete') {
            $phone = sanitize_text_field($_POST['phone'] ?? '');
            if (swac_delete_approved_retailer($phone)) {
                $message = __('Retailer successfully removed from directory.', 'stateless-wa-commerce');
            }
        } elseif ($action === 'update_master_pin') {
            $master_pin = sanitize_text_field($_POST['master_pin'] ?? '');
            if (strlen($master_pin) >= 4) {
                update_option('swac_b2b_master_pin', $master_pin, false);
                $message = __('Master Desk PIN updated successfully.', 'stateless-wa-commerce');
            } else {
                $error = __('Master PIN must be at least 4 digits.', 'stateless-wa-commerce');
            }
        } elseif ($action === 'update_staff_pin') {
            $staff_pin = sanitize_text_field($_POST['staff_pin'] ?? '');
            if ($staff_pin === '') {
                delete_option('swac_b2b_staff_pin');
                $message = __('Staff PIN removed.', 'stateless-wa-commerce');
            } elseif (strlen($staff_pin) >= 4) {
                update_option('swac_b2b_staff_pin', $staff_pin, false);
                $message = __('Staff PIN updated successfully.', 'stateless-wa-commerce');
            } else {
                $error = __('Staff PIN must be at least 4 digits.', 'stateless-wa-commerce');
            }
        }
    }

    $retailers = swac_get_approved_retailers();
    $master_pin = (string)get_option('swac_b2b_master_pin', '');
    $staff_pin = (string)get_option('swac_b2b_staff_pin', '');

    include __DIR__ . '/templates/retailers-admin-page.php';
}
