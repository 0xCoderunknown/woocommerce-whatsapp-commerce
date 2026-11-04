# Stateless WhatsApp Commerce for WooCommerce

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![WooCommerce: 8.0 - 11.1](https://img.shields.io/badge/WooCommerce-8.0%20--%2011.1-purple.svg)](https://woocommerce.com/)
[![HPOS: Declared Compatible](https://img.shields.io/badge/HPOS-Declared%20Compatible-success.svg)](https://woocommerce.com/)
[![Full-Page Cache: Static Ready](https://img.shields.io/badge/Full--Page%20Cache-Static%20Ready-brightgreen.svg)](https://www.litespeedtech.com/)

> [!WARNING]
> **Important: Store Behavior Notice Upon Activation**
>
> Activating Stateless WhatsApp Commerce transforms your WooCommerce store from a traditional payment gateway store into
> a **direct WhatsApp order-intent catalog**:
> - **Page Redirections**: Cart, Checkout, and My Account pages are intercepted and redirected (default: redirects to
    homepage).
> - **Sessions & Fragments Suppressed**: Native WooCommerce frontend session creation (`wp_woocommerce_sessions`) and
    render-blocking cart fragments AJAX (`wc-ajax=get_refreshed_fragments`) are disabled site-wide for all visitors.
> - **Native Emails & Gateways Bypassed**: Standard WooCommerce customer transaction emails and gateway checkouts are
    not triggered because orders are initiated directly via customer WhatsApp messages.
>
> **Need to adjust or disable these behaviours?**
> Open `features.php` (in the plugin root) — every lockdown feature group has its own on/off toggle with a full
> explanation. For a complete lockdown disable, set `swac_enable_catalog_lockdown` to false via filter:
> ```php
> add_filter('swac_enable_catalog_lockdown', '__return_false');
> ```

---

## ⚡ Overview

**Stateless WhatsApp Commerce** enables WooCommerce stores to run as **session-free public catalogs** that can be served
directly from full-page static cache (e.g. LiteSpeed Cache, Nginx fastcgi_cache, or Edge CDNs) without PHP or database
execution overhead on cache hits.

Customer cart items, address details, and delivery zone serviceability run in the browser using vanilla JavaScript
and `localStorage`. When the user is ready, the cart compiles into an itemized, structured **WhatsApp message link**
(`wa.me`) where the customer reviews and transmits the order directly to your dispatch team.

---

## 📊 Operational Comparison

| Metric / Behavior                        | Standard WooCommerce                                                | Stateless WhatsApp Commerce                                            |
|:-----------------------------------------|:--------------------------------------------------------------------|:-----------------------------------------------------------------------|
| **Full-Page Cache Hits**                 | Frequently bypassed by session cookies & cart fragments             | **Served from full-page cache without PHP or database overhead**       |
| **Database Session Writes**              | 1 write per browsing session (`wp_woocommerce_sessions`)            | **0 Database Writes** (handled entirely client-side)                   |
| **Frontend Cookies**                     | Plugin sets `wp_woocommerce_session_*`, `woocommerce_items_in_cart` | **Plugin sets zero frontend commerce cookies** (static cache friendly) |
| **Cart Fragments AJAX**                  | Dispatches `get_refreshed_fragments` on page loads                  | **Completely disabled** (no background server requests)                |
| **Checkout Workflow**                    | Multi-step form with cart abandonment                               | **Direct WhatsApp Order** with itemized pricing & address              |
| **Edge Cacheability (Cloudflare / CDN)** | Requires complex cookie-bypass exclusion rules                      | **Edge-cache friendly** (depends on your edge cache rules)             |

> *Note on cookies & cache: The plugin itself sets zero frontend commerce cookies. Overall site cookie behavior and
Cloudflare edge caching also depend on your active theme, third-party plugins, and CDN page rule configurations.*

---

## 🏗️ Architecture Overview

```text
               Public Visitors / Crawlers
                           │
                           ▼
          ┌───────────────────────────────────┐
          │  LiteSpeed Web Server / Edge CDN  │ ──► [ Static Full-Page Cache Hit ]
          └───────────────────────────────────┘
                           │
                           │ (Cache Miss / Purge Only)
                           ▼
          ┌───────────────────────────────────┐
          │    Catalog Lockdown & Security    │
          │  - SWAC_Null_Session_Handler      │
          │  - Cart Fragments Intercept       │
          │  - Guest Checkout Diverter        │
          │  - HPOS Compatibility Declared    │
          └───────────────────────────────────┘
                           │
                           ▼
               Browser / Client-Side DOM
          ┌───────────────────────────────────┐
          │  assets/js/wa_cart.js (Engine)    │
          │  - LocalStorage Cart CRUD         │
          │  - Client-Side Address Capture    │
          │  - Dynamic Postal Zone Gate       │
          │  - Live DOM Price Reconciliation  │
          └───────────────────────────────────┘
                           │
                           ▼
             Serialized WhatsApp Order URL
          [ wa.me/number?text=Itemized+Order ]
```

---

## 🚀 Key Features

- **Zero-Session Lockdown**:
  Stubs the WooCommerce session engine with `SWAC_Null_Session_Handler`. Eliminates session cookies, database writes,
  and cart fragment overhead. Fully toggleable via `swac_enable_catalog_lockdown`.
- **Client-Side Cart & Slide-Out Drawer**:
  Lightweight, zero-dependency slide-out order drawer with real-time totals, dynamic shipping threshold alerts, and
  postal zone gating.
- **Dynamic Price Reconciliation**:
  Reconciles `localStorage` cart item prices against fresh DOM attributes on cached pages, preventing stale cache
  checkouts.
- **Filterable Zone Registry**:
  Supports open checkout or gated delivery zones via `apply_filters('swac_delivery_zones', ...)`.
- **Timezone-Aware Delivery Cutoffs**:
  Calculates local order cutoffs using WordPress core `wp_timezone()`, complete with dynamic calendar date
  stamping `(j M)` for weekend and weekday afternoon windows.
- **High-Performance Submodules**:
    - `modules/pharmacy/`: Chemical compositions (`composition`), pack sizes (`pack_size`), and generic alternate price
      comparisons.
    - `modules/explore/`: High-speed directory exploration for brands and categories with instant live client search.
    - `modules/seo/`: Dynamic OpenGraph headers, taxonomy titles, and Schema.org JSON-LD metadata without external SEO
      plugins.
    - `modules/wholesale/`: B2B Wholesale & schemes engine with PTR pricing, dynamic retailer margin badges (₹ & %),
      active promotional schemes (12+1 free goods, volume slab tiers, CD %), carton packing guides, direct shop-page
      bulk ordering, zero-cost phone + 4-digit PIN rate gating, and mobile frontend distributor desk with 1-click CSV
      synchronization.
    - `integrations/`: Streaming low-memory XML sitemap generator (`/sitemap.xml`) and reverse-proxy SSL enforcer.
- **HPOS & Modern Block Ready**:
  Compatibility for High-Performance Order Storage (`custom_order_tables`) and Cart/Checkout Blocks declared
  via `FeaturesUtil::declare_compatibility()` on `before_woocommerce_init`.

---

## 🎨 Theme & Builder Compatibility

Stateless WhatsApp Commerce is built as a **true drop-in solution** designed to work out of the box with almost any
WordPress theme or visual page builder:

- **100% Theme Agnostic**: Leaves all site layout, typography, branding, headers, footers, and product grid designs
  completely untouched. The plugin does not override your theme's archive or single-product templates.
- **Tested with Visual Page Builders**: Works seamlessly with **Breakdance Builder**, **Elementor**, **Bricks**, **Divi**,
  and modern **WordPress Block Themes** (Full Site Editing).
- **Automatic "Add to Cart" Transformation**:
    - **Catalog & Archive Loops**: Automatically hooks into `woocommerce_loop_add_to_cart_link` to transform standard
      server-side buttons into client-side WhatsApp order actions.
    - **Single Product Pages**: Automatically intercepts the native WooCommerce `form.cart`
      submission (`.single_add_to_cart_button`) directly in the browser via JavaScript (`preventDefault`). Clicking "Add
      to Cart" immediately adds the product to `localStorage` without triggering page reloads, AJAX delays, or session
      cookies.
    - **Custom Builder Templates**: When designing custom single-product layouts in Breakdance or Elementor, you can
      either keep the builder's standard Add to Cart widget (which is automatically intercepted) or drop in the
      dedicated `[swac_action_row]` shortcode for integrated quantity steppers and WhatsApp ordering.
- **Zero-Dependency Slide-Out Drawer**:
    - Replaces traditional multi-step `/cart/` and `/checkout/` redirection loops with a modern, lightweight,
      client-side slide-out cart drawer.
    - Features real-time totals, dynamic shipping threshold progress, item quantity selectors, and postal zone
      serviceability checks, compiling everything directly into the final `wa.me` order link.

---

## ⚙️ Configuration

Two files. That's it. Open each, fill in your values, save.

```
stateless-wa-commerce/
  core/config.php   ← store settings  (phone, name, shipping, limits)
  features.php      ← feature toggles (modules, lockdown groups, cleanups)
```

### 1. `core/config.php` — Store Settings

Every value is a plain PHP constant. No functions, no filters, no WordPress knowledge required:

```php
// Required
define('SWAC_PHONE',            '919876543210'); // E.164 without '+'
define('SWAC_STORE_NAME',       'My Store');

// Optional
define('SWAC_CITY_NAME',        'Mumbai');
define('SWAC_FREE_SHIPPING_AT', 500.0);  // Free shipping above this value
define('SWAC_SHIPPING_CHARGE',  49.0);   // Flat charge below threshold
define('SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES', ['781001', '781005']); // Always-free postal codes
define('SWAC_MAX_CART_ITEMS',   10);     // Protects WhatsApp URL length
define('SWAC_MAX_QTY_PER_ITEM', 10);     // Per-item quantity ceiling
define('SWAC_ORDER_DISCLAIMER', '⚡ Final bill & product availability will be confirmed on WhatsApp.');

// Loyalty reward (optional cart incentive)
define('SWAC_LOYALTY_ENABLED',    true);
define('SWAC_LOYALTY_THRESHOLD',  500);
define('SWAC_LOYALTY_ITEM_LABEL', 'Free Lip Balm');
```

### 2. `features.php` — Feature Toggles

```php
// ZONE 1: Optional WordPress cleanups (OFF by default)
define('SWAC_DISABLE_BLOG',         false); // Redirect posts/blog to homepage
define('SWAC_DISABLE_BLOCK_EDITOR', false); // Gutenberg off site-wide

// ZONE 2: Lockdown feature groups (ON by default, individually tunable)
define('SWAC_LOCKDOWN_HEAD_BLOAT',         true);
define('SWAC_LOCKDOWN_SECURITY',           true);
define('SWAC_LOCKDOWN_HEARTBEAT_COMMENTS', true); // Turn OFF to keep product reviews
define('SWAC_LOCKDOWN_ADMIN_CLEANUP',      true);
define('SWAC_LOCKDOWN_REGISTRATION',       true);
define('SWAC_LOCKDOWN_SCRIPT_DEQUEUE',     true);

// ZONE 3: Modules (OFF by default)
define('SWAC_MODULE_PHARMACY', false);
define('SWAC_MODULE_EXPLORE',  false);
define('SWAC_MODULE_SEO',       false);
define('SWAC_MODULE_WHOLESALE', true);  // B2B wholesale pricing, schemes & PIN login
```

> [!NOTE]
> Cart & checkout redirect, WC AJAX intercept, and the zero-session engine are **not** listed in `features.php` — they
> are architectural non-negotiables. Disabling them would break the WhatsApp checkout model entirely.

---

### 3. Delivery Zones (`swac_delivery_zones`)

Delivery zone serviceability cannot be defined via constants (it's runtime, potentially database-driven). Configure via
filter:

```php
add_filter('swac_delivery_zones', function ($config) {
    $config['gated'] = true; // Enforce postal code gate
    $config['zones']['express'] = [
        'label'        => 'Express Same-Day',
        'badge_text'   => '✓ Express Same-Day Delivery Available',
        'status_class' => 'swac-pin-success',
        'allow_order'  => true,
        'pincodes'     => [
            '400001' => 'Mumbai Fort',
            '400051' => 'Bandra',
        ],
    ];
    return $config;
});
```

### 4. Advanced: Programmatic Config Overrides

For dynamic config (multi-site, environment-specific, or database-driven values), the `swac_commerce_config` filter
overrides any constant:

```php
add_filter('swac_commerce_config', function ($config) {
    $config['phone_number']     = get_option('my_store_phone');
    $config['free_shipping_at'] = (float) get_option('my_shipping_threshold');
    return $config;
});
```

---

## 📚 Documentation Matrix

This repository maintains a strict **single source of truth** architecture across all documentation files:

| Document | Primary Audience | Authoritative Scope / Single Source of Truth |
|:---|:---|:---|
| **[README.md](README.md)** | Store Owners & Admins | Project overview, key value proposition, quick-start configuration, theme compatibility, and test launcher. |
| **[docs/shortcodes.md](docs/shortcodes.md)** | Page Builders & Devs | **Definitive Shortcode Reference**: Exhaustive attribute lists, default parameters, visual examples, and aliases. |
| **[docs/architecture.md](docs/architecture.md)** | Software Architects | **Definitive Architecture Deep Dive**: Zero-session caching, DOM state machine, delivery engine, and B2B wholesale gating. |
| **[AGENTS.md](AGENTS.md)** | AI Agents & Maintainers | **Maintainer & AI Operating Manual**: Non-negotiable code invariants, state schemas, subsystem ownership, and REST API matrix. |
| **[CONTRIBUTING.md](CONTRIBUTING.md)** | Open Source Contributors | Community contribution guidelines, coding standards, branch workflow, and automated test requirements. |
| **[SECURITY.md](SECURITY.md)** | Security Researchers | Supported versions matrix, vulnerability disclosure process, and plugin security model. |
| **[CHANGELOG.md](CHANGELOG.md)** | Release Auditors | Chronological release history following the Keep a Changelog standard and Indian standard date format (`DD-MM-YYYY`). |

---

## 📋 Shortcode System

Stateless WhatsApp Commerce provides a modular suite of shortcodes designed for visual page builders (Breakdance, Elementor, Bricks) and theme templates. All shortcodes use the unique `[swac_*]` prefix:

- **Storefront & Ordering**: `[swac_button]`, `[swac_action_row]`
- **Pricing & Packaging**: `[swac_price_block]`, `[swac_pack_size]`, `[swac_price_pack_hierarchy]`
- **Product Meta & Delivery**: `[swac_delivery_notice]`, `[swac_stock_status]`
- **Catalogs & Directories**: `[swac_explore_brands]`, `[swac_categories_grid]`, `[swac_directory_search]`
- **B2B Wholesale Portal**: `[swac_distributor_portal]`
- **Pharmacy & Clinical**: `[swac_generic_box]`, `[swac_composition_tag]`, `[swac_rx_badge]`, `[swac_size_chart]`, `[swac_explore_salts]`
- **Catalog Health**: `[swac_missing_images_finder]`

> 📖 **Definitive Shortcode Reference**:  
> For complete attribute specifications, defaults, visual examples, and legacy aliases, consult the single source of truth:  
> 👉 **[docs/shortcodes.md](docs/shortcodes.md)**

---

## 🛠️ Requirements & Compatibility

- **WordPress**: 6.2+
- **PHP**: 8.1+ (8.2+ recommended)
- **WooCommerce**: 8.0 through 11.1 (Tested up to 11.1)
- **HPOS**: Fully compatible (Custom Order Tables declared)
- **Cart/Checkout Blocks**: Declared compatible
- **Themes & Page Builders**: 100% theme-agnostic; tested with Breakdance Builder, Elementor, Bricks, and classic/block
  themes
- **Web Server / Cache**: LiteSpeed Web Server, Nginx, or any reverse-proxy / full-page caching layer

---

## 🧪 Testing & Verification

The repository includes a 0-dependency automated testing suite powered by Node.js native test runner and static PHP
verification:

```bash
# Run unit tests and PHP invariant/domain verification
npm test

# Windows 1-Click Launcher (no Node on PATH required)
run-tests.cmd
```

---

## 🤝 Contributing

Contributions are welcome! Please review [CONTRIBUTING.md](CONTRIBUTING.md) for coding standards, pull request processes, and invariant verification steps.

---

## 🔒 Security

For security vulnerabilities and disclosure policies, please refer to [SECURITY.md](SECURITY.md).

---

## ⚖️ Disclaimer

This plugin is an independent open-source project and is not affiliated with, endorsed by, or sponsored by WhatsApp,
Meta, or WooCommerce. WhatsApp is a registered trademark of Meta Platforms, Inc. WooCommerce is a registered trademark
of Automattic Inc.

---

## 📄 License

Stateless WhatsApp Commerce for WooCommerce is licensed under the [GNU General Public License v2.0 or later](LICENSE).
