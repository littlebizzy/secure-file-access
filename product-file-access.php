<?php
/*
Plugin Name: Product File Access
Plugin URI: https://www.littlebizzy.com/plugins/product-file-access
Description: Provides subscription-based access to downloadable files using a shortcode.
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
        update_option('pfa_message_no_subscription', sanitize_text_field($_POST['pfa_message_no_subscription']));
        update_option('pfa_message_invalid_url', sanitize_text_field($_POST['pfa_message_invalid_url']));
        update_option('pfa_message_not_logged_in', sanitize_text_field($_POST['pfa_message_not_logged_in']));
        update_option('pfa_message_no_sub_ids', sanitize_text_field($_POST['pfa_message_no_sub_ids']));
        update_option('pfa_message_invalid_shortcode', sanitize_text_field($_POST['pfa_message_invalid_shortcode']));
        update_option('pfa_default_subscription_ids', sanitize_text_field($_POST['pfa_default_subscription_ids']));
        update_option('pfa_default_roles', sanitize_text_field($_POST['pfa_default_roles']));
        update_option('pfa_default_label', sanitize_text_field($_POST['pfa_default_label']));
        echo '<div class="updated"><p><strong>Settings saved successfully.</strong></p></div>';
    }

    // get current settings
    $message_no_subscription = get_option('pfa_message_no_subscription', '<strong>You need an active subscription or a valid role to access this file.</strong>');
    $message_invalid_url = get_option('pfa_message_invalid_url', '<strong>Invalid file URL provided.</strong>');
    $message_not_logged_in = get_option('pfa_message_not_logged_in', '<strong>Please log in to access this file.</strong>');
    $message_no_sub_ids = get_option('pfa_message_no_sub_ids', '<strong>No subscription IDs provided.</strong>');
    $message_invalid_shortcode = get_option('pfa_message_invalid_shortcode', '<strong>The shortcode is missing required attributes.</strong>');
    $default_subscription_ids = get_option('pfa_default_subscription_ids', '');
    $default_roles = get_option('pfa_default_roles', 'administrator');
    $default_label = get_option('pfa_default_label', 'Download File');

    // settings form
    ?>
    <div class="wrap">
        <h1>Product File Access Settings</h1>

        <!-- Tabs Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="#error-messages" class="nav-tab nav-tab-active">Error Messages</a>
            <a href="#woocommerce-settings" class="nav-tab">WooCommerce Settings</a>
        </h2>

        <!-- Tab Contents -->
        <form method="POST">
            <div id="error-messages" class="tab-content" style="display: block;">
                <h3>Error Messages</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Message: No Access</th>
                        <td><input type="text" name="pfa_message_no_subscription" value="<?php echo esc_attr($message_no_subscription); ?>" class="regular-text" style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <th scope="row">Message: Invalid File URL</th>
                        <td><input type="text" name="pfa_message_invalid_url" value="<?php echo esc_attr($message_invalid_url); ?>" class="regular-text" style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <th scope="row">Message: Not Logged In</th>
                        <td><input type="text" name="pfa_message_not_logged_in" value="<?php echo esc_attr($message_not_logged_in); ?>" class="regular-text" style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <th scope="row">Message: No Subscription IDs Provided</th>
                        <td><input type="text" name="pfa_message_no_sub_ids" value="<?php echo esc_attr($message_no_sub_ids); ?>" class="regular-text" style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <th scope="row">Message: Invalid Shortcode</th>
                        <td><input type="text" name="pfa_message_invalid_shortcode" value="<?php echo esc_attr($message_invalid_shortcode); ?>" class="regular-text" style="width: 100%;"></td>
                    </tr>
                </table>
            </div>

            <div id="woocommerce-settings" class="tab-content" style="display: none;">
                <h3>WooCommerce and Role Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Default Subscription IDs</th>
                        <td><input type="text" name="pfa_default_subscription_ids" value="<?php echo esc_attr($default_subscription_ids); ?>" class="regular-text" style="width: 100%;">
                        <p class="description">Enter comma-separated subscription IDs (e.g., 101,102,103). This will be used when no subscription IDs are specified in the shortcode.</p></td>
                    </tr>
                    <tr>
                        <th scope="row">Default Roles</th>
                        <td><input type="text" name="pfa_default_roles" value="<?php echo esc_attr($default_roles); ?>" class="regular-text" style="width: 100%;">
                        <p class="description">Enter comma-separated WordPress roles (e.g., administrator,editor). This will be used when no roles are specified in the shortcode.</p></td>
                    </tr>
                    <tr>
                        <th scope="row">Default Download Button Label</th>
                        <td><input type="text" name="pfa_default_label" value="<?php echo esc_attr($default_label); ?>" class="regular-text" style="width: 100%;">
                        <p class="description">Default label for the download button. This can be overridden using the shortcode <code>label</code> attribute.</p></td>
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
                const targetTab = this.getAttribute('href');
                document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('nav-tab-active'));
                this.classList.add('nav-tab-active');
                document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
                document.querySelector(targetTab).style.display = 'block';
            });
        });
    </script>
    <?php
}

// shortcode for file access
add_shortcode('file_access', function ($atts) {
    // get default settings
    $default_label = get_option('pfa_default_label', 'Download File');
    $message_no_subscription = get_option('pfa_message_no_subscription', '<strong>You need an active subscription or a valid role to access this file.</strong>');
    $default_subscription_ids = get_option('pfa_default_subscription_ids', '');
    $default_roles = explode(',', get_option('pfa_default_roles', 'administrator'));

    // parse shortcode attributes
    $atts = shortcode_atts([
        'url' => '', // File URL
        'label' => $default_label, // Default label
        'subscriptions' => '', // Comma-separated subscription IDs
        'roles' => '', // Comma-separated roles
    ], $atts, 'file_access');

    $url = esc_url($atts['url']);
    $label = esc_html($atts['label']);
    $subscriptions = array_filter(array_map('trim', explode(',', $atts['subscriptions'] ?: $default_subscription_ids)));
    $roles = array_filter(array_map('trim', explode(',', $atts['roles'] ?: implode(',', $default_roles))));

    if (empty($url)) {
        return "<p><strong>Invalid file URL provided.</strong></p>";
    }

    if (!is_user_logged_in()) {
        return "<p><strong>Please log in to access this file.</strong></p>";
    }

    $user_id = get_current_user_id();
    $user = wp_get_current_user();

    // Check subscriptions
    $has_subscription = false;
    foreach ($subscriptions as $subscription_id) {
        if (wcs_user_has_subscription($user_id, $subscription_id, 'active')) {
            $has_subscription = true;
            break;
        }
    }

    // Check roles
    $has_role = false;
    foreach ($roles as $role) {
        if (in_array($role, $user->roles, true)) {
            $has_role = true;
            break;
        }
    }

    // Grant access if user has a valid subscription or role
    if ($has_subscription || $has_role) {
        return sprintf('<p><a href="%s" target="_blank"><strong>%s</strong></a></p>', $url, $label);
    }

    return "<p>{$message_no_subscription}</p>";
});

// Ref: ChatGPT
