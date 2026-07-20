<?php

// prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// create a short-lived protected download url
function sfa_create_protected_download_url( $url, $user_id, $roles, $subscriptions ) {
	$url = esc_url_raw( $url, array( 'http', 'https' ) );
	$user_id = absint( $user_id );

	if ( empty( $url ) || ! $user_id ) {
		return '';
	}

	$token = '';
	$transient_key = '';

	// avoid reusing an existing token in the unlikely event of a collision
	for ( $attempt = 0; $attempt < 3; $attempt++ ) {
		$token = wp_generate_password( 64, false, false );
		$transient_key = 'sfa_download_' . $token;

		if ( false === get_transient( $transient_key ) ) {
			break;
		}
	}

	if ( empty( $token ) || false !== get_transient( $transient_key ) ) {
		return '';
	}

	$expires_at = time() + ( 15 * MINUTE_IN_SECONDS );
	$download = array(
		'user_id' => $user_id,
		'url' => $url,
		'roles' => array_values( (array) $roles ),
		'subscriptions' => array_values( (array) $subscriptions ),
		'expires_at' => $expires_at,
	);

	if ( ! set_transient( $transient_key, $download, 15 * MINUTE_IN_SECONDS ) ) {
		return '';
	}

	return add_query_arg( 'download', $token, home_url( '/' ) );
}

// check a user's current access against stored rules
function sfa_protected_download_user_has_access( $user_id, $roles, $subscriptions ) {
	$user_id = absint( $user_id );

	if ( ! $user_id ) {
		return false;
	}

	// administrators always have access
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}

	$roles = array_map( 'sanitize_key', (array) $roles );
	$roles = array_values( array_unique( array_filter( $roles ) ) );
	$user_roles = array_map( 'sanitize_key', (array) $user->roles );

	if ( array_intersect( $roles, $user_roles ) ) {
		return true;
	}

	if ( function_exists( 'wcs_user_has_subscription' ) ) {
		foreach ( (array) $subscriptions as $subscription_id ) {
			$subscription_id = absint( $subscription_id );

			if (
				$subscription_id &&
				(
					wcs_user_has_subscription( $user_id, $subscription_id, 'active' ) ||
					wcs_user_has_subscription( $user_id, $subscription_id, 'pending-cancel' )
				)
			) {
				return true;
			}
		}
	}

	return false;
}

// send headers that prevent protected download responses from being cached or referred
function sfa_send_protected_download_headers() {
	if ( headers_sent() ) {
		return;
	}

	nocache_headers();
	header( 'Cache-Control: private, no-store, max-age=0', true );
	header( 'Referrer-Policy: no-referrer', true );
}

// stop a protected download request with a user-facing message
function sfa_stop_protected_download( $message ) {
	sfa_send_protected_download_headers();

	wp_die(
		esc_html( $message ),
		esc_html__( 'Download unavailable', 'secure-file-access' ),
		array( 'response' => 403 )
	);
}

// process protected download requests
add_action( 'template_redirect', 'sfa_handle_protected_download', 0 );
function sfa_handle_protected_download() {
	if ( ! isset( $_GET['download'] ) ) {
		return;
	}

	$token = sanitize_text_field( wp_unslash( $_GET['download'] ) );

	// ignore unrelated download query parameters
	if ( ! preg_match( '/\A[A-Za-z0-9]{64}\z/', $token ) ) {
		return;
	}

	$transient_key = 'sfa_download_' . $token;
	$download = get_transient( $transient_key );

	if (
		! is_array( $download ) ||
		! isset( $download['user_id'] ) ||
		! isset( $download['url'] ) ||
		! isset( $download['roles'] ) ||
		! isset( $download['subscriptions'] ) ||
		! isset( $download['expires_at'] ) ||
		time() > absint( $download['expires_at'] )
	) {
		delete_transient( $transient_key );
		sfa_stop_protected_download( __( 'This download link is invalid or has expired.', 'secure-file-access' ) );
	}

	if ( ! is_user_logged_in() ) {
		sfa_stop_protected_download(
			get_option( 'sfa_message_not_logged_in', __( 'Please log in to access this file.', 'secure-file-access' ) )
		);
	}

	$user_id = get_current_user_id();
	if ( $user_id !== absint( $download['user_id'] ) ) {
		sfa_stop_protected_download(
			get_option( 'sfa_message_no_access', __( 'You do not have access to this file.', 'secure-file-access' ) )
		);
	}

	if ( ! sfa_protected_download_user_has_access( $user_id, $download['roles'], $download['subscriptions'] ) ) {
		sfa_stop_protected_download(
			get_option( 'sfa_message_no_access', __( 'You do not have access to this file.', 'secure-file-access' ) )
		);
	}

	$url = esc_url_raw( $download['url'], array( 'http', 'https' ) );
	if ( empty( $url ) ) {
		sfa_stop_protected_download(
			get_option( 'sfa_message_invalid_url', __( 'Invalid file URL provided.', 'secure-file-access' ) )
		);
	}

	// make the protected link single-use after all checks pass
	delete_transient( $transient_key );
	sfa_send_protected_download_headers();

	wp_redirect( $url, 302, 'Secure File Access' );
	exit;
}
