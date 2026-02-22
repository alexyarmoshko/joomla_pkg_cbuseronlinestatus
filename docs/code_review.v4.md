# Code Review v4

Date: 2026-02-22  
Scope: Follow-up review of `main` at `6f7b7be` ("Third code review amendments").

## Findings (Ordered by Severity)

No new functional or release-blocking findings were identified in this pass.

## Verification of v3 Findings

- `v3#1` (Medium: SHA256 publication pipeline no-op): **Fixed**.
  - `Makefile:51`, `Makefile:54`, and `Makefile:72` now replace the value inside `<sha256>...</sha256>` directly.
  - Update feed sources intentionally keep empty `<sha256></sha256>` nodes (`mod_cbuseronlinestatus.update.xml:14`, `plg_system_cbuseronlinestatus.update.xml:14`, `pkg_cbuseronlinestatus.update.xml:14`), which are compatible with the new replacement approach.
- `v3#2` (Low: RELEASE.md inconsistency): **Fixed**.
  - `RELEASE.md:31` now documents the tag-value replacement approach instead of placeholder-token restoration.

## Validation Performed

- Commit-level review:
  - `git show --patch --unified=3 6f7b7be -- Makefile RELEASE.md`
- Consistency check:
  - `rg -n "<sha256>|__SHA256_" -g "*.xml" .`
  - Result: no `__SHA256_*` placeholders remain; update feeds retain empty `<sha256>` nodes as intended.

## Residual Risk / Testing Gap

- `make dist` was not executed in this environment, so end-to-end runtime confirmation of hash injection into generated update manifests is still pending local build verification.
