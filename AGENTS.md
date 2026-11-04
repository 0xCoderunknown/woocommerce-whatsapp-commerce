# AI Maintenance Operating Manual (`AGENTS.md`)

This operational manual documents architectural mental models, immutable invariants, and maintenance procedures for AI
coding agents and developers working on **Stateless WhatsApp Commerce for WooCommerce (`woocommerce-whatsapp-commerce`)**.

> 📖 **Documentation Single Source of Truth**:
> - Architecture & Caching Deep Dive: [docs/architecture.md](docs/architecture.md)
> - Shortcode Reference & Attributes: [docs/shortcodes.md](docs/shortcodes.md)
> - Store Owner Configuration Guide: [README.md](README.md)
> - FOSS Contribution Guidelines: [CONTRIBUTING.md](CONTRIBUTING.md)
> - Release History & Version Log: [CHANGELOG.md](CHANGELOG.md)

---

## 1. The Stateless WhatsApp Catalog Mental Model

`woocommerce-whatsapp-commerce` transforms stateful WooCommerce into a **zero-session, high-performance public catalog**
coupled with a **client-side WhatsApp commerce engine**.

1. **Zero Server Sessions**: WordPress maintains **zero user cart state** and **no guest sessions**. `WC_Session` is replaced with `SWAC_Null_Session_Handler`, making `wp_woocommerce_sessions` completely inert.
2. **No Guest Cookies**: Guest account creation and passwords on public storefront pages are suppressed. `/cart/`, `/checkout/`, and `/my-account/` routes 302-redirect to `home_url('/')`.
3. **Full-Page Cache Friendliness**: Public catalog pages are servable as static HTML directly from LiteSpeed Cache (LSCache), Nginx `fastcgi_cache`, Cloudflare, or Edge CDNs with **zero PHP execution** on cache hits.
4. **Client-Side State Engine**: Cart items (`localStorage['swac_cart']`) and customer addresses (`localStorage['swac_customer_details']`) live strictly in the visitor's browser. Cached prices are reconciled against fresh DOM nodes on drawer open.

*For complete technical breakdown and diagrams, consult [docs/architecture.md](docs/architecture.md).*

---

## 2. Immutable Invariants

Any pull request, refactor, or AI-generated edit **MUST NOT** violate these non-negotiable architectural invariants:

1. **No `session_start` or Native Session Usage**:
    - Never call `session_start()`, never access `$_SESSION`, and never invoke `WC()->session->set()`.
    - Never reintroduce WooCommerce native session initialization on public pages.
2. **No Guest Cookies**:
    - Never issue cookies (`setcookie`, `woocommerce_set_cart_cookies`) to guests or catalog visitors.
    - Any cookie headers sent to public guests break LiteSpeed and reverse-proxy static page caching.
3. **Preserve Shortcodes & Backward-Compatibility Aliases**:
    - Never break or remove existing shortcodes. Consult [docs/shortcodes.md](docs/shortcodes.md) before making shortcode edits.
    - Preserve legacy aliases (`[g1_*]`, `[cdr_*]`) and function shims to prevent breaking existing sites.
