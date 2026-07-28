# Downloads

Secure File Access replaces the destination in the page HTML with a short-lived local download link.

The plugin supports two download sources:

- a normal HTTP or HTTPS URL
- a generated GitHub Release ZIP archive or an explicitly named uploaded ZIP asset

Normal URLs are not copied by WordPress. Uploaded GitHub assets redirect directly when GitHub supplies a temporary URL or stream unchanged through a private temporary file when GitHub returns `200 OK`. Generated GitHub archives are downloaded, normalized, and streamed through WordPress using temporary files.

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

- it has already completed a successful redirect or local GitHub ZIP preparation
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
2. rejects draft and prerelease releases
3. normalizes the release tag's generated ZIP archive when `github_asset` is omitted
4. requires an exact uploaded ZIP asset when `github_asset` is supplied

A supplied `github_tag` must match an exact published stable release. A missing tag does not fall back to the latest release.

A supplied `github_asset` must exactly match an uploaded ZIP asset in the selected release. A missing asset does not fall back to the generated archive.

### Generated Archive Normalization

When `github_asset` is omitted, Secure File Access:

1. requests GitHub's generated archive endpoint with redirects disabled
2. validates GitHub's temporary HTTPS download URL
3. creates a unique private temporary workspace and requires `0700` permissions
4. creates the source ZIP inside that workspace and safely streams GitHub's archive into it
5. requires the archive to contain exactly one safe root directory
6. rejects unsafe paths and symbolic links
7. extracts the archive into the workspace's separate `source` directory
8. moves and renames the generated root under `package/repository-name`
9. rebuilds the ZIP with the repository name as both the filename and root directory
10. streams the rebuilt ZIP to the authorized user
11. removes the source ZIP, extracted files, rebuilt ZIP, and workspace

For `littlebizzy/force-https`, the result is:

```text
force-https.zip
└── force-https/
```

The rebuilt archive is streamed from disk rather than assembled as one complete in-memory response. WordPress uses `ZipArchive` when available and its bundled PclZip library otherwise.

Generated archives are rebuilt for each successful protected request. Normalized packages are not cached.

### Uploaded Release Assets

When `github_asset` is supplied, Secure File Access requests the exact uploaded ZIP asset through the authenticated GitHub API.

When GitHub returns a temporary HTTPS redirect:

1. Secure File Access validates the redirect URL
2. WordPress redirects the authorized user's browser to that URL
3. GitHub transfers the uploaded asset directly to the user

When GitHub returns `200 OK` with the ZIP body:

1. Secure File Access creates a unique private temporary workspace with `0700` permissions
2. WordPress streams the asset unchanged into a temporary file inside that workspace
3. WordPress streams the file to the authorized user using the uploaded asset filename
4. the temporary file and workspace are removed

Uploaded assets are not extracted, inspected internally, renamed, or rebuilt. Their contents and original filename remain controlled by the publisher.

### Local ZIP Streaming

Generated archives and direct `200 OK` uploaded assets use the same local ZIP sender. Before sending the response, Secure File Access removes removable output buffers and requires that no active output buffer or previously sent response headers remain.

If those conditions cannot be met, the plugin closes the file, removes the temporary workspace, and stops with an error instead of sending a corrupted or fully buffered ZIP response.

### GitHub Response Handling

The release metadata request must return `200 OK` because the plugin needs the release information from its JSON response.

The generated-archive API request must return `301`, `302`, `303`, `307`, or `308` with a valid `Location` header. A direct `200 OK` archive response is rejected because generated archives require the validated temporary-URL normalization flow.

The uploaded-asset API request accepts either a valid temporary redirect or `200 OK`. Redirects preserve the direct GitHub transfer. A direct `200 OK` asset response is streamed unchanged through the private WordPress workspace.

For generated archives, WordPress streams the validated temporary URL into the private workspace. That file request must return `200 OK` with a non-empty ZIP file so the archive can be normalized locally.

Common API responses are handled as follows:

