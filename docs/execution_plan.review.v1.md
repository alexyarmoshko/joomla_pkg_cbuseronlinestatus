# Execution Plan Review v1

## Scope

This review checks `docs/execution_plan.md` against the current Joomla/CB codebase in `..\ecskc.eu.sites\prod-html` and existing extension conventions in `..\joomla_plg_cbgjguestaccess` and `..\joomla_mod_ystides`.

## Overall Assessment

Status: **Needs revision before implementation**.

The plan has the right direction, but it is not yet decision complete and contains several factual mismatches against the current CB/Joomla state that can cause regressions if implemented as written.

## Findings (Ordered by Severity)

### 1. Blocker - Baseline is outdated in key places

`docs/execution_plan.md` still describes StatusField and module logic as fully unpatched, but production code already contains timeout patches in two places:

- `..\ecskc.eu.sites\prod-html\components\com_comprofiler\plugin\user\plug_cbcore\library\Field\StatusField.php:43`
- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:400`

The plan text at `docs/execution_plan.md:76`, `docs/execution_plan.md:81`, and `docs/execution_plan.md:186` should be updated to reflect this current reality. Otherwise the implementation narrative and acceptance checks are misleading.

### 2. Blocker - Proposed module SQL drops `shared_session` compatibility

The plan repeatedly hardcodes `client_id = 0` in new queries (for example `docs/execution_plan.md:309`, `docs/execution_plan.md:322`, `docs/execution_plan.md:507`).

Current CB module behavior supports shared-session deployments by switching between `IS NULL` and `= 0`:

- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:128`
- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:187`
- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:250`
- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:262`
- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:273`

If this is not preserved, online counts and lists can break on shared-session sites.

### 3. Blocker - PMS milestone is not decision complete

Milestone 3 contains conflicting directions:

- It proposes an override implementation (`docs/execution_plan.md:217` onward).
- It also leaves a fallback to “maybe manual patch” (`docs/execution_plan.md:235`, `docs/execution_plan.md:237`).

Given the selected scope, PMS is mandatory for v1. The plan must commit to one concrete implementation path and remove fallback ambiguity.

Confirmed PMS class namespace in live code:

- `..\ecskc.eu.sites\prod-html\components\com_comprofiler\plugin\user\plug_pms_mypmspro\library\Table\MessageTable.php:10`

### 4. High - CB plugin hook parity is under-specified for the replacement module

The original module templates depend on `modCBOnlineHelper::getPlugins()` hook output across multiple locations:

- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\helper.php`
- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\tmpl\default.php`
- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\tmpl\_statistics.php`
- `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\tmpl\_census.php`

The plan only says “if available” (`docs/execution_plan.md:346`) without defining how compatibility is guaranteed. This can produce silent behavior differences for CB plugin integrations.

### 5. High - Validation steps are partly non-deterministic

`docs/execution_plan.md:336` suggests temporary debug output for verification. This is not reliable acceptance criteria for a package-level release.

The plan should require deterministic checks per mode and per PMS notification behavior, with expected outcomes.

### 6. Medium - Plan hygiene and consistency issues

- `Progress` lacks timestamped entries despite PLANS guidance (`docs/execution_plan.md:17`).
- ASCII tree rendering is corrupted (`docs/execution_plan.md:117`).
- “Affects every place” language (`docs/execution_plan.md:9`) conflicts with explicit non-coverage notes (`docs/execution_plan.md:41`) and optional PMS fallback text.

## Required Edits to `docs/execution_plan.md`

1. Update context/baseline to explicitly state:
- `StatusField.php` is already timeout-patched.
- `mod_comprofileronline.php` mode 1 is already timeout-patched.
- Remaining stale areas are mode 9, mode 6, mode 7, and PMS notification check in MessageTable.

2. Replace hardcoded `client_id = 0` assumptions in new module queries with the same `shared_session` branching used by the original module.

3. Make Milestone 3 decision complete:
- Keep PMS in-scope and mandatory for v1.
- Remove manual-patch fallback language.
- Specify exact class interception target as `CB\Plugin\PMS\Table\MessageTable`.

4. Define explicit CB plugin hook compatibility strategy for the replacement module templates and helper.

5. Replace debug-style verification with deterministic behavior tests and expected outcomes.

6. Clean formatting and update living sections:
- Fix tree rendering.
- Add timestamped progress format.
- Tighten scope wording to match what is truly covered.

## Locked Decisions for v1

- PMS notification fix is mandatory in package scope.
- Shared-session compatibility must be preserved.
- Review baseline should use the current live code state, not historical assumptions.

## Validation Matrix to Add Into Execution Plan

1. Baseline check: verify current patched status in live CB files before implementation starts.
2. Mode 1 check: online users list excludes stale sessions.
3. Mode 9 check: online connections excludes stale sessions.
4. Mode 6 check: online/offline/guest stats remain internally consistent with timeout logic.
5. Mode 7 check: census online count uses timeout logic.
6. Shared-session check: behavior works in both `IS NULL` and `= 0` client_id conditions.
7. PMS check: with `messages_notify_offline=1`, stale-session recipient gets notification email.
8. Revert check: after restoring CB `.original.php` files, behavior remains correct through package only.

## Residual Risks

- Full-class CB overrides can drift with upstream CB updates; plan should include explicit upgrade diff checks per release.
- Module behavior parity risk remains unless plugin-hook compatibility is fully specified and tested.
