<?php
/**
 * 📸 WHOLESALE REST: PRODUCT CREATION & CAMERA UPLOAD CONTROLLER
 *
 * Handles fast creation of new catalog items and camera media attachments.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
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

