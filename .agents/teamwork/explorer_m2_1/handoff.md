# Handoff Report: Milestone 2 Authentication Overhaul (`login.php`, `logout.php`)

**Agent**: `explorer_m2_1` (teamwork_preview_explorer)  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m2_1`  
**Handoff Type**: Hard (Investigation & Design Completed)  

---

## 1. Observation

1. **Backdoor in `login.php` (line 16)**:
   In `D:\New folder\my-web-project\login.php`:
   ```php
   16: if ($user && (password_verify($password, $user['password']) || $password === '123456')) {
   ```
   Directly allows authentication for any database user if the entered password equals `'123456'`, bypassing `password_verify()`.

2. **Incorrect Post-Login Redirect for Teachers (line 28)**:
   In `D:\New folder\my-web-project\login.php`:
   ```php
   25: if ($user['role'] === 'admin') {
   26:     header('Location: admin.php');
   27: } else {
   28:     header('Location: nhap_diem.php');
   29: }
   ```
   `PROJECT.md` lines 14 & 23 and `DISPATCH.md` task 2 explicitly mandate:
   `admin -> admin.php`, `teacher -> index.php`.

3. **Loss of Targeted Username in Failed Login Audit Logs (line 33)**:
   In `D:\New folder\my-web-project\login.php`:
   ```php
   33: logAction('Đăng nhập thất bại', "Thử đăng nhập sai mật khẩu với tài khoản [$username].");
   ```
   Combined with `includes/db.php` lines 103-104:
   ```php
   $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
   $username = $_SESSION['username'] ?? 'Khách';
   log_action($userId, $username, $action, $details);
   ```
   Because `$_SESSION['username']` is not populated on failed login, `logAction()` records `username = 'Khách'` in `audit_logs`, obscuring the targeted account name.

4. **Incomplete Session Teardown in `logout.php` (lines 5-10)**:
   In `D:\New folder\my-web-project\logout.php`:
   ```php
   5: if (isset($_SESSION['username'])) {
   6:     logAction('Đăng xuất', "Tài khoản [{$_SESSION['username']}] đã đăng xuất.");
   7: }
   8: 
   9: session_destroy();
   10: header('Location: login.php');
   ```
   `$_SESSION` superglobal array is not emptied (`$_SESSION = []`), and the client session cookie is not invalidated with an expired `setcookie()`.

5. **Cryptographic Integrity of Seed Hashes in `database.sql` (lines 124-127)**:
   All seed accounts (`admin`, `gv_toan`, `gv_van`) have hash `$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG`, which verifies against `'123456'` via `password_verify()`.

---

## 2. Logic Chain

1. **Step 1 (Backdoor Elimination & Hashing)**:
   From Observation 1 and Observation 5, removing `|| $password === '123456'` and strictly using `password_verify($password, $user['password'])` eliminates the vulnerability of universal bypass on all accounts while preserving valid logins for `admin`, `gv_toan`, and `gv_van` using their legitimate password `'123456'`.
2. **Step 2 (Role Redirection Alignment)**:
   From Observation 2, `index.php` is the teacher's primary landing view (displaying early warning indicators and assigned classes). Redirecting non-admin users to `index.php` directly satisfies `PROJECT.md` line 14 and `DISPATCH.md` task 2.
3. **Step 3 (Forensic Logging Precision)**:
   From Observation 3, invoking `log_action(null, $username, 'Đăng nhập thất bại', ...)` directly passes the attempted username parameter into `audit_logs.username`, providing complete audit trails for security monitoring. On successful login, `log_action((int)$user['id'], $user['username'], 'Đăng nhập thành công', ...)` ensures exact ID and username binding.
4. **Step 4 (Session Lifecycle Hardening)**:
   From Observation 4, calling `session_regenerate_id(true)` upon successful authentication in `login.php` stops session fixation. In `logout.php`, recording user identity before unsetting `$_SESSION = []`, expiring the session cookie, and executing `session_destroy()` achieves complete session destruction.

---

## 3. Caveats

- **Scope Boundary**: `explorer_m2_1` is strictly responsible for `login.php` and `logout.php`. Middleware design (`includes/auth.php`) and `admin.php` route protection are assigned to `explorer_m2_2`. BOLA protection for `nhap_diem.php` and the automated test suite `tests/verify_m2.php` are assigned to `explorer_m2_3`.
- **Pre-Authentication GET Redirect**: `proposed_login.php` includes an optional safe redirect for already authenticated users visiting `login.php` via GET. Automated tests invoking `login.php` via fresh HTTP POST requests (without session cookies) are unaffected.
- **Password Whitespace**: `proposed_login.php` casts `$_POST['password']` to string without trimming, ensuring trailing or special password characters are never altered.

---

## 4. Conclusion

The authentication and session destruction mechanisms have been fully redesigned and verified via syntax check (`php -l`).
Artifacts generated in `D:\New folder\my-web-project\.agents\teamwork\explorer_m2_1\`:
1. `proposed_login.php`: Complete drop-in replacement for `login.php`.
2. `proposed_logout.php`: Complete drop-in replacement for `logout.php`.
3. `auth_overhaul.patch`: Unified diff patch ready for application.
4. `analysis.md`: Detailed architectural and cryptographic analysis.

The implementer (`worker_m2`) can immediately apply these replacements to complete Features #5 & #6 of Milestone 2.

---

## 5. Verification Method

To independently verify the implementation:

1. **Syntax Validation**:
   ```powershell
   D:\xam\php\php.exe -l "D:\New folder\my-web-project\login.php"
   D:\xam\php\php.exe -l "D:\New folder\my-web-project\logout.php"
   ```
   *Expected Output*: `No syntax errors detected`.

2. **Milestone 1 Test Suite Regression**:
   ```powershell
   D:\xam\php\php.exe "D:\New folder\my-web-project\tests\verify_m1.php"
   ```
   *Expected Output*: 12/12 PASS.

3. **HTTP Authentication & Redirection Test Vectors**:
   Execute POST requests to `http://localhost/my-web-project/login.php`:
   - `username=admin&password=123456` -> Returns HTTP 302 with `Location: admin.php`.
   - `username=gv_toan&password=123456` -> Returns HTTP 302 with `Location: index.php`.
   - `username=gv_van&password=123456` -> Returns HTTP 302 with `Location: index.php`.
   - `username=admin&password=wrongpass` -> Returns HTTP 200 with error message, 0 redirect.
   - `username=nonexistent&password=123456` -> Returns HTTP 200 with error message, 0 redirect.

4. **Audit Logs Verification**:
   Query MySQL `edu_radar.audit_logs`:
   ```sql
   SELECT id, user_id, username, action, details FROM audit_logs ORDER BY id DESC LIMIT 5;
   ```
   Verify that:
   - Successful logins record `action = 'Đăng nhập thành công'`, exact `user_id` (1, 2, or 3), and username.
   - Failed logins record `action = 'Đăng nhập thất bại'`, `user_id = NULL`, and the attempted username (not `'Khách'`).
   - Logout records `action = 'Đăng xuất'` with the authenticated user's ID and name.

5. **Invalidation Conditions**:
   - Any login succeeds with `'123456'` for an account whose bcrypt hash does not match `'123456'`.
   - A teacher login redirects to `nhap_diem.php` instead of `index.php`.
   - Failed login records `'Khách'` instead of the attempted username.
