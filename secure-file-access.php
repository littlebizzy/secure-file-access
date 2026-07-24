<?php
/*
Plugin Name: Secure File Access
Plugin URI: https://www.littlebizzy.com/plugins/secure-file-access
Description: Easy file downloads for WordPress
Version: 1.5.2
Author: LittleBizzy
Author URI: https://www.littlebizzy.com
Requires PHP: 7.0
Tested up to: 7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Update URI: false
GitHub Plugin URI: littlebizzy/secure-file-access
Primary Branch: master
Text Domain: secure-file-access
*/

// prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// load download handling
require_once __DIR__ . '/downloads.php';

// disable wordpress.org updates for this plugin
add_filter( 'gu_override_dot_org', function( $overrides ) {
	$overrides[] = 'secure-file-access/secure-file-access.php';
	return $overrides;
}, 999 );

// validate the selected settings tab
function sfa_validate_settings_tab( $tab ) {
    if ( ! is_string( $tab ) ) {
        return 'defaults';
    }

    $tab = sanitize_key( wp_unslash( $tab ) );
    $tabs = array( 'defaults', 'errors', 'github' );

    if ( ! in_array( $tab, $tabs, true ) ) {
        return 'defaults';
    }

    return $tab;
}

// register settings page
add_action( 'admin_menu', function() {
	$hook_suffix = add_options_page(
		__( 'Secure File Access Settings', 'secure-file-access' ),
		__( 'Secure File Access', 'secure-file-access' ),
		'manage_options',
		'secure-file-access',
		'sfa_settings_page'
	);

	add_action( 'load-' . $hook_suffix, 'sfa_handle_settings_actions' );
} );

// process settings actions before admin page output
function sfa_handle_settings_actions() {
    // ensure capability
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings_url = add_query_arg( 'page', 'secure-file-access', admin_url( 'options-general.php' ) );
    $active_tab = 'defaults';
    if ( isset( $_POST['sfa_active_tab'] ) ) {
        $active_tab = sfa_validate_settings_tab( $_POST['sfa_active_tab'] );
    }

    // remove github token
    if ( isset( $_POST['sfa_remove_github_token'] ) && check_admin_referer( 'sfa_remove_github_token', 'sfa_remove_nonce' ) ) {
        delete_option( 'sfa_github_token' );
        wp_safe_redirect(
            add_query_arg(
                array(
                    'sfa_notice' => 'token-removed',
                    'tab' => $active_tab,
                ),
                $settings_url
            )
        );
        exit;
    }

    // save settings
    if ( isset( $_POST['sfa_save_settings'] ) && check_admin_referer( 'sfa_save_settings', 'sfa_nonce' ) ) {

        // sanitize no access message
        $message_no_access = '';
        if ( isset( $_POST['sfa_message_no_access'] ) ) {
            $message_no_access = sanitize_text_field( wp_unslash( $_POST['sfa_message_no_access'] ) );
        }

        // sanitize invalid url message
        $message_invalid_url = '';
        if ( isset( $_POST['sfa_message_invalid_url'] ) ) {
            $message_invalid_url = sanitize_text_field( wp_unslash( $_POST['sfa_message_invalid_url'] ) );
        }

        // sanitize not logged in message
        $message_not_logged_in = '';
        if ( isset( $_POST['sfa_message_not_logged_in'] ) ) {
            $message_not_logged_in = sanitize_text_field( wp_unslash( $_POST['sfa_message_not_logged_in'] ) );
        }

        // sanitize default label
        $default_label = '';
        if ( isset( $_POST['sfa_default_label'] ) ) {
            $default_label = sanitize_text_field( wp_unslash( $_POST['sfa_default_label'] ) );
        }

        // normalize roles split on commas only
        $roles_input = '';
        if ( isset( $_POST['sfa_default_roles'] ) && is_string( $_POST['sfa_default_roles'] ) ) {
            $roles_input = wp_unslash( $_POST['sfa_default_roles'] );
        }
        $roles_parts = explode( ',', $roles_input );
        $roles_parts = array_map( 'trim', $roles_parts );
        $roles_parts = array_map( 'sanitize_key', $roles_parts ); // sanitize_key lowercases
        $roles_parts = array_values( array_unique( array_filter( $roles_parts ) ) );
        $roles_string = implode( ',', $roles_parts );

        // normalize product ids split on commas only
        $products_input = '';
        if ( isset( $_POST['sfa_default_product_ids'] ) && is_string( $_POST['sfa_default_product_ids'] ) ) {
            $products_input = wp_unslash( $_POST['sfa_default_product_ids'] );
        }
        $products_parts = explode( ',', $products_input );
        $products_parts = array_map( 'trim', $products_parts );
        $products_parts = array_map( function( $v ) { return preg_replace( '/\D+/', '', $v ); }, $products_parts );
        $products_parts = array_map( 'absint', $products_parts );
        $products_parts = array_values( array_unique( array_filter( $products_parts ) ) );
        $products_string = implode( ',', $products_parts );

        // normalize subscription ids split on commas only
        $subs_input = '';
        if ( isset( $_POST['sfa_default_subscription_ids'] ) && is_string( $_POST['sfa_default_subscription_ids'] ) ) {
            $subs_input = wp_unslash( $_POST['sfa_default_subscription_ids'] );
        }
        $subs_parts = explode( ',', $subs_input );
        $subs_parts = array_map( 'trim', $subs_parts );
        $subs_parts = array_map( function( $v ) { return preg_replace( '/\D+/', '', $v ); }, $subs_parts );
        $subs_parts = array_map( 'absint', $subs_parts );
        $subs_parts = array_values( array_unique( array_filter( $subs_parts ) ) );
        $subs_string = implode( ',', $subs_parts );

        // sanitize github token
        $github_token = '';
        if ( isset( $_POST['sfa_github_token'] ) && is_string( $_POST['sfa_github_token'] ) ) {
            $github_token = sanitize_text_field( wp_unslash( $_POST['sfa_github_token'] ) );
            $github_token = trim( $github_token );
        }

        // persist
        update_option( 'sfa_message_no_access', $message_no_access );
        update_option( 'sfa_message_invalid_url', $message_invalid_url );
        update_option( 'sfa_message_not_logged_in', $message_not_logged_in );
        update_option( 'sfa_default_product_ids', $products_string );
        update_option( 'sfa_default_subscription_ids', $subs_string );
        update_option( 'sfa_default_roles', $roles_string );
        update_option( 'sfa_default_label', $default_label );

        // save github token only when a new value is submitted
        if ( ! empty( $github_token ) ) {
            if ( false === get_option( 'sfa_github_token', false ) ) {
                add_option( 'sfa_github_token', $github_token, '', false );
            } else {
                update_option( 'sfa_github_token', $github_token, false );
            }
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'sfa_notice' => 'settings-saved',
                    'tab' => $active_tab,
                ),
                $settings_url
            )
        );
        exit;
    }
}

