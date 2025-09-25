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
	if ( isset( $_POST['sfa_save_settings'] ) && isset( $_POST['sfa_nonce'] ) && check_admin_referer( 'sfa_save_settings', 'sfa_nonce' ) ) {
		$message_no_access = isset( $_POST['sfa_message_no_access'] ) ? sanitize_text_field( wp_unslash( $_POST['sfa_message_no_access'] ) ) : '';
		$message_invalid_url = isset( $_POST['sfa_message_invalid_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sfa_message_invalid_url'] ) ) : '';
		$message_not_logged_in = isset( $_POST['sfa_message_not_logged_in'] ) ? sanitize_text_field( wp_unslash( $_POST['sfa_message_not_logged_in'] ) ) : '';
		$default_label = isset( $_POST['sfa_default_label'] ) ? sanitize_text_field( wp_unslash( $_POST['sfa_default_label'] ) ) : '';

		$roles_input = isset( $_POST['sfa_default_roles'] ) ? wp_unslash( $_POST['sfa_default_roles'] ) : '';
		$roles_parts = array_filter( array_map( 'trim', explode( ',', $roles_input ) ) );
		$roles_parts = array_map( 'strtolower', $roles_parts );
		$roles_string = implode( ',', $roles_parts );

		$subs_input = isset( $_POST['sfa_default_subscription_ids'] ) ? wp_unslash( $_POST['sfa_default_subscription_ids'] ) : '';
		$subs_parts = array_filter( array_map( 'trim', explode( ',', $subs_input ) ) );
		$subs_string = implode( ',', $subs_parts );

		update_option( 'sfa_message_no_access', $message_no_access );
		update_option( 'sfa_message_invalid_url', $message_invalid_url );
		update_option( 'sfa_message_not_logged_in', $message_not_logged_in );
		update_option( 'sfa_default_subscription_ids', $subs_string );
		update_option( 'sfa_default_roles', $roles_string );
		update_option( 'sfa_default_label', $default_label );

		echo '<div class="updated"><p><strong>' . esc_html__( 'Settings saved successfully.', 'secure-file-access' ) . '</strong></p></div>';
	}

	// load settings
	$message_no_access = get_option( 'sfa_message_no_access', '<strong>' . __( 'You do not have access to this file.', 'secure-file-access' ) . '</strong>' );
	$message_invalid_url = get_option( 'sfa_message_invalid_url', '<strong>' . __( 'Invalid file URL provided.', 'secure-file-access' ) . '</strong>' );
	$message_not_logged_in = get_option( 'sfa_message_not_logged_in', '<strong>' . __( 'Please log in to access this file.', 'secure-file-access' ) . '</strong>' );
	$default_subscription_ids = get_option( 'sfa_default_subscription_ids', '' );
	$default_roles = get_option( 'sfa_default_roles', 'administrator' );
	$default_label = get_option( 'sfa_default_label', __( 'Download File', 'secure-file-access' ) );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'Secure File Access Settings', 'secure-file-access' ) ); ?></h1>

		<?php
		// subscriptions notice
		if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'WooCommerce Subscriptions is not active. Subscription checks will be skipped; only role-based access will apply.', 'secure-file-access' ) .
			'</p></div>';
		}
		?>

		<h2 class="nav-tab-wrapper">
			<a href="#error-messages" class="nav-tab nav-tab-active"><?php echo esc_html( __( 'Error Messages', 'secure-file-access' ) ); ?></a>
			<a href="#access-defaults" class="nav-tab"><?php echo esc_html( __( 'Access Defaults', 'secure-file-access' ) ); ?></a>
		</h2>

		<form method="POST">
			<?php wp_nonce_field( 'sfa_save_settings', 'sfa_nonce' ); ?>

			<div id="error-messages" class="tab-content" style="display: block;">
				<h3><?php echo esc_html( __( 'Error Messages', 'secure-file-access' ) ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Message: No Access', 'secure-file-access' ) ); ?></th>
						<td><input type="text" name="sfa_message_no_access" value="<?php echo esc_attr( $message_no_access ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Message: Invalid File URL', 'secure-file-access' ) ); ?></th>
						<td><input type="text" name="sfa_message_invalid_url" value="<?php echo esc_attr( $message_invalid_url ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Message: Not Logged In', 'secure-file-access' ) ); ?></th>
						<td><input type="text" name="sfa_message_not_logged_in" value="<?php echo esc_attr( $message_not_logged_in ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
				</table>
			</div>

			<div id="access-defaults" class="tab-content" style="display: none;">
				<h3><?php echo esc_html( __( 'Access Defaults', 'secure-file-access' ) ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Default Subscription IDs', 'secure-file-access' ) ); ?></th>
						<td>
							<input type="text" name="sfa_default_subscription_ids" value="<?php echo esc_attr( $default_subscription_ids ); ?>" class="regular-text" style="width: 100%;">
							<p class="description"><?php echo esc_html( __( 'Comma-separated subscription product IDs. Leave empty to rely on roles only.', 'secure-file-access' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Default Roles', 'secure-file-access' ) ); ?></th>
						<td>
							<input type="text" name="sfa_default_roles" value="<?php echo esc_attr( $default_roles ); ?>" class="regular-text" style="width: 100%;">
							<p class="description"><?php echo esc_html( __( 'Comma-separated WordPress roles (e.g., administrator,editor). Administrators have access by default.', 'secure-file-access' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Default Download Button Label', 'secure-file-access' ) ); ?></th>
						<td>
							<input type="text" name="sfa_default_label" value="<?php echo esc_attr( $default_label ); ?>" class="regular-text" style="width: 100%;">
							<p class="description"><?php echo esc_html( __( 'Can be overridden in the shortcode using the "label" attribute.', 'secure-file-access' ) ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<input type="submit" name="sfa_save_settings" class="button button-primary" value="<?php echo esc_attr( __( 'Save Changes', 'secure-file-access' ) ); ?>">
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

	// messages
	$message_no_access = get_option( 'sfa_message_no_access', '<strong>' . __( 'You do not have access to this file.', 'secure-file-access' ) . '</strong>' );
	$message_invalid_url = get_option( 'sfa_message_invalid_url', '<strong>' . __( 'Invalid file URL provided.', 'secure-file-access' ) . '</strong>' );
	$message_not_logged_in = get_option( 'sfa_message_not_logged_in', '<strong>' . __( 'Please log in to access this file.', 'secure-file-access' ) . '</strong>' );

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
		return '<p>' . wp_kses_post( $message_invalid_url ) . '</p>';
	}

	// require login
	if ( ! is_user_logged_in() ) {
		return '<p>' . wp_kses_post( $message_not_logged_in ) . '</p>';
	}

	// user context
	$user_id = get_current_user_id();
	$user = wp_get_current_user();

	// compute rules
	$roles = array_filter( array_map( 'trim', explode( ',', $atts['roles'] ?: implode( ',', $default_roles ) ) ) );
	$subscriptions = array_filter( array_map( 'trim', explode( ',', $atts['subscriptions'] ?: $default_sub_ids ) ) );

	$has_access = false;

	// role check
	$user_roles_lower = array_map( 'strtolower', (array) $user->roles );
	foreach ( $roles as $role ) {
		if ( in_array( strtolower( $role ), $user_roles_lower, true ) ) {
			$has_access = true;
			break;
		}
	}

	// subscription check
	if ( ! $has_access && function_exists( 'wcs_user_has_subscription' ) ) {
		foreach ( $subscriptions as $sub_id ) {
			if ( wcs_user_has_subscription( $user_id, $sub_id, 'active' ) ) {
				$has_access = true;
				break;
			}
		}
	}

	// render
	if ( $has_access ) {
		return sprintf(
			'<p><a href="%s" target="_blank" rel="noopener noreferrer"><strong>%s</strong></a></p>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	return '<p>' . wp_kses_post( $message_no_access ) . '</p>';
} );

// Ref: ChatGPT
