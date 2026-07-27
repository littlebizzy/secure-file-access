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

	$response = wp_safe_remote_get(
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

	$assets = array();
	if ( isset( $release['assets'] ) && is_array( $release['assets'] ) ) {
		$assets = $release['assets'];
	}

	return array(
		'owner' => $owner,
		'repo' => $repo,
		'tag' => $release['tag_name'],
		'assets' => $assets,
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

// delete one temporary file or directory tree
function sfa_remove_temporary_path( $path ) {
	if ( ! is_string( $path ) || '' === $path || ( ! file_exists( $path ) && ! is_link( $path ) ) ) {
		return;
	}

	if ( is_link( $path ) || is_file( $path ) ) {
		wp_delete_file( $path );
		return;
	}

	$items = scandir( $path );
	if ( ! is_array( $items ) ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		sfa_remove_temporary_path( $path . DIRECTORY_SEPARATOR . $item );
	}

	rmdir( $path );
}

// register cleanup for one temporary path at request shutdown
function sfa_register_temporary_path( $path ) {
	register_shutdown_function( 'sfa_remove_temporary_path', $path );
}

// create a private temporary workspace
function sfa_create_temporary_workspace() {
	$temp_dir = trailingslashit( get_temp_dir() );

	for ( $attempt = 0; $attempt < 3; $attempt++ ) {
		$workspace = $temp_dir . 'sfa-' . strtolower( wp_generate_password( 16, false, false ) );

		if ( ! file_exists( $workspace ) && wp_mkdir_p( $workspace ) ) {
			if ( ! chmod( $workspace, 0700 ) ) {
				sfa_remove_temporary_path( $workspace );
				continue;
			}

			sfa_register_temporary_path( $workspace );
			return $workspace;
		}
	}

	return new WP_Error( 'sfa_github_archive_workspace', __( 'WordPress could not create a private temporary directory for this GitHub archive.', 'secure-file-access' ) );
}

// validate one archive entry and return its top-level directory
function sfa_validate_github_archive_entry( $entry_name ) {
	if ( ! is_string( $entry_name ) || '' === $entry_name || false !== strpos( $entry_name, "\0" ) || false !== strpos( $entry_name, '\\' ) ) {
		return '';
	}

	if ( '/' === substr( $entry_name, 0, 1 ) || preg_match( '/\A[A-Za-z]:\//', $entry_name ) ) {
		return '';
	}

	$entry_name = rtrim( $entry_name, '/' );
	if ( '' === $entry_name || 0 !== validate_file( $entry_name ) ) {
		return '';
	}

	$parts = explode( '/', $entry_name );
	foreach ( $parts as $part ) {
		if ( '' === $part || '.' === $part || '..' === $part ) {
			return '';
		}
	}

	return $parts[0];
}

// inspect a generated archive and require one safe root directory
function sfa_get_github_archive_root( $archive_path ) {
	$root = '';
	$entry_count = 0;

	if ( class_exists( 'ZipArchive' ) ) {
		$archive = new ZipArchive();
		$opened = $archive->open( $archive_path, ZipArchive::CHECKCONS );
		if ( true !== $opened ) {
			return new WP_Error( 'sfa_github_archive_invalid', __( 'GitHub returned an invalid ZIP archive.', 'secure-file-access' ) );
		}

		for ( $index = 0; $index < $archive->numFiles; $index++ ) {
			$entry = $archive->statIndex( $index );
			if ( ! is_array( $entry ) || empty( $entry['name'] ) ) {
				$archive->close();
				return new WP_Error( 'sfa_github_archive_invalid', __( 'GitHub returned an invalid ZIP archive.', 'secure-file-access' ) );
			}

			$entry_root = sfa_validate_github_archive_entry( $entry['name'] );
			if ( '' === $entry_root ) {
				$archive->close();
				return new WP_Error( 'sfa_github_archive_unsafe', __( 'The GitHub ZIP archive contains an unsafe file path.', 'secure-file-access' ) );
			}

			if ( '' === $root ) {
				$root = $entry_root;
			} elseif ( $root !== $entry_root ) {
				$archive->close();
				return new WP_Error( 'sfa_github_archive_roots', __( 'The GitHub ZIP archive does not contain exactly one root directory.', 'secure-file-access' ) );
			}

			if ( method_exists( $archive, 'getExternalAttributesIndex' ) ) {
				$operating_system = 0;
				$attributes = 0;

				if ( $archive->getExternalAttributesIndex( $index, $operating_system, $attributes ) && 3 === $operating_system ) {
					$file_type = ( $attributes >> 16 ) & 0170000;
					if ( 0120000 === $file_type ) {
						$archive->close();
						return new WP_Error( 'sfa_github_archive_symlink', __( 'The GitHub ZIP archive contains a symbolic link.', 'secure-file-access' ) );
					}
				}
			}

			$entry_count++;
		}

		$archive->close();
	} else {
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		$archive = new PclZip( $archive_path );
		$entries = $archive->listContent();

		if ( ! is_array( $entries ) ) {
			return new WP_Error( 'sfa_github_archive_invalid', __( 'GitHub returned an invalid ZIP archive.', 'secure-file-access' ) );
		}

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['filename'] ) ) {
				return new WP_Error( 'sfa_github_archive_invalid', __( 'GitHub returned an invalid ZIP archive.', 'secure-file-access' ) );
			}

			$entry_root = sfa_validate_github_archive_entry( $entry['filename'] );
			if ( '' === $entry_root ) {
				return new WP_Error( 'sfa_github_archive_unsafe', __( 'The GitHub ZIP archive contains an unsafe file path.', 'secure-file-access' ) );
			}

			if ( '' === $root ) {
				$root = $entry_root;
			} elseif ( $root !== $entry_root ) {
				return new WP_Error( 'sfa_github_archive_roots', __( 'The GitHub ZIP archive does not contain exactly one root directory.', 'secure-file-access' ) );
			}

			$entry_count++;
		}
	}

	if ( '' === $root || 0 === $entry_count ) {
		return new WP_Error( 'sfa_github_archive_empty', __( 'GitHub returned an empty ZIP archive.', 'secure-file-access' ) );
	}

	return $root;
}

