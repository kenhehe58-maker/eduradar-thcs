## Forensic Audit Report

**Work Product**: Milestone 2: Authentication, Password Hashing, Strict RBAC & Audit Logging (`login.php`, `logout.php`, `includes/auth.php`, `admin.php`, `nhap_diem.php`, `tests/verify_m2.php`, MySQL `edu_radar.audit_logs`)  
**Profile**: General Project (Integrity Mode: `development` per `ORIGINAL_REQUEST.md`)  
**Auditor**: `auditor_m2_1`  
**Verdict**: **CLEAN**

---

### Executive Summary

An exhaustive forensic integrity audit was conducted on Milestone 2 deliverables. The audit verified that:
1. `tests/verify_m2.php` executes genuine cURL HTTP requests against the live Apache server (`http://localhost/my-web-project/`) and runs genuine queries against the live MariaDB database (`edu_radar`), without facades, mock assertions, or hardcoded PASS outputs.
2. The authentication logic in `login.php` strictly verifies passwords using `password_verify()` against bcrypt hashes in MySQL; the legacy backdoor bypass `|| $password === '123456'` has been eliminated.
3. Access control in `includes/auth.php`, `admin.php`, and `nhap_diem.php` enforces strict server-side RBAC and BOLA/IDOR prevention, blocking unauthorized requests with HTTP 403 and logging them to `audit_logs`.
4. MySQL table `edu_radar.audit_logs` genuinely records all security-relevant events (`Đăng nhập thành công`, `Đăng nhập thất bại`, `Đăng xuất`, `Truy cập trái phép bị chặn`, etc.) with authentic `user_id`, `username`, `action`, `details`, client `ip_address`, and `created_at` timestamps.

---

### Phase Results

- **Phase 1.1: Hardcoded Output Detection**: **PASS** — No hardcoded test passes, fake assertions, or static result returns found in application or test code.
- **Phase 1.2: Facade & Stub Implementation Detection**: **PASS** — All middleware functions (`require_login`, `require_admin`, `current_user`, `verify_teacher_assignment`, `require_teacher_assignment`) contain real logic querying MySQL via PDO prepared statements and managing real PHP sessions.
- **Phase 1.3: Pre-populated Verification Artifact Detection**: **PASS** — Workspace was scanned for pre-existing log files, test results, or cached tokens; 0 pre-populated artifacts were found.
- **Phase 1.4: Backdoor Elimination**: **PASS** — Grep search across all codebase files confirmed `|| $password === '123456'` is completely removed; `123456` appears only in test fixtures, database seed comments, and client-side form prefill demo helpers.
- **Phase 2.1: PHP Syntax Verification**: **PASS** — `php -l` executed on all 8 files (`login.php`, `logout.php`, `admin.php`, `nhap_diem.php`, `includes/auth.php`, `includes/db.php`, `includes/config.php`, `tests/verify_m2.php`) with 0 syntax errors detected.
- **Phase 2.2: Live Automated Test Execution (`verify_m2.php`)**: **PASS** — Test suite executed against live Apache and MariaDB; all 16 tests passed with exit code 0.
- **Phase 2.3: Regression Verification (`verify_m1.php`)**: **PASS** — Milestone 1 suite executed against live MariaDB; all 12 tests passed with exit code 0.
- **Phase 2.4: Adversarial Stress Test Suite (`challenge_m2.php`)**: **PASS** — Adversarial suite executed covering SQLi, fuzzing, dictionary attacks, session fixation, post-logout replay, and brute-force bursts; all 17 challenges passed with exit code 0.
- **Phase 2.5: Independent Live cURL Probes**: **PASS** — Direct cURL requests to `admin.php` unauthenticated returned HTTP 403; unauthenticated requests to `nhap_diem.php` returned HTTP 302 to `login.php`.
- **Phase 2.6: Audit Trail Authenticity & Live DB Inspection**: **PASS** — Direct MariaDB queries confirmed genuine rows in `edu_radar.audit_logs` matching all performed actions with client IP `::1` or `127.0.0.1` and exact user IDs.
- **Phase 2.7: Dependency & Architecture Audit**: **PASS** — Zero external libraries, zero Composer packages, pure PHP 8.0 + PDO + cURL, zero SQLite remnants.

---

### Evidence

#### 1. PHP Syntax Verification Tool Output
```text
No syntax errors detected in login.php
No syntax errors detected in logout.php
No syntax errors detected in admin.php
No syntax errors detected in nhap_diem.php
No syntax errors detected in includes/auth.php
No syntax errors detected in includes/db.php
No syntax errors detected in includes/config.php
No syntax errors detected in tests/verify_m2.php
```

