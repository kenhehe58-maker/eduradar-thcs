# BRIEFING — 2026-10-17T05:58:00Z

## Mission
Audit the live environment (XAMPP at D:\xam, PHP at D:\xam\php\php.exe, Apache port 80, MySQL edu_radar on port 3306), inspect schemas, keys, constraints, duplicates, users, and database.sql, and produce comprehensive analysis and handoff reports.

## 🔒 My Identity
- Archetype: explorer
- Roles: explorer, live environment auditor, database auditor
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_survey_2
- Original parent: 391812a0-32ec-4263-b097-6162669cc4f7
- Milestone: survey & audit live environment

## 🔒 Key Constraints
- Read-only investigation — do NOT implement or alter live application code or schema directly
- Use XAMPP PHP executable at D:\xam\php\php.exe
- Test against live MySQL (edu_radar on port 3306) and live Apache (port 80)
- Document all observations, schema structures, foreign keys, unique constraints, and duplicate rows
- Output reports to analysis.md and handoff.md in working directory

## Current Parent
- Conversation ID: 391812a0-32ec-4263-b097-6162669cc4f7
- Updated: 2026-10-17T05:24:19Z

## Investigation State
- **Explored paths**:
  - D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
  - D:\New folder\my-web-project\.agents\teamwork\explorer_survey_2\DISPATCH.md
  - D:\xam\php\php.exe (v8.0.30 CLI, modules pdo_mysql, zip, SimpleXML, etc.)
  - Apache port 80 (Apache 2.4.58 Win64, junction D:\xam\htdocs\my-web-project -> D:\New folder\my-web-project)
  - MySQL MariaDB 10.4.32 on port 3306 (database edu_radar)
  - Tables: users, classes, teacher_classes, students, scores, audit_logs
  - Project code: login.php, admin.php, nhap_diem.php, index.php, logout.php, includes/config.php, includes/db.php, database.sql, database.sqlite
- **Key findings**:
  - Apache, MariaDB, and PHP CLI are fully responsive and correctly mapped.
  - Zero duplicate rows in existing MySQL tables.
  - Critical schema issue: `students` table lacks UNIQUE(class_id, full_name, birthday).
  - Critical auth bypass: `login.php:16` contains hardcoded `|| $password === '123456'` bypass.
  - Critical permission bypass: `nhap_diem.php` permits unassigned teachers to view/edit grades.
  - Discrepancies in `database.sql`: missing `audit_logs`, lingering `attendance`, lacks unique constraint on `students`, only 8 classes instead of 36.
  - XLSX is missing across admin/teacher imports, and Zalo button is still present in `index.php`.
- **Unexplored areas**:
  - None. Audit is complete and comprehensive.

## Key Decisions Made
- Performed complete read-only audit across runtime, network, database schemas, credentials, and codebases.
- Documented all findings in analysis.md and handoff.md.

## Artifact Index
- D:\New folder\my-web-project\.agents\teamwork\explorer_survey_2\analysis.md — detailed audit analysis report
- D:\New folder\my-web-project\.agents\teamwork\explorer_survey_2\handoff.md — 5-component handoff report
