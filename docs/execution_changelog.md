# Execution Changelog — pkg_cbuseronlinestatus

## 2026-02-22 (module timeout UX clarification)

- Improved module configuration UX to reduce confusion after Milestone 10b (plugin timeout via application state):
  - Added a display-only custom module field `RuntimeTimeoutField.php` that shows the **effective runtime timeout** in module settings (`plugin-published timeout` when available, otherwise the module fallback timeout).
  - Kept the module's editable timeout parameter for resilience, but relabeled it from **Online Timeout** to **Fallback Timeout** and updated its description to clarify it is used only when the system plugin does not publish a runtime timeout (disabled, unverified, unavailable, etc.).
  - Added module language strings for the new runtime/fallback labels and descriptions.
  - Reordered the module's shared basic fields to match the existing CB Online module's settings order more closely (pre/post/user text before limit/exclude, etc.) and aligned shared labels/descriptions with CB wording to reduce admin-side relearning.
- Updated `README.md` module parameter documentation to describe the new runtime display field and fallback behavior.

## 2026-02-22 (code review v7 fixes)

- Addressed all 3 findings from code review v7 (`docs/code_review.v7.md`):
  - (Critical) **#1 — Missing database injection**: Added `$plugin->setDatabase(...)` call in `services/provider.php` so `DatabaseAwareTrait::getDatabase()` works when `computeAndStoreHashes()` runs on first request after install/update.
  - (High) **#2 — `upstream_hashes` not declared in XML**: Added a hidden `upstream_hashes` field (`type="hidden"`, `filter="raw"`) to `cbuseronlinestatus.xml` so Joomla preserves the stored hash JSON when an admin saves plugin configuration.
  - (Medium) **#3 — `isset()` on nullable hashes**: Replaced `isset($storedHashes[$path])` with `array_key_exists($path, $storedHashes)` in `verifyUpstreamHashes()` so that tracked files stored with a `null` hash (file absent) are correctly recognized as present in the stored state.
  - (High) **#4 — `getCache` method error**: Replaced `$this->getApplication()->getCache(...)` with `\Joomla\CMS\Factory::getCache(...)` in `saveParams()` since the former method does not exist on the `AdministratorApplication` instance in Joomla 4/5.

## 2026-02-22 (Milestone 10 implementation)

- Implemented Milestone 10 (Post-release hardening) in full:
  - **10a**: Tracked and verified upstream hashes for `StatusField.php` and `MessageTable.php`. Created `UpstreamHashesField` and updated `CbUserOnlineStatus.php` with the integrity gate and admin verification logic.
  - **10b**: Decoupled the module from the plugin's internal namespace by reading `cbuserstatus.timeout` from Joomla's application state in the module helper. The plugin publishes this value during `onAfterInitialise()`.
  - **10c**: Added Kunena session timeout synchronization via the new `timeout_source` parameter and the custom `OnlineTimeoutField` class.
- Added corresponding language strings to the plugin's en-GB `.ini` file.
- Completed execution plan review v8 which verified the final corrections to 10a, 10c.

## 2026-02-22 (review v7 — remove self-disabling, frontend leak guard, Kunena field fix)

- Completed review v7 (`docs/execution_plan.review.v7.md`). 1 high, 1 medium, 1 low-severity finding.
- Amended execution plan to address all 3 findings:
  - (High) **Milestone 10a**: Removed `disablePlugin()` entirely. The plugin no longer sets `enabled = 0` in any scenario. If a frontend visitor's request triggers a hash mismatch, `disablePlugin()` would have prevented the admin from ever seeing the warning (the plugin would not load on subsequent requests). Instead, the plugin stays enabled and relies purely on `hashes_verified = 0` to gate the autoloader. The plugin continues running on every request but only displays warnings on admin pages and only registers the autoloader when hashes are verified.
  - (Medium) **Milestone 10a**: Wrapped all warning messages (`PLG_SYSTEM_CBUSERONLINESTATUS_HASHES_NOT_VERIFIED` and `PLG_SYSTEM_CBUSERONLINESTATUS_UPSTREAM_CHANGED`) in `isClient('administrator')` checks. Previously, if a frontend visitor triggered the hash computation or mismatch, the warning (containing internal file paths) could leak into the frontend template output.
  - (Low) **Milestone 10c**: Changed `OnlineTimeoutField` from `readonly="readonly"` to `disabled="disabled"` with the `name` attribute removed when in Kunena mode. A `readonly` field is still submitted in the POST payload, which would overwrite the user's stored manual timeout value with the Kunena value on every save. Using `disabled` (with no `name`) ensures the Kunena value is display-only. When the admin switches back to "manual" mode, the original stored manual value is preserved.
