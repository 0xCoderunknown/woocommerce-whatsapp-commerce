<?php
/**
 * ⚙️ DEVELOPER FEATURE FLAGS — Stateless WhatsApp Commerce
 * ─────────────────────────────────────────────────────────
 *
 * This is one of TWO files you need to know about:
 *
 *   features.php      ← you are here  (what the plugin DOES)
 *   core/config.php   ← store config  (phone number, name, shipping, etc.)
 *
 * Everything here defaults to the safest, least-invasive setting.
 * Read each block, decide what you want, flip true/false. Save. Done.
 *
 * ─────────────────────────────────────────────────────────
 * WHAT THIS PLUGIN GUARANTEES IT WILL NOT TOUCH BY DEFAULT:
 *   ✗ Your blog / posts
 *   ✗ The block editor for non-product post types
 *   ✗ Any WordPress feature not directly related to WooCommerce checkout
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ZONE 1 │ OPTIONAL WORDPRESS CLEANUPS
// ───────────────────────────────────────────────────────────────────────────────
// OFF by default. The plugin does NOT touch your blog, posts, or editor
// unless you explicitly enable something here.
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Disable the WordPress blog engine.
 *
 * When TRUE:
 *   - Singular posts (/?p=1, /post-slug/) redirect → homepage (301)
 *   - Blog index (/blog/, is_home()) redirects → homepage (301)
 *   - Author, date, and tag archives redirect → homepage (301)
 *   - The "Posts" menu is removed from wp-admin
 *
 * Posts are NOT deleted. They remain in the database and wp-admin.
 *
 * ✅ Enable if: pure commerce store, no editorial content whatsoever.
 * ❌ Leave OFF if: you have a blog, news section, or any 'post' content.
 */
define('SWAC_DISABLE_BLOG', false);

/**
 * Disable the block editor (Gutenberg) site-wide.
 *
 * The plugin already disables Gutenberg for 'product' post types so the
 * classic WooCommerce product editor stays intact. Enable this to also
 * disable it for pages, posts, and every other post type.
 *
 * ✅ Enable if: your entire workflow uses the classic editor everywhere.
 * ❌ Leave OFF if: you use blocks for pages, blog posts, or landing content.
 */
define('SWAC_DISABLE_BLOCK_EDITOR', false);

/**
 * Streamline product editor tabs in wp-admin.
 *
 * When TRUE, removes unused product data tabs (shipping, linked products,
 * attributes, variations, advanced) for simple physical-only catalogs.
 *
 * ✅ Enable if: pure simple-product catalog without variations or attributes.
 * ❌ Leave OFF (default) if: you sell variable products (sizes, colors) or use product attributes.
 */
define('SWAC_STREAMLINE_PRODUCT_TABS', false);

/**
 * Enable AJAX Select2 dropdowns for taxonomies in wp-admin product editor.
 *
 * Array of taxonomy slugs to enhance with Select2 AJAX dropdowns.
 * Defaults to ['product_brand'] so standard stores retain native multi-category
 * checkboxes for 'product_cat'.
 *
 * Add 'product_cat' only if your catalog strictly assigns a single category per product.
 */
define('SWAC_SELECT2_TAXONOMIES', ['product_brand']);


// ═══════════════════════════════════════════════════════════════════════════════
// ZONE 2 │ CATALOG LOCKDOWN FEATURE GROUPS
// ───────────────────────────────────────────────────────────────────────────────
// These are ON by default. Each one supports the WhatsApp-first commerce
// model. You can turn individual groups OFF if you need hybrid behaviour —
// for example, keeping product reviews while losing everything else.
//
// ┌─ LOCKED — not listed here, intentionally ────────────────────────────────┐
// │  Cart & checkout redirect     WhatsApp IS the checkout. Removing the     │
// │  WC AJAX intercept            redirect or re-enabling cart AJAX would    │
// │  Zero-session engine          break the plugin's core model entirely.    │
// │                               These are architectural, not preferences.  │
// └──────────────────────────────────────────────────────────────────────────┘
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Clean up <head> bloat.
 *
 * Removes from <head>: emoji scripts, RSD/WLW manifest links, WP generator tag,
 * feed auto-discovery links, shortlink tag, oEmbed discovery links, REST API
 * link header, adjacent post rel links, and pingback link.
 *
 * Also removes WooCommerce product gallery features (zoom, lightbox, slider)
 * which are unused when the catalog is served from static cache.
 *
 * ✅ Leave ON for: cleaner HTML output and faster page weight.
 * ❌ Turn OFF if: you rely on feed auto-discovery, oEmbed, or WC gallery scripts.
 */
define('SWAC_LOCKDOWN_HEAD_BLOAT', true);

/**
 * Security & REST API hardening.
 *
 * - Disables XML-RPC entirely.
 * - Blocks unauthenticated access to all /wc/ REST routes.
 * - Blocks public user enumeration via /wp/v2/users.
 *
 * ✅ Leave ON for: baseline security on a public-facing commerce store.
 * ❌ Turn OFF if: an external app or mobile client consumes WC REST as a guest.
 */
