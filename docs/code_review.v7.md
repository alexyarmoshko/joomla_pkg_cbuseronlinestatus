# Code Review v7 - Milestone 10 Implementation

**Date**: 2026-02-22  
**Reviewer**: Codex (GPT-5)  
**Scope**: Milestone 10 changes (10a hash verification gate, 10b module/plugin decoupling, 10c Kunena timeout sync)

## Findings

### 1. Critical - Plugin now depends on `DatabaseAwareTrait`, but no database is injected

Milestone 10a introduces `saveParams()` and calls `$this->getDatabase()` from the system plugin, but the plugin service provider still only injects the application object.

- First-run path triggers DB usage immediately: `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php:73`
- DB access happens here: `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php:179`
- Service provider constructs plugin and sets only application: `plg_system_cbuseronlinestatus/services/provider.php:28`, `plg_system_cbuseronlinestatus/services/provider.php:32`

### Impact

On the first request after install/update (when hashes are missing), `computeAndStoreHashes()` can fail before persisting hashes, blocking the new verification flow.

### Recommended fix

Inject the Joomla database into the plugin in `services/provider.php` (for example via `$plugin->setDatabase(...)`) before returning the plugin instance.

## 2. High - `upstream_hashes` param is persisted programmatically but not declared in plugin form XML

The plugin reads and writes `params.upstream_hashes`, and the custom display field expects it in form data, but the plugin XML does not define an `upstream_hashes` field.

- Plugin reads stored JSON: `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php:69`
- Plugin writes stored JSON: `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php:139`
- Display field reads `upstream_hashes` from form: `plg_system_cbuseronlinestatus/src/Field/UpstreamHashesField.php:39`
- XML defines `upstream_hashes_display` and `hashes_verified`, but not `upstream_hashes`: `plg_system_cbuseronlinestatus/cbuseronlinestatus.xml:52`, `plg_system_cbuseronlinestatus/cbuseronlinestatus.xml:58`

### Impact

When an admin saves the plugin configuration, Joomla form submission may omit `upstream_hashes`, causing the stored hash JSON to be dropped from params. That can force hash recomputation / re-verification loops and break the intended review flow.

### Recommended fix

Add a hidden `upstream_hashes` field to the plugin XML so Joomla preserves the stored hash JSON on normal saves.

## 3. Medium - Missing tracked files are treated as changed forever because `isset()` is used on nullable hashes

`computeAndStoreHashes()` intentionally stores `null` when a tracked file does not exist. Later, verification uses `isset($storedHashes[$path])`, which returns `false` for keys whose value is `null`.

- `null` is stored for missing files: `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php:137`
- Presence check uses `isset()`: `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php:151`

### Impact

If a tracked file is absent, verification marks it as changed on every request, repeatedly resetting `hashes_verified` and preventing stable verification.

### Recommended fix

Use `array_key_exists($path, $storedHashes)` for key presence checks, then compare the stored nullable value to the current nullable hash.

## Validation Performed

- PHP syntax lint passed:
  - `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php`
  - `plg_system_cbuseronlinestatus/src/Field/OnlineTimeoutField.php`
  - `plg_system_cbuseronlinestatus/src/Field/UpstreamHashesField.php`

## Notes

- This was a static code review only; no Joomla runtime verification was performed in this review pass.