- Updated Decision Log entry for 10a to reflect the no-disable approach and cite review v7.
- Updated `PLG_SYSTEM_CBUSERONLINESTATUS_UPSTREAM_CHANGED` language string: "disabled" → "deactivated".
- Updated Revision History.

## 2026-02-22 (Milestone 10a simplification — standard radio field)

- **Milestone 10a** simplified: replaced AJAX-based "Mark as Verified" button (`VerifyHashesField.php` + `com_ajax` + `onAjaxCbuseronlinestatus()` + JavaScript) with a standard Joomla `radio` field (`hashes_verified`) in the plugin settings.
  - Admin reviews hashes in a read-only `UpstreamHashesField.php` custom field (displays file paths and SHA256 hashes as a table, no interactivity).
  - Admin sets `hashes_verified` radio to "Verified" and clicks the standard Joomla Save button — no AJAX, no custom buttons, no JavaScript needed.
  - Eliminated: `VerifyHashesField.php`, `onAjaxCbuseronlinestatus()` event handler, `com_ajax` routing, inline JavaScript.
  - Added: `UpstreamHashesField.php` (read-only display), standard `radio` field definition in manifest XML.
- Updated Decision Log, directory structure (`VerifyHashesField.php` → `UpstreamHashesField.php`), and Revision History.

## 2026-02-22 (Milestone 10a/10c redesign per user feedback)

- **Milestone 10a** redesigned from passive admin warning to active verification gate with self-disabling:
  - Plugin computes and stores SHA256 hashes in its own `#__extensions` params on first run (using `saveParams()` pattern from `plg_system_stats`).
  - Hashes start unverified (`hashes_verified = 0`); plugin refuses to register autoloader until admin explicitly verifies.
  - Admin verifies via a custom `VerifyHashesField` form field in the plugin configuration, which renders tracked files, hashes, and a "Mark as Verified" button (AJAX via `com_ajax` + `onAjaxCbuseronlinestatus()`).
  - On subsequent requests, if any tracked file's hash mismatches the stored value, the plugin recomputes hashes, resets `hashes_verified` to `0`, calls `disablePlugin()` (same pattern as `plg_system_stats`), and warns the admin.
  - Admin must review changes, re-verify hashes, and manually re-enable the plugin.
- **Milestone 10c** updated: when `timeout_source` is `kunena`, the `online_timeout` field is shown as a readonly greyed-out input displaying the actual Kunena `sessionTimeOut` value, instead of being hidden. Implemented via a custom `OnlineTimeoutField` form field class extending `NumberField`.
- Updated Decision Log entry for 10a to reflect the verification gate design.
- Updated directory structure to include `VerifyHashesField.php` and `OnlineTimeoutField.php`.
- Updated Revision History.

## 2026-02-22 (code review v6 — post-release hardening milestone)

- Completed code review v6 (`docs/code_review.v6.md`). The review approved the package as production-ready and identified two areas of risk (upstream synchronization, plugin coupling) with proposed solutions.
- Added Milestone 10 to execution plan with three sub-milestones:
  - **10a — Upstream file-hash verification**: Store SHA256 hashes of the original CB `StatusField.php` and `MessageTable.php` in the plugin. On admin page loads (throttled to once per 24 hours), hash the live CB files and compare. If a mismatch is detected, enqueue a Joomla warning message alerting the administrator. This addresses the residual risk of CB updates silently being masked by the overrides (review Section 5.1).
  - **10b — Module-plugin namespace decoupling**: Replace the module helper's direct `class_exists` check against the plugin's FQCN with a Joomla application state read (`Factory::getApplication()->get('cbuserstatus.timeout')`). The plugin sets this value in `onAfterInitialise`. This eliminates the compile-time namespace dependency while preserving the same priority chain (review Section 5.2).
  - **10c — Kunena timeout synchronization**: Add a `timeout_source` list parameter (`manual` / `kunena`) to the plugin. When set to `kunena`, the plugin reads `KunenaConfig::getInstance()->sessionTimeOut` at runtime instead of using its own `online_timeout` field. If Kunena is not installed, falls back to manual value with a warning. This keeps CB online status and Kunena forum session timeout in sync automatically (new requirement from user).