define('SWAC_LOCKDOWN_SECURITY', true);

/**
 * Disable frontend Heartbeat & close comments site-wide.
 *
 * - Heartbeat is removed from the frontend only. Admin Heartbeat stays active
 *   (post-locking and autosave continue to work in wp-admin).
 * - Comments are closed on all post types. The "Reviews" tab is hidden
 *   inside the WooCommerce product editor.
 *
 * ✅ Leave ON for: reduced server polling, no comment spam surface.
 * ❌ Turn OFF if: you want product reviews or blog comments to remain active.
 */
define('SWAC_LOCKDOWN_HEARTBEAT_COMMENTS', true);

/**
 * Admin dashboard & WooCommerce menu cleanup.
 *
 * Removes from wp-admin:
 *   - Comments menu page
 *   - WooCommerce Marketing & WC Admin homescreen / analytics / add-ons
 *   - WooCommerce upsell notices and Woo.com connection nag
 *   - Noisy WP dashboard widgets (Quick Press, Activity, Primary feed)
 *   - WooCommerce product reviews dashboard widget
 *   - Gutenberg for 'product' post type (classic editor stays)
 *   - Block widget editor (classic widgets stay)
 *
 * ✅ Leave ON for: a decluttered admin focused on products and orders.
 * ❌ Turn OFF if: you use WC Analytics, the marketing hub, or block widgets.
 */
define('SWAC_LOCKDOWN_ADMIN_CLEANUP', true);

/**
 * Suppress guest registration & customer-facing WooCommerce emails.
 *
 * - Public registration is blocked (guests cannot create accounts).
 * - All customer-facing WC transactional emails are disabled:
 *   new account, password reset, order processing, completed, refunded, invoice.
 * - Admin order notification emails are NOT affected.
 *
 * This makes sense for WhatsApp commerce: order confirmation happens in the
 * WhatsApp conversation, not via a WooCommerce email.
 *
 * ✅ Leave ON if: all orders flow through WhatsApp and email is not the channel.
 * ❌ Turn OFF if: you allow customer accounts or use WC for order tracking emails.
 */
define('SWAC_LOCKDOWN_REGISTRATION', true);

/**
 * Dequeue unused scripts & styles.
 *
 * Removes for all visitors:
 *   WP block library CSS, WC blocks CSS, global-styles, WC core layout CSS,
 *   WC gallery JS (flexslider, zoom, photoswipe), wp-embed.
 *
 * Additionally removes for guests (non-logged-in):
 *   cart-fragments, dashicons, select2/selectWoo, checkout scripts,
 *   password-strength meter, wc-order-attribution tracking, wc-add-to-cart.
 *
 * ✅ Leave ON for: significantly faster page loads.
 * ❌ Turn OFF if: your theme or a third-party plugin depends on any of these assets.
 */
define('SWAC_LOCKDOWN_SCRIPT_DEQUEUE', true);


// ═══════════════════════════════════════════════════════════════════════════════
// ZONE 3 │ MODULE ACTIVATION
// ───────────────────────────────────────────────────────────────────────────────
// Modules are optional subsystems. All are OFF by default.
// A module only loads if BOTH: the constant is true AND the folder exists
// under /modules/<name>/. Missing folders are silently ignored.
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Pharmacy module  →  /modules/pharmacy/
 *
 * Adds: prescription taxonomies, pack metrics (strip/bottle/ml counts),
 * generic-name upsell sidebar, salt/ingredient explorer, pharmacy product
 * meta fields, and dosage size charts.
 */
define('SWAC_MODULE_PHARMACY', false);

/**
 * Directory / Explore module  →  /modules/explore/
 *
 * Adds: brand directory and category directory pages with live search,
 * designed for large catalogs where customers browse by brand or category.
 */
define('SWAC_MODULE_EXPLORE', false);

/**
 * Native SEO module  →  /modules/seo/
 *
 * Adds: homepage schema markup, per-product SEO meta (title, description,
 * canonical), and taxonomy-level SEO. Works standalone or alongside an
 * SEO plugin (does not conflict with Rank Math or Yoast).
 */
define('SWAC_MODULE_SEO', false);

/**
 * Wholesale / B2B Schemes module  →  /modules/wholesale/
 *
 * Adds: Wholesale PTR pricing, MRP-to-PTR retailer margin badges,
 * active scheme tags (free goods, volume tier slabs, cash discount),
 * carton packing guide labels, and direct shop-page bulk ordering.
 */
define('SWAC_MODULE_WHOLESALE', true);

