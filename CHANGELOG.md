# Changelog

All notable changes to **Stateless WhatsApp Commerce for WooCommerce** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

All release dates strictly follow the Indian standard date format: **`DD-MM-YYYY`**.

---

## [1.0.0] — 21-11-2026

### Added
- **Stateless Zero-Session Commerce Core**:
  - Replaced `WC_Session` with `SWAC_Null_Session_Handler`, making database session writes and `wp_woocommerce_sessions` completely inert.
  - Suppressed WooCommerce guest tracking cookies (`woocommerce_set_cart_cookies`) and AJAX cart fragments polling (`?wc-ajax=get_refreshed_fragments`), enabling 100% full-page static caching (LiteSpeed Cache, Nginx `fastcgi_cache`, Cloudflare, Edge CDNs).
  - Implemented 302 guest lockdown redirect routing `/cart/`, `/checkout/`, and `/my-account/` safely back to `home_url('/')` while preserving visual page builder previews (Breakdance, Elementor, Bricks, Divi, Oxygen).
  - Built 0-dependency client-side LocalStorage cart state machine (`swac_cart`, `swac_customer_details`) with real-time DOM price reconciliation on drawer open.
  - Implemented slide-out cart drawer UI and floating WhatsApp order trigger bubble with item counters and interactive quantity steppers.
  - Added WhatsApp deep-link order serializer (`order-serializer.js`) formatting line items, savings, address routing, and footer disclaimers.
  - Configurable cart ceilings (`SWAC_MAX_CART_ITEMS`, `SWAC_MAX_QTY_PER_ITEM`) to prevent WhatsApp deep-link URL truncation (~2000 chars).
  - Optional loyalty incentive rewards system (`SWAC_LOYALTY_ENABLED`) triggered above configurable cart subtotal thresholds.
- **Delivery & Serviceability Engine**:
  - Timezone-aware delivery dispatch engine (`core/delivery-cutoff.php`, `assets/js/delivery-engine.js`) evaluating morning same-day, afternoon next-day, and weekend cutoffs in Kolkata IST (`wp_timezone()`).
  - Configurable cutoff hour (`SWAC_DELIVERY_CUTOFF_HOUR`) and custom cutoff message filter (`swac_delivery_cutoff_message`).
  - Multi-tier postal code zoning with Open mode and Gated mode (`SWAC_DELIVERY_GATED`).
  - Always-free postal codes configuration (`SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES` and `swac_always_free_shipping_postal_codes` filter) waiving shipping fees regardless of subtotal.
  - Flat shipping fees and configurable free delivery subtotal threshold (`SWAC_FREE_SHIPPING_AT`, `SWAC_SHIPPING_CHARGE`).
- **Wholesale & Schemes B2B Module**:
  - Price-to-Retailer (PTR) metadata, retail MRP comparison, and live margin calculations (amount and percentage).
  - Master shipping carton guide (`1 Carton = X pcs`) and Minimum Order Quantity (MOQ) enforcement.
  - Structured promotional scheme rules: Free goods (Buy X get Y Free), volume discount slabs, cash discount percentages (CD), and custom scheme badges.
  - Counter-speed shorthand scheme parser converting expressions like `"10+1"`, `"12+1 FREE"`, `"10:2%"`, and `"2% CD"` into structured scheme metadata.
  - Zero-cost phone number + 4-digit PIN authentication (`/wp-json/swac/v1/retailer-login`) with brute-force rate limiting and timing-safe `hash_equals()` verification.
  - WhatsApp 1-tap direct-invite auto-login links (`?b2b_login=PHONE&pin=PIN`) for friction-free retailer onboarding.
  - Shikhar-style Walled Garden mode (`SWAC_CATALOG_MODE = 'walled_garden'`) hiding catalog, prices, and search behind wholesale authentication with zero layout flash (FOUC) and automatic administrator bypass.
  - Search engine stealth shield (`SWAC_PRIVATE_CATALOG = true`) emitting `X-Robots-Tag: noindex, nofollow`, disallowing crawling in `robots.txt`, and returning 404 on `/sitemap.xml`.
  - Route-based wholesale dispatch replacing consumer address forms with registered store name, mobile number, delivery route / transport bilty, and remarks.
- **Distributor Desk (`[swac_distributor_portal]`)**:
  - Mobile-first, counter-speed distributor portal with fixed 1200px desktop boundary and rock-solid tabular rate grid (`.swac-fast-grid`).
  - 3-tier role-based access control with sliding 30-day multi-device session tokens (`swac_b2b_desk_tokens`):
    - **Owner Role (`owner`)**: Full authority over rates (PTR), MRP, schemes, MOQ, product creation, retailer directory, store settings, and CSV synchronization.
    - **Staff Role (`staff`)**: Dedicated warehouse stock authority with 1-tap toggling (`[ 🟢 IN STOCK ]` / `[ 🔴 OUT OF STOCK ]`) via high-speed `<100ms` endpoint (`/desk/fast-row-update`); rates and administrative tools are strictly read-only or hidden.
    - **Salesman Role (`salesman`)**: Field order booking authority with read-only catalog access and approved retailer directory route viewing (with private retailer PINs stripped).
  - High-speed product creation with direct smartphone camera photo capture and upload.
  - Bulk spreadsheet CSV export and import with UTF-8 BOM (`\xEF\xBB\xBF`) for Microsoft Excel compatibility.
  - Mobile frontend Store Settings management for WhatsApp phone number, store title, delivery fees, order disclaimers, and access PINs.
  - Cross-Origin Resource Sharing (CORS) whitelist (`X-SWAC-Master-Token`, `X-SWAC-Retailer-Token`) supporting standalone Flutter Web applications.
