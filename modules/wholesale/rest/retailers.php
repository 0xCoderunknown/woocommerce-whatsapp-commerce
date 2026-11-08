<?php
/**
 * 👥 WHOLESALE REST: RETAILER DIRECTORY CONTROLLER
 *
 * Handles fetching, onboarding, and deleting approved wholesale retail accounts.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns approved retailers for desk management.
 * Strips secret PINs when accessed by Salesman role for route booking.
 */
function swac_rest_desk_get_retailers(WP_REST_Request $request): WP_REST_Response
{
    $retailers = function_exists('swac_get_approved_retailers') ? swac_get_approved_retailers() : [];
    $role = function_exists('swac_desk_get_auth_role') ? swac_desk_get_auth_role($request) : 'owner';

    if ($role === 'salesman') {
        $sanitized = [];
        foreach ($retailers as $r) {
            unset($r['pin']);
            $sanitized[] = $r;
        }
        return new WP_REST_Response(['success' => true, 'retailers' => $sanitized], 200);
    }

    return new WP_REST_Response(['success' => true, 'retailers' => array_values($retailers)], 200);
}

/**
 * Saves a retailer from the desk.
 */
function swac_rest_desk_save_retailer(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $saved = function_exists('swac_save_approved_retailer') ? swac_save_approved_retailer($params) : false;

    if ($saved) {
        return new WP_REST_Response(['success' => true, 'message' => __('Retailer saved.', 'stateless-wa-commerce')], 200);
    }
    return new WP_REST_Response(['success' => false, 'message' => __('Validation error. Check mobile number.', 'stateless-wa-commerce')], 400);
}

/**
 * Deletes a retailer from the desk.
 */
function swac_rest_desk_delete_retailer(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $phone = sanitize_text_field((string)($params['phone'] ?? ''));
    $deleted = function_exists('swac_delete_approved_retailer') ? swac_delete_approved_retailer($phone) : false;

    if ($deleted) {
        return new WP_REST_Response(['success' => true, 'message' => __('Retailer removed.', 'stateless-wa-commerce')], 200);
    }
    return new WP_REST_Response(['success' => false, 'message' => __('Retailer not found.', 'stateless-wa-commerce')], 404);
}

