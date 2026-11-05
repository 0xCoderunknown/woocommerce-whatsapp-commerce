<?php
/**
 * 🔌 WHOLESALE / B2B: DISTRIBUTOR DESK REST API CONTROLLERS
 *
 * Handles Master PIN auth, quick rate sheet, camera product creation, retailer CRUD, and CSV sync.
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
 * Registers REST API endpoints for Frontend Distributor Desk.
 */
add_action('rest_api_init', function () {
    // 1. Master PIN Verification
    register_rest_route('swac/v1', '/desk/auth', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_auth',
        'permission_callback' => '__return_true',
    ]);

    // 1b. Master Logout
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

    // 3. Quick Update Rate & Scheme
    register_rest_route('swac/v1', '/desk/quick-update', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_quick_update',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 3b. High-Speed In-Place Row Update (<100ms counter grid)
    register_rest_route('swac/v1', '/desk/fast-row-update', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_fast_row_update',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 4. Create New Product
    register_rest_route('swac/v1', '/desk/create-product', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_create_product',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 5. Retailer Management Endpoints
    register_rest_route('swac/v1', '/desk/retailers', [
        'methods' => 'GET',
        'callback' => 'swac_rest_desk_get_retailers',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/save-retailer', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_save_retailer',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/delete-retailer', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_delete_retailer',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 6. CSV Export & Import Endpoints
    register_rest_route('swac/v1', '/desk/export-csv', [
        'methods' => 'GET',
        'callback' => 'swac_rest_desk_export_csv',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/import-csv', [
        'methods' => 'POST',
        'callback' => 'swac_rest_desk_import_csv',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);
});

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

/**
 * Parses shorthand scheme text into structured scheme configuration.
 *
 * Supported formats:
 * - Free goods: "10+1", "12+1 FREE", "12 + 1" => free_goods (buy=10, free=1)
 * - Tier discount: "10:2%", "10:2%, 25:5%" => tier_discount
 * - Cash discount: "2% CD", "2%CD" => cash_discount (cd=2)
 * - Empty / none: "" or "none" or "-" => none
 * - Anything else: => custom
 *
 * @param string $shorthand Shorthand notation.
 * @return array Parsed configuration.
 */
function swac_parse_scheme_shorthand(string $shorthand): array
{
    $clean = trim($shorthand);
    if ($clean === '' || strcasecmp($clean, 'none') === 0 || $clean === '-') {
        return [
            'scheme_type' => 'none',
        ];
    }

    // Pattern 1: Free goods: X+Y or X+Y FREE
    if (preg_match('/^\s*(\d+)\s*\+\s*(\d+)(?:\s+FREE)?\s*$/i', $clean, $matches)) {
        return [
            'scheme_type' => 'free_goods',
            'scheme_buy' => (int)$matches[1],
            'scheme_free' => (int)$matches[2],
        ];
    }

    // Pattern 2: Tier discount: X:Y% (single or multiple comma-separated slabs)
    if (preg_match('/^\s*\d+\s*:\s*\d+(?:\.\d+)?%/i', $clean)) {
        return [
            'scheme_type' => 'tier_discount',
            'scheme_tiers' => sanitize_text_field($clean),
        ];
    }

    // Pattern 3: Cash discount: X% CD
    if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*%\s*CD\s*$/i', $clean, $matches)) {
        return [
            'scheme_type' => 'cash_discount',
            'scheme_cd' => (float)$matches[1],
        ];
    }

    // Pattern 4: Custom tag
    return [
        'scheme_type' => 'custom',
        'scheme_custom' => sanitize_text_field($clean),
    ];
}

/**
 * Generates shorthand string representation from stored product scheme meta.
 *
 * @param int $product_id Product ID.
 * @return string Shorthand representation.
 */
function swac_get_scheme_shorthand(int $product_id): string
{
    $type = (string)get_post_meta($product_id, '_swac_b2b_scheme_type', true);
    if ($type === 'free_goods') {
        $buy = get_post_meta($product_id, '_swac_b2b_scheme_buy', true);
        $free = get_post_meta($product_id, '_swac_b2b_scheme_free', true);
        return ($buy !== '' && $free !== '') ? "{$buy}+{$free}" : '';
    }
    if ($type === 'tier_discount') {
        return (string)get_post_meta($product_id, '_swac_b2b_scheme_tiers', true);
    }
    if ($type === 'cash_discount') {
        $cd = get_post_meta($product_id, '_swac_b2b_scheme_cd', true);
        return ($cd !== '' && (float)$cd > 0) ? "{$cd}% CD" : '';
    }
    if ($type === 'custom') {
        return (string)get_post_meta($product_id, '_swac_b2b_scheme_custom', true);
    }
    return '';
}

/**
 * Returns products for the daily quick rate sheet.
 */
function swac_rest_desk_get_products(WP_REST_Request $request): WP_REST_Response
{
    $search = sanitize_text_field((string)$request->get_param('search'));
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 150,
        'orderby' => 'title',
        'order' => 'ASC',
    ];

    if (!empty($search)) {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);
    $items = [];

    foreach ($query->posts as $post) {
        $product = wc_get_product($post->ID);
        if (!$product) {
            continue;
        }

        $b2b = function_exists('swac_b2b_get_product_data')
            ? swac_b2b_get_product_data($product)
            : ['ptr' => (float)$product->get_price(), 'mrp' => (float)$product->get_regular_price(), 'carton_pack' => 0, 'scheme_type' => 'none', 'scheme_badge' => ''];

        $image_id = $product->get_image_id();
        $thumb = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();

        $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';
        $brand_terms = get_the_terms($product->get_id(), $brand_tax);
        $brand = (!empty($brand_terms) && !is_wp_error($brand_terms)) ? current($brand_terms)->name : '';

        $moq = (int)get_post_meta($product->get_id(), '_swac_b2b_moq', true);
        if ($moq < 1) {
            $moq = 1;
        }

        $items[] = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'brand' => $brand,
            'thumb' => $thumb,
            'in_stock' => $product->is_in_stock(),
            'mrp' => $b2b['mrp'],
            'ptr' => $b2b['ptr'],
            'moq' => $moq,
            'carton_pack' => $b2b['carton_pack'],
            'scheme_type' => $b2b['scheme_type'],
            'scheme_shorthand' => swac_get_scheme_shorthand($product->get_id()),
            'scheme_badge' => $b2b['scheme_badge'],
            'permalink' => $product->get_permalink(),
        ];
    }

    return new WP_REST_Response(['success' => true, 'products' => $items], 200);
}

/**
 * Handles quick inline update of Rate, MRP, Scheme, Carton, and Stock.
 */
function swac_rest_desk_quick_update(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $product_id = absint($params['id'] ?? 0);
    $product = wc_get_product($product_id);

    if (!$product) {
        return new WP_REST_Response(['success' => false, 'message' => __('Product not found.', 'stateless-wa-commerce')], 404);
    }

    // 1. Update PTR
    if (isset($params['ptr'])) {
        $ptr = (float)$params['ptr'];
        update_post_meta($product_id, '_swac_b2b_ptr', $ptr > 0 ? (string)$ptr : '');
    }

    // 2. Update MRP
    if (isset($params['mrp'])) {
        $mrp = (float)$params['mrp'];
        if ($mrp > 0) {
            update_post_meta($product_id, '_regular_price', (string)$mrp);
            update_post_meta($product_id, '_price', (string)$mrp);
        }
    }

    // 3. Update Carton Pack
    if (isset($params['carton_pack'])) {
        $carton = absint($params['carton_pack']);
        update_post_meta($product_id, '_swac_b2b_carton_pack', $carton);
    }

    // 4. Update Scheme
    if (isset($params['scheme_type'])) {
        $scheme_type = sanitize_key($params['scheme_type']);
        update_post_meta($product_id, '_swac_b2b_scheme_type', $scheme_type);

        if ($scheme_type === 'free_goods') {
            update_post_meta($product_id, '_swac_b2b_scheme_buy', absint($params['scheme_buy'] ?? 12));
            update_post_meta($product_id, '_swac_b2b_scheme_free', absint($params['scheme_free'] ?? 1));
        } elseif ($scheme_type === 'tier_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_tiers', sanitize_text_field($params['scheme_tiers'] ?? ''));
        } elseif ($scheme_type === 'cash_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_cd', sanitize_text_field($params['scheme_cd'] ?? '2'));
        } elseif ($scheme_type === 'custom') {
            update_post_meta($product_id, '_swac_b2b_scheme_custom', sanitize_text_field($params['scheme_custom'] ?? ''));
        }
    }

    // 5. Update Stock Status (fast path via wc_update_product_stock_status)
    if (isset($params['in_stock'])) {
        $in_stock = (bool)$params['in_stock'];
        $new_status = $in_stock ? 'instock' : 'outofstock';
        if (function_exists('wc_update_product_stock_status')) {
            wc_update_product_stock_status($product_id, $new_status);
        } else {
            update_post_meta($product_id, '_stock_status', $new_status);
        }
    }

    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product_id);
    }

    // Trigger selective cache purge if available
    if (function_exists('swac_purge_product_cache')) {
        swac_purge_product_cache($product_id);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => __('Rate & Scheme updated.', 'stateless-wa-commerce'),
    ], 200);
}

