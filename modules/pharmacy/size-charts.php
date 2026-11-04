<?php
/**
 * 📏 PHARMACY MODULE: CATEGORY SIZE CHARTS
 *
 * Shortcode: [swac_size_chart]
 * Renders HTML5 accordion size specification tables based on product categories.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('pharmacy')) {
    return;
}

if (!function_exists('swac_render_size_chart')) {
    function swac_render_size_chart(): string
    {
        $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
        if (!$product instanceof WC_Product) {
            return '';
        }

        $default_charts = [
            'adult-diaper' => [
                'slugs' => ['adult-diaper-bed-pan', 'adult-diapers-underpads'],
                'title' => __('📏 Adult Diaper Size Chart', 'stateless-wa-commerce'),
                'header' => [__('Size', 'stateless-wa-commerce'), __('Waist Range', 'stateless-wa-commerce')],
                'rows' => [
                    ['<strong>M</strong> (Medium)', '28 – 44 inches'],
                    ['<strong>L</strong> (Large)', '38 – 54 inches'],
                    ['<strong>XL</strong> (Extra Large)', '48 – 68 inches'],
                ],
                'note' => __('*Sizes are approximate. Measure waist for the best fit.', 'stateless-wa-commerce'),
            ],
            'baby-diaper' => [
                'slugs' => ['baby-diaper', 'diapers-pants'],
                'title' => __('👶 Baby Diaper Size Chart', 'stateless-wa-commerce'),
                'header' => [__('Size', 'stateless-wa-commerce'), __('Weight Range', 'stateless-wa-commerce')],
                'rows' => [
                    ['New Born (NB)', 'Up to 5 kg'],
                    ['Small (S)', '4 – 8 kg'],
                    ['Medium (M)', '7 – 12 kg'],
                    ['Large (L)', '9 – 14 kg'],
                    ['Extra Large (XL)', '12 – 17 kg'],
                ],
                'note' => __('*Weight ranges may vary slightly by brand fit.', 'stateless-wa-commerce'),
            ],
        ];

        /**
         * Filter category size charts configuration.
         *
         * @param array $default_charts Default size charts dictionary.
         */
        $chart_config = (array)apply_filters('swac_category_size_charts', $default_charts);

        $active_config = null;
        $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
        if (empty($product_cats) || is_wp_error($product_cats)) {
            return '';
        }

        foreach ($chart_config as $config) {
            if (!empty(array_intersect($config['slugs'], $product_cats))) {
                $active_config = $config;
                break;
            }
        }

        if (!$active_config) {
            return '';
        }

        ob_start();
        ?>
        <details class="swac-size-chart-accordion">
            <summary class="swac-size-chart-summary">
                <span><?php echo esc_html($active_config['title']); ?></span>
            </summary>
            <div class="swac-size-chart-content">
                <table class="swac-size-table">
                    <thead>
                    <tr>
                        <?php foreach ($active_config['header'] as $th) : ?>
                            <th><?php echo esc_html($th); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($active_config['rows'] as $row) : ?>
                        <tr>
                            <td><?php echo wp_kses_post($row[0]); ?></td>
                            <td><?php echo wp_kses_post($row[1]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!empty($active_config['note'])) : ?>
                    <p class="swac-size-chart-note"><?php echo esc_html($active_config['note']); ?></p>
                <?php endif; ?>
            </div>
        </details>
        <?php
        return (string)ob_get_clean();
    }
}

add_shortcode('swac_size_chart', 'swac_render_size_chart');

add_action('woocommerce_after_add_to_cart_button', function () {
    if (function_exists('is_product') && !is_product()) {
        return;
    }
    echo swac_render_size_chart();
}, 100);
