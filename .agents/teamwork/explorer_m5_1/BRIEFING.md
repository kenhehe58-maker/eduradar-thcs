# BRIEFING — 2026-10-17T17:35:30Z

## Mission
Investigate and design the Early-Warning Radar Diagnostic Engine in index.php (3 diagnostic zones, top stat cards with percentages, instant client-side filter tabs, radar table with VnEdu score breakdown, and strict teacher RBAC).

## 🔒 My Identity
- Archetype: explorer
- Roles: teamwork_preview_explorer (M5 Early-Warning Radar Engine Explorer)
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m5_1
- Original parent: 391812a0-32ec-4263-b097-6162669cc4f7
- Milestone: M5

## 🔒 Key Constraints
- Read-only investigation — do NOT implement source code
- Adhere strictly to user request and project specs: no unrequested features, NO Zalo buttons
- Strictly use MySQL / MariaDB via PDO prepared statements (zero SQLite)
- Classify into 3 diagnostic zones:
  - 🔴 Nguy cấp (Critical): avg_score < 3.5 OR ck < 3.0 OR failing both GK and CK (gk < 5.0 && ck < 5.0)
  - 🟡 Cần chú ý (Warning / At Risk): 3.5 <= avg_score < 5.0 OR downward trend (ck < gk - 1.5)
  - 🟢 Ổn định (Stable): avg_score >= 5.0 without critical warning flags
- Output analysis.md and handoff.md in working directory
- Notify parent via send_message

## Current Parent
- Conversation ID: 391812a0-32ec-4263-b097-6162669cc4f7
- Updated: 2026-10-17T17:35:00Z

## Investigation State
- **Explored paths**:
  - `index.php`: Found legacy columns (`m1, m2, p15_1...`), missing subject filter, Zalo button & toast, undefined `PASS_SCORE`.
  - `database.sql`: Schema with tables `users`, `classes`, `teacher_classes`, `students`, `scores`, `audit_logs`.
  - `nhap_diem.php`: Score calculation formula, columns `tx1, tx2, tx3, tx4, gk, ck, avg_score`, teacher assignment verification.
  - `includes/auth.php`: RBAC functions `require_login()`, `require_admin()`, `verify_teacher_assignment()`, `require_teacher_assignment()`.
  - `assets/css/style.css` & `assets/js/main.js`: Style tokens, missing `.chip` styles, legacy Zalo and modal event handlers.
- **Key findings**:
  - Diagnostic rules mapped: Critical conditions take absolute precedence over Warning; Warning takes precedence over Stable.
  - Downward trend `ck < gk - 1.5` requires float rounding: `round($gk - $ck, 1) > 1.5`.
  - Safe handling of NULL scores prevents false-positive alerts on unrated students.
  - Teacher RBAC query requires joining `teacher_classes tc` with `scores sc` on `sc.student_id = s.id AND sc.subject = tc.subject` to prevent cross-subject data leakage.
  - Top stat cards with percentage formula: `round((count / total) * 100, 1)` with division-by-zero safeguard.
  - Client-side filtering combines chip tabs (All, Red, Yellow, Green) and multi-field search (name, code, phone, class, reason).
  - Direct "Vào sổ điểm" link replaces obsolete Zalo copy button.
- **Unexplored areas**:
  - None within explorer_m5_1 scope.

## Key Decisions Made
- Function `evaluateRiskZone()` specifies waterfall priority (Red -> Yellow -> Green -> Incomplete).
- Query pattern binds `teacher_classes.teacher_id = :teacher_id` strictly from session.
- Both `analysis.md` and `handoff.md` finalized and documented.

## Artifact Index
- `progress.md` — Liveness heartbeat and milestone progress
- `BRIEFING.md` — Persistent agent working memory
- `analysis.md` — Comprehensive architectural investigation and design specification
- `handoff.md` — 5-component self-contained hard handoff report
