# Adversarial Challenge Report: Milestone 3 Admin Matrix & RBAC Security Boundaries

**Agent**: `challenger_m3_2` (teamwork_preview_challenger)  
**Roles**: critic, specialist  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\challenger_m3_2`  
**Date**: 2026-10-17  
**Verdict**: **APPROVE**  
**Overall Risk Assessment**: **LOW** (Zero exploitable privilege escalation, administrative bypass, matrix corruption, or duplicate assignment defects discovered)

---

## 1. Challenge Summary

We subjected Milestone 3's administrative control module (`admin.php`), class matrix, batch assignment engine, and server-side RBAC boundary controls to an exhaustive empirical challenge harness (`tests/challenge_m3_admin_rbac.php`), issuing 49 live HTTP requests against the active XAMPP Apache 2.4 server (`http://localhost/my-web-project/`) and directly verifying database state against live MariaDB 10.4 (`edu_radar`).

A total of **49 adversarial live HTTP tests** were executed across five threat vectors:
1. **Class Management Under Attack Conditions (Authorized Admin)**: 10 challenges.
2. **Batch Checkbox Assignment Under Attack Conditions (Authorized Admin)**: 12 challenges.
3. **RBAC & Route Protection — Unauthenticated Sessions**: 10 challenges.
4. **RBAC & Route Protection — Teacher Account Sessions (`gv_toan`)**: 13 challenges.
5. **Negative Controls & Database Forensic Integrity**: 4 challenges.

**Outcome**: **49 / 49 PASS (0 Failures, 100% Pass Rate)**.
- All invalid class formats (`15Z9`, `5A1`, `10A1`, `6E1`), XSS payloads (`6A<script>alert(1)</script>`), and SQL injection patterns (`6A1' OR 1=1 --`) were rejected with format error notices and resulted in 0 database mutations.
- Duplicate class creation attempts for seed classes (`6A1`) and dynamically created classes (`8B99_BURST`) were cleanly intercepted; 5 rapid burst creation requests resulted in exactly 1 class and 0 duplicates.
- Foreign key cascade deletions functioned cleanly: deleting a class automatically cascaded and purged associated records in `teacher_classes`, `students`, and `scores` without leaving orphaned records.
- Batch assignments survived mass selection across all 36 secondary school classes (Grades 6–9); subsequent rapid re-submission bursts produced 0 duplicate rows in `teacher_classes`, with all 36 classes reported as skipped.
- Batch assignment strictly blocked invalid teacher IDs (`0`, `-1`, non-existent `99999`, and non-teacher accounts like `admin` user ID `1`), empty subjects, whitespace subjects, and non-positive class IDs with 0 database mutations.
- Unauthenticated and teacher sessions (`gv_toan`) attempting forged POST or GET requests to `add_class`, `create_class`, `delete_class`, `create_teacher`, `batch_assign`, `delete_assign`, `impersonate`, and `import_students` were unconditionally blocked with **`HTTP 403 Forbidden`** (or `HTTP 302 Found` for unauthenticated sessions) and resulted in **ZERO database modifications** across `users`, `classes`, `teacher_classes`, `students`, and `scores`.
- Every unauthorized access attempt was logged to MariaDB `audit_logs` with action `'Truy cập trái phép bị chặn'`, recording the requesting username, role, HTTP method, and URI.
- Regression test suites passed with 100% compliance: `verify_m3.php` (28/28), `verify_m2.php` (16/16), and `verify_m1.php` (12/12).

---

## 2. Adversarial Challenges & Findings

### Vector 1: Class Management Under Attack Conditions (Authorized Admin)

#### [Medium] Challenge V1.01–V1.06: Malformed, Out-of-Range, XSS, and SQLi Class Names
- **Assumption Challenged**: Can an administrator inadvertently or maliciously insert classes with out-of-range grades (< 6 or > 9), invalid section letters ('E', 'Z'), stored XSS payloads (`<script>alert(1)</script>`), or SQL injection strings (`' OR 1=1 --`), corrupting the 6A1..9C9 class matrix or triggering XSS?
- **Attack Scenarios Tested**:
  - `POST action=add_class` with `name=15Z9`, `grade=15`, `section=Z`.
  - `POST action=add_class` with `name=5A1`, `grade=5`, `section=A`.
  - `POST action=add_class` with `name=10A1`, `grade=10`, `section=A`.
  - `POST action=add_class` with `name=6E1`, `grade=6`, `section=E`.
  - `POST action=add_class` with `name=6A<script>alert(1)</script>`, `grade=6`, `section=A`.
  - `POST action=add_class` with `name=6A1' OR 1=1 --`, `grade=6`, `section=A`.
