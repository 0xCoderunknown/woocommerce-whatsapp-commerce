<?php
/**
 * 🧹 CORE: CACHE COORDINATOR & PURGE MANAGER
 *
 * Coordinates transient invalidation and targeted LiteSpeed Cache purging
 * when products, terms, or taxonomy relationships change.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_flush_explore_transients')) {
    /**
     * Increments the global transient version and purges targeted URLs in LiteSpeed Cache.
     *
     * @param string[] $purge_urls Optional array of specific URLs to purge.
     * @return void
     */
    function swac_flush_explore_transients(array $purge_urls = []): void
    {
        // 1. Increment cache version: invalidates all version-keyed transients atomically
        $current_version = (int)get_option('swac_explore_cache_version', 1);
        update_option('swac_explore_cache_version', $current_version + 1, false);

        // 2. Targeted LiteSpeed Cache Purges
        if (class_exists('LiteSpeed\\Purge')) {
            $default_urls = [
                home_url('/'),
                home_url('/sitemap.xml'),
            ];

            $all_purge_urls = array_unique(array_filter(array_merge($default_urls, $purge_urls)));

            /**
             * Filter URLs to purge in LiteSpeed Cache.
             *
             * @param string[] $all_purge_urls Array of URLs to purge.
             */
            $filtered_urls = (array)apply_filters('swac_cache_purge_urls', $all_purge_urls);

            foreach ($filtered_urls as $url) {
                if (!empty($url) && is_string($url)) {
                    do_action('litespeed_purge_url', $url);
                }
            }
        }
    }
}

if (!function_exists('swac_on_taxonomy_change')) {
    /**
     * Handler for taxonomy term creation, modification, or deletion.
     *
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     * @return void
     */
    function swac_on_taxonomy_change($term_id, $tt_id = 0, $taxonomy = ''): void
    {
        $purge_urls = [];
        if ($term_id && $taxonomy) {
            $term_link = get_term_link((int)$term_id, $taxonomy);
            if (!empty($term_link) && !is_wp_error($term_link)) {
                $purge_urls[] = $term_link;
            }
        }
        swac_flush_explore_transients($purge_urls);
    }
}

if (!function_exists('swac_on_product_save')) {
    /**
     * Handler for product save/update to purge affected URLs only.
     *
     * @param int $post_id
     * @return void
     */
    function swac_on_product_save($post_id): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $purge_urls = [];
        $permalink = get_permalink($post_id);
        if (!empty($permalink)) {
            $purge_urls[] = $permalink;
        }

        // Purge custom taxonomy term archives associated with this product
        $taxonomies = (array)apply_filters('swac_cache_purge_taxonomies', ['product_cat', 'product_brand']);

        foreach ($taxonomies as $tax) {
            $terms = function_exists('swac_get_product_terms_memoized')
                ? swac_get_product_terms_memoized($post_id, $tax)
                : get_the_terms($post_id, $tax);

            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $link = get_term_link($term);
                    if (!empty($link) && !is_wp_error($link)) {
                        $purge_urls[] = $link;
                    }
                }
            }
        }

        swac_flush_explore_transients($purge_urls);
    }
}

// 🔄 Flush when products are saved or updated
add_action('save_post_product', 'swac_on_product_save', 10, 1);

// Register dynamic taxonomy purge hooks
add_action('init', function () {
    $taxonomies = (array)apply_filters('swac_cache_purge_taxonomies', ['product_cat', 'product_brand']);
    foreach ($taxonomies as $tax) {
        add_action("edited_{$tax}", 'swac_on_taxonomy_change', 10, 3);
        add_action("delete_{$tax}", 'swac_on_taxonomy_change', 10, 3);
        add_action("create_{$tax}", 'swac_on_taxonomy_change', 10, 3);
    }
}, 20);
