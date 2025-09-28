# Secure File Access

Easy file downloads for WordPress

## Changelog

### 1.0.0
- supports shortcode `[file_access]` for secure file download links with role and WooCommerce subscription-based access control
- creates admin settings page under Options with tabbed navigation for Access Defaults and Error Messages
- integrated optional WooCommerce Subscriptions check for active subscription based file access
- administrators always have access to files displayed on the frontend
- default roles and WooCommerce subscription settings are blank
- default label for file download buttons is "Download File"
- supports customizable error messages for “no access”, “invalid URL”, and “not logged in” states
- supports localization/translation
- supports Git Updater
- supports PHP 7.0 to 8.3
- supports Multisite
