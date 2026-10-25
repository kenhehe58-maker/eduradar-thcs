# Execution Plan — Project Orchestrator (orchestrator_1)

## Objective
Audit, clean up, and rebuild the Vietnamese THCS student grade and early warning PHP + MySQL web application running on live XAMPP Apache (`http://localhost/my-web-project/`) and MySQL (`edu_radar`). Strict requirement conformance, no duplicate data, clear role separation, and 100% automated multi-account verification.

## Phase 0: Survey & Scope Mapping
- Dispatch 3 Explorers / Spec Miners concurrently:
  - **Explorer 1**: Existing codebase audit (PHP files, routing, session handling, UI components, dead code, leftover SQLite files).
  - **Explorer 2**: Live MySQL (`edu_radar`) database schema, current table structures, indexes, foreign keys, duplicate rows, and XAMPP environment check.
  - **Spec Miner 3**: VnEdu export/import formats (student list `.xlsx`/`.csv`, gradebook `.xlsx`/`.csv` structure, score calculation formula, warning criteria, required test scenarios).
- Synthesize findings and produce `PROJECT.md` (Feature Inventory, Architecture, Milestones, Interface Contracts, Code Layout).

## Phase 1: Dual Track Decomposition & Dispatch
- **Track A (Implementation Track)**:
  - **Milestone 1**: Database Schema Overhaul, Deduplication & Migration (`database.sql`, clean tables with UNIQUE constraints, audit log triggers/structure, seed data).
  - **Milestone 2**: Authentication, Security & RBAC (`login.php`, `logout.php`, auth middleware/functions, password hashing, strict teacher vs admin gating).
  - **Milestone 3**: Admin Module (`admin.php` - Grade 6-9 matrix, batch checkbox assignments, VnEdu student list import `.xlsx`/`.csv`, direct gradebook inspection button, audit log viewer).
  - **Milestone 4**: VnEdu Gradebook Spreadsheet (`nhap_diem.php` - spreadsheet UI, 4 TX columns + GK + CK + TBmhk auto-calculation, keyboard navigation, sample file, `.xlsx` bulk import/export).
  - **Milestone 5**: Early-Warning Radar & Design Polish (`index.php` - Red/Yellow/Green classifications with diagnostic reasons, NO Zalo button, Stitch UI cohesive styling across all pages).
- **Track B (E2E Testing Track)**:
  - Comprehensive CLI test suite (`tests/run_tests.php` via `D:\xam\php\php.exe`) exercising all requirements R1-R6 against live Apache (`http://localhost/my-web-project/`) and MySQL (`edu_radar`).

## Phase 2: Verification, Adversarial Hardening & Final Acceptance
- Forensic Audit (`teamwork_preview_auditor`) verifying zero cheating, real logic, no hardcoded responses.
- Reviewers and Challengers verifying 100% test pass with 0 failures.
- Handover summary to user and parent agent.