/**
 * High-Speed In-Place Row Update for Distributor Counter Grid.
 *
 * Designed for <100ms execution:
 * - Bypasses heavy WC_Product::save() lifecycle.
 * - Uses wc_update_product_stock_status() for direct stock toggles.
 * - Writes directly to post-meta for wholesale keys.
 * - Synchronizes retail MRP to both _regular_price and _price.
 * - Parses shorthand scheme notation ("10+1", "10:2%", "2% CD", etc.).
 * - Bypasses heavy global cache purge storms on individual cell updates.
 *
 * @param WP_REST_Request $request Incoming REST request.
 * @return WP_REST_Response
 */
function swac_rest_desk_fast_row_update(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $product_id = absint($params['id'] ?? 0);

    if (!$product_id || get_post_type($product_id) !== 'product') {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Invalid product ID.', 'stateless-wa-commerce'),
        ], 404);
    }

    $updated_fields = [];

    // 0. Support single-field format: { id, field, value }
    if (isset($params['field']) && array_key_exists('value', $params)) {
        $field_name = sanitize_key((string)$params['field']);
        $params[$field_name] = $params['value'];
    }

    // 1. Stock Status (1-Tap Toggle)
    if (array_key_exists('in_stock', $params)) {
        $val = $params['in_stock'];
        $in_stock = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($in_stock === null) {
            $in_stock = (!empty($val) && $val !== '0');
        }
        $status = $in_stock ? 'instock' : 'outofstock';
        if (function_exists('wc_update_product_stock_status')) {
            wc_update_product_stock_status($product_id, $status);
        } else {
            update_post_meta($product_id, '_stock_status', $status);
        }
        $updated_fields[] = 'in_stock';
    }

    // 2. Base Wholesale Rate (PTR)
    if (array_key_exists('ptr', $params)) {
        $ptr = (float)$params['ptr'];
        update_post_meta($product_id, '_swac_b2b_ptr', $ptr > 0 ? (string)$ptr : '');
        $updated_fields[] = 'ptr';
    }

    // 3. Retail MRP (Synchronized to both _regular_price and _price)
    if (array_key_exists('mrp', $params)) {
        $mrp = (float)$params['mrp'];
        if ($mrp > 0) {
            update_post_meta($product_id, '_regular_price', (string)$mrp);
            update_post_meta($product_id, '_price', (string)$mrp);
            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients($product_id);
            }
            $updated_fields[] = 'mrp';
        }
    }

    // 4. Scheme Shorthand (e.g. "10+1", "10:2%", "2% CD", or custom)
    if (array_key_exists('scheme_shorthand', $params) || array_key_exists('scheme', $params)) {
        $shorthand_val = (string)($params['scheme_shorthand'] ?? $params['scheme']);
        $parsed = swac_parse_scheme_shorthand($shorthand_val);

        update_post_meta($product_id, '_swac_b2b_scheme_type', $parsed['scheme_type']);
        if ($parsed['scheme_type'] === 'free_goods') {
            update_post_meta($product_id, '_swac_b2b_scheme_buy', (string)$parsed['scheme_buy']);
            update_post_meta($product_id, '_swac_b2b_scheme_free', (string)$parsed['scheme_free']);
            delete_post_meta($product_id, '_swac_b2b_scheme_tiers');
            delete_post_meta($product_id, '_swac_b2b_scheme_cd');
            delete_post_meta($product_id, '_swac_b2b_scheme_custom');
        } elseif ($parsed['scheme_type'] === 'tier_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_tiers', $parsed['scheme_tiers']);
            delete_post_meta($product_id, '_swac_b2b_scheme_buy');
            delete_post_meta($product_id, '_swac_b2b_scheme_free');
            delete_post_meta($product_id, '_swac_b2b_scheme_cd');
            delete_post_meta($product_id, '_swac_b2b_scheme_custom');
        } elseif ($parsed['scheme_type'] === 'cash_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_cd', (string)$parsed['scheme_cd']);
            delete_post_meta($product_id, '_swac_b2b_scheme_buy');
            delete_post_meta($product_id, '_swac_b2b_scheme_free');
            delete_post_meta($product_id, '_swac_b2b_scheme_tiers');
            delete_post_meta($product_id, '_swac_b2b_scheme_custom');
        } elseif ($parsed['scheme_type'] === 'custom') {
            update_post_meta($product_id, '_swac_b2b_scheme_custom', $parsed['scheme_custom']);
            delete_post_meta($product_id, '_swac_b2b_scheme_buy');
            delete_post_meta($product_id, '_swac_b2b_scheme_free');
            delete_post_meta($product_id, '_swac_b2b_scheme_tiers');
            delete_post_meta($product_id, '_swac_b2b_scheme_cd');
        } else {
            // none
            delete_post_meta($product_id, '_swac_b2b_scheme_buy');
            delete_post_meta($product_id, '_swac_b2b_scheme_free');
            delete_post_meta($product_id, '_swac_b2b_scheme_tiers');
            delete_post_meta($product_id, '_swac_b2b_scheme_cd');
            delete_post_meta($product_id, '_swac_b2b_scheme_custom');
        }
        $updated_fields[] = 'scheme_shorthand';
    }

    // 5. Minimum Order Quantity (MOQ)
    if (array_key_exists('moq', $params)) {
        $moq = max(1, absint($params['moq']));
        update_post_meta($product_id, '_swac_b2b_moq', (string)$moq);
        $updated_fields[] = 'moq';
    }

    // 6. Optional Carton Pack Guide
    if (array_key_exists('carton_pack', $params)) {
        $carton = max(0, absint($params['carton_pack']));
        update_post_meta($product_id, '_swac_b2b_carton_pack', $carton > 0 ? (string)$carton : '');
        $updated_fields[] = 'carton_pack';
    }

    return new WP_REST_Response([
        'success' => true,
        'id' => $product_id,
        'updated_fields' => $updated_fields,
        'message' => __('Row updated.', 'stateless-wa-commerce'),
    ], 200);
}

