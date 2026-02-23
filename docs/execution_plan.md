# Yak Shaver CB User Online Status — Joomla Package to Fix Stale Online Indicators

This Execution Plan is a living document. The sections `Progress`, `Surprises & Discoveries`, `Decision Log`, and `Outcomes & Retrospective` must be kept up to date as work proceeds.

## Purpose / Big Picture

Community Builder (CB) for Joomla considers a user "online" whenever a row exists in the `#__session` database table for that user — regardless of how old the session is. Joomla sessions can linger well past a user's actual departure (the session lifetime may be 60+ minutes, and garbage collection is non-deterministic). This causes stale "online" indicators: users who left hours ago still appear as online in profile pages, online-user modules, and statistics counts. Worse, private-message email notifications can be silently suppressed for "online" users who are actually long gone.

After this package is installed, a user will be treated as "online" only if their most recent session activity occurred within a configurable time window (default: 30 minutes). This covers all CB locations that check or display online status: the profile/list indicator (`StatusField`), the PMS notification suppression (`MessageTable`), and the four session-based module modes (Online Users, Online Connections, Online Statistics, User Census). It does not cover `cbConnection.php`'s `isOnline` column (not rendered anywhere) or Kunena (which already handles this independently via its own `showSessionType=2` / `showSessionStartTime=1800` settings).

The package replaces two files currently patched in the production Joomla site (`modules/mod_comprofileronline/mod_comprofileronline.php` and `components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.php`). After installation, those files can be reverted to their CB originals, eliminating update-conflict risk.

To verify: install the package, revert the two patched CB files, and observe that (a) the online-users module shows only recently active users, (b) user profiles show the correct online/offline dot, and (c) PMS email notifications are sent to users whose sessions have gone stale.

## Progress

