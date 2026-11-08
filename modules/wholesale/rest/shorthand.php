<?php
/**
 * 🏷️ WHOLESALE REST: SCHEME SHORTHAND PARSER
 *
 * Bi-directional parser converting notation ("10+1", "10:2%", "2% CD") into structured meta.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parses shorthand scheme text into structured scheme configuration.
 *
 * Supported formats:
 * - Free goods: "10+1", "12+1 FREE", "12 + 1" => free_goods (buy=10, free=1)
 * - Tier discount: "10:2%", "10:2%, 25:5%" => tier_discount
 * - Cash discount: "2% CD", "2%CD" => cash_discount (cd=2)
 * - Empty / none: "" or "none" or "-" => none
 * - Anything else: => custom
 *
 * @param string $shorthand Shorthand notation.
 * @return array Parsed configuration.
 */
function swac_parse_scheme_shorthand(string $shorthand): array
{
    $clean = trim($shorthand);
    if ($clean === '' || strcasecmp($clean, 'none') === 0 || $clean === '-') {
        return [
            'scheme_type' => 'none',
        ];
    }

    // Normalize words "plus" or "and" into "+"
    $normalized = preg_replace('/\b(?:plus|and)\b/i', '+', $clean);

    // Pattern 1: Free goods: X+Y (with optional free/goods/bonus descriptor)
    if (preg_match('/^\s*(\d+)\s*\+\s*(\d+)/i', $normalized, $matches)) {
        return [
            'scheme_type' => 'free_goods',
            'scheme_buy' => (int)$matches[1],
            'scheme_free' => (int)$matches[2],
        ];
    }

    // Pattern 2: Tier discount: X:Y% (single or multiple comma-separated slabs)
    if (preg_match('/^\s*\d+\s*:\s*\d+(?:\.\d+)?%/i', $clean)) {
        return [
            'scheme_type' => 'tier_discount',
            'scheme_tiers' => sanitize_text_field($clean),
        ];
    }

    // Pattern 3: Cash discount / Flat Percentage: X% or X% CD / OFF / SCHEME
    if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*%/i', $clean, $matches)) {
        return [
            'scheme_type' => 'cash_discount',
            'scheme_cd' => (float)$matches[1],
        ];
    }

    // Pattern 4: Custom tag / Informational Note
    return [
        'scheme_type' => 'custom',
        'scheme_custom' => sanitize_text_field($clean),
    ];
}

/**
 * Generates shorthand string representation from stored product scheme meta.
 *
 * @param int $product_id Product ID.
 * @return string Shorthand representation.
 */
function swac_get_scheme_shorthand(int $product_id): string
{
    $type = (string)get_post_meta($product_id, '_swac_b2b_scheme_type', true);
    if ($type === 'free_goods') {
        $buy = get_post_meta($product_id, '_swac_b2b_scheme_buy', true);
        $free = get_post_meta($product_id, '_swac_b2b_scheme_free', true);
        return ($buy !== '' && $free !== '') ? "{$buy}+{$free}" : '';
    }
    if ($type === 'tier_discount') {
        return (string)get_post_meta($product_id, '_swac_b2b_scheme_tiers', true);
    }
    if ($type === 'cash_discount') {
        $cd = get_post_meta($product_id, '_swac_b2b_scheme_cd', true);
        return ($cd !== '' && (float)$cd > 0) ? "{$cd}% CD" : '';
    }
    if ($type === 'custom') {
        return (string)get_post_meta($product_id, '_swac_b2b_scheme_custom', true);
    }
    return '';
}