/**
 * Handles fast creation of a new product from the frontend mobile form.
 */
function swac_rest_desk_create_product(WP_REST_Request $request): WP_REST_Response
{
    $name = sanitize_text_field((string)$request->get_param('name'));
    $mrp = (float)$request->get_param('mrp');
    $ptr = (float)$request->get_param('ptr');
    $carton = absint($request->get_param('carton_pack'));
    $brand_name = sanitize_text_field((string)$request->get_param('brand'));
    $scheme_type = sanitize_key((string)$request->get_param('scheme_type') ?: 'none');

    if (empty($name) || $mrp <= 0) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Product name and MRP are required.', 'stateless-wa-commerce'),
        ], 400);
    }

    // 1. Create WooCommerce Product
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_status('publish');
    $product->set_regular_price($mrp);
    $product->set_price($ptr > 0 ? $ptr : $mrp);
    $product->set_stock_status('instock');
    $product_id = $product->save();

    if (!$product_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Failed to create product.', 'stateless-wa-commerce'),
        ], 500);
    }

    // 2. Set Wholesale Meta
    if ($ptr > 0) {
        update_post_meta($product_id, '_swac_b2b_ptr', (string)$ptr);
    }
    if ($carton > 0) {
        update_post_meta($product_id, '_swac_b2b_carton_pack', $carton);
    }
    if ($scheme_type !== 'none') {
        update_post_meta($product_id, '_swac_b2b_scheme_type', $scheme_type);
        if ($scheme_type === 'free_goods') {
            update_post_meta($product_id, '_swac_b2b_scheme_buy', absint($request->get_param('scheme_buy') ?: 12));
            update_post_meta($product_id, '_swac_b2b_scheme_free', absint($request->get_param('scheme_free') ?: 1));
        } elseif ($scheme_type === 'tier_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_tiers', sanitize_text_field((string)$request->get_param('scheme_tiers')));
        } elseif ($scheme_type === 'cash_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_cd', sanitize_text_field((string)$request->get_param('scheme_cd') ?: '2'));
        } elseif ($scheme_type === 'custom') {
            update_post_meta($product_id, '_swac_b2b_scheme_custom', sanitize_text_field((string)$request->get_param('scheme_custom')));
        }
    }

    // 3. Assign Brand Taxonomy
    if (!empty($brand_name)) {
        $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';
        if (taxonomy_exists($brand_tax)) {
            wp_set_object_terms($product_id, $brand_name, $brand_tax, true);
        }
    }

    // 4. Handle Direct Photo Upload (Phone Camera / Gallery)
    $files = $request->get_file_params();
    if (!empty($files['image']) && !empty($files['image']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload('image', $product_id);
        if (!is_wp_error($attachment_id)) {
            $product->set_image_id($attachment_id);
            $product->save();
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'product_id' => $product_id,
        'name' => $name,
        'permalink' => $product->get_permalink(),
        'message' => sprintf(
        /* translators: %s: product title */
            __('%s successfully added to wholesale catalog!', 'stateless-wa-commerce'),
            $name
        ),
    ], 201);
}

