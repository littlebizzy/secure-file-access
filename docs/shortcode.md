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

The visitor must be logged in and have access through an allowed WordPress role, a recorded WooCommerce product purchase, an eligible WooCommerce subscription, or the `manage_options` capability.

## Attributes

| Attribute | Required | Description |
| --- | --- | --- |
| `url` | One source required | HTTP or HTTPS destination for a normal protected download. |
| `github_repo` | One source required | GitHub repository in `owner/repository` format. |
| `github_tag` | No | Exact published stable release tag. Uses the latest stable release when omitted. |
| `github_asset` | No | Exact ZIP release asset filename. Required when the selected release contains multiple ZIP assets. |
| `label` | No | Download link text. Uses the configured default label when omitted. |
| `products` | No | Comma-separated WooCommerce product IDs. Overrides the configured default product IDs when non-empty. |
| `roles` | No | Comma-separated WordPress role slugs. Overrides the configured default roles when non-empty. |
| `subscriptions` | No | Comma-separated WooCommerce subscription product IDs. Overrides the configured default subscription IDs when non-empty. |

Exactly one source is required. Use either `url` or `github_repo`, not both. A missing source, any URL combined with GitHub source attributes, an invalid repository, or `github_tag` or `github_asset` without a valid `github_repo` displays `Invalid download source provided.`

## GitHub Examples

### Latest Stable Release

```text
[file_access github_repo="littlebizzy/private-plugin"]
```

When the latest stable release contains exactly one ZIP asset, it is selected automatically.

### Exact Release Tag

```text
[file_access github_repo="littlebizzy/private-plugin" github_tag="v2.0.0"]
```

### Exact Release Asset

```text
[file_access github_repo="littlebizzy/private-plugin" github_asset="private-plugin.zip"]
```

### Exact Tag and Asset

```text
[file_access github_repo="littlebizzy/private-plugin" github_tag="v2.0.0" github_asset="private-plugin.zip"]
```

The asset name must match the uploaded ZIP asset exactly. When multiple ZIP assets exist and `github_asset` is omitted, the download stops with an explanatory error.

## Access Examples

### Custom Label

```text
[file_access url="https://example.com/plugin.zip" label="Download Plugin"]
```

### Product Purchase Access

```text
[file_access url="https://example.com/plugin.zip" products="123,456"]
```

A logged-in user receives access when WooCommerce records that their WordPress account purchased any listed product. Guest orders are not matched by billing email.

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

### Combined Access

```text
[file_access github_repo="littlebizzy/private-plugin" label="Download Plugin" products="123" roles="customer" subscriptions="456"]
```

Products, roles, and subscriptions use OR logic. A user only needs one qualifying purchase, one allowed role, or one eligible subscription.

## Default Settings and Overrides

Default product IDs, roles, subscription product IDs, and the download label can be configured under **Settings > Secure File Access**.

A non-empty shortcode `products`, `roles`, or `subscriptions` value replaces the corresponding default for that download. It is not merged with the saved default. The `label` value sets the link text directly.

Users with the `manage_options` capability always have access. If no product IDs, roles, or subscription product IDs are configured, only those users can use the protected download link.

## Protected Download Links

The destination URL and GitHub token are not placed directly in the page HTML. Authorized users receive a local protected link that:

- is tied to the current user
- expires after 15 minutes
- rechecks login, user binding, `manage_options`, product purchases, roles, and subscriptions when opened
- becomes invalid after a successful redirect
- uses private, non-cacheable responses without forwarding referrer information

Only HTTP and HTTPS URL destinations are accepted. A non-empty URL that fails validation uses the configured Invalid File URL message. Missing, conflicting, or incomplete source attributes use the built-in Invalid Download Source message. GitHub downloads require a configured token with access to the selected repository.

See [Downloads](downloads.md) for the complete download flow and [Settings](settings.md) for saved defaults and GitHub token configuration.
