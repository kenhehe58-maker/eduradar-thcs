# BRIEFING — 2026-10-17T10:05:00Z

## Mission
Investigate and design the score calculation engine (client-side JS and server-side PHP), database persistence, transaction safety, and strict preservation of RBAC & BOLA / IDOR protection in nhap_diem.php.

## 🔒 My Identity
- Archetype: explorer
- Roles: M4 Score Calculation & Persistence Explorer
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m4_2
- Original parent: 391812a0-32ec-4263-b097-6162669cc4f7
- Milestone: M4 (VnEdu Gradebook & Spreadsheet)

## 🔒 Key Constraints
- Read-only investigation — do NOT implement code in production files (leave implementation to implementer/orchestrator)
- Maintain strict RBAC & BOLA / IDOR protection in nhap_diem.php
- Ensure formula consistency between client-side JS and server-side PHP
- Ensure transaction safety (beginTransaction / commit / rollBack)
- Validate scores (0.0 to 10.0 or null) and student ownership per class

## Current Parent
- Conversation ID: 391812a0-32ec-4263-b097-6162669cc4f7
- Updated: not yet

## Investigation State
- **Explored paths**:
  - `ORIGINAL_REQUEST.md`, `PROJECT.md`, `DISPATCH.md`
  - `nhap_diem.php` (existing implementation)
  - `includes/auth.php`, `includes/db.php`, `database.sql`
  - `tests/verify_m1.php`, `tests/verify_m2.php`, `tests/verify_m3.php`, `tests/challenge_m2_rbac.php`
- **Key findings**:
  - Database schema in `database.sql` and live MySQL has columns: `id, student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at`.
  - Identified critical mismatch: `nhap_diem.php` currently references outdated `m1..t1_1` causing `PDOException: Unknown column 'm1' in 'field list'` on save.
  - Designed mathematical formula per Thông tư 22/2021/TT-BGDĐT: ĐĐGtx (weight 1), ĐĐGgk (weight 2), ĐĐGck (weight 3). Generalized dynamic formula: $W_{total} = N_{tx} + 2[gk] + 3[ck]$ and $S_{total} = S_{tx} + 2gk + 3ck$.
  - Designed client-side JS calculation engine: real-time row recalculation on `input`, decimal comma normalization (`8,5` -> `8.5`) on `change`, out-of-range visual warnings (`.is-invalid`), form submit guard.
  - Designed server-side PHP persistence: atomic upsert (`INSERT ... ON DUPLICATE KEY UPDATE`), pre-loading class student whitelist (`$validMap = array_flip($validIds)`) to prevent cross-class ID injection, wrapping in `db()->beginTransaction()` / `commit()` / `rollBack()`.
  - Preserved strict RBAC (`require_teacher_assignment`) and anti-IDOR checks (`$_GET['teacher_id']` and `$_POST['teacher_id']`).
  - Added backward compatibility mapping (`$row['tx1'] ?? $row['m1'] ?? null`) to protect legacy challenge tests from regression.
  - Specified audit logging: `Lưu sổ điểm` post-commit.
- **Unexplored areas**: None within M4-2 scope.

## Key Decisions Made
- Use atomic `INSERT ... ON DUPLICATE KEY UPDATE` prepared once outside student loop.
- Whitelist students per class in O(1) memory map before processing POST scores.
- Support both `tx1..tx4` and legacy fallback keys `m1..p15_2`.
- Rollback transaction if any score fails $0.0 \le \text{score} \le 10.0$ validation.

## Artifact Index
- `analysis.md` — Detailed analysis and complete code designs (PHP & JS)
- `handoff.md` — 5-component hard handoff report
- `progress.md` — Liveness heartbeat and milestone checklist
