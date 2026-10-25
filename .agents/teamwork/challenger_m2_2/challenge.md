# Adversarial Challenge Report: Milestone 2 RBAC & BOLA / IDOR Protection

**Agent**: `challenger_m2_2` (teamwork_preview_challenger)  
**Roles**: critic, specialist  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\challenger_m2_2`  
**Date**: 2026-10-17  
**Verdict**: **APPROVE**  
**Overall Risk Assessment**: **LOW** (Zero exploitable privilege escalation, BOLA, or IDOR bypasses discovered)

---

## 1. Challenge Summary

We subjected the Milestone 2 implementation of EduRadar THCS to an exhaustive empirical challenge harness (`tests/challenge_m2_rbac.php`), issuing live HTTP requests against the active XAMPP Apache 2.4 server (`http://localhost/my-web-project/`) and directly validating database state against live MariaDB 10.4 (`edu_radar`).

A total of **37 adversarial live HTTP tests** were executed across four distinct threat vectors:
1. **Administrative Route Protection & Privilege Escalation (`admin.php`)**: 12 challenges.
2. **Broken Object Level Authorization (BOLA) & IDOR Protection (`nhap_diem.php`)**: 18 challenges.
3. **Legitimate Negative Controls (Authorized Admin & Teacher operations)**: 5 challenges.
4. **Database State & Audit Log Forensics**: 2 challenges.

**Outcome**: **37 / 37 PASS (0 Failures)**. All forged requests were rejected with `HTTP 403 Forbidden` (or `HTTP 302 Found` to `login.php` for unauthenticated sessions), no sensitive administrative or gradebook data was leaked in response payloads, and **zero unauthorized mutations occurred across `teacher_classes`, `classes`, `students`, and `scores` tables**.

---

## 2. Adversarial Challenges & Findings

### Challenge Area 1: Administrative Control Bypass & Privilege Escalation (`admin.php`)

