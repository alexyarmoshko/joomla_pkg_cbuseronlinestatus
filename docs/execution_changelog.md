# Execution Changelog — pkg_cbuseronlinestatus

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
