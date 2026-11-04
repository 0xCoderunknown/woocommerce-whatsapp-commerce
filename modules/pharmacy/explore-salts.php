<?php
/**
 * 🧪 PHARMACY MODULE: COMPOSITIONS / SALTS DIRECTORY
 *
 * Shortcode: [swac_explore_salts]
 * Attributes:
 *  - columns    : Responsive column count (default: 4)
 *  - hide_empty : Hide salts without published products (default: false)
 *  - orderby    : Term sort field (default: 'name')
 *  - order      : Sort direction (default: 'ASC')
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('pharmacy')) {
    return;
}

function swac_explore_salts_shortcode($atts)
{
    wp_enqueue_style('swac-pharmacy-style');
    wp_enqueue_style('swac-explore-style');
    wp_enqueue_script('swac-frontend-script');

    $atts = shortcode_atts([
        'columns' => 4,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ], $atts, 'swac_explore_salts');

    $salt_tax = defined('SWAC_TAX_SALT') ? SWAC_TAX_SALT : 'composition';
    if (!taxonomy_exists($salt_tax)) {
        return '';
    }

    $cache_ver = (int)get_option('swac_explore_cache_version', 1);
    $cache_key = 'swac_salts_' . md5(serialize($atts) . '_v' . $cache_ver);
    $terms = get_transient($cache_key);

    if (false === $terms) {
        $terms = get_terms([
            'taxonomy' => $salt_tax,
            'hide_empty' => filter_var($atts['hide_empty'], FILTER_VALIDATE_BOOLEAN),
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ]);

        if (!is_wp_error($terms)) {
            set_transient($cache_key, $terms, HOUR_IN_SECONDS);
        }
    }

    if (empty($terms) || is_wp_error($terms)) {
        return '<p class="swac-no-results">' . esc_html__('No compositions found. Please check if compositions have associated products, or set hide_empty="false".', 'stateless-wa-commerce') . '</p>';
    }

    $columns = max(1, min(6, intval($atts['columns'])));

    $grouped = [];
    foreach ($terms as $term) {
        $first_char = function_exists('swac_get_term_initial') ? swac_get_term_initial((string)($term->name ?? '')) : '#';
        if (!preg_match('/[A-Z]/', $first_char)) {
            $first_char = '#';
        }
        $grouped[$first_char][] = $term;
    }
    ksort($grouped);

    ob_start();
    ?>
    <div class="swac-global-search-container">
        <div class="swac-search-hero-section">
            <div class="swac-search-filter-wrapper">
                <div class="swac-input-relative">
                    <input type="text"
                           id="swac-salt-search"
                           class="swac-cat-search-input swac-live-search"
                           data-target="#swac-salt-list"
                           placeholder="<?php esc_attr_e('Search active compositions...', 'stateless-wa-commerce'); ?>"
                           aria-label="<?php esc_attr_e('Filter compositions', 'stateless-wa-commerce'); ?>"
                           autocomplete="off">
                    <span class="swac-search-icon">🔍</span>
                </div>
            </div>
        </div>
        <p id="swac-salts-no-results" class="swac-empty-state" style="display: none;">
            <?php esc_html_e('No compositions found matching your search.', 'stateless-wa-commerce'); ?>
        </p>
    </div>

    <div class="swac-explore-wrapper" id="swac-salt-list" style="--swac-salt-cols: <?php echo esc_attr($columns); ?>;">
        <?php foreach ($grouped as $letter => $letter_terms) : ?>
            <div class="swac-letter-section" data-letter="<?php echo esc_attr($letter); ?>">
                <h3 class="swac-letter-heading"><?php echo esc_html($letter); ?></h3>
                <div class="swac-grid">
                    <?php foreach ($letter_terms as $term) :
                        $link = get_term_link($term);
                        if (is_wp_error($link)) continue;
                        ?>
                        <a href="<?php echo esc_url($link); ?>" class="swac-item-card">
                            <span class="swac-item-name"><?php echo esc_html($term->name); ?></span>
                            <?php if ($term->count > 0) : ?>
                                <span class="swac-item-count">(<?php echo esc_html($term->count); ?>)</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

add_shortcode('swac_explore_salts', 'swac_explore_salts_shortcode');
