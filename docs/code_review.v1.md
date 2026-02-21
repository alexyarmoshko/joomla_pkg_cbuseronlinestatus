# Code Review v1

Date: 2026-02-21  
Scope: Current implementation on `main` (`a2f6f24`).

## Findings (Ordered by Severity)

### 1. Critical: Package manifest points to child ZIP filenames that are never produced

- Evidence:
  - `pkg_cbuseronlinestatus.xml:14` references `plg_system_cbuseronlinestatus.zip`.
  - `pkg_cbuseronlinestatus.xml:15` references `mod_cbuseronlinestatus.zip`.
  - `Makefile:15` defines plugin ZIP output as `plg_system_cbuseronlinestatus-$(VERSION).zip`.
  - `Makefile:16` defines module ZIP output as `mod_cbuseronlinestatus-$(VERSION).zip`.
  - `Makefile:59` and `Makefile:60` include only those versioned ZIP names in the package archive.
- Impact:
  - The package manifest references files that are not present in the built package, so package installation/update can fail when Joomla resolves sub-extension archives.
- Recommended fix:
  - Keep filenames consistent between `pkg_cbuseronlinestatus.xml` and `Makefile` (either use non-versioned filenames in build output, or update manifest entries to versioned names at build time).

### 2. High: Prepended autoloader can terminate requests if class probing happens before CB bootstrap

- Evidence:
  - `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php:99` requires override files as soon as the class name matches.
  - `plg_system_cbuseronlinestatus/src/Field/StatusField.php:19` contains `defined('CBLIB') or die();`.
  - `plg_system_cbuseronlinestatus/src/Table/MessageTable.php:22` contains `defined('CBLIB') or die();`.
- Impact:
  - Any early `class_exists()` / autoload probe for those CB class names before `CBLIB` is defined will hard-stop the request.
- Recommended fix:
  - In `overrideAutoloader()`, return early unless `defined('CBLIB')` is true before requiring override files.

### 3. Medium: Guest count in statistics mode ignores timeout, so stale sessions remain counted

- Evidence:
  - User online query applies timeout at `mod_cbuseronlinestatus/src/Helper/CbUserOnlineStatusHelper.php:250`.
  - Guest query does not apply timeout at `mod_cbuseronlinestatus/src/Helper/CbUserOnlineStatusHelper.php:277`.
- Impact:
  - Mode 6 reports stale guest totals while user totals are timeout-filtered, producing inconsistent and inflated statistics.
- Recommended fix:
  - Apply the same timeout condition to the guest-session query.

### 4. Medium: SHA256 publication step in build pipeline is currently a no-op

- Evidence:
  - `Makefile:51`, `Makefile:54`, and `Makefile:69` replace `__SHA256_*` placeholders.
  - `mod_cbuseronlinestatus.update.xml:14`, `plg_system_cbuseronlinestatus.update.xml:14`, and `pkg_cbuseronlinestatus.update.xml:14` contain empty `<sha256></sha256>` tags (no placeholders exist).
- Impact:
  - `make dist` does not populate SHA256 values into update manifests, reducing update metadata integrity and breaking expected release automation.
- Recommended fix:
  - Either restore placeholder tokens in XML files or switch `sed` expressions to replace the value inside `<sha256>...</sha256>` directly.

## Testing/Verification Gaps

- No automated test coverage was found for:
  - Package installability (manifest/file-name consistency).
  - Timeout behavior parity across all module counters (including guests).
  - Autoloader safety when CB classes are probed before CB bootstrap.
