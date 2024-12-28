<?php
/*
Plugin Name: Product File Access
Plugin URI: https://www.littlebizzy.com/plugins/product-file-access
Description: Provides subscription-based or product-based access to downloadable files using a shortcode.
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

// settings page content
function pfa_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // save settings
    if (isset($_POST['pfa_save_settings'])) {
        update_option('pfa_message_no_access', sanitize_text_field($_POST['pfa_message_no_access']));
        update_option('pfa_message_invalid_url', sanitize_text_field($_POST['pfa_message_invalid_url']));
        update_option('pfa_message_not_logged_in', sanitize_text_field($_POST['pfa_message_not_logged_in']));
        update_option('pfa_message_no_sub_ids', sanitize_text_field($_POST['pfa_message_no_sub_ids']));
        update_option('pfa_message_invalid_shortcode', sanitize_text_field($_POST['pfa_message_invalid_shortcode']));
        update_option('pfa_default_subscription_ids', sanitize_text_field($_POST['pfa_default_subscription_ids']));
        update_option('pfa_default_product_ids', sanitize_text_field($_POST['pfa_default_product_ids']));
        update_option('pfa_default_roles', sanitize_text_field($_POST['pfa_default_roles']));
        update_option('pfa_default_label', sanitize_text_field($_POST['pfa_default_label']));
        echo '<div class="updated"><p><strong>Settings saved successfully.</strong></p></div>';
    }

    // get current settings
    $message_no_access = get_option('pfa_message_no_access', '<strong>You do not have access to this file.</strong>');
    $message_invalid_url = get_option('pfa_message_invalid_url', '<strong>Invalid file URL provided.</strong>');
    $message_not_logged_in = get_option('pfa_message_not_logged_in', '<strong>Please log in to access this file.</strong>');
    $message_no_sub_ids = get_option('pfa_message_no_sub_ids', '<strong>No subscription IDs provided.</strong>');
    $message_invalid_shortcode = get_option('pfa_message_invalid_shortcode', '<strong>The shortcode is missing required attributes.</strong>');
    $default_subscription_ids = get_option('pfa_default_subscription_ids', '');
    $default_product_ids = get_option('pfa_default_product_ids', '');
    $default_roles = get_option('pfa_default_roles', 'administrator');
    $default_label = get_option('pfa_default_label', 'Download File');

    // settings form
    ?>
    <div class="wrap">
        <h1>Product File Access Settings</h1>

        <!-- Tabs Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="#error-messages" class="nav-tab nav-tab-active" data-tab="error-messages">Error Messages</a>
            <a href="#woocommerce-settings" class="nav-tab" data-tab="woocommerce-settings">WooCommerce Settings</a>
        </h2>

        <!-- Tab Contents -->
        <form method="POST">
            <!-- Error Messages Tab -->
            <div id="error-messages" class="tab-content" style="display: block;">
                <table class="form-table">
                    <tr>
                        <th scope="row">Message: No Access</th>
                        <td>
                            <input type="text" name="pfa_message_no_access" value="<?php echo esc_attr($message_no_access); ?>" class="regular-text" style="width: 100%;">
                            <p class="description">This message is displayed when the user does not meet any conditions (e.g., subscription, product, or role) for accessing the file.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Message: Invalid File URL</th>
                        <td>
                            <input type="text" name="pfa_message_invalid_url" value="<?php echo esc_attr($message_invalid_url); ?>" class="regular-text" style="width: 100%;">
                            <p class="description">This message is displayed when the file URL provided in the shortcode is invalid or not accessible.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Message: Not Logged In</th>
                        <td>
                            <input type="text" name="pfa_message_not_logged_in" value="<?php echo esc_attr($message_not_logged_in); ?>" class="regular-text" style="width: 100%;">
                            <p class="description">This message is displayed when the user is not logged in and tries to access a file that requires authentication.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Message: No Subscription IDs Provided</th>
                        <td>
                            <input type="text" name="pfa_message_no_sub_ids" value="<?php echo esc_attr($message_no_sub_ids); ?>" class="regular-text" style="width: 100%;">
                            <p class="description">This message is displayed when no subscription IDs are configured in the shortcode or default settings.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Message: Invalid Shortcode</th>
                        <td>
                            <input type="text" name="pfa_message_invalid_shortcode" value="<?php echo esc_attr($message_invalid_shortcode); ?>" class="regular-text" style="width: 100%;">
                            <p class="description">This message is displayed when the shortcode is missing required attributes, such as a file URL.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- WooCommerce Settings Tab -->
            <div id="woocommerce-settings" class="tab-content" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th scope="row">Default Subscription IDs</th>
                        <td>
                            <input type="text" name="pfa_default_subscription_ids" value="<?php echo esc_attr($default_subscription_ids); ?>" class="regular-text" style="width: 100%;">
                            <p class="description">Enter comma-separated WooCommerce subscription IDs that grant access to files. These IDs will be used when no specific subscription IDs are provided in the shortcode.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Product IDs</th>
                        <td>
                            <input type="text" name="pfa_default_product_ids" value="<?php echo esc_attr($default_product_ids); ?>" class="regular-text" style="width: 100%;">
                            <p class="description">Enter comma-separated WooCommerce product IDs for one-time purchase eligibility. These IDs will be used when no specific product IDs are provided in the shortcode.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Roles</th>
                        <td>
                            <input type="text" name="pfa_default_roles" value="<?php echo esc_attr($default_roles); ?>" class="regular-text" style="width: 100%;">
                            <p class="description">Enter comma-separated WordPress roles (e.g., administrator,editor) that grant access to files. These roles will be used when no specific roles are provided in the shortcode.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Download Button Label</th>
                        <td>
                            <input type="text" name="pfa_default_label" value="<?php echo esc_attr($default_label); ?>" class="regular-text" style="width: 100%;">
                            <p class="description">Specify the default label for the download button (e.g., "Download File"). This label can be overridden in the shortcode.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <input type="submit" name="pfa_save_settings" class="button button-primary" value="Save Changes">
            </p>
        </form>
    </div>

    <script>
        // JavaScript to toggle tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', function(event) {
                event.preventDefault();
                const targetTab = this.dataset.tab;

                // Update active tab
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
                this.classList.add('nav-tab-active');

                // Show the selected tab content and hide others
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.style.display = content.id === targetTab ? 'block' : 'none';
                });
            });
        });
    </script>
    <?php
}

// shortcode for file access
add_shortcode('file_access', function ($atts) {
    // Access logic remains unchanged from the previous version
});

// Ref: ChatGPT
