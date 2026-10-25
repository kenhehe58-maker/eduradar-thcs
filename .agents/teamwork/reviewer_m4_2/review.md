# Quality & Adversarial Review Report: Milestone 4 (VnEdu Gradebook, Calculation Engine & RBAC)

**Reviewer**: `reviewer_m4_2` (Reviewer & Adversarial Critic)  
**Target Milestone**: Milestone 4 (Features #12, #13, #14: VnEdu Spreadsheet Gradebook, Thông tư 22 Calculation Engine, Persistence & RBAC)  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\reviewer_m4_2`  
**Date**: 2026-10-17  

---

## 1. Review Summary

**Verdict**: **APPROVE**  
**Overall Risk Assessment**: **LOW**  
**Integrity Audit**: **CLEAN (0 Integrity Violations Detected)**

The implementation delivered by `worker_m4` across `nhap_diem.php`, `includes/xlsx_helper.php`, and `tests/verify_m4.php` meets and exceeds all requirements specified in `ORIGINAL_REQUEST.md` (R1, R3, R5) and `PROJECT.md` (M4 Features #12, #13, #14):
1. **Thông tư 22 Calculation Engine**: Server-side PHP and client-side JavaScript calculations are perfectly aligned, mathematically identical, and accurately implement both full-semester evaluation (`(S_tx + 2*gk + 3*ck)/(N_tx + 5)`) and dynamic weighted partial evaluation with 1-decimal rounding.
2. **Database Transaction Integrity**: `save_scores` and `import_file` / `import_gradebook` wrap multi-row mutations in explicit `$pdo->beginTransaction()`, `$pdo->commit()`, and `$pdo->rollBack()`.
3. **Student Whitelist & Anti-BOLA Defense**: Strict class roster pre-check (`array_flip`) eliminates cross-class student ID injection (such as ID 99999). Scores are upserted atomically using `ON DUPLICATE KEY UPDATE` anchored to `UNIQUE(student_id, subject)`.
4. **Strict RBAC & BOLA / IDOR Protection**: `require_teacher_assignment()` is enforced on every entry point (GET, POST save_scores, POST import_file, and GET export). Unauthorized requests, foreign `teacher_id` tampering, or attempts to access unassigned classes/subjects are terminated immediately with HTTP 403 and logged to `audit_logs`.
5. **Comprehensive Audit Logging**: All operational events (`Lưu sổ điểm`, `Import file điểm`, `Xuất file điểm`, `Admin kiểm tra sổ điểm`, `Truy cập trái phép bị chặn`) are immutably written to `audit_logs`.
6. **Empirical Verification**: All 5 test suites (`verify_m4.php`, `verify_m2.php`, `challenge_m2_rbac.php`, `verify_m1.php`, `verify_m3.php`) executed against live Apache (`http://localhost/my-web-project/`) and MariaDB (`edu_radar`) with **132/132 PASS (0 failures)**.

---

## 2. Integrity Verification (Anti-Cheat & Anti-Facade Audit)

| Integrity Dimension | Inspection Method | Result | Notes |
|---|---|---|---|
| **Hardcoded Test Results** | Inspected `nhap_diem.php` calculation logic lines 253–265 and 402–414 | **CLEAN** | Dynamic arithmetic calculations using native sum and count arrays; no hardcoded student-specific return values or mock shortcuts. |
| **Facade Implementations** | Traced DB execution paths in `nhap_diem.php` | **CLEAN** | Genuine MySQL PDO prepared statements executing `INSERT ... ON DUPLICATE KEY UPDATE` into live MariaDB. |
| **Bypassed Requirements** | Inspected keyboard navigation, validation, import, export, and RBAC | **CLEAN** | Full 2D grid key listeners, sticky 2-tier table, native OpenXML builder/parser without external dependencies, full transaction boundaries. |
| **Fabricated Logs / Artifacts** | Direct SQL inspection of `audit_logs` in live database | **CLEAN** | 100+ fresh audit records written with accurate IP addresses, real timestamps, and detailed Vietnamese action logs. |
| **Self-Certifying Work** | Independent execution of test suites via CLI command | **CLEAN** | Independent execution of all test suites via `D:\xam\php\php.exe` yielding 100% exit code 0. |

---

## 3. Detailed Findings

### Minor Finding 1 (Informational / Suggestion) — Explicit Hidden Fields for Class & Subject in Form
- **What**: The form `<form method="POST" id="gradebookForm">` in `nhap_diem.php` does not explicitly declare `<input type="hidden" name="class_id">` and `<input type="hidden" name="subject">` inside the form tag.
- **Where**: `nhap_diem.php:559`
- **Why**: The form inherits `$selectedClassId` and `$selectedSubject` because the form's `action` attribute is omitted, causing the browser to submit to the current URL query string (`nhap_diem.php?class_id=1&subject=Toán`). While standards-compliant and functioning flawlessly across all tests, explicitly including hidden inputs is an ed-tech best practice for forms submitted from dynamic hash/history states.
- **Impact**: Minor / Informational. No functional failure; all 39 M4 tests and 37 M2 adversarial challenges pass.
- **Suggestion**: In Milestone 5 UI polish, optionally add `<input type="hidden" name="class_id" value="<?= (int)$selectedClassId ?>">` and `<input type="hidden" name="subject" value="<?= htmlspecialchars($selectedSubject) ?>">`.

---

## 4. Verified Claims

1. **Thông tư 22 Weighted Average Formula**:
   - Claim: Calculates `(S_tx + 2*gk + 3*ck)/(N_tx + 5)` when GK & CK exist, or dynamic weighted average when partial.
   - Verification: Verified in `verify_m4.php` (tests 2.1–2.4) and manual calculation trace:
     - Full entry (TX=[8,7,6.5,6], GK=5, CK=5) -> Sum=27.5 + 10 + 15 = 52.5 / 9 = 5.833... -> rounded to 5.8 (PASS).
     - Partial entry (TX=[8,7], GK=7, CK=null) -> Sum=15 + 14 = 29 / 4 = 7.25 -> rounded to 7.3 (PASS).
     - Only TX (TX=[8,7]) -> Sum=15 / 2 = 7.5 (PASS).
     - Empty set -> returns `null` (displayed as `—`) (PASS).
   - Status: **VERIFIED PASS**.

2. **Database Transaction Persistence in `save_scores`**:
   - Claim: Wrapped in `beginTransaction()`, `commit()`, and `rollBack()`.
   - Verification: Inspected `nhap_diem.php:233-277`. Transaction started before student loop, committed after all upserts, and rolled back in `catch (Throwable $e)`. Verified in `verify_m4.php` test 4.1 & 4.2.
   - Status: **VERIFIED PASS**.

3. **Anti-BOLA Student Whitelist Verification**:
   - Claim: Student IDs in `$_POST['scores']` must belong to `$selectedClassId`; foreign IDs are ignored.
   - Verification: Inspected `nhap_diem.php:203-241` (`$validStudentMap = array_flip(...)`). Verified in `verify_m4.php` test 5.2 and `challenge_m2_rbac.php` test 2.15 where student `99999` was submitted and rejected with 0 records written to `scores`.
   - Status: **VERIFIED PASS**.

4. **Strict Server-Side RBAC & BOLA / IDOR Route Guard**:
   - Claim: `require_teacher_assignment()` protects all routes; unassigned classes/subjects, forged POSTs, and `teacher_id` tampering return HTTP 403.
   - Verification: Verified via `verify_m4.php` (tests 9.1–9.6), `verify_m2.php` (tests 3.1–4.5), and `challenge_m2_rbac.php` (tests 1.1–2.18).
   - Status: **VERIFIED PASS**.

5. **Audit Logging Forensics**:
   - Claim: Actions `Lưu sổ điểm`, `Import file điểm`, `Xuất file điểm`, `Admin kiểm tra sổ điểm`, and `Truy cập trái phép bị chặn` are logged with IP and details.
   - Verification: Verified via `verify_m4.php` (tests 4.3, 7.4, 8.5, 10.1, 10.2) and `challenge_m2_rbac.php` (tests 4.2). Direct MySQL query confirmed 1350+ security audit logs.
   - Status: **VERIFIED PASS**.

---

## 5. Adversarial Challenge & Stress-Test Report

### Overall Risk Assessment: LOW

### Stress-Test Scenarios & Results

1. **Boundary Condition: Zero Score (`0.0`) Submission**
   - *Attack / Stress*: Does a student score of `0.0` get filtered out by PHP `empty()` or JS falsy check?
   - *Result*: Inspected `nhap_diem.php:253`: Uses `fn($v) => $v !== null`. In PHP, `0.0 !== null` evaluates to `true`. In JS, `parseFloat("0") >= 0` evaluates to `true`.
   - *Verdict*: **ROBUST (PASS)**.

2. **Negative and Out-of-Bounds Score Injection (`-5.0`, `15.0`, `xyz`)**
   - *Attack / Stress*: Does malicious or accidental invalid input pollute `scores` table?
   - *Result*: `parseScore()` in `nhap_diem.php:208-216` checks `$f < 0.0 || $f > 10.0` and non-numeric strings, returning `null`. Client-side JS blocks form submission if `.is-invalid` exists.
   - *Verdict*: **ROBUST (PASS)**.

3. **Cross-Class Student ID Injection Attack**
   - *Attack / Stress*: Attacker authorized for 6A1 posts `scores[99999][tx1]=10` or a student ID belonging to 9A1.
   - *Result*: Pre-loaded `$validStudentMap` for 6A1 discards any ID not in 6A1 before executing SQL.
   - *Verdict*: **ROBUST (PASS)**.

4. **IDOR Parameter Tampering (`?teacher_id=3`, `?teacher_id=1`)**
   - *Attack / Stress*: Teacher `gv_toan` (ID 2) adds `?teacher_id=3` to view or post to `gv_van`'s gradebook.
   - *Result*: `nhap_diem.php:30-36` intercepts non-admin users where `(int)$_GET['teacher_id'] !== $realUserId`, halts with HTTP 403, and logs the IDOR tampering attempt.
   - *Verdict*: **ROBUST (PASS)**.

5. **SQL Injection via Query Parameters (`class_id="1 OR 1=1"`, `subject="' OR '1'='1"`)**
   - *Attack / Stress*: Injecting SQL syntax into `class_id` or `subject`.
   - *Result*: `class_id` is cast to integer `(int)`. `subject` is parameterized with PDO `?` placeholders. Both verified in `challenge_m2_rbac.php` test 2.17 returning HTTP 403.
   - *Verdict*: **ROBUST (PASS)**.

---

## 6. Coverage Gaps & Unverified Items

- **Coverage Gaps**: None. All functional, persistence, security, and export pathways in Milestone 4 are covered with live automated tests.
- **Unverified Items**: None. All assertions were independently tested on the live XAMPP environment.

---

## 7. Final Verdict

**APPROVE**: Milestone 4 is fully verified, robust against adversarial attacks, mathematically compliant with Thông tư 22, and exhibits zero integrity violations. Ready for Milestone 5 (Early-Warning Radar & UI Redesign).
