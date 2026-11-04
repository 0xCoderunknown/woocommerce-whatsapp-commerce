<?php
/**
 * 🛠️ BACKEND: CATALOG AUDIT & MISSING IMAGES TOOL
 *
 * Shortcode: [swac_missing_images_finder]
 *
 * Provides a low-memory catalog diagnostic report identifying published products
 * that lack featured thumbnails, prioritized by in-stock items.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_render_missing_images_finder')) {
    /**
     * Renders the missing product images audit interface.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    function swac_render_missing_images_finder(array $atts = []): string
    {
        if (!current_user_can('manage_woocommerce')) {
            return '<p class="swac-tax-error">' . esc_html__('Access Denied: Insufficient Privileges.', 'stateless-wa-commerce') . '</p>';
        }

        $parsed_atts = shortcode_atts([
            'limit' => 200,
        ], $atts, 'swac_missing_images_finder');

        $limit = max(1, min(1000, (int)$parsed_atts['limit']));

        $args = [
            'post_type' => ['product'],
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_thumbnail_id',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_thumbnail_id',
                    'value' => ['', '0'],
                    'compare' => 'IN',
                ],
            ],
        ];

        $product_ids = get_posts($args);

        if (empty($product_ids)) {
            return '<p style="color:#16a34a; font-weight:bold; padding:15px; background:#f0fdf4; border-radius:4px;">'
                . esc_html__('🎉 Catalog in alignment: Zero products are missing featured images.', 'stateless-wa-commerce')
                . '</p>';
        }

        // Prime caches to eliminate N+1 DB lookups
        update_meta_cache('post', $product_ids);
        update_object_term_cache($product_ids, 'product');

        $audit_data = [];

        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }

            $raw_sku = $product->get_sku();
            $sku = !empty($raw_sku) ? esc_html($raw_sku) : '<span style="color:#94a3b8; font-style:italic;">' . esc_html__('No SKU', 'stateless-wa-commerce') . '</span>';
            $name = $product->get_name();
            $stock_status = $product->get_stock_status();
            $edit_url = get_edit_post_link($id);

            $sort_weight = ($stock_status === 'instock') ? 0 : 1;

            $audit_data[] = [
                'id' => $id,
                'sku' => $sku,
                'name' => $name,
                'stock_status' => $stock_status,
                'edit_url' => $edit_url,
                'sort_weight' => $sort_weight,
            ];
        }

        usort($audit_data, function ($a, $b) {
            return $a['sort_weight'] <=> $b['sort_weight'];
        });

        ob_start();
        include __DIR__ . '/templates/missing-images-report.php';
        return (string)ob_get_clean();
    }
}

add_shortcode('swac_missing_images_finder', 'swac_render_missing_images_finder');

/**
 * 🛍️ ADMIN NOTICES: WOOCOMMERCE DEPENDENCY & CONFIGURATION WARNINGS
 *
 * - Shows an error if WooCommerce is not installed/active.
 * - Shows a warning if no WhatsApp phone number has been configured.
 */
add_action('admin_notices', function () {
    if (!class_exists('WooCommerce')) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php esc_html_e('Stateless WhatsApp Commerce', 'stateless-wa-commerce'); ?></strong>
                <?php esc_html_e('requires WooCommerce to be installed and active.', 'stateless-wa-commerce'); ?>
            </p>
        </div>
        <?php
        return;
    }

    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
    if (empty($cfg['phone_number']) && empty($cfg['base_url'])) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('Stateless WhatsApp Commerce:', 'stateless-wa-commerce'); ?></strong>
                <?php esc_html_e('No WhatsApp phone number is configured. Customer ordering links and buttons are currently disabled to prevent broken links. Please set SWAC_PHONE in core/config.php or via the swac_commerce_config filter.', 'stateless-wa-commerce'); ?>
            </p>
        </div>
        <?php
    }
});
