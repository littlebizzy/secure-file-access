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

// shortcode to display a download link for a product if the user has an active subscription to specific plans
add_shortcode('product_file_access', function ($atts) {
    // parse attributes
    $atts = shortcode_atts([
        'product_id' => 0, // the product ID of the downloadable product
    ], $atts, 'product_file_access');

    $product_id = intval($atts['product_id']);
    if (!$product_id) {
        return '<p>Invalid product ID.</p>'; // error if no product ID is provided
    }

    // define acceptable subscription plan IDs
    $acceptable_subscription_ids = [101, 102, 103]; // replace with your WooCommerce Subscription IDs

    // check if the user is logged in
    if (!is_user_logged_in()) {
        return '<p>Please log in to access the download.</p>';
    }

    $user_id = get_current_user_id();

    // check if the user has an active subscription to any of the acceptable plans
    $has_access = false;
    foreach ($acceptable_subscription_ids as $subscription_id) {
        if (wcs_user_has_subscription($user_id, $subscription_id, 'active')) {
            $has_access = true;
            break;
        }
    }

    if (!$has_access) {
        return '<p>You need an active subscription to one of our plans to access this download.</p>';
    }

    // get the product
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_downloadable()) {
        return '<p>This product is not available for download.</p>';
    }

    // get download URLs
    $downloads = $product->get_downloads();
    if (empty($downloads)) {
        return '<p>No downloads available for this product.</p>';
    }

    // display download links
    $output = '<p>Download your files:</p><ul>';
    foreach ($downloads as $download) {
        $output .= sprintf(
            '<li><a href="%s" target="_blank">%s</a></li>',
            esc_url($download['file']),
            esc_html($download['name'])
        );
    }
    $output .= '</ul>';

    return $output;
});

// Ref: ChatGPT
