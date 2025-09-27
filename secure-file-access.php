<?php
/*
Plugin Name: Secure File Access
Plugin URI: https://www.littlebizzy.com/plugins/secure-file-access
Description: Easy file downloads for WordPress
Version: 1.0.0
Author: LittleBizzy
Author URI: https://www.littlebizzy.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
GitHub Plugin URI: littlebizzy/secure-file-access
Primary Branch: master
Text Domain: secure-file-access
Domain Path: /languages
*/

// prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// disable wordpress.org updates for this plugin
add_filter( 'gu_override_dot_org', function( $overrides ) {
	$overrides[] = 'secure-file-access/secure-file-access.php';
	return $overrides;
}, 999 );

// load text domain
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'secure-file-access', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// register settings page
add_action( 'admin_menu', function() {
	add_options_page(
		__( 'Secure File Access Settings', 'secure-file-access' ),
		__( 'Secure File Access', 'secure-file-access' ),
		'manage_options',
		'secure-file-access',
		'sfa_settings_page'
	);
} );

// settings page content
function sfa_settings_page() {
    // ensure capability
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
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
        if ( isset( $_POST['sfa_default_roles'] ) ) {
            $roles_input = wp_unslash( $_POST['sfa_default_roles'] );
        }
        $roles_parts = explode( ',', $roles_input );
        $roles_parts = array_map( 'trim', $roles_parts );
        $roles_parts = array_map( 'sanitize_key', $roles_parts ); // sanitize_key lowercases
        $roles_parts = array_values( array_unique( array_filter( $roles_parts ) ) );
        $roles_string = implode( ',', $roles_parts );

        // normalize subscription ids split on commas only
        $subs_input = '';
        if ( isset( $_POST['sfa_default_subscription_ids'] ) ) {
            $subs_input = wp_unslash( $_POST['sfa_default_subscription_ids'] );
        }
        $subs_parts = explode( ',', $subs_input );
        $subs_parts = array_map( 'trim', $subs_parts );
        $subs_parts = array_map( function( $v ) { return preg_replace( '/\D+/', '', $v ); }, $subs_parts );
        $subs_parts = array_map( 'absint', $subs_parts );
        $subs_parts = array_values( array_unique( array_filter( $subs_parts ) ) );
        $subs_string = implode( ',', $subs_parts );

        // persist
        update_option( 'sfa_message_no_access', $message_no_access );
        update_option( 'sfa_message_invalid_url', $message_invalid_url );
        update_option( 'sfa_message_not_logged_in', $message_not_logged_in );
        update_option( 'sfa_default_subscription_ids', $subs_string );
        update_option( 'sfa_default_roles', $roles_string );
        update_option( 'sfa_default_label', $default_label );

        // admin notice
        echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Settings saved successfully.', 'secure-file-access' ) . '</strong></p></div>';
    }

	// load settings (plain text defaults)
	$message_no_access = get_option( 'sfa_message_no_access', __( 'You do not have access to this file.', 'secure-file-access' ) );
	$message_invalid_url = get_option( 'sfa_message_invalid_url', __( 'Invalid file URL provided.', 'secure-file-access' ) );
	$message_not_logged_in = get_option( 'sfa_message_not_logged_in', __( 'Please log in to access this file.', 'secure-file-access' ) );
	$default_subscription_ids = get_option( 'sfa_default_subscription_ids', '' );
	$default_roles = get_option( 'sfa_default_roles', 'administrator' );
	$default_label = get_option( 'sfa_default_label', __( 'Download File', 'secure-file-access' ) );
	?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php
        // subscriptions notice
        if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__( 'WooCommerce Subscriptions is not active. Subscription checks will be skipped; only role-based access will apply.', 'secure-file-access' )
            );
        }
        ?>

		<h2 class="nav-tab-wrapper">
			<a href="#access-defaults" class="nav-tab nav-tab-active"><?php _e( 'Access Defaults', 'secure-file-access' ); ?></a>
			<a href="#error-messages" class="nav-tab"><?php _e( 'Error Messages', 'secure-file-access' ); ?></a>
		</h2>

        <form method="post">
            <?php wp_nonce_field( 'sfa_save_settings', 'sfa_nonce' ); ?>

            <div id="access-defaults" class="tab-content" style="display: block;">
                <h3><?php echo esc_html__( 'Access Defaults', 'secure-file-access' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Default Subscription IDs', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="text" name="sfa_default_subscription_ids" value="<?php echo esc_attr( $default_subscription_ids ); ?>" class="regular-text" style="width:100%;">
                            <p class="description"><?php echo esc_html__( 'Comma-separated subscription product IDs. Leave empty to rely on roles only.', 'secure-file-access' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Default Roles', 'secure-file-access' ); ?></th>
                        <td>
                            <input type="text" name="sfa_default_roles" value="<?php echo esc_attr( $default_roles ); ?>" class="regular-text" style="width:100%;">
                            <p class="description"><?php echo esc_html__( 'Comma-separated WordPress roles (e.g., administrator,editor). Administrators have access by default.', 'secure-file-access' ); ?></p>
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

            <div id="error-messages" class="tab-content" style="display: none;">
                <h3><?php echo esc_html__( 'Error Messages', 'secure-file-access' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Message: No Access', 'secure-file-access' ); ?></th>
                        <td><input type="text" name="sfa_message_no_access" value="<?php echo esc_attr( $message_no_access ); ?>" class="regular-text" style="width:100%;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Message: Invalid File URL', 'secure-file-access' ); ?></th>
                        <td><input type="text" name="sfa_message_invalid_url" value="<?php echo esc_attr( $message_invalid_url ); ?>" class="regular-text" style="width:100%;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Message: Not Logged In', 'secure-file-access' ); ?></th>
                        <td><input type="text" name="sfa_message_not_logged_in" value="<?php echo esc_attr( $message_not_logged_in ); ?>" class="regular-text" style="width:100%;"></td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <input type="submit" name="sfa_save_settings" class="button button-primary" value="<?php echo esc_attr__( 'Save Changes', 'secure-file-access' ); ?>">
            </p>
        </form>
	</div>

	<script>
		// simple tab toggling without external files
		document.querySelectorAll('.nav-tab').forEach(function(tab) {
			tab.addEventListener('click', function(e) {
				e.preventDefault();
				document.querySelectorAll('.nav-tab').forEach(function(t) { t.classList.remove('nav-tab-active'); });
				this.classList.add('nav-tab-active');
				document.querySelectorAll('.tab-content').forEach(function(content) { content.style.display = 'none'; });
				var target = this.getAttribute('href');
				var pane = document.querySelector(target);
				if (pane) { pane.style.display = 'block'; }
			});
		});
	</script>
	<?php
}

// shortcode for file access
add_shortcode( 'file_access', function( $atts ) {
    // defaults
    $default_label = get_option( 'sfa_default_label', __( 'Download File', 'secure-file-access' ) );
    $default_sub_ids = get_option( 'sfa_default_subscription_ids', '' );
    $default_roles = explode( ',', get_option( 'sfa_default_roles', 'administrator' ) );
    $default_roles = array_map( 'trim', $default_roles );
    $default_roles = array_map( 'sanitize_key', $default_roles ); // sanitize_key lowercases
    $default_roles = array_values( array_filter( $default_roles ) );

    // messages (plain text defaults)
    $message_no_access = get_option( 'sfa_message_no_access', __( 'You do not have access to this file.', 'secure-file-access' ) );
    $message_invalid_url = get_option( 'sfa_message_invalid_url', __( 'Invalid file URL provided.', 'secure-file-access' ) );
    $message_not_logged_in = get_option( 'sfa_message_not_logged_in', __( 'Please log in to access this file.', 'secure-file-access' ) );

    // shortcode atts
    $atts = shortcode_atts(
        [
            'url' => '',
            'label' => $default_label,
            'subscriptions' => '',
            'roles' => '',
        ],
        $atts,
        'file_access'
    );

    $url = esc_url( $atts['url'] );
    $label = esc_html( $atts['label'] );

    // require url
    if ( empty( $url ) ) {
        return '<div class="sfa sfa--invalid-url" role="alert"><span class="sfa__message">' . esc_html( $message_invalid_url ) . '</span></div>';
    }

    // require login
    if ( ! is_user_logged_in() ) {
        return '<div class="sfa sfa--not-logged-in" role="alert"><span class="sfa__message">' . esc_html( $message_not_logged_in ) . '</span></div>';
    }

    // user context
    $user_id = get_current_user_id();
    $user = wp_get_current_user();

    // compute rules
    // split on commas only to avoid mid-word splits
    $roles = $atts['roles'] ? $atts['roles'] : implode( ',', $default_roles );
    $roles = explode( ',', $roles );
    $roles = array_map( 'trim', $roles );
    $roles = array_map( 'strtolower', $roles );
    $roles = array_map( 'sanitize_key', $roles );
    $roles = array_values( array_unique( array_filter( $roles ) ) );

    // split on commas only and keep digits for ids
    $subscriptions = $atts['subscriptions'] ? $atts['subscriptions'] : $default_sub_ids;
    $subscriptions = explode( ',', $subscriptions );
    $subscriptions = array_map( 'trim', $subscriptions );
    $subscriptions = array_map( function( $v ) { return preg_replace( '/\D+/', '', $v ); }, $subscriptions );
    $subscriptions = array_values( array_unique( array_filter( $subscriptions ) ) );

    $has_access = false;

    // role check
    $user_roles_lower = array_map( 'strtolower', (array) $user->roles );
    if ( array_intersect( $roles, $user_roles_lower ) ) {
        $has_access = true;
    }

    // subscription check
    if ( ! $has_access && function_exists( 'wcs_user_has_subscription' ) ) {
        foreach ( $subscriptions as $sub_id ) {
            $sub_id = absint( $sub_id );
            if ( $sub_id && wcs_user_has_subscription( $user_id, $sub_id, 'active' ) ) {
                $has_access = true;
                break;
            }
        }
    }

    // render
    if ( $has_access ) {
        return sprintf(
            '<div class="sfa sfa--link"><a class="sfa__a" href="%s" target="_blank" rel="noopener noreferrer"><span class="sfa__label">%s</span></a></div>',
            esc_url( $url ),
            esc_html( $label )
        );
    }

    return '<div class="sfa sfa--no-access" role="alert"><span class="sfa__message">' . esc_html( $message_no_access ) . '</span></div>';
} );

// Ref: ChatGPT