#### 2. Live Execution Output: `tests/verify_m2.php`
```text
============================================================
  EduRadar Milestone 2: Automated Verification Suite
============================================================

[TEST] 1.1 includes/auth.php exists with required RBAC functions..... [PASS]
[TEST] 1.2 login.php backdoor bypass (|| $password === '123456') removed [PASS]
[TEST] 1.3 logout.php exists with session clearing & audit log....... [PASS]
[TEST] 2.1 Admin login with '123456' succeeds and redirects to admin.php [PASS]
[TEST] 2.2 Teacher logins (gv_toan, gv_van) succeed and write audit logs [PASS]
[TEST] 2.3 Wrong password and non-existent user rejected and logged.. [PASS]
[TEST] 2.4 Logout endpoint clears session and writes audit log....... [PASS]
[TEST] 3.1 Unauthenticated access to admin.php is rejected (HTTP 403/302) [PASS]
[TEST] 3.2 Teacher (gv_toan) accessing GET admin.php is rejected with 403 [PASS]
[TEST] 3.3 Teacher (gv_toan) POST to admin.php is blocked (0 DB changes) [PASS]
[TEST] 3.4 Admin accessing GET admin.php succeeds (HTTP 200)......... [PASS]
[TEST] 4.1 Unauthenticated access to nhap_diem.php redirects to login.php [PASS]
[TEST] 4.2 Assigned teacher (gv_toan on 6A1 Toán) succeeds (HTTP 200) [PASS]
[TEST] 4.3 BOLA: gv_toan viewing unassigned pair (subject or class) is 403 [PASS]
[TEST] 4.4 IDOR: gv_toan tampering ?teacher_id=3 to view Văn returns 403 [PASS]
[TEST] 4.5 BOLA Tampering: POST blocked (403) & Admin inspects (200). [PASS]

============================================================
  Total Tests: 16 | Passed: 16 | Failed: 0
============================================================
```

#### 3. Live Execution Output: `tests/challenge_m2.php` (17/17 Adversarial Challenges)
```text
==========================================================================
  EduRadar Milestone 2: Adversarial Empirical Authentication Challenge
==========================================================================

--- SECTION 1: Backdoor Elimination & Password Verification ---
[BACKDOOR] 1.1 Login with '123456' on non-existent accounts fails (5 varied names) [PASS]
       Observed: All 5 non-existent accounts rejected with HTTP 200
[BACKDOOR] 1.2 Audit logs record exact fake usernames for all failed attempts [PASS]
       Observed: Audit log entry verified: Thử đăng nhập thất bại với tài khoản [ghost_user_1].
[BACKDOOR] 1.3 User with modified/different password hash rejects '123456' [PASS]
       Observed: Custom hash account strictly verified: '123456' rejected, custom pass accepted
[BACKDOOR] 1.4 Seed accounts reject dictionary of wrong passwords....... [PASS]
       Observed: All 16 dictionary attack attempts were rejected

--- SECTION 2: SQL Injection & Input Fuzzing against login.php ---
[SQL_INJECTION] 2.1 Authentication bypass SQLi payloads in username field fail [PASS]
       Observed: 9 SQL injection username payloads safely neutralized
[SQL_INJECTION] 2.2 SQL injection payloads in password field fail....... [PASS]
       Observed: 5 SQL injection password payloads safely rejected
[SQL_INJECTION] 2.3 Audit log safely records SQL injection payloads via prepared statements [PASS]
       Observed: Payload safely recorded without escaping corruption in audit_logs
[FUZZING] 2.4 Empty, whitespace-only, and 10,000-character long inputs handled cleanly [PASS]
       Observed: Extreme input lengths handled with stable HTTP 200 responses

--- SECTION 3: Session Fixation Prevention ---
[SESSION_FIXATION] 3.1 Attacker pre-set session cookie is regenerated upon admin login [PASS]
       Observed: Session ID rotated from [458d33de55316987...] to [67p2sgd2v30s9gm7...], old ID blocked with HTTP 403
[SESSION_FIXATION] 3.2 Session ID is regenerated upon teacher login (gv_toan) [PASS]
       Observed: Teacher session successfully regenerated on authentication

--- SECTION 4: Post-Logout Session Termination & Access Invalidation ---
[POST_LOGOUT] 4.1 Admin post-logout: Old session cookie completely rejected from admin.php [PASS]
       Observed: Admin session destroyed: post-logout GET status 403, POST blocked with 0 DB changes
[POST_LOGOUT] 4.2 Teacher post-logout: Old session cookie rejected from nhap_diem.php & index.php [PASS]
       Observed: Teacher session destroyed: post-logout GET status 302, scores unchanged
[POST_LOGOUT] 4.3 Audit logs verify logout event and subsequent post-logout unauthorized alerts [PASS]
       Observed: Both logout action and subsequent blocked attempts recorded in audit_logs

--- SECTION 5: Brute-Force Burst & Forensic Accountability ---
[BRUTE_FORCE] 5.1 Rapid burst of 25 failed logins handled cleanly with 100% audit accuracy [PASS]
       Observed: Executed 25 failed login attempts in 0.43s; exactly 25 audit records logged; legitimate login healthy

--- SECTION 6: RBAC Cross-Account & BOLA Adversarial Checks ---
[RBAC_BOLA] 6.1 Teacher gv_toan blocked from all unassigned classes & subjects (403) [PASS]
       Observed: All BOLA probes blocked (403 on unassigned, safe fallback on non-positive ID)
[RBAC_BOLA] 6.2 Teacher gv_toan blocked from admin route GET, POST, and query actions (403) [PASS]
       Observed: All admin access attempts by teacher strictly rejected with HTTP 403
[RBAC_BOLA] 6.3 Admin retains full legitimate inspection across all classes and subjects [PASS]
       Observed: Admin legitimately inspects all classes, subjects, and teachers (HTTP 200)

==========================================================================
  Total Challenges: 17 | Passed: 17 | Failed: 0
==========================================================================

All adversarial challenges defeated. Challenger verdict: APPROVE
```

