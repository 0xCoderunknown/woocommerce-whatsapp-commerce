<?php
/**
 * 📄 WHOLESALE REST: CSV EXPORT & IMPORT CONTROLLER
 *
 * Streams catalog rates as UTF-8 BOM CSV and processes bulk CSV rate updates.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Streams wholesale product rate sheet as CSV with UTF-8 BOM.
 *
 * @param WP_REST_Request $request
 */
function swac_rest_desk_export_csv(WP_REST_Request $request)
{
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ];
    $query = new WP_Query($args);

    $filename = 'wholesale-rates-' . gmdate('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Output UTF-8 BOM so Excel opens with proper UTF-8 support
    fputs($out, "\xEF\xBB\xBF");

    // CSV Header row
    fputcsv($out, ['ID', 'SKU', 'Product Name', 'Brand', 'MRP', 'PTR', 'Carton Pack', 'Scheme Type', 'In Stock']);

    $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';

    foreach ($query->posts as $post) {
        $product = wc_get_product($post->ID);
        if (!$product) {
            continue;
        }

        $b2b = function_exists('swac_b2b_get_product_data')
            ? swac_b2b_get_product_data($product)
            : ['ptr' => (float)$product->get_price(), 'mrp' => (float)$product->get_regular_price(), 'carton_pack' => 0, 'scheme_type' => 'none'];

        $brand_terms = taxonomy_exists($brand_tax) ? get_the_terms($product->get_id(), $brand_tax) : [];
        $brand = (!empty($brand_terms) && !is_wp_error($brand_terms)) ? current($brand_terms)->name : '';

        fputcsv($out, [
            $product->get_id(),
            $product->get_sku(),
            $product->get_name(),
            $brand,
            $b2b['mrp'],
            $b2b['ptr'],
            $b2b['carton_pack'],
            $b2b['scheme_type'],
            $product->is_in_stock() ? 'yes' : 'no',
        ]);
    }

    fclose($out);
    exit;
}

/**
 * Processes bulk rate and stock update from uploaded CSV file.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function swac_rest_desk_import_csv(WP_REST_Request $request): WP_REST_Response
{
    $files = $request->get_file_params();
    if (empty($files['file']) || empty($files['file']['tmp_name'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('No CSV file uploaded.', 'stateless-wa-commerce'),
        ], 400);
    }

    $filename = $files['file']['name'] ?? '';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== 'csv' && $ext !== 'txt') {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Invalid file format. Please upload a valid CSV file.', 'stateless-wa-commerce'),
        ], 400);
    }

    $tmp_file = $files['file']['tmp_name'];
    $handle = fopen($tmp_file, 'r');
    if ($handle === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Could not open CSV file.', 'stateless-wa-commerce'),
        ], 500);
    }

    // Skip UTF-8 BOM if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return new WP_REST_Response([
            'success' => false,
            'message' => __('CSV file is empty.', 'stateless-wa-commerce'),
        ], 400);
    }

    // Normalize header mapping
    $col_map = [];
    foreach ($header as $idx => $col_name) {
        $norm = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]/', '', $col_name)));
        $col_map[$norm] = $idx;
    }

    $updated_count = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if (empty($row) || count(array_filter($row)) === 0) {
            continue;
        }

        $id = isset($col_map['id']) ? absint($row[$col_map['id']] ?? 0) : 0;
        $sku = isset($col_map['sku']) ? sanitize_text_field((string)($row[$col_map['sku']] ?? '')) : '';

        $product = null;
        if ($id > 0) {
            $product = wc_get_product($id);
        }
        if (!$product && !empty($sku)) {
            $found_id = wc_get_product_id_by_sku($sku);
            if ($found_id) {
                $product = wc_get_product($found_id);
            }
        }

        if (!$product) {
            continue;
        }

        $product_id = $product->get_id();
        $changed = false;

        // 1. Regular Price / MRP
        if (isset($col_map['mrp']) && isset($row[$col_map['mrp']])) {
            $mrp = (float)$row[$col_map['mrp']];
            if ($mrp > 0) {
                $product->set_regular_price($mrp);
                $changed = true;
            }
        }

        // 2. PTR / Wholesale Rate
        if (isset($col_map['ptr']) && isset($row[$col_map['ptr']])) {
            $ptr = (float)$row[$col_map['ptr']];
            update_post_meta($product_id, '_swac_b2b_ptr', $ptr > 0 ? (string)$ptr : '');
            $changed = true;
        }

        // 3. Carton Pack
        if (isset($col_map['cartonpack']) && isset($row[$col_map['cartonpack']])) {
            $carton = absint($row[$col_map['cartonpack']]);
            update_post_meta($product_id, '_swac_b2b_carton_pack', $carton);
            $changed = true;
        }

        // 4. Stock Status
        if (isset($col_map['instock']) && isset($row[$col_map['instock']])) {
            $raw_stock = strtolower(trim((string)$row[$col_map['instock']]));
            $is_stock = in_array($raw_stock, ['1', 'yes', 'true', 'instock', 'in stock', 'y'], true);
            $product->set_stock_status($is_stock ? 'instock' : 'outofstock');
            $changed = true;
        }

        if ($changed) {
            $product->save();
            if (function_exists('swac_purge_product_cache')) {
                swac_purge_product_cache($product_id);
            }
            $updated_count++;
        }
    }

    fclose($handle);

    return new WP_REST_Response([
        'success' => true,
        'count' => $updated_count,
        'message' => sprintf(
        /* translators: %d: number of updated products */
            __('Successfully updated %d products from CSV.', 'stateless-wa-commerce'),
            $updated_count
        ),
    ], 200);
}

