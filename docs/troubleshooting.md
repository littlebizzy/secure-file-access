# Troubleshooting

Use this guide when a shortcode does not render a usable download link or a protected link stops with an error.

## Start Here

Confirm that:

- the visitor is logged in
- exactly one download source is configured
- the user currently qualifies through `manage_options`, an allowed role, a recorded WooCommerce purchase, or an eligible WooCommerce subscription
- required plugins are active for the configured access method
- the page was reloaded after changing settings, purchases, roles, subscriptions, release assets, or GitHub credentials

Protected links are tied to one user, expire after 15 minutes, and are deleted after a successful redirect. Reload the page to create a new link when the user still has access.

## Invalid Download Source

`Invalid download source provided.` means the shortcode does not define exactly one valid source.

Check for:

- no `url` or `github_repo`
- both `url` and any GitHub source attribute
- `github_tag` or `github_asset` without a valid `github_repo`
- a repository value that is not in `owner/repository` format

Use either:

```text
[file_access url="https://example.com/plugin.zip"]
```

or:

```text
[file_access github_repo="littlebizzy/private-plugin"]
```

## Invalid File URL

The configured **Invalid File URL** message is used only when a non-empty `url` value cannot be accepted as an HTTP or HTTPS destination.

Confirm that the URL:

- begins with `http://` or `https://`
- is complete and correctly quoted in the shortcode
- does not use another protocol such as `ftp:`, `file:`, or `javascript:`

Missing or conflicting source attributes use the separate built-in invalid-source message.

## User Does Not Have Access

Access methods use OR logic. A logged-in user needs only one of the following:

- the `manage_options` capability
- an allowed WordPress role
- a recorded purchase of an allowed WooCommerce product
- an active or pending-cancel subscription for an allowed subscription product ID

A non-empty shortcode `products`, `roles`, or `subscriptions` value replaces the corresponding saved default for that download. It is not merged with the default.

Access is checked when the shortcode is displayed and checked again when the protected link is opened. Reload the page after changing the user's access.

## WooCommerce Purchase Is Not Recognized

Product purchase checks require WooCommerce and use its native purchase history check.

Confirm that:

- WooCommerce is active
- the configured product ID is correct
- WooCommerce considers the order paid
- the order belongs to the same logged-in WordPress user account

Secure File Access supplies the logged-in user ID and does not match guest purchases by billing email. A guest order or an order attached to another account will not grant access.

Secure File Access does not add separate refund rules, download limits, license keys, or guest-purchase matching.

## Subscription Is Not Recognized

Subscription checks require WooCommerce Subscriptions.

Confirm that:

- WooCommerce and WooCommerce Subscriptions are active
- the configured subscription product ID is correct
- the subscription belongs to the logged-in user
- the subscription status is `active` or `pending-cancel`

Other subscription statuses do not grant access.

## GitHub Token Is Missing or Rejected

GitHub Release downloads require a token configured under **Settings > Secure File Access > GitHub Access**.

For private repositories:

- prefer a fine-grained personal access token limited to the required repositories with **Contents: Read-only** permission
- a classic personal access token generally requires the broader `repo` scope
- verify any organization approval or SSO authorization requirement
- replace or remove the saved token from the GitHub Access tab when needed

Leaving the token field blank preserves the existing token.

## GitHub Access Is Denied or a Resource Is Missing

GitHub can return similar failures for an inaccessible private repository and a repository, release, tag, or asset that does not exist.

Confirm that:

- `github_repo` uses the correct `owner/repository` value
- the configured token can access that repository
- `github_tag` exactly matches a published stable GitHub Release tag
- the release is not a draft or prerelease
- `github_asset` exactly matches the uploaded ZIP filename

A Git tag without an associated GitHub Release is not used.

## No ZIP Asset or Multiple ZIP Assets

Secure File Access considers uploaded `.zip` release assets only.

- If the release has exactly one ZIP asset, it is selected automatically.
- If the release has no ZIP assets, upload one to the release.
- If the release has multiple ZIP assets, set the exact `github_asset` filename.

Example:

```text
[file_access github_repo="littlebizzy/private-plugin" github_asset="private-plugin.zip"]
```

Source archives generated automatically by GitHub are not release assets and are not selected.

## GitHub Rate Limit or Temporary Failure

The plugin distinguishes GitHub rate limits and temporary GitHub server failures without displaying API response bodies.

It does not automatically retry failed requests. Reload the WordPress page later to create a new protected link and try again.

## GitHub Did Not Provide a Temporary Download URL

Secure File Access requires GitHub's asset API to return a temporary redirect. It does not proxy or stream a directly returned ZIP body through PHP.

The temporary URL must:

- use HTTPS
- include a valid host
- contain no embedded username or password
- pass WordPress URL safety validation

A response that does not meet those requirements is rejected.

## Protected Link Is Invalid or Expired

`This download link is invalid or has expired.` can mean that:

- more than 15 minutes passed
- the link already completed a successful redirect
- the temporary record was removed by WordPress or an object cache
- the stored record is incomplete

Reload the page to generate a new link. Protected links cannot be transferred between user accounts.

## Plugin Deactivation or Deletion

Deactivation preserves all settings.

Deleting the plugin removes only the saved `sfa_github_token`, including per-site tokens across Multisite. Other saved options are preserved for possible reinstallation.

See [Shortcode](shortcode.md) for attributes and examples, [Settings](settings.md) for saved defaults and credentials, and [Downloads](downloads.md) for the complete protected-link flow.
