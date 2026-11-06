# Changelog

All notable changes to **Stateless WhatsApp Commerce for WooCommerce** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

All release dates strictly follow the Indian standard date format: **`DD-MM-YYYY`**.

---

## [1.7.4] — 06-11-2026

### Fixed & Enhanced
- **Walled Garden Store Owner & Admin Bypass**:
  - Automatically exempted logged-in WordPress Administrators and Store Managers (`current_user_can('manage_woocommerce')`) from the frontend Walled Garden gate screen, allowing direct access to the `/shop/` catalog and unlocked rate views.
  - Added support for Distributor Master Tokens (`swac_b2b_master_token`) in client-side auth state (`retailer-auth.js` and `<head>` script) so distributors who unlock the mobile Distributor Desk are recognized on the storefront with a `👑 Distributor Admin` status pill without needing a dummy retailer account.
  - Supported clearing `swac_b2b_master_token` on status pill logout.

---

## [1.7.3] — 05-11-2026

### Removed & Migrated (Legacy Compatibility Purge)
- **Purged Legacy `g1_*` & `cdr_*` Shims**: Completely purged old backward-compatibility aliases and shims across all subsystems in favor of the canonical `swac_*` architecture:
  - Purged `g1_get_delivery_cutoff_message()` in `core/delivery-cutoff.php`.
  - Purged `[cdr_whatsapp_button]` and `cdr_whatsapp_button_shortcode()` in `frontend/components/whatsapp-button.php`.
  - Purged `[g1_delivery_notice]` in `frontend/single/delivery-notice.php`.
  - Purged `[cdr_generic_box]`, `[g1_generic_box]`, and `cdr_generic_box_html()` in `modules/pharmacy/generic-upsell.php`.
  - Purged `[g1_composition_tag]` in `modules/pharmacy/product-meta.php`.
  - Cleaned legacy CSS classes `.cdr-alt-salt` and `.cdr-totals-loyalty .cdr-loyalty-gift-icon`.
- **Documentation Alignment**: Updated `AGENTS.md` and `docs/shortcodes.md` to reflect the clean unified `swac_*` namespace without obsolete legacy aliases.

---

## [1.7.2] — 05-11-2026

### Performance & Modernization
- **Autoload Optimization (WordPress 6.6+)**: Explicitly passed `autoload = false` to `swac_b2b_approved_retailers`, `swac_b2b_master_hash`, `swac_b2b_master_pin`, and `swac_cron_cleaned` to keep `wp_load_alloptions` lean and prevent B2B directories from bloating static catalog page loads.
- **Modern Script Loading (WordPress 6.3+)**: Updated `swac-desk-script` enqueueing in `modules/wholesale/frontend-desk.php` to use `['strategy' => 'defer', 'in_footer' => true]`.
- **PHP 8.2 Baseline & Modern Primitives**:
  - Declared `Requires PHP: 8.2` in primary plugin header.
  - Replaced legacy `strpos` substring searches with native `str_contains` in URL builders and admin handlers.
  - Replaced legacy `substr` checks with native `str_ends_with` across SSL local domain detectors and pharmacy pack metrics.

---

## [1.7.1] — 05-11-2026

### Added
- **Store Contact & Delivery Settings**: Added frontend Store Settings tab to Distributor Desk for direct mobile management of WhatsApp business phone, store title, delivery fees, and order footer disclaimer.

### Security & Hardening
- **Distributor Master PIN Hardening**: Removed predictable fallback to merchant phone number or default passcode; Distributor Desk fails closed if unconfigured in settings.
- **Brute-Force Rate Limiting**: Added IP-based attempt throttling on Master Desk and Retailer login endpoints to block automated PIN guessing.
- **Session Expiry & Quick Lock**: Added 30-day sliding token lifespan for warehouse staff and a one-tap Lock action in the Distributor Desk header.

### Performance & Architectural Refactoring
- **Lightweight Legacy Desk Actions**: Replaced full WooCommerce lifecycle hooks (`$product->save()`) in legacy rate sheet updates with direct meta and stock updates.
- **Wholesale REST Controller Modularization**: Restructured wholesale REST controllers into clean, domain-specific modules (`modules/wholesale/rest/`) for enhanced maintainability.
- **Counter Grid Script Fix**: Cleaned up duplicate debounce handler in live search input.

---

## [1.7.0] — 05-11-2026

