# DISPATCH — explorer_m5_3 (M5 SaaS UI Redesign & Test Suite Design Explorer)

## 2026-10-17T17:27:00Z
- **Role**: teamwork_preview_explorer (M5 SaaS UI & Test Suite Design Explorer)
- **Working Directory**: D:\New folder\my-web-project\.agents\teamwork\explorer_m5_3
- **Files to Read**:
  - D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md
  - D:\New folder\my-web-project\.agents\teamwork\PROJECT.md
  - D:\New folder\my-web-project\includes\header.php
  - D:\New folder\my-web-project\includes\footer.php
  - D:\New folder\my-web-project\assets\css\style.css
  - D:\New folder\my-web-project\login.php
  - D:\New folder\my-web-project\admin.php
  - D:\New folder\my-web-project\nhap_diem.php
  - D:\New folder\my-web-project\index.php
- **Tasks**:
  1. Inspect the styling across all views (`login.php`, `admin.php`, `nhap_diem.php`, `index.php`, `header.php`, `footer.php`).
  2. Design modern Stitch UI aesthetic:
     - Typography: Google Font `Plus Jakarta Sans`, clean line heights and font weights.
     - Color Tokens: Primary brand indigo/blue, success emerald, warning amber, danger rose/red, neutral slate grays.
     - Cards: subtle borders (`1px solid #e2e8f0`), soft shadows, rounded corners (`rounded-xl`).
     - Role Badges: distinct visual badges for "Quản trị viên" (admin) and "Giáo viên bộ môn" (teacher).
     - Navigation: active tab highlighting in `header.php`, user info display, clean logout button.
  3. Design the automated verification test suite `tests/verify_m5.php`:
     - Test Group 1: Early-Warning classification logic (Nguy cấp, Cần chú ý, Ổn định) against known student score profiles.
     - Test Group 2: Downward trend detection (`ck < gk - 1.5`) and failing GK+CK detection.
     - Test Group 3: Stat counters and percentage calculations accuracy.
     - Test Group 4: Interactive filter tabs functionality and DOM attributes.
     - Test Group 5: Complete Zalo elimination verification (assert 0 occurrences of 'zalo', 'actionModal' in `index.php` and `assets/js/main.js`).
     - Test Group 6: Strict RBAC verification on `index.php` (teachers only see their assigned classes/subjects; admin sees all).
     - Test Group 7: Stitch UI design tokens and stylesheet integrity.
  4. Write `analysis.md` and `handoff.md`, then send a message when done.