// extract one validated archive into a temporary directory
function sfa_extract_github_archive( $archive_path, $destination ) {
	if ( ! wp_mkdir_p( $destination ) ) {
		return new WP_Error( 'sfa_github_archive_extract_dir', __( 'WordPress could not create a temporary extraction directory.', 'secure-file-access' ) );
	}

	if ( class_exists( 'ZipArchive' ) ) {
		$archive = new ZipArchive();
		$opened = $archive->open( $archive_path, ZipArchive::CHECKCONS );
		if ( true !== $opened ) {
			return new WP_Error( 'sfa_github_archive_invalid', __( 'GitHub returned an invalid ZIP archive.', 'secure-file-access' ) );
		}

		$extracted = $archive->extractTo( $destination );
		$archive->close();

		if ( ! $extracted ) {
			return new WP_Error( 'sfa_github_archive_extract', __( 'WordPress could not extract the GitHub ZIP archive.', 'secure-file-access' ) );
		}
	} else {
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		$archive = new PclZip( $archive_path );
		$extracted = $archive->extract( PCLZIP_OPT_PATH, $destination );

		if ( ! is_array( $extracted ) || empty( $extracted ) ) {
			return new WP_Error( 'sfa_github_archive_extract', __( 'WordPress could not extract the GitHub ZIP archive.', 'secure-file-access' ) );
		}
	}

	return true;
}

// reject symbolic links anywhere inside an extracted directory
function sfa_github_archive_has_symlink( $directory ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isLink() ) {
			return true;
		}
	}

	return false;
}

// create a normalized zip containing the repository directory
function sfa_create_normalized_github_zip( $repository_directory, $workspace, $output_path ) {
	$repository_name = basename( $repository_directory );

	if ( class_exists( 'ZipArchive' ) ) {
		$archive = new ZipArchive();
		$opened = $archive->open( $output_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		if ( true !== $opened ) {
			return new WP_Error( 'sfa_github_archive_create', __( 'WordPress could not create the normalized GitHub ZIP archive.', 'secure-file-access' ) );
		}

		if ( ! $archive->addEmptyDir( $repository_name ) ) {
			$archive->close();
			return new WP_Error( 'sfa_github_archive_create', __( 'WordPress could not create the normalized GitHub ZIP archive.', 'secure-file-access' ) );
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $repository_directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isLink() ) {
				$archive->close();
				return new WP_Error( 'sfa_github_archive_symlink', __( 'The GitHub ZIP archive contains a symbolic link.', 'secure-file-access' ) );
			}

			$relative_path = substr( $item->getPathname(), strlen( $repository_directory ) + 1 );
			$archive_path = $repository_name . '/' . str_replace( DIRECTORY_SEPARATOR, '/', $relative_path );

			if ( $item->isDir() ) {
				$added = $archive->addEmptyDir( $archive_path );
			} else {
				$added = $archive->addFile( $item->getPathname(), $archive_path );
			}

			if ( ! $added ) {
				$archive->close();
				return new WP_Error( 'sfa_github_archive_create', __( 'WordPress could not create the normalized GitHub ZIP archive.', 'secure-file-access' ) );
			}
		}

		if ( ! $archive->close() ) {
			return new WP_Error( 'sfa_github_archive_create', __( 'WordPress could not create the normalized GitHub ZIP archive.', 'secure-file-access' ) );
		}
	} else {
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		$archive = new PclZip( $output_path );
		$created = $archive->create(
			$repository_directory,
			PCLZIP_OPT_REMOVE_PATH,
			$workspace
		);

		if ( ! is_array( $created ) || empty( $created ) ) {
			return new WP_Error( 'sfa_github_archive_create', __( 'WordPress could not create the normalized GitHub ZIP archive.', 'secure-file-access' ) );
		}
	}

	if ( ! is_file( $output_path ) || 0 >= filesize( $output_path ) ) {
		return new WP_Error( 'sfa_github_archive_create', __( 'WordPress could not create the normalized GitHub ZIP archive.', 'secure-file-access' ) );
	}

	return true;
}