/**
 * Returns approved retailers for desk management.
 */
function swac_rest_desk_get_retailers(): WP_REST_Response
{
    $retailers = function_exists('swac_get_approved_retailers') ? swac_get_approved_retailers() : [];
    return new WP_REST_Response(['success' => true, 'retailers' => array_values($retailers)], 200);
}

/**
 * Saves a retailer from the desk.
 */
function swac_rest_desk_save_retailer(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $saved = function_exists('swac_save_approved_retailer') ? swac_save_approved_retailer($params) : false;

    if ($saved) {
        return new WP_REST_Response(['success' => true, 'message' => __('Retailer saved.', 'stateless-wa-commerce')], 200);
    }
    return new WP_REST_Response(['success' => false, 'message' => __('Validation error. Check mobile number.', 'stateless-wa-commerce')], 400);
}

/**
 * Deletes a retailer from the desk.
 */
function swac_rest_desk_delete_retailer(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $phone = sanitize_text_field((string)($params['phone'] ?? ''));
    $deleted = function_exists('swac_delete_approved_retailer') ? swac_delete_approved_retailer($phone) : false;

    if ($deleted) {
        return new WP_REST_Response(['success' => true, 'message' => __('Retailer removed.', 'stateless-wa-commerce')], 200);
    }
    return new WP_REST_Response(['success' => false, 'message' => __('Retailer not found.', 'stateless-wa-commerce')], 404);
}

