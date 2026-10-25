# BRIEFING — 2026-10-17T08:24:00Z

## Mission
Audit, clean up, and rebuild an existing PHP + MySQL web app that helps Vietnamese lower-secondary (THCS) teachers manage student grades and early warnings on live XAMPP Apache and MySQL.

## 🔒 My Identity
- Archetype: orchestrator
- Roles: orchestrator, user_liaison, human_reporter, successor
- Working directory: D:\New folder\my-web-project\.agents\teamwork\orchestrator_1
- Original parent: parent
- Original parent conversation ID: 98b4d118-a6bb-450e-994e-a5dec514bfe7

## 🔒 My Workflow
- **Pattern**: Project
- **Scope document**: D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
1. **Decompose**: Survey completed. PROJECT.md authored with full Feature Inventory (18 features across M1-M5 and E2E track).
2. **Dispatch & Execute**:
   - M1: PASSED GATE (CLEAN audit, 12/12 verify, 20/20 challenge, 14/14 security).
   - M2: worker_m2 completed implementation (16/16 verify_m2, 12/12 verify_m1). Verification panel running (reviewer_m2_1, reviewer_m2_2, challenger_m2_1, challenger_m2_2, auditor_m2_1).
3. **On failure**:
   - Retry: nudge stuck agent or re-send task
   - Replace: spawn fresh agent with partial progress
   - Skip: proceed without (only if non-critical)
   - Redistribute: split stuck agent's remaining work
   - Redesign: re-partition decomposition
   - Escalate: report to parent (sub-orchestrators only, last resort)
4. **Succession**: Track cumulative spawns (up to 128 total limit).
- **Work items**:
  1. Survey & Architecture [done]
  2. M1: Database Schema & Migration [done]
  3. M2: Auth & Access Control [in verification / gate check]
  4. M3: Admin Matrix & Student Import [pending]
  5. M4: Gradebook Spreadsheet & Excel Import/Export [pending]
  6. M5: Early-Warning Radar & UI Redesign [pending]
  7. M6 / E2E: Testing Suite & Final Multi-Account Verification [pending]
- **Current phase**: 2 (Milestone 2 - Auth & Strict RBAC Verification)
- **Current focus**: Milestone 2 verification panel evaluating auth, session, and BOLA protection

## 🔒 Key Constraints
- NEVER write, modify, or create source code files directly.
- NEVER run build/test commands yourself — require workers to do so.
- NEVER investigate or explore the problem at the code level — dispatch Explorers for technical investigation.
- File editing ONLY for metadata/state files (.md) in .agents/teamwork/ folder.
- Plain PHP + MySQL (edu_radar), PDO with prepared statements. Remove SQLite.
- XAMPP Apache on port 80 (http://localhost/my-web-project/), MySQL on port 3306.
- Strict server-side RBAC: admin vs teacher, no unassigned class/subject access.
- VnEdu standard student list import (.xlsx/.csv) & gradebook import/export (.xlsx/.csv).
- No Zalo button or parent message generator (explicitly excluded).
- Pass 100% of automated multi-account acceptance tests with 0 failures.
- Never reuse a subagent after it has delivered its handoff — always spawn fresh.

## Current Parent
- Conversation ID: 98b4d118-a6bb-450e-994e-a5dec514bfe7
- Updated: 2026-10-17T08:24:00Z

## Key Decisions Made
- Milestone 1 passed gate with CLEAN audit and all tests passing.
- Milestone 2 implemented by worker_m2 (tests/verify_m2.php 16/16 pass).
- Dispatched M2 verification panel (2 Reviewers, 2 Challengers, 1 Forensic Auditor).

## Team Roster
| Agent | Type | Work Item | Status | Conv ID |
|-------|------|-----------|--------|---------|
| worker_m2 | teamwork_preview_worker | M2 Implementation & Route Hardening | completed | 0b3dd320-4c0d-4c26-869f-baa5c5f0fdfc |
| reviewer_m2_1 | teamwork_preview_reviewer | M2 Auth & Session Review | in-progress | f5f35827-baa1-4e30-9e2c-704195b48ba0 |
| reviewer_m2_2 | teamwork_preview_reviewer | M2 RBAC & BOLA Review | in-progress | c7816dcc-6c50-4db4-be51-04c2817ae076 |
| challenger_m2_1 | teamwork_preview_challenger | M2 Auth Adversarial Challenge | in-progress | 76cd4c01-de07-4282-852c-81fc1712be45 |
| challenger_m2_2 | teamwork_preview_challenger | M2 RBAC Adversarial Challenge | in-progress | f072ac4f-ecc1-4509-9d85-e5d0a2d70942 |
| auditor_m2_1 | teamwork_preview_auditor | M2 Forensic Integrity Audit | in-progress | e4582b1b-181b-4e8f-b8f3-6ac7fc3a21ff |

## Succession Status
- Succession required: no
- Spawn count: 21 / 128
- Pending subagents: f5f35827-baa1-4e30-9e2c-704195b48ba0, c7816dcc-6c50-4db4-be51-04c2817ae076, 76cd4c01-de07-4282-852c-81fc1712be45, f072ac4f-ecc1-4509-9d85-e5d0a2d70942, e4582b1b-181b-4e8f-b8f3-6ac7fc3a21ff
- Predecessor: none
- Successor: not applicable

## Active Timers
- Heartbeat cron: task-341 (*/10 * * * *)
- Safety timer: none

## Artifact Index
- D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md — Original User Request
- D:\New folder\my-web-project\.agents\teamwork\PROJECT.md — Master Project Specification
- D:\New folder\my-web-project\.agents\teamwork\orchestrator_1\BRIEFING.md — Persistent working memory
- D:\New folder\my-web-project\.agents\teamwork\orchestrator_1\progress.md — Liveness and step tracking
- D:\New folder\my-web-project\.agents\teamwork\orchestrator_1\plan.md — Execution plan
- D:\New folder\my-web-project\.agents\teamwork\orchestrator_1\GATE_STATUS.md — Milestone gate tracking
