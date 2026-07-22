<?php

// prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// sanitize a github repository in owner/repository format
function sfa_sanitize_github_repository( $repository ) {
	$repository = sanitize_text_field( $repository );
	$repository = trim( $repository );

	if ( ! preg_match( '/\A[A-Za-z0-9-]+\/[A-Za-z0-9._-]+\z/', $repository ) ) {
		return '';
	}

	return $repository;
}

// create and store a short-lived protected download token
function sfa_create_protected_download_token( $source, $user_id, $roles, $subscriptions ) {
	$user_id = absint( $user_id );

	if ( ! is_array( $source ) || empty( $source['source'] ) || ! $user_id ) {
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

	$download = array(
		'user_id' => $user_id,
		'roles' => array_values( (array) $roles ),
		'subscriptions' => array_values( (array) $subscriptions ),
		'expires_at' => time() + ( 15 * MINUTE_IN_SECONDS ),
	);
	$download = array_merge( $download, $source );

	if ( ! set_transient( $transient_key, $download, 15 * MINUTE_IN_SECONDS ) ) {
		return '';
	}

	return add_query_arg( 'download', $token, home_url( '/' ) );
}

// create a short-lived protected url download
function sfa_create_protected_download_url( $url, $user_id, $roles, $subscriptions ) {
	$url = esc_url_raw( $url, array( 'http', 'https' ) );

	if ( empty( $url ) ) {
		return '';
	}

	return sfa_create_protected_download_token(
		array(
			'source' => 'url',
			'url' => $url,
		),
		$user_id,
		$roles,
		$subscriptions
	);
}

// create a short-lived protected github release download
function sfa_create_protected_github_download_url( $repository, $tag, $asset, $user_id, $roles, $subscriptions ) {
	$repository = sfa_sanitize_github_repository( $repository );
	$tag = trim( sanitize_text_field( $tag ) );
	$asset = trim( sanitize_text_field( $asset ) );

	if ( empty( $repository ) ) {
		return '';
	}

	if ( strlen( $tag ) > 255 || strlen( $asset ) > 255 ) {
		return '';
	}

	if ( false !== strpos( $asset, '/' ) || false !== strpos( $asset, '\\' ) ) {
		return '';
	}

	return sfa_create_protected_download_token(
		array(
			'source' => 'github',
			'github_repo' => $repository,
			'github_tag' => $tag,
			'github_asset' => $asset,
		),
		$user_id,
		$roles,
		$subscriptions
	);
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

// build headers for authenticated github api requests
function sfa_github_api_headers( $token, $accept ) {
	return array(
		'Accept' => $accept,
		'Authorization' => 'Bearer ' . $token,
		'X-GitHub-Api-Version' => '2026-03-10',
		'User-Agent' => 'Secure File Access',
	);
}

// load one github release and select its zip asset
function sfa_get_github_release_asset( $repository, $tag, $asset_name ) {
	$token = get_option( 'sfa_github_token', '' );
	if ( ! is_string( $token ) || '' === trim( $token ) ) {
		return new WP_Error( 'sfa_github_token_missing', __( 'GitHub token is not configured.', 'secure-file-access' ) );
	}
	$token = trim( $token );

	$repository = sfa_sanitize_github_repository( $repository );
	if ( empty( $repository ) ) {
		return new WP_Error( 'sfa_github_repository_invalid', __( 'Invalid GitHub repository provided.', 'secure-file-access' ) );
	}

	$parts = explode( '/', $repository, 2 );
	$owner = $parts[0];
	$repo = $parts[1];
	$api_url = 'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/releases/latest';

	if ( '' !== $tag ) {
		$api_url = 'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/releases/tags/' . rawurlencode( $tag );
	}

	$response = wp_remote_get(
		$api_url,
		array(
			'timeout' => 15,
			'redirection' => 3,
			'headers' => sfa_github_api_headers( $token, 'application/vnd.github+json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'sfa_github_release_unavailable', __( 'The GitHub repository or release could not be accessed.', 'secure-file-access' ) );
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $release ) ) {
		return new WP_Error( 'sfa_github_release_unavailable', __( 'The GitHub repository or release could not be accessed.', 'secure-file-access' ) );
	}

	if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
		return new WP_Error( 'sfa_github_release_unavailable', __( 'The selected GitHub release is not a published stable release.', 'secure-file-access' ) );
	}

	if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
		return new WP_Error( 'sfa_github_assets_missing', __( 'No ZIP release asset was found.', 'secure-file-access' ) );
	}

	$zip_assets = array();
	foreach ( $release['assets'] as $release_asset ) {
		if ( ! is_array( $release_asset ) || empty( $release_asset['id'] ) || empty( $release_asset['name'] ) ) {
			continue;
		}

		if ( isset( $release_asset['state'] ) && 'uploaded' !== $release_asset['state'] ) {
			continue;
		}

		if ( ! preg_match( '/\.zip\z/i', $release_asset['name'] ) ) {
			continue;
		}

		$zip_assets[] = $release_asset;
	}

	if ( '' !== $asset_name ) {
		foreach ( $zip_assets as $release_asset ) {
			if ( $asset_name === $release_asset['name'] ) {
				return array(
					'owner' => $owner,
					'repo' => $repo,
					'id' => absint( $release_asset['id'] ),
				);
			}
		}

		return new WP_Error( 'sfa_github_asset_not_found', __( 'The requested GitHub ZIP release asset was not found.', 'secure-file-access' ) );
	}

	if ( 1 < count( $zip_assets ) ) {
		return new WP_Error( 'sfa_github_asset_ambiguous', __( 'Multiple GitHub ZIP release assets were found. Specify the github_asset shortcode attribute.', 'secure-file-access' ) );
	}

	if ( 1 !== count( $zip_assets ) ) {
		return new WP_Error( 'sfa_github_assets_missing', __( 'No ZIP release asset was found.', 'secure-file-access' ) );
	}

	return array(
		'owner' => $owner,
		'repo' => $repo,
		'id' => absint( $zip_assets[0]['id'] ),
	);
}

// resolve a github release asset to a temporary download url
function sfa_get_github_release_asset_url( $repository, $tag, $asset_name ) {
	$release_asset = sfa_get_github_release_asset( $repository, $tag, $asset_name );
	if ( is_wp_error( $release_asset ) ) {
		return $release_asset;
	}

	$token = get_option( 'sfa_github_token', '' );
	if ( ! is_string( $token ) || '' === trim( $token ) ) {
		return new WP_Error( 'sfa_github_token_missing', __( 'GitHub token is not configured.', 'secure-file-access' ) );
	}
	$token = trim( $token );

	$api_url = 'https://api.github.com/repos/' . rawurlencode( $release_asset['owner'] ) . '/' . rawurlencode( $release_asset['repo'] ) . '/releases/assets/' . absint( $release_asset['id'] );
	$response = wp_remote_get(
		$api_url,
		array(
			'timeout' => 15,
			'redirection' => 0,
			'decompress' => false,
			'limit_response_size' => 1,
			'headers' => sfa_github_api_headers( $token, 'application/octet-stream' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'sfa_github_asset_unavailable', __( 'The GitHub release asset could not be downloaded.', 'secure-file-access' ) );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( ! in_array( $response_code, array( 301, 302, 303, 307, 308 ), true ) ) {
		return new WP_Error( 'sfa_github_asset_no_redirect', __( 'GitHub did not provide a temporary download URL for this release asset.', 'secure-file-access' ) );
	}

	$redirect_url = wp_remote_retrieve_header( $response, 'location' );
	$redirect_url = esc_url_raw( $redirect_url, array( 'https' ) );
	if ( empty( $redirect_url ) ) {
		return new WP_Error( 'sfa_github_asset_no_redirect', __( 'GitHub did not provide a temporary download URL for this release asset.', 'secure-file-access' ) );
	}

	return $redirect_url;
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

	if ( ! is_string( $_GET['download'] ) ) {
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
		! isset( $download['roles'] ) ||
		! isset( $download['subscriptions'] ) ||
		! isset( $download['expires_at'] ) ||
		time() >= absint( $download['expires_at'] )
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

	$source = 'url';
	if ( isset( $download['source'] ) ) {
		$source = sanitize_key( $download['source'] );
	}

	if ( 'github' === $source ) {
		if ( ! isset( $download['github_repo'] ) || ! isset( $download['github_tag'] ) || ! isset( $download['github_asset'] ) ) {
			delete_transient( $transient_key );
			sfa_stop_protected_download( __( 'This download link is invalid or has expired.', 'secure-file-access' ) );
		}

		$url = sfa_get_github_release_asset_url( $download['github_repo'], $download['github_tag'], $download['github_asset'] );
		if ( is_wp_error( $url ) ) {
			sfa_stop_protected_download( $url->get_error_message() );
		}
	} elseif ( 'url' === $source ) {
		if ( ! isset( $download['url'] ) ) {
			delete_transient( $transient_key );
			sfa_stop_protected_download( __( 'This download link is invalid or has expired.', 'secure-file-access' ) );
		}

		$url = esc_url_raw( $download['url'], array( 'http', 'https' ) );
		if ( empty( $url ) ) {
			sfa_stop_protected_download(
				get_option( 'sfa_message_invalid_url', __( 'Invalid file URL provided.', 'secure-file-access' ) )
			);
		}
	} else {
		delete_transient( $transient_key );
		sfa_stop_protected_download( __( 'This download link is invalid or has expired.', 'secure-file-access' ) );
	}

	// make the protected link single-use after all checks pass
	delete_transient( $transient_key );
	sfa_send_protected_download_headers();

	wp_redirect( $url, 302, 'Secure File Access' );
	exit;
}