/**
 * Catalog Operating Mode.
 *
 * Defines the storefront persona:
 *
 *   - 'walled_garden' : (Wholesalers / Distributors — Shikhar style)
 *                       100% private B2B portal. The entire catalog, products, and prices
 *                       are walled behind Phone + 4-digit PIN. Unauthenticated visitors cannot
 *                       view products or prices. Automatically shields the site from search
 *                       engines (noindex, nofollow, robots.txt disallow). Enables 1-tap
 *                       route-based WhatsApp ordering for retailers.
 *
 *   - 'hybrid'        : (B2B + B2C Catalog)
 *                       Public catalog browsing for guests; rates and bulk ordering
 *                       locked behind 4-digit PIN for approved retailers.
 *
 *   - 'retail'        : (Standard B2C Store like GuwahatiOne)
 *                       Public retail consumer storefront. Standard pricing, MRP strike-throughs,
 *                       residential delivery address, and same-day cutoff timers.
 */
define('SWAC_CATALOG_MODE', 'walled_garden');

/**
 * Private Catalog / Stealth Mode (Search Engine Invisibility).
 *
 * When TRUE (or automatically when SWAC_CATALOG_MODE is 'walled_garden'):
 *   - Search engines (Google, Bing) are blocked via X-Robots-Tag: noindex, nofollow
 *   - Meta robots tag noindex is printed in <head>
 *   - Disallow: / is added to robots.txt
 *   - XML Sitemap (/sitemap.xml) is disabled
 */
define('SWAC_PRIVATE_CATALOG', true);

if (!function_exists('swac_get_catalog_mode')) {
    /**
     * Retrieves the resolved catalog operating mode.
     *
     * @return string 'walled_garden'|'hybrid'|'retail'
     */
    function swac_get_catalog_mode(): string
    {
        if (defined('SWAC_CATALOG_MODE')) {
            return (string)SWAC_CATALOG_MODE;
        }
        return swac_is_module_active('wholesale') ? 'walled_garden' : 'retail';
    }
}

if (!function_exists('swac_is_private_catalog')) {
    /**
     * Checks if search engine indexing is disallowed for this catalog.
     *
     * @return bool
     */
    function swac_is_private_catalog(): bool
    {
        if (defined('SWAC_PRIVATE_CATALOG') && SWAC_PRIVATE_CATALOG) {
            return true;
        }
        return swac_get_catalog_mode() === 'walled_garden';
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
// WIRING — translates constants → filters (do not edit below this line)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Feed Zone 2 constants into the lockdown feature system.
 * Priority 5 ensures this runs before the static cache inside
 * swac_is_lockdown_enabled() seals the feature map on first call.
 */
add_filter('swac_lockdown_features', function (array $features): array {
    $features['head_bloat'] = SWAC_LOCKDOWN_HEAD_BLOAT;
    $features['security_hardening'] = SWAC_LOCKDOWN_SECURITY;
    $features['heartbeat_comments'] = SWAC_LOCKDOWN_HEARTBEAT_COMMENTS;
    $features['admin_cleanup'] = SWAC_LOCKDOWN_ADMIN_CLEANUP;
    $features['registration_suppress'] = SWAC_LOCKDOWN_REGISTRATION;
    $features['script_dequeue'] = SWAC_LOCKDOWN_SCRIPT_DEQUEUE;
    return $features;
}, 5);

/**
 * Feed Zone 3 constants into the module activation system.
 * Uses the same filter already consumed by swac_is_module_active().
 */
add_filter('swac_active_modules', function (array $modules): array {
    if (SWAC_MODULE_PHARMACY && !in_array('pharmacy', $modules, true)) {
        $modules[] = 'pharmacy';
    }
    if (SWAC_MODULE_EXPLORE && !in_array('explore', $modules, true)) {
        $modules[] = 'explore';
    }
    if (SWAC_MODULE_SEO && !in_array('seo', $modules, true)) {
        $modules[] = 'seo';
    }
    if (SWAC_MODULE_WHOLESALE && !in_array('wholesale', $modules, true)) {
        $modules[] = 'wholesale';
    }
    return $modules;
}, 5);

/**
 * SWAC_DISABLE_BLOG wiring.
 * Hooks into template_redirect to 301-redirect blog-related URLs.
 * Removes Posts from the admin menu (non-destructive — no data is deleted).
 */
if (SWAC_DISABLE_BLOG) {
    add_action('template_redirect', function () {
        if (
            (is_single() && get_post_type() === 'post') ||
            is_author() ||
            is_date() ||
            is_tag() ||
            is_home()
        ) {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }
    }, 1);

    add_action('admin_menu', function () {
        remove_menu_page('edit.php'); // Posts
    });
}

/**
 * SWAC_DISABLE_BLOCK_EDITOR wiring.
 * Extends the product-only Gutenberg disable (in catalog-lockdown.php)
 * to cover all post types. Priority 200 overrides theme/plugin filters.
 */
if (SWAC_DISABLE_BLOCK_EDITOR) {
    add_filter('use_block_editor_for_post', '__return_false', 200);
    add_filter('use_block_editor_for_post_type', '__return_false', 200);
}
