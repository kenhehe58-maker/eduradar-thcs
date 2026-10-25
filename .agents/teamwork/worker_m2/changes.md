# Changes Summary — Milestone 2: Authentication, Password Hashing & Strict RBAC

**Worker**: `worker_m2` (teamwork_preview_worker)  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\worker_m2`  

---

## 1. Files Created and Modified

### `includes/auth.php` (NEW)
- Created centralized authentication and RBAC middleware.
- Functions implemented:
  - `require_login(): void`: Verifies active session; redirects unauthenticated users to `login.php`.
  - `current_user(bool $refreshFromDb = false): ?array`: Returns array `['id', 'username', 'full_name', 'role']` or null if unauthenticated, with optional DB synchronization.
  - `require_admin(): void`: Restricts access to users with `$_SESSION['role'] === 'admin'`. Logs unauthorized attempts with `'Truy cập trái phép bị chặn'` into `audit_logs`, emits HTTP 403 status and displays a styled Stitch UI access denied page before terminating execution.
  - `verify_teacher_assignment(int $teacher_id, int $class_id, string $subject): bool`: Queries `teacher_classes` via indexed prepared statements to verify whether teacher ID is assigned to class ID and subject.
  - `require_teacher_assignment(int $teacher_id, int $class_id, string $subject): void`: Enforces teacher assignment checks on gradebook routes. Automatically allows admins full access across all classes and subjects. For teachers, verifies matching session identity and teaching assignment. Logs violations to `audit_logs`, emits HTTP 403, and displays styled denial page.
  - `render_forbidden_page(string $message): void`: Outputs responsive, modern Stitch UI error page for HTTP 403.

### `login.php` (OVERHAULED)
- Removed universal bypass vulnerability: deleted `|| $password === '123456'`.
- Enforced strict bcrypt verification using native `password_verify($password, $user['password'])`.
- Implemented session fixation protection via `session_regenerate_id(true)`.
- Implemented role-based redirection:
  - `admin` -> `admin.php`
  - `teacher` -> `index.php`
- Hardened audit logging:
  - Successful login: `log_action((int)$user['id'], $user['username'], 'Đăng nhập thành công', ...)`
  - Failed login: `log_action(null, $username, 'Đăng nhập thất bại', ...)` recording the exact attempted username rather than generic `'Khách'`.
- Preserved Stitch UI styling and pre-fill buttons for demo credentials.

### `logout.php` (OVERHAULED)
- Replaced basic `session_destroy()` with full, secure teardown:
  - Logs `log_action($userId, $username, 'Đăng xuất', ...)` before destroying session context.
  - Clears `$_SESSION = []`.
  - Invalidates client-side session cookie via `setcookie()` with expired timestamp.
  - Calls `session_destroy()`.
  - Redirects cleanly to `login.php`.

### `admin.php` (HARDENED)
- Added top-level `require_admin()` guard at file entry point immediately after including `includes/auth.php`.
- Added defense-in-depth `require_admin()` checks at the entry point of all action handlers:
  - POST `batch_assign`
  - POST `import_students`
  - GET `impersonate`
  - GET `delete_assign`
- Sanitized SQL queries: converted dynamic string concatenations (`$cid`, `$target_class_id`, `$aid`) into PDO prepared statements with parameter binding.
- Migrated legacy `logAction()` calls to `log_action((int)$_SESSION['user_id'], $_SESSION['username'], ...)`.

### `nhap_diem.php` (HARDENED AGAINST BOLA / IDOR)
- Integrated `includes/auth.php` and `require_login()`.
- Neutralized parameter tampering on `$_GET['teacher_id']`: strictly reserved for `role === 'admin'`; non-admin teachers attempting to supply another `teacher_id` receive HTTP 403 and are logged to `audit_logs`.
- Enforced `require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject)` on both GET (viewing) and POST (saving grades & importing files):
  - Assigned teachers: Full access to their assigned class and subject (HTTP 200).
  - Unassigned teachers: Blocked immediately with HTTP 403 and logged to `audit_logs` (0 database changes).
  - Admin: Unrestricted inspection and editing across all 36 classes and subjects.
- Added student cross-verification in POST `save_scores`: validates that each student ID belongs to the targeted class before saving, preventing cross-class score injection.
- Migrated audit logging to `log_action()`.

### `tests/verify_m2.php` (NEW)
- Created automated multi-account verification test suite runnable via `D:\xam\php\php.exe tests/verify_m2.php`.
- Covers 16 comprehensive test cases across 4 groups:
  - Group 1: Static verification (functions in `includes/auth.php`, backdoor removal in `login.php`, session clearing in `logout.php`).
  - Group 2: Authentication & session lifecycle (`admin`, `gv_toan`, `gv_van` valid logins, wrong password rejection, unknown user rejection, logout session destruction).
  - Group 3: Admin route guard (unauthenticated rejection with 403/302, teacher GET rejection with 403, teacher forged POST blocked with 0 DB changes, admin access 200).
  - Group 4: Gradebook BOLA / IDOR protection (unauthenticated redirect, assigned teacher access 200, BOLA unassigned subject and class blocked with 403, IDOR `teacher_id` tampering blocked with 403, POST score tampering blocked with 403 and 0 DB changes, admin inspection access 200).
- Validates double assertions: HTTP status codes + live MySQL database and audit log rows.

---

## 2. Verification Results

1. **`tests/verify_m2.php`**:
   - Command: `D:\xam\php\php.exe tests/verify_m2.php`
   - Result: `Total Tests: 16 | Passed: 16 | Failed: 0` (Exit code: `0`).

2. **`tests/verify_m1.php`** (Regression test):
   - Command: `D:\xam\php\php.exe tests/verify_m1.php`
   - Result: `Total Tests: 12 | Passed: 12 | Failed: 0` (Exit code: `0`).

3. **PHP Lint (`php -l`)**:
   - `login.php`: Clean
   - `logout.php`: Clean
   - `admin.php`: Clean
   - `nhap_diem.php`: Clean
   - `includes/auth.php`: Clean
   - `tests/verify_m2.php`: Clean