/**
 * Streams wholesale product rate sheet as CSV with UTF-8 BOM.
 *
 * @param WP_REST_Request $request
 */
function swac_rest_desk_export_csv(WP_REST_Request $request)
{
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ];
    $query = new WP_Query($args);

    $filename = 'wholesale-rates-' . gmdate('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Output UTF-8 BOM so Excel opens with proper UTF-8 support
    fputs($out, "\xEF\xBB\xBF");

    // CSV Header row
    fputcsv($out, ['ID', 'SKU', 'Product Name', 'Brand', 'MRP', 'PTR', 'Carton Pack', 'Scheme Type', 'In Stock']);

    $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';

    foreach ($query->posts as $post) {
        $product = wc_get_product($post->ID);
        if (!$product) {
            continue;
        }

        $b2b = function_exists('swac_b2b_get_product_data')
            ? swac_b2b_get_product_data($product)
            : ['ptr' => (float)$product->get_price(), 'mrp' => (float)$product->get_regular_price(), 'carton_pack' => 0, 'scheme_type' => 'none'];

        $brand_terms = taxonomy_exists($brand_tax) ? get_the_terms($product->get_id(), $brand_tax) : [];
        $brand = (!empty($brand_terms) && !is_wp_error($brand_terms)) ? current($brand_terms)->name : '';

        fputcsv($out, [
            $product->get_id(),
            $product->get_sku(),
            $product->get_name(),
            $brand,
            $b2b['mrp'],
            $b2b['ptr'],
            $b2b['carton_pack'],
            $b2b['scheme_type'],
            $product->is_in_stock() ? 'yes' : 'no',
        ]);
    }

    fclose($out);
    exit;
}

