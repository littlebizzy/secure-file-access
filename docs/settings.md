# Settings

Secure File Access settings are available under **Settings > Secure File Access** in the WordPress admin area.

Only administrators with the `manage_options` capability can view or change these settings.

## Access Defaults

### Default Subscription IDs

Enter one or more WooCommerce subscription product IDs separated by commas.

```text
123,456
```

A logged-in user receives access when they have an active or pending-cancel subscription for any listed product ID.

Leave this setting blank to avoid using subscription-based access by default.

### Default Roles

Enter one or more WordPress role slugs separated by commas.

```text
customer,shop_manager
```

A logged-in user receives access when any listed role matches one of their current WordPress roles.

Administrators always have access, even when no role is listed.

### Default Download Button Label

Controls the default text shown for protected download links.

The initial value is:

```text
Download File
```

A shortcode can override this value with the `label` attribute.

## Error Messages

### Message: No Access

Shown when a logged-in user does not match an allowed role or eligible WooCommerce subscription.

Default:

```text
You do not have access to this file.
```

### Message: Invalid File URL

Shown when the shortcode `url` value is empty or cannot be accepted as an HTTP or HTTPS destination.

Default:

```text
Invalid file URL provided.
```

### Message: Not Logged In

Shown when a visitor tries to access a protected file without being logged in.

Default:

```text
Please log in to access this file.
```

GitHub-specific source, release, and asset errors use built-in translated messages and are not configurable in version 1.4.0.

## GitHub Access

The GitHub Access tab stores one personal access token for the current WordPress site. The token is used for authenticated GitHub Release downloads, including private repositories.

A fine-grained personal access token should have **Contents: Read-only** permission for every repository used by a shortcode. A classic personal access token must likewise be able to read the selected private repositories.

The saved token is never displayed again. The settings page only shows whether a token is configured.

- Leave the field blank to preserve the current token.
- Enter a new token and save to replace it.
- Use **Remove Token** to delete it explicitly.

The token is stored in the non-autoloaded `sfa_github_token` option and is sent only in server-side requests to `api.github.com`.

Removing the token does not change existing shortcodes, but GitHub downloads will stop with a clear error until another usable token is saved.

See [Shortcode](shortcode.md) for `github_repo`, `github_tag`, and `github_asset` usage.

## WooCommerce Subscriptions

WooCommerce Subscriptions is optional.

When it is not active, the settings page displays a warning and subscription checks are skipped. Role-based access and administrator access continue to work normally.

If neither default roles nor subscription product IDs are configured, only administrators receive access unless a shortcode supplies its own access rules.

## Shortcode Overrides

The `roles`, `subscriptions`, and `label` shortcode attributes can override their corresponding defaults for an individual download.

Shortcode roles and subscription IDs replace the saved defaults for that download. They are not merged with them.

See [Shortcode](shortcode.md) for the complete attribute reference and examples.

## Multisite

Settings are stored separately for each WordPress site. Each site can therefore use its own GitHub personal access token.

Deleting the plugin removes the stored GitHub personal access token from every site in a Multisite network. Other saved settings are preserved for possible reinstallation.

Normal plugin deactivation does not delete any settings.

See [Downloads](downloads.md) for the protected-link flow and GitHub release behavior.