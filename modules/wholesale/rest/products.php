<?php
/**
 * ⚡ WHOLESALE REST: PRODUCT RATE SHEET & FAST ROW CONTROLLER
 *
 * Handles catalog rate sheets, inline edits, and high-speed (<100ms) row updates.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
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

    // Role-based authorization: staff can ONLY toggle stock
    $role = function_exists('swac_desk_get_auth_role') ? swac_desk_get_auth_role($request) : 'owner';
    if ($role === 'staff') {
        $restricted_keys = ['ptr', 'mrp', 'scheme', 'scheme_shorthand', 'moq', 'carton', 'carton_pack'];
        foreach ($restricted_keys as $key) {
            if (array_key_exists($key, $params)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Staff accounts are authorized for stock toggles only. Price and scheme updates require Owner PIN.', 'stateless-wa-commerce'),
                ], 403);
            }
        }
        if (!array_key_exists('in_stock', $params)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('No stock update parameter provided.', 'stateless-wa-commerce'),
            ], 400);
        }
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

