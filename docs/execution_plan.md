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
- [ ] Milestone 1: System plugin `plg_system_cbuseronlinestatus` — project scaffolding, class autoloader for StatusField and MessageTable overrides.
- [ ] Milestone 2: StatusField override class with configurable timeout.
- [ ] Milestone 3: MessageTable override for PMS notification fix (mandatory for v1).
- [ ] Milestone 4: Custom module `mod_cbuseronlinestatus` — scaffolding and manifest.
- [ ] Milestone 5: Module helper with session queries (modes 1, 6, 7, 9) and `shared_session` branching.
- [ ] Milestone 6: Module dispatcher and layouts with CB plugin hook compatibility.
- [ ] Milestone 7: Package manifest `pkg_cbuseronlinestatus`, Makefiles, README.
- [ ] Milestone 8: Integration testing on DDEV site — deterministic validation matrix (V1–V9).
- [ ] Milestone 9: Revert patched CB files, final validation.

## Surprises & Discoveries

- Observation: The production `StatusField.php` and `mod_comprofileronline.php` (mode 1) are already timeout-patched with a hardcoded 1800-second window. The initial plan described them as unpatched.
  Evidence: `StatusField.php` line 43: `$isOnline = ( $lastTime != null ) && ((time() - $lastTime)<=1800);`. `mod_comprofileronline.php` line 400: `AND (UNIX_TIMESTAMP() - time <= 1800)`.

- Observation: The CB module uses `shared_session` branching for `client_id` in every session query — not a hardcoded `= 0`. This is critical for shared-session Joomla deployments.
  Evidence: `$_CB_framework->getCfg('shared_session') ? " IS NULL" : " = 0"` appears at lines 128, 187, 250, 262, 273, and 397 of `mod_comprofileronline.php`.

- Observation: `MessageTable.php` is 639 lines, well above the initially estimated ~200 line threshold for "feasible full override". The override is still viable but carries higher drift risk on CB updates.
  Evidence: `wc -l MessageTable.php` returns 639.

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
  Rationale: Review v1 identified the "maybe manual patch" language as decision-incomplete. The PMS notification fix is a core goal of the package. Although the class is 639 lines, the full-body override is the only viable approach. Drift risk is managed by documenting an upgrade diff check per CB release in the README.
  Date/Author: 2026-02-21 / AI (review v1)

- Decision: Preserve `shared_session` branching (`client_id IS NULL` vs `= 0`) in all module session queries.
  Rationale: Review v1 identified hardcoded `client_id = 0` in the plan as a blocker. The original CB module uses `$_CB_framework->getCfg('shared_session')` branching at every `client_id` condition. Dropping this would break online counts on shared-session sites.
  Date/Author: 2026-02-21 / AI (review v1)

- Decision: Reuse `modCBOnlineHelper::getPlugins()` from the original CB module for plugin hook compatibility in the replacement module templates.
  Rationale: Review v1 flagged under-specification of CB plugin hook parity. The original templates call this helper at multiple hook points. Including the original `helper.php` at render time (guarded by `class_exists`) preserves third-party CB plugin integration and degrades gracefully if the original module is uninstalled.
  Date/Author: 2026-02-21 / AI (review v1)

## Outcomes & Retrospective

