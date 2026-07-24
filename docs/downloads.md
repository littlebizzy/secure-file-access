# Downloads

Secure File Access replaces the destination in the page HTML with a short-lived local download link.

The plugin supports two download sources:

- a normal HTTP or HTTPS URL
- a GitHub Release ZIP asset

The plugin does not permanently host or copy either source.

## How Downloads Work

When an authorized user views a `[file_access]` shortcode, the plugin:

1. validates the configured download source
2. checks the user's current access
3. creates a random 64-character download token
4. stores the source and access rules temporarily
5. displays a local link containing the token

The original URL, GitHub API URL, and GitHub personal access token are not included in the rendered shortcode HTML.

## Download Link Lifetime

Each protected download link expires after 15 minutes.

The link becomes invalid sooner when:

- it has already completed a successful redirect
- its temporary record is removed by WordPress or an object cache
- the stored download data is incomplete or invalid

Expired or invalid links display **This download link is invalid or has expired.**

Reloading the page can create a new protected link when the user still has access.

## User Binding

A protected link is tied to the WordPress user who received it.

Opening the link requires that user to remain logged in. Another user cannot use the same link, even when that user would independently qualify for the same download.

Sharing or copying a protected link therefore does not transfer access to another account.

## Access Rechecks

Access is checked when the shortcode is displayed and checked again when the protected link is opened.

The download is allowed when the current user:

- has the `manage_options` capability
- matches any allowed WordPress role
- purchased any allowed WooCommerce product
- has an active or pending-cancel WooCommerce subscription for any allowed product ID

Product purchases, roles, and subscriptions use OR logic. Only one qualifying purchase, matching role, or eligible subscription is required.

When WooCommerce is not active, product purchase and subscription checks are skipped and role-based and `manage_options` access continue to work.

When WooCommerce is active but WooCommerce Subscriptions is not, product purchase checks continue to work and only subscription checks are skipped.

If the user's access changes after the page loads, the protected link uses the latest access state when opened.

## WooCommerce Product Purchases

Product purchase access uses WooCommerce's native `wc_customer_bought_product()` check.

The plugin supplies the logged-in WordPress user ID and leaves the email argument empty. This prevents guest orders from being matched by billing email. WooCommerce decides which order statuses count as paid.

Product IDs are stored with the protected token and checked again when the link is opened. Secure File Access does not add separate refund rules, download limits, license keys, or guest-purchase matching.

## URL Downloads

Normal URL downloads accept only HTTP and HTTPS destinations.

After all checks pass, the temporary token is deleted and the browser receives a redirect to the configured destination. The destination server then handles file availability, authentication, and transfer.

A non-empty URL that cannot be accepted displays the configured **Invalid File URL** message. A missing source or conflicting or incomplete source attributes display the built-in **Invalid download source provided.** message. The URL is sanitized when the shortcode is rendered and checked again before the redirect.

## GitHub Release Downloads

GitHub downloads use the saved `sfa_github_token` only on the WordPress server.

When the protected link is opened, the plugin:

1. requests the latest published stable GitHub Release, or the exact release supplied by `github_tag`
2. ignores draft and prerelease releases
3. finds uploaded ZIP release assets
4. selects the ZIP automatically when exactly one exists
5. requires `github_asset` when multiple ZIP assets exist
6. requests the selected asset through the authenticated GitHub API
7. redirects the browser to GitHub's temporary download URL

The plugin does not proxy or stream the ZIP through PHP. This avoids PHP memory limits, execution timeouts, and large temporary files.

GitHub's asset API can return either a temporary redirect or the file body directly. Secure File Access requires the temporary redirect and stops with an error rather than downloading a directly streamed asset through WordPress.

The temporary redirect must use HTTPS, include a valid host, contain no embedded username or password, and pass WordPress URL safety validation. An invalid redirect is rejected instead of being sent to the browser.

The GitHub personal access token is never added to the protected link or redirect URL. The final temporary GitHub URL may be visible to the authorized user's browser after the redirect and expires according to GitHub's own handling.

GitHub release metadata is resolved when the protected link is opened. Secure File Access does not cache release or asset metadata.

## GitHub Errors

GitHub API failures are converted to concise messages without displaying GitHub response bodies or credentials.

Secure File Access distinguishes:

- a rejected token
- an API rate limit
- access denied by GitHub
- a missing or inaccessible repository, release, or asset
- a temporary GitHub server failure

The plugin does not automatically retry failed GitHub requests. Reloading the WordPress page creates a new protected link when the user still has access.

## GitHub Asset Selection

When `github_tag` is omitted, the repository's latest published stable GitHub Release is used. A Git tag without an associated GitHub Release is not considered.

When `github_asset` is omitted:

- exactly one ZIP asset is selected automatically
- no ZIP assets produces an error
- multiple ZIP assets produces an error asking for `github_asset`

When `github_asset` is supplied, its filename must exactly match an uploaded ZIP asset in the selected release.

## Privacy and Caching

Protected download responses are marked private and non-cacheable.

The plugin also sends a no-referrer policy before redirecting, so the destination should not receive the protected WordPress download URL through the browser's referrer header.

These protections apply to the local protected-link response. The destination server or GitHub still receives normal connection information directly from the visitor's browser when the redirect is followed.

See [Shortcode](shortcode.md) for usage examples and [Settings](settings.md) for access defaults, error messages, and GitHub token configuration.
