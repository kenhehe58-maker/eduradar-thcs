## 2026-10-17T06:26:00Z
You are worker_m1 (teamwork_preview_worker).
Working directory: D:\New folder\my-web-project\.agents\teamwork\worker_m1
Project specification: D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
Original user request: D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md

Inputs from Explorers:
- D:\New folder\my-web-project\.agents\teamwork\explorer_m1_1\handoff.md & analysis.md (DDL & seed specifications)
- D:\New folder\my-web-project\.agents\teamwork\explorer_m1_2\handoff.md & analysis.md (config.php, db.php refactoring, SQLite removal)
- D:\New folder\my-web-project\.agents\teamwork\explorer_m1_3\handoff.md & analysis.md (migration execution & verification script)

Your Exclusive Write Ownership:
- D:\New folder\my-web-project\database.sql
- D:\New folder\my-web-project\includes\config.php
- D:\New folder\my-web-project\includes\db.php
- D:\New folder\my-web-project\database.sqlite (deletion)
- D:\New folder\my-web-project\tests\verify_m1.php (verification script)

Mandatory Integrity Warning:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Milestone 1 Tasks:
1. Update D:\New folder\my-web-project\database.sql exactly per explorer_m1_1's design:
   - 6 tables: users, classes, teacher_classes, students, scores, audit_logs.
   - All UNIQUE constraints: UNIQUE(username), UNIQUE(name), UNIQUE(teacher_id, class_id, subject), UNIQUE(class_id, full_name, birthday), UNIQUE(student_id, subject).
   - Foreign keys with ON DELETE CASCADE.
   - scores columns: tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at.
   - Clean seeds: 36 classes (6A1..9C9), test accounts (admin, gv_toan, gv_van) with hash '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', teaching assignments, sample students and scores.
2. Update D:\New folder\my-web-project\includes\config.php per explorer_m1_2's design (pure MySQL, no SQLite).
3. Update D:\New folder\my-web-project\includes\db.php per explorer_m1_2's design:
   - db(): PDO singleton (MySQL, utf8mb4, ERRMODE_EXCEPTION, FETCH_ASSOC, EMULATE_PREPARES => false).
   - log_action(?int $user_id, ?string $username, string $action, string $details = ''): void
   - logAction(string $action, string $details = ''): void (backward-compatible wrapper)
   - Zero SQLite code or functions.
4. Delete D:\New folder\my-web-project\database.sqlite.
5. Apply database.sql to live MariaDB edu_radar using D:\xam\mysql\bin\mysql.exe.
6. Create and run tests/verify_m1.php using D:\xam\php\php.exe verifying all 10 tests pass with 0 failures.
7. Write changes.md and handoff.md in your working directory, and notify parent when done.
