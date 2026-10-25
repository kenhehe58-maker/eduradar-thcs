# BRIEFING — 2026-10-17T05:58:00Z

## Mission
Comprehensive codebase audit of D:\New folder\my-web-project covering files, architecture, auth, RBAC, unrequested features/Zalo, SQLite, and UI styles.

## 🔒 My Identity
- Archetype: explorer
- Roles: investigation, synthesis
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_survey_1
- Original parent: 391812a0-32ec-4263-b097-6162669cc4f7
- Milestone: codebase_audit_and_survey

## 🔒 Key Constraints
- Read-only investigation — do NOT implement source code changes
- Strictly audit codebase, enumerate files, inspect architecture, authentication, routing, RBAC, unrequested features/modals/Zalo, SQLite files, and UI styles
- Write analysis.md and handoff.md in working directory
- Report back to parent via send_message

## Current Parent
- Conversation ID: 391812a0-32ec-4263-b097-6162669cc4f7
- Updated: not yet

## Investigation State
- **Explored paths**:
  - `includes/config.php`
  - `includes/db.php`
  - `database.sql`
  - `database.sqlite`
  - `login.php`
  - `logout.php`
  - `admin.php`
  - `nhap_diem.php`
  - `index.php`
  - `assets/css/style.css`
  - `assets/js/main.js`
  - `sample_students.csv`
  - `sample_vnedu.csv`
  - Live MariaDB `edu_radar` schema & row counts
  - Live XAMPP Apache `http://localhost/my-web-project/`
- **Key findings**:
  1. `login.php:16`: Hardcoded password bypass `|| $password === '123456'`.
  2. `database.sql:77-81`: Dummy password hash fails verification; missing `audit_logs` table; missing `students` UNIQUE constraint.
  3. `nhap_diem.php:46-108`: Complete absence of authorization check; teachers can view/update any class/subject.
  4. `index.php:209-221`: Unrequested Zalo copy button, toast, and message builder. Query cross-joins subjects.
  5. `assets/js/main.js:43-60`: Dead/unrequested `actionModal` code and Zalo clipboard logic.
  6. `database.sqlite` (40KB) and SQLite fallback in `db.php`/`config.php` must be removed.
  7. `scores` table uses legacy columns `m1, m2, p15_1, p15_2, t1_1, gk, ck` instead of VnEdu TT22 `tx1..4, gk, ck`.
  8. Missing Excel (.xlsx) support across admin and gradebook; missing test suite `tests/run_tests.php`.
- **Unexplored areas**: None. Full codebase and database inspected.

## Key Decisions Made
- Structured comprehensive analysis into `analysis.md` across 8 thematic sections.
- Authored 5-component `handoff.md` with explicit observation references, logic chain, caveats, conclusion, and reproduction verification methods.

## Artifact Index
- D:\New folder\my-web-project\.agents\teamwork\explorer_survey_1\BRIEFING.md — Persistent working memory
- D:\New folder\my-web-project\.agents\teamwork\explorer_survey_1\progress.md — Liveness heartbeat
- D:\New folder\my-web-project\.agents\teamwork\explorer_survey_1\analysis.md — Comprehensive audit report
- D:\New folder\my-web-project\.agents\teamwork\explorer_survey_1\handoff.md — 5-component handoff report
