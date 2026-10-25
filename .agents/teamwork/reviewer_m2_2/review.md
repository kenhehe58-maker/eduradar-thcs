# Milestone 2 Quality & Adversarial Review Report

**Reviewer**: `reviewer_m2_2` (teamwork_preview_reviewer)  
**Roles**: reviewer, critic  
**Target Milestone**: Milestone 2 (Authentication, Password Hashing & Strict RBAC)  
**Target Files**: `includes/auth.php`, `login.php`, `logout.php`, `admin.php`, `nhap_diem.php`, `tests/verify_m2.php`  
**Date**: 2026-10-17  

---

## Executive Summary

- **Verdict**: **APPROVE**
- **Integrity Violation Scan**: **CLEAN** (0 violations detected: no hardcoded test outputs, no dummy logic, no bypasses).
- **Automated Verification Status**:
  - `tests/verify_m2.php`: **16/16 Passed** (0 failures, exit code 0)
  - `tests/verify_m1.php`: **12/12 Passed** (0 failures, exit code 0)
- **Live System Testing**: Verified against XAMPP Apache 2.4 on port 80 and MariaDB 10.4 on port 3306.

---

## 1. Quality Review

### 1.1 RBAC Middleware (`includes/auth.php`)
- **`require_login()`**:
  - Correctly verifies `isset($_SESSION['user_id'])` and `!empty($_SESSION['user_id'])`.
  - Redirects unauthenticated requests to `login.php` with immediate script termination (`exit`).
- **`require_admin()`**:
  - Rigorously checks `$_SESSION['user_id']` and `$_SESSION['role'] === 'admin'`.
  - Non-admin or unauthenticated access results in HTTP 403 Forbidden, an audit log record in MySQL (`audit_logs`), a styled 403 error page via `render_forbidden_page()`, and immediate termination.
- **`current_user()`**:
  - Retrieves active user session data with an optional `$refreshFromDb` parameter using prepared queries.
- **`verify_teacher_assignment()`**:
  - Trims inputs and strictly validates boundaries (`$teacher_id > 0`, `$class_id > 0`, non-empty `$subject`).
  - Queries `teacher_classes` via prepared statements: `SELECT 1 FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = ? LIMIT 1`.
  - Safely catches exceptions and returns boolean without leaking database internals.
- **`require_teacher_assignment()`**:
  - Verifies authentication first via `require_login()`.
  - Grants admin inspection privileges while enforcing strict checks for teachers: `teacher_id === sessionUserId` and `verify_teacher_assignment() === true`.
  - Enforces HTTP 403, writes to `audit_logs`, and renders forbidden error template upon violation.

### 1.2 Route Guarding (`admin.php`)
- Protected at top of file (line 5) via `require_admin()` prior to any state-changing operations.
- Handler actions (`batch_assign`, `import_students`, `impersonate`, `delete_assign`) explicitly re-verify admin authorization.
- Zero database mutations occur when teacher or unauthenticated entities send GET/POST requests.
- All SQL statements use prepared statements (`$pdo->prepare()`), eliminating SQL injection risks.

### 1.3 BOLA/IDOR Prevention (`nhap_diem.php`)
- **IDOR Neutralization**: Line 29 directly halts non-admin requests attempting to specify a different `?teacher_id` with HTTP 403 and audit logging.
- **BOLA Neutralization**: Verifies that requested `(class_id, subject)` belongs to the authenticated teacher. Unassigned pairs are rejected with HTTP 403.
- **Cross-Class Score Protection**: When saving scores, the handler executes `$chkStudent->execute([$studentId, $selectedClassId])`, ensuring teachers cannot inject scores for students in unassigned classes.
- **Admin Supervision**: Admins retain legitimate school-wide inspection and editing capabilities as specified in `PROJECT.md`.

### 1.4 Authentication & Session Lifecycle (`login.php`, `logout.php`)
- Backdoor bypass `|| $password === '123456'` is completely eliminated.
- Password authentication exclusively uses `password_verify($password, $user['password'])`.
- Prevents session fixation via `session_regenerate_id(true)`.
- `logout.php` records identity, zeroes `$_SESSION = []`, expires the session cookie with `time() - 42000`, calls `session_destroy()`, and redirects to `login.php`.
- Complete forensic trail in `audit_logs` capturing success, failure (with attempted username), and logout.

---

## 2. Adversarial Stress-Testing & Attack Surface Analysis

| # | Attack Scenario | Attack Vector Tested | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| 1 | Unauthenticated Admin Access | `GET /admin.php` without cookies | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| 2 | Teacher Route Escalation | `GET /admin.php` with `gv_toan` session | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| 3 | Forged Admin Action | `POST /admin.php` (batch assign) with `gv_toan` | HTTP 403, 0 rows added | HTTP 403, 0 rows added | **PASS** |
| 4 | Teacher IDOR Tampering | `GET /nhap_diem.php?teacher_id=3` by `gv_toan` | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| 5 | Teacher BOLA Tampering | `GET /nhap_diem.php?class_id=1&subject=Ngữ Văn` by `gv_toan` | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| 6 | Forged Grade Update | `POST /nhap_diem.php` with unassigned subject | HTTP 403, 0 grade changes | HTTP 403, 0 grade changes | **PASS** |
| 7 | Authentication Bypass Attempt | `POST /login.php` with invalid password (`1234567`) | Login rejected, HTTP 200 | Login rejected, HTTP 200 | **PASS** |
| 8 | Post-Logout Protected Access | `GET /admin.php` using terminated cookie | Blocked (HTTP 403) | Blocked (HTTP 403) | **PASS** |
| 9 | Edge Case: Non-numeric / SQLi in subject | `verify_teacher_assignment(2, 1, "Toán' OR '1'='1")` | Evaluates to `false` | Evaluates to `false` | **PASS** |
| 10 | Legitimate Admin Inspection | `GET /nhap_diem.php?class_id=1&subject=Ngữ Văn` by `admin` | HTTP 200 OK | HTTP 200 OK | **PASS** |

---

## 3. Verified Claims

1. **Claim**: `includes/auth.php` exists with all required RBAC functions.
   - *Method*: Verified via unit checks and file inspection. Functions `require_login`, `require_admin`, `current_user`, `verify_teacher_assignment`, `require_teacher_assignment` are all defined and fully operational.
2. **Claim**: Backdoor `|| $password === '123456'` removed.
   - *Method*: Static file scan and negative password authentication tests.
3. **Claim**: Unauthenticated and teacher access to `admin.php` is blocked with HTTP 403 and zero DB changes.
   - *Method*: Executed via cURL against live Apache and verified `classes` table row count remained unchanged.
4. **Claim**: Teachers can only access assigned `(class_id, subject)` pairs in `nhap_diem.php`.
   - *Method*: Tested `gv_toan` accessing assigned vs unassigned pairs. Assigned returned 200; unassigned returned 403.
5. **Claim**: Unauthorized access attempts are recorded in `audit_logs`.
   - *Method*: Inspected live MySQL table `audit_logs`, confirming IP, username, role, and URI details are logged.

---

## 4. Final Verdict

**APPROVE** — The implementation of Milestone 2 meets all security requirements, architectural contracts, and acceptance criteria in `PROJECT.md` and `ORIGINAL_REQUEST.md` with zero integrity violations.
