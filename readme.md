# Secure File Access

Easy file downloads for WordPress

## Description

Secure File Access creates protected download links using the `[file_access]` shortcode. Downloads can use a normal HTTP or HTTPS URL or a ZIP asset from a public or private GitHub Release.

Visitors must be logged in, administrators always have access, and other users receive access when they match any configured WordPress role, purchased any configured WooCommerce product, or have an active or pending-cancel WooCommerce subscription for any configured product ID.

Default product IDs, subscription product IDs, WordPress roles, the download button label, and frontend error messages can be configured under **Settings > Secure File Access**. Non-empty shortcode `products`, `roles`, and `subscriptions` values override their corresponding defaults for individual downloads.

WooCommerce and WooCommerce Subscriptions are optional. Product purchase checks require WooCommerce, while subscription checks require WooCommerce Subscriptions. If no product IDs, roles, or subscription product IDs are configured, only administrators receive access. Normal file URLs are sanitized and unsupported protocols are rejected before download links are rendered.

Authorized downloads use a short-lived local `?download=` link instead of placing the destination URL or GitHub token in the page HTML. Each link is tied to the current user, expires after 15 minutes, rechecks access when requested, and becomes invalid after a successful redirect. Protected download responses are marked private and non-cacheable and do not forward referrer information.

The **GitHub Access** tab stores one personal access token per WordPress site. GitHub shortcodes use the latest published stable release by default, can optionally pin an exact release tag or ZIP asset, and resolve the authenticated asset to a temporary GitHub download URL without proxying the file through PHP.

Normal URL usage:

```text
[file_access url="https://example.com/plugin.zip"]
```

WooCommerce product purchase access:

```text
[file_access url="https://example.com/plugin.zip" products="123,456"]
```

WooCommerce subscription purchase access with latest GitHub Release:

```text
[file_access github_repo="littlebizzy/private-plugin" subscriptions="123"]
```

Override the configured access defaults and GitHub release selection:

```text
[file_access github_repo="littlebizzy/private-plugin" github_tag="v2.0.0" github_asset="private-plugin.zip" label="Download Plugin" products="123,456" subscriptions="789" roles="customer,shop_manager"]
```

## Documentation

- [Downloads](docs/downloads.md)
- [Settings](docs/settings.md)
- [Shortcode](docs/shortcode.md)
- [Troubleshooting](docs/troubleshooting.md)

## Changelog

### 1.5.0
- adds WooCommerce product purchase access to URL and GitHub downloads through the `products` shortcode attribute and a Default Product IDs setting
- grants logged-in users access when their account purchased any listed product, using OR logic alongside administrator, role, and eligible subscription access without matching guest orders by billing email
- stores product rules in short-lived protected tokens and rechecks the customer's purchase access when each download link is opened

### 1.4.1
- distinguishes rejected tokens, GitHub rate limits, access failures, missing resources, and temporary GitHub server failures without exposing API response bodies
- validates temporary GitHub asset redirects as safe HTTPS URLs with a valid host and no embedded credentials before redirecting the browser

### 1.4.0
- adds `github_repo`, `github_tag`, and `github_asset` source attributes to the existing `[file_access]` shortcode while preserving normal `url` downloads
- resolves the latest published stable GitHub Release by default, with optional exact stable release tags
- automatically selects a single uploaded ZIP asset and requires an exact `github_asset` filename when multiple ZIP assets exist
- uses the saved `sfa_github_token` only in server-side GitHub API requests and redirects authorized users to GitHub's temporary asset URL without proxying or streaming files through PHP

### 1.3.1
- adds root-level `uninstall.php` to delete the per-site `sfa_github_token` option when the plugin is deleted, including every site on Multisite, while preserving all other plugin options
- renames `protected-downloads.php` to `downloads.php` and updates the loader path in `secure-file-access.php` without changing runtime download behavior

### 1.3.0
- adds a GitHub Access settings tab for one personal access token per WordPress site
- stores the token in the non-autoloaded `sfa_github_token` option without displaying the saved value
- preserves the configured token when the masked token field is blank and supports explicit replacement or removal
- prepares credential storage for future private repository downloads without making GitHub API requests

### 1.2.0
- replaces destination URLs in rendered shortcode HTML with 64-character, user-bound transient download tokens
- expires protected download links after 15 minutes and invalidates them after a successful redirect
- rechecks login, administrator capability, configured roles, and active or pending-cancel WooCommerce subscriptions when each download is requested
- sends private no-store cache headers and a no-referrer policy before redirecting to the sanitized HTTP or HTTPS destination

### 1.1.0
- preserves access for pending-cancel WooCommerce subscriptions until the prepaid term ends
- sanitizes file URLs and rejects unsupported protocols before rendering download links
- `Tested up to:` bumped to 7.0

### 1.0.0
- supports shortcode `[file_access]` for secure file download links with role and WooCommerce subscription-based access control
- creates admin settings page under Settings with tabbed navigation for Access Defaults and Error Messages
- integrated optional WooCommerce Subscriptions check for active subscription based file access
- administrators always have access to files displayed on the frontend
- default roles and WooCommerce subscription settings are blank
- default label for file download buttons is "Download File"
- supports customizable error messages for “no access”, “invalid URL”, and “not logged in” states
- supports localization/translation
- supports Git Updater
- supports PHP 7.0 to 8.4
- supports Multisite