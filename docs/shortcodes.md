# Shortcodes Documentation

This document describes all shortcodes available in **Stateless WhatsApp Commerce for WooCommerce**.
It serves as the definitive reference for page builders, theme templates, and developers.

---

## Quick Reference Index

| Primary Shortcode | Purpose | Key Attributes |
| :--- | :--- | :--- |
| `[swac_button]` | Customizable WhatsApp order/contact button | `text`, `message`, `fullwidth`, `icon` |
| `[swac_price_block]` | Hero price display (Current price, MRP strike, discount %) | `context` (`single` \| `card`) |
| `[swac_pack_size]` | Packaging unit badge (e.g. "10 Tablets", "500 ml"). | None |
| `[swac_price_pack_hierarchy]` | Unified price and pack size container | `context` (`single` \| `card`) |
| `[swac_action_row]` | Add to Order quantity controls & WhatsApp button | None |
| `[swac_delivery_notice]` | Timezone-aware dispatch cutoff notice | None |
| `[swac_stock_status]` | Single product out-of-stock safety net badge | None |
| `[swac_explore_brands]` | Alphabetical brand directory grid with live search | `hide_empty`, `orderby`, `order`, `image_size` |
| `[swac_categories_grid]` | Category explorer grid under a parent category slug | `parent_slug` (required), `hide_empty`, `orderby`, `order`, `image_size` |
| `[swac_directory_search]` | Real-time client-side live search input for directory grids | `placeholder` |
| `[swac_generic_box]` | Verified generic clinical alternate comparison & savings box | None |
| `[swac_composition_tag]` | Clickable medicine active pharmaceutical salt tag | None |
| `[swac_rx_badge]` | Prescription required regulatory badge | None |
| `[swac_size_chart]` | Diaper & apparel size chart accordion | None |
| `[swac_explore_salts]` | Alphabetical chemical composition directory grid | `columns`, `hide_empty`, `orderby`, `order` |
| [swac_missing_images_finder] | Catalog audit table finding products missing images | limit (default: 200) |
| [swac_distributor_portal] | Frontend mobile-first distributor workspace (rates, product entry, PINs) | None |

---

## 1. Universal & Component Shortcodes

### `[swac_button]`
- **Purpose**: Displays a styled, high-converting WhatsApp action button with a pre-filled message.
- **Attributes**:
  - `text` (string): Button label. Default: `'Order on WhatsApp'`.
  - `message` (string): Pre-filled text passed to WhatsApp. Default: `'Hello ' . get_bloginfo('name') . '.'`.
  - `fullwidth` (`'yes'` | `'no'`): Toggles 100% full-width block expansion. Default: `'no'`.
  - `icon` (`'yes'` | `'no'`): Toggles rendering of the WhatsApp SVG icon. Set to `'no'` when providing custom emoji or icon prefixes in `text`. Default: `'yes'`.
- **Aliases**: `[cdr_whatsapp_button]`
- **Examples**:
  ```text
  [swac_button text="Chat with Us" message="I have a question about my order." fullwidth="yes"]
  ```
  ```text
  [swac_button icon="no" text="📸 Send Prescription Photo" message="Hi, I am attaching my prescription."]
  ```

---

## 2. Single Product Shortcodes

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
- **Example**:
  ```text
  [swac_action_row]
  ```

### `[swac_delivery_notice]`
- **Purpose**: Displays a live, timezone-aware delivery cutoff notice based on the configured delivery schedule, complete with dynamic calendar date stamping `(j M)`.
  - **Morning Window** (Mon–Sat < 12:00 PM): `"Order before 12 noon for same-day delivery"`
  - **Weekday Afternoon Window** (Mon–Fri ≥ 12:00 PM): `"Order now to receive it by tomorrow (6 Oct)"`
  - **Weekend Window** (Sat ≥ 12:00 PM or Sun): `"Order now to get it by Monday (5 Oct)"`
- **Aliases**: `[g1_delivery_notice]`
- **Example**:
  ```text
  [swac_delivery_notice]
  ```

### `[swac_stock_status]`
- **Purpose**: Fail-safe out-of-stock indicator that displays reliably even in custom page builder templates.
- **Example**:
  ```text
  [swac_stock_status]
  ```

