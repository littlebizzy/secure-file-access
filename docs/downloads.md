# Downloads

Secure File Access replaces the destination URL in the page HTML with a short-lived local download link.

The plugin does not host, copy, or proxy the file. After access is confirmed, the visitor is redirected to the configured HTTP or HTTPS destination.

## How Downloads Work

When an authorized user views a `[file_access]` shortcode, the plugin:

1. confirms that the destination URL is valid
2. checks the user's current access
3. creates a random 64-character download token
4. stores the destination and access rules temporarily
5. displays a local link containing the token

The original destination URL is not included in the rendered shortcode HTML.

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

- is an administrator with the `manage_options` capability
- matches any allowed WordPress role
- has an active or pending-cancel WooCommerce subscription for any allowed product ID

Roles and subscriptions use OR logic. Only one matching role or eligible subscription is required.

When WooCommerce Subscriptions is not active, subscription checks are skipped and role-based access continues to work.

If the user's access changes after the page loads, the protected link uses the latest access state when opened.

## Successful Downloads

After all checks pass, the temporary token is deleted and the browser receives a redirect to the destination URL.

This makes the protected link single-use after a successful redirect. The destination server then handles the actual file response, availability, authentication, and transfer.

## URL Requirements

Only HTTP and HTTPS destination URLs are accepted.

An empty or unsupported URL displays the configured **Invalid File URL** message. The URL is sanitized when the shortcode is rendered and checked again before the redirect.

## Privacy and Caching

Protected download responses are marked private and non-cacheable.

The plugin also sends a no-referrer policy before redirecting, so the destination should not receive the protected WordPress download URL through the browser's referrer header.

These protections apply to the local protected-link response. The destination server may still receive normal connection information directly from the visitor's browser when the redirect is followed.

## GitHub Downloads

Version 1.3.1 can store a GitHub personal access token, but the token is reserved for future private repository support.

Current shortcode downloads do not use the GitHub API or the saved GitHub token.

See [Shortcode](shortcode.md) for usage examples and [Settings](settings.md) for access defaults and error messages.
