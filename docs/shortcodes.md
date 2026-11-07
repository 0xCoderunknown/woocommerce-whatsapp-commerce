# Shortcodes Reference Manual

This document is the **authoritative single source of truth** for all shortcodes available in **Stateless WhatsApp Commerce for WooCommerce**.
It serves as the definitive reference for visual page builders (Breakdance, Elementor, Bricks), custom theme templates, and store engineers.

> 📖 **Related Documentation**:
> - Technical Architecture: [docs/architecture.md](architecture.md)
> - Store Configuration & Setup: [README.md](../README.md)
> - Maintainer & AI Operating Manual: [AGENTS.md](../AGENTS.md)

---

## Architectural Separation: Base Layer vs. Modular Subsystems

The plugin separates presentation tags into two architectural tiers:

1. **Base Layer (Core Commerce & Storefront)**:
   The foundational, zero-session catalog primitives. These provide WhatsApp order routing, strike-through MRP formatting, delivery cutoff timers, quantity action rows, and catalog maintenance audit tools. They operate out of the box with zero module dependencies.
2. **Modular Subsystems (`modules/`)**:
   Domain-specific enhancements that can be individually enabled or disabled in `features.php`:
   - **Explore Directory Module (`modules/explore/`)**: Directory browsing for brands, parent-child taxonomy grids, and instant client-side directory search.
   - **Pharmacy Subsystem (`modules/pharmacy/`)**: Clinical medicine features including active salt formulas, generic substitution savings boxes, Rx badges, and sizing guides.
   - **Wholesale & B2B Subsystem (`modules/wholesale/`)**: Trade ordering, carton packing logic, approved retailer directory, and the complete frontend Distributor Desk.

---

## Quick Reference Index

### Base Layer (Core Commerce)
| Shortcode | Purpose | Key Attributes |
|:---|:---|:---|
| `[swac_button]` | Customizable WhatsApp action/order button | `text`, `message`, `fullwidth`, `icon` |
| `[swac_price_block]` | Hero price display with regular price strike & discount % | `context` (`single` \| `card`) |
| `[swac_pack_size]` | Packaging unit badge (e.g. "10 Tablets", "500 ml") | None |
| `[swac_price_pack_hierarchy]` | Unified price block and pack size container | `context` (`single` \| `card`) |
| `[swac_action_row]` | Add to Order quantity controls & WhatsApp button | None |
| `[swac_delivery_notice]` | Timezone-aware dispatch cutoff notice | None |
| `[swac_stock_status]` | Out-of-stock safety net indicator | None |
| `[swac_missing_images_finder]` | Low-memory catalog audit report for missing images | `limit` (default: `200`) |

### Module 1: Explore Directory (`modules/explore/`)
| Shortcode | Purpose | Key Attributes |
|:---|:---|:---|
| `[swac_explore_brands]` | Alphabetical brand directory grid with live search | `hide_empty`, `orderby`, `order`, `image_size` |
| `[swac_categories_grid]` | Category explorer grid under a parent category slug | `parent_slug` (required), `hide_empty`, `orderby`, `order`, `image_size` |
| `[swac_directory_search]` | Real-time client-side live search input for directory grids | `placeholder` |

### Module 2: Pharmacy & Clinical Chemistry (`modules/pharmacy/`)
| Shortcode | Purpose | Key Attributes |
|:---|:---|:---|
| `[swac_generic_box]` | Clinical generic alternate comparison & savings box | None |
| `[swac_composition_tag]` | Clickable medicine active pharmaceutical salt tag | None |
| `[swac_rx_badge]` | Prescription required regulatory badge | None |
| `[swac_size_chart]` | Diaper & apparel size chart accordion | None |
| `[swac_explore_salts]` | Alphabetical chemical composition directory grid | `columns`, `hide_empty`, `orderby`, `order` |

### Module 3: Wholesale & B2B Trade (`modules/wholesale/`)
| Shortcode | Purpose | Key Attributes |
|:---|:---|:---|
| `[swac_distributor_portal]` | Frontend mobile-first distributor workspace (rates, stock, PINs, CSV) | None (Secured by Master PIN / Admin) |

---

# PART I: BASE LAYER SHORTCODES

The Base Layer contains universal storefront elements, single-product commerce controls, and catalog maintenance diagnostics.

---

## 1. Universal & Call-to-Action Shortcodes

### `[swac_button]`

- **Purpose**: Displays a styled, high-converting WhatsApp action button with a pre-filled message.
- **Attributes**:
    - `text` (string): Button label. Default: `'Order on WhatsApp'`.
    - `message` (string): Pre-filled text passed to WhatsApp. Default: `'Hello ' . get_bloginfo('name') . '.'`.
    - `fullwidth` (`'yes'` | `'no'`): Toggles 100% full-width block expansion. Default: `'no'`.
    - `icon` (`'yes'` | `'no'`): Toggles rendering of the WhatsApp SVG icon. Set to `'no'` when providing custom emoji or icon prefixes in `text`. Default: `'yes'`.