/**
 * Processes bulk rate and stock update from uploaded CSV file.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function swac_rest_desk_import_csv(WP_REST_Request $request): WP_REST_Response
{
    $files = $request->get_file_params();
    if (empty($files['file']) || empty($files['file']['tmp_name'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('No CSV file uploaded.', 'stateless-wa-commerce'),
        ], 400);
    }

    $filename = $files['file']['name'] ?? '';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== 'csv' && $ext !== 'txt') {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Invalid file format. Please upload a valid CSV file.', 'stateless-wa-commerce'),
        ], 400);
    }

    $tmp_file = $files['file']['tmp_name'];
    $handle = fopen($tmp_file, 'r');
    if ($handle === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Could not open CSV file.', 'stateless-wa-commerce'),
        ], 500);
    }

    // Skip UTF-8 BOM if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return new WP_REST_Response([
            'success' => false,
            'message' => __('CSV file is empty.', 'stateless-wa-commerce'),
        ], 400);
    }

    // Normalize header mapping
    $col_map = [];
    foreach ($header as $idx => $col_name) {
        $norm = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]/', '', $col_name)));
        $col_map[$norm] = $idx;
    }

    $updated_count = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if (empty($row) || count(array_filter($row)) === 0) {
            continue;
        }

        $id = isset($col_map['id']) ? absint($row[$col_map['id']] ?? 0) : 0;
        $sku = isset($col_map['sku']) ? sanitize_text_field((string)($row[$col_map['sku']] ?? '')) : '';

        $product = null;
        if ($id > 0) {
            $product = wc_get_product($id);
        }
        if (!$product && !empty($sku)) {
            $found_id = wc_get_product_id_by_sku($sku);
            if ($found_id) {
                $product = wc_get_product($found_id);
            }
        }

        if (!$product) {
            continue;
        }

        $product_id = $product->get_id();
        $changed = false;

        // 1. Regular Price / MRP
        if (isset($col_map['mrp']) && isset($row[$col_map['mrp']])) {
            $mrp = (float)$row[$col_map['mrp']];
            if ($mrp > 0) {
                $product->set_regular_price($mrp);
                $changed = true;
            }
        }

        // 2. PTR / Wholesale Rate
        if (isset($col_map['ptr']) && isset($row[$col_map['ptr']])) {
            $ptr = (float)$row[$col_map['ptr']];
            update_post_meta($product_id, '_swac_b2b_ptr', $ptr > 0 ? (string)$ptr : '');
            $changed = true;
        }

        // 3. Carton Pack
        if (isset($col_map['cartonpack']) && isset($row[$col_map['cartonpack']])) {
            $carton = absint($row[$col_map['cartonpack']]);
            update_post_meta($product_id, '_swac_b2b_carton_pack', $carton);
            $changed = true;
        }

        // 4. Stock Status
        if (isset($col_map['instock']) && isset($row[$col_map['instock']])) {
            $raw_stock = strtolower(trim((string)$row[$col_map['instock']]));
            $is_stock = in_array($raw_stock, ['1', 'yes', 'true', 'instock', 'in stock', 'y'], true);
            $product->set_stock_status($is_stock ? 'instock' : 'outofstock');
            $changed = true;
        }

        if ($changed) {
            $product->save();
            if (function_exists('swac_purge_product_cache')) {
                swac_purge_product_cache($product_id);
            }
            $updated_count++;
        }
    }

    fclose($handle);

    return new WP_REST_Response([
        'success' => true,
        'count' => $updated_count,
        'message' => sprintf(
        /* translators: %d: number of updated products */
            __('Successfully updated %d products from CSV.', 'stateless-wa-commerce'),
            $updated_count
        ),
    ], 200);
}

