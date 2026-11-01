<?php
/**
 * 🛠️ BACKEND: AJAX SELECT2 TAXONOMY UI
 *
 * Replaces standard WordPress taxonomy meta boxes with high-performance Select2 AJAX dropdowns.
 * Prevents memory exhaustion on catalogs with thousands of terms.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function () {
    $default_taxonomies = defined('SWAC_SELECT2_TAXONOMIES') ? (array)SWAC_SELECT2_TAXONOMIES : ['product_brand'];
    $taxonomies = (array)apply_filters('swac_select2_taxonomies', $default_taxonomies);

    foreach ($taxonomies as $tax_slug) {
        $tax_obj = get_taxonomy($tax_slug);
        if (!$tax_obj) {
            continue;
        }

        // Remove default metaboxes
        remove_meta_box($tax_slug . 'div', 'product', 'side');
        remove_meta_box('tagsdiv-' . $tax_slug, 'product', 'side');

        // Add AJAX Select2 Dropdown
        add_meta_box(
            'swac_' . $tax_slug . '_dropdown',
            $tax_obj->labels->singular_name ?: $tax_obj->label,
            'swac_render_taxonomy_dropdown',
            'product',
            'side',
            'default',
            ['taxonomy' => $tax_slug]
        );
    }
});

if (!function_exists('swac_render_taxonomy_dropdown')) {
    /**
     * Render the Select2 dropdown box for a given taxonomy.
     *
     * @param WP_Post $post Post object.
     * @param array   $box  Meta box arguments.
     * @return void
     */
    function swac_render_taxonomy_dropdown($post, array $box): void
    {
        $taxonomy = $box['args']['taxonomy'] ?? '';
        if (!$taxonomy) {
            return;
        }

        $tax_obj = get_taxonomy($taxonomy);
        $tax_name = $tax_obj ? ($tax_obj->labels->singular_name ?: $tax_obj->label) : ucfirst($taxonomy);

        $selected_terms = wp_get_object_terms($post->ID, $taxonomy);
        $selected_term = (!empty($selected_terms) && !is_wp_error($selected_terms)) ? $selected_terms[0] : null;

        wp_nonce_field('swac_save_taxonomy_select2', 'swac_taxonomy_nonce_' . $taxonomy);
        ?>
        <div class="swac-taxonomy-select2-wrapper wa-taxonomy-select2-wrapper" style="margin-top: 5px;">
            <select
                name="swac_tax_select2[<?php echo esc_attr($taxonomy); ?>]"
                class="swac-taxonomy-select2 wa-taxonomy-select2"
                data-taxonomy="<?php echo esc_attr($taxonomy); ?>"
                data-placeholder="<?php echo esc_attr(sprintf(__('Select %s...', 'stateless-wa-commerce'), $tax_name)); ?>"
                style="width: 100%;"
            >
                <option value=""><?php echo esc_html(sprintf(__('— No %s —', 'stateless-wa-commerce'), $tax_name)); ?></option>
                <?php if ($selected_term) : ?>
                    <option value="<?php echo esc_attr($selected_term->term_id); ?>" selected>
                        <?php echo esc_html($selected_term->name); ?>
                    </option>
                <?php endif; ?>
            </select>
        </div>
        <?php
    }
}

/**
 * Save selected term on post save.
 */
add_action('save_post_product', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_product', $post_id)) {
        return;
    }

    $raw_input = $_POST['swac_tax_select2'] ?? null;
    if (empty($raw_input) || !is_array($raw_input)) {
        return;
    }

    $default_taxonomies = defined('SWAC_SELECT2_TAXONOMIES') ? (array)SWAC_SELECT2_TAXONOMIES : ['product_brand'];
    $allowed_taxonomies = (array)apply_filters('swac_select2_taxonomies', $default_taxonomies);

    foreach ($allowed_taxonomies as $taxonomy) {
        if (!taxonomy_exists($taxonomy) || !isset($raw_input[$taxonomy])) {
            continue;
        }

        $swac_nonce = 'swac_taxonomy_nonce_' . sanitize_key($taxonomy);
        if (!isset($_POST[$swac_nonce]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$swac_nonce])), 'swac_save_taxonomy_select2')) {
            continue;
        }

        $term_id = (int) $raw_input[$taxonomy];
        if ($term_id > 0) {
            wp_set_object_terms($post_id, [$term_id], $taxonomy, false);
        } else {
            wp_set_object_terms($post_id, [], $taxonomy, false);
        }
    }
});

/**
 * AJAX Endpoint: Search Taxonomy Terms for Select2
 */
$swac_search_terms_handler = function () {
    $nonce = sanitize_text_field(wp_unslash($_REQUEST['security'] ?? ''));
    if (!wp_verify_nonce($nonce, 'swac_admin_tax_nonce')) {
        wp_send_json_error('Invalid security token', 403);
    }

    if (!current_user_can('edit_products')) {
        wp_send_json_error('Unauthorized', 403);
    }

    $default_taxonomies = defined('SWAC_SELECT2_TAXONOMIES') ? (array)SWAC_SELECT2_TAXONOMIES : ['product_brand'];
    $allowed_taxonomies = (array)apply_filters('swac_select2_taxonomies', $default_taxonomies);
    $taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : '';

    if (!in_array($taxonomy, $allowed_taxonomies, true)) {
        wp_send_json(['results' => []]);
    }

    $search = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    if (mb_strlen($search) < 2) {
        wp_send_json(['results' => []]);
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'search'     => $search,
        'number'     => 20,
    ]);

    $results = [];
    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $results[] = [
                'id'   => (int)$term->term_id,
                'text' => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
            ];
        }
    }

    wp_send_json(['results' => $results]);
};

add_action('wp_ajax_swac_search_taxonomy_terms', $swac_search_terms_handler);

/**
 * Enqueue Select2 in admin product edit screens
 */
add_action('admin_enqueue_scripts', function ($hook) {
    global $post_type;

    if (!in_array($hook, ['post.php', 'post-new.php'], true) || $post_type !== 'product') {
        return;
    }

    // Use WooCommerce core registered Select2 assets
    wp_enqueue_style('select2');
    wp_enqueue_script('select2');

    wp_enqueue_script(
        'swac-admin-taxonomy',
        SWAC_URL . 'assets/js/admin-taxonomy.js',
        ['jquery', 'select2'],
        SWAC_VERSION,
        true
    );

    $localize_payload = [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('swac_admin_tax_nonce'),
    ];

    wp_localize_script('swac-admin-taxonomy', 'swacAdminTax', $localize_payload);
});
