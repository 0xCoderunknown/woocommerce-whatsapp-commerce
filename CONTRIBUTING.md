# Contributing to Stateless WhatsApp Commerce

Thank you for your interest in contributing to **Stateless WhatsApp Commerce for WooCommerce**! We are committed to
maintaining a high-performance, vendor-neutral, zero-session architecture for modern headless and catalog commerce.

This project is Free and Open Source Software (FOSS) released under the **GNU General Public License v2.0 or later (GPL-2.0+)**.

---

## Code of Conduct

Please be respectful, collaborative, and considerate in all interactions within this project.

---

## 📚 Documentation Single Source of Truth

Before creating or proposing changes, consult the authoritative documentation sources:

| Document | Purpose |
|:---|:---|
| **[docs/architecture.md](docs/architecture.md)** | Technical deep-dive on zero-session caching, client-side reconciliation, and B2B wholesale gating. |
| **[docs/shortcodes.md](docs/shortcodes.md)** | Definitive shortcode dictionary, attributes, defaults, and usage examples. |
| **[AGENTS.md](AGENTS.md)** | Complete operational manual, state schemas, REST API matrix, and maintainer invariants. |
| **[CHANGELOG.md](CHANGELOG.md)** | Release notes following Keep a Changelog in `DD-MM-YYYY` format. |

---

## Core Architectural Invariants

Any code submitted to this repository **must strictly honor** these non-negotiable invariants:

1. **Zero Session Database Writes & No Cookies**:
    - The plugin must never generate guest PHP sessions, database session writes (`wp_woocommerce_sessions`), or guest tracking cookies (`wp_woocommerce_session_*`).
    - Never call `session_start()`, never use `$_SESSION`, and never invoke `WC()->session->set()`.
2. **Full-Page Cache Compatibility**:
    - Every public catalog page, archive, and single product page must remain cache-friendly and servable from static cache by edge reverse proxies and web server caches (LiteSpeed LSCache, Nginx `fastcgi_cache`).
    - Never inject personalized user data server-side into public templates.
3. **Public FOSS & Vendor Neutrality**:
    - All business logic, store names, phone numbers, delivery zones, and currency symbols must be generic or filterable via WordPress hooks.
    - Never hardcode proprietary tokens, client phone numbers, private store names, or specific delivery routes.
4. **WooCommerce 11.x & HPOS Strict Compliance**:
    - All code must support High-Performance Order Storage (HPOS). Direct SQL queries against `wp_posts` for order data are strictly prohibited.
5. **PHP 8.2+ Strict Typing & Null Safety**:
    - Always declare return types where possible.
    - Guard against `null` inputs to string functions (`trim()`, `str_contains()`, `preg_match()`).
6. **4-Way Version Synchronization & Indian Date Format**:
    - Version bumps require updating `stateless-wa-commerce.php`, `core/config.php`, `package.json`, and `CHANGELOG.md` simultaneously.
    - Release dates in `CHANGELOG.md` must strictly follow Indian standard date format: `DD-MM-YYYY`.

---

## Development Workflow

1. **Fork & Branch**:
    - Fork the repository on GitHub.
    - Create a feature branch: `git checkout -b feature/my-new-feature`
2. **Coding Standards**:
    - Follow WordPress Coding Standards (WPCS) and WordPress VIP guidelines.
    - Ensure all output is properly escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
    - Ensure all user inputs are sanitized (`sanitize_text_field`, `sanitize_key`).
    - Keep frontend scripts strictly zero-dependency (vanilla JavaScript; no jQuery, React, or Vue).
3. **Testing & Invariant Verification**:
    - Run the automated test suite before opening a PR:
      ```bash
      # On macOS / Linux / Windows with Node
      npm test

      # On Windows (zero prerequisite launcher)
      run-tests.cmd
      ```
    - Verify that all unit tests and PHP invariant/domain lint checks pass with 0 failures.
4. **Submitting a Pull Request**:
    - Provide a clear, descriptive PR title and summary explaining the rationale and technical approach.
    - Reference any related issues or discussions.