(To be populated at completion.)

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
    ├── docs/
    │   ├── execution_plan.md          (this file)
    │   └── execution_changelog.md
    ├── plg_system_cbuseronlinestatus/
    │   ├── cbuseronlinestatus.xml      (plugin manifest)
    │   ├── services/
    │   │   └── provider.php
    │   ├── src/
    │   │   ├── Extension/
    │   │   │   └── CbUserOnlineStatus.php   (main plugin class)
    │   │   ├── Field/
    │   │   │   └── StatusField.php          (StatusField override)
    │   │   └── Table/
    │   │       └── MessageTable.php         (MessageTable override)
    │   ├── language/
    │   │   └── en-GB/
    │   │       ├── plg_system_cbuseronlinestatus.ini
    │   │       └── plg_system_cbuseronlinestatus.sys.ini
    │   ├── index.html
    │   └── LICENSE
    ├── mod_cbuseronlinestatus/
    │   ├── mod_cbuseronlinestatus.xml  (module manifest)
    │   ├── services/
    │   │   └── provider.php
    │   ├── src/
    │   │   ├── Dispatcher/
    │   │   │   └── Dispatcher.php
    │   │   └── Helper/
    │   │       └── CbUserOnlineStatusHelper.php
    │   ├── tmpl/
    │   │   ├── default.php             (Online Users list)
    │   │   ├── default_statistics.php  (Online Statistics)
    │   │   └── default_census.php      (User Census)
    │   ├── language/
    │   │   └── en-GB/
    │   │       ├── mod_cbuseronlinestatus.ini
    │   │       └── mod_cbuseronlinestatus.sys.ini
    │   ├── index.html
    │   └── LICENSE
    ├── pkg_cbuseronlinestatus.xml      (package manifest)
    ├── Makefile                         (top-level: builds plugin, module, package ZIPs)
    ├── installation/                    (build output)
    ├── LICENSE
    ├── README.md
    └── RELEASE.md
```

### Milestone 1: System plugin scaffolding

After this milestone, the plugin project structure exists with all boilerplate files, the Joomla manifest, the DI service provider, and the main plugin class. The plugin subscribes to `onAfterInitialise` and registers a prepended PHP autoloader. The autoloader is set up but not yet loading any override classes.

Files to create:

`plg_system_cbuseronlinestatus/cbuseronlinestatus.xml` — Joomla plugin manifest. Type `plugin`, group `system`, method `upgrade`. Namespace `YakShaver\Plugin\System\Cbuseronlinestatus` with path `src`. One configurable parameter: `online_timeout` (integer field, default `1800`, label "Online Timeout (seconds)", description "Users with session activity older than this many seconds are considered offline. Default: 1800 (30 minutes)."). Include language folder and update server pointing to `https://raw.githubusercontent.com/alexyarmoshko/joomla_pkg_cbuseronlinestatus/refs/heads/main/plg_system_cbuseronlinestatus.update.xml`.

`plg_system_cbuseronlinestatus/services/provider.php` — DI provider following the pattern from the `remember` plugin. Creates `CbUserOnlineStatus` instance, sets application and database.

`plg_system_cbuseronlinestatus/src/Extension/CbUserOnlineStatus.php` — Main plugin class in namespace `YakShaver\Plugin\System\Cbuseronlinestatus\Extension`. Extends `CMSPlugin`, implements `SubscriberInterface`, uses `DatabaseAwareTrait`. Subscribes to `onAfterInitialise`. In the handler, if this is a site client request, calls `spl_autoload_register` with a prepended autoloader method. The autoloader checks if the requested class is `CB\Plugin\Core\Field\StatusField` or `CB\Plugin\PMS\Table\MessageTable` and, if so, loads the corresponding override from the plugin's own `src/Field/StatusField.php` or `src/Table/MessageTable.php`. The timeout value is read from `$this->params->get('online_timeout', 1800)` and stored in a static property accessible by the override classes.

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

The autoloader logic in the plugin class works as follows: when PHP attempts to load `CB\Plugin\Core\Field\StatusField`, the prepended autoloader checks if this exact FQCN is requested. If yes, it requires the override file from the plugin directory and returns `true`, preventing CB's own autoloader from loading the original. Because CB uses PSR-4 autoloading through `CBLib`, and our autoloader is prepended, our version loads first.

Important: the override file must declare the exact same namespace (`CB\Plugin\Core\Field`) and class name (`StatusField`) and must include all the same `use` statements and methods as the original. The class must NOT extend the original (since we are replacing it entirely). Copy all three methods verbatim (`getField`, `prepareFieldDataSave`, `bindSearchCriteria`) and modify only the `$isOnline` assignment in `getField`.

Verification: With the plugin installed and enabled, navigate to a user's profile page. The online status indicator should show "OFFLINE" for users who have not had session activity in the last 30 minutes, even if they have a `#__session` row. To test: log in as a test user, note they show as "ONLINE", then manually update their session `time` in the database to be 31 minutes old (`UPDATE #__session SET time = UNIX_TIMESTAMP() - 1860 WHERE userid = ?`), and refresh — the indicator should now show "OFFLINE".