- [x] (2026-02-20) Initial execution plan drafted.
- [x] (2026-02-21) Review v1 completed; plan amended to address all findings.
- [x] (2026-02-21) Review v2 completed; plan amended to address 5 of 6 findings (finding #4 verified as false positive — ~640 lines confirmed).
- [x] (2026-02-21) Review v3 completed; plan amended to address all 3 findings (autoloader guard, ASCII tree, line-count normalization).
- [x] (2026-02-21) Review v4 completed; plan amended to address both low-severity findings (autoloader wording, line-count evidence order).
- [x] (2026-02-21) Review v5 completed; plan amended to address all 3 findings (V1 baseline pattern, Milestone 9 revert checks).
- [x] (2026-02-21) Review v6 completed; plan amended to address 1 low-severity finding (Milestone 9 revert command paths).
- [x] (2026-02-21) Milestone 1 completed: System plugin `plg_system_cbuseronlinestatus` - project scaffolding, class autoloader for StatusField and MessageTable overrides.
- [x] (2026-02-21) Milestone 2 completed: StatusField override class with configurable timeout.
- [x] (2026-02-21) Milestone 3 completed: MessageTable override for PMS notification fix (mandatory for v1).
- [x] (2026-02-21) Milestone 4 completed: Custom module `mod_cbuseronlinestatus` - scaffolding and manifest.
- [x] (2026-02-21) Milestone 5 completed: Module helper with session queries (modes 1, 6, 7, 9) and `shared_session` branching.
- [x] (2026-02-21) Milestone 6 completed: Module dispatcher and layouts with CB plugin hook compatibility.
- [x] (2026-02-21) Milestone 7 completed: Package manifest `pkg_cbuseronlinestatus`, Makefiles, README.
- [x] (2026-02-21) Milestone 8 completed for v1.0.0 release validation (deterministic validation matrix V1-V9).
- [x] (2026-02-21) Milestone 9 completed for v1.0.0 release rollout (revert patched CB files, final validation).
- [x] (2026-02-22) Post-release repo review completed; documentation updated to reflect released implementation state and current README behavior/details.
- [x] (2026-02-22) Code review v6 (`docs/code_review.v6.md`) completed; findings documented for post-release hardening.
- [x] (2026-02-22) Review v7 completed; plan amended to address all 3 findings (remove disablePlugin, admin-only warnings, disabled Kunena field).
- [x] (2026-02-22) Review v8 completed; plan approved.
- [x] (2026-02-22) Milestone 10a completed: Upstream file-hash verification with admin verification gate.
- [x] (2026-02-22) Milestone 10b completed: Decouple module from plugin namespace via Joomla application state.
- [x] (2026-02-22) Milestone 10c completed: Kunena timeout synchronization via `timeout_source` parameter and custom form field.
- [x] (2026-02-22) Post-10b module admin UX clarification completed: added runtime timeout display field and relabeled module timeout as fallback.

## Surprises & Discoveries

- Observation: The production `StatusField.php` and `mod_comprofileronline.php` (mode 1) are already timeout-patched with a hardcoded 1800-second window. The initial plan described them as unpatched.
  Evidence: `StatusField.php` line 43: `$isOnline = ( $lastTime != null ) && ((time() - $lastTime)<=1800);`. `mod_comprofileronline.php` line 400: `AND (UNIX_TIMESTAMP() - time <= 1800)`.

- Observation: The CB module uses `shared_session` branching for `client_id` in every session query — not a hardcoded `= 0`. This is critical for shared-session Joomla deployments.
  Evidence: `$_CB_framework->getCfg('shared_session') ? " IS NULL" : " = 0"` appears at lines 128, 187, 250, 262, 273, and 397 of `mod_comprofileronline.php`.

- Observation: `MessageTable.php` is ~640 lines, well above the initially estimated ~200 line threshold for "feasible full override". The override is still viable but carries higher drift risk on CB updates.
  Evidence: PowerShell `(Get-Content MessageTable.php | Measure-Object -Line).Lines` reports 640; Bash/WSL `wc -l MessageTable.php` returns 639 (difference is trailing-newline handling). Both confirm ~640 lines.

## Decision Log

- Decision: Use a class-autoloader approach (via `spl_autoload_register` with prepend) in the system plugin to override `StatusField` and `MessageTable`, rather than modifying `CBframework::userOnlineLastTime()`.
  Rationale: `CBframework` is a massive class (~1200 lines) instantiated during CB's early bootstrap; overriding it via autoloader or proxy risks breaking `instanceof` checks and internal state. `StatusField` and `MessageTable` are small, focused classes where a full-body override is safe and maintainable.
  Date/Author: 2026-02-20 / AI

- Decision: Create a standalone replacement module `mod_cbuseronlinestatus` rather than patching `mod_comprofileronline` for the four session-querying modes (1, 6, 7, 9).
  Rationale: The module entry point is procedural PHP (not a class), so there is no clean override mechanism. A standalone module is independent of CB updates and uses Joomla 5's module architecture (namespace, dispatcher, helper).
  Date/Author: 2026-02-20 / AI

- Decision: The `cbConnection.php` `isOnline` column (lines 543/570/598 of `libraries/CBLib/CB/Legacy/cbConnection.php`) is not addressed.
  Rationale: The column is computed via a LEFT JOIN on `#__session` without a time filter, but it is not rendered in any CB template — all display code uses `$cbUser->getField('onlinestatus')` which routes through StatusField. The practical risk is negligible.
  Date/Author: 2026-02-20 / AI

- Decision: Kunena's online status is not addressed by this package.
  Rationale: Kunena already enforces a 30-minute window independently via its config (`showSessionType=2`, `showSessionStartTime=1800`). No patching needed.
  Date/Author: 2026-02-20 / AI

- Decision: Base name for all extensions is `cbuseronlinestatus`. No site-specific names are used.
  Rationale: Per user requirement. Enables reuse on any Joomla+CB site.
  Date/Author: 2026-02-20 / AI

- Decision: The custom module supports only the four session-dependent modes (Online Users, Online Connections, Online Statistics, User Census). Other CB module modes (Latest Visitors, Latest Registrations, Custom Latest, etc.) do not query the session table and should continue using the original CB module.
  Rationale: Only session-based modes exhibit the stale-online bug. The other modes query `#__comprofiler` / `#__users` tables directly and are unaffected.
  Date/Author: 2026-02-20 / AI

- Decision: PMS MessageTable override is mandatory for v1. No manual-patch fallback.
  Rationale: Review v1 identified the "maybe manual patch" language as decision-incomplete. The PMS notification fix is a core goal of the package. Although the class is ~640 lines, the full-body override is the only viable approach. Drift risk is managed by documenting an upgrade diff check per CB release in the README.
  Date/Author: 2026-02-21 / AI (review v1)

- Decision: Preserve `shared_session` branching (`client_id IS NULL` vs `= 0`) in all module session queries.
  Rationale: Review v1 identified hardcoded `client_id = 0` in the plan as a blocker. The original CB module uses `$_CB_framework->getCfg('shared_session')` branching at every `client_id` condition. Dropping this would break online counts on shared-session sites.
  Date/Author: 2026-02-21 / AI (review v1)

- Decision: Reuse `modCBOnlineHelper::getPlugins()` from the original CB module for plugin hook compatibility in the replacement module templates.
  Rationale: Review v1 flagged under-specification of CB plugin hook parity. The original templates call this helper at multiple hook points. Including the original `helper.php` at render time (guarded by `class_exists`) preserves third-party CB plugin integration and degrades gracefully if the original module is uninstalled.
  Date/Author: 2026-02-21 / AI (review v1)

- Decision: Set `protected $autoloadLanguage = true;` in the `CbUserOnlineStatus` plugin class.
  Rationale: Review v2 identified that project instructions (`AGENTS.md`) require namespaced service-provider plugins to enable automatic language loading. Without this, runtime language strings (parameter labels, descriptions) would not load in the frontend.
  Date/Author: 2026-02-21 / AI (review v2)

- Decision: Explicitly cast timeout to `(int)` and exclude user IDs to `(int)` before SQL interpolation in module helper queries.
  Rationale: Review v2 flagged that the plan showed interpolated `{$timeout}` without specifying value hardening. Project data-access rules (`AGENTS.md:55-57`) require parameterized/bound values and no string concatenation. Integer casting satisfies this for the CB database API context.
  Date/Author: 2026-02-21 / AI (review v2)

- Decision: Provide both Bash/WSL and PowerShell variants for all command-line examples in the plan.
  Rationale: Review v2 noted the repo context is Windows-centered but commands used Unix utilities (`grep`, `wc`, `mkdir -p`). Dual variants ensure novice-safety regardless of shell.
  Date/Author: 2026-02-21 / AI (review v2)

- Decision: Milestone 1 autoloader contains FQCN routing logic for both overrides, but override files are not created until Milestones 2 and 3.
  Rationale: Review v2 flagged a contradiction between "not yet loading" and the autoloader description. Clarified that the autoloader code exists but the target files do not, so CB's own autoloader loads the originals until override files are present.
  Date/Author: 2026-02-21 / AI (review v2)

- Decision: Guard autoloader `require` calls with `is_readable($path)` before including override files.
  Rationale: Review v3 identified that raw `require` on a missing file causes a fatal error, not a silent fallback. The `is_readable` guard ensures CB's own autoloader handles the class if the override file does not exist (e.g., during Milestone 1 before override files are created).
  Date/Author: 2026-02-21 / AI (review v3)

- Decision: Use plain ASCII tree characters (`|--`, `` `-- ``) in the directory structure diagram instead of UTF-8 box-drawing characters.
  Rationale: Review v3 confirmed that UTF-8 box-drawing chars render as mojibake on some Windows tools (Windows-1252 interpretation). Plain ASCII ensures readability across all editors and terminals.
  Date/Author: 2026-02-21 / AI (review v3)

- Decision: Use SHA256 file-hash verification with an admin verification gate and override deactivation (without disabling the plugin), rather than CB version tracking or dynamic on-the-fly patching.
  Rationale: Code review v6 (Section 5.1) identified three strategies. File hashing is the most reliable: it detects actual file-level changes regardless of CB version number, produces no false positives (unlike version tracking), and avoids the fragility and security concerns of `eval()`-based dynamic patching. The verification gate ensures the plugin never activates overrides without explicit admin sign-off. When upstream files change, the plugin deactivates overrides by setting `hashes_verified = 0` but remains enabled (`enabled = 1`) so it can continue warning the admin on every backend request. The plugin must NOT call `disablePlugin()` because if a frontend visitor triggers the hash mismatch, disabling the plugin would prevent the admin from ever seeing the warning (review v7, finding #1). All warning messages are guarded by `isClient('administrator')` to prevent leaking file paths to frontend visitors (review v7, finding #2). Hash state is persisted in `#__extensions` params using the `saveParams()` pattern from Joomla core's `plg_system_stats`. Admin verification uses a standard Joomla radio field (`hashes_verified`) and the normal plugin save flow — no AJAX, no custom buttons, no JavaScript. A read-only custom `UpstreamHashes` form field displays the computed hashes for review.
  Date/Author: 2026-02-22 / AI (code review v6, revised per user feedback and review v7)

- Decision: Use Joomla application state (`Application::set/get`) to decouple the module from the plugin's namespace, rather than custom events, plugin parameter lookup, or DI container registration.
  Rationale: Code review v6 (Section 5.2) identified four decoupling strategies. Application state is the most efficient for sharing a single integer value. Custom events add dispatcher overhead, plugin parameter lookup requires JSON decoding, and DI container registration is excessive boilerplate. Application state is a one-liner on each side and preserves the same priority chain (plugin > module param > default).
  Date/Author: 2026-02-22 / AI (code review v6)

- Decision: Add a `timeout_source` plugin parameter to optionally read the online timeout from Kunena's `KunenaConfig::sessionTimeOut` instead of the plugin's own `online_timeout` field.
  Rationale: Kunena forum runs on the same site and has its own session timeout (default 1800s). When both are active, divergent timeout values cause UX inconsistency (user appears online in CB but offline in Kunena or vice versa). The `timeout_source` parameter lets administrators keep both in sync without manual coordination. The Kunena class is accessed via `class_exists` guard so the plugin degrades gracefully if Kunena is not installed.
  Date/Author: 2026-02-22 / AI (code review v6)

## Outcomes & Retrospective

- Outcome: v1.0.0 package was implemented and released with three build artifacts: system plugin, site module, and package ZIP (`pkg_cbuseronlinestatus-1.0.0.zip`).
- Outcome: The released implementation covers all targeted stale-online locations in scope: `StatusField`, PMS `MessageTable`, and CB online module modes 1/6/7/9 via `mod_cbuseronlinestatus`.
- Outcome: Build tooling generates versioned child ZIP filenames and patches update XML metadata (download URL + SHA256) during `make dist`.
- Outcome: The repo now includes release notes (`docs/RELEASE.md`, with root `RELEASE.md` pointer) and a README aligned with the released module/plugin parameters and build prerequisites.
- Residual risk: Full `MessageTable` override remains a CB-upgrade drift surface; compare against upstream CB on each CB update before releasing package updates.
- Retrospective: The autoloader-based plugin override plus standalone Joomla 5 module avoided core/CB file patch maintenance while keeping rollback straightforward (disable plugin / unpublish module).

## Context and Orientation

### The problem in detail

CB stores user session data in Joomla's `#__session` table. When a user visits the site, a row is created with a `time` column (Unix timestamp of last activity) and `guest = 0` for authenticated users. When the user closes their browser, the session row is not immediately deleted — it persists until Joomla's session garbage collector runs (based on `session.gc_probability` and `session.gc_maxlifetime` PHP settings). This means a user who left 3 hours ago may still have a session row.

CB's original (unpatched) code checks for online status in two patterns:

Pattern A — method-based: `$_CB_framework->userOnlineLastTime($userId)` (defined in `libraries/CBLib/CB/Legacy/CBframework.php` at line 1197) runs `SELECT MAX(time) FROM #__session WHERE userid = ? AND guest = 0`. It returns the timestamp or null. Callers then check `!= null` to determine "online". This pattern is used by:

- `StatusField::getField()` in `components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.php` — the online/offline indicator on profiles and user lists. **Current state: already timeout-patched in production** (line 43 adds `&& ((time() - $lastTime)<=1800)`).
- `MessageTable::store()` in `components/com_comprofiler/plugin/user/plug_pms_mypmspro/library/Table/MessageTable.php` line 133 — suppresses email notifications if the recipient is "online". **Current state: NOT patched.** The FQCN is `CB\Plugin\PMS\Table\MessageTable` (namespace confirmed from the file header).

Pattern B — direct SQL: The module `modules/mod_comprofileronline/mod_comprofileronline.php` queries `#__session` directly in four modes. All queries use `shared_session` branching: `$_CB_framework->getCfg('shared_session') ? " IS NULL" : " = 0"` for the `client_id` condition, which must be preserved in the replacement module.

- Mode 1 (default "Online Users", line 394): `SELECT DISTINCT userid FROM #__session WHERE client_id ... AND guest = 0 ORDER BY time DESC`. **Current state: already timeout-patched in production** (line 400 adds `AND (UNIX_TIMESTAMP() - time <= 1800)`).
- Mode 9 ("Online Connections", line 119): Same pattern with a JOIN to `#__comprofiler_members`. **Current state: NOT patched** (no timeout filter).
- Mode 7 ("User Census", line 185): `SELECT COUNT(*) FROM #__session WHERE client_id ... AND guest = 0` (the `$onlineUsers` count). **Current state: NOT patched.**
- Mode 6 ("Online Statistics", line 248): Same count query plus offline and guest counts. **Current state: NOT patched.**

The remaining stale areas that this package must address are: mode 9 (Online Connections), mode 6 (Online Statistics), mode 7 (User Census), and the PMS notification check in `MessageTable::store()`. The already-patched locations (StatusField and mode 1) will also be covered by the package so the manual patches can be reverted.

### Files in this project

The package repo lives at `..\joomla_pkg_cbuseronlinestatus\`.

### Existing extension conventions

All extensions follow the user's established repo conventions (see `~\repos\joomla_plg_cbgjguestaccess\` for plugin and `~\repos\joomla_mod_ystides\` for module reference):

- Author: `Yak Shaver`, email `me@kayakshaver.com`, URL `https://www.kayakshaver.com`
- GitHub owner: `alexyarmoshko`
- License: GPL v2+
- Joomla 5 architecture: namespaced classes, `services/provider.php` DI container, `src/` directory structure
- Plugin namespace pattern: `YakShaver\Plugin\System\Cbuseronlinestatus`
- Module namespace pattern: `Joomla\Module\Cbuseronlinestatus`
- Build: Makefile producing ZIP into `installation/` directory with update XML sha256 patching
- Language files under `language/en-GB/`

### Template files (from `~\repos\agent-ordnung\user\templates\`)

- `template.variables.txt` — author metadata values
- `php.header.template.php` — PHP file header block
- `xml.manifest.attribution.template.xml` — XML manifest author/copyright/license block
- `Makefile.plugin.template` — Plugin Makefile skeleton
- `Makefile.module.template` — Module Makefile skeleton
- `Makefile.template` — Generic package Makefile skeleton

## Plan of Work

The work is organized as a monorepo at `~\repos\joomla_pkg_cbuseronlinestatus\` that produces three installable ZIPs: the system plugin, the site module, and a Joomla package that bundles both.

### Repository directory structure

```text
    joomla_pkg_cbuseronlinestatus/
    |-- docs/
    |   |-- execution_plan.md          (this file)
    |   `-- execution_changelog.md
    |-- plg_system_cbuseronlinestatus/
    |   |-- cbuseronlinestatus.xml      (plugin manifest)
    |   |-- services/
    |   |   `-- provider.php
    |   |-- src/
    |   |   |-- Extension/
    |   |   |   `-- CbUserOnlineStatus.php   (main plugin class)
    |   |   |-- Field/
    |   |   |   |-- StatusField.php          (StatusField override)
    |   |   |   |-- OnlineTimeoutField.php   (custom form field, Milestone 10c)
    |   |   |   `-- UpstreamHashesField.php  (custom form field, Milestone 10a)
    |   |   `-- Table/
    |   |       `-- MessageTable.php         (MessageTable override)
    |   |-- language/
    |   |   `-- en-GB/
    |   |       |-- plg_system_cbuseronlinestatus.ini
    |   |       `-- plg_system_cbuseronlinestatus.sys.ini
    |   |-- index.html
    |   `-- LICENSE
    |-- mod_cbuseronlinestatus/
    |   |-- mod_cbuseronlinestatus.xml  (module manifest)
    |   |-- services/
    |   |   `-- provider.php
    |   |-- src/
    |   |   |-- Dispatcher/
    |   |   |   `-- Dispatcher.php
    |   |   `-- Helper/
    |   |       `-- CbUserOnlineStatusHelper.php
    |   |-- tmpl/
    |   |   |-- default.php             (Online Users list)
    |   |   |-- default_statistics.php  (Online Statistics)
    |   |   `-- default_census.php      (User Census)
    |   |-- language/
    |   |   `-- en-GB/
    |   |       |-- mod_cbuseronlinestatus.ini
    |   |       `-- mod_cbuseronlinestatus.sys.ini
    |   |-- index.html
    |   `-- LICENSE
    |-- pkg_cbuseronlinestatus.xml      (package manifest)
    |-- Makefile                         (top-level: builds plugin, module, package ZIPs)
    |-- installation/                    (build output)
    |-- LICENSE
    |-- README.md
    `-- RELEASE.md
```

### Milestone 1: System plugin scaffolding

After this milestone, the plugin project structure exists with all boilerplate files, the Joomla manifest, the DI service provider, and the main plugin class. The plugin subscribes to `onAfterInitialise` and registers a prepended PHP autoloader. The autoloader contains the FQCN routing logic for both `StatusField` and `MessageTable`, but the actual override class files do not exist yet — they are created in Milestones 2 and 3 respectively. The autoloader guards each `require` with `is_readable($path)` before including the file; if the file does not exist, the autoloader returns without loading anything, and CB's own autoloader loads the original class as usual.

Files to create:

`plg_system_cbuseronlinestatus/cbuseronlinestatus.xml` — Joomla plugin manifest. Type `plugin`, group `system`, method `upgrade`. Namespace `YakShaver\Plugin\System\Cbuseronlinestatus` with path `src`. One configurable parameter: `online_timeout` (integer field, default `1800`, label "Online Timeout (seconds)", description "Users with session activity older than this many seconds are considered offline. Default: 1800 (30 minutes)."). Include language folder and update server pointing to `https://raw.githubusercontent.com/alexyarmoshko/joomla_pkg_cbuseronlinestatus/refs/heads/main/plg_system_cbuseronlinestatus.update.xml`.

`plg_system_cbuseronlinestatus/services/provider.php` — DI provider following the pattern from the `remember` plugin. Creates `CbUserOnlineStatus` instance, sets application and database.

`plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php` — Main plugin class in namespace `YakShaver\Plugin\System\Cbuseronlinestatus\Extension`. Extends `CMSPlugin`, implements `SubscriberInterface`, uses `DatabaseAwareTrait`. Sets `protected $autoloadLanguage = true;` to ensure runtime language file loading for this namespaced service-provider plugin. Subscribes to `onAfterInitialise`. In the handler, if this is a site client request, calls `spl_autoload_register` with a prepended autoloader method. The autoloader checks if the requested class is `CB\Plugin\Core\Field\StatusField` or `CB\Plugin\PMS\Table\MessageTable` and, if so, loads the corresponding override from the plugin's own `src/Field/StatusField.php` or `src/Table/MessageTable.php`. The timeout value is read from `$this->params->get('online_timeout', 1800)` and stored in a static property accessible by the override classes.

`plg_system_cbuseronlinestatus/language/en-GB/plg_system_cbuseronlinestatus.ini` and `.sys.ini` — Language strings for the plugin name, description, and parameter labels.

`plg_system_cbuseronlinestatus/index.html` — Empty HTML file (Joomla convention).

Verification: Install the plugin ZIP on the DDEV site, enable it in Extensions > Plugins. Check that the site loads without errors and the plugin appears in the plugin list.

### Milestone 2: StatusField override

After this milestone, the plugin intercepts the loading of CB's `StatusField` class and loads a custom version that adds the configurable timeout check. The online/offline indicator on user profiles and user lists correctly shows "offline" for users whose session is older than the timeout.

File to create:

`plg_system_cbuseronlinestatus/src/Field/StatusField.php` — This file defines class `CB\Plugin\Core\Field\StatusField` (same fully qualified name as the original). It is a copy of the original `StatusField` class from `components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.original.php` with one modification: in the `getField()` method, the line:

```php
    $isOnline = ( $lastTime != null );
```

is replaced with:

```php
    $timeout = \YakShaver\Plugin\System\Cbuseronlinestatus\Extension\CbUserOnlineStatus::getOnlineTimeout();
    $isOnline = ( $lastTime != null ) && ( ( time() - $lastTime ) <= $timeout );
```

The `getOnlineTimeout()` method is a public static method on the main plugin class that returns the configured timeout value (with a fallback of 1800 seconds if the plugin is not yet loaded). This method must be added to `CbUserOnlineStatus.php`.

The autoloader logic in the plugin class works as follows: when PHP attempts to load `CB\Plugin\Core\Field\StatusField`, the prepended autoloader checks if this exact FQCN is requested. If yes, it loads the override class first; once the class exists, CB's own autoloader is not used for that class. Because CB uses PSR-4 autoloading through `CBLib`, and our autoloader is prepended, our version loads first.

Important: the override file must declare the exact same namespace (`CB\Plugin\Core\Field`) and class name (`StatusField`) and must include all the same `use` statements and methods as the original. The class must NOT extend the original (since we are replacing it entirely). Copy all three methods verbatim (`getField`, `prepareFieldDataSave`, `bindSearchCriteria`) and modify only the `$isOnline` assignment in `getField`.

Verification: With the plugin installed and enabled, navigate to a user's profile page. The online status indicator should show "OFFLINE" for users who have not had session activity in the last 30 minutes, even if they have a `#__session` row. To test: log in as a test user, note they show as "ONLINE", then manually update their session `time` in the database to be 31 minutes old (`UPDATE #__session SET time = UNIX_TIMESTAMP() - 1860 WHERE userid = ?`), and refresh — the indicator should now show "OFFLINE".

### Milestone 3: MessageTable override for PMS notifications

After this milestone, the PMS (Private Messaging System) notification logic correctly treats users with stale sessions as offline, allowing email notifications to be sent. This is mandatory for v1 — there is no manual-patch fallback.

The autoloader in `CbUserOnlineStatus.php` is extended to also intercept `CB\Plugin\PMS\Table\MessageTable`. The FQCN is confirmed from the file header: `namespace CB\Plugin\PMS\Table;` in `components/com_comprofiler/plugin/user/plug_pms_mypmspro/library/Table/MessageTable.php`.

The class is ~640 lines, which makes a full-body copy fragile against upstream CB updates. However, it is the only viable approach because `store()` cannot be overridden via inheritance without a circular autoloader dependency (the override must replace, not extend, the original). To manage the drift risk, the README must document that after each CB update the override file should be diffed against the new upstream `MessageTable.php` and the single-line change reapplied if needed.

File to create:

`plg_system_cbuseronlinestatus/src/Table/MessageTable.php` — A full copy of the original `MessageTable` class (same namespace `CB\Plugin\PMS\Table`, same class name `MessageTable`, all `use` statements and methods preserved verbatim) with one modification. In the `store()` method, the notification check at line 133:

```php
    PMSHelper::getGlobalParams()->getBool( 'messages_notify_offline', false ) && ( $_CB_framework->userOnlineLastTime( $this->getInt( 'to_user', 0 ) ) != null )
```

is replaced with:

```php
    PMSHelper::getGlobalParams()->getBool( 'messages_notify_offline', false ) && ( function() use ( $_CB_framework ) {
        $lastTime = $_CB_framework->userOnlineLastTime( $this->getInt( 'to_user', 0 ) );
        $timeout  = \YakShaver\Plugin\System\Cbuseronlinestatus\Extension\CbUserOnlineStatus::getOnlineTimeout();
        return ( $lastTime !== null ) && ( ( time() - $lastTime ) <= $timeout );
    } )()
```

The autoloader method in `CbUserOnlineStatus.php` must check for both FQCNs:

```php
    $map = [
        'CB\\Plugin\\Core\\Field\\StatusField' => __DIR__ . '/../Field/StatusField.php',
        'CB\\Plugin\\PMS\\Table\\MessageTable' => __DIR__ . '/../Table/MessageTable.php',
    ];
    if ( isset( $map[$class] ) && is_readable( $map[$class] ) ) {
        require $map[$class];
    }
```

Verification: Enable `messages_notify_offline` in CB PMS settings (Components > Community Builder > Plugin Management > PMS Pro > Parameters). Send a private message to a user whose session is older than 30 minutes (set via `UPDATE #__session SET time = UNIX_TIMESTAMP() - 1860 WHERE userid = ?`). Expected outcome: the email notification is sent because the user is treated as offline. Without the fix, the notification would be suppressed because the stale session makes the user appear online.

### Milestone 4: Custom module scaffolding

After this milestone, the module project structure exists with all boilerplate files and can be installed on a Joomla 5 site. It does not yet display any content.

Files to create:

`mod_cbuseronlinestatus/mod_cbuseronlinestatus.xml` — Joomla module manifest. Type `module`, client `site`, method `upgrade`. Namespace `Joomla\Module\Cbuseronlinestatus` with path `src`. Parameters:

- `mode` (list): "Online Users" (value 1, default), "Online Connections" (9), "Online Statistics" (6), "User Census" (7). Only the four session-based modes.
- `online_timeout` (integer, default 1800): "Online Timeout (seconds)" — same semantics as the plugin parameter. If the system plugin is installed, the plugin's value takes precedence (the helper checks for it).
- `limit` (integer, default 30): Maximum users to display. Show only for modes 1 and 9.
- `exclude` (text): Comma-separated user IDs to exclude.
- `exclude_self` (radio yes/no, default no): Exclude the viewing user.
- `pretext` (textarea, raw filter): HTML to show above the user list.
- `posttext` (textarea, raw filter): HTML to show below the user list.
- `usertext` (textarea, raw filter): CB substitution-supported HTML for each user. If blank, uses `formatname` field.
- `label` (list: "Text Only"=1, "Icon Only"=2, "Text and Icon"=3, default 1): Label display for statistics/census modes.
- `separator` (text, default ","): Thousands separator for counter modes.
- `maincbtpl` (radio yes/no, default yes): Load CB template CSS.
- `cb_plugins` (radio yes/no, default no): Load CB plugins for event integration.
- Advanced fieldset: `layout`, `moduleclass_sfx`, `cache`, `cache_time`.

Include update server pointing to `https://raw.githubusercontent.com/alexyarmoshko/joomla_pkg_cbuseronlinestatus/refs/heads/main/mod_cbuseronlinestatus.update.xml`.

`mod_cbuseronlinestatus/services/provider.php` — DI provider following the `mod_ystides` pattern. Registers `ModuleDispatcherFactory` with namespace `\\Joomla\\Module\\Cbuseronlinestatus`, `HelperFactory` with `\\Joomla\\Module\\Cbuseronlinestatus\\Site\\Helper`, and the Module service.

`mod_cbuseronlinestatus/language/en-GB/mod_cbuseronlinestatus.ini` and `.sys.ini` — Module name, description, parameter labels.

`mod_cbuseronlinestatus/index.html` — Empty HTML file.

Verification: Build the module ZIP, install on the DDEV site. The module appears in Extensions > Modules and can be assigned to a position. It renders nothing yet.

### Milestone 5: Module helper with session queries

After this milestone, the module helper class provides four public methods that return data for each of the four supported modes. All queries include the configurable timeout filter on the `time` column.

File to create:

`mod_cbuseronlinestatus/src/Helper/CbUserOnlineStatusHelper.php` — Class `Joomla\Module\Cbuseronlinestatus\Site\Helper\CbUserOnlineStatusHelper`. This class uses Joomla's `DatabaseAwareTrait` and is instantiated via the `HelperFactory`.

The helper needs access to CB's API for user rendering. It includes the CB foundation at the top of methods that need it:

```php
    if (!defined('_VALID_CB') && file_exists(JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.foundation.php')) {
        include_once JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.foundation.php';
        cbimport('cb.html');
        cbimport('language.front');
    }
```

The helper provides a method `getOnlineTimeout(\Joomla\Registry\Registry $params): int` that returns the effective timeout. It first checks if the system plugin's static method is available (`CbUserOnlineStatus::getOnlineTimeout()`), falling back to the module's own `online_timeout` parameter, falling back to 1800.

It provides `getLayoutVariables(\Joomla\Registry\Registry $params): array` which is called by the Dispatcher. Based on the `mode` parameter, it calls the appropriate internal method and returns an associative array of template variables.

All session queries must preserve CB's `shared_session` compatibility. The `client_id` condition must use the same branching as the original module:

```php
$clientIdClause = $_CB_framework->getCfg( 'shared_session' ) ? ' IS NULL' : ' = 0';
```

This produces `client_id IS NULL` for shared-session deployments and `client_id = 0` for standard deployments. The helper must read this from `$_CB_framework->getCfg('shared_session')` at query time, not hardcode either value. The session time filter clause added to all queries is:

```sql
AND (UNIX_TIMESTAMP() - s.time <= {$timeout})
```

where `{$timeout}` is the configured timeout in seconds. The timeout value must be explicitly cast to integer (`(int) $timeout`) before interpolation to prevent injection, even though it originates from a trusted parameter. Similarly, user IDs in the `exclude` list must each be cast to integer (`(int) $id`) before being joined into an `IN (...)` clause — never concatenate raw user input into SQL. The helper uses `$_CB_database` for consistency with the original module code and CB user rendering.

Internal methods (all private):

`getOnlineUserIds(params, timeout, exclude, limit)` — Mode 1 (Online Users). Returns an array of user IDs. Query:

```sql
    SELECT DISTINCT userid FROM #__session
    WHERE client_id {IS NULL or = 0}  -- shared_session branching
    AND guest = 0
    AND (UNIX_TIMESTAMP() - time <= ?)
    [AND userid NOT IN (?)]
    ORDER BY time DESC
    LIMIT ?
```

`getOnlineConnectionIds(params, timeout, exclude, limit, currentUserId)` — Mode 9 (Online Connections). Returns an array of user IDs. Query:

```sql
    SELECT DISTINCT s.userid FROM #__session AS s
    INNER JOIN #__comprofiler_members AS m
      ON m.referenceid = ? AND m.memberid = s.userid AND m.accepted = 1 AND m.pending = 0
    WHERE s.client_id {IS NULL or = 0}  -- shared_session branching
    AND s.guest = 0
    AND (UNIX_TIMESTAMP() - s.time <= ?)
    [AND s.userid NOT IN (?)]
    ORDER BY s.time DESC
```

`getOnlineStatistics(params, timeout, exclude)` — Mode 6. Returns an associative array with keys `onlineUsers`, `offlineUsers`, `guestUsers`. Three queries, each with the timeout filter on the session table and `shared_session` branching for `client_id`. The offline-users query uses a LEFT JOIN on `#__session` with `s.client_id {IS NULL or = 0}` (shared_session branching) and checks `s.session_id IS NULL` to find users with no active session, matching the original module logic.

`getCensusData(params, timeout, exclude)` — Mode 7. Returns an associative array with keys `totalUsers`, `latestUser` (CBuser instance), `onlineUsers`, `usersToday`, `usersWeek`, `usersMonth`, `usersYear`. The `onlineUsers` count query includes the timeout filter. The registration-date-based queries are unchanged from the original.

For modes 1 and 9, after obtaining user IDs, the helper calls `CBuser::advanceNoticeOfUsersNeeded($userIds)` and then builds an array of `CBuser` instances via `CBuser::getInstance((int) $userId)`.

The method also loads the CB template if `maincbtpl` is enabled (via `outputCbTemplate()`) and loads CB plugins if `cb_plugins` is enabled.

Verification: The helper is verified through the module rendering in Milestone 6 and the deterministic validation matrix in Milestone 8. No temporary debug output is needed.

### Milestone 6: Module dispatcher and layouts

After this milestone, the module renders output identical to the original CB module for all four supported modes, but with the timeout filter applied.

Files to create:

`mod_cbuseronlinestatus/src/Dispatcher/Dispatcher.php` — Class `Joomla\Module\Cbuseronlinestatus\Site\Dispatcher\Dispatcher`. Extends `AbstractModuleDispatcher`, implements `HelperFactoryAwareInterface`. The `getLayoutData()` method calls the helper's `getLayoutVariables($data['params'])` and merges the result into the layout data array.

`mod_cbuseronlinestatus/tmpl/default.php` — Layout for modes 1 and 9 (Online Users and Online Connections). Receives `$cbUsers` (array of CBuser instances), `$preText`, `$postText`, `$params`, `$templateClass`. Renders an unordered list of users with CB profile links, identical structure to `modules/mod_comprofileronline/tmpl/default.php`.

CB plugin hook compatibility: The original module's templates call `modCBOnlineHelper::getPlugins($params, $location)` at multiple hook points (`start`, `beforeUsers`, `beforeLinks`, `afterUsers`, `almostEnd`, `end` for default; `beforeStatistics`, `beforeList`, `afterStatistics` for statistics; `beforeCensus`, `beforeList`, `afterCensus` for census). This helper class is defined in `modules/mod_comprofileronline/helper.php` and triggers the `onAfterOnlineModule` CB plugin event, which third-party CB plugins can subscribe to for injecting content into the module output.

The replacement module must preserve this behavior. The strategy is: include the original helper file at render time if the `cb_plugins` module parameter is enabled. In the module helper's `getLayoutVariables()` method, if `$params->get('cb_plugins')` is true:

```php
    $helperFile = JPATH_SITE . '/modules/mod_comprofileronline/helper.php';
    if ( file_exists( $helperFile ) ) {
        require_once $helperFile;
    }
```

This makes `modCBOnlineHelper::getPlugins()` available to the template files. The templates then call it at the same hook points as the original, guarded by `class_exists('modCBOnlineHelper')`. If the original CB module is uninstalled (removing `helper.php`), the hook calls silently produce no output, which is the correct degradation behavior. The templates must use the exact same hook point names and call signatures as the originals to ensure third-party CB plugin compatibility.

`mod_cbuseronlinestatus/tmpl/default_statistics.php` — Layout for mode 6 (Online Statistics). Receives `$onlineUsers`, `$offlineUsers`, `$guestUsers`, `$label`, `$separator`, `$templateClass`, `$preText`, `$postText`, `$params`. Renders counters, matching the structure of `modules/mod_comprofileronline/tmpl/_statistics.php`. Uses `modCBOnlineHelper::getPlugins()` (guarded by `class_exists`) at hook points: `start`, `beforeStatistics`, `beforeList`, `afterStatistics`, `almostEnd`, `end`.

`mod_cbuseronlinestatus/tmpl/default_census.php` — Layout for mode 7 (User Census). Receives `$totalUsers`, `$latestUser`, `$onlineUsers`, `$usersToday`, `$usersWeek`, `$usersMonth`, `$usersYear`, `$label`, `$separator`, `$templateClass`, `$preText`, `$postText`, `$params`. Matches `modules/mod_comprofileronline/tmpl/_census.php`. Uses `modCBOnlineHelper::getPlugins()` (guarded by `class_exists`) at hook points: `start`, `beforeCensus`, `beforeList`, `afterCensus`, `almostEnd`, `end`.

The Dispatcher selects the layout sublayout based on the mode: modes 1 and 9 use `default`, mode 6 uses `default_statistics`, mode 7 uses `default_census`.

Verification: Install the module on the DDEV site, assign to a module position (e.g., the same position where `mod_comprofileronline` was). Configure mode = "Online Users" with default timeout. Log in with a test user, verify they appear in the list. Manually age their session row (`UPDATE #__session SET time = UNIX_TIMESTAMP() - 1860 WHERE userid = ?`), refresh — they should disappear from the list.

### Milestone 7: Package manifest, Makefiles, README

After this milestone, a single `pkg_cbuseronlinestatus` ZIP can be installed on Joomla to deploy both the plugin and module at once. The repo has a top-level Makefile that builds all three ZIPs.

Files to create:

`pkg_cbuseronlinestatus.xml` — Joomla package manifest. Type `package`, method `upgrade`. Lists two extensions: `plg_system_cbuseronlinestatus` (type plugin, group system) and `mod_cbuseronlinestatus` (type module, client site). The `<files>` section references the two sub-ZIPs by filename.

`Makefile` — Top-level Makefile with targets:

- `info` — prints package metadata
- `dist` — builds the plugin ZIP, the module ZIP, then the package ZIP containing both plus the package manifest. Updates SHA256 in update XMLs.
- `clean` — removes all built ZIPs
- `dist-plugin` — builds only the plugin ZIP
- `dist-module` — builds only the module ZIP

The plugin ZIP is built by zipping the contents of `plg_system_cbuseronlinestatus/`. The module ZIP is built by zipping the contents of `mod_cbuseronlinestatus/`. The package ZIP contains the package manifest, the two sub-ZIPs, and the LICENSE.

Update XML files to create:

- `plg_system_cbuseronlinestatus.update.xml`
- `mod_cbuseronlinestatus.update.xml`

`README.md` — GitHub-style README with: project title, description of the problem, what the package does, installation instructions, configuration (plugin parameter for timeout), which CB modes the module supports, compatibility notes (Joomla 5.x, CB 2.11+, PHP 8.1+), license.

`RELEASE.md` — Initial release notes for v1.0.0.

`LICENSE` — GPL v2 full text (copy from an existing repo like `joomla_plg_cbgjguestaccess`).

Verification: Run `make dist` in the repo root. Three ZIPs should appear in `installation/`: `plg_system_cbuseronlinestatus-v1-0-0.zip`, `mod_cbuseronlinestatus-v1-0-0.zip`, `pkg_cbuseronlinestatus-v1-0-0.zip`.

### Milestone 8: Integration testing on DDEV site

After this milestone, the package has been installed on the DDEV development site and all online-status indicators work correctly with the timeout filter. Each test in the validation matrix below has a deterministic setup, action, and expected outcome.

Setup steps:

1. Build the package ZIP via `make dist`.
2. Install `pkg_cbuseronlinestatus` via Joomla's Extensions > Install on the DDEV site (`prod-ecskc-eu.r2d2.dahaus`).
3. Enable the system plugin `plg_system_cbuseronlinestatus` in Extensions > Plugins. Set ordering to run early (before CB plugins if possible).
4. Create a module instance of `mod_cbuseronlinestatus` in Extensions > Modules. Assign it to the same position as the existing CB online module. Configure mode = "Online Users".
5. Unpublish the existing `mod_comprofileronline` module instance (or keep both visible temporarily for comparison).

Validation matrix — each test must pass before the milestone is considered complete:

**V1 — Baseline check.** Before any testing, confirm the current patched state of the live CB files. From the site root, run one of:

- Bash/WSL: `grep -n 'time() - $lastTime' components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.php` and `grep -n 'UNIX_TIMESTAMP' modules/mod_comprofileronline/mod_comprofileronline.php`
- PowerShell: `Select-String -Pattern 'time\(\) - \$lastTime' -Path components\com_comprofiler\plugin\user\plug_cbcore\library\Field\StatusField.php` and `Select-String -Pattern 'UNIX_TIMESTAMP' -Path modules\mod_comprofileronline\mod_comprofileronline.php`

Expected: StatusField.php line 43 contains `(time() - $lastTime)<=1800`, mod_comprofileronline.php line 400 contains `UNIX_TIMESTAMP() - time <= 1800`. This confirms the two already-patched locations.

**V2 — Mode 1 (Online Users).** Setup: log in as test user, note they appear in the module user list. Action: age their session via `UPDATE #__session SET time = UNIX_TIMESTAMP() - 1860 WHERE userid = <testUserId>`, then refresh the page. Expected: the test user disappears from the online users list.

**V3 — Mode 9 (Online Connections).** Setup: ensure the test user has at least one CB connection who is logged in with a fresh session. Change module mode to "Online Connections". Action: age the connection's session as in V2 and refresh. Expected: the connection disappears from the online connections list.

**V4 — Mode 6 (Online Statistics).** Setup: change module mode to "Online Statistics". Note the online/offline/guest counts. Action: age one authenticated user's session as in V2 and refresh. Expected: online count decreases by 1 and offline count increases by 1. Guest count is unchanged.

**V5 — Mode 7 (User Census).** Setup: change module mode to "User Census". Note the online count. Action: age one authenticated user's session as in V2 and refresh. Expected: online count decreases by 1. Other census values (total users, latest user, registrations) are unchanged.

**V6 — Shared-session compatibility.** This test verifies that the `client_id` condition works for both standard and shared-session configurations. Setup: check the current value of `shared_session` in CB configuration. Action: with the current setting, run V2 again and confirm the same expected result. If the site does not use shared sessions, this test passes by default; note the setting value in the test results.

**V7 — PMS notification.** Setup: enable `messages_notify_offline` in CB PMS settings (Components > Community Builder > Plugin Management > PMS Pro > Parameters). Ensure mail sending is configured. Action: age the recipient's session as in V2, then send them a private message from another user. Expected: an email notification is sent to the recipient (check the mail log or recipient inbox). Without the fix, the notification would be suppressed because the stale session makes the recipient appear online.

**V8 — StatusField profile indicator.** Setup: navigate to a test user's profile page. Confirm the online/offline dot. Action: age their session as in V2 and refresh the profile page. Expected: the indicator changes from "ONLINE" (green circle) to "OFFLINE" (red circle-o).

**V9 — No PHP errors.** After completing V1–V8, check Joomla's system log (System > Global Configuration > Logging > Path to Log Folder, or `administrator/logs/`). Expected: no PHP errors, warnings, or notices related to `cbuseronlinestatus`, `StatusField`, or `MessageTable`.

Verification: All nine validation tests pass.

### Milestone 9: Revert patched CB files, final validation

After this milestone, the two manually patched CB files are reverted to their originals, and the site continues to function correctly with online status handled entirely by the new package.

Steps:

1. In the production site repo (`C:\Users\alex\repos\ecskc.eu.sites\prod-html\`), restore the original CB files:
   - Copy `modules/mod_comprofileronline/mod_comprofileronline.original.php` to `modules/mod_comprofileronline/mod_comprofileronline.php`
   - Copy `components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.original.php` to `components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.php`

2. Confirm the originals are restored by checking that the timeout patches are no longer present. Run one of:
   - Bash/WSL: `grep -c 'time() - $lastTime' components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.php` should return 0, and `grep -c 'UNIX_TIMESTAMP() - time <= 1800' modules/mod_comprofileronline/mod_comprofileronline.php` should return 0.
   - PowerShell: `(Select-String -Pattern 'time\(\) - \$lastTime' -Path components\com_comprofiler\plugin\user\plug_cbcore\library\Field\StatusField.php).Count` should return 0, and `(Select-String -Pattern 'UNIX_TIMESTAMP\(\) - time <= 1800' -Path modules\mod_comprofileronline\mod_comprofileronline.php).Count` should return 0.

3. Refresh the site and re-run validation tests V2 and V8 from Milestone 8. The online-status behavior must remain correct — the package's autoloader and module now handle the timeout, not the file patches. If either test fails, the package is not working correctly and the revert should be rolled back.

4. The `.original.php` backup files can optionally be deleted.

Verification: Site loads without errors. V2 and V8 pass after revert. The CB files are now unmodified originals, safe for future CB updates.

### Milestone 10: Post-release hardening — upstream drift detection, plugin decoupling, and Kunena timeout sync

This milestone addresses three findings from the code review v6 (`docs/code_review.v6.md`) performed on the released v1.0.0 package. The changes improve maintainability and operational safety without altering the core timeout-override behavior. After this milestone, administrators receive a warning when CB updates change the overridden files, the module no longer depends on the plugin's internal namespace, and the plugin can optionally synchronize its timeout with Kunena's forum session timeout.

#### 10a — Upstream file-hash verification with admin verification gate (addresses review Section 5.1)

The tightest risk in the package is the full-body override of CB's `StatusField.php` (~80 lines) and `MessageTable.php` (~640 lines). If Joomlapolis releases a CB update that changes these files, the overrides silently mask the upstream changes — including potential security patches. This sub-milestone adds a self-enforcing integrity gate: the plugin computes and stores SHA256 hashes of the tracked CB files, requires explicit admin verification before it will activate its overrides, and automatically deactivates its overrides (without disabling the plugin) if the upstream files change. The plugin remains enabled in Joomla at all times so it can continue to display warnings to the administrator on every backend request until the hashes are re-verified.

The tracked files (relative to the Joomla site root) are defined as a class constant:

    private const TRACKED_FILES = [
        'components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.php',
        'components/com_comprofiler/plugin/user/plug_pms_mypmspro/library/Table/MessageTable.php',
    ];

**State storage.** The plugin stores hash data in its own `params` JSON in `#__extensions`, using the same direct-UPDATE pattern that `plg_system_stats` uses in Joomla core. Two params keys are used:

- `upstream_hashes` — a JSON object mapping each tracked file path to its SHA256 hash (e.g., `{"components/.../StatusField.php": "abc123...", ...}`). Computed automatically by the plugin at runtime.
- `hashes_verified` — a standard radio field in the plugin configuration (`0` = "Not Verified", `1` = "Verified"). Set to `0` programmatically when hashes are first computed or when a file change is detected. Set to `1` only by the administrator saving the plugin settings with the radio set to "Verified".

A private method `saveParams()` persists `$this->params` to `#__extensions` using `$db->lockTable('#__extensions')`, a direct UPDATE query binding the JSON-serialized params, followed by `$db->unlockTables()` and a cache clear of the `com_plugins` group. This follows the pattern from `plg_system_stats::saveParams()`.

**Important:** The plugin does NOT call `disablePlugin()` or set `enabled = 0` in any scenario. If the plugin were to disable itself (e.g., triggered by a frontend visitor's request), Joomla would not load it on subsequent requests, and the administrator would never see the warning — the plugin would silently stop functioning. Instead, the plugin always remains enabled and relies on the `hashes_verified` param to control whether the autoloader is registered. When hashes are not verified, the plugin runs but does nothing except display warnings to administrators.

**Lifecycle flow:**

1. **First install / fresh state.** When `onAfterInitialise()` runs and `upstream_hashes` is empty (not yet computed), the plugin computes `hash_file('sha256', JPATH_SITE . '/' . $path)` for each tracked file, stores the hashes in `upstream_hashes` via `saveParams()`, and sets `hashes_verified` to `0`. The autoloader is NOT registered — the plugin does nothing on the frontend until hashes are verified. On admin pages (`$this->getApplication()->isClient('administrator')`), it enqueues a warning message telling the administrator to review the tracked files and verify the hashes in the plugin configuration. On frontend pages, no message is enqueued (to avoid leaking internal file paths to site visitors).

2. **Admin verification.** The administrator opens the plugin configuration in Extensions > Plugins. The "Upstream File Tracking" fieldset shows:
   - A read-only custom field (`type="UpstreamHashes"`) displaying each tracked file path and its computed SHA256 hash. This is a custom `FormField` subclass at `plg_system_cbuseronlinestatus/src/Field/UpstreamHashesField.php` that reads the `upstream_hashes` param and renders a simple read-only table — no buttons, no JavaScript, no AJAX.
   - A standard Joomla `radio` field (`hashes_verified`) with options "Not Verified" (`0`) and "Verified" (`1`).

   The administrator reviews the displayed hashes, confirms the tracked files are as expected, sets the radio to "Verified", and clicks "Save" (the standard Joomla plugin save button). The `hashes_verified` param is persisted to `#__extensions` by Joomla's normal plugin save flow — no custom `saveParams()` call is needed for this step.

3. **Normal operation.** When `onAfterInitialise()` runs and `hashes_verified` is `1`, the plugin checks all tracked files against the stored hashes. If all match, the autoloader is registered and the plugin operates normally. If any hash mismatches (meaning CB has been updated), the plugin:
   - Recomputes and stores the new hashes via `saveParams()`.
   - Sets `hashes_verified` to `0` (in the same `saveParams()` call).
   - On admin pages (`isClient('administrator')`), enqueues a warning message naming the changed file(s) and instructing the administrator to review the changes, update the overrides if needed, and re-verify. On frontend pages, no message is enqueued.
   - Does NOT register the autoloader for this request. Because the plugin remains enabled, it will continue to display the "not verified" warning (step 2) on every subsequent admin-side request until the admin re-verifies.

4. **Re-verification after CB update.** The administrator reviews the changed CB files (comparing against the override versions), updates the overrides in the plugin package if necessary, reinstalls the package, then opens the plugin configuration, sets "Hashes Verified" to "Verified", and saves. No re-enabling step is needed — the plugin was never disabled.

**Plugin class changes.** The `onAfterInitialise` handler gains the hash-check gate before registering the autoloader. No additional event subscriptions are needed — the verification is a standard Joomla form save, not an AJAX call.

The `onAfterInitialise` method flow becomes:

    // 1. Read hash state
    $storedHashes = json_decode($this->params->get('upstream_hashes', '{}'), true);
    $verified     = (bool) $this->params->get('hashes_verified', 0);

    // 2. If no hashes stored yet, compute and store, warn admin
    if (empty($storedHashes)) {
        $this->computeAndStoreHashes();
        // warn admin, do not register autoloader
        return;
    }

    // 3. If not verified, warn admin, do not register autoloader
    if (!$verified) {
        if ($this->getApplication()->isClient('administrator')) {
            $this->getApplication()->enqueueMessage(
                Text::_('PLG_SYSTEM_CBUSERONLINESTATUS_HASHES_NOT_VERIFIED'), 'warning'
            );
        }
        return;
    }

    // 4. Verified — check for upstream changes
    if (!$this->verifyUpstreamHashes($storedHashes)) {
        // Files changed — recompute hashes, set hashes_verified=0, warn admin (admin only)
        return;
    }

    // 5. All good — read timeout and register autoloader
    // (existing timeout + autoloader logic here)

**Manifest XML changes.** Add `addfieldprefix` to the `<fields>` element. Add the hash display and verification radio in a new fieldset:

    <fields name="params" addfieldprefix="YakShaver\Plugin\System\Cbuseronlinestatus\Field">
        <fieldset name="basic" label="COM_PLUGINS_BASIC_FIELDSET_LABEL">
            <!-- existing fields (timeout_source, online_timeout) -->
        </fieldset>
        <fieldset name="upstream" label="PLG_SYSTEM_CBUSERONLINESTATUS_FIELDSET_UPSTREAM_LABEL">
            <field
                name="upstream_hashes_display"
                type="UpstreamHashes"
                label="PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_UPSTREAM_HASHES_LABEL"
                description="PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_UPSTREAM_HASHES_DESC"
            />
            <field
                name="hashes_verified"
                type="radio"
                label="PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_HASHES_VERIFIED_LABEL"
                description="PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_HASHES_VERIFIED_DESC"
                default="0"
                class="btn-group"
            >
                <option value="0">PLG_SYSTEM_CBUSERONLINESTATUS_NOT_VERIFIED</option>
                <option value="1">PLG_SYSTEM_CBUSERONLINESTATUS_VERIFIED</option>
            </field>
        </fieldset>
    </fields>

**New file.** `plg_system_cbuseronlinestatus/src/Field/UpstreamHashesField.php` — a custom `FormField` subclass that reads the `upstream_hashes` param from the plugin's stored params, and renders a read-only HTML table listing each tracked file path and its SHA256 hash. No buttons, no JavaScript, no AJAX. If no hashes have been computed yet, it displays a notice that hashes will be computed on the next page load.

**Language strings to add to `plg_system_cbuseronlinestatus.ini`:**

    PLG_SYSTEM_CBUSERONLINESTATUS_FIELDSET_UPSTREAM_LABEL="Upstream File Tracking"
    PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_UPSTREAM_HASHES_LABEL="Tracked CB File Hashes"
    PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_UPSTREAM_HASHES_DESC="SHA256 hashes of the original CB files that this plugin overrides. Review these after a CB update to confirm the overrides are still compatible."
    PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_HASHES_VERIFIED_LABEL="Hashes Verified"
    PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_HASHES_VERIFIED_DESC="Set to 'Verified' after reviewing the tracked file hashes. The plugin will not activate its overrides until this is set to 'Verified'. This is automatically reset to 'Not Verified' if CB updates the tracked files."
    PLG_SYSTEM_CBUSERONLINESTATUS_NOT_VERIFIED="Not Verified"
    PLG_SYSTEM_CBUSERONLINESTATUS_VERIFIED="Verified"
    PLG_SYSTEM_CBUSERONLINESTATUS_HASHES_NOT_VERIFIED="Yak Shaver CB User Online Status: The tracked CB file hashes have not been verified. The plugin overrides are inactive. Please open the plugin configuration, review the hashes, and set 'Hashes Verified' to 'Verified'."
    PLG_SYSTEM_CBUSERONLINESTATUS_UPSTREAM_CHANGED="Yak Shaver CB User Online Status: Community Builder has updated "%s". The plugin overrides have been deactivated to prevent conflicts. Please review the changes, update the overrides if needed, and re-verify the hashes in the plugin configuration."
    PLG_SYSTEM_CBUSERONLINESTATUS_HASHES_PENDING="Hashes have not been computed yet. They will be calculated on the next page load."

**Verification:** Install a fresh build of the plugin. After installation, the plugin is enabled but the autoloader is inactive — CB's original classes load as usual. On admin pages, a warning appears prompting verification (no warning on frontend pages). Open the plugin configuration: the "Upstream File Tracking" fieldset shows the two tracked files with their computed hashes and a "Hashes Verified" radio set to "Not Verified". Set it to "Verified" and click Save. Refresh the frontend: the overrides are now active (verify via V2 and V8 from Milestone 8). Then manually alter one byte in the original CB `StatusField.php` (e.g., add a trailing comment), refresh any admin page — the plugin detects the change, deactivates overrides, and enqueues a warning naming the changed file. Confirm that CB's original `StatusField` loads on the frontend (the timeout override is gone) and that no warning is shown to frontend visitors. Remove the added comment, open plugin config (the radio is back to "Not Verified" and new hashes are shown), set to "Verified", save, and confirm overrides are active again — no re-enabling step is needed since the plugin was never disabled.

#### 10b — Decouple module from plugin namespace (addresses review Section 5.2)

Currently the module helper (`mod_cbuseronlinestatus/src/Helper/CbUserOnlineStatusHelper.php`) checks for the plugin class by its fully qualified namespace (`\YakShaver\Plugin\System\Cbuseronlinestatus\Extension\CbUserOnlineStatus`) to read the timeout. While functional, this means the module has compile-time knowledge of the plugin's internal namespace structure.

The recommended decoupling uses Joomla's application state as a lightweight shared-state mechanism. The change has two sides:

Plugin side: In the `onAfterInitialise()` method of `CbUserOnlineStatus.php`, after setting the static timeout property, add one line:

    $this->getApplication()->set('cbuserstatus.timeout', self::$onlineTimeout);

Module side: In `CbUserOnlineStatusHelper::getOnlineTimeout()`, replace the current `class_exists` check with a read from application state:

    public function getOnlineTimeout(Registry $params): int
    {
        $timeout = Factory::getApplication()->get('cbuserstatus.timeout', 0);
        if ($timeout > 0) {
            return $timeout;
        }
        return (int) $params->get('online_timeout', 1800);
    }

This preserves the same priority chain (plugin value > module parameter > 1800 default) without any namespace coupling. If the plugin is disabled or not installed, the application state key does not exist and the module falls back to its own parameter.

The `StatusField.php` and `MessageTable.php` overrides continue to call `CbUserOnlineStatus::getOnlineTimeout()` directly — this is acceptable because they are shipped inside the plugin package and are guaranteed to coexist with the plugin class.

Verification: Install the updated package. Confirm that the module still displays the correct timeout-filtered online users. Then disable the system plugin in Extensions > Plugins, confirm the module falls back to its own `online_timeout` parameter (set it to a different value, e.g., 900, and verify the module uses 900). Re-enable the plugin and confirm it takes precedence again.

#### 10c — Kunena timeout synchronization (addresses new requirement)

Kunena forum has its own session timeout setting (`sessionTimeOut`, default 1800 seconds) in `Kunena\Forum\Libraries\Config\KunenaConfig`. When both CB User Online Status and Kunena are active on the same site, having two independent timeout values creates a UX inconsistency: a user could appear "online" in CB but "offline" in Kunena (or vice versa) if the timeouts diverge.

This sub-milestone adds a `timeout_source` parameter to the plugin that allows the administrator to synchronize the timeout with Kunena's configuration instead of maintaining a separate value.

Add a new field to the plugin XML manifest (`plg_system_cbuseronlinestatus/cbuseronlinestatus.xml`), in the `basic` fieldset, before the existing `online_timeout` field:

    <field
        name="timeout_source"
        type="list"
        label="PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_TIMEOUT_SOURCE_LABEL"
        description="PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_TIMEOUT_SOURCE_DESC"
        default="manual"
    >
        <option value="manual">PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_TIMEOUT_SOURCE_MANUAL</option>
        <option value="kunena">PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_TIMEOUT_SOURCE_KUNENA</option>
    </field>

The `online_timeout` number field remains in the form for both modes, but its behavior changes based on `timeout_source`:

- When `timeout_source` is `manual`: the field is a normal editable number input (current behavior).
- When `timeout_source` is `kunena`: the field is rendered as a readonly, greyed-out input showing the actual value read from Kunena's configuration. This lets the administrator see the effective timeout without being able to edit it (since the value is controlled by Kunena).

To achieve this, replace the standard `type="number"` field with a custom form field class `type="OnlineTimeout"` at `plg_system_cbuseronlinestatus/src/Field/OnlineTimeoutField.php` (discovered via the same `addfieldprefix` already added in 10a). This class extends `Joomla\CMS\Form\Field\NumberField` and overrides `getInput()`:

- It reads the `timeout_source` value from the form data (`$this->form->getValue('timeout_source', 'params', 'manual')`).
- If the source is `kunena` and `Kunena\Forum\Libraries\Config\KunenaConfig` exists, it reads `KunenaConfig::getInstance()->sessionTimeOut`, sets the field value to that integer, and renders the `<input>` element with `disabled="disabled"` and the `name` attribute removed so the Kunena value is strictly for display and is NOT submitted with the form. This prevents the Kunena timeout value from overwriting the user's previously configured manual value in the database — if Kunena is later uninstalled or the source is switched back to "manual", the original manual value is preserved.
- If the source is `kunena` but Kunena is not installed, it renders the field as disabled with the current manual value and appends a small warning note below the input ("Kunena not installed — showing manual fallback value").
- If the source is `manual`, it delegates to the parent `NumberField::getInput()` for standard editable behavior.

Update the `online_timeout` field definition in the plugin manifest:

    <field
        name="online_timeout"
        type="OnlineTimeout"
        label="PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_ONLINE_TIMEOUT_LABEL"
        description="PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_ONLINE_TIMEOUT_DESC"
        default="1800"
        filter="integer"
        validate="number"
        min="60"
    />

In `CbUserOnlineStatus::onAfterInitialise()`, modify the timeout resolution logic:

    $source = $this->params->get('timeout_source', 'manual');

    if ($source === 'kunena') {
        $kunenaConfigClass = '\\Kunena\\Forum\\Libraries\\Config\\KunenaConfig';
        if (class_exists($kunenaConfigClass)) {
            $kunenaConfig = $kunenaConfigClass::getInstance();
            if ($kunenaConfig !== null) {
                self::$onlineTimeout = (int) $kunenaConfig->sessionTimeOut;
            }
        } else {
            // Kunena not installed — fall back to manual value and log a notice
            self::$onlineTimeout = (int) $this->params->get('online_timeout', 1800);
            $this->getApplication()->enqueueMessage(
                Text::_('PLG_SYSTEM_CBUSERONLINESTATUS_KUNENA_NOT_FOUND'),
                'warning'
            );
        }
    } else {
        self::$onlineTimeout = (int) $this->params->get('online_timeout', 1800);
    }

Add the `use Joomla\CMS\Language\Text;` import if not already present.

Language strings to add to `plg_system_cbuseronlinestatus.ini`:

    PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_TIMEOUT_SOURCE_LABEL="Timeout Source"
    PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_TIMEOUT_SOURCE_DESC="Where to read the online timeout value from. 'Manual' uses the value configured below. 'Kunena' reads the session timeout from Kunena's forum configuration, keeping both in sync automatically."
    PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_TIMEOUT_SOURCE_MANUAL="Manual"
    PLG_SYSTEM_CBUSERONLINESTATUS_FIELD_TIMEOUT_SOURCE_KUNENA="Kunena Forum"
    PLG_SYSTEM_CBUSERONLINESTATUS_KUNENA_NOT_FOUND="Yak Shaver CB User Online Status: Timeout source is set to 'Kunena' but the Kunena component is not installed. Falling back to manual timeout value."
    PLG_SYSTEM_CBUSERONLINESTATUS_KUNENA_NOT_INSTALLED_NOTE="Kunena not installed — showing manual fallback value."

Verification: Install the updated plugin. In the plugin configuration, set "Timeout Source" to "Kunena Forum" — the "Online Timeout (seconds)" field should remain visible but become greyed out and disabled, displaying Kunena's current `sessionTimeOut` value (e.g., 1800). The disabled field is not submitted with the form, so clicking Save does not overwrite the stored manual value. Change Kunena's session timeout (Components > Kunena Forum > Configuration > General > Session time out) to a different value (e.g., 900 seconds), then reopen the plugin configuration — the disabled field should now show 900. Switch back to "Manual" and confirm the field becomes editable again and shows the original stored manual value (not the Kunena value).

## Concrete Steps

(To be updated as implementation proceeds. Below is the initial command sequence.)

Working directory: `C:\Users\alex\repos\joomla_pkg_cbuseronlinestatus\`

Step 1 — Create the directory structure:

Bash/WSL:

```shell
    mkdir -p plg_system_cbuseronlinestatus/{services,src/{Extension,Field,Table},language/en-GB}
    mkdir -p mod_cbuseronlinestatus/{services,src/{Dispatcher,Helper},tmpl,language/en-GB}
    mkdir -p installation
```

PowerShell:

```powershell
    @('plg_system_cbuseronlinestatus\services',
      'plg_system_cbuseronlinestatus\src\Extension',
      'plg_system_cbuseronlinestatus\src\Field',
      'plg_system_cbuseronlinestatus\src\Table',
      'plg_system_cbuseronlinestatus\language\en-GB',
      'mod_cbuseronlinestatus\services',
      'mod_cbuseronlinestatus\src\Dispatcher',
      'mod_cbuseronlinestatus\src\Helper',
      'mod_cbuseronlinestatus\tmpl',
      'mod_cbuseronlinestatus\language\en-GB',
      'installation') | ForEach-Object { New-Item -ItemType Directory -Force -Path $_ }
```

Step 2 — Create all source files as described in Milestones 1–7.

Step 3 — Build:

```shell
    make dist
```

Expected output:

```shell
    Built installation/plg_system_cbuseronlinestatus-v1-0-0.zip
    Built installation/mod_cbuseronlinestatus-v1-0-0.zip
    Built installation/pkg_cbuseronlinestatus-v1-0-0.zip
```

Step 4 — Install on DDEV site:

```shell
    Navigate to https://prod-ecskc-eu.r2d2.dahaus/administrator/
    Extensions > Install > Upload Package File
    Upload pkg_cbuseronlinestatus-v1-0-0.zip
```

Step 5 — Enable and configure (see Milestone 8).

Step 6 — Revert CB files (see Milestone 9).

## Validation and Acceptance

The package is accepted when all of the following conditions are met:

1. The system plugin loads without errors and both `StatusField` and `MessageTable` overrides are active. Verified by tests V8 (profile indicator) and V7 (PMS notification) from the Milestone 8 validation matrix.

2. The custom module renders online users correctly in all four modes (Online Users, Online Connections, Online Statistics, User Census) with the timeout filter and `shared_session` branching applied. Verified by tests V2–V6.

3. The two originally patched CB files (`mod_comprofileronline.php` and `StatusField.php`) have been reverted to their unmodified CB originals and the site continues to work correctly. Verified by Milestone 9 revert check.

4. `make dist` produces three installable ZIPs without errors.

5. No PHP errors, warnings, or notices appear in Joomla's system log related to the package. Verified by test V9.

## Idempotence and Recovery

All files in this package are new (no existing files are modified). The package uses Joomla's `method="upgrade"` in all manifests, so re-installing is safe and idempotent.

If the system plugin fails to load or causes errors, it can be disabled in Extensions > Plugins without affecting the rest of the site (CB's original StatusField will load normally via CB's own autoloader).

If the custom module causes issues, it can be unpublished in Extensions > Modules and the original `mod_comprofileronline` re-published.

The package can be fully uninstalled via Extensions > Manage > Manage, which removes all files and database entries cleanly.

## Artifacts and Notes

### Original StatusField online check (to be replaced)

```php
    // Original (StatusField.original.php line 41):
    $isOnline = ( $lastTime != null );

    // Override (with timeout):
    $timeout = \YakShaver\Plugin\System\Cbuseronlinestatus\Extension\CbUserOnlineStatus::getOnlineTimeout();
    $isOnline = ( $lastTime != null ) && ( ( time() - $lastTime ) <= $timeout );
```

### Original module session query (mode 1, no timeout)

```sql
    SELECT DISTINCT userid FROM #__session
    WHERE client_id {IS NULL or = 0}  -- shared_session branching
    AND guest = 0
    ORDER BY time DESC
```

### Fixed module session query (with timeout)

```sql
    SELECT DISTINCT userid FROM #__session
    WHERE client_id {IS NULL or = 0}  -- shared_session branching
    AND guest = 0
    AND (UNIX_TIMESTAMP() - time <= 1800)
    ORDER BY time DESC
```

### MessageTable notification check (line 133)

```php
    // Original:
    $_CB_framework->userOnlineLastTime( $this->getInt( 'to_user', 0 ) ) != null

    // Override (with timeout — mandatory for v1):
    $lastTime = $_CB_framework->userOnlineLastTime( $this->getInt( 'to_user', 0 ) );
    $timeout = \YakShaver\Plugin\System\Cbuseronlinestatus\Extension\CbUserOnlineStatus::getOnlineTimeout();
    ( $lastTime !== null ) && ( ( time() - $lastTime ) <= $timeout )
```

## Interfaces and Dependencies

### System plugin — main class

In `plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php`, define:

```php
    namespace YakShaver\Plugin\System\Cbuseronlinestatus\Extension;

    final class CbUserOnlineStatus extends CMSPlugin implements SubscriberInterface
    {
        use DatabaseAwareTrait;

        protected $autoloadLanguage = true;

        private static int $onlineTimeout = 1800;

        public static function getSubscribedEvents(): array
        // Returns ['onAfterInitialise' => 'onAfterInitialise']

        public function onAfterInitialise(AfterInitialiseEvent $event): void
        // Reads $this->params->get('online_timeout', 1800), stores in static property.
        // Registers prepended autoloader via spl_autoload_register.

        public static function getOnlineTimeout(): int
        // Returns self::$onlineTimeout (default 1800 if plugin not yet loaded).

        public function overrideAutoloader(string $class): void
        // Maps FQCN to override file path. Guards with is_readable() before require.
        // If $class === 'CB\\Plugin\\Core\\Field\\StatusField' and file is readable, requires src/Field/StatusField.php.
        // If $class === 'CB\\Plugin\\PMS\\Table\\MessageTable' and file is readable, requires src/Table/MessageTable.php.
        // If the file does not exist, returns without loading — CB's own autoloader handles it.
    }
```

### StatusField override

In `plg_system_cbuseronlinestatus/src/Field/StatusField.php`, define:

```php
    namespace CB\Plugin\Core\Field;
    // Exact same namespace as original — this replaces the class entirely.

    class StatusField extends cbFieldHandler
    {
        public function getField(&$field, &$user, $output, $reason, $list_compare_types)
        // Identical to original except $isOnline uses timeout check.

        public function prepareFieldDataSave(&$field, &$user, &$postdata, $reason)
        // Identical to original.

        public function bindSearchCriteria(&$field, &$searchVals, &$postdata, $list_compare_types, $reason)
        // Identical to original.
    }
```

### Module helper

In `mod_cbuseronlinestatus/src/Helper/CbUserOnlineStatusHelper.php`, define:

```php
    namespace Joomla\Module\Cbuseronlinestatus\Site\Helper;

    class CbUserOnlineStatusHelper
    {
        use DatabaseAwareTrait;

        public function getLayoutVariables(Registry $params): array
        // Dispatches to the correct internal method based on mode parameter.
        // Returns array of template variables.

        public function getOnlineTimeout(Registry $params): int
        // Returns effective timeout (plugin > module param > 1800).

        private function getOnlineUserIds(Registry $params, int $timeout, array $exclude, int $limit): array
        private function getOnlineConnectionIds(Registry $params, int $timeout, array $exclude, int $limit, int $currentUserId): array
        private function getOnlineStatistics(Registry $params, int $timeout, array $exclude): array
        private function getCensusData(Registry $params, int $timeout, array $exclude): array
    }
```

### Module dispatcher

In `mod_cbuseronlinestatus/src/Dispatcher/Dispatcher.php`, define:

```php
    namespace Joomla\Module\Cbuseronlinestatus\Site\Dispatcher;

    class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
    {
        use HelperFactoryAwareTrait;

        protected function getLayoutData(): array
        // Calls helper, merges layout variables, sets sublayout based on mode.
    }
```

### External dependencies

- Joomla 5.x (CMS framework, module/plugin architecture)
- Community Builder 2.11+ (CBuser API, CB database API, CB template system)
- PHP 8.1+ (required by Joomla 5)

## Revision History

- 2026-02-21: Plan amended per review v1 (`docs/execution_plan.review.v1.md`). Addressed 3 blockers (outdated baseline, hardcoded `client_id`, PMS ambiguity), 2 high-severity items (plugin hook compatibility, non-deterministic verification), and 1 medium (formatting/hygiene). All review findings resolved. See `docs/execution_changelog.md` for full details.
- 2026-02-21: Plan amended per review v2 (`docs/execution_plan.review.v2.md`). Addressed 5 of 6 findings: removed PMS conditional wording in Artifacts, added `autoloadLanguage` to plugin class spec, resolved Milestone 1 sequencing contradiction, normalized shell commands with PowerShell variants, added SQL value hardening for timeout/exclude. Finding #4 (line-count discrepancy) verified as false positive — `wc -l` confirms ~640 lines. See `docs/execution_changelog.md` for full details.
- 2026-02-21: Plan amended per review v3 (`docs/execution_plan.review.v3.md`). Addressed all 3 findings: added `is_readable` guard to autoloader (prevents fatal on missing override files), replaced UTF-8 box-drawing tree with plain ASCII, normalized MessageTable line count to ~640. See `docs/execution_changelog.md` for full details.
- 2026-02-21: Plan amended per review v4 (`docs/execution_plan.review.v4.md`). Addressed both low-severity findings: fixed autoloader "returns true" wording to match void/guard-based design, reordered line-count evidence to list PowerShell first. See `docs/execution_changelog.md` for full details.
- 2026-02-21: Plan amended per review v5 (`docs/execution_plan.review.v5.md`). Addressed all 3 findings: fixed V1 baseline to search `time() - $lastTime` in StatusField (not `UNIX_TIMESTAMP`), fixed Milestone 9 revert to use same diagnostic pattern, tightened module revert check to exact `UNIX_TIMESTAMP() - time <= 1800` expression. See `docs/execution_changelog.md` for full details.
- 2026-02-21: Plan amended per review v6 (`docs/execution_plan.review.v6.md`). Addressed 1 low-severity finding: added full relative paths to Milestone 9 revert verification commands (previously bare filenames, not runnable from site root). See `docs/execution_changelog.md` for full details.
- 2026-02-22: Post-release repo review updated the plan status to reflect the shipped v1.0.0 implementation (Milestones 1-9 completed) and populated Outcomes & Retrospective. See `docs/execution_changelog.md` for details.
- 2026-02-22: Plan amended per code review v6 (`docs/code_review.v6.md`). Added Milestone 10 with three sub-milestones: 10a (upstream file-hash verification), 10b (module-plugin namespace decoupling via application state), 10c (Kunena timeout synchronization via `timeout_source` parameter). Added 3 decisions to Decision Log. See `docs/execution_changelog.md` for details.
- 2026-02-22: Milestone 10a redesigned per user feedback: changed from passive admin warning to active verification gate with self-disabling. Plugin now computes and stores hashes on install, requires explicit admin verification via custom form field button before activating overrides, and automatically disables itself when upstream files change. Uses `saveParams()` and `disablePlugin()` patterns from `plg_system_stats`. Milestone 10c updated: timeout field shown as readonly greyed-out input (not hidden) when source is Kunena, using a custom `OnlineTimeoutField` form field class. Updated directory structure to include new files. See `docs/execution_changelog.md` for details.
- 2026-02-22: Milestone 10a simplified per user feedback: replaced AJAX-based "Mark as Verified" button with a standard Joomla `radio` field (`hashes_verified`) in the plugin settings. Admin reviews hashes in a read-only `UpstreamHashes` custom field, sets radio to "Verified", and clicks standard Save. Eliminates need for `com_ajax` handler, `onAjaxCbuseronlinestatus` event, `VerifyHashesField.php`, and JavaScript. Renamed custom field file from `VerifyHashesField.php` to `UpstreamHashesField.php` (read-only display only). See `docs/execution_changelog.md` for details.
- 2026-02-22: Plan amended per review v7 (`docs/execution_plan.review.v7.md`). Addressed all 3 findings: (High) removed `disablePlugin()` from 10a — plugin stays enabled and relies on `hashes_verified` param to gate autoloader, ensuring admin always sees warnings; (Medium) wrapped all 10a warning messages in `isClient('administrator')` guard to prevent frontend visitors from seeing file paths; (Low) changed 10c `OnlineTimeoutField` from `readonly` to `disabled` with `name` attribute removed, so Kunena value is display-only and doesn't overwrite the stored manual value on form save. See `docs/execution_changelog.md` for details.
- 2026-02-22: Module admin UX clarified after 10b decoupling: added a display-only runtime timeout field in module settings (effective timeout from application state, falling back to module timeout) and relabeled the editable module timeout field to "Fallback Timeout" to reflect runtime behavior. Updated module language strings and README. See `docs/execution_changelog.md` for details.
