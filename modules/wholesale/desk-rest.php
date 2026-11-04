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
        $stored_hash = get_option('swac_b2b_master_hash', '');
        if (!empty($stored_hash) && hash_equals($stored_hash, hash('sha256', $token))) {
            return true;
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
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_auth',
        'permission_callback' => '__return_true',
    ]);

    // 2. Fetch Catalog Items for Quick Rate Sheet
    register_rest_route('swac/v1', '/desk/products', [
        'methods'             => 'GET',
        'callback'            => 'swac_rest_desk_get_products',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 3. Quick Update Rate & Scheme
    register_rest_route('swac/v1', '/desk/quick-update', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_quick_update',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 4. Create New Product
    register_rest_route('swac/v1', '/desk/create-product', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_create_product',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 5. Retailer Management Endpoints
    register_rest_route('swac/v1', '/desk/retailers', [
        'methods'             => 'GET',
        'callback'            => 'swac_rest_desk_get_retailers',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/save-retailer', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_save_retailer',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/delete-retailer', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_delete_retailer',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 6. CSV Export & Import Endpoints
    register_rest_route('swac/v1', '/desk/export-csv', [
        'methods'             => 'GET',
        'callback'            => 'swac_rest_desk_export_csv',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/import-csv', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_import_csv',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);
});

/**
 * Handles master passcode verification for distributor mobile login.
 */
function swac_rest_desk_auth(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $pin = sanitize_text_field((string)($params['pin'] ?? ''));

    if (empty($pin)) {
        return new WP_REST_Response(['success' => false, 'message' => __('PIN required.', 'stateless-wa-commerce')], 400);
    }

    $configured_pin = (string)get_option('swac_b2b_master_pin', '');
    if (empty($configured_pin)) {
        $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
        $raw_phone = $cfg['phone_number'] ?? '';
        $configured_pin = strlen($raw_phone) >= 4 ? substr($raw_phone, -4) : '1234';
    }

    if ($pin !== $configured_pin) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Incorrect Master PIN. Please try again.', 'stateless-wa-commerce'),
        ], 403);
    }

    $token = wp_generate_password(32, false);
    update_option('swac_b2b_master_hash', hash('sha256', $token));

    return new WP_REST_Response([
        'success' => true,
        'token'   => $token,
        'message' => __('Access Granted.', 'stateless-wa-commerce'),
    ], 200);
}

/**
 * Returns products for the daily quick rate sheet.
 */
function swac_rest_desk_get_products(WP_REST_Request $request): WP_REST_Response
{
    $search = sanitize_text_field((string)$request->get_param('search'));
    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 150,
        'orderby'        => 'title',
        'order'          => 'ASC',
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

        $items[] = [
            'id'           => $product->get_id(),
            'name'         => $product->get_name(),
            'brand'        => $brand,
            'thumb'        => $thumb,
            'in_stock'     => $product->is_in_stock(),
            'mrp'          => $b2b['mrp'],
            'ptr'          => $b2b['ptr'],
            'carton_pack'  => $b2b['carton_pack'],
            'scheme_type'  => $b2b['scheme_type'],
            'scheme_badge' => $b2b['scheme_badge'],
            'permalink'    => $product->get_permalink(),
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
            $product->set_regular_price($mrp);
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

    // 5. Update Stock Status
    if (isset($params['in_stock'])) {
        $in_stock = (bool)$params['in_stock'];
        $product->set_stock_status($in_stock ? 'instock' : 'outofstock');
    }

    $product->save();

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
        'success'    => true,
        'product_id' => $product_id,
        'name'       => $name,
        'permalink'  => $product->get_permalink(),
        'message'    => sprintf(
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
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
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
        'count'   => $updated_count,
        'message' => sprintf(
            /* translators: %d: number of updated products */
            __('Successfully updated %d products from CSV.', 'stateless-wa-commerce'),
            $updated_count
        ),
    ], 200);
}

