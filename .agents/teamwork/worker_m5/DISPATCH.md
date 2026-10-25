# DISPATCH — worker_m5 (Milestone 5 Implementation Worker)

## 2026-10-17T17:37:00Z
- **Role**: teamwork_preview_worker (Milestone 5 Implementation Worker)
- **Working Directory**: D:\New folder\my-web-project\.agents\teamwork\worker_m5
- **Files to Read**:
  - D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
  - D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
  - D:\New folder\my-web-project\.agents\teamwork\explorer_m5_1\handoff.md and analysis.md
  - D:\New folder\my-web-project\.agents\teamwork\explorer_m5_2\handoff.md and analysis.md
  - D:\New folder\my-web-project\.agents\teamwork\explorer_m5_3\handoff.md and analysis.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Implement Milestone 5:
1. **Early-Warning Radar in `index.php`**:
   - Implement deterministic diagnostic logic:
     - 🔴 **Nguy cấp (Critical)**: `avg_score < 3.5` OR `ck < 3.0` OR failing both GK and CK (`gk < 5.0 && ck < 5.0`). Vietnamese diagnostic reasons.
     - 🟡 **Cần chú ý (Warning / At Risk)**: `3.5 <= avg_score < 5.0` OR downward trend (`ck < gk - 1.5`).
     - 🟢 **Ổn định (Stable)**: `avg_score >= 5.0` without critical warning flags.
   - Stat cards with percentages (Tổng số, 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định) with safe divide-by-zero handling.
   - Interactive client-side filter chips (Tất cả, 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định) and instant search.
   - 12-column radar table with student codes, names, birthdays, scores (`tx1..tx4, gk, ck, avg_score`), status badges, diagnostic reasons, and "Vào sổ điểm" link (`nhap_diem.php?class_id=...&subject=...`).
   - Strict teacher RBAC: only display classes and subjects assigned in `teacher_classes` (admin sees all).
2. **Complete Zalo Purge & Dead Code Removal**:
   - Remove `buildFeedback()` in `index.php`.
   - Remove Zalo button column `<td><button class="btn copy-btn">📋 Copy Tin Nhắn Zalo</button></td>` and column header.
   - Remove Zalo toast `<div id="toast" class="toast">...</div>`.
   - In `assets/js/main.js`: completely delete Zalo clipboard copy, toast trigger, and dead `actionModal` code (`openActionModal`, `closeActionModal`, window click listener). Harden search/filter.
3. **SaaS UI Redesign (Stitch UI Style)**:
   - Enhance `assets/css/style.css`: Google Font `Plus Jakarta Sans`, design tokens, soft multi-layer shadows, rounded corners, clean table styles.
   - Distinct role badges in `includes/header.php`: `.role-badge.role-admin` ("Quản trị viên") and `.role-badge.role-teacher` ("Giáo viên bộ môn").
   - Navigation: active tab highlighting in `header.php`, user display name, role badge, clean logout button.
4. **Automated Verification Test Suite `tests/verify_m5.php`**:
   - Implement `tests/verify_m5.php` covering all 7 test groups (42 assertions) testing live Apache (`http://localhost/my-web-project/`) and MariaDB (`edu_radar`).
   - Run via `D:\xam\php\php.exe tests/verify_m5.php` ensuring 100% pass (0 failures).
5. **Regression Verification**:
   - Run `tests/verify_m1.php`, `tests/verify_m2.php`, `tests/verify_m3.php`, `tests/verify_m4.php`, `tests/challenge_m2_rbac.php`.
   - Ensure 0 regressions.
6. Write `changes.md` and `handoff.md`, then send a message when done.
