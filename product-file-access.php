<?php
/*
Plugin Name: Product File Access
Plugin URI: https://www.littlebizzy.com/plugins/product-file-access
Description: Controls access to WooCommerce product files based on active subscription plans.
Version: 1.0.0
Author: LittleBizzy
Author URI: https://www.littlebizzy.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
GitHub Plugin URI: littlebizzy/product-file-access
Primary Branch: master
*/

// prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// disable wordpress.org updates for this plugin
add_filter( 'gu_override_dot_org', function( $overrides ) {
    $overrides[] = 'product-file-access/product-file-access.php';
    return $overrides;
}, 999 );

// register settings page
add_action('admin_menu', function() {
    add_options_page(
        'Product File Access Settings',
        'Product File Access',
        'manage_options',
        'product-file-access',
        'pfa_settings_page'
    );
});

// add custom CSS for wider input fields
add_action('admin_head', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'product-file-access') {
        echo '<style>
            .pfa-settings-page .regular-text {
                width: 100%;
                max-width: 100%;
            }
            .pfa-settings-page th {
                width: 20%;
            }
        </style>';
    }
});

// settings page content
function pfa_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // save settings
    if (isset($_POST['pfa_save_settings'])) {
        update_option('pfa_subscription_ids', sanitize_text_field($_POST['pfa_subscription_ids']));
        update_option('pfa_message_not_logged_in', sanitize_text_field($_POST['pfa_message_not_logged_in']));
        update_option('pfa_message_no_subscription', sanitize_text_field($_POST['pfa_message_no_subscription']));
        update_option('pfa_message_invalid_product', sanitize_text_field($_POST['pfa_message_invalid_product']));
        update_option('pfa_message_not_downloadable', sanitize_text_field($_POST['pfa_message_not_downloadable']));
        update_option('pfa_message_no_downloads', sanitize_text_field($_POST['pfa_message_no_downloads']));
        echo '<div class="updated"><p><strong>Settings saved successfully.</strong></p></div>';
    }

    // get current settings
    $subscription_ids = get_option('pfa_subscription_ids', '');
    $message_not_logged_in = get_option('pfa_message_not_logged_in', '<strong>Please log in to access this download.</strong>');
    $message_no_subscription = get_option('pfa_message_no_subscription', '<strong>You need an active subscription to access this download.</strong>');
    $message_invalid_product = get_option('pfa_message_invalid_product', '<strong>Invalid product.</strong>');
    $message_not_downloadable = get_option('pfa_message_not_downloadable', '<strong>This product is not downloadable.</strong>');
    $message_no_downloads = get_option('pfa_message_no_downloads', '<strong>No files available for this product.</strong>');

    // settings form
    ?>
    <div class="wrap pfa-settings-page">
        <h1>Product File Access Settings</h1>
        <form method="POST">
            <table class="form-table">
                <tr>
                    <th scope="row">Acceptable Subscription IDs</th>
                    <td><input type="text" name="pfa_subscription_ids" value="<?php echo esc_attr($subscription_ids); ?>" class="regular-text">
                    <p class="description">Enter comma-separated subscription IDs (e.g., 101,102,103).</p></td>
                </tr>
                <tr>
                    <th scope="row">Message: Not Logged In</th>
                    <td><input type="text" name="pfa_message_not_logged_in" value="<?php echo esc_attr($message_not_logged_in); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Message: No Active Subscription</th>
                    <td><input type="text" name="pfa_message_no_subscription" value="<?php echo esc_attr($message_no_subscription); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Message: Invalid Product</th>
                    <td><input type="text" name="pfa_message_invalid_product" value="<?php echo esc_attr($message_invalid_product); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Message: Not Downloadable</th>
                    <td><input type="text" name="pfa_message_not_downloadable" value="<?php echo esc_attr($message_not_downloadable); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Message: No Downloads Available</th>
                    <td><input type="text" name="pfa_message_no_downloads" value="<?php echo esc_attr($message_no_downloads); ?>" class="regular-text"></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="pfa_save_settings" id="submit" class="button button-primary" value="Save Changes">
            </p>
        </form>
    </div>
    <?php
}

// shortcode for product file access
add_shortcode('product_file_access', function ($atts) {
    // get settings
    $subscription_ids = explode(',', get_option('pfa_subscription_ids', ''));
    $message_not_logged_in = get_option('pfa_message_not_logged_in', '<strong>Please log in to access this download.</strong>');
    $message_no_subscription = get_option('pfa_message_no_subscription', '<strong>You need an active subscription to access this download.</strong>');
    $message_invalid_product = get_option('pfa_message_invalid_product', '<strong>Invalid product.</strong>');
    $message_not_downloadable = get_option('pfa_message_not_downloadable', '<strong>This product is not downloadable.</strong>');
    $message_no_downloads = get_option('pfa_message_no_downloads', '<strong>No files available for this product.</strong>');

    // parse attributes
    $atts = shortcode_atts(['product_id' => 0], $atts, 'product_file_access');
    $product_id = intval($atts['product_id']);

    if (!$product_id) {
        return "<p>{$message_invalid_product}</p>";
    }

    if (!is_user_logged_in()) {
        return "<p>{$message_not_logged_in}</p>";
    }

    $user_id = get_current_user_id();
    $has_access = false;

    foreach ($subscription_ids as $subscription_id) {
        if (wcs_user_has_subscription($user_id, trim($subscription_id), 'active')) {
            $has_access = true;
            break;
        }
    }

    if (!$has_access) {
        return "<p>{$message_no_subscription}</p>";
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_downloadable()) {
        return "<p>{$message_not_downloadable}</p>";
    }

    $downloads = $product->get_downloads();
    if (empty($downloads)) {
        return "<p>{$message_no_downloads}</p>";
    }

    $output = '<p><strong>Download your files:</strong></p><ul>';
    foreach ($downloads as $download) {
        $output .= sprintf('<li><a href="%s" target="_blank">%s</a></li>', esc_url($download['file']), esc_html($download['name']));
    }
    $output .= '</ul>';

    return $output;
});

// Ref: ChatGPT
