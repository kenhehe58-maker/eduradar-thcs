# Handoff Report: Milestone 2 RBAC & BOLA / IDOR Adversarial Verification

**Agent**: `challenger_m2_2` (teamwork_preview_challenger)  
**Roles**: critic, specialist  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\challenger_m2_2`  
**Date**: 2026-10-17  
**Handoff Type**: Hard (Milestone 2 RBAC & BOLA / IDOR protection empirically challenged and verified)  
**Verdict**: **APPROVE**  

---

## 1. Observation

1. **Target Environment & Services**:
   - Web Server: Apache 2.4 on port 80 at `http://localhost/my-web-project/` (`D:\xam\htdocs\my-web-project`).
   - Database: MariaDB 10.4 on port 3306 (`127.0.0.1:3306`, database `edu_radar`, user `root`, no password).
   - PHP CLI: `D:\xam\php\php.exe` (PHP 8.0.30 with cURL, PDO MySQL, and OpenSSL).

2. **Source Code Implementation Inspected**:
   - `includes/auth.php`: Lines 71–96 define `require_admin()` checking `$_SESSION['role'] === 'admin'`, issuing `http_response_code(403)`, logging to `audit_logs`, and rendering `render_forbidden_page()`. Lines 99–171 define `verify_teacher_assignment()` and `require_teacher_assignment()`, cross-referencing `teacher_id` with `teacher_classes` and enforcing that `teacher_id === (int)$_SESSION['user_id']`.
   - `admin.php`: Line 5 invokes `require_admin()` at the top of the file before any GET or POST handlers execute.
   - `nhap_diem.php`: Lines 16–35 block non-admin `teacher_id` tampering (`if (isset($_GET['teacher_id']) && (int)$_GET['teacher_id'] !== $realUserId)` with HTTP 403 and audit log). Lines 81, 100, and 158 enforce `require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject)` on both GET view and POST handlers (`save_scores`, `import_file`). Lines 107–110 enforce student ownership validation (`SELECT 1 FROM students WHERE id = ? AND class_id = ?`), skipping non-matching student IDs.

