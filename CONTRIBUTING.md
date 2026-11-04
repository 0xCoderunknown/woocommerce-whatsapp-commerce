# Contributing to Stateless WhatsApp Commerce

Thank you for your interest in contributing to **Stateless WhatsApp Commerce for WooCommerce**! We are committed to maintaining a high-performance, vendor-neutral, zero-session architecture for modern headless and catalog commerce.

---

## Code of Conduct

Please be respectful, collaborative, and considerate in all interactions within this project.

---

## Core Architectural Invariants

Before writing or submitting code, please understand the non-negotiable architectural constraints:

1. **Zero Session Database Writes**:
   - The plugin must never generate guest PHP sessions, database session writes (`wp_woocommerce_sessions`), or guest tracking cookies.
   - Any feature requiring customer session persistence must be handled client-side (e.g. `localStorage`) or via the WhatsApp order message.
2. **Full-Page Cache Compatibility**:
   - Every public catalog page, archive, and single product page must remain cache-friendly and servable from static cache by edge reverse proxies and web server caches (LiteSpeed LSCache, Nginx).
   - Never inject personalized user data server-side into public templates.
3. **WooCommerce 11.x & HPOS Strict Compliance**:
   - All code must support High-Performance Order Storage (HPOS). Direct SQL queries against `wp_posts` for order data are strictly prohibited.
4. **PHP 8.2+ Strict Typing & Null Safety**:
   - Always declare return types where possible.
   - Guard against `null` inputs to string functions (`trim()`, `str_contains()`, `preg_match()`).
5. **No Vendor Lock-In**:
   - All business logic, store names, phone numbers, delivery zones, and currency symbols must be generic or filterable via WordPress hooks.

---

## Development Workflow

1. **Fork & Branch**:
   - Fork the repository.
   - Create a feature branch: `git checkout -b feature/my-new-feature`
2. **Coding Standards**:
   - Follow WordPress Coding Standards (WPCS) and WordPress VIP guidelines.
   - Ensure all output is properly escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
   - Ensure all user inputs are sanitized (`sanitize_text_field`, `sanitize_key`).
3. **Testing & Invariant Verification**:
   - Run the automated test suite: `npm test` or `run-tests.cmd` on Windows (executes 0-dependency client-side unit tests and PHP invariant/syntax verification).
   - Validate PHP syntax: `php -l <file>` on modified files.
   - Test compatibility with active caching plugins (LiteSpeed Cache, WP Super Cache, Cloudflare APO).
4. **Submitting a Pull Request**:
   - Provide a clear, descriptive PR title and summary explaining the rationale and technical approach.
   - Reference any related issues or discussions.
