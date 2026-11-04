<?php
/**
 * 🗂️ EXPLORE MODULE: CATEGORIES GRID & DIRECTORY SEARCH
 *
 * Shortcodes:
 *  - [swac_categories_grid parent_slug="..."]
 *  - [swac_directory_search]
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('explore')) {
    return;
}

// ─── 1. SHORTCODE: CATEGORIES GRID ───────────────────────────────────────────
function swac_categories_grid_shortcode($atts)
{
    wp_enqueue_style('swac-explore-style');
    wp_enqueue_script('swac-frontend-script');

    $atts = shortcode_atts([
        'parent_slug' => '',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
        'image_size' => 'medium',
    ], $atts, 'swac_categories_grid');

    if (empty($atts['parent_slug'])) {
        return '<p class="swac-no-results">' . esc_html__('Please specify a parent_slug attribute (e.g. [swac_categories_grid parent_slug="your-category"]).', 'stateless-wa-commerce') . '</p>';
    }

    $parent_term = get_term_by('slug', $atts['parent_slug'], 'product_cat');
    if (!$parent_term || is_wp_error($parent_term)) {
        return '<p class="swac-no-results">' . sprintf(esc_html__('Category not found for slug "%s".', 'stateless-wa-commerce'), esc_html($atts['parent_slug'])) . '</p>';
    }

    $cache_ver = (int)get_option('swac_explore_cache_version', 1);
    $cache_key = 'swac_cats_' . md5(serialize($atts) . '_' . $parent_term->term_id . '_v' . $cache_ver);
    $terms = get_transient($cache_key);

    if (false === $terms) {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'child_of' => $parent_term->term_id,
            'hide_empty' => filter_var($atts['hide_empty'], FILTER_VALIDATE_BOOLEAN),
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ]);

        if (!is_wp_error($terms)) {
            set_transient($cache_key, $terms, HOUR_IN_SECONDS);
        }
    }

    if (empty($terms) || is_wp_error($terms)) {
        return '<p class="swac-no-results">' . esc_html__('No subcategories found. Please check if categories have associated products, or set hide_empty="false".', 'stateless-wa-commerce') . '</p>';
    }

    // Keep leaf terms
    $parent_ids = wp_list_pluck($terms, 'parent');
    $leaf_terms = [];
    foreach ($terms as $term) {
        if (!in_array($term->term_id, $parent_ids, true)) {
            $leaf_terms[] = $term;
        }
    }
    $terms = $leaf_terms;

    if (empty($terms)) {
        return '<p class="swac-no-results">' . esc_html__('No subcategories found. Please check if categories have associated products, or set hide_empty="false".', 'stateless-wa-commerce') . '</p>';
    }

    ob_start();
    ?>
    <div class="swac-explore-container">
        <?php $parent_heading = html_entity_decode((string)($parent_term->name ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        <h3 class="swac-letter-heading"><?php echo esc_html(strtoupper($parent_heading)) . ':'; ?></h3>

        <div class="swac-brand-grid">
            <?php foreach ($terms as $term) :
                $link = get_term_link($term);
                if (is_wp_error($link)) {
                    continue;
                }

                $image_id = get_term_meta($term->term_id, 'thumbnail_id', true);
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
                                    /* translators: %d: count */
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

add_shortcode('swac_categories_grid', 'swac_categories_grid_shortcode');

// ─── 2. SHORTCODE: DIRECTORY SEARCH ──────────────────────────────────────────
function swac_directory_search_shortcode($atts)
{
    wp_enqueue_script('swac-frontend-script');

    $atts = shortcode_atts([
        'placeholder' => __('Search directory...', 'stateless-wa-commerce'),
    ], $atts, 'swac_directory_search');

    ob_start();
    ?>
    <div class="swac-global-search-container">
        <div class="swac-search-hero-section">
            <div class="swac-search-filter-wrapper">
                <div class="swac-input-relative">
                    <input type="text"
                           class="swac-cat-search-input swac-live-search"
                           placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                           aria-label="<?php esc_attr_e('Filter directory', 'stateless-wa-commerce'); ?>"
                           autocomplete="off">
                    <span class="swac-search-icon">🔍</span>
                </div>
            </div>
        </div>
        <p class="swac-empty-state" style="display: none;">
            <?php esc_html_e('No items found matching your search.', 'stateless-wa-commerce'); ?>
        </p>
    </div>
    <?php
    return (string)ob_get_clean();
}

add_shortcode('swac_directory_search', 'swac_directory_search_shortcode');
