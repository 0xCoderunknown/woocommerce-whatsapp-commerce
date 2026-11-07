# Stateless WhatsApp Commerce for WooCommerce

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![WooCommerce: 8.0 - 11.2](https://img.shields.io/badge/WooCommerce-8.0%20--%2011.2-purple.svg)](https://woocommerce.com/)
[![PHP: 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![HPOS: Declared Compatible](https://img.shields.io/badge/HPOS-Declared%20Compatible-success.svg)](https://woocommerce.com/)
[![Full-Page Cache: Static Ready](https://img.shields.io/badge/Full--Page%20Cache-Static%20Ready-brightgreen.svg)](https://www.litespeedtech.com/)
[![Zero Server Sessions: Enforced](https://img.shields.io/badge/Sessions-0%20Server%20State-orange.svg)](#-zero-session-architecture)
[![Zero Frontend Cookies: Enforced](https://img.shields.io/badge/Cookies-0%20Frontend%20Cookies-orange.svg)](#-zero-session-architecture)

> [!WARNING]
> **Important: Store Behavior Notice Upon Activation**
>
> Activating Stateless WhatsApp Commerce transforms your WooCommerce store from a traditional server-side checkout store into a **zero-session, high-performance static catalog coupled with a client-side WhatsApp commerce engine**:
> - **Page Redirections**: Standard `/cart/`, `/checkout/`, and `/my-account/` routes are intercepted and 302-redirected to the homepage.
> - **Sessions & Fragments Suppressed**: Native WooCommerce session creation (`wp_woocommerce_sessions`) and render-blocking cart fragments AJAX (`wc-ajax=get_refreshed_fragments`) are completely disabled site-wide.
> - **Native Emails & Gateways Bypassed**: Standard WooCommerce payment gateways and customer transactional emails are not invoked because orders are transmitted directly as structured WhatsApp messages.
>
> **Need to adjust or disable these behaviours?**  
> Open `features.php` in the plugin root — every lockdown feature group has its own on/off toggle. To disable the catalog lockdown completely, hook the filter:
> ```php
> add_filter('swac_enable_catalog_lockdown', '__return_false');
> ```

---

## ⚡ Why Stateless WhatsApp Commerce?

High-traffic WooCommerce stores frequently suffer from server congestion, sluggish page loads, and cache invalidation caused by WooCommerce's default stateful architecture:
1. Every guest visit creates a row in `wp_woocommerce_sessions`.
2. Every page load triggers an AJAX cart fragment request (`?wc-ajax=get_refreshed_fragments`).
3. Set-Cookie headers prevent full-page static caching (LiteSpeed Cache, Nginx `fastcgi_cache`, Cloudflare, and Edge CDNs).

**Stateless WhatsApp Commerce solves this fundamentally** by moving cart state, totals calculations, customer address drafts, and delivery zoning entirely into the visitor's browser (`localStorage`). Public catalog pages are rendered as 100% static HTML that can be served directly from memory or CDN edge nodes with **zero PHP or database execution** on cache hits.

When ready to order, the client compiles an itemized, structured, URL-safe WhatsApp message and launches the chat (`wa.me`) directly with your sales or dispatch team.

---

## 📊 Operational Comparison

| Metric / Behavior | Standard WooCommerce | Stateless WhatsApp Commerce |
|:---|:---|:---|
| **Full-Page Cache Hits** | Frequently bypassed by cookies & cart fragments | **100% Static HTML Cache Hit** (zero PHP execution) |
| **Database Session Writes** | 1 write per session in `wp_woocommerce_sessions` | **0 Database Writes** (state lives in `localStorage`) |
| **Frontend Commerce Cookies** | Sets `wp_woocommerce_session_*`, `woocommerce_items_in_cart` | **0 Frontend Cookies** issued to guests |
| **Cart Fragments AJAX** | Fires `?wc-ajax=get_refreshed_fragments` on every page load | **Completely Eliminated** (no background server requests) |
| **Checkout Workflow** | Multi-step form with cart abandonment | **Instant WhatsApp Order** with itemized pricing & address |
| **Edge Cacheability (CDN)** | Requires complex cookie-bypass exclusion rules | **Edge-Cache Native** (Cloudflare / LiteSpeed / Fastly) |
| **Server Resource Overhead** | High CPU & MySQL spikes during traffic bursts | **Minimal Server Load** (serves static assets like HTML/CSS) |

---

## 🏗️ Architectural Overview

```text
               Public Visitors / Retailers / Search Crawlers
                                    │
                                    ▼
                 ┌─────────────────────────────────────┐
                 │  LiteSpeed / Nginx / Cloudflare CDN │ ──► [ Static Cache Hit (0ms PHP) ]
                 └─────────────────────────────────────┘
                                    │
                                    │ (Cache Miss / Purge Only)
                                    ▼
                 ┌─────────────────────────────────────┐
                 │   Zero-Session Catalog Lockdown     │
                 │   - SWAC_Null_Session_Handler       │
                 │   - Cart Fragments Intercept        │
                 │   - /cart/ & /checkout/ 302 Bypass  │
                 │   - HPOS Compatibility Declared     │
                 └─────────────────────────────────────┘
                                    │
                                    ▼
                 ┌─────────────────────────────────────┐
                 │     Browser / Client State Engine   │
                 │  - delivery-engine.js (Zones & IST) │
                 │  - cart-math.js (Totals & MRP)      │
                 │  - drawer-ui.js (Slide-Out UI)      │
                 │  - wa_cart.js (State Machine)       │
                 │  - order-serializer.js (wa.me URL)  │
                 │  - retailer-auth.js (4-Digit PIN)   │
                 └─────────────────────────────────────┘
                                    │
                                    ▼
                      Serialized WhatsApp Order URL
                   [ wa.me/number?text=Itemized+Order ]
```

---

## 🎭 3 Storefront Operating Personas

Stateless WhatsApp Commerce supports three distinct operational modes configured via `SWAC_CATALOG_MODE` in `features.php`:

### 1. `walled_garden` — Private B2B Wholesale Portal (Shikhar Style)
*Designed for stockists, distributors, and FMCG wholesalers.*
- **100% Private Catalog**: The entire product catalog, prices, search, and cart drawer are shielded behind a mobile phone + 4-digit PIN authentication gate.
- **Zero Layout Flash (FOUC)**: A lightweight, synchronous `<head>` script inspects `localStorage['swac_b2b_retailer']` and pre-applies `.swac-retailer-auth` before first paint.
- **Search Engine Stealth Shield**: Automatically disallows search engine indexing via `X-Robots-Tag: noindex, nofollow`, `<meta name="robots">`, dynamic `robots.txt` disallow rules, and 404 responses on `/sitemap.xml`.
- **Store Owner & Admin Bypass**: Logged-in WordPress Administrators and Store Managers (`manage_woocommerce`) and holders of active Master Tokens automatically bypass the gate.
- **WhatsApp 1-Tap Auto-Login**: Wholesalers can share direct WhatsApp login links (`?b2b_login=PHONE&pin=PIN`) that automatically verify and authenticate the retailer on link click.
- **B2B Route-Based Dispatch**: Replaces consumer address inputs with verified store name, mobile number, delivery route / area (e.g. *Central Market Route*, *Transport Bilty*), and optional dispatch remarks.

### 2. `hybrid` — Dual-Audience Catalog (B2B + B2C)
*Designed for businesses serving both retail consumers and wholesale trade accounts.*
- Public visitors browse standard consumer products and retail pricing freely.
- Wholesale rates (PTR), retailer margins (₹ & %), carton packing guides, and trade volume schemes are masked behind a discrete locked view.
- Retailers enter their mobile + 4-digit PIN in a modal to reveal wholesale pricing across the entire catalog without page reloads.

### 3. `retail` — High-Speed Consumer Storefront
*Designed for direct-to-consumer e-commerce, pharmacies, and local businesses.*
- Displays standard retail pricing with strike-through MRP savings badges.
- Features residential delivery address validation, timezone-aware delivery cutoff badges (e.g. same-day delivery countdown), and optional loyalty cart rewards.

---

## 🏢 Mobile Frontend Distributor Desk (`[swac_distributor_portal]`)

Stateless WhatsApp Commerce includes a standalone, mobile-responsive **Distributor Desk** that allows store owners and warehouse staff to operate their wholesale catalog directly from a smartphone or tablet without accessing `/wp-admin/`:

### Dual-Role Access Control (Owner vs. Staff)
| Feature / Action | Owner Role (`swac_b2b_master_pin`) | Staff Role (`swac_b2b_staff_pin`) |
|:---|:---:|:---:|
| **Catalog Search & Filter** | ✅ Yes | ✅ Yes |
| **1-Tap Stock Toggle (`[ 🟢 IN STOCK ]` / `[ 🔴 OUT OF STOCK ]`)** | ✅ Yes | ✅ Yes |
| **Fast Row Update (<100ms)** | ✅ Yes (All Fields) | ✅ Yes (Stock Toggle Only) |
| **Edit Rates (PTR), Retail MRP, MOQ** | ✅ Yes | ❌ Read-Only / Disabled |
| **Edit Promotional Schemes** | ✅ Yes | ❌ Read-Only / Disabled |
| **Create Product (with Phone Camera Upload)** | ✅ Yes | ❌ Hidden |
| **Approved Retailer Directory Management** | ✅ Yes | ❌ Hidden |
| **Store Settings (WhatsApp, Shipping, Disclaimer)** | ✅ Yes | ❌ Hidden |
| **CSV Export & Bulk CSV Import** | ✅ Yes | ❌ Hidden |

### Highlights:
- **Counter-Speed Quick-Update Grid**: Tab-through inputs for Rate (PTR), Retail MRP, Promotional Scheme, and MOQ with auto-save on blur (`Saving...` → `Saved ✓`).
- **Shorthand Promotional Scheme Parser**: Type shorthand schemes directly into the grid:
  - `"10+1"` or `"12+1 FREE"` → Free goods scheme.
  - `"10:2%"` or `"25:5%"` → Volume slab tier discounts.
  - `"2% CD"` or `"3%"` → Cash discount scheme.
- **Fast REST Endpoints**: Targeted endpoints (`/wp-json/swac/v1/desk/fast-row-update`) update product meta in under 100ms without full `WC_Product::save()` lifecycle overhead.
- **Brute-Force Rate Limiting**: IP-based attempt throttling protects Master and Staff PIN endpoints against automated guessing.
- **Excel-Ready CSV Sync**: CSV export streams with a UTF-8 Byte Order Mark (`\xEF\xBB\xBF`) for seamless opening in Microsoft Excel.

---

## 🚀 Key Subsystems & Features

### 1. Zero-Session Engine & Full-Page Cache Friendliness
- Stubs `WC_Session` with `SWAC_Null_Session_Handler`, making `wp_woocommerce_sessions` completely inert.
- Dequeues `cart-fragments.js` and unregisters WooCommerce session cron jobs.
- Issues zero commerce cookies to catalog visitors.
- Emits `Cache-Control: public, max-age=...` headers compatible with LiteSpeed, Nginx, Varnish, and Cloudflare.

### 2. Modular Client State Machine (0 Dependencies)
- **`delivery-engine.js`**: Calculates delivery cutoff windows in the local timezone (e.g. Asia/Kolkata IST) and evaluates postal codes against open or gated delivery zones.
- **`cart-math.js`**: Pure mathematical calculations for subtotal, MRP, savings amount, savings percentage, loyalty item additions, and flat shipping charges.
- **`order-serializer.js`**: Compiles in-memory items, carton breakdowns, scheme tags, delivery notes, and sanitized address fields into an itemized, URL-safe plain-text WhatsApp order string.
- **`drawer-ui.js`**: Controls the slide-out drawer markup, live quantity steppers, postal code gating indicators, and loyalty item banners.
- **`retailer-auth.js`**: Handles client-side PIN verification modal, status pill display (`👑 Distributor Admin` / `🏪 Retailer: Store Name`), and Walled Garden gate interactions.
- **`wa_cart.js`**: Orchestrates `localStorage['swac_cart']` CRUD, DOM price reconciliation on drawer open, and WhatsApp direct launch.

### 3. Delivery Zones & Cutoff Intelligence
- **Open Mode**: Accepts any valid postal code format.
- **Gated Mode**: Validates the customer's postal code against registered service tiers (`same_day`, `outskirts`).
- **Always-Free Postal Codes**: Whitelist specific postal codes (`SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES`) that automatically receive free delivery regardless of the cart subtotal.
- **Timezone-Aware Delivery Timers**: Dynamically updates customer-facing badges based on the store's local time:
  - *Morning*: *"Order before 12 noon for same-day delivery"*
  - *Afternoon*: *"Order now to receive it by tomorrow (7 Nov)"*
  - *Weekend*: *"Order now to get it by Monday (9 Nov)"*

### 4. High-Performance Submodules
- **Wholesale & Schemes (`modules/wholesale/`)**: B2B PTR rates, MOQ, carton packing guides, margin calculations, and dual-state shop loops.
- **Pharmacy & Clinical (`modules/pharmacy/`)**: Active chemical compositions (`composition`), quantitative pack sizes (`pack_size`), prescription flags (`isRx`), and generic alternative price comparisons.
- **Explore & Directories (`modules/explore/`)**: High-speed brand and category directory grids featuring instant client-side live search.
- **Native SEO (`modules/seo/`)**: Schema.org JSON-LD Product & WebSite metadata, OpenGraph tags, and canonical tags generated statically without third-party SEO plugins.
- **Catalog Health (`backend/`)**: Built-in missing product images audit tool (`[swac_missing_images_finder]`).

---

## 🎨 Theme & Builder Compatibility

Stateless WhatsApp Commerce is built as a **true drop-in solution** designed to work out of the box with any WordPress theme or visual page builder:

- **100% Theme Agnostic**: Leaves site layouts, typography, header/footer templates, and archive loops untouched.
- **Page Builder Tested**: Seamless compatibility with **Breakdance Builder**, **Elementor**, **Bricks**, **Divi**, and **WordPress Block Themes** (Full Site Editing).
- **Breakdance Fixed Canvas**: The Distributor Desk enforces an authoritative 1200px desktop boundary even on blank Breakdance canvas templates without theme containers.
- **Automatic Add-to-Cart Interception**:
  - **Loop Cards**: Hooks into `woocommerce_loop_add_to_cart_link` to convert standard buttons into client-side order actions.
  - **Single Product Pages**: Intercepts `form.cart` submission (`.single_add_to_cart_button`) directly via vanilla JS (`preventDefault`), adding items immediately to `localStorage` without page reloads or AJAX lag.
  - **Custom Builder Layouts**: Drop in the dedicated `[swac_action_row]` shortcode for built-in quantity steppers and WhatsApp ordering buttons.

---

## ⚙️ Configuration Walkthrough

Configuration is split cleanly between two files in the plugin root:

```text
woocommerce-whatsapp-commerce/
  ├── core/config.php   ← Store settings (phone, name, shipping, limits)
  └── features.php      ← Feature toggles (modules, lockdown groups, cleanups)
```

### 1. Store Settings (`core/config.php`)

All settings are plain PHP constants. Edit and save:

```php
// --- Store Identity ---
define('SWAC_PHONE',            '919876543210'); // WhatsApp phone in E.164 format (without '+')
define('SWAC_STORE_NAME',       'Apex Wholesale Mart'); // Store display name
define('SWAC_CITY_NAME',        'Mumbai');      // City / geographic territory (optional)

// --- Delivery & Shipping ---
define('SWAC_FREE_SHIPPING_AT', 500.0);         // Free shipping order threshold (0.0 to disable)
define('SWAC_SHIPPING_CHARGE',  49.0);          // Flat delivery charge below threshold
define('SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES', ['400001', '400051']); // Postal codes with always-free delivery

// --- Order Limits & Safety ---
define('SWAC_MAX_CART_ITEMS',   15);            // Maximum distinct items per order (URL safety)
define('SWAC_MAX_QTY_PER_ITEM', 10);            // Maximum quantity per line item
define('SWAC_ORDER_DISCLAIMER', '⚡ Final bill & product availability will be confirmed on WhatsApp.');

// --- Optional Loyalty Reward ---
define('SWAC_LOYALTY_ENABLED',    true);        // Enable loyalty reward offer in cart drawer
define('SWAC_LOYALTY_THRESHOLD',  1000);        // Minimum cart total to unlock reward
define('SWAC_LOYALTY_ITEM_LABEL', 'Free Travel Kit'); // Label shown to customer
define('SWAC_LOYALTY_ITEM_WORTH', 150);         // Display worth
define('SWAC_LOYALTY_ITEM_PRICE', 0);           // Charge for reward (0 = free)
define('SWAC_LOYALTY_FLAG',       'OFFER ACCEPTED ✅');
```

---

### 2. Feature Toggles (`features.php`)

Fine-tune store behaviour, cleanups, and active modules:

```php
// ZONE 1: Optional WordPress Cleanups (OFF by default)
define('SWAC_DISABLE_BLOG',         false); // 301-redirects blog posts to homepage
define('SWAC_DISABLE_BLOCK_EDITOR', false); // Disable Gutenberg site-wide (classic editor stays)
define('SWAC_STREAMLINE_PRODUCT_TABS', false); // Removes unused product tabs in admin
define('SWAC_SELECT2_TAXONOMIES',   ['product_brand']); // AJAX dropdowns in product editor

// ZONE 2: Catalog Lockdown Groups (ON by default)
define('SWAC_LOCKDOWN_HEAD_BLOAT',         true); // Strips emoji scripts, oEmbed, generator tags
define('SWAC_LOCKDOWN_SECURITY',           true); // Blocks XML-RPC, locks unauthenticated WC REST
define('SWAC_LOCKDOWN_HEARTBEAT_COMMENTS', true); // Disables frontend Heartbeat, closes comments
define('SWAC_LOCKDOWN_ADMIN_CLEANUP',      true); // Removes noisy WP dashboard widgets & Woo nags
define('SWAC_LOCKDOWN_REGISTRATION',       true); // Suppresses guest account registration & emails
define('SWAC_LOCKDOWN_SCRIPT_DEQUEUE',     true); // Dequeues unused WC block styles & scripts

// ZONE 3: Modules & Operating Persona
define('SWAC_MODULE_PHARMACY',  false);          // Rx flags, compositions, pack sizes
define('SWAC_MODULE_EXPLORE',   false);          // Brand & category live directory grids
define('SWAC_MODULE_SEO',       false);          // Native Schema.org JSON-LD & OpenGraph
define('SWAC_MODULE_WHOLESALE', true);           // B2B PTR rates, schemes & distributor desk

// Storefront Operating Mode: 'walled_garden' | 'hybrid' | 'retail'
define('SWAC_CATALOG_MODE',     'walled_garden');

// Search Engine Invisibility (Auto-enabled in walled_garden mode)
define('SWAC_PRIVATE_CATALOG',  true);           // noindex, nofollow, disallow robots, 404 sitemap
```

---

## 🔌 Developer Hooks & Runtime Filters

Stateless WhatsApp Commerce provides clean WordPress filter hooks for dynamic or database-driven overrides:

### 1. Delivery Zones Registry (`swac_delivery_zones`)
```php
add_filter('swac_delivery_zones', function (array $config): array {
    $config['gated'] = true; // Enable postal code gating
    $config['zones']['standard'] = [
        'label'        => 'Metro Service Area',
        'badge_text'   => '✓ Same-Day Delivery Available',
        'status_class' => 'swac-pin-success',
        'allow_order'  => true,
        'pincodes'     => [
            '400001' => 'Fort',
            '400051' => 'Bandra',
        ],
    ];
    $config['zones']['outskirts'] = [
        'label'        => 'Outer Territory',
        'badge_text'   => '⚠️ Delivery takes 2-3 business days',
        'status_class' => 'swac-pin-warning',
        'allow_order'  => false, // Block ordering from unsupported pincodes
        'pincodes'     => [
            '401107' => 'Bhayandar',
        ],
    ];
    return $config;
});
```

### 2. Always-Free Postal Codes Filter (`swac_always_free_shipping_postal_codes`)
```php
add_filter('swac_always_free_shipping_postal_codes', function (array $codes): array {
    $codes[] = '400001';
    return array_unique($codes);
});
```

### 3. Programmatic Store Configuration (`swac_commerce_config`)
```php
add_filter('swac_commerce_config', function (array $config): array {
    $config['phone_number']     = get_option('my_dynamic_store_phone');
    $config['free_shipping_at'] = (float)get_option('my_dynamic_shipping_threshold');
    return $config;
});
```

### 4. SSL Redirect Enforcement Bypass (`swac_enforce_ssl_redirects`)
```php
// Localhost, 127.0.0.1, .test, and .local domains are automatically bypassed.
// Use this filter to selectively bypass SSL redirection on custom staging environments:
add_filter('swac_enforce_ssl_redirects', function (bool $enforce): bool {
    if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'staging') {
        return false;
    }
    return $enforce;
});
```

---

## 📋 Shortcode Suite

All shortcodes strictly utilize the unified `[swac_*]` namespace and render clean, valid HTML compatible with visual page builders:

| Shortcode | Primary Subsystem | Description |
|:---|:---|:---|
| `[swac_action_row]` | Storefront / Single | Full product ordering block with quantity stepper, stock badge, and WhatsApp order button. |
| `[swac_button]` | Storefront / Action | Standalone WhatsApp order button for custom layouts and landing pages. |
| `[swac_price_block]` | Pricing Engine | MRP strike-through, current price, savings amount, and discount percentage badge. |
| `[swac_delivery_notice]`| Delivery Engine | Timezone-aware delivery cutoff notice with dynamic calendar date stamping. |
| `[swac_distributor_portal]`| Wholesale B2B | Mobile frontend Distributor Desk with quick rate sheet, camera upload, and CSV sync. |
| `[swac_explore_brands]` | Explore Module | Brand directory grid with client-side live search and product counts. |
| `[swac_categories_grid]`| Explore Module | Category directory grid with client-side live search. |
| `[swac_directory_search]`| Explore Module | Standalone search bar for brand and category listings. |
| `[swac_generic_box]` | Pharmacy Module | Generic medicine alternative upsell box with price savings comparison. |
| `[swac_composition_tag]`| Pharmacy Module | Active pharmaceutical salt composition tag linking to ingredient explorer. |
| `[swac_rx_badge]` | Pharmacy Module | Prescription requirement regulatory badge (`[Rx]`). |
| `[swac_pack_size]` | Pharmacy Module | Formatted pack size indicator (e.g. *10 Tablets*, *100 ml*). |
| `[swac_explore_salts]` | Pharmacy Module | Salt composition directory with live search filter. |
| `[swac_missing_images_finder]`| Admin Tools | Store admin report listing published products missing featured catalog images. |

> 📖 **Definitive Shortcode Reference**:  
> For exhaustive attribute specifications, defaults, code examples, and parameters, consult the single source of truth:  
> 👉 **[docs/shortcodes.md](docs/shortcodes.md)**

---

## 📚 Documentation Matrix

This repository maintains a strict **single source of truth** architecture. Information is never duplicated across multiple markdown files:

| Document | Primary Audience | Single Source of Truth Scope |
|:---|:---|:---|
| **[README.md](README.md)** | Store Owners & Developers | Project overview, architecture, configuration walkthrough, storefront modes, and quick-start. |
| **[docs/shortcodes.md](docs/shortcodes.md)** | Page Builders & Frontend Devs | **Definitive Shortcode Reference**: Exhaustive attribute dictionaries, visual examples, and shortcode outputs. |
| **[docs/architecture.md](docs/architecture.md)** | Systems Architects & Devs | **Definitive Architecture Deep Dive**: Zero-session caching, DOM state machine, delivery engine, and B2B rate masking. |
| **[AGENTS.md](AGENTS.md)** | AI Agents & Maintainers | **Maintainer & AI Operating Manual**: Immutable invariants, state schemas, subsystem ownership, and REST matrix. |
| **[CONTRIBUTING.md](CONTRIBUTING.md)** | Open Source Contributors | Community contribution guidelines, coding standards, branch workflow, and test requirements. |
| **[SECURITY.md](SECURITY.md)** | Security Researchers | Supported versions matrix, vulnerability disclosure process, and plugin security model. |
| **[CHANGELOG.md](CHANGELOG.md)** | Release Auditors | Chronological release history adhering to Keep a Changelog and Indian date format (`DD-MM-YYYY`). |

---

## 🛠️ System Requirements

- **WordPress**: 6.2+
- **PHP**: 8.2+ (strict requirement)
- **WooCommerce**: 8.0 through 11.2 (HPOS declared compatible)
- **Web Server / Cache**: LiteSpeed Web Server (LSCache), Nginx (`fastcgi_cache`), Apache, or Cloudflare / Edge CDNs
- **Database**: MySQL 5.7+ or MariaDB 10.4+
- **Browser Compatibility**: Chrome, Firefox, Safari, Edge, Android Chrome, Mobile Safari (ES6+ vanilla JavaScript)

---

## 🧪 Testing & Verification

Stateless WhatsApp Commerce features a 0-dependency automated testing suite powered by Node.js native test runner and the PHP static verification runner:

```bash
# Execute the complete automated test suite (Unit tests + PHP invariant linting)
npm test

# Windows 1-Click Launcher (executes directly without Node on PATH)
run-tests.cmd
```

### Test Suite Coverage:
- `cart-math.test.js`: Subtotal, MRP, savings amount, savings percentage, and loyalty reward additions.
- `shipping-thresholds.test.js`: Free shipping threshold evaluation, flat shipping fees, and always-free postal code waivers.
- `delivery-cutoff.test.js`: Timezone cutoff windows (morning same-day, afternoon next-day, weekend Monday cutoff).
- `delimiter-safety.test.js`: WhatsApp base URL `?`/`&` parameter concatenation, address line-break injection sanitization, and emoji encoding.
- `wholesale-schemes.test.js`: Wholesale PTR margins, carton conversions, free goods schemes, cash discounts, and trade order formatting.
- `modular-engines.test.js`: Isolated validation of `delivery-engine.js` and `cart-math.js` without browser DOM dependencies.
- `runner.js`: PHP token balance, HPOS compatibility, text domain validation, and session/cookie invariant enforcement.

---

## 🤝 Contributing

Contributions are warmly welcomed! Please review [CONTRIBUTING.md](CONTRIBUTING.md) for coding standards, pull request processes, and architectural invariants before submitting changes.

---

## 🔒 Security

If you discover a security vulnerability within Stateless WhatsApp Commerce, please follow our coordinated disclosure policy in [SECURITY.md](SECURITY.md). Please do not disclose vulnerabilities in public GitHub issues.

---

## ⚖️ Disclaimer

This plugin is an independent open-source project and is not affiliated with, endorsed by, or sponsored by WhatsApp, Meta Platforms, Inc., or WooCommerce / Automattic Inc. WhatsApp is a registered trademark of Meta Platforms, Inc. WooCommerce is a registered trademark of Automattic Inc.

---

## 📄 License

Stateless WhatsApp Commerce for WooCommerce is licensed under the [GNU General Public License v2.0 or later](LICENSE).