3. **Empirical Challenge Test Execution (`tests/challenge_m2_rbac.php`)**:
   Command executed:
   ```powershell
   D:\xam\php\php.exe tests/challenge_m2_rbac.php
   ```
   Verbatim output:
   ```
   ==========================================================================
     EduRadar Milestone 2: Adversarial Empirical Challenge Suite
     Target: Apache (localhost/my-web-project/) & MariaDB (edu_radar)
   ==========================================================================

   --- SECTION 1: Adversarial Attacks Against admin.php (RBAC & Privilege Escalation) ---
   [ADMIN_ATTACK] 1.1 Unauthenticated GET admin.php rejected (HTTP 403).... [PASS]
          Observed: HTTP 403 returned, no admin UI leaked
   [ADMIN_ATTACK] 1.2 Unauthenticated GET admin.php with impersonate query (HTTP 403) [PASS]
          Observed: HTTP 403 returned, impersonation rejected
   [ADMIN_ATTACK] 1.3 Unauthenticated GET admin.php with delete_assign query (HTTP 403, 0 DB deletions) [PASS]
          Observed: HTTP 403 returned, assignment ID 1 remained intact in MySQL
   [ADMIN_ATTACK] 1.4 Unauthenticated forged POST admin.php batch_assign (HTTP 403, 0 DB insertions) [PASS]
          Observed: HTTP 403 returned, 0 rows added to teacher_classes
   [ADMIN_ATTACK] 1.5 Unauthenticated forged POST admin.php import_students (HTTP 403, 0 DB insertions) [PASS]
          Observed: HTTP 403 returned, 0 students created
   [ADMIN_ATTACK] 1.6 Teacher (gv_toan) GET admin.php rejected (HTTP 403).. [PASS]
          Observed: HTTP 403 returned, teacher barred and logged in audit_logs
   [ADMIN_ATTACK] 1.7 Teacher (gv_toan) GET admin.php delete_assign=3 (HTTP 403, 0 deletions) [PASS]
          Observed: HTTP 403 returned, teacher_classes ID 3 preserved intact
   [ADMIN_ATTACK] 1.8 Teacher (gv_toan) GET admin.php impersonate=1 (HTTP 403, no admin escalation) [PASS]
          Observed: HTTP 403 returned, privilege escalation thwarted
   [ADMIN_ATTACK] 1.9 Teacher (gv_toan) forged POST batch_assign to 8A1 & 9C3 (HTTP 403, 0 DB insertions) [PASS]
          Observed: HTTP 403 returned, teacher_classes modified = 0
   [ADMIN_ATTACK] 1.10 Teacher (gv_toan) forged POST import_students (HTTP 403, 0 DB insertions) [PASS]
          Observed: HTTP 403 returned, students table untouched
   [ADMIN_ATTACK] 1.11 Header spoofing bypass attempt (X-Forwarded-For, X-Original-URL) (HTTP 403) [PASS]
          Observed: HTTP 403 returned, header spoofing ignored
   [ADMIN_ATTACK] 1.12 Negative Control: Authentic Admin GET admin.php succeeds (HTTP 200) [PASS]
          Observed: HTTP 200 returned, admin portal accessible to authentic admin

   --- SECTION 2: Adversarial Attacks Against nhap_diem.php (BOLA & IDOR) ---
   [BOLA_NHAP_DIEM] 2.1 Unauthenticated GET nhap_diem.php redirects to login.php (HTTP 302) [PASS]
          Observed: HTTP 302 to login.php returned
   [BOLA_NHAP_DIEM] 2.2 Unauthenticated POST nhap_diem.php save_scores rejected (HTTP 302, 0 score changes) [PASS]
          Observed: HTTP 302 returned, student 1 score unchanged
   [BOLA_NHAP_DIEM] 2.3 Teacher (gv_toan) forged GET for 7A1 Ngữ Văn (unassigned subject) (HTTP 403) [PASS]
          Observed: HTTP 403 returned, logged to audit_logs
   [BOLA_NHAP_DIEM] 2.4 Teacher (gv_toan) forged GET for 8A1 Toán (unassigned class) (HTTP 403) [PASS]
          Observed: HTTP 403 returned, access to 8A1 blocked
   [BOLA_NHAP_DIEM] 2.5 Teacher (gv_toan) forged GET for 9C3 Tin Học (unassigned class & subject) (HTTP 403) [PASS]
          Observed: HTTP 403 returned, access to 9C3 Tin Học blocked
   [BOLA_NHAP_DIEM] 2.6 Teacher (gv_toan) forged GET for 6A1 Ngữ Văn (peer teacher assignment) (HTTP 403) [PASS]
          Observed: HTTP 403 returned, peer gradebook access blocked
   [BOLA_NHAP_DIEM] 2.7 Teacher (gv_toan) forged POST save_scores for 7A1 Ngữ Văn (HTTP 403, 0 DB changes) [PASS]
          Observed: HTTP 403 returned, zero score modifications in MySQL
   [BOLA_NHAP_DIEM] 2.8 Teacher (gv_toan) forged POST save_scores for 8A1 Toán (HTTP 403, 0 DB changes) [PASS]
          Observed: HTTP 403 returned, 8A1 grade submission rejected
   [BOLA_NHAP_DIEM] 2.9 Teacher (gv_toan) forged POST save_scores for 9C3 Tin Học (HTTP 403, 0 DB changes) [PASS]
          Observed: HTTP 403 returned, 9C3 Tin Học grade submission rejected
   [BOLA_NHAP_DIEM] 2.10 Teacher (gv_toan) forged POST save_scores for 6A1 Ngữ Văn (HTTP 403, 0 DB changes) [PASS]
          Observed: HTTP 403 returned, student 2 Ngữ Văn score unchanged
   [IDOR_NHAP_DIEM] 2.11 Teacher (gv_toan) IDOR tampering ?teacher_id=3 (gv_van) (HTTP 403) [PASS]
          Observed: HTTP 403 returned, IDOR tampering logged to audit_logs
   [IDOR_NHAP_DIEM] 2.12 Teacher (gv_toan) IDOR tampering ?teacher_id=1 (admin) (HTTP 403) [PASS]
          Observed: HTTP 403 returned, admin IDOR tampering blocked
   [IDOR_NHAP_DIEM] 2.13 Teacher (gv_toan) IDOR tampering with arbitrary IDs (999, 0, -1, abc, SQLi) (All HTTP 403) [PASS]
          Observed: All 6 tampered teacher_id values returned HTTP 403
   [IDOR_NHAP_DIEM] 2.14 Teacher (gv_toan) IDOR via POST ?teacher_id=3 (HTTP 403, 0 DB changes) [PASS]
          Observed: HTTP 403 returned, student 3 score untouched
   [BOLA_NHAP_DIEM] 2.15 Cross-Class Student ID Injection in legitimate class POST (0 unauthorized DB updates) [PASS]
          Observed: Cross-class student 99999 rejected by ownership check, 0 records added
   [BOLA_NHAP_DIEM] 2.16 Peer Teacher (gv_van) cross-testing against gv_toan classes (All HTTP 403) [PASS]
          Observed: All 3 cross-teacher attacks from gv_van returned HTTP 403
   [INPUT_BOUNDARY] 2.17 Unassigned & Malformed Query String Injection in nhap_diem.php (All HTTP 403) [PASS]
          Observed: All 7 unassigned/boundary queries returned HTTP 403
   [INPUT_BOUNDARY] 2.18 Default Fallback on GET without parameters safely opens assigned class (HTTP 200) [PASS]
          Observed: HTTP 200 returned, teacher landing on assigned class is safe and compliant

   --- SECTION 3: Legitimate Access Controls (Negative Controls) ---
   [CONTROL] 3.1 Authorized Teacher (gv_toan) accesses 6A1 Toán (HTTP 200) [PASS]
          Observed: HTTP 200 returned, authorized gradebook loaded
   [CONTROL] 3.2 Authorized Teacher (gv_toan) accesses 7A1 Toán (HTTP 200) [PASS]
          Observed: HTTP 200 returned, authorized gradebook loaded
   [CONTROL] 3.3 Authorized Teacher (gv_van) accesses 6A1 Ngữ Văn (HTTP 200) [PASS]
          Observed: HTTP 200 returned, authorized gradebook loaded
   [CONTROL] 3.4 Authentic Admin direct inspection of 6A1 Ngữ Văn (HTTP 200) [PASS]
          Observed: HTTP 200 returned, admin has legitimate inspection rights
   [CONTROL] 3.5 Authentic Admin inspection with teacher_id parameter (HTTP 200) [PASS]
          Observed: HTTP 200 returned, admin inspection banner active

   --- SECTION 4: Database Integrity & Audit Log Forensics ---
   [DB_INTEGRITY] 4.1 Final database row counts unchanged across all protected tables [PASS]
          Observed: teacher_classes (3), classes (36), students (5), scores (7) completely unchanged
   [AUDIT_LOGS] 4.2 Audit log recorded every blocked unauthorized access attempt [PASS]
          Observed: Total new audit logs: 69, Total blocked attempts logged: 443

   ==========================================================================
     Total Adversarial Challenges: 37 | Passed: 37 | Failed: 0
   ==========================================================================

   VERDICT: APPROVE
   All adversarial challenges passed with HTTP 403 / 302 and ZERO database mutations.
   ```

