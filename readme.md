# Secure File Access

Easy file downloads for WordPress

## Description

Secure File Access creates protected download links using the `[file_access]` shortcode. Downloads can use a normal HTTP or HTTPS URL, a normalized ZIP archive from a public or private GitHub Release, or an explicitly named uploaded ZIP asset.

Visitors must be logged in, administrators always have access, and other users receive access when they match any configured WordPress role, purchased any configured WooCommerce product, or have an active or pending-cancel WooCommerce subscription for any configured product ID.

Default product IDs, subscription product IDs, WordPress roles, the download button label, and frontend error messages can be configured under **Settings > Secure File Access**. Non-empty shortcode `products`, `roles`, and `subscriptions` values override their corresponding defaults for individual downloads.

WooCommerce and WooCommerce Subscriptions are optional. Product purchase checks require WooCommerce, while subscription checks require WooCommerce Subscriptions. If no product IDs, roles, or subscription product IDs are configured, only administrators receive access. Normal file URLs are sanitized and unsupported protocols are rejected before download links are rendered.

Authorized downloads use a short-lived local `?download=` link instead of placing the destination URL or GitHub token in the page HTML. Each link is tied to the current user, expires after 15 minutes, rechecks access when requested, and becomes invalid after a successful redirect or generated-archive preparation. Protected download responses are marked private and non-cacheable and do not forward referrer information.

The **GitHub Access** tab stores one personal access token per WordPress site. GitHub shortcodes use the latest published stable release by default and can optionally require an exact release tag or uploaded ZIP asset. Generated archives are temporarily downloaded, rebuilt with the repository name as the ZIP filename and root folder, and streamed through WordPress. Explicitly named uploaded assets redirect directly when GitHub supplies a temporary URL or stream unchanged through WordPress when GitHub returns `200 OK`.

For example, `github_repo="littlebizzy/private-plugin"` without `github_asset` produces:

```text
private-plugin.zip
└── private-plugin/
```

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

### 1.6.1
- fixes private source ZIP creation by passing a trailing-slash workspace path to `wp_tempnam()`, preventing valid generated archives from failing the workspace containment check
- accepts direct `200 OK` GitHub release asset responses by streaming the unchanged ZIP through the private workspace while preserving temporary redirect handling
- stops output-buffer cleanup when the active buffer cannot be removed, preventing a possible streaming loop

### 1.6.0
- rebuilds generated GitHub release archives with the repository name as the ZIP filename and internal root folder
- streams normalized generated archives through WordPress using temporary disk files without caching the rebuilt package
- validates generated archives for one safe root directory, unsafe paths, and symbolic links before rebuilding them
- separates `source` and `package` staging directories so repository names cannot collide with internal paths
- requires private `0700` workspace permissions before generated archive processing
- streams generated source archives with `wp_safe_remote_get()` before validation and extraction
- uses `wp_safe_remote_get()` for GitHub release metadata and validates any followed redirects
- preserves direct GitHub asset redirects, including `302 Found`, for explicitly named uploaded ZIP assets

### 1.5.5
- downloads GitHub's generated ZIP archive for the selected release when `github_asset` is omitted
- fails when a specified release tag or named uploaded ZIP asset does not exist, without falling back
- preserves authenticated temporary redirects for generated archives and uploaded assets

### 1.5.4
- removes the repeated Access Defaults, Error Messages, and GitHub Access headings from inside their matching settings tabs
- renames the Access Defaults field label from **Default Download Button Label** to **Default Button Label**
- moves the GitHub personal access token link into the GitHub Access introduction and removes the duplicate link beside the token field

### 1.5.3
- changes the settings tabs from an `h2` wrapper to a labelled `nav` and adds `aria-current="page"` to the active tab
- changes each settings section heading from `h3` to `h2` and updates `aria-current` when tabs switch without reloading

### 1.5.2
- preserves the selected settings tab after saving settings or removing the GitHub token
- adds validated query-based tab URLs using `defaults`, `errors`, and `github`, with invalid values falling back to Access Defaults

### 1.5.1
- adds a direct link from the GitHub Access settings tab to GitHub's personal access token page, opening safely in a new browser tab
- redirects after saving settings or removing the GitHub token to prevent browser form resubmission prompts while preserving standard WordPress success notices

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
