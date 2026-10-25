# DISPATCH — 2026-10-17T09:55:00Z

## Identity
- Role: M4 Score Calculation & Persistence Explorer
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m4_2
- Parent: orchestrator_1_gen2 (391812a0-32ec-4263-b097-6162669cc4f7)

## Mission
Investigate and design the score calculation engine (client-side JS and server-side PHP), database persistence, transaction safety, and RBAC / BOLA defense in `nhap_diem.php`.

## Mandatory Reading (Authoritative Sources)
1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Read FIRST)
2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Milestone 4, Feature #13)
3. `D:\New folder\my-web-project\nhap_diem.php`
4. `D:\New folder\my-web-project\includes\auth.php`

## Technical Scope
1. **Calculation Formula**:
   - `ĐĐGtx` (thường xuyên, hệ số 1): `tx1, tx2, tx3, tx4` (up to 4 tests).
   - `ĐĐGgk` (giữa kỳ, hệ số 2): `gk`.
   - `ĐĐGck` (cuối kỳ, hệ số 3): `ck`.
   - Formula:
     - When `gk !== null && ck !== null`:
       `avg = (sum(tx) + 2*gk + 3*ck) / (count(tx) + 5)`
     - Dynamic weighted fallback for partial entries:
       `weight_sum = count(tx)*1 + (gk !== null ? 2 : 0) + (ck !== null ? 3 : 0)`
       `score_sum = sum(tx) + 2*(gk ?? 0) + 3*(ck ?? 0)`
       `avg = weight_sum > 0 ? (score_sum / weight_sum) : null`
     - Rounded to 1 decimal place: `round($avg, 1)`.
   - Implement synchronized calculation in both JavaScript (live update on input) and PHP (server-side verification on save).
2. **Server-Side Persistence & Integrity**:
   - Scores table: `scores` (`id, student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, created_at, updated_at`).
   - Transaction wrap: `db()->beginTransaction()`, execute upserts, `db()->commit()`.
   - Strict student verification: verify each student belongs to `$class_id` before saving scores to prevent cross-class ID injection.
3. **RBAC & BOLA / IDOR Verification**:
   - Maintain `require_teacher_assignment($teacherId, $classId, $subject)` on both GET and POST.
   - Non-admin teachers must NOT be able to inspect other teachers' classes or tamper with `?teacher_id`. Admin retains full inspection rights.
4. **Audit Logging**:
   - Record `Lưu sổ điểm` in `audit_logs` with class name, subject, and student count.

## Output
Write `analysis.md` and `handoff.md` in your working directory. Send a message to your parent when done.
