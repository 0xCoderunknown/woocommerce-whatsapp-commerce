<?php
/**
 * 🔒 CORE: ZERO-SESSION & CACHE-FRIENDLY ENGINE
 *
 * Implements the non-negotiable architectural invariants:
 * 1. Stubs WC_Session with SWAC_Null_Session_Handler (zero session writes to wp_woocommerce_sessions).
 * 2. Suppresses WooCommerce guest cart cookies (enables 100% full-page static caching).
 * 3. Intercepts and blocks render-blocking cart fragments AJAX requests (403 status:disabled).
 * 4. 302-redirects legacy /cart/, /checkout/, and /my-account/ routes to home_url('/').
 * 5. Unschedules obsolete WooCommerce background cron jobs.
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

if (!function_exists('swac_is_lockdown_enabled')) {
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
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. FRONTEND ROUTE REDIRECTION (302 GATE)
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
// 2. ADD-TO-CART & WC-AJAX FRAGMENT INTERCEPT
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
// 3. ZERO-SESSION STUBBING (SWAC_Null_Session_Handler) & COOKIE SUPPRESSION
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
// 4. BACKGROUND CRON CLEANUP GUARD
// ─────────────────────────────────────────────────────────────────────────────

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

    update_option('swac_cron_cleaned', 1, false);
}, 20);
