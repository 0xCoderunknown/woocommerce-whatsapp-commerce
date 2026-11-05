<?php
/**
 * 🔒 INTEGRATIONS: SSL & MIXED-CONTENT SECURITY ADAPTER
 *
 * Bridge to Cloudflare, Edge CDNs, and Reverse Proxies.
 * Enforces canonical HTTPS redirects and Content-Security-Policy headers.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_ssl')) {
    /**
     * Determine if the current connection is secure (HTTPS),
     * including behind Cloudflare, load balancers, and reverse proxies.
     *
     * @return bool
     */
    function swac_is_ssl(): bool
    {
        if (is_ssl()) {
            return true;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }

        if (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower((string)$_SERVER['HTTP_FRONT_END_HTTPS']) === 'on') {
            return true;
        }

        if (isset($_SERVER['HTTP_CF_VISITOR'])) {
            $visitor = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
            if (is_array($visitor) && isset($visitor['scheme']) && $visitor['scheme'] === 'https') {
                return true;
            }
        }

        return false;
    }
}

/**
 * 1. Automatically 301 redirect HTTP frontend requests to HTTPS.
 */
add_action('template_redirect', function () {
    $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '';
    $clean_host = preg_replace('/:\d+$/', '', $host);
    $is_local = in_array($clean_host, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($clean_host, '.test')
        || str_ends_with($clean_host, '.local')
        || (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), ['local', 'development'], true));

    if ($is_local) {
        return;
    }

    $enforce = apply_filters('swac_enforce_ssl_redirects', true);
    if (!$enforce) {
        return;
    }

    if (swac_is_ssl()) {
        return;
    }

    if ((defined('WP_CLI') && WP_CLI) || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $target_url = 'https://' . $host . $request_uri;

    wp_safe_redirect($target_url, 301);
    exit;
}, 1);

/**
 * 2. Send Content-Security-Policy to auto-upgrade insecure assets.
 */
add_action('send_headers', function () {
    if (headers_sent()) {
        return;
    }

    $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '';
    $clean_host = preg_replace('/:\d+$/', '', $host);
    $is_local = in_array($clean_host, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($clean_host, '.test')
        || str_ends_with($clean_host, '.local')
        || (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), ['local', 'development'], true));

    if ($is_local) {
        return;
    }

    header('Content-Security-Policy: upgrade-insecure-requests;');
});
