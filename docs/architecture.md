# Stateless WhatsApp Commerce: Architecture Deep Dive

This document is the **definitive architectural source of truth** for **Stateless WhatsApp Commerce for WooCommerce**.
It details the internal design, caching mechanics, client-side state machine, and B2B wholesale extensions that enable
the plugin to operate as a high-speed, zero-session catalog.

---

## 1. The Core Problem with Standard WooCommerce

Standard WooCommerce is engineered around stateful server sessions:

1. Every guest request initializes `WC_Session_Handler`, dispatching a session cookie (`wp_woocommerce_session_*`).
2. Even passive product browsing triggers database writes to `wp_woocommerce_sessions`.
3. Every page load fires an uncacheable AJAX request (`wc-ajax=get_refreshed_fragments`) to populate server-side cart
   fragments.
4. Edge caches (Cloudflare, LiteSpeed, Varnish) bypass full-page static caching whenever WooCommerce session cookies are
   detected.

---

## 2. The Zero-Session Lockdown Solution

`stateless-wa-commerce` completely eliminates server-side session management:

### 2.1. Session Stubbing (`SWAC_Null_Session_Handler`)

The plugin registers `SWAC_Null_Session_Handler extends WC_Session`, replacing WooCommerce's default database session
handler for guest requests:

- `save_data()`, `init()`, and `destroy_session()` are null-safe no-ops.
- `has_session()` always returns `false`.
- `maybe_set_customer_session_cookie()` and `set_customer_session_cookie()` suppress all session `Set-Cookie` headers.
- `forget_session()` and `cleanup_sessions()` safely return without touching `wp_woocommerce_sessions`.

### 2.2. Cart Fragments Suppression

The WooCommerce AJAX actions for cart fragments (`woocommerce_get_refreshed_fragments`), cart additions, and checkout
review updates are removed. Requests matching blocked
endpoints (`wc-ajax=get_refreshed_fragments`, `apply_coupon`, `update_order_review`) are intercepted early during `init`
and halted with an HTTP 403 JSON response (`{"status":"disabled"}`).

### 2.3. Frontend Route Redirection (302 Gate)

Frontend requests to WooCommerce cart (`/cart/`), checkout (`/checkout/`), and my-account (`/my-account/`) pages are
intercepted via `template_redirect` and safely redirected (HTTP 302) to the homepage (`home_url('/')`) for all visitors,
preventing dual-cart confusion while keeping visual builder preview modes (`elementor-preview`, `breakdance`,
Customizer) unhindered. Additionally, requests to `admin.php?page=wc-admin` (such as onboarding product creation tasks)
automatically intercept `admin_page_access_denied` and redirect to the classic product editor.

### 2.4. Toggleable Lockdown Engine

The six non-architectural lockdown feature groups are individually tunable via constants
in `features.php` (`SWAC_LOCKDOWN_HEAD_BLOAT`, `SWAC_LOCKDOWN_SECURITY`, `SWAC_LOCKDOWN_HEARTBEAT_COMMENTS`, `SWAC_LOCKDOWN_ADMIN_CLEANUP`, `SWAC_LOCKDOWN_REGISTRATION`, `SWAC_LOCKDOWN_SCRIPT_DEQUEUE`),
or programmatically via filters:

```php
// Turn off lockdown completely:
add_filter('swac_enable_catalog_lockdown', '__return_false');

// Or customize individual features programmatically:
add_filter('swac_lockdown_features', function ($features) {
    $features['admin_cleanup'] = false; // preserve full WooCommerce marketing & analytics admin
    return $features;
});
```

---

## 3. Client-Side Cart & DOM State Machine

Since the server maintains zero cart state, all cart interactions are executed entirely in the visitor's browser:

### 3.1. LocalStorage State Storage

- Cart items are stored under the localStorage key `swac_cart`.
- Customer address metadata is stored under `swac_customer_details`.
- Floating cart trigger bubble (`#swac-cart-bubble`) dynamically listens for storage updates and updates its item
  counter badge.

### 3.2. Real-Time Price Reconciliation