- **Blast Radius**: If passed, out-of-range grades break the 4-column grade grouping (`$groupedClasses[6..9]`); XSS payloads could execute in admin and teacher session views; SQLi could damage MariaDB tables.
- **Empirical Observation**: All 6 malicious payloads were rejected before execution. In `admin.php` lines 108–114:
  `$grade < 6 || $grade > 9` guards grade boundaries;
  `!in_array($section, ['A', 'B', 'C', 'D'])` guards section names;
  `!preg_match('/^[6-9][A-Z0-9_]{1,20}$/i', $className)` strictly restricts class identifiers to standard alphanumeric patterns starting with grades 6–9.
  MariaDB queries confirmed 0 rows added to `classes`.
- **Verdict**: **PASS (ROBUST)**.

#### [Low] Challenge V1.07–V1.08: Duplicate Class Creations & Rapid Creation Bursts
- **Assumption Challenged**: Does rapid re-submission of class creation bypass validation and trigger duplicate rows in `classes` or cause an unhandled fatal PDO exception?
- **Attack Scenarios Tested**:
  - Creating duplicate of existing seed class `6A1`.
  - Rapid burst: 5 successive identical POST requests to create `8B99_BURST` (grade 8, section B).
- **Blast Radius**: Duplicate class names violate the single source of truth for rosters and gradebooks; unhandled exceptions produce HTTP 500 errors.
- **Empirical Observation**: Both pre-query duplicate checking (`SELECT id FROM classes WHERE name = ?`) and the database table constraint `UNIQUE KEY unique_class_name (name)` successfully prevented duplicate entries. The 5 burst requests produced exactly 1 class record and 0 duplicates.
- **Verdict**: **PASS (ROBUST)**.

#### [Medium] Challenge V1.09–V1.10: Rapid Deletion Bursts & Foreign Key Cascade Integrity
- **Assumption Challenged**: Does deleting a class leave orphaned assignments in `teacher_classes`, orphaned students in `students`, or orphaned grades in `scores`? Does deleting a non-existent class cause a server crash?
- **Attack Scenarios Tested**:
  - Deleting `8B99_BURST` twice in immediate succession.
  - Creating class `7C99_CASCADE`, assigning `gv_toan` to it in `teacher_classes`, creating a student in `students`, adding scores in `scores`, and executing `POST action=delete_class`.
- **Blast Radius**: Orphaned child records corrupt audit reports, grade averages, and referential integrity; repeated deletion errors cause HTTP 500 crashes.
- **Empirical Observation**: The second deletion was handled gracefully, returning the user notification "Lớp học cần xóa không tồn tại!" with HTTP 200. On `7C99_CASCADE`, foreign key constraints with `ON DELETE CASCADE` (`fk_tc_class`, `fk_students_class`, `fk_scores_student`) cleanly purged all associated child records across `teacher_classes`, `students`, and `scores`. Forensic audit confirmed 0 orphaned rows.
- **Verdict**: **PASS (ROBUST)**.

---

### Vector 2: Batch Assignment Under Attack Conditions (Authorized Admin)

#### [High] Challenge V2.01–V2.03: Mass Selection of All 36 Classes & Rapid Re-Submission Bursts
- **Assumption Challenged**: Does batch-assigning all 36 classes (Grades 6–9, Sections A–C, Numbers 1–3) overload the server or transaction? Does re-submitting the same mass selection create duplicate rows in `teacher_classes`?
- **Attack Scenarios Tested**:
  - `POST action=batch_assign` selecting all 36 class IDs (1..36) for `gv_toan` in subject `Toán`.
  - Re-submitting the exact same 36-class payload immediately (burst 1).
  - Re-submitting the exact same 36-class payload again (burst 2).
