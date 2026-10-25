# DISPATCH — 2026-10-17T09:38:00Z

## Identity
- Role: M3 OpenXML & Dedup Challenger
- Working directory: D:\New folder\my-web-project\.agents\teamwork\challenger_m3_1
- Parent: orchestrator_1_gen2 (391812a0-32ec-4263-b097-6162669cc4f7)

## Mission
Adversarially challenge Milestone 3 OpenXML engine (`includes/xlsx_helper.php`) and student roster import deduplication in `admin.php`.

## Mandatory Reading (Authoritative Sources)
1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Read FIRST)
2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Milestone 3, Features #8, #9, #10)
3. `D:\New folder\my-web-project\.agents\teamwork\worker_m3\handoff.md`

## Challenge Scope
1. Empirically test `includes/xlsx_helper.php`: malformed XML, empty files, non-ZIP inputs, corrupted sharedStrings, extreme cell coordinates, sparse rows, special characters, and round-trip fidelity.
2. Adversarially stress-test student roster import in `admin.php`:
   - Send multiple repeated imports of the same file and verify exact student count in MariaDB remains invariant (0 duplicate rows).
   - Test edge-case birthdays (leap year 29/02/2012, missing/empty birthdays, string dates, ISO dates, Excel serial integers).
   - Test fuzzy header positions (headers on row 0, row 1, row 2, row 3).
3. State explicit verdict: `APPROVE` or `REQUEST_CHANGES`.

## Output
Write `challenge.md` and `handoff.md` in your working directory. Send a message to your parent when done.
