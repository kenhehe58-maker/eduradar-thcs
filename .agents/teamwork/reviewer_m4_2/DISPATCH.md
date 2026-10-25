# DISPATCH — reviewer_m4_2 (M4 Calc & RBAC Reviewer)

## 2026-10-17T17:05:00Z
- **Role**: teamwork_preview_reviewer (M4 Calc & RBAC Reviewer)
- **Working Directory**: D:\New folder\my-web-project\.agents\teamwork\reviewer_m4_2
- **Files to Read**:
  - D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
  - D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
  - D:\New folder\my-web-project\.agents\teamwork\worker_m4\handoff.md
  - D:\New folder\my-web-project\.agents\teamwork\worker_m4\changes.md
  - D:\New folder\my-web-project\nhap_diem.php
  - D:\New folder\my-web-project\includes\auth.php
  - D:\New folder\my-web-project\tests\verify_m4.php
- **Tasks**:
  1. Review server-side and client-side calculation logic for Thông tư 22:
     `W_total = count(tx)*1 + (gk !== null ? 2 : 0) + (ck !== null ? 3 : 0)`
     `S_total = sum(tx) + 2*(gk ?? 0) + 3*(ck ?? 0)`
     `avg_score = W_total > 0 ? round(S_total / W_total, 1) : null`
  2. Review database persistence: atomic database transaction (`beginTransaction`, `commit`, `rollBack`), student ID validation against current class whitelist (preventing student injection), atomic upsert via `ON DUPLICATE KEY UPDATE`.
  3. Review RBAC and BOLA / IDOR protection: `require_teacher_assignment($pdo, $class_id, $subject)` on both GET and POST requests. Admin full access.
  4. Review audit logging in `audit_logs` table for save and import operations.
  5. Execute test suites with `D:\xam\php\php.exe`: `tests/verify_m4.php`, `tests/verify_m2.php`, and `tests/challenge_m2_rbac.php`.
  6. Write `review.md` and `handoff.md` with explicit verdict `APPROVE` or `REQUEST_CHANGES`.

## 2026-10-17T10:16:47Z
Sender: 391812a0-32ec-4263-b097-6162669cc4f7
Content:
You are reviewer_m4_2. Working directory: D:\New folder\my-web-project\.agents\teamwork\reviewer_m4_2.
Read D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md, PROJECT.md, DISPATCH.md in your working directory, and worker_m4's handoff.md and changes.md in D:\New folder\my-web-project\.agents\teamwork\worker_m4.
Review Milestone 4 calculation logic, database persistence, and RBAC:
- Thông tư 22 weighted average formula on both server-side and client-side.
- Database transaction persistence (beginTransaction, commit, rollBack) in save_scores.
- Student whitelist verification (blocking cross-class student ID injection) and atomic upsert into scores.
- Strict RBAC and BOLA / IDOR protection via require_teacher_assignment() on GET and POST.
- Audit logging in audit_logs for save and export.
Run test suites via D:\xam\php\php.exe: tests/verify_m4.php, tests/verify_m2.php, and tests/challenge_m2_rbac.php.
State your verdict (APPROVE or REQUEST_CHANGES) in review.md and handoff.md, then send a message when done.