- Added 3 decisions to Decision Log (file-hash strategy, application-state decoupling, Kunena sync).
- Updated Progress, Revision History, and this changelog.

## 2026-02-22 (post-release repo review)

- Reviewed the repository against the released implementation and current docs.
- Updated `README.md` to reflect actual module parameters (including pre/post text, user text, thousands separator), clarify CB hook compatibility wording, and document real build prerequisites (`awk`, POSIX shell/Git Bash on Windows, `sha256sum` or `shasum`).
- Updated `docs/execution_plan.md` to reflect the released state:
  - Marked Milestones 1-9 as completed for v1.0.0.
  - Added a post-release documentation-sync progress entry.
  - Populated `Outcomes & Retrospective`.
- Added root `RELEASE.md` pointer to `docs/RELEASE.md` so the repo matches the documented/expected release-notes location.
- Fixed duplicate CB hook invocation in `mod_cbuseronlinestatus/tmpl/default.php` by caching hook output once before the list-render condition (prevents double execution/duplicate side effects when `cb_plugins` is enabled).
- Added `README.md` sections for known limitations and post-Community-Builder-upgrade verification checks.

## 2026-02-21 (review v6)

- Completed review v6 (`docs/execution_plan.review.v6.md`). No blockers; 1 low-severity reproducibility issue.
- Amended execution plan to address the finding:
  - (Low) Milestone 9 revert verification commands now use full relative paths from the site root (`components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.php` and `modules/mod_comprofileronline/mod_comprofileronline.php`) instead of bare filenames. Previously the commands were not copy/paste-runnable from the production repo root without manual `cd`.
- Updated Progress and Revision History sections.

## 2026-02-21 (review v5)

- Completed review v5 (`docs/execution_plan.review.v5.md`). No blockers; 2 medium and 1 low validation-command correctness issues.
- Amended execution plan to address all 3 findings:
  - (Medium) V1 baseline check for StatusField now searches for `time() - $lastTime` instead of `UNIX_TIMESTAMP`. The StatusField patch uses PHP's `time()` function, not SQL `UNIX_TIMESTAMP()` — the previous pattern would never match, making the baseline check a false pass.
  - (Medium) Milestone 9 revert check for StatusField now searches for `time() - $lastTime` instead of `UNIX_TIMESTAMP`. Same root cause as above — the revert check could not distinguish patched from original because the search pattern was absent in both versions.
  - (Low) Milestone 9 revert check for module now searches for the exact expression `UNIX_TIMESTAMP() - time <= 1800` instead of the broad token `1800`. The broad token could match unrelated occurrences, producing a noisy/unreliable revert check.
- Updated Progress and Revision History sections.

## 2026-02-21 (review v4)

- Completed review v4 (`docs/execution_plan.review.v4.md`). No blocking findings; 2 low-severity wording/ordering issues.
- Amended execution plan to address both findings:
  - (Low) Fixed autoloader description in Milestone 2: replaced "returns `true`, preventing CB's own autoloader from loading the original" with "loads the override class first; once the class exists, CB's own autoloader is not used for that class". The autoloader method is `void`, not boolean — the previous wording was inconsistent with the Milestone 1/Interfaces specification.
  - (Low) Reordered line-count evidence in Surprises & Discoveries to list PowerShell first and Bash/WSL second, matching the Windows-primary workspace. Content unchanged; only presentation order adjusted to reduce review churn.
- Updated Progress and Revision History sections.

## 2026-02-21 (review v3)

