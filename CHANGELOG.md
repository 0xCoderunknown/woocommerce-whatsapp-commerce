# Changelog

All notable changes to **Stateless WhatsApp Commerce for WooCommerce** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

All release dates strictly follow the Indian standard date format: **`DD-MM-YYYY`**.

---

## [1.5.0] — 03-11-2026

### Added
- **Wholesale & Schemes B2B Module** (`modules/wholesale/`):
  - **Wholesale PTR & Schemes Meta**: Added `_swac_b2b_ptr`, `_swac_b2b_carton_pack`, and flexible promotional scheme rules (free goods e.g. `12+1`, volume tier slabs, cash discount %, custom text).
  - **Dynamic Margin Calculation**: Automatically computes retailer margin amount and percentage relative to MRP on loop cards and single product pages.
  - **Carton Packing Guide**: Displays informative carton reference labels (`1 Carton = X pcs`) to streamline bulk order sizing.
  - **Direct Shop-Page & Single-Page Ordering**: Added inline quantity selector and bulk-order action button across both catalog cards and single product views.
  - **Zero-Cost Retailer Login Gate**: Implemented mobile + 4-digit PIN authentication (`modules/wholesale/retailer-auth.php`) with zero SMS gateway costs.
  - **Client-Side Wholesale Price Masking**: Gated wholesale rates, schemes, and margins behind client-side verification, keeping the public storefront 100% full-page static cacheable.
  - **WhatsApp Retailer Registration Handshake**: Integrated 1-tap WhatsApp account application (`modules/wholesale/login-modal.php`) for unapproved retailers.
  - **Approved Retailers Admin Directory**: Added CRUD management screen in WordPress admin under Products to manage retailer PINs and accounts.
  - **Frontend Distributor Desk (`[swac_distributor_portal]` / `[swac_frontend_admin]`)**: Mobile-first workspace with live-search rate tweaker, single-screen product entry with direct camera photo upload, and retailer PIN desk.
  - **1-Click CSV Rate Sync**: Added one-click CSV export and upload to sync hundreds of product rates and stock states instantly.
  - **WhatsApp Order Compilation**: Enhanced order payload to itemize cartons, loose units, active schemes, and retailer store identification for warehouse dispatch.

---

## [1.4.1] — 01-11-2026

### Fixed
- **Action Row Button Binding**: Resolved critical issue where `[swac_action_row]` quantity controls (`.swac-qty-btn`) and Add to Order buttons (`.swac-single-add-btn`) were inert in `assets/js/wa_cart.js`. Added complete click listeners, data attribute extraction, and quantity clamping.
- **Single Product Add-to-Cart Label Filter**: Added `woocommerce_product_single_add_to_cart_text` filter to ensure standard WooCommerce single product pages display "Add to Order", matching archive loop buttons.
- **Page Builder Context Resolution in Pharmacy Tags**: Hardened `swac_composition_tag_shortcode` in `modules/pharmacy/product-meta.php` with queried object and `get_the_ID()` fallbacks when global `$post` is null.
- **PHP CLI Test Runner Detection**: Enhanced `tests/php/runner.js` to auto-detect Windows XAMPP PHP installations (`K:\xampp\php\php.exe`) and run full `php -l` syntax checking across all PHP files.

### Added
- **Configurable Product Editor Tab Streamlining**: Introduced `SWAC_STREAMLINE_PRODUCT_TABS` feature flag (default `false`), preserving `attribute` and `variations` tabs by default so standard stores selling variable products are not impaired.
- **Non-Destructive Taxonomy Select2 Overrides**: Introduced `SWAC_SELECT2_TAXONOMIES` feature flag (default `['product_brand']`), ensuring WooCommerce's native hierarchical category checklist (`product_catdiv`) is preserved by default.

---

## [1.4.0] — 10-10-2026

### Added
- **Multi-Tier Always-Free Postal Code Support** (`core/config.php`, `core/delivery-zones.php`, `frontend/enqueue.php`, `assets/js/wa_cart.js`, `assets/css/cart-drawer.css`):
  - Added `SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES` configuration constant (defaults to `[]`).
  - Added helper function `swac_is_always_free_shipping_postal_code($code)` with `swac_always_free_shipping_postal_codes` filter.
  - Localized `always_free_shipping_codes` to both `window.swacZones` and `window.swacConfig` / `window.swacCommerce`.
  - Added client-side `isAlwaysFreePincode(pincode)` and made `computeCartTotals()` postal-code aware to automatically waive shipping (`shipping = 0`, `isAlwaysFree: true`).
  - Made `renderLoyaltyUI()` display a distinct green `.swac-loyalty-card--free-zone` badge ("🎉 Free Delivery Unlocked!") while bypassing shipping gap nudges.
  - Added real-time postal code `input` and `blur` event triggers to dynamically recalculate drawer totals and loyalty cards as the customer types.
