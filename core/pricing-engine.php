<?php
/**
 * ⚡ CORE PRICING & CALCULATION ENGINE
 *
 * Provides pure computational pricing logic, discount calculations, regular price
 * strike-through formatting, and savings percentage accounting.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure core helper utilities are available
require_once __DIR__ . '/helpers.php';

// Automatically trim trailing zero decimals on prices (e.g. 25.00 -> 25)
add_filter('woocommerce_price_trim_zeros', '__return_true');

if (!function_exists('swac_calculate_price_data')) {
    /**
     * Calculates structured pricing data for a WooCommerce product.
     *
     * @param WC_Product $product
     * @return array
     */
    function swac_calculate_price_data(WC_Product $product): array
    {
        $price = (float)$product->get_price();
        $regular_price = (float)$product->get_regular_price();

        // If product is on sale or regular price exceeds current price
        $is_discounted = ($regular_price > $price && $price > 0);
        $savings_amount = $is_discounted ? ($regular_price - $price) : 0.0;
        $discount_percentage = ($is_discounted && $regular_price > 0)
            ? (int)round(($savings_amount / $regular_price) * 100)
            : 0;

        return [
            'price' => $price,
            'regular_price' => $regular_price,
            'is_discounted' => $is_discounted,
            'savings_amount' => $savings_amount,
            'discount_percentage' => $discount_percentage,
        ];
    }
}

if (!function_exists('swac_format_price_block_html')) {
    /**
     * Formats unified strike-through price block HTML with savings badge.
     *
     * @param WC_Product $product
     * @param bool $is_card Whether rendering inside an archive card.
     * @return string
     */
    function swac_format_price_block_html(WC_Product $product, bool $is_card = false): string
    {
        $data = swac_calculate_price_data($product);
        $context_class = $is_card ? 'swac-price-block--card' : 'swac-price-block--single';

        ob_start();
        ?>
        <div class="swac-price-block <?php echo esc_attr($context_class); ?>">
            <span class="swac-price-current"><?php echo wc_price($data['price']); ?></span>

            <?php if ($data['is_discounted']) : ?>
                <span class="swac-price-mrp-wrap">
                    <span class="swac-mrp-label"><?php esc_html_e('MRP', 'stateless-wa-commerce'); ?></span>
                    <del class="swac-price-regular"><?php echo wc_price($data['regular_price']); ?></del>
                </span>
                <span class="swac-discount-badge">
                    <?php echo sprintf(esc_html__('%d%% OFF', 'stateless-wa-commerce'), $data['discount_percentage']); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
