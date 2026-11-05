# Changelog

All notable changes to **Stateless WhatsApp Commerce for WooCommerce** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

All release dates strictly follow the Indian standard date format: **`DD-MM-YYYY`**.

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
