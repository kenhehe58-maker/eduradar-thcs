# BRIEFING — 2026-10-17T11:43:00Z

## Mission
Design the comprehensive 4-Tier test matrix for tests/run_tests.php covering all 18 features from PROJECT.md and all Acceptance Criteria from ORIGINAL_REQUEST.md.

## 🔒 My Identity
- Archetype: explorer
- Roles: E2E Feature Coverage & Scenario Explorer
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_2
- Original parent: 391812a0-32ec-4263-b097-6162669cc4f7
- Milestone: E2E / M6

## 🔒 Key Constraints
- Read-only investigation — do NOT implement production code
- Comprehensive mapping of all 18 features from PROJECT.md into a 4-Tier test matrix
- Concrete test cases for tests/run_tests.php:
  1. Multi-account authentication (admin, gv_toan, gv_van password 123456, wrong password rejection, session rotation)
  2. Strict RBAC & anti-BOLA route guards (admin vs teacher, 403 on unassigned classes/subjects)
  3. Secondary School Class Matrix & deduplicated batch assignment
  4. VnEdu student roster import with deduplication on (class_id, full_name, birthday)
  5. VnEdu spreadsheet gradebook in nhap_diem.php: weighted average, inline saving, OpenXML bulk import/export
  6. Early-warning radar diagnostics in index.php (Red, Yellow, Green zones), 0 Zalo
  7. Audit logging verification in audit_logs for all tested actions
- Write analysis.md and handoff.md in working directory; send message to parent when done

## Current Parent
- Conversation ID: 391812a0-32ec-4263-b097-6162669cc4f7
- Updated: 2026-10-17T11:47:30Z

## Investigation State
- **Explored paths**:
  - `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (R1–R6, Acceptance criteria)
  - `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Features #1–#18, interface contracts)
  - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_1\DISPATCH.md`
  - `D:\New folder\my-web-project\.agents\teamwork\explorer_e2e_3\DISPATCH.md`
  - `D:\New folder\my-web-project\tests\` (verify_m1..m5, challenge_m1..m5)
  - `D:\New folder\my-web-project\includes\` (config.php, db.php, auth.php, xlsx_helper.php, radar_helper.php)
  - `D:\New folder\my-web-project\` (login.php, logout.php, admin.php, nhap_diem.php, index.php, database.sql)
  - `D:\New folder\my-web-project\.agents\teamwork\auditor_m5_it2_1\audit.md`
- **Key findings**:
  - Designed full 4-Tier test matrix with 41 concrete test cases covering all 18 features from PROJECT.md
  - Tier 1: Environment, Schema & Static Health (6 tests: T1.01–T1.06)
  - Tier 2: Multi-Account Auth, Session Rotation & Strict RBAC (14 tests: T2.01–T2.14)
  - Tier 3: Core Functional & Business Workflows (12 tests: T3.01–T3.12)
  - Tier 4: Deduplication, Anti-Tampering & Audit Forensics (9 tests: T4.01–T4.09)
  - Strict anti-facade: All tests use genuine cURL requests to live Apache and genuine PDO queries to MySQL
  - Zero PHP error/warning tolerance enforced across all live HTTP responses
- **Unexplored areas**: None. Complete mapping achieved.

## Key Decisions Made
- Structured the runner into 4 fail-fast tiers: Tier 1 gating prevents cascading errors if DB/Apache is down.
- Implemented dedicated cookie jars per actor (`admin`, `gv_toan`, `gv_van`, `guest`) to avoid session bleed.
- Established deterministic test identifiers (`6D1_TEST`, `gv_test_t3`) with automatic pre/post cleanup for 100% idempotency.
- Mapped all 18 features directly in a traceable matrix with exact SQL assertions and audit log verification.

## Artifact Index
- `BRIEFING.md` — Persistent working memory
- `progress.md` — Heartbeat log
- `analysis.md` — Comprehensive 4-Tier test matrix specification (41 test cases)
- `handoff.md` — 5-component handoff report for orchestrator/worker
