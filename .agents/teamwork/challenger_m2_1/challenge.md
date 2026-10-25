# Adversarial Challenge Report: Milestone 2 Authentication & Session Security

**Agent**: `challenger_m2_1` (empirical challenger)  
**Roles**: critic, specialist  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\challenger_m2_1`  
**Target Milestone**: Milestone 2 (Authentication & Strict RBAC)  
**Date**: 2026-10-17  
**Verdict**: **APPROVE**  

---

## 1. Challenge Summary

- **Overall Risk Assessment**: **LOW**
- **System Posture**: The authentication and session subsystem implemented by `worker_m2` has successfully withstood comprehensive empirical adversarial testing directly against the live Apache server (`http://localhost/my-web-project/`) and MariaDB database (`edu_radar`).
- **Core Defenses Verified**:
  1. *Backdoor Elimination*: The legacy bypass (`|| $password === '123456'`) is completely removed from `login.php`. No non-existent account, modified account, or mismatched password hash can authenticate using `'123456'`.
  2. *SQL Injection Immunity*: Prepared statements in `login.php`, `includes/auth.php`, and `includes/db.php` neutralized 100% of SQL injection payloads across `username` and `password` fields without SQL syntax errors or data corruption. Payloads are safely recorded into `audit_logs` without payload execution.
  3. *Session Fixation Defense*: `session_regenerate_id(true)` is strictly called on authentication in `login.php`. Pre-authentication session IDs (e.g. attacker-injected cookies) are rotated to fresh IDs, and old pre-auth session IDs are strictly invalidated and denied access to protected endpoints (`admin.php`, `nhap_diem.php`).
  4. *Post-Logout Termination*: `logout.php` completely clears runtime `$_SESSION`, destroys the cookie on the browser, and destroys the server-side session. Replay attacks using old authenticated session cookies are denied access (HTTP 403 / 302) with 0 database modifications.
  5. *Brute-Force & Forensic Audit*: A burst of 25 rapid failed login attempts was absorbed cleanly in under 0.6 seconds with zero 500 errors, 100% recording accuracy in `audit_logs`, and zero disruption to subsequent legitimate user logins.
  6. *RBAC & BOLA Boundaries*: Teacher accounts cannot access `admin.php` (GET, POST, or query actions return HTTP 403), cannot view or submit scores for unassigned classes or subjects (HTTP 403), and cannot spoof `teacher_id` via IDOR parameter tampering (HTTP 403).

---

## 2. Adversarial Challenges & Findings

### [Low] Challenge 1: Non-positive or Non-numeric `class_id` Parameter Fallback
- **Assumption Challenged**: How does `nhap_diem.php` handle malformed or negative class identifiers such as `?class_id=-1` or `?class_id=abc`?
- **Attack Scenario**: An attacker passes `nhap_diem.php?class_id=-1&subject=Toán` hoping to trigger an unhandled SQL exception or leak data across unassigned classes.
- **Empirical Observation**: In `nhap_diem.php`, line 57 checks `isset($_GET['class_id']) && (int)$_GET['class_id'] > 0`. Because `-1 <= 0`, it defaults to the teacher's own first assigned class (`$myClasses[0]['id']`). The page renders HTTP 200 showing strictly the teacher's own assigned class, leaking zero unauthorized data. Unassigned positive IDs (e.g. `class_id=2` or `class_id=9999`) strictly trigger HTTP 403 Forbidden.
- **Blast Radius**: None. The fallback is scoped strictly within the logged-in teacher's assigned classes in `teacher_classes`.
- **Recommendation**: For strict consistency in future milestones, non-positive integer queries could optionally return an explicit HTTP 400 or 403 error page rather than silent fallback, though the current behavior is secure against data leakage.

### [Low] Challenge 2: Client IP Handling in Audit Logging
- **Assumption Challenged**: Does `log_action()` in `includes/db.php` safely capture client IP addresses under HTTP proxy spoofing headers?
- **Attack Scenario**: Client supplies forged `X-Forwarded-For: 192.168.1.100, 10.0.0.1` or arbitrary malformed strings.
- **Empirical Observation**: `includes/db.php` validates forwarded IPs with `filter_var($forwarded, FILTER_VALIDATE_IP)` and truncates to 45 characters (`substr($ip, 0, 45)`), preventing header injection and buffer overflows in the MariaDB `audit_logs.ip_address` column.
- **Blast Radius**: None.