- Completed review v3 (`docs/execution_plan.review.v3.md`) against live code, project rules, and prior reviews v1/v2.
- Review v3 found no architecture blockers; 3 remaining findings (1 high, 1 medium, 1 low).
- Amended execution plan to address all 3 findings:
  - (High) Added `is_readable($path)` guard to autoloader before `require` calls. Raw `require` on a missing file causes a PHP fatal, not a silent fallback. The guard ensures CB's own autoloader handles the class when override files do not yet exist (Milestone 1 timeframe). Updated: Milestone 1 narrative, Milestone 3 code snippet (now uses `$map` + `is_readable` pattern), and Interfaces section autoloader method description.
  - (Medium) Replaced UTF-8 box-drawing characters in directory tree with plain ASCII (`|--`, `` `-- ``). UTF-8 box chars rendered as mojibake (`ÃÄÄ`, `ÀÄÄ`) on some Windows tools interpreting the file as Windows-1252.
  - (Low) Normalized MessageTable line-count from "639 lines" to "~640 lines" across all occurrences (Surprises & Discoveries, Decision Log, Milestone 3). Added note that `wc -l` returns 639 while PowerShell may report 640 due to trailing-newline handling.
- Added 2 new decisions to Decision Log (autoloader guard, ASCII tree).
- Updated Progress and Revision History sections.

## 2026-02-21 (review v2)

- Completed review v2 (`docs/execution_plan.review.v2.md`) against live code, project rules, and review v1 baseline.
- Review v2 found no architecture blockers; 6 consistency/safety/standards findings.
- Amended execution plan to address 5 of 6 findings:
  - (High) Removed conditional wording `// Desired fix (if override is feasible):` in Artifacts section for PMS override. Now reads `// Override (with timeout — mandatory for v1):`, consistent with Milestone 3 mandatory decision.
  - (High) Added `protected $autoloadLanguage = true;` to `CbUserOnlineStatus` class spec (Interfaces section) and Milestone 1 description. Required by `AGENTS.md` for namespaced service-provider plugins to load runtime language files.
  - (Medium) Resolved Milestone 1 sequencing contradiction: clarified that the autoloader contains FQCN routing logic but override files are not yet created (created in Milestones 2 and 3); until then, CB's own autoloader loads the originals.
  - (Medium) Normalized shell command examples: added PowerShell variants alongside Bash/WSL for `wc -l` evidence, V1 baseline check (`grep` → `Select-String`), Milestone 9 revert check, and Concrete Steps directory creation (`mkdir -p` → `New-Item`).
  - (Medium) Added explicit SQL value hardening guidance: timeout must be `(int)` cast before interpolation; exclude user IDs must each be `(int)` cast before `IN (...)` clause construction.
- Finding #4 (MessageTable line-count discrepancy) verified as false positive: `wc -l` confirms 639 lines, matching the plan. Review claimed 513 lines, which was incorrect.
- Added 4 new decisions to Decision Log (autoloadLanguage, SQL hardening, shell normalization, Milestone 1 sequencing).
- Updated Progress and Revision History sections.

## 2026-02-21 (review v1)

- Completed review v1 (`docs/execution_plan.review.v1.md`) against live production code.
- Amended execution plan to address all 6 review findings:
  - (Blocker) Updated baseline to reflect that `StatusField.php` and `mod_comprofileronline.php` mode 1 are already timeout-patched in production. Remaining unpatched areas: mode 9, mode 6, mode 7, and PMS `MessageTable`.
  - (Blocker) Replaced hardcoded `client_id = 0` in all module SQL examples with `shared_session` branching (`IS NULL` vs `= 0`), matching the original CB module behavior.
  - (Blocker) Made PMS Milestone 3 decision-complete: MessageTable override is mandatory for v1, no manual-patch fallback. Confirmed FQCN as `CB\Plugin\PMS\Table\MessageTable`. Documented drift risk mitigation.
  - (High) Specified CB plugin hook compatibility strategy: reuse `modCBOnlineHelper::getPlugins()` from the original module, guarded by `class_exists`, at all original hook points.
  - (High) Replaced debug-style verification in Milestone 5 with deterministic validation matrix (V1–V9) in Milestone 8 covering all modes, shared-session, PMS, StatusField, and error checks.
  - (Medium) Fixed plan hygiene: added timestamps to Progress, fixed directory tree (added `Table/` for MessageTable override), tightened scope wording, fixed trailing spaces.
- Added 3 new decisions to Decision Log (PMS mandatory, shared_session, plugin hook strategy).
- Added 3 entries to Surprises & Discoveries (existing patches, shared_session branching, MessageTable size).

## 2026-02-20

- Created initial execution plan based on analysis of all online-status locations in the production Joomla/CB site.
- Identified 6 locations needing fixes (2 already patched in production, 4 unpatched).
- Confirmed Kunena is independently handling online timeout (showSessionType=2, showSessionStartTime=1800) — no action needed.
- Decided on two-extension package approach: system plugin (StatusField + MessageTable overrides via autoloader) + custom module (session-query modes with timeout filter).
- Documented all decisions, trade-offs, and the full directory structure in the execution plan.
