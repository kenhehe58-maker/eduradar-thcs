# DISPATCH — worker_e2e_1

## Objective
Run `D:\xam\php\php.exe tests/run_tests.php` against live Apache (port 80) and MariaDB (port 3306). Identify and resolve the 3 test discrepancies so that all 42/42 tests pass with 0 failures and exit code 0. Publish `TEST_READY.md`.

## Context & Inputs
- Authoritative User Request: `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md`
- Global Architecture & Inventory: `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md`
- Test Runner: `D:\New folder\my-web-project\tests\run_tests.php`
- Explorer Reports:
  - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_1\handoff.md`
  - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2\handoff.md`
  - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_3\handoff.md`

## Known Test Discrepancies
From initial run (39/42 passed):
1. Logout / unauthenticated redirection HTTP status assertion (e.g. test expecting 302 vs 403 or vice versa on logout/unauth routes, check both test expectation and application code according to ORIGINAL_REQUEST.md requirements).
2. Unauth access HTTP status code on admin actions/routes (check ORIGINAL_REQUEST.md R1: "Only role === 'admin' can access admin.php or trigger any admin action. Teachers or unauthenticated users get HTTP 403 / redirect with zero database changes").
3. Plus Jakarta Sans font in `admin.php` (check R6: "crisp typography (Plus Jakarta Sans / Inter)").

## Mandatory Integrity Warning
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

## Deliverables
- Execute `D:\xam\php\php.exe tests/run_tests.php` to see current failure output.
- Fix discrepancies cleanly in `tests/run_tests.php` or application code.
- Re-run `D:\xam\php\php.exe tests/run_tests.php` and verify 42/42 PASS, 0 failures, exit code 0.
- Verify regressions across `tests/verify_m1.php` through `tests/verify_m5.php`.
- Create `TEST_READY.md` at project root `D:\New folder\my-web-project\TEST_READY.md` summarizing test coverage and runner command.
- Write completion report to `D:\New folder\my-web-project\.agents\teamwork\worker_e2e_1\handoff.md` and notify orchestrator.


## 2026-10-17T14:52:03Z
[Message] timestamp=2026-10-17T14:52:03Z sender=5193827a-cbeb-4c91-8825-758b95342978 priority=MESSAGE_PRIORITY_HIGH content=You are worker_e2e_1. Your working directory is D:\New folder\my-web-project\.agents\teamwork\worker_e2e_1.
You MUST read:
1. Authoritative User Request: D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
2. Global Architecture: D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
3. Worker Dispatch: D:\New folder\my-web-project\.agents\teamwork\worker_e2e_1\DISPATCH.md
4. Test Runner: D:\New folder\my-web-project\tests\run_tests.php
