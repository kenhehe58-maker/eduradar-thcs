# BRIEFING — 2026-10-17T09:55:00Z

## Mission
Orchestrate the complete audit, cleanup, and overhaul of edu_radar PHP+MySQL web application for Vietnamese THCS teachers, ensuring 100% test pass on live XAMPP Apache and MariaDB.

## 🔒 My Identity
- Archetype: orchestrator
- Roles: orchestrator, user_liaison, human_reporter, successor
- Working directory: D:\New folder\my-web-project\.agents\teamwork\orchestrator_1_gen2
- Original parent: 98b4d118-a6bb-450e-994e-a5dec514bfe7
- Original parent conversation ID: 98b4d118-a6bb-450e-994e-a5dec514bfe7

## 🔒 My Workflow
- **Pattern**: Project Orchestration (Dual Track: Implementation + E2E Testing)
- **Scope document**: D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
1. **Decompose**: Decomposed into 5 sequential implementation milestones (M1–M5) + E2E Testing Track.
2. **Dispatch & Execute**:
   - Milestone cycle: Explorer(s) -> Worker -> Reviewers (2) -> Challengers (2) -> Forensic Auditor -> Gate.
   - On Gate pass: update GATE_STATUS.md and mark milestone DONE in PROJECT.md.
3. **On failure**: Retry -> Replace -> Skip -> Redistribute -> Redesign -> Escalate.
4. **Succession**: Self-succeed when needed. Current generation: gen2.
- **Work items**:
  1. Phase 0: Survey & Scope Mapping [DONE]
  2. Milestone 1: Database Overhaul & Data Access Layer [DONE]
  3. Milestone 2: Auth & Strict Server-Side RBAC [DONE]
  4. Milestone 3: Admin Class Matrix & VnEdu Student Import [DONE]
  5. Milestone 4: VnEdu Gradebook Spreadsheet & Excel Import/Export [DONE]
  6. Milestone 5: Early-Warning Radar & SaaS UI Redesign [IN-PROGRESS - Implementation]
  7. E2E Track & Acceptance Testing: tests/run_tests.php [PENDING]
- **Current phase**: Milestone 5 Verification & Gate Check
- **Current focus**: Milestone 5 Verification Panel running in parallel (reviewer_m5_1, reviewer_m5_2, challenger_m5_1, challenger_m5_2, auditor_m5_1)

