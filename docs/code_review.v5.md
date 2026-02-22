# Code Review v5 (Final)

Date: 2026-02-22  
Scope: Final follow-up review of `main` at `609031e` ("Fix to makefile sha256 calculation") with artifact verification after `make dist`.

## Findings (Ordered by Severity)

### 1. Medium: Makefile version extraction still fails in this Windows/PowerShell environment (`grep -P` dependency), and `make info` exits successfully with invalid output

- Evidence:
  - `Makefile:13` uses `grep -oP` to extract the package version.
  - Running `make info` in this environment prints an empty version and malformed artifact names (`*- .zip`), while also showing `CreateProcess(... grep ...) failed`.
  - The command still returns exit code `0`, so the failure is easy to miss.
- Impact:
  - On environments without GNU `grep` (or without `-P` support), local build metadata resolution can silently fail.
  - Developers may get invalid artifact names or broken builds while the `make` target appears successful.
- Recommended fix:
  - Replace the `grep -oP` version extraction with a more portable approach (for example `awk`, `sed`, `xmllint`, or a PowerShell-compatible path), and fail fast if `VERSION` is empty.

## Verification of Recent SHA256 Build Fix

- `v4` residual gap (runtime SHA injection verification): **Resolved**.
  - Built artifacts are present in `installation/` with versioned names.
  - Update feed files now contain populated SHA256 values.
  - Current feed SHA256 values match the computed SHA256 of the generated ZIP artifacts.

## Artifact Validation Performed

- Artifact presence:
  - `installation/mod_cbuseronlinestatus-1.0.0.zip`
  - `installation/plg_system_cbuseronlinestatus-1.0.0.zip`
  - `installation/pkg_cbuseronlinestatus-1.0.0.zip`
- Hash verification (computed vs update XML):
  - Module ZIP SHA256 = `108C170AAC6DD3AA2E5298C8CC8DB41E7F453024A301641DC9F9A487A2EA2EEB`
    - Matches `mod_cbuseronlinestatus.update.xml:13` (`108c170aac6dd3aa2e5298c8cc8db41e7f453024a301641dc9f9a487a2ea2eeb`)
  - Plugin ZIP SHA256 = `AE2E53C813E7C82762A55A5AD578DD39730EB874AE07D59FDA66D4ADEE25CCBA`
    - Matches `plg_system_cbuseronlinestatus.update.xml:13` (`ae2e53c813e7c82762a55a5ad578dd39730eb874ae07d59fda66d4adee25ccba`)
  - Package ZIP SHA256 = `F7624D238A0B5215754C95F83FBFD47D3DC0507D48757274D0AE4437CC1FD3DA`
    - Matches `pkg_cbuseronlinestatus.update.xml:13` (`f7624d238a0b5215754c95f83fbfd47d3dc0507d48757274d0ae4437cc1fd3da`)
- Package content verification:
  - `installation/pkg_cbuseronlinestatus-1.0.0.zip` contains versioned child ZIPs.
  - Embedded `pkg_cbuseronlinestatus.xml` inside the package references versioned child ZIP filenames.

## Final Assessment

- Release artifact integrity metadata generation is working in the verified build output.
- No new release-blocking issues were identified beyond the remaining Makefile version-extraction portability problem noted above.
