<?php
/*
Plugin Name: Product File Access
Plugin URI: https://www.littlebizzy.com/plugins/product-file-access
Description: Provides subscription-based or role-based access to downloadable files using a shortcode.
Version: 1.0.0
Author: LittleBizzy
Author URI: https://www.littlebizzy.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
GitHub Plugin URI: littlebizzy/product-file-access
Primary Branch: master
Text Domain: product-file-access
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

// load text domain
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'product-file-access', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// register settings page
add_action( 'admin_menu', function() {
	add_options_page(
		'Product File Access Settings',
		'Product File Access',
		'manage_options',
		'product-file-access',
		'pfa_settings_page'
	);
} );

// settings page content
function pfa_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['pfa_save_settings'] ) ) {
		update_option( 'pfa_message_no_access', sanitize_text_field( $_POST['pfa_message_no_access'] ) );
		update_option( 'pfa_message_invalid_url', sanitize_text_field( $_POST['pfa_message_invalid_url'] ) );
		update_option( 'pfa_message_not_logged_in', sanitize_text_field( $_POST['pfa_message_not_logged_in'] ) );
		update_option( 'pfa_message_invalid_shortcode', sanitize_text_field( $_POST['pfa_message_invalid_shortcode'] ) );
		update_option( 'pfa_default_subscription_ids', sanitize_text_field( $_POST['pfa_default_subscription_ids'] ) );
		update_option( 'pfa_default_roles', sanitize_text_field( $_POST['pfa_default_roles'] ) );
		update_option( 'pfa_default_label', sanitize_text_field( $_POST['pfa_default_label'] ) );
		echo '<div class="updated"><p><strong>Settings saved successfully.</strong></p></div>';
	}

	$message_no_access = get_option( 'pfa_message_no_access', '<strong>You do not have access to this file.</strong>' );
	$message_invalid_url = get_option( 'pfa_message_invalid_url', '<strong>Invalid file URL provided.</strong>' );
	$message_not_logged_in = get_option( 'pfa_message_not_logged_in', '<strong>Please log in to access this file.</strong>' );
	$message_invalid_shortcode = get_option( 'pfa_message_invalid_shortcode', '<strong>The shortcode is missing required attributes.</strong>' );
	$default_subscription_ids = get_option( 'pfa_default_subscription_ids', '' );
	$default_roles = get_option( 'pfa_default_roles', 'administrator' );
	$default_label = get_option( 'pfa_default_label', 'Download File' );
	?>
	<div class="wrap">
		<h1>Product File Access Settings</h1>

		<h2 class="nav-tab-wrapper">
			<a href="#error-messages" class="nav-tab nav-tab-active">Error Messages</a>
			<a href="#woocommerce-settings" class="nav-tab">WooCommerce Settings</a>
		</h2>

		<form method="POST">
			<div id="error-messages" class="tab-content" style="display: block;">
				<h3>Error Messages</h3>
				<table class="form-table">
					<tr>
						<th scope="row">Message: No Access</th>
						<td><input type="text" name="pfa_message_no_access" value="<?php echo esc_attr( $message_no_access ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
					<tr>
						<th scope="row">Message: Invalid File URL</th>
						<td><input type="text" name="pfa_message_invalid_url" value="<?php echo esc_attr( $message_invalid_url ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
					<tr>
						<th scope="row">Message: Not Logged In</th>
						<td><input type="text" name="pfa_message_not_logged_in" value="<?php echo esc_attr( $message_not_logged_in ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
					<tr>
						<th scope="row">Message: Invalid Shortcode</th>
						<td><input type="text" name="pfa_message_invalid_shortcode" value="<?php echo esc_attr( $message_invalid_shortcode ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
				</table>
			</div>

			<div id="woocommerce-settings" class="tab-content" style="display: none;">
				<h3>WooCommerce and Role Settings</h3>
				<table class="form-table">
					<tr>
						<th scope="row">Default Subscription IDs</th>
						<td><input type="text" name="pfa_default_subscription_ids" value="<?php echo esc_attr( $default_subscription_ids ); ?>" class="regular-text" style="width: 100%;">
							<p class="description">Comma-separated WooCommerce subscription product IDs.</p></td>
					</tr>
					<tr>
						<th scope="row">Default Roles</th>
						<td><input type="text" name="pfa_default_roles" value="<?php echo esc_attr( $default_roles ); ?>" class="regular-text" style="width: 100%;">
							<p class="description">Comma-separated WordPress roles like administrator,editor.</p></td>
					</tr>
					<tr>
						<th scope="row">Default Download Button Label</th>
						<td><input type="text" name="pfa_default_label" value="<?php echo esc_attr( $default_label ); ?>" class="regular-text" style="width: 100%;">
							<p class="description">Can be overridden in the shortcode using <code>label</code>.</p></td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<input type="submit" name="pfa_save_settings" class="button button-primary" value="Save Changes">
			</p>
		</form>
	</div>

	<script>
		document.querySelectorAll('.nav-tab').forEach(tab => {
			tab.addEventListener('click', function(e) {
				e.preventDefault();
				document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
				this.classList.add('nav-tab-active');
				document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
				document.querySelector(this.getAttribute('href')).style.display = 'block';
			});
		});
	</script>
	<?php
}

// shortcode for file access
add_shortcode( 'file_access', function( $atts ) {
	$default_label = get_option( 'pfa_default_label', 'Download File' );
	$default_sub_ids = get_option( 'pfa_default_subscription_ids', '' );
	$default_roles = explode( ',', get_option( 'pfa_default_roles', 'administrator' ) );

	$message_no_access = get_option( 'pfa_message_no_access', '<strong>You do not have access to this file.</strong>' );
	$message_invalid_url = get_option( 'pfa_message_invalid_url', '<strong>Invalid file URL provided.</strong>' );
	$message_not_logged_in = get_option( 'pfa_message_not_logged_in', '<strong>Please log in to access this file.</strong>' );
	$message_invalid_shortcode = get_option( 'pfa_message_invalid_shortcode', '<strong>The shortcode is missing required attributes.</strong>' );

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

	if ( empty( $url ) ) {
		return '<p>' . $message_invalid_url . '</p>';
	}

	if ( ! is_user_logged_in() ) {
		return '<p>' . $message_not_logged_in . '</p>';
	}

	$user_id = get_current_user_id();
	$user = wp_get_current_user();
	$roles = array_filter( array_map( 'trim', explode( ',', $atts['roles'] ?: implode( ',', $default_roles ) ) ) );
	$subscriptions = array_filter( array_map( 'trim', explode( ',', $atts['subscriptions'] ?: $default_sub_ids ) ) );

	$has_access = false;

	foreach ( $roles as $role ) {
		if ( in_array( $role, $user->roles, true ) ) {
			$has_access = true;
			break;
		}
	}

	if ( ! $has_access && function_exists( 'wcs_user_has_subscription' ) ) {
		foreach ( $subscriptions as $sub_id ) {
			if ( wcs_user_has_subscription( $user_id, $sub_id, 'active' ) ) {
				$has_access = true;
				break;
			}
		}
	}

	if ( $has_access ) {
		return sprintf(
			'<p><a href="%s" target="_blank" rel="noopener noreferrer"><strong>%s</strong></a></p>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	return '<p>' . $message_no_access . '</p>';
} );

// Ref: ChatGPT