#### 4. Independent Live cURL Verification Evidence
- **Unauthenticated GET `admin.php`**:
```text
HTTP/1.1 403 Forbidden
Date: Sat, 17 Oct 2026 08:27:39 GMT
Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.0.30
Content-Type: text/html; charset=UTF-8

<!DOCTYPE html>
<html lang="vi">
...
<div class="error-badge">MÃ LỖI: HTTP 403 FORBIDDEN</div>
<h1 class="error-title">Truy Cập Bị Từ Chối</h1>
...
```

- **Unauthenticated GET `nhap_diem.php`**:
```text
HTTP/1.1 302 Found
Date: Sat, 17 Oct 2026 08:27:47 GMT
Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.0.30
Location: login.php
```

- **Session ID Rotation on Login (`login.php`)**:
```text
HTTP/1.1 302 Found
Date: Sat, 17 Oct 2026 08:30:01 GMT
Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.0.30
Set-Cookie: PHPSESSID=1smdqmtq455073onfdiafkkhg2; path=/
Location: admin.php
```

#### 5. MySQL `edu_radar.audit_logs` Forensic Evidence
Sample entries from MariaDB showing authentic audit trail captures:
```text
id    user_id username action                      details                                                      ip_address created_at
3588  1       admin    Admin kiểm tra sổ điểm       Admin đang thanh tra sổ điểm lớp ID [1] môn [Toán]...        ::1        2026-10-17 10:30:46
3587  1       admin    Đăng nhập thành công        Người dùng [admin] vai trò [admin] đã đăng nhập...           ::1        2026-10-17 10:30:46
3585  3       gv_van   Đăng nhập thành công        Người dùng [gv_van] vai trò [teacher] đã đăng nhập...        ::1        2026-10-17 10:30:46
3584  2       gv_toan  Đăng nhập thành công        Người dùng [gv_toan] vai trò [teacher] đã đăng nhập...       ::1        2026-10-17 10:30:45
3581  3       gv_van   Truy cập trái phép bị chặn  Giáo viên [gv_van] cố tình giả mạo tham số teacher_id=2     ::1        2026-10-17 10:30:45
3580  3       gv_van   Truy cập trái phép bị chặn  Từ chối quyền giáo viên [POST /my-web-project/nhap_diem.php] ::1        2026-10-17 10:30:45
3579  3       gv_van   Truy cập trái phép bị chặn  Từ chối quyền giáo viên [GET /my-web-project/nhap_diem.php]  ::1        2026-10-17 10:30:44
3577  2       gv_toan  Lưu sổ điểm                 Cập nhật bảng điểm môn [Toán] cho lớp [6A1] (0 học sinh)     ::1        2026-10-17 10:30:44
2877  NULL    admin    Đăng nhập thất bại          Thử đăng nhập thất bại với tài khoản [admin].                ::1        2026-10-17 10:26:35
```

---

### Conclusion

All integrity forensic checks have passed without any integrity violations, facades, backdoors, or mock test results.
**Final Forensic Audit Verdict**: **CLEAN**.
