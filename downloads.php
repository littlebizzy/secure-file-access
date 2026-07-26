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
function sfa_create_protected_download_token( $source, $user_id, $roles, $subscriptions, $products = array() ) {
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
		'products' => array_values( (array) $products ),
		'expires_at' => time() + ( 15 * MINUTE_IN_SECONDS ),
	);
	$download = array_merge( $download, $source );

	if ( ! set_transient( $transient_key, $download, 15 * MINUTE_IN_SECONDS ) ) {
		return '';
	}

	return add_query_arg( 'download', $token, home_url( '/' ) );
}

// create a short-lived protected url download
function sfa_create_protected_download_url( $url, $user_id, $roles, $subscriptions, $products = array() ) {
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
		$subscriptions,
		$products
	);
}

// create a short-lived protected github release download
function sfa_create_protected_github_download_url( $repository, $tag, $asset, $user_id, $roles, $subscriptions, $products = array() ) {
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
		$subscriptions,
		$products
	);
}

// check a user's current access against stored rules
function sfa_protected_download_user_has_access( $user_id, $roles, $subscriptions, $products = array() ) {
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

	// use the logged-in user id only so guest purchases are not matched by email
	if ( function_exists( 'wc_customer_bought_product' ) ) {
		foreach ( (array) $products as $product_id ) {
			$product_id = absint( $product_id );

			if ( $product_id && wc_customer_bought_product( '', $user_id, $product_id ) ) {
				return true;
			}
		}
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

// convert a failed github api response into a safe user-facing error
function sfa_github_api_error( $response, $fallback_code, $fallback_message ) {
	if ( is_wp_error( $response ) ) {
		return new WP_Error( $fallback_code, $fallback_message );
	}

	$response_code = absint( wp_remote_retrieve_response_code( $response ) );
	$rate_limit_remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
	$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );

	if (
		429 === $response_code ||
		(
			403 === $response_code &&
			( '0' === (string) $rate_limit_remaining || '' !== (string) $retry_after )
		)
	) {
		return new WP_Error( 'sfa_github_rate_limited', __( 'GitHub API rate limit reached. Please try again later.', 'secure-file-access' ) );
	}

	if ( 401 === $response_code ) {
		return new WP_Error( 'sfa_github_token_rejected', __( 'The configured GitHub token was rejected.', 'secure-file-access' ) );
	}

	if ( 403 === $response_code ) {
		return new WP_Error( 'sfa_github_access_denied', __( 'GitHub denied access to the repository, release, or download.', 'secure-file-access' ) );
	}

	if ( 404 === $response_code ) {
		return new WP_Error( 'sfa_github_not_found', __( 'The GitHub repository, release, archive, or asset could not be found or accessed.', 'secure-file-access' ) );
	}

	if ( 500 <= $response_code && 599 >= $response_code ) {
		return new WP_Error( 'sfa_github_unavailable', __( 'GitHub is temporarily unavailable. Please try again later.', 'secure-file-access' ) );
	}

	return new WP_Error( $fallback_code, $fallback_message );
}

// load one published stable github release
function sfa_get_github_release( $repository, $tag ) {
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
		return sfa_github_api_error(
			$response,
			'sfa_github_release_unavailable',
			__( 'The GitHub repository or release could not be accessed.', 'secure-file-access' )
		);
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $release ) ) {
		return new WP_Error( 'sfa_github_release_unavailable', __( 'The GitHub repository or release could not be accessed.', 'secure-file-access' ) );
	}

	if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
		return new WP_Error( 'sfa_github_release_unavailable', __( 'The selected GitHub release is not a published stable release.', 'secure-file-access' ) );
	}

	if ( empty( $release['tag_name'] ) || ! is_string( $release['tag_name'] ) || strlen( $release['tag_name'] ) > 255 ) {
		return new WP_Error( 'sfa_github_release_unavailable', __( 'The selected GitHub release does not provide a valid tag.', 'secure-file-access' ) );
	}

	return array(
		'owner' => $owner,
		'repo' => $repo,
		'tag' => $release['tag_name'],
		'assets' => isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array(),
	);
}

// select an exact uploaded zip asset from one github release
function sfa_get_github_release_asset( $release, $asset_name ) {
	if ( ! is_array( $release ) || empty( $release['owner'] ) || empty( $release['repo'] ) || empty( $asset_name ) ) {
		return new WP_Error( 'sfa_github_asset_not_found', __( 'The requested GitHub ZIP release asset was not found.', 'secure-file-access' ) );
	}

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

		if ( $asset_name === $release_asset['name'] ) {
			return array(
				'owner' => $release['owner'],
				'repo' => $release['repo'],
				'id' => absint( $release_asset['id'] ),
			);
		}
	}

	return new WP_Error( 'sfa_github_asset_not_found', __( 'The requested GitHub ZIP release asset was not found.', 'secure-file-access' ) );
}