- **Pharmacy & Health Module**:
  - Salt / active ingredient composition explorer shortcode (`[swac_explore_salts]`).
  - Prescription medicine indicators (`isRx`) and checkout compliance warnings.
  - Pack metrics badges (tablets, capsules, syrups, suspensions, strip counts).
  - Generic medicine alternative upsell box (`[swac_generic_box]`).
- **Explore & Navigation Module**:
  - Brand and category exploration directory grids (`[swac_explore_brands]`, `[swac_categories_grid]`) with instant client-side search filtering (`explore-search.js`).
- **SEO & Search Performance Module**:
  - High-performance, low-memory streaming XML sitemap generator (`/sitemap.xml`) bypassing post query limits.
  - Static JSON-LD Product & WebSite schema, canonical link generation, and OpenGraph social metadata.
  - Toggle `SWAC_ENABLE_SITEMAP` to prevent conflicts with external SEO suites.
- **Backend Utilities & Developer Tooling**:
  - Missing product image audit report shortcode (`[swac_missing_images_finder]`).
  - Select2 taxonomy UI integration using native WooCommerce core handles.
  - Developer feature flag control panel (`features.php`) for fine-grained toggling of cleanups, lockdown groups, and modules.
  - 0-dependency automated testing suite powered by native Node.js test runner (32 unit tests) and PHP static invariant linter (53 PHP files).

### Changed
- Standardized baseline requirement to **PHP 8.2+** and **WooCommerce 8.0+** (tested up to 11.1).
- Declared full compatibility with WooCommerce High-Performance Order Storage (HPOS / `custom_order_tables`) and Cart/Checkout Blocks (`cart_checkout_blocks`).
- Standardized text domain and function namespacing across the entire codebase to `woocommerce-whatsapp-commerce` and `swac_*`.
- Decoupled client frontend engine into high-performance, zero-dependency ES modules: `delivery-engine.js`, `cart-math.js`, `order-serializer.js`, `drawer-ui.js`, and `wa_cart.js`.
- Extracted B2B wholesale styles into dedicated modular stylesheet (`assets/css/modules/wholesale.css`), enqueued conditionally to minimize frontend payload.
- Modernized script loading with WordPress 6.3+ `defer` strategy in footer.
- Optimized options loading with explicit `autoload = false` on heavy directories and tokens to prevent `wp_load_alloptions` cache bloat.
- Replaced classic WooCommerce lifecycle hooks (`$product->save()`) in distributor desk fast updates with direct metadata and stock mutation for counter-speed response times.
- Hardened distributor master PIN and retailer authentication with timing-safe `hash_equals()` comparison, IP brute-force rate limiting, and dynamic `wp_salt('auth')` derivation.
- Hardened canonical HTTPS redirection and CSP headers to safely bypass local development environments (`localhost`, `127.0.0.1`, `.test`, `.local`).
- Hardened single product shortcodes (`[swac_price_block]`, `[swac_action_row]`, `[swac_button]`, `[swac_delivery_notice]`) for robust rendering across visual page builders (Breakdance, Elementor, Bricks).

### Removed
- **WooCommerce Guest Sessions & Cookies**: Completely eliminated `WC_Session` writes, `wp_woocommerce_sessions` database queries, and guest tracking cookies.
- **Background Cron Wakeups**: Unregistered obsolete WooCommerce session garbage collection cron jobs.
- **Cart Fragments Polling**: Blocked native WooCommerce cart fragments script (`wc-cart-fragments`) and AJAX polling (`?wc-ajax=get_refreshed_fragments`).
- **Legacy Compatibility Shims & Prefixes**:
  - Purged all legacy `g1_*` and `cdr_*` shortcode aliases (`[g1_delivery_notice]`, `[cdr_whatsapp_button]`, `[cdr_generic_box]`, `[g1_generic_box]`, `[g1_composition_tag]`).
  - Purged legacy functions (`g1_get_delivery_cutoff_message()`, `cdr_whatsapp_button_shortcode()`, `cdr_generic_box_html()`).
  - Purged single-token fallback `swac_b2b_master_hash` in favor of multi-device session tokens map (`swac_b2b_desk_tokens`).
  - Purged obsolete CSS classes (`.cdr-alt-salt`, `.cdr-totals-loyalty .cdr-loyalty-gift-icon`).