### Added
- **Distributor Counter-Speed In-Place Quick-Update Grid**:
  - Live in-place tabular editor in `[swac_distributor_portal]` with tab-through inputs for Rate (PTR), Retail MRP, Promotional Scheme, and MOQ.
  - 1-Tap Stock Pill button for instantaneous toggling between `[ 🟢 IN STOCK ]` and `[ 🔴 OUT OF STOCK ]` with optimistic UI response.
  - Auto-save on blur and field commit with subtle inline sync feedback (`Saving...` → `Saved ✓`).
  - High-speed `POST /wp-json/swac/v1/desk/fast-row-update` REST endpoint executing targeted updates without full `WC_Product::save()` lifecycle overhead.
  - Server-side shorthand scheme notation parser converting `"10+1"`, `"12+1 FREE"`, `"10:2%"`, `"2% CD"` directly into structured promotional scheme data.
  - Introduced Minimum Order Quantity (`_swac_b2b_moq`) post-meta across REST controllers and WooCommerce wholesale admin panels.

### Fixed
- **Loop Price Infinite Recursion**: Removed recursive `$product->get_price_html()` call inside `swac_calculate_price_data()` and added re-entrancy protection to `woocommerce_get_price_html` filter in `frontend/archive/archive-loop.php` to prevent memory exhaustion during product list rendering.
- **Localhost SSL Redirect Guard**: Prevented canonical HTTPS 301 redirection and CSP `upgrade-insecure-requests` headers on local development environments (`localhost`, `127.0.0.1`, `.test`, `.local`) and corrected subdirectory URL path resolution in `integrations/ssl-security.php`.

---

## [1.6.0] — 04-11-2026

### Added
- **Shikhar-Style Walled Garden Mode** (`SWAC_CATALOG_MODE = 'walled_garden'`):
  - Completely hides the product catalog, pricing, search, and cart drawer behind a B2B Wholesale PIN login gate.
  - Zero layout flash (FOUC) for authenticated retailers via an inline `<head>` script.
  - Public visitors and guests see the distributor portal login card only, preventing rate exposure.
- **Search Engine Stealth Shield** (`SWAC_PRIVATE_CATALOG = true`):
  - Disallows search engine crawling and indexing for private wholesale sites via `X-Robots-Tag: noindex, nofollow`, `<meta name="robots">`, and dynamic `robots.txt` disallow rules.
  - Automatically returns HTTP 404 on `/sitemap.xml` to prevent inventory discovery by competitors.
- **WhatsApp 1-Tap Direct-Invite Auto-Login**:
  - Wholesalers can distribute direct login links (`?b2b_login=PHONE&pin=PIN`) via WhatsApp that instantly authenticate retailers on click without manual credential entry.
- **B2B Route-Based Dispatch**:
  - Replaces consumer address forms with verified store name, mobile number, delivery route / area (e.g. Central Wholesale Route, Transport Bilty), and optional dispatch remarks.
- **Modular Zero-Dependency Frontend Engine**:
  - Decomposed the client engine into high-performance, standalone modules for delivery cutoffs (`delivery-engine.js`), pricing & totals math (`cart-math.js`), order serialization (`order-serializer.js`), and drawer presentation (`drawer-ui.js`).

---

## [1.5.1] — 04-11-2026

### Security & Hardening
- **Timing-Safe PIN Verification**: Secured retailer 4-digit PINs and distributor Master PIN authentication using `hash_equals()` to eliminate timing leak vulnerabilities.
- **Dynamic Auth Salting**: Hardened B2B authentication token generation using dynamic WordPress core `wp_salt('auth')`.
- **CSV Import Validation**: Enforced strict `.csv` / `.txt` file validation for bulk rate sheet imports on the Distributor Desk.
- **Admin & Cache Isolation**: Guarded WooCommerce price filters from altering admin product tables and prevented redundant cache purges during post revisions.

---

## [1.5.0] — 03-11-2026

### Added
- **Wholesale & Schemes B2B Module**:
  - **Wholesale PTR & Schemes Pricing**: Added wholesale price-to-retailer (PTR) fields, carton pack sizes, and flexible promotional scheme rules (buy X get Y free, volume discount slabs, cash discounts, custom badges).
  - **Dynamic Margin Calculation**: Automatically computes retailer margin amount and percentage relative to MRP on catalog cards and single product views.
  - **Carton Packing Guide**: Displays master carton packing references (`1 Carton = X pcs`) to streamline bulk order sizing.
  - **Zero-Cost Retailer PIN Login Gate**: Built-in phone number + 4-digit PIN authentication with zero third-party SMS gateway fees.
  - **Frontend Distributor Desk (`[swac_distributor_portal]`)**: Mobile-first portal with instant rate search, fast product creation with direct camera photo upload, and retailer PIN directory.
  - **1-Click Spreadsheet CSV Rate Sync**: Bulk export and import to update hundreds of product rates and stock states instantly.
  - **WhatsApp Wholesale Order Compilation**: Order messages automatically itemize whole cartons, loose units, applied promotional schemes, and verified retailer store routing.