- **Assumption Challenged**: Can unauthenticated attackers or authenticated teachers (`gv_toan`) view `admin.php`, delete assignments, impersonate users, assign themselves to arbitrary classes, or import rogue student rosters?
- **Attack Scenarios Tested**:
  - `GET /admin.php` unauthenticated.
  - `GET /admin.php?impersonate=2&cid=1&sub=Toán` unauthenticated.
  - `GET /admin.php?delete_assign=1` unauthenticated.
  - `POST /admin.php` unauthenticated with `action=batch_assign` (assigning classes `10, 19, 36`).
  - `POST /admin.php` unauthenticated with `action=import_students` to class 1.
  - `GET /admin.php` authenticated as teacher `gv_toan`.
  - `GET /admin.php?delete_assign=3` authenticated as teacher `gv_toan` (attempting to delete `gv_van`'s assignment).
  - `GET /admin.php?impersonate=1` authenticated as teacher `gv_toan` (attempting privilege escalation to admin).
  - `POST /admin.php` authenticated as teacher `gv_toan` with `action=batch_assign` (attempting to self-assign 8A1 & 9C3).
  - `POST /admin.php` authenticated as teacher `gv_toan` with `action=import_students` (attempting to add students).
  - Header spoofing bypass: `X-Forwarded-For: 127.0.0.1`, `X-Real-IP: 127.0.0.1`, `X-Original-URL: /index.php`, `X-Rewrite-URL: /index.php`, `Client-IP: 127.0.0.1`.
- **Observed Behavior**:
  - All 11 attack variations were blocked with **`HTTP 403 Forbidden`**.
  - Response bodies contained only the standardized Stitch UI 403 error page; zero administrative UI, class lists, or assignment tables were leaked.
  - MySQL queries before and after the attacks confirmed:
    - `teacher_classes` count remained unchanged (3 rows).
    - `classes` count remained unchanged (36 rows).
    - `students` count remained unchanged (5 rows).
  - Authentic Admin request (`admin` / `123456`) successfully loaded the admin portal with **`HTTP 200 OK`**.
- **Assessment**: **ROBUST**. Defense in depth is enforced via `require_admin()` at line 5 of `admin.php` before any GET or POST action handlers execute.

---

### Challenge Area 2: Broken Object Level Authorization (BOLA) in Gradebook (`nhap_diem.php`)

- **Assumption Challenged**: Can a teacher (`gv_toan`) view or modify student grades for unassigned classes (e.g., `7A1 Ngữ Văn`, `8A1 Toán`, `9C3 Tin Học`) or for classes where they teach a different subject (e.g., `6A1 Ngữ Văn` belonging to `gv_van`)?
- **Attack Scenarios Tested**:
  - `GET /nhap_diem.php?class_id=10&subject=Ngữ Văn` as `gv_toan` (`7A1 Ngữ Văn`, unassigned subject).
  - `GET /nhap_diem.php?class_id=19&subject=Toán` as `gv_toan` (`8A1 Toán`, unassigned class).
  - `GET /nhap_diem.php?class_id=36&subject=Tin Học` as `gv_toan` (`9C3 Tin Học`, unassigned class and subject).
  - `GET /nhap_diem.php?class_id=1&subject=Ngữ Văn` as `gv_toan` (`6A1 Ngữ Văn`, peer teacher's subject).
  - `POST /nhap_diem.php?class_id=10&subject=Ngữ Văn` as `gv_toan` submitting grades for student in 7A1.
  - `POST /nhap_diem.php?class_id=19&subject=Toán` as `gv_toan` submitting grades for student in 8A1.
  - `POST /nhap_diem.php?class_id=36&subject=Tin Học` as `gv_toan` submitting grades for student in 9C3.
  - `POST /nhap_diem.php?class_id=1&subject=Ngữ Văn` as `gv_toan` submitting grades for student in 6A1.
  - Cross-class student ID injection: `POST /nhap_diem.php?class_id=1&subject=Toán` (assigned pair), but injecting non-assigned student ID `99999` in `scores` payload.
  - Peer teacher cross-testing: `gv_van` attempting GET and POST against `6A1 Toán` (assigned to `gv_toan`).
- **Observed Behavior**:
  - All GET and POST requests for unassigned classes and subjects were terminated immediately with **`HTTP 403 Forbidden`**.
  - Direct database inspection confirmed zero grade modifications in MySQL `scores` table.
  - In the cross-class student ID injection test, student ownership validation (`SELECT 1 FROM students WHERE id = ? AND class_id = ?`) properly skipped invalid student IDs, preventing cross-class grade corruption.
  - Cross-testing with `gv_van` confirmed symmetric protection: `gv_van` was strictly blocked from accessing `gv_toan`'s `6A1 Toán` gradebook.
- **Assessment**: **ROBUST**. Strict authorization checks in `require_teacher_assignment()` cross-reference `$_SESSION['user_id']` against `teacher_classes` using parameterized PDO queries.

---

### Challenge Area 3: Insecure Direct Object Reference (IDOR) via `teacher_id` Parameter

- **Assumption Challenged**: Can a teacher tamper with `?teacher_id=X` in query string or POST parameters to view or edit another teacher's gradebook?
- **Attack Scenarios Tested**:
  - `GET /nhap_diem.php?teacher_id=3&class_id=1&subject=Ngữ Văn` as `gv_toan` (attempting to view `gv_van`'s gradebook).
  - `GET /nhap_diem.php?teacher_id=1&class_id=1&subject=Toán` as `gv_toan` (attempting to view admin's inspection view).
  - `GET /nhap_diem.php?teacher_id=999` as `gv_toan` (non-existent teacher ID).
  - `GET /nhap_diem.php?teacher_id=0` and `?teacher_id=-1` as `gv_toan`.
  - `GET /nhap_diem.php?teacher_id=abc` and URL-encoded SQLi payloads `teacher_id=3%20OR%201=1`.
  - `POST /nhap_diem.php?teacher_id=3&class_id=1&subject=Ngữ Văn` as `gv_toan` attempting to forge grades.
  - `GET /nhap_diem.php?teacher_id=2` as `gv_van` (peer IDOR tampering).
- **Observed Behavior**:
  - Every non-admin request containing a `teacher_id` that differed from `$_SESSION['user_id']` was immediately halted with **`HTTP 403 Forbidden`**.
  - Every IDOR tampering attempt was recorded in `audit_logs` with specific diagnostic details (e.g. `Giáo viên [gv_toan] cố tình giả mạo tham số teacher_id=3`).
  - Zero database modifications occurred.
  - Authentic Admin inspection (`admin` using `?teacher_id=2`) succeeded with **`HTTP 200 OK`**, demonstrating that administrative oversight remains fully functional.
- **Assessment**: **ROBUST**. `nhap_diem.php` explicitly checks `if (isset($_GET['teacher_id']) && (int)$_GET['teacher_id'] !== $realUserId)` for all non-admin roles and halts execution before any data retrieval.

---

### Challenge Area 4: Boundary & Malformed Input Handling

- **Assumption Challenged**: Can SQL injection, negative integers, or out-of-range IDs in `class_id` or `subject` bypass the authorization check?
- **Attack Scenarios Tested**:
  - `class_id=999999&subject=Toán` (non-existent class ID).
  - `class_id=1&subject=Địa Lý` (unassigned subject).
  - `class_id=1&subject=Hóa Học` (unassigned subject).
  - `class_id=1&subject=' OR '1'='1` (SQL injection payload in subject).
  - `class_id=999 UNION SELECT 1&subject=Toan` (SQL injection payload in class_id).
  - `GET /nhap_diem.php` without query parameters (default landing navigation).
- **Observed Behavior**:
  - All non-existent, unassigned, and SQL-injected parameters were rejected with **`HTTP 403 Forbidden`**.
  - PDO prepared statements with positional parameters (`?`) completely neutralized SQL injection attempts in both `class_id` and `subject`.
  - Navigating to `nhap_diem.php` without parameters safely defaulted to the logged-in teacher's first assigned class and subject, returning **`HTTP 200 OK`** without exposing any unassigned data.
- **Assessment**: **ROBUST**.

---

## 3. Stress Test Results Matrix

| # | Threat Vector | Target Endpoint & Payload | Session / Role | Expected | Actual | DB Mutated | Result |
|---|---|---|---|---|---|---|---|
| 1.1 | Unauth Admin Access | `GET admin.php` | Guest (No Session) | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.2 | Unauth Impersonation | `GET admin.php?impersonate=2&cid=1` | Guest | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.3 | Unauth Assignment Deletion | `GET admin.php?delete_assign=1` | Guest | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.4 | Unauth Forged Batch Assign | `POST admin.php action=batch_assign` | Guest | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.5 | Unauth Student Roster Import | `POST admin.php action=import_students` | Guest | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.6 | Teacher Admin Access | `GET admin.php` | `gv_toan` (Teacher) | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.7 | Teacher Assignment Deletion | `GET admin.php?delete_assign=3` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.8 | Teacher Impersonation Escalation | `GET admin.php?impersonate=1` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.9 | Teacher Forged Batch Assign | `POST admin.php action=batch_assign (8A1, 9C3)` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.10 | Teacher Student Import | `POST admin.php action=import_students` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.11 | Admin Header Spoofing | `GET admin.php X-Forwarded-For: 127.0.0.1` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 1.12 | Legitimate Admin Access | `GET admin.php` | `admin` | HTTP 200 | HTTP 200 | N/A | **PASS** |
| 2.1 | Unauth Gradebook Access | `GET nhap_diem.php` | Guest | HTTP 302 -> login.php | HTTP 302 | 0 rows | **PASS** |
| 2.2 | Unauth Score Submission | `POST nhap_diem.php action=save_scores` | Guest | HTTP 302/403 | HTTP 302 | 0 rows | **PASS** |
| 2.3 | BOLA Unassigned Subject | `GET nhap_diem.php?class_id=10&subject=Ngữ Văn` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.4 | BOLA Unassigned Class | `GET nhap_diem.php?class_id=19&subject=Toán` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.5 | BOLA Unassigned Pair | `GET nhap_diem.php?class_id=36&subject=Tin Học` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.6 | BOLA Peer Subject | `GET nhap_diem.php?class_id=1&subject=Ngữ Văn` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.7 | BOLA Forged POST 7A1 Văn | `POST nhap_diem.php?class_id=10&subject=Ngữ Văn` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.8 | BOLA Forged POST 8A1 Toán | `POST nhap_diem.php?class_id=19&subject=Toán` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.9 | BOLA Forged POST 9C3 Tin | `POST nhap_diem.php?class_id=36&subject=Tin Học` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.10 | BOLA Forged POST 6A1 Văn | `POST nhap_diem.php?class_id=1&subject=Ngữ Văn` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.11 | IDOR Tampering teacher_id=3 | `GET nhap_diem.php?teacher_id=3&class_id=1` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.12 | IDOR Tampering teacher_id=1 | `GET nhap_diem.php?teacher_id=1&class_id=1` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.13 | IDOR Tampering Arbitrary | `GET nhap_diem.php?teacher_id=[999, 0, -1, abc, SQLi]` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.14 | IDOR Forged POST | `POST nhap_diem.php?teacher_id=3&class_id=1` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.15 | Cross-Class Student Injection | `POST nhap_diem.php?class_id=1 scores[99999]=...` | `gv_toan` | Rejected student | Filtered | 0 rows | **PASS** |
| 2.16 | Peer Cross-Testing | `GET/POST 6A1 Toán & teacher_id=2` | `gv_van` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.17 | SQLi & Boundary Attacks | `class_id=[999999, SQLi] & subject=[DiaLy, SQLi]` | `gv_toan` | HTTP 403 | HTTP 403 | 0 rows | **PASS** |
| 2.18 | Safe Default Navigation | `GET nhap_diem.php` | `gv_toan` | HTTP 200 (own class) | HTTP 200 | 0 rows | **PASS** |
| 3.1 | Authorized Access 6A1 Toán | `GET nhap_diem.php?class_id=1&subject=Toán` | `gv_toan` | HTTP 200 | HTTP 200 | N/A | **PASS** |
| 3.2 | Authorized Access 7A1 Toán | `GET nhap_diem.php?class_id=10&subject=Toán` | `gv_toan` | HTTP 200 | HTTP 200 | N/A | **PASS** |
| 3.3 | Authorized Access 6A1 Văn | `GET nhap_diem.php?class_id=1&subject=Ngữ Văn` | `gv_van` | HTTP 200 | HTTP 200 | N/A | **PASS** |
| 3.4 | Admin Direct Inspection | `GET nhap_diem.php?class_id=1&subject=Ngữ Văn` | `admin` | HTTP 200 | HTTP 200 | N/A | **PASS** |
| 3.5 | Admin Inspected Teacher View | `GET nhap_diem.php?teacher_id=2&class_id=1` | `admin` | HTTP 200 | HTTP 200 | N/A | **PASS** |
| 4.1 | DB Row Count Preservation | Snapshot comparisons of 4 core tables | System | Exactly equal | Identical | 0 diffs | **PASS** |
| 4.2 | Audit Log Generation | Count of 'Truy cập trái phép bị chặn' | System | New rows written | 443 logged | Validated | **PASS** |

---

## 4. Unchallenged Areas

- **Spreadsheet UI & OpenXML (.xlsx) File Import/Export**: The 6-column VnEdu spreadsheet calculation engine (`tx1..tx4, gk, ck, avg_score`), keyboard navigation, and OpenXML Excel file handling are scheduled for **Milestone 4** per `PROJECT.md`.
- **Early-Warning Radar & Zalo Removal**: Classification into Red/Yellow/Green warnings and UI cleanup are scheduled for **Milestone 5**.
- **Admin Class Matrix & VnEdu Student Roster Import**: Scheduled for **Milestone 3**.

---

## 5. Verdict

**Verdict**: **APPROVE**  
Milestone 2 RBAC & BOLA / IDOR Protection is thoroughly implemented, verified, and resistant against adversarial attack vectors.
