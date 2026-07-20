# Secure File Access

Easy file downloads for WordPress with role and WooCommerce subscription-based access control.

## Description

Secure File Access creates protected download links using the `[file_access]` shortcode. Visitors must be logged in, administrators always have access, and other users receive access when they match any configured WordPress role or have an active or pending-cancel WooCommerce subscription for any configured product ID.

Default subscription product IDs, WordPress roles, the download button label, and frontend error messages can be configured under **Settings > Secure File Access**. Shortcode `roles` and `subscriptions` values override their corresponding defaults for individual downloads.

WooCommerce Subscriptions is optional. When it is not active, only role-based access is available. If no roles or subscription product IDs are configured, only administrators receive access. File URLs are sanitized and unsupported protocols are rejected before download links are rendered.

Basic usage:

```text
[file_access url="https://example.com/plugin.zip"]
```

Override the configured defaults for a specific download:

```text
[file_access url="https://example.com/plugin.zip" label="Download Plugin" subscriptions="123,456" roles="customer,shop_manager"]
```

## Changelog

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