- **Background Cron Cleanup Guard** (`core/catalog-lockdown.php`):
  - Added a persistent one-time option guard (`swac_cron_cleaned`) on `init` that unschedules and clears unused WooCommerce background cron events (`woocommerce_cleanup_sessions`, `woocommerce_cleanup_personal_data`, `woocommerce_cancel_unpaid_orders`, `woocommerce_tracker_send_event`) to eliminate needless PHP wakeups.
- **0-Dependency Automated Testing Suite & CommonJS Export Parity** (`package.json`, `tests/`, `assets/js/wa_cart.js`):
  - Created root `package.json` with native Node.js test script (`node --test tests/unit/*.test.js && node tests/php/runner.js`).
  - Added Node runtime environment guards (`typeof window`, `typeof document`, `typeof localStorage`) inside `assets/js/wa_cart.js`.
  - Exported core calculation helpers via CommonJS (`module.exports = { computeCartTotals, evaluateDeliveryZone, isAlwaysFreePincode, ... }`).
  - Added unit test suites under `tests/unit/` covering cart math, shipping thresholds, delivery cutoff windows, and delimiter safety.
  - Added static PHP verification runner `tests/php/runner.js` enforcing text domain consistency, structural token balance, and immutable zero-session invariants.
- **AI Maintenance Operating Manual** (`AGENTS.md`):
  - Codified the zero-session Stateless WhatsApp Catalog mental model, immutable invariants, Indian date format standard (`DD-MM-YYYY`), and testing guidelines for AI coding agents.

### Fixed
- **Text Domain Standardization** (`frontend/archive/archive-loop.php`, `frontend/single/notices.php`, `frontend/single/product-meta.php`):
  - Replaced lingering `woocommerce` text domains with canonical `stateless-wa-commerce` domain for Out of Stock badges and labels.

---

## [1.3.2] — 05-10-2026

### Fixed
- **403 Intercept on `wc-admin` (Menu Capability Check)**: Hooked WooCommerce Admin routes (`page=wc-admin`) to `admin_page_access_denied` in addition to `admin_init`. WordPress core validates menu capabilities inside `wp-admin/includes/menu.php` and executes `wp_die(..., 403)` before `admin_init` is ever called; intercepting `admin_page_access_denied` catches the access denial before headers are closed and seamlessly redirects to the classic product editor (`post-new.php?post_type=product`).
- **Eliminated Frontend Dual-Cart Split Brain**: Removed `is_user_logged_in()` exemptions from frontend `/cart/`, `/checkout/`, and `/my-account/` redirects (`template_redirect`), `woocommerce_get_cart_url`, native AJAX add-to-cart suppression, `wc-add-to-cart`/`wc-cart-fragments` script dequeues, and `SWAC_Null_Session_Handler`. Prevents administrators from having phantom products accumulating in native WooCommerce PHP sessions while concurrently viewing the client-side WhatsApp `localStorage` drawer.
- **Visual Builder Preview Safe Guards**: Explicitly exempted visual page builder editors and previews (`?elementor-preview`, `?breakdance`, `is_customize_preview()`, and `is_admin()`) so template editing remains completely unhindered.
- **Admin Bar Dashicons**: Ensured Dashicons remain enqueued on the frontend for authenticated users so the WordPress admin toolbar icons render crisply.

### Added
- **Theme & Builder Compatibility Guide** (`README.md`): Added comprehensive documentation on drop-in compatibility with **Breakdance Builder**, **Elementor**, **Bricks**, and **Block Themes**, detailing how native "Add to Cart" forms and loop buttons are intercepted and replaced by the zero-dependency slide-out drawer.
- **Explore Shortcode Empty State Guidance**: Enhanced fallback notices in `modules/explore/brands.php`, `modules/explore/categories.php`, and `modules/pharmacy/explore-salts.php` to clarify when terms are hidden due to having no associated products (`hide_empty="true"` by default), advising users to assign products or pass `hide_empty="false"`. Also replaced silent blank outputs in `[swac_categories_grid]` with helpful missing/invalid slug messages.

---

## [1.3.1] — 05-10-2026

