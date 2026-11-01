<?php
/**
 * 🛠️ BACKEND: PRODUCT EDITOR STREAMLINING & DISCOUNT BADGES
 *
 * Streamlines the WooCommerce product editor screen for catalog administration
 * and displays inline discount percentage badges in the admin product table.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    /**
     * 1. Hide "Virtual" and "Downloadable" checkboxes if physical catalog.
     */
    add_filter('product_type_options', function ($options) {
        if (isset($options['virtual'])) {
            unset($options['virtual']);
        }
        if (isset($options['downloadable'])) {
            unset($options['downloadable']);
        }
        return $options;
    });

    /**
     * 2. Streamline Product Data Tabs (toggleable via filter)
     */
    $streamline_enabled = apply_filters(
        'swac_streamline_product_editor',
        defined('SWAC_STREAMLINE_PRODUCT_TABS') ? SWAC_STREAMLINE_PRODUCT_TABS : false
    );
    if ($streamline_enabled) {
        add_filter('woocommerce_product_data_tabs', function ($tabs) {
            $tabs_to_remove = [
                'shipping',
                'linked_product',
                'attribute',
                'variations',
                'advanced',
                'marketplace-suggestions',
            ];

            foreach ($tabs_to_remove as $tab) {
                if (isset($tabs[$tab])) {
                    unset($tabs[$tab]);
                }
            }

            return $tabs;
        }, 99);
    }
});

/**
 * 3. Display Discount Badge in Admin Product List Table (edit.php?post_type=product)
 */
add_action('manage_product_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'price') {
        return;
    }

    $product = wc_get_product($post_id);
    if (!$product) {
        return;
    }

    $discount_percent = 0;

    if (function_exists('swac_calculate_price_data')) {
        $price_data = swac_calculate_price_data($product);
        $discount_percent = $price_data['discount_percent'];
    }

    if ($discount_percent <= 0 && $product->is_type('variable') && $product->is_on_sale()) {
        $min_regular = (float)$product->get_variation_regular_price('min', true);
        $min_sale = (float)$product->get_variation_sale_price('min', true);
        if ($min_regular > $min_sale && $min_regular > 0) {
            $discount_percent = (int)round((($min_regular - $min_sale) / $min_regular) * 100);
        }
    }

    if ($discount_percent > 0) {
        echo sprintf(
            '<span class="swac-admin-discount-badge wa-admin-discount-badge" style="display:inline-block; margin-left:6px; background:#dcfce7; color:#15803d; font-size:11px; font-weight:600; padding:1px 5px; border-radius:3px; vertical-align:middle; line-height:1.2;">%d%% OFF</span>',
            esc_html($discount_percent)
        );
    }
}, 10, 2);
