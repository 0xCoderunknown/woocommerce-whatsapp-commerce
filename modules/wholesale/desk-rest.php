<?php
/**
 * 🔌 WHOLESALE / B2B: DISTRIBUTOR DESK REST API ROUTER
 *
 * Registers REST API endpoints for Frontend Distributor Desk and loads modular sub-controllers.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('wholesale')) {
    return;
}

// Load modular REST sub-controllers
require_once __DIR__ . '/rest/auth.php';
require_once __DIR__ . '/rest/shorthand.php';
require_once __DIR__ . '/rest/products.php';
require_once __DIR__ . '/rest/create-product.php';
require_once __DIR__ . '/rest/retailers.php';
require_once __DIR__ . '/rest/csv.php';
require_once __DIR__ . '/rest/settings.php';

/**
 * Whitelist custom auth headers for Flutter Web cross-origin preflight requests (OPTIONS).
 */
add_filter('rest_allowed_cors_headers', function (array $headers): array {
    $headers[] = 'X-SWAC-Master-Token';
    $headers[] = 'X-SWAC-Retailer-Token';
    return array_unique($headers);
});

/**
 * Registers REST API endpoints for Frontend Distributor Desk.
 */
add_action('rest_api_init', function () {
    // 1. Master PIN Verification & Logout
    register_rest_route('swac/v1', '/desk/auth', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_auth',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('swac/v1', '/desk/logout', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_logout',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 2. Fetch Catalog Items for Quick Rate Sheet
    register_rest_route('swac/v1', '/desk/products', [
        'methods' => 'GET',
        'callback' => 'swac_rest_desk_get_products',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 3. Quick Update Rate & Scheme (Owner Only)
    register_rest_route('swac/v1', '/desk/quick-update', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_quick_update',
        'permission_callback' => 'swac_desk_is_owner_authorized',
    ]);

    // 3b. High-Speed In-Place Row Update (<100ms counter grid - Owner & Staff)
    register_rest_route('swac/v1', '/desk/fast-row-update', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_fast_row_update',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 4. Create New Product (Owner Only)
    register_rest_route('swac/v1', '/desk/create-product', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_create_product',
        'permission_callback' => 'swac_desk_is_owner_authorized',
    ]);

    // 5. Retailer Management Endpoints (Owner & Salesman Read, Owner Write)
    register_rest_route('swac/v1', '/desk/retailers', [
        'methods' => 'GET',
        'callback' => 'swac_rest_desk_get_retailers',
        'permission_callback' => 'swac_desk_can_read_retailers',
    ]);

    register_rest_route('swac/v1', '/desk/save-retailer', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_save_retailer',
        'permission_callback' => 'swac_desk_is_owner_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/delete-retailer', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_delete_retailer',
        'permission_callback' => 'swac_desk_is_owner_authorized',
    ]);

    // 6. CSV Export & Import Endpoints (Owner Only)
    register_rest_route('swac/v1', '/desk/export-csv', [
        'methods' => 'GET',
        'callback' => 'swac_rest_desk_export_csv',
        'permission_callback' => 'swac_desk_is_owner_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/import-csv', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_import_csv',
        'permission_callback' => 'swac_desk_is_owner_authorized',
    ]);

    // 7. Store Essentials Settings Endpoints (Owner Only)
    register_rest_route('swac/v1', '/desk/settings', [
        'methods' => 'GET',
        'callback' => 'swac_rest_desk_get_settings',
        'permission_callback' => 'swac_desk_is_owner_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/settings', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_save_settings',
        'permission_callback' => 'swac_desk_is_owner_authorized',
    ]);
});
