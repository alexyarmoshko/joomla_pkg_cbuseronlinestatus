# Release Notes - v1.0.0

**Release Date:** 2026-02-23

## What's New

Initial release of the Yak Shaver CB User Online Status package.

### System Plugin (`plg_system_cbuseronlinestatus`)

- **StatusField override** - Applies configurable timeout to the online/offline indicator on profiles and user lists.
- **MessageTable override** - Applies the same timeout to PMS notification suppression, so offline users correctly receive email notifications.
- **Prepended autoloader** - Non-destructive interception of CB class loading; disabling the plugin instantly restores vanilla CB behavior.
- **Upstream file tracking + verification gate** - Computes and stores SHA256 hashes for the overridden CB files and keeps overrides inactive until an administrator reviews hashes and sets **Hashes Verified** to **Verified**.
- **Automatic re-verification on CB updates** - If a tracked CB file changes, the plugin refreshes stored hashes, resets **Hashes Verified** to **Not Verified**, and warns administrators to review and re-verify before overrides reactivate.
- **Optional Kunena timeout synchronization** - **Timeout Source** parameter (`Manual` / `Kunena Forum`) can read Kunena's `sessionTimeOut` at runtime; if Kunena is unavailable, the plugin falls back to the manual timeout and warns in admin.
- **Admin UI timeout field behavior** - In Kunena mode, the **Online Timeout** field is display-only and does not overwrite the saved manual timeout value when plugin settings are saved.

### Site Module (`mod_cbuseronlinestatus`)

- **Mode 1 — Online Users** — Lists users with active sessions within the timeout window.
- **Mode 9 — Online Connections** — Lists online connections of the current user.
- **Mode 6 — Online Statistics** — Shows online, offline, and guest counts with timeout filtering.
- **Mode 7 - User Census** - Shows total users, latest user, online count, and registration breakdowns.
- Preserves `shared_session` branching in all queries.
- Optional CB plugin hook compatibility via the `cb_plugins` parameter.
- **Runtime Timeout (display-only)** - Module settings show the effective timeout currently in use (plugin-published runtime timeout when available, otherwise the module fallback timeout).
- **Fallback Timeout labeling** - The editable module timeout parameter is labeled/documented as a fallback used when the system plugin timeout is unavailable (for example plugin disabled, not verified, or unavailable).

## Changelog

### Fixed

- **(Critical)** Package build now patches the manifest at build time with versioned child ZIP filenames, then restores the source file; all installation artifacts are consistently versioned.
- **(High)** Prepended autoloader now checks `defined('CBLIB')` before loading override files, preventing hard-stops when other extensions probe CB class names before CB has bootstrapped.
- **(Medium)** Guest count in statistics mode (mode 6) now applies the same timeout filter as the user online count, eliminating inflated/stale guest totals.
- **(Medium)** SHA256 injection in `make dist` now replaces the value inside `<sha256>` tags directly, working with empty source tags instead of requiring placeholder tokens.

## Upgrade Notes

- Fresh install only (v1.0.0).
- After installation, **enable the system plugin**, open its settings, review **Upstream File Tracking**, set **Hashes Verified** to **Verified**, then **publish the module**.
- If you previously patched `StatusField.php` or `mod_comprofileronline.php` manually, revert those patches after installing this package.

## Requirements

- Joomla 5.x, PHP 8.1+, Community Builder 2.9+
