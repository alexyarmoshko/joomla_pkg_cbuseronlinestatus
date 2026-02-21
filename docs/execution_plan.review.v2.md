# Execution Plan Review v2

## Scope

Second review of `docs/execution_plan.md` against:

- Live CB/Joomla code in `..\ecskc.eu.sites\prod-html`
- Project implementation rules in `AGENTS.md`
- Prior review baseline in `docs/execution_plan.review.v1.md`

Review date: **2026-02-21**.

## Overall Assessment

Status: **Substantially improved vs v1; no architecture blockers found.**

The major v1 issues (baseline accuracy, `shared_session`, mandatory PMS scope, hook compatibility, deterministic validation) are now addressed. Remaining items are consistency, implementation-safety, and standards-compliance gaps.

## Findings (Ordered by Severity)

### 1. High - Mandatory PMS decision is still contradicted in Artifacts

The plan now states PMS override is mandatory in Milestone 3, but Artifacts still says the fix is conditional:

- Mandatory statement: `docs/execution_plan.md:232`
- Contradicting text: `docs/execution_plan.md:591`

At `docs/execution_plan.md:591`, the snippet still says `// Desired fix (if override is feasible):`, which reintroduces ambiguity removed in v1.

### 2. High - Plugin localization requirement is missing from implementation spec

Project instructions require namespaced service-provider plugins to set:

- `AGENTS.md:50` -> `protected $autoloadLanguage = true;`

The execution plan does not specify this in `CbUserOnlineStatus` interfaces/milestones, which risks missing runtime language loading behavior.

### 3. Medium - Milestone 1 contains an internal sequencing contradiction

Milestone 1 says the autoloader is registered but "not yet loading any override classes":

- `docs/execution_plan.md:187`

But in the same milestone, class behavior is described as already intercepting and loading both overrides:

- `docs/execution_plan.md:195`

This should be made decision-complete: either Milestone 1 only registers a passive loader, or it actively intercepts with placeholder files present.

### 4. Medium - Stale evidence: MessageTable length claim no longer matches current repo

Plan states 639 lines:

- `docs/execution_plan.md:37`
- `docs/execution_plan.md:236`

Current file in full repo is 513 lines (measured on 2026-02-21):

- `..\ecskc.eu.sites\prod-html\components\com_comprofiler\plugin\user\plug_pms_mypmspro\library\Table\MessageTable.php`

This is not a functional blocker, but it weakens trust in discovery evidence.

### 5. Medium - Validation commands are mixed-shell and not novice-safe as written

The plan uses Unix utilities in verification steps (`grep`, `wc`, `mkdir -p`) in a Windows-centered repo context:

- `docs/execution_plan.md:38`
- `docs/execution_plan.md:449`

Either explicitly require a Bash/WSL/DDEV shell for those commands or provide PowerShell equivalents alongside them.

### 6. Medium - DB safety guidance should be made explicit for timeout SQL construction

Plan currently describes interpolated timeout SQL:

- `docs/execution_plan.md:331`
- `docs/execution_plan.md:334`

Given project data-access rules (`AGENTS.md:55`-`AGENTS.md:57`), the plan should explicitly require integer-casting/binding for timeout values and preserve safe handling for `exclude` lists.

## What Was Fixed Since v1 (Confirmed)

- Baseline now reflects that `StatusField` and mode 1 are already patched.
- `shared_session` branching is now specified for module queries.
- PMS is now in mandatory scope in Milestone 3.
- Hook parity strategy via `modCBOnlineHelper::getPlugins()` is now documented.
- Validation now uses a deterministic matrix V1-V9.

## Required Edits Before Implementation

1. Remove all conditional wording for PMS override feasibility in Artifacts; keep language strictly mandatory.
2. Add `protected $autoloadLanguage = true;` to the `CbUserOnlineStatus` class spec and milestone acceptance notes.
3. Resolve Milestone 1 sequencing contradiction (passive registration vs active interception).
4. Update MessageTable line-count evidence to current value or remove exact count.
5. Normalize command examples by shell (PowerShell + Bash variants).
6. Explicitly state timeout/exclude query value hardening approach (casting/binding).

## Residual Risk

Even after the above edits, the main ongoing risk remains upstream CB drift for full-class overrides (`StatusField`, `MessageTable`). Keep the per-release diff/rebase check in README and release process.