Because public HTML pages are aggressively cached for days by static full-page caches, a product's price in cached HTML
might differ from the price stored in a returning user's `localStorage` cart:

1. When the cart drawer opens, `assets/js/wa_cart.js` scans `#swac-product-data` elements in the current DOM.
2. If the current product exists in `localStorage`, the stored price is dynamically updated to the fresh price on the
   page.
3. Totals and discounts are recomputed on the fly.

### 3.3. WhatsApp Order Serialization

When the user clicks "Send Order on WhatsApp":

1. Customer address and postal code are verified against client-side delivery zone rules.
2. The order is compiled into a clean, human-readable plain text message:
    - Greeting & store name
    - Itemized list with quantities and unit prices
    - MRP Subtotal, Savings, Delivery Fee, and Net Payable
    - Delivery Address & Postal Code
3. The string is encoded with standard URL serialization and opened via the configured WhatsApp gateway (
   default: `https://api.whatsapp.com/send?phone={phone}&text={encoded_message}`
   or `https://wa.me/{phone}?text={encoded_message}`), allowing the customer to review and transmit the order directly
   via WhatsApp.

---

## 4. Cache Coordination & Selective Invalidation

LiteSpeed Cache and reverse proxies allow surgical invalidation:

- When a product is updated or its price changes, `core/cache-manager.php`
  invokes `do_action('litespeed_purge_url', $url)` strictly for:
    - The product permalink
    - Its direct category and brand archive URLs
    - The homepage and `/sitemap.xml`
- Entire site cache wipes are strictly avoided, eliminating cache stampedes.

---

## 5. Delivery Zones & Dynamic Dispatch Windows

The delivery engine (`core/delivery-zones.php` and `core/delivery-cutoff.php`) manages postal code validation and order
cutoff scheduling without database lookups on page loads:

### 5.1. Open vs. Gated Postal Modes

- **Open Mode** (default): Any valid postal code is accepted. Shipping fees apply based on `SWAC_FREE_SHIPPING_AT` and
  `SWAC_SHIPPING_CHARGE`.
- **Gated Mode**: Only postal codes registered in `swac_delivery_zones` are serviceable. Unregistered postal codes
  render a clear "out of delivery area" notice and prevent order dispatch.

### 5.2. Always-Free Postal Code Subsystem

Store owners can designate specific postal codes that permanently receive free delivery regardless of cart subtotal:

- Configured via `SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES` in `core/config.php` or the `swac_always_free_shipping_postal_codes` filter.
- Localized to the browser at `window.swacZones.always_free_shipping_codes`.
- The client engine (`computeCartTotals`) evaluates postal codes and waives delivery charges (`shipping = 0`, `isAlwaysFree = true`).

### 5.3. Timezone-Aware Cutoff Scheduling

Calculates dispatch estimates using WordPress `wp_timezone()` (Asia/Kolkata default):

- **Weekday Morning** (< 12:00 PM): Same-day dispatch.
- **Weekday Afternoon** (>= 12:00 PM): Next-day dispatch with formatted calendar date stamping `(j M)`.
- **Weekend Windows**: Orders placed after Saturday 12:00 PM or on Sunday are stamped for Monday dispatch.

---

## 6. Wholesale B2B Schemes & Zero-Cost Gating Architecture

When `SWAC_MODULE_WHOLESALE` is active (`modules/wholesale/`), the catalog serves high-volume B2B trade between
stockists and retail stores while adhering to the core zero-session philosophy:

### 6.1. Dual-State Gated View Without Cache Busting

Unlike traditional B2B plugins that rely on server-side `is_user_logged_in()` checks (which force private caching or
cache-bypass headers), Stateless WhatsApp Commerce renders both states in static HTML:

- `.swac-b2b-locked-view` / `.swac-b2b-locked-action`: Default state seen by public visitors and search engine bots.
  Rates, schemes, and margin calculations are masked behind a `[ 🔒 Retailer PIN to View Rate ]` trigger.
- `.swac-b2b-unlocked-view` / `.swac-b2b-unlocked-action`: Unlocked state containing PTR rates, MRP comparison,
  calculated margin percentages, promotional scheme badges, carton packing guide (`1 Carton = X pcs`), and inline
  quantity controls.

