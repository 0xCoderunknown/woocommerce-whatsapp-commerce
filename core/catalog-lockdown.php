<?php
/**
 * 🔒 CORE: MASTER CATALOG LOCKDOWN & PERFORMANCE BOOTSTRAP
 *
 * Coordinates:
 * 1. core/session-engine.php: Inviolable zero-session engine, cart fragment intercept, and 302 route diverters.
 * 2. core/system-cleanup.php: Optional bloat cleanups, script dequeues, and admin sanitization.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Immutable Zero-Session Engine
require_once __DIR__ . '/session-engine.php';

// 2. Tunable System Cleanups & Bloat Removal
require_once __DIR__ . '/system-cleanup.php';