---

## 3. Stress Test Results Matrix (`tests/challenge_m2.php`)

All 17 empirical tests executed directly against `http://localhost/my-web-project/` and live MariaDB `edu_radar`:

| Category | Test Case | Expected Behavior | Actual Behavior | Result |
| :--- | :--- | :--- | :--- | :---: |
| **BACKDOOR** | 1.1 Login with '123456' on 5 fake accounts | HTTP 200 rejection, error message, 0 session | All 5 fake accounts rejected with HTTP 200 | **PASS** |
| **BACKDOOR** | 1.2 Audit logs record exact fake usernames | Record in `audit_logs` with action 'Đăng nhập thất bại' | Log verified: `Thử đăng nhập thất bại với tài khoản [ghost_user_1].` | **PASS** |
| **BACKDOOR** | 1.3 Account with modified bcrypt hash | Rejects '123456', accepts custom password | '123456' rejected; custom password authenticated | **PASS** |
| **BACKDOOR** | 1.4 Dictionary attack against seed accounts | Rejects 16 wrong password variations | All 16 dictionary attacks rejected with HTTP 200 | **PASS** |
| **SQLI** | 2.1 SQLi payloads in username (`' OR 1=1`, UNION, etc.) | Rejection without SQL exception, 0 access | 9 SQLi payloads safely neutralized | **PASS** |
| **SQLI** | 2.2 SQLi payloads in password field | Rejection via `password_verify()` | 5 SQLi password payloads safely rejected | **PASS** |
| **SQLI** | 2.3 SQLi payload stored in `audit_logs` | Safely escaped and recorded via prepared stmt | Payload safely recorded without SQL injection | **PASS** |
| **FUZZING** | 2.4 Empty, whitespace, 10k-character inputs | Clean validation, no crash/DoS | Handled cleanly with stable HTTP 200 responses | **PASS** |
| **FIXATION** | 3.1 Session fixation on Admin login | Session ID rotated, old session blocked (403) | ID rotated from `33c5...` to `dkvc...`, old ID blocked (403) | **PASS** |
| **FIXATION** | 3.2 Session fixation on Teacher login | Session ID rotated upon authentication | Teacher session successfully regenerated | **PASS** |
| **LOGOUT** | 4.1 Admin post-logout access rejection | Old cookie denied on `admin.php` (403/302), 0 DB change | Old session blocked: GET status 403, POST blocked (0 changes) | **PASS** |
| **LOGOUT** | 4.2 Teacher post-logout access rejection | Old cookie denied on `nhap_diem.php` (302), 0 score change | Old session blocked: GET status 302, scores unchanged | **PASS** |
| **LOGOUT** | 4.3 Audit logs verify logout & blocked replays | Logs 'Đăng xuất' and 'Truy cập trái phép bị chặn' | Both actions confirmed in `audit_logs` | **PASS** |
| **BURST** | 5.1 25 rapid failed logins burst | Handled cleanly in < 1s, 25 audit rows, system stable | 25 attempts executed in 0.58s; exactly 25 logs; system healthy | **PASS** |
| **RBAC_BOLA** | 6.1 Teacher accessing unassigned class/subject | Strict HTTP 403 on unassigned; safe fallback on `<=0` | Unassigned subject/class blocked (403); 0 data leaked | **PASS** |
| **RBAC_BOLA** | 6.2 Teacher accessing admin routes | Strict HTTP 403 on GET, POST, query actions | All teacher attempts on `admin.php` return HTTP 403 | **PASS** |
| **RBAC_BOLA** | 6.3 Admin legitimate inspection | HTTP 200 across all classes, subjects, teachers | Full inspection succeeds across all parameters | **PASS** |

**Total Tests**: 17 | **Passed**: 17 | **Failed**: 0 | **Success Rate**: 100%

---

## 4. Unchallenged Areas

- **Excel `.xlsx` Parsing Security**: Handled in Milestone 3 (Admin student roster import) and Milestone 4 (Teacher gradebook spreadsheet import).
- **Early-Warning Radar Algorithm**: Handled in Milestone 5.
- **Frontend CSS/Styling & UI Polish**: Handled in Milestone 5.

---

## 5. Explicit Verdict

### **APPROVE**
Milestone 2 Authentication & Strict RBAC is empirically verified, resilient against adversarial attack vectors, and ready to serve as the secure foundation for Milestone 3.