// download and normalize one generated github release archive
function sfa_prepare_github_release_archive( $release ) {
	if ( ! is_array( $release ) || empty( $release['repo'] ) || in_array( $release['repo'], array( '.', '..' ), true ) ) {
		return new WP_Error( 'sfa_github_archive_unavailable', __( 'The GitHub release archive could not be prepared.', 'secure-file-access' ) );
	}

	$archive_url = sfa_get_github_release_archive_url( $release );
	if ( is_wp_error( $archive_url ) ) {
		return $archive_url;
	}

	$workspace = sfa_create_temporary_workspace();
	if ( is_wp_error( $workspace ) ) {
		return $workspace;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$source_archive = wp_tempnam( 'github-archive.zip', trailingslashit( $workspace ) );
	if ( ! is_string( $source_archive ) || '' === $source_archive ) {
		return new WP_Error( 'sfa_github_archive_download', __( 'WordPress could not create a private temporary file for the GitHub ZIP archive.', 'secure-file-access' ) );
	}

	if ( wp_normalize_path( $workspace ) !== wp_normalize_path( dirname( $source_archive ) ) ) {
		wp_delete_file( $source_archive );
		return new WP_Error( 'sfa_github_archive_download', __( 'WordPress could not create a private temporary file for the GitHub ZIP archive.', 'secure-file-access' ) );
	}

	$response = wp_safe_remote_get(
		$archive_url,
		array(
			'timeout' => 300,
			'redirection' => 5,
			'decompress' => false,
			'stream' => true,
			'filename' => $source_archive,
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_file( $source_archive ) || 0 >= filesize( $source_archive ) ) {
		return new WP_Error( 'sfa_github_archive_download', __( 'WordPress could not download the GitHub ZIP archive.', 'secure-file-access' ) );
	}

	$archive_root = sfa_get_github_archive_root( $source_archive );
	if ( is_wp_error( $archive_root ) ) {
		return $archive_root;
	}

	$source_directory = $workspace . DIRECTORY_SEPARATOR . 'source';
	$extracted = sfa_extract_github_archive( $source_archive, $source_directory );
	if ( is_wp_error( $extracted ) ) {
		return $extracted;
	}

	wp_delete_file( $source_archive );

	$entries = scandir( $source_directory );
	if ( ! is_array( $entries ) ) {
		return new WP_Error( 'sfa_github_archive_extract', __( 'WordPress could not inspect the extracted GitHub archive.', 'secure-file-access' ) );
	}
	$entries = array_values( array_diff( $entries, array( '.', '..' ) ) );

	if (
		1 !== count( $entries ) ||
		$archive_root !== $entries[0] ||
		! is_dir( $source_directory . DIRECTORY_SEPARATOR . $entries[0] ) ||
		is_link( $source_directory . DIRECTORY_SEPARATOR . $entries[0] )
	) {
		return new WP_Error( 'sfa_github_archive_roots', __( 'The GitHub ZIP archive does not contain exactly one root directory.', 'secure-file-access' ) );
	}

	$extracted_root = $source_directory . DIRECTORY_SEPARATOR . $entries[0];
	if ( sfa_github_archive_has_symlink( $extracted_root ) ) {
		return new WP_Error( 'sfa_github_archive_symlink', __( 'The GitHub ZIP archive contains a symbolic link.', 'secure-file-access' ) );
	}

	$package_directory = $workspace . DIRECTORY_SEPARATOR . 'package';
	if ( ! wp_mkdir_p( $package_directory ) ) {
		return new WP_Error( 'sfa_github_archive_package_dir', __( 'WordPress could not create a temporary package directory.', 'secure-file-access' ) );
	}

	$repository_directory = $package_directory . DIRECTORY_SEPARATOR . $release['repo'];
	if ( ! rename( $extracted_root, $repository_directory ) ) {
		return new WP_Error( 'sfa_github_archive_rename', __( 'WordPress could not rename the GitHub archive root directory.', 'secure-file-access' ) );
	}

	rmdir( $source_directory );
	$output_path = $workspace . DIRECTORY_SEPARATOR . $release['repo'] . '.zip';
	$created = sfa_create_normalized_github_zip( $repository_directory, $package_directory, $output_path );
	if ( is_wp_error( $created ) ) {
		return $created;
	}

	return array(
		'path' => $output_path,
		'filename' => $release['repo'] . '.zip',
		'workspace' => $workspace,
	);
}

// resolve one github release to an uploaded asset or normalized archive
function sfa_get_github_release_download( $repository, $tag, $asset_name ) {
	$release = sfa_get_github_release( $repository, $tag );
	if ( is_wp_error( $release ) ) {
		return $release;
	}

	if ( '' !== $asset_name ) {
		$url = sfa_get_github_release_asset_url( $release, $asset_name );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		return array(
			'type' => 'redirect',
			'url' => $url,
		);
	}

	$archive = sfa_prepare_github_release_archive( $release );
	if ( is_wp_error( $archive ) ) {
		return $archive;
	}
	$archive['type'] = 'file';

	return $archive;
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

// stream one normalized github archive to the authorized user
function sfa_send_github_release_archive( $archive ) {
	if (
		! is_array( $archive ) ||
		empty( $archive['path'] ) ||
		empty( $archive['filename'] ) ||
		empty( $archive['workspace'] ) ||
		! is_file( $archive['path'] )
	) {
		return new WP_Error( 'sfa_github_archive_send', __( 'The normalized GitHub ZIP archive is unavailable.', 'secure-file-access' ) );
	}

	$file_size = filesize( $archive['path'] );
	if ( false === $file_size || 0 >= $file_size ) {
		return new WP_Error( 'sfa_github_archive_send', __( 'The normalized GitHub ZIP archive is unavailable.', 'secure-file-access' ) );
	}

	$handle = fopen( $archive['path'], 'rb' );
	if ( false === $handle ) {
		return new WP_Error( 'sfa_github_archive_send', __( 'WordPress could not open the normalized GitHub ZIP archive.', 'secure-file-access' ) );
	}

	while ( ob_get_level() ) {
		ob_end_clean();
	}

	sfa_send_protected_download_headers();
	header( 'Content-Type: application/zip', true );
	header( 'Content-Disposition: attachment; filename="' . $archive['filename'] . '"', true );
	header( 'Content-Length: ' . $file_size, true );
	header( 'X-Content-Type-Options: nosniff', true );

	while ( ! feof( $handle ) ) {
		$buffer = fread( $handle, 1048576 );
		if ( false === $buffer ) {
			break;
		}

		echo $buffer;
		flush();
	}

	fclose( $handle );
	sfa_remove_temporary_path( $archive['workspace'] );
	exit;
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

	$github_download = array();
	if ( 'github' === $source ) {
		if ( ! isset( $download['github_repo'] ) || ! isset( $download['github_tag'] ) || ! isset( $download['github_asset'] ) ) {
			delete_transient( $transient_key );
			sfa_stop_protected_download( __( 'This download link is invalid or has expired.', 'secure-file-access' ) );
		}

		$github_download = sfa_get_github_release_download( $download['github_repo'], $download['github_tag'], $download['github_asset'] );
		if ( is_wp_error( $github_download ) ) {
			sfa_stop_protected_download( $github_download->get_error_message() );
		}

		if ( empty( $github_download['type'] ) || ! in_array( $github_download['type'], array( 'redirect', 'file' ), true ) ) {
			sfa_stop_protected_download( __( 'The GitHub release download could not be prepared.', 'secure-file-access' ) );
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

	if ( 'github' === $source && 'file' === $github_download['type'] ) {
		$sent = sfa_send_github_release_archive( $github_download );
		if ( is_wp_error( $sent ) ) {
			sfa_stop_protected_download( $sent->get_error_message() );
		}
	}

	if ( 'github' === $source ) {
		$url = $github_download['url'];
	}

	sfa_send_protected_download_headers();
	wp_redirect( $url, 302, 'Secure File Access' );
	exit;
}