- **Blast Radius**: Duplicate teaching assignments cause ambiguous gradebook ownership and UI rendering anomalies.
- **Empirical Observation**:
  - First mass assignment successfully assigned all 36 classes in a single atomic transaction (`$pdo->beginTransaction()` ... `$pdo->commit()`).
  - Both re-submission bursts produced exactly 0 new records in `teacher_classes`. All 36 classes were recognized as existing and skipped, with UI feedback explicitly reporting: `(Đã bỏ qua 36 lớp đã được phân công từ trước)`.
  - Table row count remained invariant across all repetitions.
- **Verdict**: **PASS (ROBUST)**.

#### [Medium] Challenge V2.04–V2.07: Invalid Teacher IDs & Privilege Boundary Violation
- **Assumption Challenged**: Can an administrator assign teaching duties to invalid teacher IDs (`0`, `-1`, non-existent `99999`) or to non-teacher accounts (such as `admin` user ID `1`)?
- **Attack Scenarios Tested**:
  - `POST action=batch_assign` with `teacher_id = 0`.
  - `POST action=batch_assign` with `teacher_id = -1`.
  - `POST action=batch_assign` with `teacher_id = 99999`.
  - `POST action=batch_assign` with `teacher_id = 1` (`admin`).
- **Blast Radius**: Assigning teaching roles to administrators or invalid IDs corrupts role separation and causes foreign key failures.
- **Empirical Observation**: In `admin.php` lines 214–219:
  `$tStmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ? AND role = 'teacher'");`
  Only users having `role === 'teacher'` are accepted. Attempts using ID `0`, `-1`, `99999`, or admin ID `1` were rejected with error: `"Giáo viên được chọn không hợp lệ trong hệ thống!"`. Zero records were created in `teacher_classes`.
- **Verdict**: **PASS (ROBUST)**.

#### [Low] Challenge V2.08–V2.11: Malformed Subjects & Class Selection Arrays
- **Assumption Challenged**: Can empty strings (`""`), whitespace (`"   "`), empty class arrays (`class_ids = []`), or non-positive integers (`[0, -5]`) be submitted to `batch_assign`?
- **Attack Scenarios Tested**:
  - `POST action=batch_assign` with `subject = ""` and `subject = "   "`.
  - `POST action=batch_assign` with empty `class_ids = []`.
  - `POST action=batch_assign` with `class_ids = [0, -5]`.
- **Blast Radius**: Empty subjects create empty gradebook views; invalid IDs trigger database errors.
- **Empirical Observation**: Empty and whitespace subjects are trimmed and rejected with error `"Vui lòng chọn giáo viên, môn học và tích chọn ít nhất một lớp học!"`. Class IDs `<= 0` are safely ignored by `if ($cid <= 0) continue;`. Zero mutations occurred in MariaDB.
- **Verdict**: **PASS (ROBUST)**.

---

### Vector 3: RBAC & Route Protection — Unauthenticated Sessions

- **Assumption Challenged**: Can an unauthenticated anonymous attacker (no session cookie) invoke any administrative action via direct GET or forged POST requests?
- **Attack Scenarios Tested**:
  - `GET /admin.php`
  - `POST /admin.php action=add_class` (`name=6HACK_UNAUTH`, `grade=6`, `section=A`)
  - `POST /admin.php action=delete_class` (`class_id=1`)
  - `GET /admin.php?delete_class=1`
  - `POST /admin.php action=create_teacher` (`username=gv_anon_hacker`, `password=123456`)
  - `POST /admin.php action=batch_assign` (`teacher_id=2`, `subject=Toán`, `class_ids=[10, 11, 12]`)
  - `POST /admin.php action=delete_assign` (`id=1`)
  - `GET /admin.php?delete_assign=1`
  - `GET /admin.php?impersonate=2&cid=1&sub=Toán`
  - `POST /admin.php action=import_students` (`target_class_id=1`)