// validate a temporary redirect returned by github
function sfa_get_github_redirect_url( $response, $fallback_code, $fallback_message, $redirect_code, $redirect_message ) {
	if ( is_wp_error( $response ) ) {
		return sfa_github_api_error( $response, $fallback_code, $fallback_message );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( ! in_array( $response_code, array( 301, 302, 303, 307, 308 ), true ) ) {
		return sfa_github_api_error( $response, $redirect_code, $redirect_message );
	}

	$redirect_url = wp_remote_retrieve_header( $response, 'location' );
	if ( ! is_string( $redirect_url ) ) {
		return new WP_Error( $redirect_code, $redirect_message );
	}
	$redirect_url = trim( $redirect_url );

	$redirect_parts = wp_parse_url( $redirect_url );
	if (
		! is_array( $redirect_parts ) ||
		empty( $redirect_parts['scheme'] ) ||
		'https' !== strtolower( $redirect_parts['scheme'] ) ||
		empty( $redirect_parts['host'] ) ||
		isset( $redirect_parts['user'] ) ||
		isset( $redirect_parts['pass'] )
	) {
		return new WP_Error( $redirect_code, $redirect_message );
	}

	$redirect_url = wp_http_validate_url( $redirect_url );
	if ( false === $redirect_url ) {
		return new WP_Error( $redirect_code, $redirect_message );
	}

	$redirect_url = esc_url_raw( $redirect_url, array( 'https' ) );
	if ( empty( $redirect_url ) ) {
		return new WP_Error( $redirect_code, $redirect_message );
	}

	return $redirect_url;
}

// resolve an uploaded github release asset to a temporary download url
function sfa_get_github_release_asset_url( $release, $asset_name ) {
	$release_asset = sfa_get_github_release_asset( $release, $asset_name );
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

	return sfa_get_github_redirect_url(
		$response,
		'sfa_github_asset_unavailable',
		__( 'The GitHub release asset could not be downloaded.', 'secure-file-access' ),
		'sfa_github_asset_no_redirect',
		__( 'GitHub did not provide a valid temporary download URL for this release asset.', 'secure-file-access' )
	);
}

// resolve a github release tag archive to a temporary download url
function sfa_get_github_release_archive_url( $release ) {
	if ( ! is_array( $release ) || empty( $release['owner'] ) || empty( $release['repo'] ) || empty( $release['tag'] ) ) {
		return new WP_Error( 'sfa_github_archive_unavailable', __( 'The GitHub release archive could not be downloaded.', 'secure-file-access' ) );
	}

	$token = get_option( 'sfa_github_token', '' );
	if ( ! is_string( $token ) || '' === trim( $token ) ) {
		return new WP_Error( 'sfa_github_token_missing', __( 'GitHub token is not configured.', 'secure-file-access' ) );
	}
	$token = trim( $token );

	$api_url = 'https://api.github.com/repos/' . rawurlencode( $release['owner'] ) . '/' . rawurlencode( $release['repo'] ) . '/zipball/' . rawurlencode( $release['tag'] );
	$response = wp_remote_get(
		$api_url,
		array(
			'timeout' => 15,
			'redirection' => 0,
			'decompress' => false,
			'limit_response_size' => 1,
			'headers' => sfa_github_api_headers( $token, 'application/vnd.github+json' ),
		)
	);

	return sfa_get_github_redirect_url(
		$response,
		'sfa_github_archive_unavailable',
		__( 'The GitHub release archive could not be downloaded.', 'secure-file-access' ),
		'sfa_github_archive_no_redirect',
		__( 'GitHub did not provide a valid temporary download URL for this release archive.', 'secure-file-access' )
	);
}

// resolve one github release to an exact asset or generated tag archive
function sfa_get_github_release_download_url( $repository, $tag, $asset_name ) {
	$release = sfa_get_github_release( $repository, $tag );
	if ( is_wp_error( $release ) ) {
		return $release;
	}

	if ( '' !== $asset_name ) {
		return sfa_get_github_release_asset_url( $release, $asset_name );
	}

	return sfa_get_github_release_archive_url( $release );
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

	$products = array();
	if ( isset( $download['products'] ) ) {
		$products = $download['products'];
	}

	if ( ! sfa_protected_download_user_has_access( $user_id, $download['roles'], $download['subscriptions'], $products ) ) {
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

		$url = sfa_get_github_release_download_url( $download['github_repo'], $download['github_tag'], $download['github_asset'] );
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
