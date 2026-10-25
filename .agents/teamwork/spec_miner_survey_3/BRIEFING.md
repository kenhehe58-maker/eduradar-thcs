# BRIEFING — 2026-10-17T05:54:00Z

## Mission
Extract and document authoritative specifications for VnEdu student/gradebook Excel/CSV formats, calculation formulas, early-warning rules, audit logging schema & triggers, and acceptance test scenarios.

## 🔒 My Identity
- Archetype: teamwork_preview_spec_miner
- Roles: Specification Miner
- Working directory: D:\New folder\my-web-project\.agents\teamwork\spec_miner_survey_3
- Original parent: 391812a0-32ec-4263-b097-6162669cc4f7
- Milestone: survey & specification extraction

## 🔒 Key Constraints
- Read-only: Do NOT implement application code or modify production files.
- Document exact VnEdu student roster import format (.xlsx and .csv), headers, data types, normalization, deduplication key.
- Document exact VnEdu gradebook import/export format (.xlsx and .csv), headers, columns (ĐĐGtx 1..4, ĐĐGgk, ĐĐGck, ĐTBmhk), formulas, rounding rules, upsert key.
- Document early-warning diagnostic logic: thresholds, rules for 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định, diagnostic messages in Vietnamese, explicit confirmation of NO Zalo button/features.
- Document exact audit logging schema and event triggers across all user flows.
- Document all acceptance test scenarios required for tests/run_tests.php.
- Write specifications report to `specifications.md` and handoff report to `handoff.md`.
- Communicate completion via `send_message` to parent `391812a0-32ec-4263-b097-6162669cc4f7`.

## Current Parent
- Conversation ID: 391812a0-32ec-4263-b097-6162669cc4f7
- Updated: 2026-10-17T05:54:00Z

## Task Summary
- **What to extract**: Precise technical specifications for VnEdu file formats, gradebook math, early warning system, audit logging, and automated test suite.
- **Success criteria**: Complete coverage of all 5 specification areas in `specifications.md` and self-contained `handoff.md`.
- **Interface contracts**: `ORIGINAL_REQUEST.md`, database schema `database.sql`, PHP entrypoints (`admin.php`, `nhap_diem.php`, `index.php`, `login.php`).
- **Code layout**: Teamwork directory metadata only (`.agents/teamwork/spec_miner_survey_3/`).

## Key Decisions Made
- Confirmed that standard PHP ZipArchive and SimpleXML are fully available in `D:\xam\php\php.exe` without needing external composer packages.
- Documented exact VnEdu 10-column layout based on Thông tư 22/2021/TT-BGDĐT: ĐĐGtx (1..4) (weight 1), ĐĐGgk (weight 2), ĐĐGck (weight 3), ĐTBmhk (1-decimal rounding).
- Defined unambiguous rules for 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định, with total removal of Zalo features.
- Formalized complete audit log trigger matrix covering 13 distinct user actions with exact detail templates.
- Mapped out all 9 acceptance test scenarios for `tests/run_tests.php`.

## Artifact Index
- `specifications.md` — Complete specification document covering all 5 core requirements, 17 features discovered, and 17 edge cases
- `handoff.md` — 5-component self-contained handoff report
- `progress.md` — Progress heartbeat
- `DISPATCH.md` — Dispatch log

## Loaded Skills
- None
