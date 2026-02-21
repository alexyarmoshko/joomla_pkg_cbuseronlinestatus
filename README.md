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
| **Site Module** | Replaces the CB Online Users module for session-dependent modes (1, 6, 7, 9) |

## Installation

1. Download `pkg_cbuseronlinestatus-x.y.z.zip` from the [Releases](https://github.com/alexyarmoshko/joomla_pkg_cbuseronlinestatus/releases) page.
2. Install via **System → Install → Extensions** in Joomla admin.
3. **Enable the plugin** at **System → Manage → Plugins** → search for "CB User Online Status".
4. Publish the **mod_cbuseronlinestatus** module from **Content → Site Modules** and configure the mode.

## Configuration

### System Plugin Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| Online Timeout | 1800 | Seconds of inactivity before a user is considered offline |

### Module Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| Mode | Online Users | One of: Online Users (1), Online Connections (9), Online Statistics (6), User Census (7) |
| Online Timeout | 1800 | Fallback if the system plugin is not installed |
| User Limit | 30 | Maximum users shown (modes 1, 9) |
| Exclude User IDs | — | Comma-separated user IDs to exclude |
| Exclude Self | No | Hide the current user from results |
| Label Style | Text Only | Text, icon, or both (modes 6, 7) |
| Load CB Template CSS | Yes | Include CB's template stylesheet |
| Enable CB Plugin Hooks | No | Fire `onAfterOnlineModule` events (requires original CB module helper) |

## Building from Source

Requires `make`, `zip`, `sha256sum`, and `sed`.

```bash
make dist
```

Output ZIPs are written to `installation/`.

## Requirements

- Joomla 5.x
- PHP 8.1+
- Community Builder 2.9+

## License

GNU General Public License version 2 or later. See [LICENSE](LICENSE).

## Author

**Yak Shaver** — [kayakshaver.com](https://www.kayakshaver.com) — me@kayakshaver.com
