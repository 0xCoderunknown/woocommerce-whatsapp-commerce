<?php
/**
 * 🔒 CORE: MASTER CATALOG LOCKDOWN & ZERO-SESSION PERFORMANCE ENGINE
 *
 * Suppresses WooCommerce guest sessions, cart fragments, and guest cookies for catalog operation.
 * Serves public pages from full-page static cache without PHP or database work on cache hits.
 *
 * @package StatelessWaCommerce
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if (!defined('ABSPATH')) {
    exit;
}

// 🔒 MASTER LOCKDOWN TOGGLE
// Allows store owners to completely disable the catalog lockdown subsystem if running in hybrid mode
if (!apply_filters('swac_enable_catalog_lockdown', true)) {
    return;
}

/**
 * Check if a lockdown feature is enabled.
 *
 * @param string $feature Feature identifier.
 * @return bool
 */
function swac_is_lockdown_enabled(string $feature): bool
{
    static $features = null;
    if ($features === null) {
        $defaults = [
            'head_bloat' => true,
            'security_hardening' => true,
            'heartbeat_comments' => true,
            'cart_checkout_redirect' => true,
            'wc_ajax_intercept' => true,
            'admin_cleanup' => true,
            'registration_suppress' => true,
            'script_dequeue' => true,
            'session_tuning' => true,
        ];
        $features = (array)apply_filters('swac_lockdown_features', $defaults);
    }
    return !empty($features[$feature]);
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1: WP HEAD BLOAT & CLEANUP
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
// SECTION 2: SECURITY & REST API HARDENING
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
// SECTION 2B: PRIVATE STEALTH CATALOG & SEARCH ENGINE SHIELD
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
// SECTION 3: HEARTBEAT & COMMENTS DISABLE
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
// SECTION 4: FRONTEND PAGE & ADMIN LOCKDOWN (302 GATE)
// ─────────────────────────────────────────────────────────────────────────────

if (swac_is_lockdown_enabled('cart_checkout_redirect')) {
    add_action('template_redirect', function () {
        // Exempt wp-admin, Customizer preview, and visual page builder editors
        if (is_admin() || (function_exists('is_customize_preview') && is_customize_preview())) {
            return;
        }
        if (isset($_GET['elementor-preview']) || isset($_GET['breakdance'])) {
            return;
        }

        $is_locked_page = (
            (function_exists('is_cart') && is_cart()) ||
            (function_exists('is_checkout') && is_checkout()) ||
            (function_exists('is_account_page') && is_account_page())
        );

        if ($is_locked_page) {
            wp_safe_redirect(home_url('/'), 302);
            exit;
        }
    }, 1);

    add_action('login_init', function () {
        if (is_user_logged_in()) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
        if (in_array($action, ['register', 'lostpassword', 'retrievepassword'], true)) {
            wp_safe_redirect(home_url('/'), 302);
            exit;
        }
    });

    add_filter('woocommerce_get_cart_url', function ($url) {
        return is_admin() ? $url : home_url('/');
    });
    add_filter('woocommerce_get_checkout_url', function ($url) {
        return is_admin() ? $url : home_url('/');
    });
    add_filter('woocommerce_get_myaccount_page_permalink', function ($url) {
        return is_admin() ? $url : home_url('/');
    });

    add_action('woocommerce_init', function () {
        if (is_admin()) {
            return;
        }
        if (function_exists('wc_clear_notices')) {
            add_action('template_redirect', function () {
                wc_clear_notices();
            }, 2);
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5: ADD-TO-CART & WC-AJAX INTERCEPT
// ─────────────────────────────────────────────────────────────────────────────

if (swac_is_lockdown_enabled('wc_ajax_intercept')) {
    add_action('wp_loaded', function () {
        if (is_admin()) {
            return;
        }

        if (!empty($_GET['add-to-cart'])) {
            unset($_GET['add-to-cart'], $_REQUEST['add-to-cart']);

            $referer = wp_get_referer();
            $target = $referer ? remove_query_arg('add-to-cart', $referer) : home_url('/');

            wp_safe_redirect($target, 302);
            exit;
        }
    }, 1);

    add_action('wp_loaded', function () {
        if (is_admin()) {
            return;
        }

        remove_action('wp_ajax_nopriv_woocommerce_add_to_cart', [WC_AJAX::class, 'add_to_cart'], 10);
        remove_action('wp_ajax_woocommerce_add_to_cart', [WC_AJAX::class, 'add_to_cart'], 10);

        remove_action('wp_ajax_nopriv_woocommerce_get_refreshed_fragments', [WC_AJAX::class, 'get_refreshed_fragments'], 10);
        remove_action('wp_ajax_woocommerce_get_refreshed_fragments', [WC_AJAX::class, 'get_refreshed_fragments'], 10);

        remove_action('wp_ajax_nopriv_woocommerce_apply_coupon', [WC_AJAX::class, 'apply_coupon'], 10);
        remove_action('wp_ajax_woocommerce_apply_coupon', [WC_AJAX::class, 'apply_coupon'], 10);

        remove_action('wp_ajax_nopriv_woocommerce_update_order_review', [WC_AJAX::class, 'update_order_review'], 10);
        remove_action('wp_ajax_woocommerce_update_order_review', [WC_AJAX::class, 'update_order_review'], 10);

        add_filter('woocommerce_add_to_cart_redirect', function ($url) {
            $referer = wp_get_referer();
            return $referer ?: home_url('/');
        }, 99);

        add_filter('woocommerce_checkout_redirect_empty_cart', '__return_false');
    }, 20);

    add_action('init', function () {
        if (is_admin()) {
            return;
        }

        if (!empty($_GET['wc-ajax'])) {
            $endpoint = sanitize_text_field(wp_unslash($_GET['wc-ajax']));
            $blocked_endpoints = ['get_refreshed_fragments', 'apply_coupon', 'update_order_review'];

            if (in_array($endpoint, $blocked_endpoints, true)) {
                wp_send_json(['status' => 'disabled'], 403);
            }
        }
    }, 0);
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6: ADMIN DASHBOARD & MENU CLEANUP
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

        /**
         * Redirect removed WooCommerce admin pages to their classic equivalents.
         * Prevents 403 "Sorry, you are not allowed to access this page" when clicking
         * WooCommerce onboarding tasks, empty-state CTAs, or the WooCommerce Home route.
         *
         * Hooked to 'admin_page_access_denied' because WordPress validates menu capabilities
         * inside menu.php and triggers wp_die() before 'admin_init' is ever reached.
         * Also hooked to 'admin_init' as a fallback if the page is accessible.
         */
        $redirect_wc_admin = function () {
            if (!isset($_GET['page'])) {
                return;
            }

            $page = sanitize_key($_GET['page']);

            if ($page === 'wc-admin') {
                $task = isset($_GET['task']) ? sanitize_text_field(wp_unslash($_GET['task'])) : '';
                $path = isset($_GET['path']) ? sanitize_text_field(wp_unslash($_GET['path'])) : '';

                // Product onboarding task or React new product URL -> standard product editor
                if ($task === 'products' || strpos($path, 'add-product') !== false) {
                    wp_safe_redirect(admin_url('post-new.php?post_type=product'));
                    exit;
                }

                // General wc-admin (homescreen / setup) -> products catalog list
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
// SECTION 7: REGISTRATION & EMAIL SUPPRESSION
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
// SECTION 8: SCRIPT & STYLE DEQUEUE
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

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 9: SESSION, COOKIE & DATA TUNING (ZERO-SESSION LSCache COMPATIBILITY)
// ─────────────────────────────────────────────────────────────────────────────

if (swac_is_lockdown_enabled('session_tuning')) {
    add_filter('woocommerce_tracker_enabled', '__return_false');
    add_filter('woocommerce_order_attribution_enabled', '__return_false');
    add_filter('woocommerce_geolocation_local_database_path', '__return_empty_string');
    add_filter('woocommerce_customer_default_location_address', function () {
        return 'base';
    });

    add_filter('wp_sitemaps_enabled', '__return_false');
    add_filter('woocommerce_cancel_unpaid_interval', '__return_zero');

    add_filter('woocommerce_load_cart_on_every_request', '__return_false');
    add_filter('woocommerce_persistent_cart_enabled', '__return_false');

    add_action('woocommerce_loaded', 'swac_register_null_session_handler', 5);
    add_action('plugins_loaded', 'swac_register_null_session_handler', 20);

    if (!function_exists('swac_register_null_session_handler')) {
        function swac_register_null_session_handler(): void
        {
            if (class_exists('WC_Session') && !class_exists('SWAC_Null_Session_Handler')) {
                class SWAC_Null_Session_Handler extends WC_Session
                {
                    public function init()
                    {
                    }

                    public function cleanup_sessions()
                    {
                    }

                    public function get_session_data()
                    {
                        return [];
                    }

                    public function save_data()
                    {
                    }

                    public function destroy_session()
                    {
                    }

                    public function set_customer_session_cookie($set)
                    {
                    }

                    public function get_session_cookie()
                    {
                        return false;
                    }

                    public function has_session(): bool
                    {
                        return false;
                    }

                    public function maybe_set_customer_session_cookie(): void
                    {
                    }

                    public function forget_session(): void
                    {
                    }
                }
            }
        }
    }

    add_filter('woocommerce_session_handler', function ($handler_class) {
        if (!is_admin() && class_exists('SWAC_Null_Session_Handler')) {
            return 'SWAC_Null_Session_Handler';
        }
        return $handler_class;
    });

    add_filter('woocommerce_set_cart_cookies', function ($set) {
        if (!is_admin()) {
            return false;
        }
        return $set;
    }, 999);
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 10: BACKGROUND CRON CLEANUP GUARD
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Suppresses unnecessary WooCommerce background cron jobs on init.
 * Uses a persistent one-time option guard ('swac_cron_cleaned') to unschedule
 * recurring events from the database, preventing needless PHP wakeups.
 */
add_action('init', function () {
    if (get_option('swac_cron_cleaned')) {
        return;
    }

    $unneeded_crons = [
        'woocommerce_cleanup_sessions',
        'woocommerce_cleanup_personal_data',
        'woocommerce_cancel_unpaid_orders',
        'woocommerce_tracker_send_event',
    ];

    foreach ($unneeded_crons as $cron_hook) {
        $timestamp = wp_next_scheduled($cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $cron_hook);
        }
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook($cron_hook);
        }
    }

    update_option('swac_cron_cleaned', 1);
}, 20);