// settings page content
function sfa_settings_page() {
    // ensure capability
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $notice = '';
    if ( isset( $_GET['sfa_notice'] ) && is_string( $_GET['sfa_notice'] ) ) {
        $notice = sanitize_key( wp_unslash( $_GET['sfa_notice'] ) );
    }

    $active_tab = 'defaults';
    if ( isset( $_GET['tab'] ) ) {
        $active_tab = sfa_validate_settings_tab( $_GET['tab'] );
    }

    $settings_url = add_query_arg( 'page', 'secure-file-access', admin_url( 'options-general.php' ) );
    $defaults_url = add_query_arg( 'tab', 'defaults', $settings_url );
    $errors_url = add_query_arg( 'tab', 'errors', $settings_url );
    $github_url = add_query_arg( 'tab', 'github', $settings_url );

    $defaults_tab_class = 'nav-tab';
    $errors_tab_class = 'nav-tab';
    $github_tab_class = 'nav-tab';
    $defaults_display = 'none';
    $errors_display = 'none';
    $github_display = 'none';

    if ( 'errors' === $active_tab ) {
        $errors_tab_class .= ' nav-tab-active';
        $errors_display = 'block';
    } elseif ( 'github' === $active_tab ) {
        $github_tab_class .= ' nav-tab-active';
        $github_display = 'block';
    } else {
        $defaults_tab_class .= ' nav-tab-active';
        $defaults_display = 'block';
    }

	// load settings (plain text defaults)
	$message_no_access = get_option( 'sfa_message_no_access', __( 'You do not have access to this file.', 'secure-file-access' ) );
	$message_invalid_url = get_option( 'sfa_message_invalid_url', __( 'Invalid file URL provided.', 'secure-file-access' ) );
	$message_not_logged_in = get_option( 'sfa_message_not_logged_in', __( 'Please log in to access this file.', 'secure-file-access' ) );
	$default_product_ids = get_option( 'sfa_default_product_ids', '' );
	$default_subscription_ids = get_option( 'sfa_default_subscription_ids', '' );
	$default_roles = get_option( 'sfa_default_roles', '' );
	$default_label = get_option( 'sfa_default_label', __( 'Download File', 'secure-file-access' ) );
    $github_token_configured = false;
    if ( false !== get_option( 'sfa_github_token', false ) ) {
        $github_token_configured = true;
    }
	?>
    <div class="wrap" id="sfa-settings">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php
        if ( 'settings-saved' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Settings saved successfully.', 'secure-file-access' ) . '</strong></p></div>';
        } elseif ( 'token-removed' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'GitHub token removed successfully.', 'secure-file-access' ) . '</strong></p></div>';
        }

        // optional woocommerce access notices
        if ( ! function_exists( 'wc_customer_bought_product' ) ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__( 'WooCommerce is not active. Product purchase and subscription checks will be skipped; role-based and administrator access will continue to work.', 'secure-file-access' )
            );
        } elseif ( ! function_exists( 'wcs_user_has_subscription' ) ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__( 'WooCommerce Subscriptions is not active. Subscription checks will be skipped; product purchase, role-based, and administrator access will continue to work.', 'secure-file-access' )
            );
        }
        ?>

		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_url( $defaults_url ); ?>" class="<?php echo esc_attr( $defaults_tab_class ); ?>" data-tab="defaults" data-target="#access-defaults"><?php esc_html_e( 'Access Defaults', 'secure-file-access' ); ?></a>
    		<a href="<?php echo esc_url( $errors_url ); ?>" class="<?php echo esc_attr( $errors_tab_class ); ?>" data-tab="errors" data-target="#error-messages"><?php esc_html_e( 'Error Messages', 'secure-file-access' ); ?></a>
            <a href="<?php echo esc_url( $github_url ); ?>" class="<?php echo esc_attr( $github_tab_class ); ?>" data-tab="github" data-target="#github-access"><?php esc_html_e( 'GitHub Access', 'secure-file-access' ); ?></a>
		</h2>

        <form method="post">
            <?php wp_nonce_field( 'sfa_save_settings', 'sfa_nonce' ); ?>
            <input type="hidden" name="sfa_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

            <div id="access-defaults" class="tab-content" style="display: <?php echo esc_attr( $defaults_display ); ?>;">
                <h3><?php echo esc_html__( 'Access Defaults', 'secure-file-access' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Default Product IDs', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="text" name="sfa_default_product_ids" value="<?php echo esc_attr( $default_product_ids ); ?>" class="regular-text" style="width:100%;">
                            <p class="description"><?php echo esc_html__( 'Comma-separated WooCommerce product IDs. Leave empty to avoid purchase-based access by default.', 'secure-file-access' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Default Subscription IDs', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="text" name="sfa_default_subscription_ids" value="<?php echo esc_attr( $default_subscription_ids ); ?>" class="regular-text" style="width:100%;">
                            <p class="description"><?php echo esc_html__( 'Comma-separated WooCommerce subscription product IDs. Leave empty to avoid subscription-based access by default.', 'secure-file-access' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Default Roles', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="text" name="sfa_default_roles" value="<?php echo esc_attr( $default_roles ); ?>" class="regular-text" style="width:100%;">
                            <p class="description"><?php echo esc_html__( 'Comma-separated WordPress roles (e.g., editor,author). Administrators always have access.', 'secure-file-access' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Default Download Button Label', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="text" name="sfa_default_label" value="<?php echo esc_attr( $default_label ); ?>" class="regular-text" style="width:100%;">
                            <p class="description"><?php echo esc_html__( 'Can be overridden in the shortcode using the "label" attribute.', 'secure-file-access' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="error-messages" class="tab-content" style="display: <?php echo esc_attr( $errors_display ); ?>;">
                <h3><?php echo esc_html__( 'Error Messages', 'secure-file-access' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Message: No Access', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="text" name="sfa_message_no_access" value="<?php echo esc_attr( $message_no_access ); ?>" class="regular-text" style="width:100%;">
                            <p class="description"><?php echo esc_html__( 'Shown when a logged-in user does not meet the required role, product purchase, or WooCommerce subscription.', 'secure-file-access' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Message: Invalid File URL', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="text" name="sfa_message_invalid_url" value="<?php echo esc_attr( $message_invalid_url ); ?>" class="regular-text" style="width:100%;">
                            <p class="description"><?php echo esc_html__( 'Shown when a non-empty shortcode "url" attribute is invalid.', 'secure-file-access' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Message: Not Logged In', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="text" name="sfa_message_not_logged_in" value="<?php echo esc_attr( $message_not_logged_in ); ?>" class="regular-text" style="width:100%;">
                            <p class="description"><?php echo esc_html__( 'Shown when a visitor is not logged in but tries to access a protected file.', 'secure-file-access' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="github-access" class="tab-content" style="display: <?php echo esc_attr( $github_display ); ?>;">
                <h3><?php echo esc_html__( 'GitHub Access', 'secure-file-access' ); ?></h3>
                <p><?php echo esc_html__( 'Configure one GitHub personal access token for private release downloads from this WordPress site.', 'secure-file-access' ); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Personal Access Token', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="password" name="sfa_github_token" value="" class="regular-text" style="width:100%;" autocomplete="new-password" spellcheck="false">
                            <p class="description"><?php echo esc_html__( 'Enter a fine-grained or classic token. Fine-grained tokens require read-only Contents access. Leave blank to keep the configured token.', 'secure-file-access' ); ?> <a href="<?php echo esc_url( 'https://github.com/settings/personal-access-tokens' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Create a personal access token on GitHub.', 'secure-file-access' ); ?></a></p>
                            <?php
                            if ( $github_token_configured ) {
                                echo '<p><strong>' . esc_html__( 'Token configured.', 'secure-file-access' ) . '</strong></p>';
                                echo '<button type="submit" form="sfa-remove-github-token-form" class="button button-secondary">' . esc_html__( 'Remove Token', 'secure-file-access' ) . '</button>';
                            } else {
                                echo '<p><strong>' . esc_html__( 'No token configured.', 'secure-file-access' ) . '</strong></p>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <input type="submit" name="sfa_save_settings" class="button button-primary" value="<?php echo esc_attr__( 'Save Changes', 'secure-file-access' ); ?>">
            </p>
        </form>

        <form method="post" id="sfa-remove-github-token-form">
            <?php wp_nonce_field( 'sfa_remove_github_token', 'sfa_remove_nonce' ); ?>
            <input type="hidden" name="sfa_remove_github_token" value="1">
            <input type="hidden" name="sfa_active_tab" value="github">
        </form>
	</div>

    <script>
        // simple tab toggling (scoped to #sfa-settings)
        (function () {
            var wrap = document.getElementById('sfa-settings');
            if (!wrap) { return; }

            var activeTabInput = wrap.querySelector('input[name="sfa_active_tab"]');

            wrap.querySelectorAll('.nav-tab').forEach(function (tab) {
                tab.addEventListener('click', function (e) {
                    e.preventDefault();

                    // tabs
                    wrap.querySelectorAll('.nav-tab').forEach(function (t) {
                        t.classList.remove('nav-tab-active');
                    });
                    this.classList.add('nav-tab-active');

                    // panes
                    wrap.querySelectorAll('.tab-content').forEach(function (content) {
                        content.style.display = 'none';
                    });
                    var target = this.getAttribute('data-target');
                    var pane = wrap.querySelector(target);
                    if (pane) { pane.style.display = 'block'; }

                    if (activeTabInput) {
                        activeTabInput.value = this.getAttribute('data-tab');
                    }

                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', this.getAttribute('href'));
                    }
                });
            });
        })();
    </script>
	<?php
}

// shortcode for file access
add_shortcode( 'file_access', function( $atts ) {
    // defaults
    $default_label = get_option( 'sfa_default_label', __( 'Download File', 'secure-file-access' ) );
    $default_product_ids = get_option( 'sfa_default_product_ids', '' );
    $default_sub_ids = get_option( 'sfa_default_subscription_ids', '' );
    $default_roles = explode( ',', get_option( 'sfa_default_roles', '' ) );
    $default_roles = array_map( 'trim', $default_roles );
    $default_roles = array_map( 'sanitize_key', $default_roles ); // sanitize_key lowercases
    $default_roles = array_values( array_filter( $default_roles ) );

    // messages (plain text defaults)
    $message_no_access = get_option( 'sfa_message_no_access', __( 'You do not have access to this file.', 'secure-file-access' ) );
    $message_invalid_url = get_option( 'sfa_message_invalid_url', __( 'Invalid file URL provided.', 'secure-file-access' ) );
    $message_not_logged_in = get_option( 'sfa_message_not_logged_in', __( 'Please log in to access this file.', 'secure-file-access' ) );
    $message_invalid_source = __( 'Invalid download source provided.', 'secure-file-access' );

    // shortcode atts
    $atts = shortcode_atts(
        array(
            'url' => '',
            'github_repo' => '',
            'github_tag' => '',
            'github_asset' => '',
            'label' => $default_label,
            'products' => '',
            'subscriptions' => '',
            'roles' => '',
        ),
        $atts,
        'file_access'
    );

    $url_input = trim( $atts['url'] );
    $url = esc_url_raw( $url_input, array( 'http', 'https' ) );
    $github_repo_input = trim( $atts['github_repo'] );
    $github_repo = sfa_sanitize_github_repository( $github_repo_input );
    $github_tag = trim( sanitize_text_field( $atts['github_tag'] ) );
    $github_asset = trim( sanitize_text_field( $atts['github_asset'] ) );
    $label = $atts['label'];

    $github_requested = false;
    if ( '' !== $github_repo_input || '' !== $github_tag || '' !== $github_asset ) {
        $github_requested = true;
    }

    // require exactly one valid download source
    if ( ( '' !== $url_input && $github_requested ) || ( '' === $url_input && ! $github_requested ) ) {
        return '<div class="sfa-wrapper sfa-invalid-source" role="alert"><span class="sfa-message">' . esc_html( $message_invalid_source ) . '</span></div>';
    }

    if ( '' !== $url_input && empty( $url ) ) {
        return '<div class="sfa-wrapper sfa-invalid-url" role="alert"><span class="sfa-message">' . esc_html( $message_invalid_url ) . '</span></div>';
    }

    if ( $github_requested && empty( $github_repo ) ) {
        return '<div class="sfa-wrapper sfa-invalid-source" role="alert"><span class="sfa-message">' . esc_html( $message_invalid_source ) . '</span></div>';
    }

	// require log in (use simple classes)
	if ( ! is_user_logged_in() ) {
    	return '<div class="sfa-wrapper sfa-not-logged-in" role="alert"><span class="sfa-message">' . esc_html( $message_not_logged_in ) . '</span></div>';
	}

    // user context
    $user_id = get_current_user_id();

    // compute rules
	// split on commas only to avoid mid-word splits
	$roles = implode( ',', $default_roles );
	if ( ! empty( $atts['roles'] ) ) {
		$roles = $atts['roles'];
	}
	$roles = explode( ',', $roles );
	$roles = array_map( 'trim', $roles );
	$roles = array_map( 'sanitize_key', $roles );
	$roles = array_values( array_unique( array_filter( $roles ) ) );

    // split on commas only and keep digits for ids
    $products = $default_product_ids;
    if ( ! empty( $atts['products'] ) ) {
        $products = $atts['products'];
    }
    $products = explode( ',', $products );
    $products = array_map( 'trim', $products );
    $products = array_map( function( $v ) { return preg_replace( '/\D+/', '', $v ); }, $products );
    $products = array_values( array_unique( array_filter( $products ) ) );

    $subscriptions = $default_sub_ids;
    if ( ! empty( $atts['subscriptions'] ) ) {
        $subscriptions = $atts['subscriptions'];
    }
    $subscriptions = explode( ',', $subscriptions );
    $subscriptions = array_map( 'trim', $subscriptions );
    $subscriptions = array_map( function( $v ) { return preg_replace( '/\D+/', '', $v ); }, $subscriptions );
    $subscriptions = array_values( array_unique( array_filter( $subscriptions ) ) );

    // render
    if ( sfa_protected_download_user_has_access( $user_id, $roles, $subscriptions, $products ) ) {
        if ( $github_requested ) {
            $download_url = sfa_create_protected_github_download_url( $github_repo, $github_tag, $github_asset, $user_id, $roles, $subscriptions, $products );
        } else {
            $download_url = sfa_create_protected_download_url( $url, $user_id, $roles, $subscriptions, $products );
        }

        if ( empty( $download_url ) ) {
            return '<div class="sfa-wrapper sfa-invalid-source" role="alert"><span class="sfa-message">' . esc_html( $message_invalid_source ) . '</span></div>';
        }

        return sprintf(
            '<div class="sfa-wrapper"><a class="sfa-link" href="%s"><span class="sfa-label">%s</span></a></div>',
            esc_url( $download_url ),
            esc_html( $label )
        );
    }

    return '<div class="sfa-wrapper sfa-no-access" role="alert"><span class="sfa-message">' . esc_html( $message_no_access ) . '</span></div>';
} );