- **Examples**:
  ```text
  [swac_button text="Chat with Us" message="I have a question about my order." fullwidth="yes"]
  ```
  ```text
  [swac_button icon="no" text="📸 Send Prescription Photo" message="Hi, I am attaching my prescription."]
  ```

---

## 2. Single Product Commerce Controls

### `[swac_price_block]`

- **Purpose**: Renders the authoritative price block with real-time regular price strike and percentage discount badge.
- **Attributes**:
    - `context` (`'single'` | `'card'`): Chooses between hero single product typography or compact archive card typography. Default: `'single'`.
- **Example**:
  ```text
  [swac_price_block context="single"]
  ```

### `[swac_pack_size]`

- **Purpose**: Displays the product's packaging unit badge (e.g. "10 Tablets", "100 ml") from the `pack_size` taxonomy.
- **Attributes**: None.
- **Example**:
  ```text
  [swac_pack_size]
  ```

### `[swac_price_pack_hierarchy]`

- **Purpose**: Unified hero container bundling both the price block (`[swac_price_block]`) and pack size badge (`[swac_pack_size]`).
- **Attributes**:
    - `context` (`'single'` | `'card'`): Typography styling mode. Default: `'single'`.
- **Example**:
  ```text
  [swac_price_pack_hierarchy context="single"]
  ```

### `[swac_action_row]`

- **Purpose**: Single product Add to Order row containing quantity increment/decrement controls and the WhatsApp add button.
- **Attributes**: None.
- **Example**:
  ```text
  [swac_action_row]
  ```

### `[swac_delivery_notice]`

- **Purpose**: Displays a live, timezone-aware delivery cutoff notice based on the configured delivery schedule, complete with dynamic calendar date stamping `(j M)`.
    - **Morning Window** (Mon–Sat < 12:00 PM): `"Order before 12 noon for same-day delivery"`
    - **Weekday Afternoon Window** (Mon–Fri ≥ 12:00 PM): `"Order now to receive it by tomorrow (6 Oct)"`
    - **Weekend Window** (Sat ≥ 12:00 PM or Sun): `"Order now to get it by Monday (5 Oct)"`
- **Attributes**: None.
- **Example**:
  ```text
  [swac_delivery_notice]
  ```

### `[swac_stock_status]`

- **Purpose**: Fail-safe out-of-stock indicator that displays reliably even in custom page builder templates.
- **Attributes**: None.
- **Example**:
  ```text
  [swac_stock_status]
  ```

---

## 3. Catalog Maintenance & Diagnostics

### `[swac_missing_images_finder]`

- **Purpose**: Generates a low-memory catalog diagnostic report in the WordPress admin or a private frontend page, identifying published products lacking featured thumbnails, sorted with in-stock products first.
- **Access**: Restricted strictly to users with `manage_woocommerce` capability.
- **Attributes**:
    - `limit` (int): Maximum items to report. Default: `200`. Max: `1000`.
- **Example**:
  ```text
  [swac_missing_images_finder limit="500"]
  ```

---

# PART II: MODULAR SUBSYSTEMS

The following shortcodes belong to optional, specialized modules located under `modules/`. Each module can be toggled via `features.php`.

---

## Module 1: Explore Directory Subsystem (`modules/explore/`)

Provides high-speed catalog directory exploration for brands and categories with 0-dependency client-side live filtering.

### `[swac_explore_brands]`

- **Purpose**: Alphabetical directory grid of all product brands with live search and term counters.
- **Attributes**:
    - `hide_empty` (bool): Whether to hide brands with no products. Default: `true`.
    - `orderby` (string): Field to sort terms by (`'name'`, `'count'`). Default: `'name'`.
    - `order` (string): Sort order (`'ASC'`, `'DESC'`). Default: `'ASC'`.
    - `image_size` (string): Image attachment size for brand logos. Default: `'medium'`.
- **Example**:
  ```text
  [swac_explore_brands hide_empty="false" orderby="name" order="ASC"]
  ```

### `[swac_categories_grid]`

- **Purpose**: Displays child categories under a specified parent category slug as an image grid.
- **Attributes**:
    - `parent_slug` (string, required): Slug of parent category.
    - `hide_empty` (bool): Whether to hide empty child categories. Default: `true`.
    - `orderby` (string): Field to sort terms by (`'name'`, `'count'`). Default: `'name'`.
    - `order` (string): Sort direction (`'ASC'`, `'DESC'`). Default: `'ASC'`.
    - `image_size` (string): Thumbnail attachment size. Default: `'medium'`.