4. **Regression Suites Verification**:
   - `D:\xam\php\php.exe tests/verify_m2.php`: Passed 16/16 tests with 0 failures.
   - `D:\xam\php\php.exe tests/verify_m1.php`: Passed 12/12 tests with 0 failures.
   - Syntax validation (`php -l`): 0 syntax errors across `login.php`, `logout.php`, `admin.php`, `nhap_diem.php`, `includes/auth.php`, `tests/challenge_m2_rbac.php`.

---

## 2. Logic Chain

1. **RBAC Route Guard Integrity on `admin.php`**:
   - Observation 2 demonstrates that `admin.php` invokes `require_admin()` at line 5.
   - In Observation 3 (Tests 1.1–1.11), every forged unauthenticated and teacher request (`GET`, `POST action=batch_assign`, `POST action=import_students`, `GET delete_assign`, `GET impersonate`, and header spoofing) resulted in `HTTP 403 Forbidden`.
   - Direct database count assertions before and after the test run verified that row counts in `teacher_classes`, `classes`, and `students` remained strictly unchanged (Test 4.1).
   - Legitimate administrative requests by user `admin` succeeded with `HTTP 200 OK` (Test 1.12).
   - Conclusion: Administrative controls are completely inaccessible to unauthorized parties.

2. **BOLA Protection in `nhap_diem.php`**:
   - Observation 2 shows that `nhap_diem.php` validates teacher assignment via `require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject)` before rendering or saving data.
   - In Observation 3 (Tests 2.3–2.10, 2.16, 2.17), `gv_toan` attempted to view and modify grades for `7A1 Ngữ Văn`, `8A1 Toán`, `9C3 Tin Học`, and `6A1 Ngữ Văn`. All requests were halted with `HTTP 403 Forbidden`.
   - Database checks against `scores` confirmed that no grade rows were inserted or modified for unassigned classes or subjects (Tests 2.7, 2.8, 2.9, 2.10).
   - Conclusion: Broken Object Level Authorization (BOLA) is completely prevented.

