# Execution Plan Review v4

## Scope

Fourth review of `docs/execution_plan.md` against:

- Live Joomla/CB source in `..\ecskc.eu.sites\prod-html`
- Prior reviews (`docs/execution_plan.review.v1.md`, `docs/execution_plan.review.v2.md`, `docs/execution_plan.review.v3.md`)
- Project constraints in `AGENTS.md`

Review date: **2026-02-21**.

## Overall Assessment

Status: **No blocking findings.**

The three v3 findings are addressed in the current plan revision:

- Autoloader now documents `is_readable($path)` guard behavior.
- Repository tree is plain ASCII and renders correctly in Windows tools.
- MessageTable size language is normalized to `~640` with tool-variance note.

The plan is implementation-ready from a review perspective.

## Findings (Ordered by Severity)

### 1. Low - Autoloader description still has one outdated wording detail in Milestone 2

Milestone 2 says the autoloader "returns true" to prevent CB's autoloader:

- `docs/execution_plan.md:250`

Elsewhere in the same plan, the method contract is correctly modeled as `void` and guard-based (`is_readable`) in Milestone 1 / Interfaces. This is only a wording inconsistency, but it can confuse implementers.

Suggested edit:

- Replace "returns true, preventing CB's own autoloader" with wording like "loads the override class first; once class exists, CB's autoloader is not used for that class".

### 2. Low - Keep line-count evidence command guidance shell-safe and reproducible

The plan now correctly uses `~640`, but evidence still references `wc -l` as primary:

- `docs/execution_plan.md:40`

Given this workspace has WSL access constraints in some sessions, consider making the PowerShell/native check primary and keeping `wc -l` as optional.

Suggested edit:

- Add a single shell-agnostic check example (or list PowerShell first) to reduce repeat review churn on line-count interpretation.

## Confirmed v3 Fixes

1. `is_readable` guard is now explicitly documented for override loading:
- `docs/execution_plan.md:213`
- `docs/execution_plan.md:289`
- `docs/execution_plan.md:678`

2. ASCII tree replacement is complete:
- `docs/execution_plan.md:162` onward

3. PMS artifact wording is mandatory (no feasibility hedge):
- `docs/execution_plan.md:644`

4. SQL hardening guidance remains explicit:
- `docs/execution_plan.md:351`

5. `autoloadLanguage` remains included in plugin spec:
- `docs/execution_plan.md:221`
- `docs/execution_plan.md:663`

## Conclusion

Execution plan quality is now high and materially improved across v1-v4. No architectural or implementation blockers remain.
