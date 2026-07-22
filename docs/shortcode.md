# Shortcode

Secure File Access uses the `[file_access]` shortcode to display a protected download link from either a normal HTTP or HTTPS URL or a GitHub Release.

## URL Download

```text
[file_access url="https://example.com/plugin.zip"]
```

## GitHub Release Download

```text
[file_access github_repo="littlebizzy/private-plugin"]
```

When `github_tag` is omitted, the plugin uses the repository's latest published stable GitHub Release. Drafts, prereleases, and Git tags without an associated GitHub Release are not used.

The visitor must be logged in and have access through an allowed WordPress role, an eligible WooCommerce subscription, or administrator privileges.

## Attributes

| Attribute | Required | Description |
| --- | --- | --- |
| `url` | One source required | HTTP or HTTPS destination for a normal protected download. |
| `github_repo` | One source required | GitHub repository in `owner/repository` format. |
| `github_tag` | No | Exact published stable release tag. Uses the latest stable release when omitted. |
| `github_asset` | No | Exact ZIP release asset filename. Required when the selected release contains multiple ZIP assets. |
| `label` | No | Download link text. Uses the configured default label when omitted. |
| `roles` | No | Comma-separated WordPress role slugs. Overrides the configured default roles. |
| `subscriptions` | No | Comma-separated WooCommerce subscription product IDs. Overrides the configured default subscription IDs. |

Use either `url` or `github_repo`, not both. If both are supplied, the shortcode rejects the request and displays `Invalid download source provided.` GitHub tag and asset attributes require `github_repo`.

## GitHub Examples

### Latest Stable Release

```text
[file_access github_repo="littlebizzy/private-plugin"]
```

When the latest stable release contains exactly one ZIP asset, it is selected automatically.

### Exact Release Tag

```text
[file_access github_repo="littlebizzy/private-plugin" github_tag="v1.4.0"]
```

### Exact Release Asset

```text
[file_access github_repo="littlebizzy/private-plugin" github_asset="private-plugin.zip"]
```

### Exact Tag and Asset

```text
[file_access github_repo="littlebizzy/private-plugin" github_tag="v1.4.0" github_asset="private-plugin.zip"]
```

The asset name must match the uploaded ZIP asset exactly. When multiple ZIP assets exist and `github_asset` is omitted, the download stops with an explanatory error.

## Access Examples

### Custom Label

```text
[file_access url="https://example.com/plugin.zip" label="Download Plugin"]
```

### Role-Based Access

```text
[file_access url="https://example.com/plugin.zip" roles="customer,shop_manager"]
```

A logged-in user receives access when any listed role matches one of their current WordPress roles.

### Subscription-Based Access

```text
[file_access github_repo="littlebizzy/private-plugin" subscriptions="123,456"]
```

A logged-in user receives access when they have an active or pending-cancel WooCommerce subscription for any listed product ID.

### Roles and Subscriptions

```text
[file_access github_repo="littlebizzy/private-plugin" label="Download Plugin" roles="customer" subscriptions="123,456"]
```

Roles and subscriptions use OR logic. A user only needs to match one allowed role or one eligible subscription.

## Default Settings and Overrides

Default roles, subscription product IDs, and the download label can be configured under **Settings > Secure File Access**.

When a shortcode includes `roles` or `subscriptions`, that value replaces the corresponding default for that download. It is not merged with the saved default.

Administrators always have access. If no roles or subscription product IDs are configured, only administrators can use the protected download link.

## Protected Download Links

The destination URL and GitHub token are not placed directly in the page HTML. Authorized users receive a local protected link that:

- is tied to the current user
- expires after 15 minutes
- rechecks access when opened
- becomes invalid after a successful redirect
- uses private, non-cacheable responses without forwarding referrer information

Only HTTP and HTTPS URL destinations are accepted. GitHub downloads require a configured token with access to the selected repository.

See [Downloads](downloads.md) for the complete download flow and [Settings](settings.md) for saved defaults and GitHub token configuration.
