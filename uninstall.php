<?php

// prevent direct access
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// remove the stored github token from every site
if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		delete_blog_option( $site_id, 'sfa_github_token' );
	}
} else {
	delete_option( 'sfa_github_token' );
}