4. **4-Way Version Synchronization**:
    - When bumping the plugin version, update all four files in tandem:
        1. [stateless-wa-commerce.php](file:///stateless-wa-commerce.php) (`Version: X.Y.Z`)
        2. [core/config.php](file:///core/config.php) (`define('SWAC_VERSION', 'X.Y.Z');`)
        3. [package.json](file:///package.json) (`"version": "X.Y.Z"`)
        4. [CHANGELOG.md](file:///CHANGELOG.md) (`## [X.Y.Z] — DD-MM-YYYY`)
5. **Indian Date Format Standard**:
    - All dates in `CHANGELOG.md`, documentation, and commit references **MUST** strictly follow the Indian standard
      date format: **`DD-MM-YYYY`** (e.g., `10-10-2026`).
    - Never use ISO `YYYY-MM-DD` or US `MM/DD/YYYY` in release logs.
6. **Public FOSS & Vendor-Neutral Codebase**:
    - This is an open-source GPL v2+ public project on GitHub. Never hardcode private merchant phone numbers, client-specific business names, proprietary tokens, or private delivery routes in source code or documentation. All examples, tests, and defaults must remain generic.
7. **Single Source of Truth Documentation**:
    - Never scatter duplicate parameter dictionaries or architectural essays across multiple markdown files. Update the single authoritative source of truth file identified in the Documentation Matrix.

---

## 3. Delivery Zones & Postal Code Invariants

- **Modes**: Open Mode (accepts all postal codes) vs. Gated Mode (only registered tiers serviceable).
- **Always-Free Postal Codes**:
    - Constant: `SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES` in [core/config.php](file:///core/config.php).
    - Filter: `swac_always_free_shipping_postal_codes`.
    - Helper: `swac_is_always_free_shipping_postal_code($code)`.
    - Localization: `window.swacZones.always_free_shipping_codes`.
    - Client Math: `computeCartTotals(cart, pincode)` waives shipping charges (`shipping = 0`, `isAlwaysFree = true`).
    - Cutoffs: Timezone-aware cutoff windows evaluated using WordPress `wp_timezone()`.

---

## 4. Testing & Verification

Automated testing is 0-dependency and powered by Node.js native test runner and the PHP static verification runner:

```bash
# Complete test suite (Unit tests + PHP domain/invariant linting)
npm test

# Windows 1-Click Launcher (no Node on PATH required)
run-tests.cmd
```

### 4.1. Test Suite Composition

- **Unit Tests (`tests/unit/`)**:
    - `cart-math.test.js`: Cart totals, MRP, discounts, savings percentage, loyalty adjustments.
    - `shipping-thresholds.test.js`: Free shipping threshold evaluation, flat shipping fees, always-free postal codes.
    - `delivery-cutoff.test.js`: IST timezone cutoff windows (morning same-day, afternoon next-day, weekend Monday cutoff).
    - `delimiter-safety.test.js`: WhatsApp base URL `?`/`&` parameter concatenation, address line-break injection sanitization, emoji encoding.
    - `wholesale-schemes.test.js`: Wholesale margins, carton conversions, free goods math, and trade order formatting.
- **PHP Verification Runner (`tests/php/runner.js`)**:
    - Validates `stateless-wa-commerce` text domain across all i18n calls.
    - Enforces `session_start`, `setcookie`, and `$_SESSION` bans.
    - Enforces 4-way version synchronization.
    - Validates structural PHP token balance and HPOS compatibility.

Before submitting any changes, execute `npm test` or `run-tests.cmd` and verify that all test suites pass with 0 failures.

---

## 5. Wholesale B2B Architecture & Maintainer Invariants

The wholesale engine (`modules/wholesale/`) enables B2B trade between stockists and retail stores without violating stateless or cache invariants:

1. **Stateless Rate Masking**:
    - Public HTML catalog loops remain **100% full-page static cacheable**.
    - Rates are masked behind `.swac-b2b-locked-view` for guests. Authenticated retailers receive `.swac-retailer-auth` via client-side JS without page reloads.
2. **Zero-Cost Phone + 4-Digit PIN Authentication**:
    - Never introduce paid third-party SMS OTP gateways (Twilio, MSG91).
    - Authentication verifies against `swac_b2b_approved_retailers` via `/wp-json/swac/v1/retailer-login`.
3. **Frontend Distributor Desk & CSV Sync**:
    - Accessible via `[swac_distributor_portal]` shortcode.
    - Protected by Master PIN (`swac_b2b_master_pin`) and SHA-256 session token (`X-SWAC-Master-Token`).
    - CSV export streams with UTF-8 BOM (`\xEF\xBB\xBF`) for Microsoft Excel compatibility.
4. **Walled Garden & Search Engine Stealth**:
    - `SWAC_CATALOG_MODE = 'walled_garden'`: Hides entire catalog behind Wholesale PIN gate with zero layout flash (FOUC).
    - `SWAC_PRIVATE_CATALOG = true`: Emits `X-Robots-Tag: noindex, nofollow`, blocks `robots.txt`, and 404s `/sitemap.xml`.

---

## 6. Subsystem Map & File Ownership

| Subsystem | Directory / Files | Primary Responsibility |
|:---|:---|:---|
| **Plugin Entry** | `stateless-wa-commerce.php` | Bootstrap, HPOS declaration, rewrite rules, and module loading. |
| **Developer Flags** | `features.php` | Single source of truth for toggling lockdown groups and modules. |
| **Store Config** | `core/config.php` | Merchant WhatsApp number, store name, shipping tiers, city name. |
| **Core Utilities** | `core/helpers.php` | Shared telephone sanitizer, wa.me URL builder, currency & pincode helpers. |
| **Zero-Session Core** | `core/session-engine.php`, `core/catalog-lockdown.php` | `SWAC_Null_Session_Handler`, `/cart/` 302 redirects, AJAX fragment blocker. |
| **System Cleanup** | `core/system-cleanup.php` | Tunable bloat cleanups (head bloat, comments, admin menus, script dequeuing). |
| **Pricing Engine** | `core/pricing-engine.php` | Strike-through MRP HTML formatting, savings calculation, discount %. |
| **Delivery Engine** | `core/delivery-zones.php`, `core/delivery-cutoff.php` | Pincode zoning, always-free postal codes, Kolkata IST cutoff timers. |
| **Cache Coordination** | `core/cache-manager.php` | Surgical LiteSpeed / Nginx cache purging for updated products. |
| **Frontend UI / Drawer** | `frontend/components/cart-drawer.php`, `frontend/enqueue.php` | Floating trigger bubble, slide-out drawer markup, asset localization. |
| **Single Product** | `frontend/single/product-meta.php`, `frontend/single/notices.php` | `[swac_price_block]`, `[swac_action_row]`, `[swac_delivery_notice]`. |
| **Client Engine** | `assets/js/wa_cart.js`, `assets/js/delivery-engine.js`, `assets/js/cart-math.js`, `assets/js/order-serializer.js` | 0-dependency cart state machine, delivery cutoff math, cart totals, DOM price reconciliation, WhatsApp order serialization. |
| **Wholesale Core** | `modules/wholesale/product-meta.php`, `modules/wholesale/shop-card.php`, `modules/wholesale/walled-garden.php` | PTR meta, margin badge calculation, carton guide, dual-state card gating, walled garden mode. |
| **Retailer Auth** | `modules/wholesale/retailer-auth.php`, `modules/wholesale/login-modal.php` | Directory registry, 4-digit PIN verification endpoint, login modal. |
| **Distributor Desk** | `modules/wholesale/frontend-desk.php`, `modules/wholesale/desk-rest.php`, `modules/wholesale/desk-template.php`, `modules/wholesale/templates/distributor-desk-view.php`, `assets/js/distributor-desk.js` | `[swac_distributor_portal]`, quick rate sheet, camera upload, CSV sync. |
| **Retailer Auth (JS)** | `assets/js/retailer-auth.js` | Client-side 4-digit PIN verification modal, status pill, and class toggle. |
| **Pharmacy Module** | `modules/pharmacy/` | Salt composition explorer, prescription flags (`isRx`), pack size badges. |
| **Explore Module** | `modules/explore/` | Brand and category directory grids with live client-side search. |
| **SEO Module** | `modules/seo/` | Static JSON-LD Product & WebSite schema, canonical tags, OpenGraph meta. |

---

## 7. State Registry (Client & Server)

### 7.1. Browser LocalStorage Keys

- `swac_cart`: Array of cart items `[{ id, name, price, mrp, salePrice, qty, cartonPack, schemeBadge, isRx }]`.
- `swac_customer_details`: Customer delivery object `{ name, address, landmark, area, pincode }`.
- `swac_b2b_retailer`: Authenticated retailer session `{ phone, store_name, owner_name, area, token }`.
- `swac_b2b_master_token`: Master token authorizing Distributor Desk mobile access.

### 7.2. WordPress `wp_options` Keys

- `swac_b2b_approved_retailers`: Keyed array of registered retailers `[ phone => { phone, pin, store_name, owner_name, area, status, created_at } ]`.
- `swac_b2b_master_pin`: Plain master passcode for Distributor Desk mobile unlock.
- `swac_b2b_master_hash`: SHA-256 hash of currently active master session token.
- `swac_cron_cleaned`: One-time flag indicating obsolete WooCommerce background sessions cron was unregistered.

### 7.3. Product Post Meta Keys

- `_swac_b2b_ptr`: Wholesale Price to Retailer (float).
- `_swac_b2b_carton_pack`: Number of individual units packed per master shipping carton (integer).
- `_swac_b2b_scheme_type`: Promotional scheme mode (`none`, `free_goods`, `tier_discount`, `cash_discount`, `custom`).
- `_swac_b2b_scheme_buy`: Buy quantity (X) for free goods scheme (integer).
- `_swac_b2b_scheme_free`: Free bonus quantity (Y) for free goods scheme (integer).
- `_swac_b2b_scheme_tiers`: Slabs string for volume discounts (e.g. `10:2%, 25:5%`).
- `_swac_b2b_scheme_cd`: Cash discount percentage (e.g. `2`).
- `_swac_b2b_scheme_custom`: Custom promotional badge text.

---

## 8. REST API Endpoints Matrix

| Endpoint | Method | Permission | Purpose |
|:---|:---|:---|:---|
| `/wp-json/swac/v1/retailer-login` | `POST` | Public | Authenticates retailer via Phone + 4-digit PIN. |
| `/wp-json/swac/v1/desk/auth` | `POST` | Public | Validates Master PIN and issues Distributor Desk token. |
| `/wp-json/swac/v1/desk/products` | `GET` | Authorized | Returns catalog items with PTR and schemes for rate sheet. |
| `/wp-json/swac/v1/desk/quick-update` | `POST` | Authorized | Inline updates MRP, PTR, Carton, Scheme, and Stock. |
| `/wp-json/swac/v1/desk/create-product` | `POST` | Authorized | Creates new simple product with phone camera photo upload. |
| `/wp-json/swac/v1/desk/retailers` | `GET` | Authorized | Fetches approved retailer directory. |
| `/wp-json/swac/v1/desk/save-retailer` | `POST` | Authorized | Creates or edits approved retailer account and PIN. |
| `/wp-json/swac/v1/desk/delete-retailer` | `POST` | Authorized | Deletes retailer from approved directory. |
| `/wp-json/swac/v1/desk/export-csv` | `GET` | Authorized | Streams complete catalog rates as CSV with UTF-8 BOM. |
| `/wp-json/swac/v1/desk/import-csv` | `POST` | Authorized | Bulk updates catalog rates and stock from uploaded CSV. |

---

## 9. AI Anti-Patterns & Common Traps

When modifying or refactoring this codebase, AI agents MUST avoid these common mistakes:

1. ❌ **Do NOT use server-side `is_user_logged_in()` for catalog gating**:
    - Gating must ALWAYS be client-side via `.swac-b2b-locked-view` and `.swac-retailer-auth`. Adding PHP conditional rendering based on user login breaks static page caching for all other visitors.
2. ❌ **Do NOT reintroduce WooCommerce Cart sessions**:
    - Never call `WC()->cart->add_to_cart()`, `WC()->session->set()`, or `session_start()`.
3. ❌ **Do NOT introduce external frontend dependencies**:
    - Do NOT import jQuery, React, Vue, Alpine, or external CDNs into frontend assets. Keep `assets/js/wa_cart.js` strictly 0-dependency vanilla JavaScript.
4. ❌ **Do NOT break Breakdance Builder parity**:
    - Store owners use Breakdance Builder visually. All shortcodes (`[swac_action_row]`, `[swac_price_block]`, `[swac_distributor_portal]`) must output self-contained, valid HTML that renders without theme conflicts.
5. ❌ **Do NOT use unhoisted scoped helpers across functions**:
    - Ensure serialization helpers like `safeStr()` are scoped so they cannot trigger `ReferenceError: Cannot access before initialization`.
6. ❌ **Do NOT alter Date Format standards**:
    - Releases, changelogs, and docs must strictly use Indian standard `DD-MM-YYYY`.
