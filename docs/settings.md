# Settings

Secure File Access settings are available under **Settings > Secure File Access** in the WordPress admin area.

Only users with the `manage_options` capability can view or change these settings.

## Saving, Removal, and Notices

The Access Defaults, Error Messages, and GitHub Access tabs are sections of one settings form. **Save Changes** submits all three sections together, including fields in tabs that are not currently visible.

General settings saves use one action-specific WordPress nonce and require the `manage_options` capability. Submitted values are sanitized or normalized before they are stored.

**Remove Token** is intentionally a separate form and action. It uses its own action-specific nonce, deletes only the saved GitHub token, and does not submit or change the other settings.

After either action, the plugin redirects back to the settings page before WordPress outputs the admin screen. This prevents browser form-resubmission prompts. A successful general save displays **Settings saved successfully.**, while token removal displays **GitHub token removed successfully.**

Both messages use standard dismissible WordPress admin notice markup. The notice type is selected from a fixed query value after the redirect; no option, transient, or user metadata is created to store notice state. Dismissing a notice only removes it from the current page display.

## Access Defaults

### Default Product IDs

Enter one or more WooCommerce product IDs separated by commas.

```text
123,456
```

A logged-in user receives access when WooCommerce records that their WordPress account purchased any listed product. WooCommerce decides which order statuses count as paid.

Product checks use the logged-in WordPress user ID only. Guest orders are not matched by billing email.

Leave this setting blank to avoid using purchase-based access by default.

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

Users with the `manage_options` capability always have access, even when no role is listed.

### Default Download Button Label

Controls the default text shown for protected download links.

The initial value is:

```text
Download File
```

A shortcode can override this value with the `label` attribute.

## Error Messages

### Message: No Access

Shown when a logged-in user does not match an allowed role, product purchase, or eligible WooCommerce subscription.

Default:

```text
You do not have access to this file.
```

### Message: Invalid File URL

Shown when a non-empty shortcode `url` value cannot be accepted as an HTTP or HTTPS destination.

Default:

```text
Invalid file URL provided.
```

Missing, conflicting, or incomplete source attributes instead display the built-in `Invalid download source provided.` message.

### Message: Not Logged In

Shown when a visitor tries to access a protected file without being logged in.

Default:

```text
Please log in to access this file.
```

Invalid-source and GitHub-specific token, access, rate-limit, release, and asset errors use built-in translated messages and are not configurable.

## GitHub Access

The GitHub Access tab stores one personal access token for the current WordPress site. The token is used for authenticated GitHub Release downloads, including private repositories.

A fine-grained personal access token is preferred. Give it access only to repositories used by the shortcodes and grant **Contents: Read-only** permission.

The token field includes a link to [GitHub's personal access token settings](https://github.com/settings/personal-access-tokens), which opens in a new browser tab.

A classic personal access token generally requires the broader `repo` scope to read private repositories. Organization policies, approval requirements, or SSO authorization can still prevent a valid token from accessing a repository.

The saved token is never displayed again. The settings page only shows whether a token is configured.

- Leave the field blank to preserve the current token.
- Enter a new token and save to replace it.
- Use **Remove Token** to delete it explicitly.

The token is stored in the non-autoloaded `sfa_github_token` option and is sent only in server-side requests to `api.github.com`.

Removing the token does not change existing shortcodes, but GitHub downloads will stop with a clear error until another usable token is saved.

See [Shortcode](shortcode.md) for `github_repo`, `github_tag`, and `github_asset` usage.

## WooCommerce

WooCommerce is optional. When it is not active, product purchase and subscription checks are skipped while role-based and `manage_options` access continue to work.

WooCommerce Subscriptions is also optional. When WooCommerce is active but WooCommerce Subscriptions is not, product purchase checks still work and only subscription checks are skipped.

If no default product IDs, roles, or subscription product IDs are configured, only users with the `manage_options` capability receive access unless a shortcode supplies its own access rules.

## Shortcode Overrides

The `label` attribute sets the link text for an individual download.

Non-empty `products`, `roles`, and `subscriptions` values replace the corresponding saved defaults for that download. They are not merged with them.

See [Shortcode](shortcode.md) for the complete attribute reference and examples.

## Multisite

Settings are stored separately for each WordPress site. Each site can therefore use its own GitHub personal access token.

Deleting the plugin removes the stored GitHub personal access token from every site in a Multisite network. Other saved settings are preserved for possible reinstallation.

Normal plugin deactivation does not delete any settings.

See [Downloads](downloads.md) for the protected-link flow and GitHub release behavior.