- **Blast Radius**: Unauthenticated access to admin actions represents complete system compromise.
- **Empirical Observation**: All 10 unauthenticated attack requests were immediately rejected with **`HTTP 403 Forbidden`** (via `require_admin()` in `admin.php` line 20).
  - Zero administrative UI elements or statistics were returned.
  - Zero records were modified, added, or deleted across `users`, `classes`, `teacher_classes`, `students`, and `scores`.
  - Each unauthenticated attempt was recorded in `audit_logs` with action `'Truy cập trái phép bị chặn'`.
- **Verdict**: **PASS (ROBUST)**.

---

### Vector 4: RBAC & Route Protection — Teacher Account Sessions (`gv_toan`)

- **Assumption Challenged**: Can an authenticated teacher (`gv_toan`, role=`teacher`) bypass authorization to view `admin.php`, create/delete classes, create teacher accounts, self-assign classes, delete other teachers' assignments, impersonate admin or peers, or import students?
- **Attack Scenarios Tested**:
  - `GET /admin.php`
  - `POST /admin.php action=add_class` (`name=7HACK_TEACHER`, `grade=7`, `section=A`)
  - `POST /admin.php action=create_class` (`name=7HACK_TEACHER_2`, `grade=7`, `section=B`)
  - `POST /admin.php action=delete_class` (`class_id=1`)
  - `GET /admin.php?delete_class=1`
  - `POST /admin.php action=create_teacher` (`username=teacher_created_by_teacher`)
  - `POST /admin.php action=batch_assign` (attempting self-assignment to unassigned classes)
  - `POST /admin.php action=delete_assign` (`id=3`, `gv_van`'s assignment)
  - `GET /admin.php?delete_assign=3`
  - `GET /admin.php?impersonate=1` (attempting admin escalation)
  - `GET /admin.php?impersonate=3&cid=1&sub=Ngữ+Văn` (attempting peer escalation)
  - `POST /admin.php action=import_students` (`target_class_id=2`)
  - Header spoofing bypass: `X-Forwarded-For: 127.0.0.1`, `X-Real-IP: 127.0.0.1`, `Client-IP: 127.0.0.1`, `X-Original-URL: /index.php`, `X-Rewrite-URL: /index.php`.
- **Blast Radius**: Privilege escalation allows teachers to alter institutional assignments, delete classes, or modify unauthorized student rosters.
- **Empirical Observation**: Every single attack request from `gv_toan` was halted with **`HTTP 403 Forbidden`**.
  - Standardized EduRadar Stitch UI 403 Forbidden page was returned in every instance.
  - Zero administrative data, statistics, or class matrices were exposed.
  - Zero database mutations occurred across all protected tables.
  - Audit logs recorded each violation with user ID 2, username `gv_toan`, and role `teacher`.
  - Header spoofing was completely ineffective because role checking inspects trusted PHP session state (`$_SESSION['role'] === 'admin'`), not request headers.
- **Verdict**: **PASS (ROBUST)**.

---

### Vector 5: Negative Controls & Forensic Audit Integrity

- **Assumption Challenged**: Does legitimate administrative oversight remain functional? Is forensic database integrity intact after 49 adversarial attacks?
- **Empirical Observation**:
  - Authentic Admin login (`admin` / `123456`) and navigation to `GET /admin.php` returned **`HTTP 200 OK`** with the full Admin Portal UI, four statistics cards, and complete 36-class matrix.
  - Authentic Admin direct gradebook inspection (`nhap_diem.php?class_id=1&subject=Toán`) returned **`HTTP 200 OK`**.
  - MariaDB audit log forensics verified that unauthorized access attempts were logged accurately with diagnostic details.
  - Forensic referential integrity verification across all foreign keys confirmed **0 orphaned records** in `teacher_classes`, `students`, and `scores`.
- **Verdict**: **PASS (ROBUST)**.

---

## 3. Stress Test Results Matrix

| ID | Test Scenario | Expected Behavior | Actual Behavior | Result |
|---|---|---|---|---|
| **V1.01** | POST `add_class` with `15Z9` (grade > 9) | Validation error, 0 rows added | Error rendered; 0 DB rows | **PASS** |
| **V1.02** | POST `add_class` with `5A1` (grade < 6) | Validation error, 0 rows added | Error rendered; 0 DB rows | **PASS** |
| **V1.03** | POST `add_class` with `10A1` (grade > 9) | Validation error, 0 rows added | Error rendered; 0 DB rows | **PASS** |
| **V1.04** | POST `add_class` with `6E1` (invalid section 'E') | Validation error, 0 rows added | Error rendered; 0 DB rows | **PASS** |
| **V1.05** | POST `add_class` with XSS in name | Format regex error, 0 rows added | Format error; 0 DB rows | **PASS** |
| **V1.06** | POST `add_class` with SQLi in name | Format regex error, 0 rows added | Format error; 0 DB rows | **PASS** |
| **V1.07** | POST `add_class` with existing class `6A1` | Duplicate error, count unchanged | Duplicate notice; count invariant | **PASS** |
| **V1.08** | Rapid burst: 5 identical POSTs for `8B99_BURST` | Exactly 1 class created, 0 duplicates | Exactly 1 record created | **PASS** |
| **V1.09** | Rapid deletion burst: delete `8B99_BURST` twice | 2nd delete reports not found gracefully | Graceful error, no server 500 | **PASS** |
| **V1.10** | Cascade deletion: delete class with students/scores | Child records purged cleanly via FK | 0 orphaned records remaining | **PASS** |
| **V2.01** | Mass selection: batch-assign 36 classes (1..36) | All 36 classes assigned atomically | 36 classes assigned in 1 txn | **PASS** |
| **V2.02** | Rapid re-submission burst 1: identical 36 classes | 0 new rows, 36 classes skipped | 0 rows added; skip notice shown | **PASS** |
| **V2.03** | Rapid re-submission burst 2: 3rd identical submission | 0 new rows, exact invariance | Count unchanged (36 rows) | **PASS** |
| **V2.04** | POST `batch_assign` with teacher ID 0 | Validation error, 0 rows added | Error rendered; 0 DB rows | **PASS** |
| **V2.05** | POST `batch_assign` with teacher ID -1 | Validation error, 0 rows added | Error rendered; 0 DB rows | **PASS** |
| **V2.06** | POST `batch_assign` with teacher ID 99999 | Error: teacher not found, 0 rows | Error rendered; 0 DB rows | **PASS** |
| **V2.07** | POST `batch_assign` with admin user ID 1 | Error: role must be teacher, 0 rows | Rejected non-teacher role | **PASS** |
| **V2.08** | POST `batch_assign` with empty subject `""` | Validation error, 0 rows added | Error rendered; 0 DB rows | **PASS** |
| **V2.09** | POST `batch_assign` with whitespace `"   "` | Validation error, 0 rows added | Trimmed and rejected | **PASS** |
| **V2.10** | POST `batch_assign` with empty `class_ids=[]` | Validation error, 0 rows added | Error rendered; 0 DB rows | **PASS** |
| **V2.11** | POST `batch_assign` with `class_ids=[0, -5]` | Non-positive IDs filtered out | Handled safely; 0 DB rows | **PASS** |
| **V2.12** | Cleanup: restore baseline assignments | Return to baseline seeds (1, 10) | Remaining for gv_toan: 2 | **PASS** |
| **V3.01** | Unauthenticated GET `/admin.php` | HTTP 403 / 302, zero UI leak | HTTP 403; zero admin UI leaked | **PASS** |
| **V3.02** | Unauthenticated POST `add_class` | HTTP 403 / 302, 0 classes created | HTTP 403; 0 classes created | **PASS** |
| **V3.03** | Unauthenticated POST `delete_class` | HTTP 403 / 302, class 1 preserved | HTTP 403; class 1 intact | **PASS** |
| **V3.04** | Unauthenticated GET `?delete_class=1` | HTTP 403 / 302, class 1 preserved | HTTP 403; class 1 intact | **PASS** |
| **V3.05** | Unauthenticated POST `create_teacher` | HTTP 403 / 302, 0 users created | HTTP 403; 0 users created | **PASS** |
| **V3.06** | Unauthenticated POST `batch_assign` | HTTP 403 / 302, 0 assignments created | HTTP 403; 0 assignments created | **PASS** |
| **V3.07** | Unauthenticated POST `delete_assign` | HTTP 403 / 302, assignment preserved | HTTP 403; assignment intact | **PASS** |
| **V3.08** | Unauthenticated GET `?delete_assign=1` | HTTP 403 / 302, assignment preserved | HTTP 403; assignment intact | **PASS** |
| **V3.09** | Unauthenticated GET `?impersonate=2` | HTTP 403 / 302, impersonation blocked | HTTP 403; blocked | **PASS** |
| **V3.10** | Unauthenticated POST `import_students` | HTTP 403 / 302, 0 students imported | HTTP 403; 0 students imported | **PASS** |
| **V4.01** | Teacher GET `/admin.php` | HTTP 403 Forbidden, zero UI leak | HTTP 403; zero admin UI leaked | **PASS** |
| **V4.02** | Teacher POST `add_class` | HTTP 403 Forbidden, 0 classes created | HTTP 403; 0 classes created | **PASS** |
| **V4.03** | Teacher POST `create_class` | HTTP 403 Forbidden, 0 classes created | HTTP 403; 0 classes created | **PASS** |
| **V4.04** | Teacher POST `delete_class` | HTTP 403 Forbidden, class preserved | HTTP 403; class 1 intact | **PASS** |
| **V4.05** | Teacher GET `?delete_class=1` | HTTP 403 Forbidden, class preserved | HTTP 403; class 1 intact | **PASS** |
| **V4.06** | Teacher POST `create_teacher` | HTTP 403 Forbidden, 0 users created | HTTP 403; 0 users created | **PASS** |
| **V4.07** | Teacher POST `batch_assign` | HTTP 403 Forbidden, 0 assignments | HTTP 403; 0 assignments created | **PASS** |
| **V4.08** | Teacher POST `delete_assign` | HTTP 403 Forbidden, assignment preserved | HTTP 403; assignment intact | **PASS** |
| **V4.09** | Teacher GET `?delete_assign=3` | HTTP 403 Forbidden, assignment preserved | HTTP 403; assignment intact | **PASS** |
| **V4.10** | Teacher GET `?impersonate=1` (Admin) | HTTP 403 Forbidden, escalation blocked | HTTP 403; escalation blocked | **PASS** |
| **V4.11** | Teacher GET `?impersonate=3` (Peer) | HTTP 403 Forbidden, escalation blocked | HTTP 403; escalation blocked | **PASS** |
| **V4.12** | Teacher POST `import_students` | HTTP 403 Forbidden, 0 students imported | HTTP 403; 0 students imported | **PASS** |
| **V4.13** | Teacher header spoofing bypass | HTTP 403 Forbidden, spoofing ignored | HTTP 403; spoofing blocked | **PASS** |
| **V5.01** | Authorized Admin GET `/admin.php` | HTTP 200 OK, full Admin Portal UI | HTTP 200 OK; UI loaded | **PASS** |
| **V5.02** | Authorized Admin inspection of gradebook | HTTP 200 OK, gradebook accessible | HTTP 200 OK; accessible | **PASS** |
| **V5.03** | Audit log capture of blocked attacks | Blocked events recorded with role & user | Verified in `audit_logs` | **PASS** |
| **V5.04** | Forensic referential integrity | Zero orphaned records across tables | Verified 0 orphaned records | **PASS** |

---

## 4. Unchallenged Areas

- **Excel OpenXML binary engine & student roster deduplication**: Delegated to peer challenger `challenger_m3_1` (`includes/xlsx_helper.php`, malformed ZIP/XML packages, date parsing edge cases).
- **Grade calculation and spreadsheet keyboard navigation**: Scheduled for Milestone 4 (`nhap_diem.php` VnEdu table, Thông tư 22 weighting formula).

---

## 5. Verdict & Recommendation

**Verdict**: **APPROVE**

Milestone 3's Class Matrix operations, batch checkbox assignment engine, dynamic class management, and server-side RBAC security boundaries demonstrate complete resilience under adversarial testing. Authorization defenses are enforced consistently at route entry and handler level, input validation regexes reject malformed/injected parameters, duplicate prevention is enforced at both application and database constraint levels, and all security boundary violations are accurately captured in MySQL audit logs. Milestone 3 is ready for integration and downstream milestones.
