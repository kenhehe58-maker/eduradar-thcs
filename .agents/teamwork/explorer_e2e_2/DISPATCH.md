# DISPATCH — explorer_e2e_2

## 2026-10-17T18:41:15Z
- **Role**: E2E Feature Coverage & Scenario Explorer
- **Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2`
- **Context & Mission**:
  Map all requirements R1–R6 from `ORIGINAL_REQUEST.md` and Features #1–#18 from `PROJECT.md` into comprehensive, concrete test cases for `tests/run_tests.php`:
  1. Multi-Account Authentication & Session Rotation (R2, F5, F6):
     - Valid login: `admin`, `gv_toan`, `gv_van` (password `123456`).
     - Invalid login rejection: wrong passwords, SQLi username, empty fields.
     - Session fixation rotation (`session_regenerate_id(true)`).
  2. Strict Server-Side RBAC & Route Guards (R2, F6):
     - Teacher `gv_toan` accessing `admin.php` -> HTTP 403 / redirect.
     - Unauthenticated accessing `admin.php`, `nhap_diem.php`, `index.php` -> redirect `login.php`.
     - Anti-BOLA/IDOR: `gv_toan` saving/viewing grades for unassigned class or subject -> HTTP 403.
  3. Admin Class Matrix & Deduplicated Imports (R2, F7, F8, F9, F10):
     - Secondary School Matrix: Grades 6–9, Sections A–C, Classes 1–9.
     - Batch checkbox assignment creates `teacher_classes` rows; duplicate submission does NOT create duplicates.
     - Student roster `.xlsx` import: first import creates students, second identical import results in 0 duplicates (`UNIQUE(class_id, full_name, birthday)`).
  4. VnEdu Spreadsheet Gradebook & Bulk Import/Export (R3, F12, F13, F14):
     - Inline score saving updates `scores` with correct weighted `avg_score`.
     - Bulk `.xlsx` gradebook import parses real ZIP/OpenXML and upserts `scores` without duplication.
     - `.xlsx` export generates valid OpenXML file openable by `ZipArchive`.
  5. Early-Warning Radar & Zero Zalo (R4, F15, F16, F17):
     - Accurate classification into 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định.
     - Zero Zalo: 0 occurrences of 'zalo', 0 buttons, 0 modals, 0 parent message scripts.
  6. Audit Logging (R5, F4):
     - Every state-changing action logs a row in MySQL `audit_logs`.
- **Required Reading**:
  1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md`
  2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md`
- **Deliverables**:
  Write `analysis.md` and `handoff.md` in your working directory.
  Send a message to your parent when done.

## 2026-10-17T11:41:46Z
You are explorer_e2e_2. Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2.
Read D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md, PROJECT.md, and DISPATCH.md in your working directory.
Your task is to design the comprehensive 4-Tier test matrix for tests/run_tests.php covering all 18 features from PROJECT.md:
1. Multi-account authentication (admin, gv_toan, gv_van password 123456, wrong password rejection, session rotation).
2. Strict RBAC & anti-BOLA route guards (admin vs teacher, 403 on unassigned classes/subjects).
3. Secondary School Class Matrix & deduplicated batch assignment.
4. VnEdu student roster import with deduplication on (class_id, full_name, birthday).
5. VnEdu spreadsheet gradebook in nhap_diem.php: weighted average, inline saving, OpenXML bulk import/export.
6. Early-warning radar diagnostics in index.php (Red, Yellow, Green zones), 0 Zalo.
7. Audit logging verification in audit_logs for all tested actions.
Write analysis.md and handoff.md in your working directory, then send a message when done.
