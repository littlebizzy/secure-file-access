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
		__( 'Product File Access Settings', 'product-file-access' ),
		__( 'Product File Access', 'product-file-access' ),
		'manage_options',
		'product-file-access',
		'pfa_settings_page'
	);
} );

// settings page content
function pfa_settings_page() {
	// ensure capability
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// save settings
	if ( isset( $_POST['pfa_save_settings'] ) && isset( $_POST['pfa_nonce'] ) && check_admin_referer( 'pfa_save_settings', 'pfa_nonce' ) ) {
		// sanitize text fields
		$message_no_access = isset( $_POST['pfa_message_no_access'] ) ? sanitize_text_field( wp_unslash( $_POST['pfa_message_no_access'] ) ) : '';
		$message_invalid_url = isset( $_POST['pfa_message_invalid_url'] ) ? sanitize_text_field( wp_unslash( $_POST['pfa_message_invalid_url'] ) ) : '';
		$message_not_logged_in = isset( $_POST['pfa_message_not_logged_in'] ) ? sanitize_text_field( wp_unslash( $_POST['pfa_message_not_logged_in'] ) ) : '';
		$default_label = isset( $_POST['pfa_default_label'] ) ? sanitize_text_field( wp_unslash( $_POST['pfa_default_label'] ) ) : '';

		// normalize roles to trimmed, lowercase, comma-separated
		$roles_input = isset( $_POST['pfa_default_roles'] ) ? wp_unslash( $_POST['pfa_default_roles'] ) : '';
		$roles_parts = array_filter( array_map( 'trim', explode( ',', $roles_input ) ) );
		$roles_parts = array_map( 'strtolower', $roles_parts );
		$roles_string = implode( ',', $roles_parts );

		// normalize subscription ids to trimmed csv (leave validation to store owner)
		$subs_input = isset( $_POST['pfa_default_subscription_ids'] ) ? wp_unslash( $_POST['pfa_default_subscription_ids'] ) : '';
		$subs_parts = array_filter( array_map( 'trim', explode( ',', $subs_input ) ) );
		$subs_string = implode( ',', $subs_parts );

		update_option( 'pfa_message_no_access', $message_no_access );
		update_option( 'pfa_message_invalid_url', $message_invalid_url );
		update_option( 'pfa_message_not_logged_in', $message_not_logged_in );
		update_option( 'pfa_default_subscription_ids', $subs_string );
		update_option( 'pfa_default_roles', $roles_string );
		update_option( 'pfa_default_label', $default_label );

		echo '<div class="updated"><p><strong>' . esc_html__( 'Settings saved successfully.', 'product-file-access' ) . '</strong></p></div>';
	}

	// get current settings
	$message_no_access = get_option( 'pfa_message_no_access', '<strong>' . __( 'You do not have access to this file.', 'product-file-access' ) . '</strong>' );
	$message_invalid_url = get_option( 'pfa_message_invalid_url', '<strong>' . __( 'Invalid file URL provided.', 'product-file-access' ) . '</strong>' );
	$message_not_logged_in = get_option( 'pfa_message_not_logged_in', '<strong>' . __( 'Please log in to access this file.', 'product-file-access' ) . '</strong>' );
	$default_subscription_ids = get_option( 'pfa_default_subscription_ids', '' );
	$default_roles = get_option( 'pfa_default_roles', 'administrator' ); // admins allowed by default per requirements
	$default_label = get_option( 'pfa_default_label', __( 'Download File', 'product-file-access' ) );

	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'Product File Access Settings', 'product-file-access' ) ); ?></h1>

		<?php
		// show a scoped admin notice if woo subscriptions is not active
		if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'WooCommerce Subscriptions is not active. Subscription checks will be skipped; only role-based access will apply.', 'product-file-access' ) .
			'</p></div>';
		}
		?>

		<h2 class="nav-tab-wrapper">
			<a href="#error-messages" class="nav-tab nav-tab-active"><?php echo esc_html( __( 'Error Messages', 'product-file-access' ) ); ?></a>
			<a href="#woocommerce-settings" class="nav-tab"><?php echo esc_html( __( 'Access Defaults', 'product-file-access' ) ); ?></a>
		</h2>

		<form method="POST">
			<?php wp_nonce_field( 'pfa_save_settings', 'pfa_nonce' ); ?>

			<div id="error-messages" class="tab-content" style="display: block;">
				<h3><?php echo esc_html( __( 'Error Messages', 'product-file-access' ) ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Message: No Access', 'product-file-access' ) ); ?></th>
						<td><input type="text" name="pfa_message_no_access" value="<?php echo esc_attr( $message_no_access ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Message: Invalid File URL', 'product-file-access' ) ); ?></th>
						<td><input type="text" name="pfa_message_invalid_url" value="<?php echo esc_attr( $message_invalid_url ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Message: Not Logged In', 'product-file-access' ) ); ?></th>
						<td><input type="text" name="pfa_message_not_logged_in" value="<?php echo esc_attr( $message_not_logged_in ); ?>" class="regular-text" style="width: 100%;"></td>
					</tr>
				</table>
			</div>

			<div id="woocommerce-settings" class="tab-content" style="display: none;">
				<h3><?php echo esc_html( __( 'Access Defaults', 'product-file-access' ) ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Default Subscription IDs', 'product-file-access' ) ); ?></th>
						<td>
							<input type="text" name="pfa_default_subscription_ids" value="<?php echo esc_attr( $default_subscription_ids ); ?>" class="regular-text" style="width: 100%;">
							<p class="description"><?php echo esc_html( __( 'Comma-separated subscription product IDs. Leave empty to rely on roles only.', 'product-file-access' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Default Roles', 'product-file-access' ) ); ?></th>
						<td>
							<input type="text" name="pfa_default_roles" value="<?php echo esc_attr( $default_roles ); ?>" class="regular-text" style="width: 100%;">
							<p class="description"><?php echo esc_html( __( 'Comma-separated WordPress roles (e.g., administrator,editor). Administrators have access by default.', 'product-file-access' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Default Download Button Label', 'product-file-access' ) ); ?></th>
						<td>
							<input type="text" name="pfa_default_label" value="<?php echo esc_attr( $default_label ); ?>" class="regular-text" style="width: 100%;">
							<p class="description"><?php echo esc_html( __( 'Can be overridden in the shortcode using the "label" attribute.', 'product-file-access' ) ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<input type="submit" name="pfa_save_settings" class="button button-primary" value="<?php echo esc_attr( __( 'Save Changes', 'product-file-access' ) ); ?>">
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
	$default_label = get_option( 'pfa_default_label', __( 'Download File', 'product-file-access' ) );
	$default_sub_ids = get_option( 'pfa_default_subscription_ids', '' );
	$default_roles = explode( ',', get_option( 'pfa_default_roles', 'administrator' ) );

	$message_no_access = get_option( 'pfa_message_no_access', '<strong>' . __( 'You do not have access to this file.', 'product-file-access' ) . '</strong>' );
	$message_invalid_url = get_option( 'pfa_message_invalid_url', '<strong>' . __( 'Invalid file URL provided.', 'product-file-access' ) . '</strong>' );
	$message_not_logged_in = get_option( 'pfa_message_not_logged_in', '<strong>' . __( 'Please log in to access this file.', 'product-file-access' ) . '</strong>' );

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

	// minimal validation by design
	if ( empty( $url ) ) {
		return '<p>' . $message_invalid_url . '</p>';
	}

	if ( ! is_user_logged_in() ) {
		return '<p>' . $message_not_logged_in . '</p>';
	}

	$user_id = get_current_user_id();
	$user = wp_get_current_user();

	// resolve roles and subscriptions (merge shortcode or defaults)
	$roles = array_filter( array_map( 'trim', explode( ',', $atts['roles'] ?: implode( ',', $default_roles ) ) ) );
	$subscriptions = array_filter( array_map( 'trim', explode( ',', $atts['subscriptions'] ?: $default_sub_ids ) ) );

	// role check
	$has_access = false;
	foreach ( $roles as $role ) {
		if ( in_array( strtolower( $role ), array_map( 'strtolower', $user->roles ), true ) ) {
			$has_access = true;
			break;
		}
	}

	// subscription check (optional if woo subscriptions is active)
	if ( ! $has_access && function_exists( 'wcs_user_has_subscription' ) ) {
		foreach ( $subscriptions as $sub_id ) {
			if ( wcs_user_has_subscription( $user_id, $sub_id, 'active' ) ) {
				$has_access = true;
				break;
			}
		}
	}

	// allow if access granted, otherwise show message
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
