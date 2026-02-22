# Code Review v3

Date: 2026-02-22  
Scope: Follow-up review of `main` at `023f205` ("Second code review amendments").

## Findings (Ordered by Severity)

### 1. Medium: SHA256 publication pipeline regressed to a no-op

- Evidence:
  - `Makefile:51`, `Makefile:54`, and `Makefile:72` still attempt token replacement for `__SHA256_*__` placeholders.
  - `mod_cbuseronlinestatus.update.xml:14`, `plg_system_cbuseronlinestatus.update.xml:14`, and `pkg_cbuseronlinestatus.update.xml:14` now contain empty tags (`<sha256></sha256>`) with no placeholder token to replace.
  - `git show 023f205` explicitly changed all three update feeds from placeholder values back to empty tags.
- Impact:
  - `make dist` cannot inject hashes into update feeds, so published update metadata ships without SHA256 values.
  - This removes checksum verification data from extension update descriptors and breaks the intended release automation.
- Recommended fix:
  - Either restore `__SHA256_PLG__`, `__SHA256_MOD__`, and `__SHA256_PKG__` tokens in the three update XML files, or change the Makefile `sed` commands to replace the value inside `<sha256>...</sha256>` directly.

### 2. Low: Release notes are now inconsistent with repository behavior

- Evidence:
  - `RELEASE.md:31` states placeholders were restored so `make dist` populates SHA256 hashes.
  - Current update manifests have empty `<sha256>` nodes and no replacement token (`mod_cbuseronlinestatus.update.xml:14`, `plg_system_cbuseronlinestatus.update.xml:14`, `pkg_cbuseronlinestatus.update.xml:14`).
- Impact:
  - Maintainers can rely on incorrect release documentation and assume integrity metadata is being generated when it is not.
- Recommended fix:
  - Align `RELEASE.md` with actual behavior, or (preferred) implement/fix SHA population so the release note remains true.

## Verification of Previous Findings

- `v2#1` (build artifact names vs update URLs): **Fixed**.
  - Child ZIP names are versioned again in `Makefile:15-16`, and update URLs point to versioned assets.
  - Package manifest is patched/restored at build time (`Makefile:57-58`, `Makefile:65`).
- `v1#2` (autoloader pre-CB hard-stop risk): **Still fixed**.
  - `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php:96` guards on `defined('CBLIB')`.
- `v1#3` (guest timeout omission): **Still fixed**.
  - `mod_cbuseronlinestatus/src/Helper/CbUserOnlineStatusHelper.php:278` applies timeout for guest sessions.

## Validation Performed

- Syntax checks:
  - `php -l plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php`
  - `php -l mod_cbuseronlinestatus/src/Helper/CbUserOnlineStatusHelper.php`
  - `php -l plg_system_cbuseronlinestatus/src/Field/StatusField.php`
  - `php -l plg_system_cbuseronlinestatus/src/Table/MessageTable.php`
  - `php -l mod_cbuseronlinestatus/src/Dispatcher/Dispatcher.php`
  - Result: no syntax errors in reviewed PHP files.
- Consistency check:
  - `rg -n "__SHA256_(PLG|MOD|PKG)__|<sha256>" -g "*.xml" .`
  - Result: only empty `<sha256>` nodes found; no replacement tokens present.
- Build dry-run:
  - Attempted `make -n dist` via bash in this environment.
  - Result: unable to execute due local shell access error (`Bash/Service/CreateInstance/E_ACCESSDENIED`).
