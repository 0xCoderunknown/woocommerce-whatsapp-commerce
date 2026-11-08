<?php
/**
 * ⚙️ WHOLESALE REST: STORE SETTINGS CONTROLLER
 *
 * Handles fetching and updating store identity, delivery parameters, and Master PIN.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieves current store contact and shipping essentials for Distributor Desk.
 */
function swac_rest_desk_get_settings(WP_REST_Request $request): WP_REST_Response
{
    $cfg = function_exists('swac_get_config') ? swac_get_config(true) : [];
    return new WP_REST_Response([
        'success' => true,
        'settings' => [
            'phone_number' => (string)($cfg['phone_number'] ?? ''),
            'store_name' => (string)($cfg['store_name'] ?? ''),
            'city_name' => (string)($cfg['city_name'] ?? ''),
            'free_shipping_at' => (float)($cfg['free_shipping_at'] ?? 0),
            'shipping_charge' => (float)($cfg['shipping_charge'] ?? 0),
            'order_disclaimer' => (string)($cfg['order_disclaimer'] ?? ''),
            'has_staff_pin' => !empty(get_option('swac_b2b_staff_pin', '')),
        ],
    ], 200);
}

/**
 * Saves store contact, delivery essentials, and optional Master PIN update.
 */
function swac_rest_desk_save_settings(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();

    if (isset($params['phone_number'])) {
        $raw_phone = (string)$params['phone_number'];
        $clean_phone = function_exists('swac_sanitize_phone') ? swac_sanitize_phone($raw_phone) : preg_replace('/[^0-9]/', '', $raw_phone);
        update_option('swac_store_phone', $clean_phone);
    }

    if (isset($params['store_name'])) {
        update_option('swac_store_name', sanitize_text_field((string)$params['store_name']));
    }

    if (isset($params['city_name'])) {
        update_option('swac_city_name', sanitize_text_field((string)$params['city_name']));
    }

    if (isset($params['free_shipping_at'])) {
        update_option('swac_free_shipping_at', (float)$params['free_shipping_at']);
    }

    if (isset($params['shipping_charge'])) {
        update_option('swac_shipping_charge', (float)$params['shipping_charge']);
    }

    if (isset($params['order_disclaimer'])) {
        update_option('swac_order_disclaimer', sanitize_textarea_field((string)$params['order_disclaimer']));
    }

    if (!empty($params['master_pin'])) {
        $pin = sanitize_text_field((string)$params['master_pin']);
        if (strlen($pin) >= 4) {
            update_option('swac_b2b_master_pin', $pin, false);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Master PIN must be at least 4 digits.', 'stateless-wa-commerce'),
            ], 400);
        }
    }

    if (isset($params['staff_pin'])) {
        $st_pin = sanitize_text_field((string)$params['staff_pin']);
        if ($st_pin === '') {
            delete_option('swac_b2b_staff_pin');
        } elseif (strlen($st_pin) >= 4) {
            update_option('swac_b2b_staff_pin', $st_pin, false);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Staff PIN must be at least 4 digits.', 'stateless-wa-commerce'),
            ], 400);
        }
    }

    if (isset($params['salesman_pin'])) {
        $sm_pin = sanitize_text_field((string)$params['salesman_pin']);
        if ($sm_pin === '') {
            delete_option('swac_b2b_salesman_pin');
        } elseif (strlen($sm_pin) >= 4) {
            update_option('swac_b2b_salesman_pin', $sm_pin, false);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Salesman PIN must be at least 4 digits.', 'stateless-wa-commerce'),
            ], 400);
        }
    }

    if (function_exists('swac_get_config')) {
        swac_get_config(true);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => __('Store settings saved successfully.', 'stateless-wa-commerce'),
    ], 200);
}

