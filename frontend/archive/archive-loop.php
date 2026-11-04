<?php
/**
 * 📦 FRONTEND ARCHIVE: PRODUCT LOOP TRANSFORMATIONS
 *
 * Replaces standard WooCommerce catalog loop add-to-cart buttons with stateless
 * WhatsApp order actions, injects card pricing, and enforces in-stock sorting.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. Remove Default WooCommerce Sale Flash
 */
add_filter('woocommerce_sale_flash', '__return_null');

/**
 * 2. Loop Add-to-Cart Button -> WhatsApp Add to Order
 */
add_filter('woocommerce_loop_add_to_cart_link', function ($button_html, $product) {
    if (!$product instanceof WC_Product) {
        return $button_html;
    }

    if (!$product->is_in_stock()) {
        return sprintf(
            '<a href="%s" class="button swac-out-of-stock-btn">%s</a>',
            esc_url($product->get_permalink()),
            esc_html__('Out of Stock', 'stateless-wa-commerce')
        );
    }

    $price = $product->get_price() ? strip_tags((string)wc_price($product->get_price())) : '';
    $name = $product->get_name();
    $url = $product->get_permalink();
    $product_id = $product->get_id();

    $mrp = (float)$product->get_regular_price();
    $sale_price = (float)$product->get_sale_price();
    if ($sale_price <= 0) {
        $sale_price = (float)$product->get_price();
    }
    $is_rx = (bool)apply_filters('swac_product_is_rx', false, $product);
    $button_label = (string)apply_filters('swac_loop_button_text', __('Add to Order', 'stateless-wa-commerce'));
    $icon = function_exists('swac_icon_svg') ? swac_icon_svg() : '';

    return sprintf(
        '<a href="%s"
            class="button swac-loop-add"
            data-product-id="%s"
            data-product-name="%s"
            data-product-url="%s"
            data-product-price="%s"
            data-product-mrp="%s"
            data-product-sale-price="%s"
            data-is-rx="%s"
            aria-label="%s"
        >%s %s</a>',
        esc_url($url),
        esc_attr($product_id),
        esc_attr($name),
        esc_url($url),
        esc_attr($price),
        esc_attr($mrp),
        esc_attr($sale_price),
        $is_rx ? '1' : '0',
        esc_attr(sprintf(__('Add %s to order', 'stateless-wa-commerce'), $name)),
        $icon,
        esc_html($button_label)
    );
}, 10, 2);

/**
 * 3. Unified Price Display for Loop Cards
 */
add_filter('woocommerce_get_price_html', function ($price_html, $product) {
    if (is_admin() || !$product instanceof WC_Product) {
        return $price_html;
    }

    if (!function_exists('is_product') || !is_product()) {
        if (function_exists('swac_format_price_block_html')) {
            return swac_format_price_block_html($product, true);
        }
    }

    return $price_html;
}, 10, 2);

/**
 * 4. Brand Display on Loop Cards
 */
add_action('woocommerce_shop_loop_item_title', function () {
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }

    $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';
    $terms = function_exists('swac_get_product_terms_memoized')
        ? swac_get_product_terms_memoized($product->get_id(), $brand_tax)
        : get_the_terms($product->get_id(), $brand_tax);

    if (!empty($terms) && !is_wp_error($terms)) {
        $brand_name = current($terms)->name;
        echo '<div class="swac-loop-brand-title" style="font-size:12px; color:#64748b; margin-top:2px;">'
            . esc_html($brand_name)
            . '</div>';
    }
}, 15);

/**
 * 5. Stock-First SQL Sorting (In-Stock items appear first)
 */
add_filter('posts_clauses', function ($clauses, $query) {
    if (is_admin() || !$query->is_main_query()) {
        return $clauses;
    }

    if (function_exists('is_shop') && (is_shop() || is_product_taxonomy() || is_product_category())) {
        global $wpdb;

        $join = (string)($clauses['join'] ?? '');
        $orderby = (string)($clauses['orderby'] ?? '');

        if (!str_contains($join, 'wc_product_meta_lookup')) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} AS wc_stock_lookup ON {$wpdb->posts}.ID = wc_stock_lookup.product_id ";
        }

        $stock_order = " CASE WHEN wc_stock_lookup.stock_status = 'instock' THEN 0 ELSE 1 END ASC, ";
        $clauses['orderby'] = $stock_order . $orderby;
    }

    return $clauses;
}, 20, 2);