3. **IDOR Mitigation via Parameter Neutralization**:
   - Observation 2 shows `nhap_diem.php` enforces that only users with `role === 'admin'` may query with `?teacher_id=`. For teachers, any value differing from `$_SESSION['user_id']` immediately halts execution with `HTTP 403`.
   - In Observation 3 (Tests 2.11–2.14), forged GET and POST requests by `gv_toan` with `teacher_id=3` (`gv_van`), `teacher_id=1` (`admin`), `teacher_id=999`, negative values, and SQLi strings were all halted with `HTTP 403 Forbidden`.
   - Legitimate administrative inspection (`admin` with `?teacher_id=2`) succeeded with `HTTP 200 OK` (Test 3.5).
   - Conclusion: Insecure Direct Object References via `teacher_id` tampering are entirely neutralized.

4. **Forensic Accountability via `audit_logs`**:
   - Observation 2 shows that both `require_admin()` and `require_teacher_assignment()` record unauthorized attempts with client IP, username, requested URI, and diagnostic detail.
   - Observation 3 (Test 4.2) verified that every blocked attack inserted a record into `audit_logs` with action `'Truy cập trái phép bị chặn'`.
   - Conclusion: The system provides complete forensic traceability of unauthorized access attempts.

---

## 3. Caveats

- **Scope Boundary**: Milestone 2 evaluates authentication, authorization, RBAC route guards, and BOLA/IDOR protection. The VnEdu 6-column spreadsheet calculation engine (`tx1..tx4, gk, ck, avg_score`), keyboard navigation, and OpenXML Excel file handling are scheduled for Milestone 4 per `PROJECT.md`.
- **Database Schema**: MySQL database `edu_radar` schema and seed data established in Milestone 1 were preserved without alteration.
- **Client IP in CLI**: In CLI testing via cURL against local Apache, `REMOTE_ADDR` is recorded as `::1` or `127.0.0.1`, correctly captured in `audit_logs.ip_address`.

---

## 4. Conclusion

**Verdict: APPROVE**

Milestone 2 RBAC & BOLA / IDOR protection satisfies all security requirements:
1. `admin.php` strictly rejects unauthenticated and teacher requests with `HTTP 403 Forbidden` and prevents any database changes to `teacher_classes`, `classes`, or `students`.
2. `nhap_diem.php` strictly rejects forged GET and POST requests for unassigned classes (`7A1 Ngữ Văn`, `8A1 Toán`, `9C3 Tin Học`, `6A1 Ngữ Văn`) with `HTTP 403 Forbidden` and zero database changes in `scores`.
3. Parameter tampering via `?teacher_id=` is completely defeated for non-admin accounts.
4. Cross-class student ID injection is neutralized by student ownership validation.
5. All blocked unauthorized attempts are logged to `audit_logs`.
6. All 37 empirical challenge tests in `tests/challenge_m2_rbac.php`, 16 tests in `tests/verify_m2.php`, and 12 tests in `tests/verify_m1.php` pass with 0 failures.

---

## 5. Verification Method

To independently verify these findings on the live system:

1. **Execute Milestone 2 Adversarial Empirical Challenge Suite (37/37 PASS)**:
   ```powershell
   D:\xam\php\php.exe "D:\New folder\my-web-project\tests\challenge_m2_rbac.php"
   ```
   *Expected Output*: `Total Adversarial Challenges: 37 | Passed: 37 | Failed: 0` and `VERDICT: APPROVE` with exit code `0`.

2. **Execute Milestone 2 Standard Verification Suite (16/16 PASS)**:
   ```powershell
   D:\xam\php\php.exe "D:\New folder\my-web-project\tests\verify_m2.php"
   ```
   *Expected Output*: `Total Tests: 16 | Passed: 16 | Failed: 0` with exit code `0`.

3. **Execute Milestone 1 Regression Suite (12/12 PASS)**:
   ```powershell
   D:\xam\php\php.exe "D:\New folder\my-web-project\tests\verify_m1.php"
   ```
   *Expected Output*: `Total Tests: 12 | Passed: 12 | Failed: 0` with exit code `0`.

4. **Verify PHP Syntax Cleanliness**:
   ```powershell
   D:\xam\php\php.exe -l "D:\New folder\my-web-project\login.php"
   D:\xam\php\php.exe -l "D:\New folder\my-web-project\logout.php"
   D:\xam\php\php.exe -l "D:\New folder\my-web-project\admin.php"
   D:\xam\php\php.exe -l "D:\New folder\my-web-project\nhap_diem.php"
   D:\xam\php\php.exe -l "D:\New folder\my-web-project\includes\auth.php"
   D:\xam\php\php.exe -l "D:\New folder\my-web-project\tests\challenge_m2_rbac.php"
   ```
   *Expected Output*: `No syntax errors detected` for all files.
