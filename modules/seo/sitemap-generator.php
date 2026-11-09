<?php
/**
 * 🗺️ INTEGRATIONS: NATIVE STREAMING XML SITEMAP GENERATOR
 *
 * High-performance, low-memory XML streaming endpoint (/sitemap.xml) for search engine crawlers.
 * Queries post IDs, modification timestamps, and taxonomy terms directly via $wpdb.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_sitemap_enabled')) {
    /**
     * Check if the native XML sitemap generator should be active.
     *
     * @return bool
     */
    function swac_is_sitemap_enabled(): bool
    {
        if (function_exists('swac_is_private_catalog') && swac_is_private_catalog()) {
            return false;
        }
        if (defined('SWAC_ENABLE_SITEMAP')) {
            return (bool)SWAC_ENABLE_SITEMAP;
        }
        return function_exists('swac_is_module_active') && swac_is_module_active('seo');
    }
}

// ── 1. Register Rewrite Rule & Query Var ──────────────────────────────────────
add_action('init', function () {
    if (swac_is_sitemap_enabled()) {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?swac_sitemap=1', 'top');
    }
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'swac_sitemap';
    return $vars;
});

// ── 2. Intercept Request & Output XML ─────────────────────────────────────────
add_action('template_redirect', 'swac_render_sitemap');

if (!function_exists('swac_render_sitemap')) {
    function swac_render_sitemap(): void
    {
        $is_sitemap = get_query_var('swac_sitemap');
        if ($is_sitemap !== '1') {
            return;
        }

        if (function_exists('swac_is_private_catalog') && swac_is_private_catalog()) {
            global $wp_query;
            if (isset($wp_query) && is_object($wp_query)) {
                $wp_query->set_404();
            }
            status_header(404);
            nocache_headers();
            return;
        }

        global $wpdb;

        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: text/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow', true);

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // ── 1. Homepage ───────────────────────────────────────────────────────
        $home_url = esc_url(home_url('/'));
        echo '  <url>' . "\n";
        echo '    <loc>' . $home_url . '</loc>' . "\n";
        echo '    <changefreq>daily</changefreq>' . "\n";
        echo '    <priority>1.0</priority>' . "\n";
        echo '  </url>' . "\n";

        // ── 2. Published Products (Excluding _swac_noindex) ────────────────────
        $limit = (int)apply_filters('swac_sitemap_products_limit', 5000);

        $products_query = "
            SELECT p.ID, p.post_name, p.post_modified_gmt, p.post_date_gmt
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_swac_noindex')
            WHERE p.post_type = 'product'
              AND p.post_status = 'publish'
              AND (pm.meta_value IS NULL OR pm.meta_value NOT IN ('1', 'true'))
            ORDER BY p.post_modified DESC
            LIMIT " . max(1, $limit) . "
        ";

        $products = $wpdb->get_results($products_query);

        if (!empty($products)) {
            _prime_post_caches(wp_list_pluck($products, 'ID'), false, false);

            foreach ($products as $prod) {
                $permalink = get_permalink($prod->ID);
                if (!$permalink) {
                    continue;
                }

                $mod_date = !empty($prod->post_modified_gmt) && $prod->post_modified_gmt !== '0000-00-00 00:00:00'
                    ? $prod->post_modified_gmt
                    : (!empty($prod->post_date_gmt) && $prod->post_date_gmt !== '0000-00-00 00:00:00' ? $prod->post_date_gmt : '');

                $ts = !empty($mod_date) ? strtotime($mod_date . ' UTC') : false;
                $lastmod = gmdate('Y-m-d\TH:i:s+00:00', $ts !== false ? $ts : time());

                echo '  <url>' . "\n";
                echo '    <loc>' . esc_url($permalink) . '</loc>' . "\n";
                echo '    <lastmod>' . esc_html($lastmod) . '</lastmod>' . "\n";
                echo '    <changefreq>weekly</changefreq>' . "\n";
                echo '    <priority>0.8</priority>' . "\n";
                echo '  </url>' . "\n";
            }
        }

        // ── 3. Active Taxonomy Terms ─────────────────────────────────────────
        $taxonomies = (array)apply_filters('swac_sitemap_taxonomies', ['product_cat', 'product_brand']);
        $taxonomies = array_filter($taxonomies, 'taxonomy_exists');

        if (!empty($taxonomies)) {
            $tax_placeholders = implode(',', array_fill(0, count($taxonomies), '%s'));

            $tax_query = $wpdb->prepare("
                SELECT t.term_id, tt.taxonomy
                FROM {$wpdb->terms} t
                INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ($tax_placeholders)
                  AND tt.count > 0
                ORDER BY t.name ASC
            ", $taxonomies);

            $terms = $wpdb->get_results($tax_query);

            if (!empty($terms)) {
                $term_ids = wp_list_pluck($terms, 'term_id');
                _prime_term_caches($term_ids);

                foreach ($terms as $term) {
                    $term_link = get_term_link((int)$term->term_id, $term->taxonomy);
                    if (empty($term_link) || is_wp_error($term_link)) {
                        continue;
                    }

                    echo '  <url>' . "\n";
                    echo '    <loc>' . esc_url($term_link) . '</loc>' . "\n";
                    echo '    <changefreq>weekly</changefreq>' . "\n";
                    echo '    <priority>0.6</priority>' . "\n";
                    echo '  </url>' . "\n";
                }
            }
        }

        echo '</urlset>';
        exit;
    }
}
