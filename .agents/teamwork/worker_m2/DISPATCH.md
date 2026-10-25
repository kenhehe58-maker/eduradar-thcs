## 2026-10-17T08:10:00Z
You are worker_m2 (teamwork_preview_worker).
Working directory: D:\New folder\my-web-project\.agents\teamwork\worker_m2
Project specification: D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
Original user request: D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md

Inputs from Explorers:
- D:\New folder\my-web-project\.agents\teamwork\explorer_m2_1\handoff.md & analysis.md (proposed_login.php, proposed_logout.php)
- D:\New folder\my-web-project\.agents\teamwork\explorer_m2_2\handoff.md & analysis.md (includes/auth.php, admin.php 403 guard)
- D:\New folder\my-web-project\.agents\teamwork\explorer_m2_3\handoff.md & analysis.md (nhap_diem.php BOLA guard, tests/verify_m2.php)

Your Exclusive Write Ownership:
- D:\New folder\my-web-project\login.php
- D:\New folder\my-web-project\logout.php
- D:\New folder\my-web-project\includes\auth.php
- D:\New folder\my-web-project\admin.php
- D:\New folder\my-web-project\nhap_diem.php
- D:\New folder\my-web-project\tests\verify_m2.php

Mandatory Integrity Warning:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Milestone 2 Tasks:
1. Implement D:\New folder\my-web-project\includes\auth.php based on explorer_m2_2 design:
   - require_login(): void (redirects to login.php if not logged in).
   - require_admin(): void (returns HTTP 403 and logs unauthorized access if role !== 'admin').
   - current_user(): ?array.
   - verify_teacher_assignment(int $teacher_id, int $class_id, string $subject): bool.
   - require_teacher_assignment(int $teacher_id, int $class_id, string $subject): void (returns HTTP 403 if unassigned teacher).
2. Update login.php per explorer_m2_1 design:
   - Remove `|| $password === '123456'` backdoor.
   - Strictly use password_verify().
   - Role-based redirect: admin -> admin.php, teacher -> index.php.
   - Log successful/failed logins to audit_logs with exact username and IP.
3. Update logout.php per explorer_m2_1 design:
   - Destroy session cleanly, log to audit_logs, redirect to login.php.
4. Update admin.php per explorer_m2_2 design:
   - Add require_admin() at page top and in all action handlers.
5. Update nhap_diem.php per explorer_m2_3 design:
   - Require login and verify teacher assignment via require_teacher_assignment() on GET and POST.
   - Deny unassigned teachers with HTTP 403 and log unauthorized access.
   - Allow admin to inspect any class/subject.
6. Create tests/verify_m2.php per explorer_m2_3 design and execute via D:\xam\php\php.exe:
   - Verify 16/16 tests pass with 0 failures against live Apache and MySQL.
   - Also run tests/verify_m1.php to verify zero regression.
7. Write changes.md and handoff.md in your working directory, and notify parent when done.
