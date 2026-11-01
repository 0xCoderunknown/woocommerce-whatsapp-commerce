<?php
/**
 * 🏷️ FRONTEND SINGLE PRODUCT: META & ACTION SHORTCODES
 *
 * Core single product display shortcodes for price blocks, pack size badges,
 * and WhatsApp add-to-order action rows.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. Price Display Shortcode: [swac_price_block context="single|card"]
 */
if (!function_exists('swac_price_block_shortcode')) {
    function swac_price_block_shortcode($atts = []) {
        $atts = shortcode_atts([
            'context' => 'single',
        ], is_array($atts) ? $atts : [], 'swac_price_block');

        if (!function_exists('is_product') || !is_product()) {
            return '';
        }

        $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
        if (!$product) {
            return '';
        }

        $is_card = ($atts['context'] === 'card') || (function_exists('wc_in_product_loop') && wc_in_product_loop());
        return function_exists('swac_format_price_block_html') ? swac_format_price_block_html($product, $is_card) : '';
    }
}
add_shortcode('swac_price_block', 'swac_price_block_shortcode');

/**
 * 2. Pack Size Display Shortcode: [swac_pack_size]
 */
if (!function_exists('swac_pack_size_shortcode')) {
    function swac_pack_size_shortcode() {
        if (!function_exists('is_product') || !is_product()) {
            return '';
        }

        $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
        if (!$product) {
            return '';
        }

        $pack_size_tax = defined('SWAC_TAX_PACK_SIZE') ? SWAC_TAX_PACK_SIZE : 'pack_size';
        $terms = function_exists('swac_get_product_terms_memoized')
            ? swac_get_product_terms_memoized($product->get_id(), $pack_size_tax)
            : get_the_terms($product->get_id(), $pack_size_tax);

        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        $term_name = current($terms)->name;
        return sprintf('<span class="swac-pack-size">%s</span>', esc_html($term_name));
    }
}
add_shortcode('swac_pack_size', 'swac_pack_size_shortcode');

/**
 * 3. Unified Price + Pack Size Hierarchy Shortcode: [swac_price_pack_hierarchy context="single|card"]
 */
if (!function_exists('swac_price_pack_hierarchy_shortcode')) {
    function swac_price_pack_hierarchy_shortcode($atts = []) {
        $atts = shortcode_atts([
            'context' => 'single',
        ], is_array($atts) ? $atts : [], 'swac_price_pack_hierarchy');

        if (!function_exists('is_product') || !is_product()) {
            return '';
        }

        $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
        if (!$product) {
            return '';
        }

        $price_html = do_shortcode(sprintf('[swac_price_block context="%s"]', esc_attr($atts['context'])));
        $pack_html = do_shortcode('[swac_pack_size]');

        if (empty($price_html) && empty($pack_html)) {
            return '';
        }

        return sprintf(
            '<div class="swac-price-pack-wrap">%s%s</div>',
            $price_html,
            $pack_html
        );
    }
}
add_shortcode('swac_price_pack_hierarchy', 'swac_price_pack_hierarchy_shortcode');

/**
 * 4. Add to Order / WhatsApp Action Row Shortcode: [swac_action_row]
 */
if (!function_exists('swac_action_row_shortcode')) {
    function swac_action_row_shortcode() {
        if (!function_exists('is_product') || !is_product()) {
            return '';
        }

        $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
        if (!$product) {
            return '';
        }

        if (!$product->is_in_stock()) {
            return sprintf(
                '<div class="swac-action-row"><button type="button" class="button swac-out-of-stock-btn" disabled>%s</button></div>',
                esc_html__('Out of Stock', 'stateless-wa-commerce')
            );
        }

        $price = $product->get_price() ? strip_tags((string)wc_price($product->get_price())) : '';
        $mrp = (float)$product->get_regular_price();
        $sale_price = (float)$product->get_sale_price();
        if ($sale_price <= 0) {
            $sale_price = (float)$product->get_price();
        }
        $is_rx = (bool)apply_filters('swac_product_is_rx', false, $product);
        $icon = function_exists('swac_icon_svg') ? swac_icon_svg() : '';

        ob_start();
        ?>
        <div class="swac-action-row">
            <div class="swac-qty-selector">
                <button type="button" class="swac-qty-btn swac-qty-minus" aria-label="<?php esc_attr_e('Decrease quantity', 'stateless-wa-commerce'); ?>">−</button>
                <input type="number" class="swac-qty-input" value="1" min="1" max="99" aria-label="<?php esc_attr_e('Product quantity', 'stateless-wa-commerce'); ?>">
                <button type="button" class="swac-qty-btn swac-qty-plus" aria-label="<?php esc_attr_e('Increase quantity', 'stateless-wa-commerce'); ?>">+</button>
            </div>
            <button
                type="button"
                class="button swac-single-add-btn"
                data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                data-product-name="<?php echo esc_attr($product->get_name()); ?>"
                data-product-url="<?php echo esc_url($product->get_permalink()); ?>"
                data-product-price="<?php echo esc_attr($price); ?>"
                data-product-mrp="<?php echo esc_attr($mrp); ?>"
                data-product-sale-price="<?php echo esc_attr($sale_price); ?>"
                data-is-rx="<?php echo $is_rx ? '1' : '0'; ?>"
            >
                <?php echo $icon; ?>
                <span><?php esc_html_e('Add to Order', 'stateless-wa-commerce'); ?></span>
            </button>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
add_shortcode('swac_action_row', 'swac_action_row_shortcode');

/**
 * 5. Single Product Add-to-Cart Button Label Filter
 * Ensures standard WooCommerce templates display "Add to Order" rather than "Add to cart".
 */
add_filter('woocommerce_product_single_add_to_cart_text', function ($text, $product = null) {
    return (string)apply_filters('swac_single_button_text', __('Add to Order', 'stateless-wa-commerce'), $product);
}, 10, 2);