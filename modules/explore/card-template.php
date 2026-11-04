<?php
/**
 * 🗂️ EXPLORE MODULE: REUSABLE DIRECTORY CARD TEMPLATE
 *
 * Renders standardized explore cards with image thumbnails and monogram fallback badges.
 * Reused across Brand and Category directories.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_render_explore_card')) {
    /**
     * Renders a directory item card.
     *
     * @param WP_Term $term Term object.
     * @param string $image_size Image size name.
     * @return string
     */
    function swac_render_explore_card(WP_Term $term, string $image_size = 'medium'): string
    {
        $link = get_term_link($term);
        if (is_wp_error($link)) {
            return '';
        }

        $image_id = get_term_meta($term->term_id, 'thumbnail_id', true);
        if (!$image_id) {
            $image_id = get_term_meta($term->term_id, 'logo_id', true);
        }
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, $image_size) : false;
        $first_letter = function_exists('swac_get_term_initial')
            ? swac_get_term_initial((string)($term->name ?? ''))
            : '#';

        ob_start();
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
        <?php
        return (string)ob_get_clean();
    }
}