## 🔒 Key Constraints
- Pure PDO MySQL singleton; ZERO SQLite references or files.
- Strict server-side RBAC (admin vs teacher) and BOLA/IDOR prevention on (class_id, subject).
- Zero-dependency OpenXML (.xlsx) parsing/generation via ZipArchive and SimpleXML (no Composer).
- Zero Zalo buttons, modals, toasts, or scripts.
- DISPATCH-ONLY: Never modify source code directly; require workers and checkers to execute commands.
- MANDATORY AUDIT INTEGRITY: Forensic auditor has binary veto (INTEGRITY VIOLATION = FAIL).
- All tests must run against live XAMPP Apache (http://localhost/my-web-project/) and MariaDB edu_radar.

## Current Parent
- Conversation ID: 98b4d118-a6bb-450e-994e-a5dec514bfe7
- Updated: 2026-10-17T08:22:00Z

## Key Decisions Made
- Milestone 1 GATE PASSED: Clean 6-table schema, pure PDO MySQL, 0 SQLite.
- Milestone 2 GATE PASSED: Backdoor removed, password_verify enforced, session rotation, RBAC 403 route guards, 37 challenges passed, Forensic Audit CLEAN.
- Milestone 3 GATE PASSED: includes/xlsx_helper.php (pure PHP OpenXML), sample files, overhauled admin.php with class matrix, batch checkbox assign, bcrypt teacher creation, student import with dedup, live audit viewer, 28/28 verify_m3, 39/39 challenge_m3, 49/49 challenge_m3_admin_rbac, Forensic Audit CLEAN.
- Milestone 4 GATE PASSED: Modern VnEdu spreadsheet gradebook in nhap_diem.php, 2D keyboard navigation, real-time calculation, transactions, OpenXML import/export, 39/39 verify_m4, 40/40 challenge_m4_score_calc, 35/35 challenge_m4_excel_bola, 181/181 regressions, CLEAN audit.

## Team Roster
| Agent | Type | Work Item | Status | Conv ID |
|-------|------|-----------|--------|---------|
| explorer_m4_1 | teamwork_preview_explorer | M4 Gradebook UI & Ergonomics | completed | b2587439-d2d6-4585-99c4-680d792f23cb |
| explorer_m4_2 | teamwork_preview_explorer | M4 Calculation & Persistence | completed | cff2eddb-8fee-4f9c-aa86-613565e9c755 |
| explorer_m4_3 | teamwork_preview_explorer | M4 Excel I/O & Tests | completed | dc25c575-980e-4a26-a872-39842b59d628 |
| worker_m4 | teamwork_preview_worker | M4 Implementation & verify_m4 | completed | 4ba4dc97-7530-4f91-a38a-e459f42c7867 |
| reviewer_m4_1 | teamwork_preview_reviewer | M4 UI & Ergonomics Review | completed | 2a9a65ab-799e-4a9c-8d88-cfd8b59c11f6 |
| reviewer_m4_2 | teamwork_preview_reviewer | M4 Calc & RBAC Review | completed | e257a499-1798-48d5-a455-3ff212e576ba |
| challenger_m4_1 | teamwork_preview_challenger | M4 Score Calc Challenge | completed | 9146492b-ac3f-4dd4-9496-7acf6da8adfe |
| challenger_m4_2 | teamwork_preview_challenger | M4 Excel & BOLA Challenge | completed | 4e203c96-326a-4300-b870-c908d14f46bb |
| auditor_m4_1 | teamwork_preview_auditor | M4 Forensic Integrity Audit | completed | cd71e260-f517-47c0-bcc3-26dc0e55449e |
| explorer_m5_1 | teamwork_preview_explorer | M5 Early-Warning Radar | completed | 73bd42c2-31bb-41f0-84b5-aba47adcc258 |
| explorer_m5_2 | teamwork_preview_explorer | M5 Zalo Removal & Dead Code | completed | 63c136a1-1807-4f31-bbe6-62a74c31d752 |
| explorer_m5_3 | teamwork_preview_explorer | M5 SaaS UI & Test Design | completed | 5d2d3195-0b10-45e8-a8c0-9159ca101215 |
| worker_m5 | teamwork_preview_worker | M5 Implementation & verify_m5 | completed | 20536a8c-36fe-4fa7-9ba7-289339e62123 |
| reviewer_m5_1 | teamwork_preview_reviewer | M5 Radar & Zalo Review | completed (REQUEST_CHANGES) | cd188e0b-6a3a-45ae-a9e3-5091d6cbab6b |
| reviewer_m5_2 | teamwork_preview_reviewer | M5 SaaS UI & RBAC Review | completed (REQUEST_CHANGES) | bb52848b-a05f-45fb-98e2-66cea35a7690 |
| challenger_m5_1 | teamwork_preview_challenger | M5 Diagnostic Calc Challenge | completed (REQUEST_CHANGES) | e3939ff9-98fc-4959-9cd6-ba2c440be160 |
| challenger_m5_2 | teamwork_preview_challenger | M5 Zalo Purge & UI Challenge | completed (APPROVE) | c2bc431f-6dbf-4ade-af5a-940ed99c3dc8 |
| auditor_m5_1 | teamwork_preview_auditor | M5 Forensic Integrity Audit | completed (INTEGRITY VIOLATION) | eeca0d9e-6cba-4a1f-8c29-76e88ac9600e |
| explorer_m5_it2_1 | teamwork_preview_explorer | M5 Diagnostic Logic & Boundary | completed | e0594439-cacd-48c2-8436-e407c321c1a1 |
| explorer_m5_it2_2 | teamwork_preview_explorer | M5 Shared Helper & DOM Dedup | completed | f333eb98-6ba4-48e1-ab48-5b6fccfbe4ea |
| explorer_m5_it2_3 | teamwork_preview_explorer | M5 Test Suite Integrity & Live HTTP | completed | 2394068c-5e8c-4ceb-b071-a57c405d6cde |
| worker_m5_it2 | teamwork_preview_worker | M5 Remediation Implementation | completed | 01c37925-f1f7-47f9-b31b-6ac56087c7a0 |
| reviewer_m5_it2_1 | teamwork_preview_reviewer | M5 Radar & Live Error Review | completed (APPROVE) | bcd2ab6a-f93c-4e5f-a6d1-34e3dd47bc0c |
| reviewer_m5_it2_2 | teamwork_preview_reviewer | M5 Architecture & UI Review | completed (APPROVE) | 57af8b61-4999-4cbb-81d4-0dc068d67cf7 |
| challenger_m5_it2_1 | teamwork_preview_challenger | M5 Diagnostic Boundary Challenge | completed (APPROVE) | db0fa06e-4ed5-4d93-aa16-c9ca110b5191 |
| challenger_m5_it2_2 | teamwork_preview_challenger | M5 Zalo Purge & Stress Challenge | completed (APPROVE) | 5df70687-9912-4d85-ae06-0c436d85e56a |
| auditor_m5_it2_1 | teamwork_preview_auditor | M5 Forensic Integrity Audit | completed (CLEAN) | 13531543-d696-4de4-8f21-f0e59cc4deb6 |
| explorer_e2e_1 | teamwork_preview_explorer | E2E Harness Architecture | completed | 2c17595f-e1e4-4545-a9e3-3b922d5a24e1 |
| explorer_e2e_2 | teamwork_preview_explorer | E2E Feature Coverage | completed | e2c36d4b-4ce9-4fae-9d64-0c0a87dee89a |
| explorer_e2e_3 | teamwork_preview_explorer | E2E Adversarial Testing | completed | c4525446-f00d-47cc-b340-979707df669b |
| worker_e2e | teamwork_preview_worker | E2E run_tests.php Implementation | in-progress | aaf5b085-5dc6-481f-8e54-8ee3f70d5971 |

## Succession Status
- Succession required: no
- Spawn count: 45 / 128
- Pending subagents: aaf5b085-5dc6-481f-8e54-8ee3f70d5971
- Predecessor: orchestrator_1
- Successor: not applicable

## Active Timers
- Heartbeat cron: 391812a0-32ec-4263-b097-6162669cc4f7/task-758 (*/10 * * * *)

## Artifact Index
- D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md — User request
- D:\New folder\my-web-project\.agents\teamwork\PROJECT.md — Master project specification
- D:\New folder\my-web-project\.agents\teamwork\orchestrator_1_gen2\progress.md — Liveness & progress tracking
- D:\New folder\my-web-project\.agents\teamwork\orchestrator_1_gen2\GATE_STATUS.md — Gate verdicts
- D:\New folder\my-web-project\includes\xlsx_helper.php — Zero-dependency OpenXML engine
- D:\New folder\my-web-project\admin.php — Overhauled Admin portal
- D:\New folder\my-web-project\sample_students.xlsx, sample_gradebook.xlsx — Sample templates