| Response | Handling | Reason |
| --- | --- | --- |
| `200 OK` from release metadata | Accepted | The JSON release record is required. |
| `301`, `302`, `303`, `307`, or `308` from an archive or asset API request | Accepted | The `Location` URL can be validated and used for the next download step. |
| `200 OK` from an uploaded-asset API request | Accepted | The unchanged ZIP body is streamed through the private workspace. |
| `200 OK` from a generated-archive API request | Rejected | Generated archives require a temporary URL before normalization. |
| `204 No Content` | Rejected | No ZIP body or temporary download URL is available. |
| `206 Partial Content` | Rejected | The API request did not provide the required complete download flow. |
| `304 Not Modified` | Rejected | The plugin does not use cached GitHub API download responses. |
| `400 Bad Request` or `422 Unprocessable Content` | Rejected | GitHub did not accept the archive or asset request. |
| `401 Unauthorized` | Rejected | The configured GitHub token was rejected. |
| `403 Forbidden` | Rejected | GitHub denied access or reported a rate limit. |
| `404 Not Found` | Rejected | The repository, release, archive, or asset is missing or inaccessible. |
| `429 Too Many Requests` | Rejected | GitHub's API rate limit was reached. |
| `500`–`599` | Rejected | GitHub reported a temporary server failure. |
| Any other response | Rejected | The response does not match the required metadata or download flow. |

Every temporary redirect must use HTTPS, include a valid host, contain no embedded username or password, and pass WordPress URL safety validation.

The GitHub personal access token is never added to the protected link or temporary redirect URL. Redirected assets expose GitHub's temporary URL to the authorized browser. Direct `200 OK` assets and generated archives are transferred from WordPress, so the user receives a local ZIP response.

GitHub release metadata is resolved when the protected link is opened. Secure File Access does not cache release, archive, or asset metadata.

## GitHub Errors

GitHub and archive-processing failures are converted to concise messages without displaying GitHub response bodies, credentials, or temporary filesystem paths.

Secure File Access distinguishes:

- a rejected token
- an API rate limit
- access denied by GitHub
- a missing or inaccessible repository, release, archive, or asset
- a temporary GitHub server failure
- an archive or asset download, validation, extraction, rename, rebuild, or streaming failure

The plugin does not automatically retry failed GitHub requests. Reloading the WordPress page creates a new protected link when the user still has access.

## GitHub Release Selection

When `github_tag` is omitted, the repository's latest published stable GitHub Release is used. A Git tag without an associated GitHub Release is not considered.

When `github_tag` is supplied, that exact published stable release must exist. The plugin does not fall back to the latest release.

When `github_asset` is omitted, the plugin normalizes GitHub's generated ZIP archive for the selected release tag. Uploaded release assets are not selected automatically.

When `github_asset` is supplied, its filename must exactly match an uploaded ZIP asset in the selected release. The plugin does not fall back to the generated archive when the named asset is missing.

A generated source archive reflects the repository contents at the release tag. Secure File Access changes only the outer ZIP filename and top-level directory name; it does not otherwise modify the repository files.

## Temporary Files and Cleanup

Generated archive processing requires a writable WordPress temporary directory and enough disk space for the downloaded ZIP, extracted files, and rebuilt ZIP. A direct `200 OK` uploaded asset requires temporary space for one unchanged ZIP file.

Temporary ZIP files are created inside a request-specific private `0700` workspace before GitHub content is written. The workspace path is unique per request and registered for shutdown cleanup. The plugin also removes it immediately after a completed stream or redirect fallback when possible. Cleanup is attempted after failures and interrupted requests; no ZIP cache is retained.

## Privacy and Caching

Protected download responses are marked private and non-cacheable.

The plugin also sends a no-referrer policy before redirects and local GitHub ZIP responses.

Normal URL destinations and redirected GitHub assets receive normal connection information directly from the visitor's browser. Direct `200 OK` assets and generated GitHub archives are first fetched by the WordPress server, then transferred from WordPress to the authorized user.

See [Shortcode](shortcode.md) for usage examples and [Settings](settings.md) for access defaults, error messages, and GitHub token configuration.
