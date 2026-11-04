<?php
/**
 * 🧹 CORE: SYSTEM CLEANUP & BLOAT SUPPRESSION
 *
 * Implements optional developer cleanups and performance tunings:
 * 1. Head bloat removal (emojis, generator tags, wlwmanifest, RSD).
 * 2. Security hardening (XML-RPC, REST user enumeration blocking).
 * 3. Heartbeat & product reviews/comments suppression.
 * 4. Admin menu and marketing dashboard bloat cleanup.
 * 5. Frontend script and style dequeuing.
 *
 * All features are individually toggleable via features.php or the 'swac_lockdown_features' filter.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Master lockdown check
if (!apply_filters('swac_enable_catalog_lockdown', true)) {
    return;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. WP HEAD BLOAT & CLEANUP
// ─────────────────────────────────────────────────────────────────────────────

if (swac_is_lockdown_enabled('head_bloat')) {
    add_action('init', function () {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');

        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');

        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);

        remove_action('wp_head', 'wp_shortlink_wp_head', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');

        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('template_redirect', 'rest_output_link_header', 11, 0);

        remove_action('wp_head', 'adjacent_posts_rel_link_under_10', 10);
        remove_action('wp_head', 'adjacent_posts_rel_link', 10);
        remove_action('wp_head', 'pingback_rel_link');
    }, 10);
    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');

    add_action('after_setup_theme', function () {
        remove_theme_support('wc-product-gallery-zoom');
        remove_theme_support('wc-product-gallery-lightbox');
        remove_theme_support('wc-product-gallery-slider');
    }, 100);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. SECURITY & REST API HARDENING
// ─────────────────────────────────────────────────────────────────────────────

if (swac_is_lockdown_enabled('security_hardening')) {
    add_filter('xmlrpc_enabled', '__return_false');

    add_action('init', function () {
        remove_action('rest_api_init', 'wp_oembed_register_route');
    }, 11);
    add_filter('embed_oembed_discover', '__return_false');

    add_filter('rest_pre_dispatch', function ($result, $server, $request) {
        $route = $request->get_route();

        if (str_contains($route, '/wp/v2/users') && !current_user_can('list_users')) {
            return new WP_Error('rest_forbidden', 'Access Denied', ['status' => 403]);
        }

        if (!is_user_logged_in() && str_starts_with($route, '/wc/')) {
            return new WP_Error('rest_forbidden', 'Access Denied', ['status' => 403]);
        }

        return $result;
    }, 10, 3);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. PRIVATE STEALTH CATALOG & SEARCH ENGINE SHIELD
// ─────────────────────────────────────────────────────────────────────────────

if (function_exists('swac_is_private_catalog') && swac_is_private_catalog()) {
    add_action('send_headers', function () {
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }
    });

    add_action('wp_head', function () {
        echo '<meta name="robots" content="noindex, nofollow, noarchive">' . "\n";
    }, 1);

    add_filter('robots_txt', function ($output) {
        return "User-agent: *\nDisallow: /\n";
    }, 99);

    add_filter('wp_sitemaps_enabled', '__return_false');
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. HEARTBEAT & COMMENTS DISABLE
// ─────────────────────────────────────────────────────────────────────────────

if (swac_is_lockdown_enabled('heartbeat_comments')) {
    add_action('init', function () {
        if (!is_admin()) {
            wp_deregister_script('heartbeat');
        }
    }, 1);

    add_filter('comments_open', '__return_false', 20, 2);
    add_filter('pings_open', '__return_false', 20, 2);
    add_filter('comments_array', '__return_empty_array', 10, 2);

    add_filter('woocommerce_product_data_tabs', function ($tabs) {
        if (isset($tabs['reviews'])) {
            unset($tabs['reviews']);
        }
        return $tabs;
    }, 98);
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. ADMIN DASHBOARD & MENU CLEANUP
// ─────────────────────────────────────────────────────────────────────────────

if (swac_is_lockdown_enabled('admin_cleanup')) {
    add_action('after_setup_theme', function () {
        if (!is_user_logged_in()) {
            show_admin_bar(false);
        }
    });

    if (is_admin()) {
        add_filter('use_block_editor_for_post_type', function ($use_block_editor, $post_type) {
            if ($post_type === 'product') {
                return false;
            }
            return $use_block_editor;
        }, 100, 2);
        add_filter('use_widgets_block_editor', '__return_false');

        add_action('admin_menu', function () {
            remove_menu_page('edit-comments.php');
            remove_menu_page('woocommerce-marketing');
            remove_menu_page('wc-admin');
            remove_submenu_page('woocommerce', 'wc-admin');
            remove_submenu_page('woocommerce', 'wc-admin&path=/analytics/overview');
            remove_submenu_page('woocommerce', 'wc-addons');
            remove_submenu_page('woocommerce', 'wc-status');
        }, 9999);

        $redirect_wc_admin = function () {
            if (!isset($_GET['page'])) {
                return;
            }

            $page = sanitize_key($_GET['page']);

            if ($page === 'wc-admin') {
                $task = isset($_GET['task']) ? sanitize_text_field(wp_unslash($_GET['task'])) : '';
                $path = isset($_GET['path']) ? sanitize_text_field(wp_unslash($_GET['path'])) : '';

                if ($task === 'products' || strpos($path, 'add-product') !== false) {
                    wp_safe_redirect(admin_url('post-new.php?post_type=product'));
                    exit;
                }

                wp_safe_redirect(admin_url('edit.php?post_type=product'));
                exit;
            }

            if (in_array($page, ['wc-addons', 'woocommerce-marketing'], true)) {
                wp_safe_redirect(admin_url('edit.php?post_type=product'));
                exit;
            }
        };

        add_action('admin_page_access_denied', $redirect_wc_admin);
        add_action('admin_init', $redirect_wc_admin);

        add_filter('woocommerce_admin_features', function ($features) {
            $bloat_features = [
                'marketing',
                'analytics',
                'analytics-dashboard',
                'analytics-settings',
                'coupons',
                'marketplace',
                'onboarding',
                'homescreen',
                'activity-panels',
                'remote-inbox-notifications',
            ];
            return array_values(array_diff($features, $bloat_features));
        });

        add_filter('woocommerce_allow_marketplace_suggestions', '__return_false');
        add_filter('woocommerce_helper_suppress_connect_notice', '__return_true');
        add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');
        add_filter('woocommerce_show_admin_notice_about_connection', '__return_false');

        add_action('wp_dashboard_setup', function () {
            remove_meta_box('dashboard_primary', 'dashboard', 'side');
            remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
            remove_meta_box('dashboard_activity', 'dashboard', 'normal');
            remove_meta_box('woocommerce_dashboard_status', 'dashboard', 'normal');
            remove_meta_box('woocommerce_dashboard_recent_reviews', 'dashboard', 'normal');
        });

        add_filter('rank_math/admin/promo_notices', '__return_empty_array');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. REGISTRATION & EMAIL SUPPRESSION
// ─────────────────────────────────────────────────────────────────────────────

if (swac_is_lockdown_enabled('registration_suppress')) {
    add_action('wp_loaded', function () {
        if (is_admin()) {
            return;
        }

        remove_action('woocommerce_register_form_start', 'woocommerce_registration_form_start');
        add_filter('woocommerce_registration_generate_username', '__return_false');
        if (!current_user_can('manage_woocommerce')) {
            add_filter('woocommerce_registration_generate_password', '__return_false');
        }
        add_filter('option_users_can_register', '__return_false');
    }, 99);

    add_filter('woocommerce_email_enabled_customer_new_account', '__return_false');
    add_filter('woocommerce_email_enabled_customer_reset_password', '__return_false');
    add_filter('woocommerce_email_enabled_customer_note', '__return_false');
    add_filter('woocommerce_email_enabled_customer_processing_order', '__return_false');
    add_filter('woocommerce_email_enabled_customer_completed_order', '__return_false');
    add_filter('woocommerce_email_enabled_customer_refunded_order', '__return_false');
    add_filter('woocommerce_email_enabled_customer_invoice', '__return_false');
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. SCRIPT & STYLE DEQUEUE
// ─────────────────────────────────────────────────────────────────────────────

if (swac_is_lockdown_enabled('script_dequeue')) {
    add_action('wp_enqueue_scripts', function () {
        if (is_admin()) {
            return;
        }

        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-blocks-style');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('wc-blocks-integration-css');
        wp_dequeue_style('classic-theme-styles');

        wp_dequeue_style('woocommerce-layout');
        wp_dequeue_style('woocommerce-smallscreen');
        wp_dequeue_style('woocommerce-general');

        wp_dequeue_script('flexslider');
        wp_dequeue_script('zoom');
        wp_dequeue_script('photoswipe');
        wp_dequeue_script('photoswipe-ui-default');
        wp_dequeue_style('photoswipe');
        wp_dequeue_style('photoswipe-default-skin');

        wp_dequeue_script('wp-embed');
        wp_deregister_script('wp-embed');

        if (!is_user_logged_in()) {
            wp_dequeue_style('dashicons');
            wp_deregister_style('dashicons');
        }

        wp_dequeue_script('wc-order-attribution');
        wp_deregister_script('wc-order-attribution');
        wp_dequeue_script('sourcebuster-js');
        wp_deregister_script('sourcebuster-js');

        wp_dequeue_script('wc-cart-fragments');
        wp_deregister_script('wc-cart-fragments');

        wp_dequeue_script('selectWoo');
        wp_dequeue_style('select2');

        wp_dequeue_script('wc-password-strength-meter');
        wp_deregister_script('wc-password-strength-meter');

        wp_dequeue_script('zxcvbn-async');
        wp_deregister_script('zxcvbn-async');

        wp_dequeue_script('wc-checkout');
        wp_deregister_script('wc-checkout');

        wp_dequeue_script('wc-address-i18n');
        wp_deregister_script('wc-address-i18n');

        wp_dequeue_script('wc-add-to-cart');
        wp_deregister_script('wc-add-to-cart');
    }, 100);
}
