# DISPATCH — explorer_m5_1 (M5 Early-Warning Radar Engine Explorer)

## 2026-10-17T17:27:00Z
- **Role**: teamwork_preview_explorer (M5 Early-Warning Radar Engine Explorer)
- **Working Directory**: D:\New folder\my-web-project\.agents\teamwork\explorer_m5_1
- **Files to Read**:
  - D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
  - D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
  - D:\New folder\my-web-project\index.php
  - D:\New folder\my-web-project\nhap_diem.php
  - D:\New folder\my-web-project\includes\auth.php
  - D:\New folder\my-web-project\includes\db.php
- **Tasks**:
  1. Inspect existing `index.php` and analyze the current dashboard logic.
  2. Design the Early-Warning Radar Diagnostic Engine:
     - 🔴 **Nguy cấp (Critical)**: `avg_score < 3.5` OR `ck < 3.0` OR failing both GK and CK (`gk < 5.0 && ck < 5.0`). Vietnamese diagnostic reasons.
     - 🟡 **Cần chú ý (Warning / At Risk)**: `3.5 <= avg_score < 5.0` OR downward trend (`ck < gk - 1.5`).
     - 🟢 **Ổn định (Stable)**: `avg_score >= 5.0` without critical warning flags.
  3. Design top stat counter cards (Tổng số học sinh, 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định) with percentages.
  4. Design interactive filter tabs / buttons (Tất cả, 🔴 Nguy cấp, 🟡 Cần chú ý, 🟢 Ổn định) with live client-side row filtering.
  5. Design student radar table with student code, name, birthday, current scores (`tx1..tx4, gk, ck, avg_score`), status pill badge, diagnostic reason tag, and direct "Vào sổ điểm" link.
  6. Ensure teacher RBAC: only display classes and subjects assigned to current teacher (or all for admin).
  7. Write `analysis.md` and `handoff.md`, then send a message when done.