### Fixed
- **Sitemap Activation Hook**: Moved `register_activation_hook()` and `register_deactivation_hook()` from `integrations/sitemap-generator.php` to `stateless-wa-commerce.php` to ensure `/sitemap.xml` rewrite rules flush properly on activation.
- **WooCommerce Native Select2**: Replaced external CDN release-candidate assets (`select2@4.1.0-rc.0`) with WooCommerce core registered `select2` script and style handles in `backend/taxonomy-ui.php`.
- **Taxonomy Save Validation**: Enforced whitelist check against `swac_select2_taxonomies` and `taxonomy_exists()` in `backend/taxonomy-ui.php` prior to updating object terms.
- **Dead Script Defer Filter**: Removed redundant `script_loader_tag` filter in `core/pricing-engine.php` (frontend script deferral is handled natively via WP 6.3+ script loading strategy in `frontend/enqueue.php`).
- **Lockdown Boundary Alignment**:
  - Removed `is_author()` from `cart_checkout_redirect` in `core/catalog-lockdown.php` (already properly handled under `SWAC_DISABLE_BLOG` in `features.php`).
  - Moved `show_admin_bar(false)` from `cart_checkout_redirect` into `SWAC_LOCKDOWN_ADMIN_CLEANUP`.
- **Defensive Function Guards**: Added `if (!function_exists(...))` guards to `swac_price_block_shortcode()`, `swac_pack_size_shortcode()`, `swac_price_pack_hierarchy_shortcode()`, and `swac_action_row_shortcode()` in `frontend/single/product-meta.php`, as well as `swac_register_null_session_handler()` in `core/catalog-lockdown.php`.
- **Markup Consistency**: Removed redundant `wa-*` legacy CSS classes from `frontend/single/delivery-notice.php` in favor of canonical `swac-*` classes.
- **Cart Drawer Disclaimer Fallback**: Removed hardcoded disclaimer fallback in `frontend/components/cart-drawer.php` that was overriding empty or custom disclaimer configurations.
- **Admin Configuration Notice**: Clarified the phone warning notice in `backend/admin-tools.php` to mention `SWAC_PHONE in core/config.php`.
- **WooCommerce Admin Route Intercept**: Added `admin_init` redirect for `admin.php?page=wc-admin` (e.g. `task=products` onboarding and empty-state product links) to redirect seamlessly to the standard WordPress product editor (`post-new.php?post_type=product`) instead of triggering a 403 "Sorry, you are not allowed to access this page" when `admin_cleanup` strips `wc-admin`.

---

## [1.3.0] — 05-10-2026

### Added
- **Developer Feature Flag Control Panel** (`features.php`):
  - New root-level `features.php` controlling optional WordPress cleanups (`SWAC_DISABLE_BLOG`, `SWAC_DISABLE_BLOCK_EDITOR`), lockdown feature groups, and modular subsystems.
- **Restructured Human-Editable Constant File** (`core/config.php`):
  - Store settings elevated to clean PHP constants with clear boundary above engine code.
- **Prescription Decoupling**: Removed prescription note from core store config to keep general stores clean.
- **Frontend Enqueue Extraction** (`frontend/enqueue.php`): Moved `wp_enqueue_scripts` out of the root plugin file into a dedicated frontend enqueue file.

---

## [1.2.2] — 03-10-2026
- Added `(j M)` calendar date stamping to delivery cutoffs and WhatsApp estimates.
- Added `icon="yes|no"` toggle to `[swac_button]`.
- Normalized loyalty gift emoji spacing and clean markup for generic/composition tags.
- Added backward-compatibility aliases for legacy `g1_` and `cdr_` shortcodes/functions.

## [1.2.1] — 30-09-2026
- Added canonical WhatsApp URL builder `swac_build_whatsapp_url()` handling query parameters safely.
- Added concise delivery estimate day labels (`getDeliveryEstimate()`).
- Streamlined WhatsApp delivery details and totals formatting.

## [1.2.0] — 29-09-2026
- Declared HPOS (`custom_order_tables`) and Cart/Checkout Blocks compatibility.
- Fault-tolerant module loading with existence guards.
- Namespaced core PHP functions, constants, filters, CSS classes, and text domain to `swac_` / `stateless-wa-commerce`.
- Unconfigured phone number safeguards.

## [1.1.0] — 26-09-2024
- Added configurable cart ceilings (`max_cart_items`, `max_qty_per_item`) to prevent WhatsApp deep-link URL truncation.
- Added floating toast notifications for user quota limits.
- Decoupled pharmaceutical Rx tagging and prescription disclaimer notes.
- Resolved WhatsApp emoji encoding across redirects.

## [1.0.0] — 26-09-2024
- Initial release of the Stateless Zero-Session WhatsApp Commerce engine for WooCommerce.
- Suppression of guest sessions, cart fragments, and tracking cookies for LiteSpeed full-page caching.
- Client-side LocalStorage cart drawer with real-time DOM price reconciliation and order serializer.
- Multi-tier delivery serviceability registry and timezone-aware dispatch cutoff engine.
- Low-memory streaming XML sitemap generator (`/sitemap.xml`) and SSL/reverse-proxy enforcer.
- Optional modular extensions for pharmacy, exploratory directories, and native SEO schemas.
