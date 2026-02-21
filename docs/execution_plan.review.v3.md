# Execution Plan Review v3

## Scope

Third review of `docs/execution_plan.md` against:

- Live CB/Joomla code at `..\ecskc.eu.sites\prod-html`
- Project rules in `AGENTS.md`
- Prior reviews `docs/execution_plan.review.v1.md` and `docs/execution_plan.review.v2.md`

Review date: **2026-02-21**.

## Overall Assessment

Status: **Much improved; v1/v2 core issues are largely resolved.**

The plan is now close to implementation-ready. Most v2 findings were correctly addressed, including PMS mandatory scope text, autoload language requirement, shell command variants, and SQL value-hardening guidance.

## Confirmed Fixes Since v2

1. PMS conditional wording was removed in Artifacts:
- `docs/execution_plan.md:633` now uses mandatory wording.

2. `autoloadLanguage` is now specified for the plugin class:
- `docs/execution_plan.md:212`
- `docs/execution_plan.md:652`

3. PowerShell variants were added for command examples:
- `docs/execution_plan.md:468`
- `docs/execution_plan.md:469`
- `docs/execution_plan.md:502`
- `docs/execution_plan.md:503`
- `docs/execution_plan.md:527`

4. SQL value hardening guidance was added:
- `docs/execution_plan.md:351`

5. Live-repo facts still match plan assumptions:
- `StatusField` patched timeout exists: `..\ecskc.eu.sites\prod-html\components\com_comprofiler\plugin\user\plug_cbcore\library\Field\StatusField.php:43`
- Mode 1 timeout patch exists: `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:400`
- PMS notification check remains unpatched in source: `..\ecskc.eu.sites\prod-html\components\com_comprofiler\plugin\user\plug_pms_mypmspro\library\Table\MessageTable.php:133`
- Shared-session branching still present in original module: `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:128`, `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:187`, `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:250`, `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:262`, `..\ecskc.eu.sites\prod-html\modules\mod_comprofileronline\mod_comprofileronline.php:273`.

## Remaining Findings (Ordered by Severity)

### 1. High - Milestone 1 autoloader fallback behavior is technically incorrect as written

Milestone 1 says missing override files will cause `require` calls to "fail silently" and then CB originals load:

- `docs/execution_plan.md:204`

That is not correct for raw `require` unless guarded (`file_exists`/`is_readable`) before including. As currently described, this can fatal during Milestone 1.

Required edit:
- Specify explicit guard logic in the autoloader (`if (is_readable($path)) { require $path; return true; } return false;`) so fallback to CB autoload is deterministic before Milestones 2/3 create override files.

### 2. Medium - Directory tree rendering is still corrupted (mojibake)

The repository tree section still contains broken box-drawing characters:

- `docs/execution_plan.md:138` onward (e.g. `ÃÄÄ`, `ÀÄÄ`)

This reduces readability for novice execution and contradicts earlier changelog language about formatting hygiene.

Required edit:
- Replace with plain ASCII tree (using `|--`, `` `-- ``) or proper UTF-8 box characters.

### 3. Low - MessageTable line-count evidence should be normalized to avoid tool-dependent confusion

Plan currently asserts `wc -l` evidence as 639:

- `docs/execution_plan.md:39`

In this environment, non-empty line indexing shows line 640 in the same file (`..\ecskc.eu.sites\prod-html\components\com_comprofiler\plugin\user\plug_pms_mypmspro\library\Table\MessageTable.php:640`). This difference is likely newline-at-EOF/tool behavior, not a substantive plan error.

Required edit:
- Use wording like "~640 lines" or include both measures with a short note to prevent recurring review churn.

## Conclusion

No architecture blockers remain. After fixing the autoloader fallback wording/logic and cleaning the tree formatting, the execution plan is in good shape for implementation.
