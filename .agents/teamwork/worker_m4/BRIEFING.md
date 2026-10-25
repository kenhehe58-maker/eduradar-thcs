# BRIEFING — 2026-10-17T10:15:00Z

## Mission
Implement Milestone 4: Modern VnEdu Spreadsheet Gradebook in `nhap_diem.php` with 6 score columns (`tx1..tx4, gk, ck, avg_score`), 2D keyboard navigation, client-side validation, real-time calculation, database transactions, student ownership validation, RBAC/BOLA protection, gradebook .xlsx/.csv bulk import with upsert, .xlsx export, sample downloads, and comprehensive test suite `tests/verify_m4.php` ensuring 100% pass and regression safety.

## 🔒 My Identity
- Archetype: implementer
- Roles: implementer, qa, specialist
- Working directory: D:\New folder\my-web-project\.agents\teamwork\worker_m4
- Original parent: 391812a0-32ec-4263-b097-6162669cc4f7
- Milestone: Milestone 4

## 🔒 Key Constraints
- DO NOT CHEAT. All implementations must be genuine.
- Overhaul `nhap_diem.php` aligning with live MySQL `scores` schema (`tx1..tx4, gk, ck, avg_score`), supporting fallback for backward compatibility.
- Sticky 2-tier headers, student identity columns (STT, Mã HS, Họ và tên, Ngày sinh).
- 2D keyboard ergonomics (Enter/Down next student in column, Up, Tab/Shift+Tab, Focus select).
- Client-side validation (0.0-10.0, comma normalization, red border + tooltip).
- Real-time row average calculation per Thông tư 22.
- Dirty state indicator badge.
- Server-side transaction persistence (`beginTransaction`, `commit`, `rollBack`).
- Student ownership validation against target class.
- RBAC / BOLA enforcement (`require_teacher_assignment()`, teacher ID tampering prevention).
- Audit logging (`Lưu sổ điểm`).
- Bulk import (.xlsx and .csv) via `includes/xlsx_helper.php` with header row resilience and upsert.
- Bulk export (.xlsx) via `includes/xlsx_helper.php`.
- Sample download links.
- Create `tests/verify_m4.php` covering all 10 test groups, 100% passing.
- 100% pass on all regression test suites (verify_m1, verify_m2, verify_m3, challenge_m2_rbac).

## Current Parent
- Conversation ID: 391812a0-32ec-4263-b097-6162669cc4f7
- Updated: 2026-10-17T10:15:00Z

## Task Summary
- **What to build**: Modern VnEdu 6-column gradebook in `nhap_diem.php`, Excel/CSV import/export, sample template downloads, and `tests/verify_m4.php`.
- **Success criteria**: 10 test groups pass in `verify_m4.php` (39/39 pass), all 4 regression suites pass (93/93 pass), genuine code modification.
- **Interface contracts**: PROJECT.md Milestone 4, SCOPE.md, explorer_m4 reports.
- **Code layout**: Root directory PHP files, `includes/`, `tests/`.

## Key Decisions Made
- Overhauled `nhap_diem.php` with 6 score columns (`tx1..tx4, gk, ck, avg_score`), 2D keyboard navigation, sticky layout, real-time validation, and transaction persistence.
- Added `detect_gradebook_headers()` in `includes/xlsx_helper.php` for header resilience.
- Created `tests/verify_m4.php` covering all 10 test groups with 39 assertions.

## Artifact Index
- DISPATCH.md — Assignment instructions
- BRIEFING.md — Situational awareness and state
- progress.md — Liveness heartbeat and milestone checklist
- changes.md — Full change breakdown
- handoff.md — Complete 5-component handoff report

## Change Tracker
- **Files modified**: `nhap_diem.php`, `includes/xlsx_helper.php`, `assets/css/style.css`, `tests/verify_m4.php`
- **Build status**: 132/132 tests PASS (0 failures)
- **Pending issues**: None

## Quality Status
- **Build/test result**: PASS (verify_m4: 39/39, verify_m1: 12/12, verify_m2: 16/16, verify_m3: 28/28, challenge_m2_rbac: 37/37)
- **Lint status**: Clean (PHP lint passed with 0 syntax errors)
- **Tests added/modified**: `tests/verify_m4.php` (39 new assertions)

## Loaded Skills
- None
