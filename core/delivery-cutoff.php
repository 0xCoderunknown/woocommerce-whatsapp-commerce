<?php
/**
 * ⚡ CORE: TIMEZONE-AWARE DELIVERY CUTOFF ENGINE
 *
 * Evaluates store dispatch windows using the WordPress site timezone (wp_timezone()).
 * Schedulers and notices are completely customizable via filter hooks.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_get_delivery_cutoff_message')) {
    /**
     * Evaluates the current dispatch cutoff window and returns the localized customer notice.
     *
     * @param int|null $timestamp Optional unix timestamp (defaults to current time).
     * @return string Delivery cutoff notice copy.
     */
    function swac_get_delivery_cutoff_message(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

        if (function_exists('wp_date')) {
            $day = (int)wp_date('N', $timestamp, $tz);  // 1 (Monday) to 7 (Sunday)
            $hour = (int)wp_date('G', $timestamp, $tz); // 0 to 23
        } else {
            $dt = new DateTimeImmutable('@' . $timestamp);
            $dt = $dt->setTimezone($tz);
            $day = (int)$dt->format('N');
            $hour = (int)$dt->format('G');
        }

        $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
        $cutoff_hour = (int)($cfg['cutoff_hour'] ?? (defined('SWAC_DELIVERY_CUTOFF_HOUR') ? SWAC_DELIVERY_CUTOFF_HOUR : 12));

        $default_message = '';

        // 1. Weekend Window: Saturday cutoff through Sunday 11:59:59 PM (midnight)
        if ($day === 7 || ($day === 6 && $hour >= $cutoff_hour)) {
            $days_to_monday = ($day === 7) ? 1 : 2;
            $target_ts = $timestamp + ($days_to_monday * 86400);
            $monday_date = function_exists('wp_date')
                ? wp_date('j M', $target_ts, $tz)
                : (new DateTimeImmutable('@' . $target_ts))->setTimezone($tz)->format('j M');
            $default_message = sprintf(__('Order now to get it by Monday (%s)', 'stateless-wa-commerce'), $monday_date);
        } // 2. Morning Cutoff Window: Monday through Saturday, before cutoff hour
        elseif ($hour < $cutoff_hour) {
            $cutoff_display = ($cutoff_hour === 12) ? __('12 noon', 'stateless-wa-commerce') : sprintf(__('%d:00', 'stateless-wa-commerce'), $cutoff_hour);
            $default_message = sprintf(__('Order before %s for same-day delivery', 'stateless-wa-commerce'), $cutoff_display);
        } // 3. Weekday Afternoon/Evening Window: Monday through Friday, after cutoff hour
        else {
            $target_ts = $timestamp + 86400;
            $tomorrow_date = function_exists('wp_date')
                ? wp_date('j M', $target_ts, $tz)
                : (new DateTimeImmutable('@' . $target_ts))->setTimezone($tz)->format('j M');
            $default_message = sprintf(__('Order now to receive it by tomorrow (%s)', 'stateless-wa-commerce'), $tomorrow_date);
        }

        /**
         * Filter the delivery cutoff message.
         *
         * @param string $message Evaluated message.
         * @param int $day Day of week (1-7).
         * @param int $hour Hour of day (0-23).
         * @param int $timestamp Current timestamp.
         */
        return (string)apply_filters('swac_delivery_cutoff_message', $default_message, $day, $hour, $timestamp);
    }
}
