<?php
/**
 * 🛠️ ADMIN TEMPLATE: MISSING PRODUCT IMAGES AUDIT REPORT
 *
 * View template for the [swac_missing_images_finder] report table.
 *
 * @var array $audit_data List of products missing thumbnails.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="swac-image-audit-wrap wa-image-audit-wrap"
     style="padding:20px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; max-width:1100px; margin:20px auto; font-family:system-ui, -apple-system, sans-serif;">
    <h3 style="margin-top:0; color:#0f172a; font-size:20px;">
        <?php esc_html_e('⚠️ Product Image Audit Report', 'stateless-wa-commerce'); ?>
    </h3>
    <p style="color:#64748b; margin-bottom:20px; font-size:15px;">
        <?php
        echo sprintf(
        /* translators: %d: count of products missing images */
            esc_html__('Found %d items missing a featured thumbnail. Sorted by In Stock items first.', 'stateless-wa-commerce'),
            count($audit_data)
        );
        ?>
    </p>
    <table style="width:100%; border-collapse:collapse; font-size:14px; text-align:left;">
        <thead>
        <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
            <th style="padding:10px 12px;"><?php esc_html_e('ID', 'stateless-wa-commerce'); ?></th>
            <th style="padding:10px 12px;"><?php esc_html_e('SKU', 'stateless-wa-commerce'); ?></th>
            <th style="padding:10px 12px;"><?php esc_html_e('Product Name', 'stateless-wa-commerce'); ?></th>
            <th style="padding:10px 12px;"><?php esc_html_e('Stock Status', 'stateless-wa-commerce'); ?></th>
            <th style="padding:10px 12px;"><?php esc_html_e('Action', 'stateless-wa-commerce'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($audit_data as $row) : ?>
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:10px 12px; color:#64748b;"><?php echo esc_html($row['id']); ?></td>
                <td style="padding:10px 12px; font-family:monospace;"><?php echo wp_kses_post($row['sku']); ?></td>
                <td style="padding:10px 12px; font-weight:500;">
                    <a href="<?php echo esc_url($row['edit_url'] ?: '#'); ?>" target="_blank"
                       style="color:#0284c7; text-decoration:none;">
                        <?php echo esc_html($row['name']); ?>
                    </a>
                </td>
                <td style="padding:10px 12px;">
                    <?php if ($row['stock_status'] === 'instock') : ?>
                        <span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600; background:#dcfce7; color:#15803d;">
                            <?php esc_html_e('In Stock', 'stateless-wa-commerce'); ?>
                        </span>
                    <?php else : ?>
                        <span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600; background:#f1f5f9; color:#64748b;">
                            <?php esc_html_e('Out of Stock', 'stateless-wa-commerce'); ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td style="padding:10px 12px;">
                    <a href="<?php echo esc_url($row['edit_url'] ?: '#'); ?>" target="_blank"
                       style="display:inline-block; padding:4px 10px; background:#0284c7; color:#fff; border-radius:4px; text-decoration:none; font-size:12px; font-weight:500;">
                        <?php esc_html_e('Upload Image', 'stateless-wa-commerce'); ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