When an approved retailer authenticates:

1. Credentials are verified against the approved retailer registry via `POST /wp-json/swac/v1/retailer-login`.
2. On 200 OK, the retailer's authenticated profile is persisted in `localStorage['swac_b2b_retailer']`.
3. Client-side JavaScript adds `.swac-retailer-auth` to `document.documentElement` and `document.body` within 1
   millisecond, unveiling wholesale pricing without requiring PHP execution or page reloads.

### 6.2. WhatsApp Trade Order Serialization

Wholesale orders serialize both piece counts and whole carton packing conversions:

```text
1. *Premium Detergent Bar 250g* — Qty: 144 (1 Carton)
   Scheme: 12 + 1 FREE
   Price: ₹24.50 × 144 = ₹3,528.00

🏪 *Retailer:* Apex Wholesale Mart (9876543210)
📍 *Route / Area:* Central Market Route
```

Warehouse dispatch clerks can read carton and scheme totals instantly without manual packing math.

### 6.3. Frontend Distributor Desk & CSV Rate Synchronization

To eliminate the friction of navigating WordPress admin screens on mobile phones, `modules/wholesale/` exposes the
`[swac_distributor_portal]` shortcode:

1. **Master PIN Authentication & Rate Limiting**: Phone access is authenticated via a 4-to-8 digit master
   passcode (`swac_b2b_master_pin`). Fail-closed design ensures missing configuration denies access. Protected by IP-based
   rate limiting (5 attempts / 15 min lock). Successful entry issues a token with a 30-day sliding expiry and 1-tap `🔒 Lock`
   invalidation.
2. **Counter-Speed Quick-Update Grid (`/desk/fast-row-update`)**: An in-place tabular spreadsheet editor enabling sub-100ms
   updates for Rate (PTR), Retail MRP, MOQ, and shorthand schemes (`10+1`, `10:2%`, `2% CD`), plus 1-tap stock pill toggling
   (`instock`/`outofstock`) bypassing heavy `WC_Product::save()` lifecycle hooks.
3. **Store & Delivery Settings (`/desk/settings`)**: Mobile-friendly tab allowing distributors to adjust WhatsApp contact,
   store display title, city focus, free delivery cutoff thresholds, and flat delivery fees directly with dynamic `wp_options` fallbacks.
4. **1-Click CSV Sync**:
    - `GET /wp-json/swac/v1/desk/export-csv`: Streams catalog rates as a UTF-8 BOM CSV, directly editable in Microsoft
      Excel.
    - `POST /wp-json/swac/v1/desk/import-csv`: Bulk updates product rates, carton packs, and stock states idempotently,
      triggering automatic cache invalidation.
5. **Modular Controller Architecture**: The REST backend is decomposed into domain-specific sub-controllers under
   `modules/wholesale/rest/` (`auth.php`, `products.php`, `shorthand.php`, `create-product.php`, `retailers.php`, `csv.php`,
   `settings.php`) for maintainability.

---

## 7. Shikhar-Style Walled Garden & Search Engine Stealth Shield

For distributors requiring complete privacy from competing wholesalers and public visitors:

### 7.1. Walled Garden Mode (`SWAC_CATALOG_MODE = 'walled_garden'`)

- The entire catalog, product pages, pricing, and search are hidden behind a full-screen B2B Wholesale PIN login gate.
- Authenticated retailers see the catalog immediately with zero layout flash (FOUC) thanks to a synchronous `<head>` script.
- WhatsApp 1-Tap Auto-Login links (`?b2b_login=PHONE&pin=PIN`) allow distributors to onboard retailers with a single WhatsApp tap.

### 7.2. Search Engine Stealth Shield (`SWAC_PRIVATE_CATALOG = true`)

- Emits `X-Robots-Tag: noindex, nofollow, noarchive` on all catalog headers.
- Outputs `<meta name="robots" content="noindex, nofollow">` in the HTML head.
- Intercepts `/robots.txt` with strict `Disallow: /` directives.
- Responds with HTTP 404 on `/sitemap.xml` to prevent automated inventory discovery.
