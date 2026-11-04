<?php
/**
 * 💬 FRONTEND SINGLE PRODUCT: WHATSAPP METADATA BRIDGE
 *
 * Injects a hidden DOM metadata anchor inside single product templates.
 * Consumed client-side by assets/js/wa_cart.js to populate the drawer order item
 * and perform DOM price reconciliation without server-side AJAX requests.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    if (!$product instanceof WC_Product) {
        $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
    }
    if (!$product instanceof WC_Product || !$product->is_in_stock()) {
        return;
    }

    $price = $product->get_price() ? wc_price($product->get_price()) : '';
    $price_plain = strip_tags((string)$price);

    $mrp = (float)$product->get_regular_price();
    $sale_price = (float)$product->get_sale_price();
    if ($sale_price <= 0) {
        $sale_price = (float)$product->get_price();
    }
    $is_rx = (bool)apply_filters('swac_product_is_rx', false, $product);
    ?>
    <span
            id="swac-product-data"
            class="swac-product-data"
            data-product-id="<?php echo esc_attr($product->get_id()); ?>"
            data-product-name="<?php echo esc_attr($product->get_name()); ?>"
            data-product-url="<?php echo esc_url(get_permalink($product->get_id())); ?>"
            data-product-price="<?php echo esc_attr($price_plain); ?>"
            data-product-mrp="<?php echo esc_attr($mrp); ?>"
            data-product-sale-price="<?php echo esc_attr($sale_price); ?>"
            data-is-rx="<?php echo $is_rx ? '1' : '0'; ?>"
            aria-hidden="true"
            style="display:none;"
    ></span>
    <?php
});
