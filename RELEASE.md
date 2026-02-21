# Release Notes — v1.0.0

**Release Date:** 2026-02-21

## What's New

Initial release of the Yak Shaver CB User Online Status package.

### System Plugin (`plg_system_cbuseronlinestatus`)

- **StatusField override** — Applies configurable timeout to the online/offline indicator on profiles and user lists.
- **MessageTable override** — Applies the same timeout to PMS notification suppression, so offline users correctly receive email notifications.
- **Prepended autoloader** — Non-destructive interception of CB class loading; disabling the plugin instantly restores vanilla CB behavior.

### Site Module (`mod_cbuseronlinestatus`)

- **Mode 1 — Online Users** — Lists users with active sessions within the timeout window.
- **Mode 9 — Online Connections** — Lists online connections of the current user.
- **Mode 6 — Online Statistics** — Shows online, offline, and guest counts with timeout filtering.
- **Mode 7 — User Census** — Shows total users, latest user, online count, and registration breakdowns.
- Preserves `shared_session` branching in all queries.
- Optional CB plugin hook compatibility via the `cb_plugins` parameter.

## Changelog

### Fixed

- **(Critical)** Package build now patches the manifest at build time with versioned child ZIP filenames, then restores the source file; all installation artifacts are consistently versioned.
- **(High)** Prepended autoloader now checks `defined('CBLIB')` before loading override files, preventing hard-stops when other extensions probe CB class names before CB has bootstrapped.
- **(Medium)** Guest count in statistics mode (mode 6) now applies the same timeout filter as the user online count, eliminating inflated/stale guest totals.
- **(Medium)** Restored `__SHA256_*__` placeholder tokens in update XML files so `make dist` correctly populates SHA256 hashes.

## Upgrade Notes

- Fresh install only (v1.0.0).
- After installation, **enable the system plugin** and **publish the module**.
- If you previously patched `StatusField.php` or `mod_comprofileronline.php` manually, revert those patches after installing this package.

## Requirements

- Joomla 5.x, PHP 8.1+, Community Builder 2.9+
