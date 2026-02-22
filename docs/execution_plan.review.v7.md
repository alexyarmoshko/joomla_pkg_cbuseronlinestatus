# Execution Plan Review v7

## Scope

Seventh review of `docs/execution_plan.md` focusing entirely on the newly added Milestone 10 (Post-release hardening), specifically:
- 10a: Upstream file-hash verification with admin verification gate
- 10b: Decouple module from plugin namespace
- 10c: Kunena timeout synchronization

Review date: **2026-02-22**.

## Overall Assessment

Status: **Changes Required (1 High severity blocker, 1 Medium severity issue).**

The additions in Milestone 10 are architecturally sound and provide excellent safety rails against upstream CB changes. However, there is a core logical flaw in the self-disabling mechanism (10a) that will result in the administrator never seeing the warning if a file change is triggered by a frontend visitor. 

## Findings (Ordered by Severity)

### 1. High - "Vanishing Warning" due to `disablePlugin()` logic (Milestone 10a)

**Issue:** 
The plan dictates that if an upstream file change is detected, the plugin sets `hashes_verified = 0`, enqueues a warning message, and calls `disablePlugin()` (which sets `enabled = 0` in the database). 
If the request that triggers this check is a frontend request by a random site visitor, the plugin will disable itself *during that frontend request*. 
On all subsequent requests (including when the administrator eventually logs into the backend), Joomla will not load the plugin because it is disabled in the database. Consequently, the plugin's `onAfterInitialise()` will never run, and the administrator will **never see the warning message**. The plugin will simply stop functioning silently without notifying the admin.

**Suggested Fix:**
Do not disable the plugin (`enabled = 0`). Instead, rely purely on the internal state `hashes_verified = 0`. 
- When an upstream change is detected, update `hashes_verified = 0` via `saveParams()`, and simply `return;` without registering the autoloader. 
- The plugin remains active (`enabled = 1`) in Joomla, but the overrides remain safely inactive.
- Because the plugin is still running, it will continue to successfully enqueue the `PLG_SYSTEM_CBUSERONLINESTATUS_HASHES_NOT_VERIFIED` warning on *every* backend request until the admin actually acknowledges it and re-verifies the hashes.

### 2. Medium - Frontend message queue leak (Milestone 10a)

**Issue:**
Step 4 of the 10a lifecycle states: "Enqueues a warning message naming the changed file(s) and instructing the administrator to review the changes..."
If an upstream file change is first triggered by a frontend visitor, this warning message will be enqueued unconditionally and might be displayed in the frontend template to the visitor, leaking system file paths and update status.

**Suggested Fix:**
Explicitly specify that the mismatch warning enqueued in step 4 must be wrapped in an `isClient('administrator')` check, identical to the logic used for the "not verified" warning in step 3. 

### 3. Low - Preservation of manual timeout value in Kunena mode (Milestone 10c)

**Issue:**
In 10c, the custom `OnlineTimeoutField` renders the Kunena timeout value as a `readonly` input. In standard HTML forms, `readonly` fields are still submitted in the POST payload. This means that when the admin clicks "Save" while in Kunena mode, the Kunena value will be submitted and Joomla will save it to the plugin's `online_timeout` parameter, overwriting the user's previously configured manual value in the database.

**Suggested Fix:**
This may be edge-case behavior that doesn't strictly break the application, but it violates the premise of "falling back to manual timeout value" if Kunena is later uninstalled or disabled. 
If the intent is to preserve the manual value in the database:
- Modify the `OnlineTimeoutField` to use `disabled="disabled"` instead of `readonly="readonly"` (and strip the `name` attribute if needed), so the Kunena value is strictly for display and not submitted. 
- *Alternatively*, display the Kunena value in a simple span or description text next to the field, rather than injecting it into the `<input>`.

## Conclusion

Milestones 10b and 10c are well-conceived and use proper Joomla DI/Event patterns. Milestone 10a's integrity check is an excellent addition, but its implementation must be tweaked to drop the `disablePlugin()` concept to ensure the notification successfully reaches the administrator.