---

## 3. Directory & Exploration Shortcodes

### `[swac_explore_brands]`
- **Purpose**: Alphabetical directory grid of product brands with transient caching and real-time live search.
- **Attributes**:
  - `hide_empty` (bool): Whether to hide brands with 0 products. Default: `true`.
  - `image_size` (string): Attachment thumbnail size for brand logos. Default: `'medium'`.
  - `orderby` (string): Sort field (`'name'`, `'count'`). Default: `'name'`.
  - `order` (string): Sort direction (`'ASC'`, `'DESC'`). Default: `'ASC'`.
- **Note**: The card grid layout adapts responsively via CSS Grid auto-fill.
- **Example**:
  ```text
  [swac_explore_brands hide_empty="true" orderby="name" order="ASC"]
  ```

### `[swac_categories_grid]`
- **Purpose**: Renders child category cards under a specified parent category slug.
- **Attributes**:
  - `parent_slug` (string, required): Slug of the parent category.
  - `hide_empty` (bool): Whether to hide empty categories. Default: `false`.
  - `image_size` (string): Thumbnail image size. Default: `'medium'`.
  - `orderby` (string): Sort field (`'name'`, `'count'`). Default: `'name'`.
  - `order` (string): Sort direction (`'ASC'`, `'DESC'`). Default: `'ASC'`.
- **Example**:
  ```text
  [swac_categories_grid parent_slug="electronics" hide_empty="false"]
  ```

### `[swac_directory_search]`
- **Purpose**: Standalone real-time client-side live search input for directory grids, with instant debounced filtering.
- **Attributes**:
  - `placeholder` (string): Input field placeholder text. Default: `'Search directory...'`.
- **Example**:
  ```text
  [swac_directory_search placeholder="Filter directory..."]
  ```

---

## 4. Pharmacy & Clinical Shortcodes

### `[swac_generic_box]`
- **Purpose**: Displays a price comparison card showing verified generic clinical alternates and potential savings.
- **Attributes**: None.
- **Example**:
  ```text
  [swac_generic_box]
  ```

### `[swac_composition_tag]`
- **Purpose**: Clickable active pharmaceutical salt composition tag linking to its salt archive. Renders clean semantic markup without emoji prefixes (`<a href="..." class="pd-salt-tag">Paracetamol 500mg</a>`).
- **Aliases**: `[g1_composition_tag]`
- **Example**:
  ```text
  [swac_composition_tag]
  ```

### `[swac_rx_badge]`
- **Purpose**: Regulatory prescription badge displayed if the item is classified as Rx-only, or OTC badge for general items.
- **Attributes**: None.
- **Example**:
  ```text
  [swac_rx_badge]
  ```

### `[swac_size_chart]`
- **Purpose**: Renders HTML5 accordion size specification tables based on product categories (e.g. Adult Diapers, Baby Diapers), filterable via `swac_category_size_charts`.
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

## 5. Administrative Diagnostic Tools

### `[swac_missing_images_finder]`
- **Purpose**: Generates a low-memory catalog diagnostic report in the WordPress admin or private frontend page, identifying published products lacking featured thumbnails, sorted by in-stock products first.
- **Access**: Restricted strictly to users with `manage_woocommerce` capability.
- **Attributes**:
  - `limit` (int): Maximum items to report. Default: `200`. Max: `1000`.
- **Example**:
  ```text
  [swac_missing_images_finder limit="500"]
  ```


### `[swac_distributor_portal]`
- **Aliases**: `[swac_frontend_admin]`
- **Purpose**: Embeds a zero-admin, mobile-friendly Frontend Distributor Desk on any Breakdance or theme page (e.g. `/distributor/`). Enables wholesalers and dispatch clerks to:
  1. Tweak daily wholesale rates (PTR), MRPs, carton conversions, active schemes, and stock status in an instant-search spreadsheet table.
  2. Add new products via single-screen form with direct phone camera / gallery photo upload.
  3. Manage and approve retailer accounts and assign 4-digit PINs.
- **Access**: Authorized automatically for logged-in WordPress administrators, or secured via Master PIN prompt for phone access without WordPress login.
- **Example**:
  ```text
  [swac_distributor_portal]
  ```