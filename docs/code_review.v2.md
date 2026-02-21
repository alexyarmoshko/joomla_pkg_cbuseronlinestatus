# Code Review v2

Date: 2026-02-21  
Scope: Follow-up review of commit `4ba4848` ("First code review amendments").

## Findings (Ordered by Severity)

### 1. High: Build artifact names now diverge from extension update download URLs

- Evidence:
  - `Makefile:15` and `Makefile:16` now build child artifacts as unversioned names:
    - `plg_system_cbuseronlinestatus.zip`
    - `mod_cbuseronlinestatus.zip`
  - `plg_system_cbuseronlinestatus.update.xml:12` still points to:
    - `.../plg_system_cbuseronlinestatus-1.0.0.zip`
  - `mod_cbuseronlinestatus.update.xml:12` still points to:
    - `.../mod_cbuseronlinestatus-1.0.0.zip`
- Impact:
  - If release assets are published directly from `make dist` output, plugin/module update feeds will reference files that do not exist (HTTP 404), breaking extension self-updates.
- Recommended fix:
  - Either restore versioned child ZIP names in `Makefile`, or update both `.update.xml` download URLs to the unversioned filenames and keep that convention consistent across releases.

## Verification of v1 Findings

- `v1#1` (critical package-manifest child ZIP mismatch): **Fixed**.
  - `Makefile` child ZIP names now match `pkg_cbuseronlinestatus.xml` entries.
- `v1#2` (autoloader pre-CB hard-stop risk): **Fixed**.
  - `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php:96` now guards on `defined('CBLIB')`.
- `v1#3` (guest timeout omission in mode 6): **Fixed**.
  - `mod_cbuseronlinestatus/src/Helper/CbUserOnlineStatusHelper.php:278` now applies timeout to guest sessions.
- `v1#4` (SHA256 replacement no-op): **Fixed**.
  - Placeholder tokens restored in all three update feeds:
    - `mod_cbuseronlinestatus.update.xml:14`
    - `plg_system_cbuseronlinestatus.update.xml:14`
    - `pkg_cbuseronlinestatus.update.xml:14`

## Validation Performed

- Syntax check:
  - `php -l plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php`
  - `php -l mod_cbuseronlinestatus/src/Helper/CbUserOnlineStatusHelper.php`
  - Result: no syntax errors.