- **Example**:
  ```text
  [swac_categories_grid parent_slug="medicines" hide_empty="true"]
  ```

### `[swac_directory_search]`

- **Purpose**: High-speed client-side search box that filters `.swac-brand-card` and `.swac-category-card` elements on the page in real time without server roundtrips.
- **Attributes**:
    - `placeholder` (string): Search input placeholder text. Default: `'Search...'`.
- **Example**:
  ```text
  [swac_directory_search placeholder="Search brands or categories..."]
  ```

---

## Module 2: Pharmacy Subsystem (`modules/pharmacy/`)

Dedicated to clinical pharmacy, chemical compositions, and healthcare regulatory requirements.

### `[swac_generic_box]`

- **Purpose**: Verified generic clinical alternate comparison box, showing active chemical salt formula match and savings calculation.
- **Attributes**: None.
- **Example**:
  ```text
  [swac_generic_box]
  ```

### `[swac_composition_tag]`

- **Purpose**: Renders the active chemical salt formula badge linked to its composition archive.
- **Attributes**: None.
- **Example**:
  ```text
  [swac_composition_tag]
  ```

### `[swac_rx_badge]`

- **Purpose**: Prescription Required regulatory notice badge for prescription medications.
- **Attributes**: None.
- **Example**:
  ```text
  [swac_rx_badge]
  ```

### `[swac_size_chart]`

- **Purpose**: Diaper and apparel sizing accordion guide.
- **Attributes**: None.
- **Example**:
  ```text
  [swac_size_chart]
  ```

### `[swac_explore_salts]`

- **Purpose**: Alphabetical chemical composition directory grid with live client-side filtering.
- **Attributes**:
    - `columns` (int): Number of grid columns (1 to 6). Default: `4`.
    - `hide_empty` (bool): Whether to hide compositions without products. Default: `false`.
    - `orderby` (string): Sort field (`'name'`, `'count'`). Default: `'name'`.
    - `order` (string): Sort direction (`'ASC'`, `'DESC'`). Default: `'ASC'`.
- **Example**:
  ```text
  [swac_explore_salts columns="4" hide_empty="false"]
  ```

---

## Module 3: Wholesale & B2B Trade Subsystem (`modules/wholesale/`)

Empowers stockists and distributors with counter-speed rate management, wholesale carton ordering, and mobile administrative controls.

### `[swac_distributor_portal]`

- **Aliases**: `[swac_frontend_admin]`
- **Purpose**: Embeds a zero-admin, mobile-friendly Frontend Distributor Desk on any Breakdance or theme page (e.g. `/distributor/`). Enables wholesalers, sales reps, and dispatch clerks to:
    1. **Dual-Role Access Control (Owner vs Staff)**:
        - **Owner**: Authenticated via Master PIN or logged-in WordPress Administrator. Full control over rates (PTR), Retail MRP, MOQ, carton packs, promotional schemes, fast product photo creation, retailer directories, store settings, and CSV synchronization.
        - **Staff**: Authenticated via shared Staff PIN (`swac_b2b_staff_pin`). Strictly limited to toggling stock pills (`[ 🟢 IN STOCK ]` / `[ 🔴 OUT OF STOCK ]`) and catalog search. All pricing, scheme, MOQ inputs, and administrative tabs are disabled and hidden.
    2. **Counter-Speed Quick-Update Grid**: Tab through Rate (PTR), Retail MRP, MOQ, and Scheme inputs with auto-save on blur, plus 1-tap Stock Pill toggling executing in `<100ms` via `/desk/fast-row-update`.
    3. **Shorthand Scheme Notation**: Input single promotional strings (`"10+1"`, `"12+1 FREE"`, `"10:2%"`, `"2% CD"`) automatically parsed into structured wholesale schema.
    4. **Fast Product Entry**: Add new simple products via a single-screen mobile form with direct phone camera / gallery photo upload (Owner only).
    5. **Retailer Account Management**: View, approve, edit, and delete wholesale retailer accounts and assign 4-digit PINs (Owner only).
    6. **Store & Delivery Settings**: Manage WhatsApp business number, store name, city focus, free shipping cutoff, flat delivery charge, order disclaimer, Master PIN, and Staff PIN directly from mobile with zero `wp-admin` access (Owner only).
    7. **1-Click CSV Synchronization**: UTF-8 BOM CSV catalog export (Excel-compatible) and bulk rate/stock CSV import (Owner only).
    8. **Multi-Session Security & Quick Lock**: Protected by Master/Staff PIN with IP rate-limiting, 30-day sliding token lifespan across multiple mobile warehouse devices, and a 1-tap `🔒 Lock` button in the header.
- **Access**: Authorized automatically for logged-in WordPress administrators, or secured via Master PIN / Staff PIN prompt for phone access without WordPress login.
- **Example**:
  ```text
  [swac_distributor_portal]
  ```
