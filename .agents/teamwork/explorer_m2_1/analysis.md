# Authentication & Session Security Overhaul: Analysis & Implementation Specification

**Module**: Milestone 2 — Authentication & Backdoor Removal (`login.php`, `logout.php`)  
**Author**: `explorer_m2_1`  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m2_1`  

---

## 1. Executive Summary

This document presents a comprehensive technical analysis and architectural overhaul specification for the authentication flow (`login.php`) and session destruction flow (`logout.php`) in EduRadar THCS. 

The primary objectives are:
1. **Eliminate the critical backdoor** in `login.php` line 16 (`|| $password === '123456'`), which previously allowed unauthorized access to any user account with `123456` regardless of their actual password hash.
2. **Strictly enforce native `password_verify()`** against bcrypt hashes stored in MySQL `users.password`.
3. **Correct role-based redirection**: Redirect `admin` to `admin.php` and `teacher` to `index.php` (fixing the prior incorrect redirect to `nhap_diem.php` on line 28).
4. **Implement robust audit logging** via `log_action()` in `includes/db.php`:
   - Record successful logins with authenticated `user_id`, `username`, and role.
   - Record failed login attempts with `user_id = NULL` while strictly recording the attempted `$username` (fixing the prior bug where `logAction()` wrote `'Khách'`).
   - Record logout actions with pre-destruction user identity.
5. **Harden session management**:
   - Prevent **Session Fixation** by issuing `session_regenerate_id(true)` upon successful login.
   - Ensure complete session destruction on logout (emptying `$_SESSION = []`, expiring client session cookie, and executing `session_destroy()`).

---

## 2. Investigation & Detailed Flaw Analysis

### 2.1 Flaw 1: Hardcoded Password Backdoor (`login.php:16`)
- **Direct Observation**:
  ```php
  // login.php (lines 16-30)
  if ($user && (password_verify($password, $user['password']) || $password === '123456')) {
      $_SESSION['user_id'] = $user['id'];
      ...
  ```
- **Vulnerability**:
  The logical OR expression `|| $password === '123456'` acts as a global master password. If an administrator creates a new teacher or modifies passwords to high-entropy strings, an attacker can still log in as that user simply by supplying `'123456'`.
- **Remediation**:
  Replace the entire condition with:
  ```php
  if ($user && password_verify($password, $user['password'])) {
  ```
  Every user must authenticate solely against the cryptographic hash in `users.password`.

### 2.2 Flaw 2: Incorrect Post-Login Teacher Redirect (`login.php:28`)
- **Direct Observation**:
  ```php
  // login.php (lines 25-29)
  if ($user['role'] === 'admin') {
      header('Location: admin.php');
  } else {
      header('Location: nhap_diem.php');
  }
  ```
- **Specification Conflict**:
  - `PROJECT.md` line 14: Teacher role has access to `index.php` (assigned classes only) and `nhap_diem.php` (assigned pairs).
  - `PROJECT.md` line 23: Role-based redirect requirement: `admin -> admin.php`, `teacher -> index.php`.
  - `DISPATCH.md` task 2: "Role-based redirect: admin -> admin.php, teacher -> index.php."
  `index.php` is the teacher's overview dashboard containing early-warning counters and class selections. Redirecting directly to `nhap_diem.php` bypasses this overview and fails the specification.
- **Remediation**:
  Change the redirect target in the `else` block to `index.php`.

### 2.3 Flaw 3: Inadequate Audit Logging on Failed Logins (`login.php:33`)
- **Direct Observation**:
  In `login.php` line 33:
  ```php
  logAction('Đăng nhập thất bại', "Thử đăng nhập sai mật khẩu với tài khoản [$username].");
  ```
  In `includes/db.php` lines 101-106:
  ```php
  function logAction(string $action, string $details = ''): void
  {
      $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
      $username = $_SESSION['username'] ?? 'Khách';
      log_action($userId, $username, $action, $details);
  }
  ```
- **Vulnerability**:
  Because the user has not authenticated, `$_SESSION['username']` is not set. As a result, `logAction()` defaults to `$username = 'Khách'`. Consequently, `audit_logs.username` in the database stores `'Khách'`, obscuring the actual targeted account name in forensic queries!
- **Remediation**:
  Call `log_action()` directly with explicit parameters:
  ```php
  log_action(
      null,
      $username,
      'Đăng nhập thất bại',
      "Thử đăng nhập thất bại với tài khoản [$username]."
  );
  ```
  This correctly records `audit_logs.user_id = NULL` and `audit_logs.username = $username`.

### 2.4 Flaw 4: Missing Session Fixation Protection (`login.php:17`)
- **Direct Observation**:
  `login.php` immediately writes to `$_SESSION` without regenerating the session identifier.
- **Vulnerability**:
  An attacker who primes a victim with a known session ID (via URL parameter, header, or cookie injection) could hijack the authenticated session once the victim logs in.
- **Remediation**:
  Call `session_regenerate_id(true);` immediately upon successful `password_verify()` before writing `$_SESSION` keys.

### 2.5 Flaw 5: Incomplete Session Destruction on Logout (`logout.php:5-10`)
- **Direct Observation**:
  ```php
  // logout.php
  if (isset($_SESSION['username'])) {
      logAction('Đăng xuất', "Tài khoản [{$_SESSION['username']}] đã đăng xuất.");
  }
  session_destroy();
  header('Location: login.php');
  exit;
  ```
- **Vulnerability**:
  In PHP:
  1. `session_destroy()` does NOT unset the `$_SESSION` global array during the current execution.
  2. The client's session cookie is not deleted or expired, leaving stale cookies on shared workstations.
  3. Calling `logAction()` relies on session resolution instead of explicitly passing parameters.
- **Remediation**:
  1. Capture `$userId` and `$username` from `$_SESSION` first.
  2. Call `log_action($userId, $username, 'Đăng xuất', ...)`.
  3. Wipe the runtime array: `$_SESSION = [];`.
  4. Invalidate the client session cookie with `setcookie()`.
  5. Call `session_destroy()`.
  6. Send `header('Location: login.php'); exit;`.

---

## 3. Cryptographic Verification & Seed Analysis

In `database.sql` (lines 124-127), default accounts are seeded as follows:
```sql
INSERT INTO users (id, username, password, full_name, role) VALUES 
(1, 'admin', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Quản Trị Viên Trường', 'admin'),
(2, 'gv_toan', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Thầy Nguyễn Văn Giáo', 'teacher'),
(3, 'gv_van', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Cô Lê Thị Hương', 'teacher');
```

- **Hash Verification**:
  `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG` is the standard bcrypt cost-10 hash of the string `'123456'`.
  This was verified via `tests/verify_m1.php` Test 4.1:
  - `password_verify('123456', $hash) === true`
  - `password_verify('invalid_pwd_test', $hash) === false`
- **Impact of Backdoor Removal**:
  Removing `|| $password === '123456'` will **not break** login for `admin`, `gv_toan`, or `gv_van` when using password `'123456'`, because their stored bcrypt hashes match `'123456'`.
  However, it **will securely reject**:
  - Any wrong password for `admin`, `gv_toan`, `gv_van` (e.g. `'1234567'`, `'admin'`).
  - Password `'123456'` on any newly created user whose password is not `'123456'`.
  - Non-existent accounts.

---

## 4. Architectural Matrix & Concrete Specifications

### 4.1 Authentication & Redirection State Machine

| Request State | Method | Credentials / Session | Action | Target / Outcome | Audit Log Recorded |
|---|---|---|---|---|---|
| Unauthenticated | GET | No session | Render login page | HTTP 200 (Login Form) | None |
| Unauthenticated | POST | `admin` + `123456` | `password_verify()` passes | 302 Redirect to `admin.php` | `Đăng nhập thành công` (user_id=1, username='admin') |
| Unauthenticated | POST | `gv_toan` + `123456` | `password_verify()` passes | 302 Redirect to `index.php` | `Đăng nhập thành công` (user_id=2, username='gv_toan') |
| Unauthenticated | POST | `gv_van` + `123456` | `password_verify()` passes | 302 Redirect to `index.php` | `Đăng nhập thành công` (user_id=3, username='gv_van') |
| Unauthenticated | POST | `admin` + `wrongpwd` | `password_verify()` fails | HTTP 200 + Error Alert | `Đăng nhập thất bại` (user_id=NULL, username='admin') |
| Unauthenticated | POST | `nonexistent` + `123456` | User not found in DB | HTTP 200 + Error Alert | `Đăng nhập thất bại` (user_id=NULL, username='nonexistent') |
| Unauthenticated | POST | Empty username or pass | Input validation fails | HTTP 200 + Error Alert | None / Validation message |
| Authenticated (Admin) | GET | `$_SESSION['role'] === 'admin'` | Auto-redirect | 302 Redirect to `admin.php` | None |
| Authenticated (Teacher) | GET | `$_SESSION['role'] === 'teacher'` | Auto-redirect | 302 Redirect to `index.php` | None |
| Authenticated | GET / POST | `logout.php` | Wipe session & cookie | 302 Redirect to `login.php` | `Đăng xuất` (user_id=X, username='...') |

---

## 5. Proposed File Modifications

Two drop-in replacement files have been prepared and syntax-checked in the agent working directory:
- `D:\New folder\my-web-project\.agents\teamwork\explorer_m2_1\proposed_login.php`
- `D:\New folder\my-web-project\.agents\teamwork\explorer_m2_1\proposed_logout.php`
- Unified patch file: `D:\New folder\my-web-project\.agents\teamwork\explorer_m2_1\auth_overhaul.patch`

### 5.1 Exact Specification for `login.php`

```php
<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Trang đăng nhập bảo mật (Authentication & Access Control)
// =============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

// Nếu người dùng đã đăng nhập từ trước, điều hướng trực tiếp theo vai trò
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // XÁC THỰC BẢO MẬT: Bỏ hoàn toàn backdoor || $password === '123456'
        // Nghiêm ngặt chỉ chấp nhận mật khẩu đã hash bcrypt qua password_verify()
        if ($user && password_verify($password, $user['password'])) {
            // Ngăn ngừa tấn công Session Fixation bằng cách cấp lại session ID mới
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // Ghi nhật ký đăng nhập thành công vào bảng audit_logs
            log_action(
                (int)$user['id'],
                $user['username'],
                'Đăng nhập thành công',
                "Người dùng [{$user['username']}] vai trò [{$user['role']}] đã đăng nhập thành công vào hệ thống."
            );

            // Điều hướng theo phân quyền (RBAC): Admin vào admin.php, Giáo viên vào index.php
            if ($user['role'] === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            // Ghi nhật ký đăng nhập thất bại kèm tên tài khoản đã cố gắng đăng nhập
            log_action(
                null,
                $username,
                'Đăng nhập thất bại',
                "Thử đăng nhập thất bại với tài khoản [$username]."
            );
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác!';
        }
    } else {
        $error = 'Vui lòng nhập đầy đủ thông tin!';
    }
}
?>
```

*(The HTML portion preserves the modern card UI, and updates the demo box to also provide a quick-fill button for `gv_van` alongside `admin` and `gv_toan`).*

### 5.2 Exact Specification for `logout.php`

```php
<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Xử lý đăng xuất an toàn (Secure Session Destruction & Audit Logging)
// =============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

// Trích xuất danh tính trước khi giải phóng bộ nhớ session
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$username = $_SESSION['username'] ?? null;

// Ghi nhật ký đăng xuất vào bảng audit_logs
if ($username !== null) {
    log_action($userId, $username, 'Đăng xuất', "Người dùng [$username] đã đăng xuất khỏi hệ thống.");
}

// Xóa sạch toàn bộ biến session trong bộ nhớ runtime
$_SESSION = [];

// Hủy cookie session phía client trình duyệt nếu cookie được sử dụng
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hủy dữ liệu session lưu trên máy chủ
session_destroy();

// Chuyển hướng an toàn về trang đăng nhập
header('Location: login.php');
exit;
```

---

## 6. Security Analysis & Compliance

1. **OWASP Authentication Compliance**:
   - **No Hardcoded Credentials / Backdoors**: Strictly removed `|| $password === '123456'`.
   - **Robust Hashing**: Bcrypt standard via `password_verify()`.
   - **Generic Failure Messages**: Displays *"Tên đăng nhập hoặc mật khẩu không chính xác!"* for both missing user and bad password, preventing user enumeration.
   - **Parameterized Queries**: All DB queries use prepared statements with placeholders `?`.
2. **Session Security**:
   - `session_regenerate_id(true)` mitigates session fixation.
   - Complete cleanup of session data and cookie expiration prevents session resurrection.
3. **Audit Trail Completeness**:
   - Every login attempt (success or failure) and logout records exact timestamps, IP addresses, targeted username, and descriptive details into MySQL `audit_logs`.

---

## 7. Verification Method

Once implemented by `worker_m2`, the changes must be verified using the following automated procedures:

1. **Syntax Check**:
   ```powershell
   D:\xam\php\php.exe -l login.php
   D:\xam\php\php.exe -l logout.php
   ```
   *Expected*: `No syntax errors detected`.

2. **Milestone 1 Regression Check**:
   ```powershell
   D:\xam\php\php.exe tests/verify_m1.php
   ```
   *Expected*: 12/12 tests PASS.

3. **HTTP Authentication Automated Verification**:
   Send HTTP POST requests to `http://localhost/my-web-project/login.php`:
   - `admin` + `123456` -> Expect HTTP 302 Redirect to `Location: admin.php`.
   - `gv_toan` + `123456` -> Expect HTTP 302 Redirect to `Location: index.php`.
   - `gv_van` + `123456` -> Expect HTTP 302 Redirect to `Location: index.php`.
   - `admin` + `1234567` -> Expect HTTP 200 with error message, 0 redirect.
   - `nonexistent` + `123456` -> Expect HTTP 200 with error message, 0 redirect.
   - Query `audit_logs` table in MySQL to verify entries exist for each test with correct actions and usernames.
