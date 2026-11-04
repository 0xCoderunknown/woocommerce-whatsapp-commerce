# Security Policy

## Supported Versions

| Version | Supported |
|:--------|:----------|
| 1.5.x   | ✅ Yes     |
| 1.4.x   | ✅ Yes     |
| 1.3.x   | ✅ Yes     |
| < 1.3.0 | ❌ No      |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you believe you have found a security vulnerability in Stateless WhatsApp Commerce, please report it privately:

1. **GitHub Private Vulnerability Reporting**: Use the [Security Advisories](../../security/advisories/new) feature on
   this repository to submit a report confidentially.
2. **Email**: If you are unable to use GitHub's private reporting, you may open a GitHub issue with only a brief
   description (no details) and request private contact.

Please include:

- A description of the vulnerability and its potential impact.
- The affected version(s).
- Steps to reproduce the issue.
- Any relevant proof-of-concept code (if applicable).

## Response Timeline

- **Acknowledgement**: We aim to acknowledge reports within **72 hours**.
- **Assessment**: We will assess severity and confirm or dispute the report within **7 days**.
- **Fix**: Critical and high-severity issues will be patched as quickly as possible. We will coordinate disclosure
  timing with the reporter.

## Scope

This plugin is a frontend-heavy, zero-session WooCommerce extension. Key areas of security concern include:

- Input sanitization and output escaping in PHP templates
- `localStorage` cart data handling in `wa_cart.js`
- WhatsApp URL construction and customer-entered data encoding
- Capability checks on admin-only shortcodes and tools
- Master PIN authentication and SHA-256 tokens in `modules/wholesale/`
- `$wpdb` query safety in the sitemap generator and uninstall routine

## Out of Scope

- Vulnerabilities in WordPress core, WooCommerce, or third-party plugins
- Vulnerabilities in services external to this plugin (WhatsApp, Meta APIs)
- Issues that require physical access to the server
