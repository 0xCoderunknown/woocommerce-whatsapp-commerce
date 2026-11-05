<?php
/**
 * 💊 PHARMACY MODULE: GENERIC ALTERNATIVE SPOTLIGHT
 *
 * Shortcode: [swac_generic_box]
 * Queries the cheapest equivalent generic alternative, calculates unit savings,
 * and formats a clinical savings spotlight box.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('pharmacy')) {
    return;
}

if (!function_exists('swac_generic_box_html')) {
    /**
     * Generates HTML markup for the spotlight alternate box.
     *
     * @param WC_Product $main_product
     * @param WP_Post $generic_post
     * @return string
     */
    function swac_generic_box_html(WC_Product $main_product, $generic_post): string
    {
        if (!$generic_post || !$main_product) {
            return '';
        }

        $alt_product = wc_get_product($generic_post->ID);
        if (!$alt_product) {
            return '';
        }

        $original_product_id = $main_product->get_id();

        $main_price = (float)$main_product->get_price();
        $alt_price = (float)$alt_product->get_price();

        $main_metrics = function_exists('swac_get_product_pack_metrics')
            ? swac_get_product_pack_metrics($main_product)
            : ['qty' => 1.0, 'unit_price' => $main_price];
        $alt_metrics = function_exists('swac_get_product_pack_metrics')
            ? swac_get_product_pack_metrics($alt_product)
            : ['qty' => 1.0, 'unit_price' => $alt_price];

        $main_unit_price = (float)$main_metrics['unit_price'];
        $alt_unit_price = (float)$alt_metrics['unit_price'];

        $unit_savings = max(0, $main_unit_price - $alt_unit_price);
        $total_savings = round($unit_savings * (float)$main_metrics['qty'], 2);
        if ($total_savings <= 0.0) {
            return '';
        }

        $currency = function_exists('swac_get_currency_symbol') ? swac_get_currency_symbol() : (function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '₹');
        $savings_formatted = $currency . number_format($total_savings, 2);

        $salt_name = function_exists('swac_get_product_salt_name')
            ? swac_get_product_salt_name($original_product_id)
            : '';

        $alt_pack_size = function_exists('swac_resolve_product_pack_size')
            ? swac_resolve_product_pack_size($alt_product)
            : '';

        $img_id = get_post_thumbnail_id($generic_post->ID);
        $img_data = $img_id ? wp_get_attachment_image_src($img_id, 'medium') : null;
        $img_url = $img_data ? $img_data[0] : wc_placeholder_img_src('medium');
        $img_width = $img_data ? (int)$img_data[1] : 300;
        $img_height = $img_data ? (int)$img_data[2] : 300;
        $alt_permalink = esc_url($alt_product->get_permalink());

        ob_start();
        ?>
        <section class="swac-spotlight-alternate-lg">
            <div class="swac-spotlight-header-lg">
                <span class="swac-spotlight-eyebrow"><?php esc_html_e('VERIFIED CLINICAL ALTERNATE', 'stateless-wa-commerce'); ?></span>
                <h3>
                    <?php
                    echo sprintf(
                    /* translators: %s: formatted savings amount */
                        esc_html__('Save %s with a Verified Alternate', 'stateless-wa-commerce'),
                        '<span class="swac-savings-amount">' . esc_html($savings_formatted) . '</span>'
                    );
                    ?>
                </h3>
                <p><?php esc_html_e('Same active ingredients. Same therapeutic effect. Quality tested.', 'stateless-wa-commerce'); ?></p>
            </div>
            <div class="swac-alternate-card-lg">
                <div class="swac-alt-thumb-lg">
                    <img src="<?php echo esc_url($img_url); ?>"
                         alt="<?php echo esc_attr(get_the_title($generic_post)); ?>"
                         width="<?php echo esc_attr($img_width); ?>"
                         height="<?php echo esc_attr($img_height); ?>"
                         loading="lazy">
                </div>
                <div class="swac-alt-info-lg">
                    <h3>
                        <a href="<?php echo $alt_permalink; ?>"><?php echo esc_html(get_the_title($generic_post)); ?></a>
                    </h3>
                    <?php if (!empty($salt_name)) : ?>
                        <div class="swac-alt-salt"><?php echo esc_html($salt_name); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($alt_pack_size)) : ?>
                        <div class="swac-alt-pack">📦 <?php echo esc_html($alt_pack_size); ?></div>
                    <?php endif; ?>
                    <div class="swac-alt-pricing">
                        <span class="swac-alt-price"><?php echo wc_price($alt_price); ?></span>
                    </div>
                </div>
                <div class="swac-alt-action-lg">
                    <a href="<?php echo $alt_permalink; ?>" class="button swac-switch-btn">
                        <?php esc_html_e('Switch & Save', 'stateless-wa-commerce'); ?>
                    </a>
                </div>
            </div>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}

function swac_generic_box_shortcode()
{
    if (!function_exists('is_product') || !is_product()) {
        return '';
    }

    $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
    if (!$product) {
        return '';
    }

    $alt_post = function_exists('swac_find_generic_alternate')
        ? swac_find_generic_alternate($product->get_id())
        : null;

    if (!$alt_post) {
        return '';
    }

    return swac_generic_box_html($product, $alt_post);
}

add_shortcode('swac_generic_box', 'swac_generic_box_shortcode');
