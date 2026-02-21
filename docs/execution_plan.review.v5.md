# Execution Plan Review v5

## Scope

Fifth review of `docs/execution_plan.md` after the v4 amendments, with focus on validation-command correctness and reproducibility.

- Baseline and revert verification steps
- Internal consistency with documented patch patterns
- Practical testability in this Windows-primary workspace

Review date: **2026-02-21**.

## Overall Assessment

Status: **No blockers, but 2 medium validation-gaps remain.**

The v4 fixes are correctly present:

- Milestone 2 autoloader wording now matches the void/guard model.
- MessageTable line-count evidence order now lists PowerShell first.

However, two verification commands still do not test the intended StatusField condition, and one module revert check remains broader than necessary.

## Findings (Ordered by Severity)

### 1. Medium - V1 baseline command does not validate the StatusField patch pattern

Current V1 baseline guidance searches for `UNIX_TIMESTAMP` in `StatusField.php`:

- `docs/execution_plan.md:480`
- `docs/execution_plan.md:481`

But the documented StatusField patch pattern is `(time() - $lastTime)<=1800`:

- `docs/execution_plan.md:35`
- `docs/execution_plan.md:483`

This means V1 can report success without actually confirming the patched StatusField logic.

Suggested edit:

- Change the StatusField check to search for `time() - $lastTime` (or the exact full expression), while keeping `UNIX_TIMESTAMP` for `mod_comprofileronline.php`.

### 2. Medium - Milestone 9 revert check for StatusField is non-diagnostic

The revert check again tests `UNIX_TIMESTAMP` in `StatusField.php`:

- `docs/execution_plan.md:514`
- `docs/execution_plan.md:515`

That pattern is absent in both patched and original StatusField versions, so this check cannot prove the revert occurred.

Suggested edit:

- For StatusField revert validation, assert that the timeout expression is absent/present as appropriate (e.g., count of `time() - $lastTime` or exact expression match).

### 3. Low - Revert check for module patch uses a broad `1800` token search

Milestone 9 uses `1800` count for `mod_comprofileronline.php`:

- `docs/execution_plan.md:514`
- `docs/execution_plan.md:515`

This is weaker than checking for the exact patch expression (`UNIX_TIMESTAMP() - time <= 1800`) and may be noisy if `1800` appears elsewhere.

Suggested edit:

- Replace generic `1800` checks with exact-expression checks for the patched line.

## Confirmed v4 Fixes

1. Autoloader wording fixed in Milestone 2:
- `docs/execution_plan.md:251`

2. Line-count evidence order now PowerShell-first:
- `docs/execution_plan.md:41`

3. Changelog records both updates:
- `docs/execution_changelog.md:7`
- `docs/execution_changelog.md:8`

## Conclusion

Plan quality remains high and implementation-ready at architecture level. Before execution, tighten V1 and Milestone 9 command checks so acceptance evidence reliably proves both patch presence and revert state.
