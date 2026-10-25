## 2026-10-17T07:55:00Z
You are explorer_m2_2 (teamwork_preview_explorer).
Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m2_2
Project specification: D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
Original user request: D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md

Milestone 2 Scope: RBAC Middleware (includes/auth.php) & Admin Route Guard (admin.php).
Task:
1. Design includes/auth.php:
   - require_login(): ensures user is logged in, else redirects to login.php.
   - require_admin(): ensures $_SESSION['role'] === 'admin', else logs unauthorized access attempt to audit_logs and issues HTTP 403 / terminates with 0 database changes.
   - current_user(): returns logged-in user array.
   - verify_teacher_assignment(int $teacher_id, int $class_id, string $subject): bool.
   - require_teacher_assignment(int $teacher_id, int $class_id, string $subject): void.
2. Inspect admin.php and detail exact placements for require_admin() at page top and in all POST handlers.
3. Write your analysis and concrete code recommendations to analysis.md and a handoff report to handoff.md.
