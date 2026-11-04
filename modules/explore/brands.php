<?php
/**
 * 🏷️ EXPLORE MODULE: BRANDS DIRECTORY GRID
 *
 * Shortcode: [swac_explore_brands]
 * Attributes:
 *  - columns    : Responsive column count (default: 4)
 *  - hide_empty : Hide brands without published products (default: true)
 *  - image_size : Thumbnail image size (default: 'medium')
 *  - orderby    : Term sort field (default: 'name')
 *  - order      : Sort direction (default: 'ASC')
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('explore')) {
    return;
}

function swac_explore_brands_shortcode($atts)
{
    wp_enqueue_style('swac-explore-style');
    wp_enqueue_script('swac-frontend-script');

    $atts = shortcode_atts([
        'columns' => 4,
        'hide_empty' => true,
        'image_size' => 'medium',
        'orderby' => 'name',
        'order' => 'ASC',
    ], $atts, 'swac_explore_brands');

    $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';
    if (!taxonomy_exists($brand_tax)) {
        return '';
    }

    $cache_ver = (int)get_option('swac_explore_cache_version', 1);
    $cache_key = 'swac_brands_' . md5(serialize($atts) . '_v' . $cache_ver);
    $terms = get_transient($cache_key);

    if (false === $terms) {
        $terms = get_terms([
            'taxonomy' => $brand_tax,
            'hide_empty' => filter_var($atts['hide_empty'], FILTER_VALIDATE_BOOLEAN),
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ]);

        if (!is_wp_error($terms)) {
            set_transient($cache_key, $terms, HOUR_IN_SECONDS);
        }
    }

    if (empty($terms) || is_wp_error($terms)) {
        return '<p class="swac-no-results">' . esc_html__('No brands found. Please check if brands have associated products, or set hide_empty="false".', 'stateless-wa-commerce') . '</p>';
    }

    ob_start();
    ?>
    <div class="swac-global-search-container">
        <div class="swac-search-hero-section">
            <div class="swac-search-filter-wrapper">
                <div class="swac-input-relative">
                    <input type="text"
                           id="swac-brand-search"
                           class="swac-cat-search-input swac-live-search"
                           data-target="#swac-brand-list"
                           placeholder="<?php esc_attr_e('Search brands...', 'stateless-wa-commerce'); ?>"
                           aria-label="<?php esc_attr_e('Filter brands', 'stateless-wa-commerce'); ?>"
                           autocomplete="off">
                    <span class="swac-search-icon">🔍</span>
                </div>
            </div>
        </div>
        <p id="swac-brands-no-results" class="swac-empty-state" style="display: none;">
            <?php esc_html_e('No brands found matching your search.', 'stateless-wa-commerce'); ?>
        </p>
    </div>

    <div class="swac-explore-wrapper">
        <div class="swac-brand-grid" id="swac-brand-list">
            <?php foreach ($terms as $term) :
                $link = get_term_link($term);
                if (is_wp_error($link)) continue;

                $image_id = get_term_meta($term->term_id, 'thumbnail_id', true);
                if (!$image_id) {
                    $image_id = get_term_meta($term->term_id, 'logo_id', true);
                }
                $image_url = $image_id ? wp_get_attachment_image_url($image_id, $atts['image_size']) : false;
                $first_letter = function_exists('swac_get_term_initial') ? swac_get_term_initial((string)($term->name ?? '')) : '#';
                ?>
                <div class="swac-brand-card swac-search-item"
                     data-title="<?php echo esc_attr(strtolower($term->name)); ?>">
                    <a href="<?php echo esc_url($link); ?>" class="swac-brand-link">
                        <div class="swac-brand-image-wrap">
                            <?php if ($image_url) : ?>
                                <img src="<?php echo esc_url($image_url); ?>"
                                     alt="<?php echo esc_attr($term->name); ?>"
                                     class="swac-brand-img"
                                     loading="lazy">
                            <?php else : ?>
                                <div class="swac-brand-placeholder">
                                    <span><?php echo esc_html($first_letter); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="swac-brand-meta">
                            <span class="swac-brand-title"><?php echo esc_html($term->name); ?></span>
                            <?php if ($term->count > 0) : ?>
                                <span class="swac-brand-count">
                                    <?php
                                    echo sprintf(
                                    /* translators: %d: product count */
                                        esc_html(_n('%d product', '%d products', $term->count, 'stateless-wa-commerce')),
                                        $term->count
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

add_shortcode('swac_explore_brands', 'swac_explore_brands_shortcode');
