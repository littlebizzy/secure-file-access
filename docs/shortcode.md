# Shortcode

Secure File Access uses the `[file_access]` shortcode to display a protected download link.

## Basic Usage

```text
[file_access url="https://example.com/plugin.zip"]
```

The visitor must be logged in and have access through an allowed WordPress role, an eligible WooCommerce subscription, or administrator privileges.

## Attributes

| Attribute | Required | Description |
| --- | --- | --- |
| `url` | Yes | HTTP or HTTPS destination for the protected download. |
| `label` | No | Download link text. Uses the configured default label when omitted. |
| `roles` | No | Comma-separated WordPress role slugs. Overrides the configured default roles. |
| `subscriptions` | No | Comma-separated WooCommerce subscription product IDs. Overrides the configured default subscription IDs. |

## Examples

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
[file_access url="https://example.com/plugin.zip" subscriptions="123,456"]
```

A logged-in user receives access when they have an active or pending-cancel WooCommerce subscription for any listed product ID.

### Roles and Subscriptions

```text
[file_access url="https://example.com/plugin.zip" label="Download Plugin" roles="customer" subscriptions="123,456"]
```

Roles and subscriptions use OR logic. A user only needs to match one allowed role or one eligible subscription.

## Default Settings and Overrides

Default roles, subscription product IDs, and the download label can be configured under **Settings > Secure File Access**.

When a shortcode includes `roles` or `subscriptions`, that value replaces the corresponding default for that download. It is not merged with the saved default.

Administrators always have access. If no roles or subscription product IDs are configured, only administrators can use the protected download link.

## Protected Download Links

The destination URL is not placed directly in the page HTML. Authorized users receive a local protected link that:

- is tied to the current user
- expires after 15 minutes
- rechecks access when opened
- becomes invalid after a successful redirect
- uses private, non-cacheable responses without forwarding referrer information

Only HTTP and HTTPS destination URLs are accepted.