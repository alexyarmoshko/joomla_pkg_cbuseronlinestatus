# Yak Shaver CB User Online Status

Joomla 5 package that fixes stale online-status indicators in Community Builder (CB).

## Problem

CB considers a user "online" if their session row exists in `#__session`, regardless of how old it is. This leads to:

- **Stale online indicators** — users show as "online" on profiles and user lists long after they have left the site.
- **Suppressed PMS notifications** — private message notifications are not sent because the recipient appears online.
- **Inflated online counts** — the Online Statistics and User Census modules report inaccurately high online user counts.

## Solution

This package applies a configurable timeout (default: 30 minutes) to all session-based online checks:

| Component | What It Fixes |
|-----------|---------------|
| **System Plugin** | Overrides `StatusField` (profile/list indicator) and `MessageTable` (PMS notification suppression) |
| **Site Module** | Replacement for CB's online module in session-dependent modes only (1, 6, 7, 9) |

## Installation

1. Download `pkg_cbuseronlinestatus-x.y.z.zip` from the [Releases](https://github.com/alexyarmoshko/joomla_pkg_cbuseronlinestatus/releases) page.
2. Install via **System → Install → Extensions** in Joomla admin.
3. **Enable the plugin** at **System → Manage → Plugins** → search for "Yak Shaver CB User Online Status".
4. Open the plugin configuration, review **Upstream File Tracking**, set **Hashes Verified** to **Verified**, and save.
5. Publish the **mod_cbuseronlinestatus** module from **Content → Site Modules** and configure the mode.
6. Keep using CB's original online module for non-session modes (for example Latest Visitors / Latest Registrations).

The plugin computes and stores tracked Community Builder file hashes automatically, but its overrides remain inactive until **Hashes Verified** is set to **Verified** in plugin settings.

## Configuration

### System Plugin Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| Timeout Source | Manual | `Manual` uses the plugin timeout value below; `Kunena Forum` reads Kunena's `sessionTimeOut` automatically |
| Online Timeout | 1800* | Seconds of inactivity before a user is considered offline (manual mode); becomes a display-only field in Kunena mode |
| Tracked CB File Hashes | Auto (display-only) | Read-only SHA256 hashes of the CB files this plugin overrides; computed on first request and refreshed when tracked files change |
| Hashes Verified | Not Verified | Must be set to `Verified` after reviewing tracked hashes before the plugin activates its overrides; resets to `Not Verified` when tracked files change |

### Module Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| Display Mode | Online Users | One of: Online Users (1), Online Connections (9), Online Statistics (6), User Census (7) |
| Runtime Timeout | 1800** | Effective timeout currently used by the module (plugin runtime timeout when available; otherwise the fallback timeout) |
| Fallback Timeout | 1800 | Used only when the system plugin does not publish a runtime timeout (for example plugin disabled, not verified, or unavailable) |
| Limit | 30 | Maximum users shown (modes 1, 9) |
| Exclude Users | — | Comma-separated user IDs to exclude |
| Exclude Self | No | Hide the current user from results |
| Pre-text | — | HTML/text shown before output (supports CB substitutions) |
| Post-text | — | HTML/text shown after output (supports CB substitutions) |
| User-text | — | Custom per-user row text with CB substitutions (modes 1, 9) |
| Label Display | Text Only | Text, icon, or both (modes 6, 7) |
| Thousands Separator | `,` | Number formatting separator (modes 6, 7) |
| Load Template | Yes | Include CB's template stylesheet |
| CB Plugins integration | No | Call CB online-module hook points via `modCBOnlineHelper::getPlugins()` (requires original `mod_comprofileronline` helper) |

`*` In plugin settings, when `Timeout Source` is `Kunena Forum`, the `Online Timeout` field is display-only and shows Kunena's current `sessionTimeOut` value. The previously saved manual timeout is preserved for fallback use.

`**` The runtime timeout field is display-only in module settings (disabled) and reflects the current effective value at page load.

Standard Joomla advanced module settings (layout, module class suffix, caching) are also available and are not listed above.

## Building from Source

Requires a POSIX shell environment (use **Git Bash** on Windows) with `make`, `awk`, `sed`, `zip`, and either `sha256sum` or `shasum`.

```bash
make dist
```

Output ZIPs are written to `installation/`.

Release notes for the current package version are in [`RELEASE.md`](docs/RELEASE.md).

## Requirements

- Joomla 5.x
- PHP 8.1+
- Community Builder 2.9+

## Known Limitations

- The PMS fix uses a full override of CB's `MessageTable` class (`plg_system_cbuseronlinestatus/src/Table/MessageTable.php`), so Community Builder updates can introduce upstream changes that need to be merged into this package.
- The optional `cb_plugins` compatibility mode depends on the original CB online module helper (`modules/mod_comprofileronline/helper.php`) still being installed.

## CB Upgrade Checks

After upgrading Community Builder, review and re-test before publishing a package update:

1. Diff upstream CB `StatusField.php` and `MessageTable.php` against this package's override classes and merge relevant upstream changes.
2. Re-test profile/list online indicators and PMS offline-notification behavior.
3. Re-test module modes 1, 6, 7, and 9 (including `shared_session` sites if applicable).

## License

GNU General Public License version 2 or later. See [LICENSE](LICENSE).

