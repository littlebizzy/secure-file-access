# Secure File Access

Easy file downloads for WordPress with role and WooCommerce subscription-based access control.

## Usage

Configure default subscription product IDs, WordPress roles, the download button label, and error messages under **Settings > Secure File Access**.

Basic usage:

```text
[file_access url="https://example.com/plugin.zip"]
```

Override the configured defaults for a specific download:

```text
[file_access url="https://example.com/plugin.zip" label="Download Plugin" subscriptions="123,456" roles="customer,shop_manager"]
```

Visitors must be logged in. Administrators always have access. Other users receive access when they match any listed role or have an active or pending-cancel WooCommerce subscription for any listed product ID. Shortcode `roles` and `subscriptions` values override their corresponding defaults.

WooCommerce Subscriptions is optional. When it is not active, only role-based access is available. If no roles or subscription product IDs are configured, only administrators receive access.

## Compatibility

- tested up to WordPress 7.0
- supports PHP 7.0 to 8.4
- supports Multisite
- supports Git Updater

## Changelog

### 1.1.0
- preserves access for pending-cancel WooCommerce subscriptions until the prepaid term ends
- validates file URLs before rendering download links
- adds shortcode usage and compatibility documentation

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