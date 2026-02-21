# Execution Changelog — pkg_cbuseronlinestatus

## 2026-02-21

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