---

## [1.4.1] — 01-11-2026

### Fixed
- **Action Row & Loop Button Reliability**: Ensured `[swac_action_row]` quantity steppers and "Add to Order" action buttons bind reliably across all catalog and single product templates.
- **Unified Action Button Labels**: Synchronized single product action buttons to display "Add to Order", matching archive loop cards.
- **Builder Template Context Resolution**: Hardened pharmaceutical composition tags and product metadata shortcodes to resolve product context accurately inside visual page builder templates.

---

## [1.4.0] — 10-10-2026

### Added
- **Multi-Tier Always-Free Postal Code Support**:
  - Added `SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES` configuration constant and `swac_always_free_shipping_postal_codes` filter.
  - Automatically waives delivery fees for designated postal codes regardless of cart subtotal, with instant UI badge feedback.
- **Background Cron Optimization**:
  - Unschedules obsolete WooCommerce session cleanup cron jobs to eliminate background PHP wakeups.
- **0-Dependency Automated Testing Suite**:
  - Integrated native Node.js test runner covering cart math, shipping rules, delivery cutoffs, delimiter safety, and wholesale schemes.
  - Added PHP static verification runner enforcing text domains, HPOS compatibility, and zero-session invariants.
- **AI Maintenance Operating Manual (`AGENTS.md`)**:
  - Established architectural guidelines, invariants, state registry, and maintenance mental models.

---

## [1.3.2] — 05-10-2026

### Fixed
- **WooCommerce Admin Route Intercept**: Redirects `page=wc-admin` access denials smoothly to the classic product editor (`post-new.php?post_type=product`) instead of throwing 403 errors.
- **Eliminated Dual-Cart Split Brain**: Removed admin-session exemptions from frontend redirects, ensuring logged-in administrators and guests share the identical zero-session client cart.
- **Visual Builder Preview Compatibility**: Explicitly exempted visual page builder editors and previews (Breakdance, Elementor, Bricks) from lockdown redirects.

---

## [1.3.1] — 05-10-2026

### Fixed
- **Sitemap Activation Lifecycle**: Standardized activation and deactivation hooks for the streaming XML sitemap generator.
- **Native Select2 Asset Standardization**: Replaced external CDN assets with WooCommerce core registered select2 handles.
- **Defensive Shortcode Guards**: Hardened shortcode callbacks and session engine handlers against duplicate function declarations.

---

## [1.3.0] — 05-10-2026

### Added
- **Developer Feature Flag Control Panel (`features.php`)**:
  - Introduced fine-grained toggles for optional WordPress cleanups, lockdown feature groups, and modular subsystems.
- **Simplified Store Settings (`core/config.php`)**:
  - Elevated essential merchant settings to human-readable PHP constants.
- **Decoupled Pharmaceutical Features**: Decoupled prescription notices from standard catalog stores into the dedicated pharmacy module.

---

## [1.2.2] — 03-10-2026

### Changed
- Added calendar date stamping to delivery cutoffs and WhatsApp estimates.
- Added `icon="yes|no"` toggle to `[swac_button]`.
- Added backward-compatibility aliases for legacy shortcodes and shims.

---

## [1.2.1] — 30-09-2026

### Changed
- Added canonical WhatsApp URL builder handling query parameters safely.
- Streamlined WhatsApp delivery details and totals formatting.

---

## [1.2.0] — 29-09-2026

### Changed
- Declared High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatibility.
- Standardized text domain and function namespacing to `stateless-wa-commerce`.

---

## [1.1.0] — 26-09-2024

### Added
- Configurable cart ceilings (`max_cart_items`, `max_qty_per_item`) to protect WhatsApp deep-link URL lengths.
- Floating toast notifications for cart quota limits.

---

## [1.0.0] — 26-09-2024

### Added
- Initial release of the Stateless Zero-Session WhatsApp Commerce engine for WooCommerce.
- Suppression of guest sessions, cart fragments, and tracking cookies for full-page static caching.
- Client-side LocalStorage cart drawer with real-time DOM price reconciliation and order serializer.
- Multi-tier delivery serviceability registry and timezone-aware dispatch cutoff engine.
- Low-memory streaming XML sitemap generator (`/sitemap.xml`) and SSL/reverse-proxy enforcer.