### Milestone 3: MessageTable override for PMS notifications

After this milestone, the PMS (Private Messaging System) notification logic correctly treats users with stale sessions as offline, allowing email notifications to be sent. This is mandatory for v1 — there is no manual-patch fallback.

The autoloader in `CbUserOnlineStatus.php` is extended to also intercept `CB\Plugin\PMS\Table\MessageTable`. The FQCN is confirmed from the file header: `namespace CB\Plugin\PMS\Table;` in `components/com_comprofiler/plugin/user/plug_pms_mypmspro/library/Table/MessageTable.php`.

The class is 639 lines, which makes a full-body copy fragile against upstream CB updates. However, it is the only viable approach because `store()` cannot be overridden via inheritance without a circular autoloader dependency (the override must replace, not extend, the original). To manage the drift risk, the README must document that after each CB update the override file should be diffed against the new upstream `MessageTable.php` and the single-line change reapplied if needed.

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
    if ( $class === 'CB\\Plugin\\Core\\Field\\StatusField' ) {
        require __DIR__ . '/../Field/StatusField.php';
    } elseif ( $class === 'CB\\Plugin\\PMS\\Table\\MessageTable' ) {
        require __DIR__ . '/../Table/MessageTable.php';
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

where `{$timeout}` is the configured timeout in seconds. The helper uses `$_CB_database` for consistency with the original module code and CB user rendering.

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

**V1 — Baseline check.** Before any testing, confirm the current patched state of the live CB files. Run: `grep -n 'UNIX_TIMESTAMP' components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.php` and `grep -n 'UNIX_TIMESTAMP' modules/mod_comprofileronline/mod_comprofileronline.php` from the site root. Expected: StatusField.php line 43 contains `(time() - $lastTime)<=1800`, mod_comprofileronline.php line 400 contains `UNIX_TIMESTAMP() - time <= 1800`. This confirms the two already-patched locations.

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

2. Confirm the originals are restored by checking that the timeout patches are no longer present: `grep -c 'UNIX_TIMESTAMP' StatusField.php` should return 0, and `grep -c '1800' mod_comprofileronline.php` on the relevant line should return 0.

3. Refresh the site and re-run validation tests V2 and V8 from Milestone 8. The online-status behavior must remain correct — the package's autoloader and module now handle the timeout, not the file patches. If either test fails, the package is not working correctly and the revert should be rolled back.

4. The `.original.php` backup files can optionally be deleted.

Verification: Site loads without errors. V2 and V8 pass after revert. The CB files are now unmodified originals, safe for future CB updates.

## Concrete Steps

(To be updated as implementation proceeds. Below is the initial command sequence.)

Working directory: `C:\Users\alex\repos\joomla_pkg_cbuseronlinestatus\`

Step 1 — Create the directory structure:

```shell
    mkdir -p plg_system_cbuseronlinestatus/{services,src/{Extension,Field,Table},language/en-GB}
    mkdir -p mod_cbuseronlinestatus/{services,src/{Dispatcher,Helper},tmpl,language/en-GB}
    mkdir -p installation
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

    // Desired fix (if override is feasible):
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

        private static int $onlineTimeout = 1800;

        public static function getSubscribedEvents(): array
        // Returns ['onAfterInitialise' => 'onAfterInitialise']

        public function onAfterInitialise(AfterInitialiseEvent $event): void
        // Reads $this->params->get('online_timeout', 1800), stores in static property.
        // Registers prepended autoloader via spl_autoload_register.

        public static function getOnlineTimeout(): int
        // Returns self::$onlineTimeout (default 1800 if plugin not yet loaded).

        public function overrideAutoloader(string $class): void
        // If $class === 'CB\\Plugin\\Core\\Field\\StatusField', requires src/Field/StatusField.php.
        // If $class === 'CB\\Plugin\\PMS\\Table\\MessageTable', requires src/Table/MessageTable.php.
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
