# Handoff Report — explorer_m5_3 (M5 SaaS UI Redesign & Test Suite Design Explorer)

## 1. Observation
1. **Files Examined**:
   - `D:\New folder\my-web-project\index.php`:
     - Line 75-76: Legacy SQLite columns `sc.m1, sc.m2, sc.p15_1, sc.p15_2, sc.t1_1, sc.gk, sc.ck, sc.avg_score` are queried instead of the MySQL VnEdu schema (`tx1, tx2, tx3, tx4, gk, ck, avg_score, subject`).
     - Line 209-211: Verbatim Zalo copy button: `<button class="btn copy-btn" data-text="<?= htmlspecialchars($s['feedback']) ?>">📋 Copy Tin Nhắn Zalo</button>`.
     - Line 221: Verbatim toast element: `<div id="toast" class="toast">Đã sao chép tin nhắn Zalo!</div>`.
     - Line 19-21: Teacher query filter `WHERE s.class_id IN (SELECT class_id FROM teacher_classes WHERE teacher_id = ?)` does not enforce subject pairing (`AND sc.subject = tc.subject`), creating data leakage potential across subjects within the same class.
   - `D:\New folder\my-web-project\assets\js\main.js`:
     - Line 25-41: Verbatim Zalo copy button event listeners with `navigator.clipboard.writeText` and `#toast`.
     - Line 43-60: Verbatim dead code `const modal = document.getElementById('actionModal');`, `openActionModal(id, name)`, `closeActionModal()`.
   - `D:\New folder\my-web-project\assets\css\style.css`:
     - Contains `:root` design tokens for `--primary` (`#4f46e5`), `--bg-main`, `--card-bg`, etc., but lacks explicit role badge classes (`.role-badge.role-admin`, `.role-badge.role-teacher`), unified top navigation bar styles (`.stitch-header`, `.header-nav`, `.nav-tab`), and filter tabs (`.filter-tabs`, `.filter-tab`).
   - `D:\New folder\my-web-project\includes\`:
     - `includes/header.php` and `includes/footer.php` do not exist yet; each page implements independent HTML document heads and navigation markup.
   - `D:\New folder\my-web-project\tests\`:
     - `verify_m1.php` (12 tests), `verify_m2.php` (16 tests), `verify_m3.php` (28 tests), and `verify_m4.php` (39 tests) all pass with 0 failures when run against `http://localhost/my-web-project/` via `D:\xam\php\php.exe`.
     - Command `D:\xam\php\php.exe tests/verify_m4.php` returned: `Total Tests: 39 | Passed: 39 | Failed: 0` (exit code 0).
     - Command `D:\xam\php\php.exe tests/verify_m3.php` returned: `Total Tests: 28 | Passed: 28 | Failed: 0` (exit code 0).

## 2. Logic Chain
1. *From Observation 1 (`index.php` legacy columns and unlinked subject query)*:
   Because the MySQL schema defines `scores` with columns `(tx1, tx2, tx3, tx4, gk, ck, avg_score, subject)`, querying `sc.m1` causes NULL score retrieval or SQL failures, and omitting `AND sc.subject = tc.subject` causes teacher `gv_toan` to see student scores from other subjects. Therefore, the query must join `teacher_classes tc` directly on `tc.class_id = s.class_id AND tc.subject = sc.subject WHERE tc.teacher_id = ?` for teachers, and provide school-wide data for admin.
2. *From Observation 1 & 2 (Zalo elements and dead modals)*:
   The original requirement R4 strictly mandates: "DO NOT include the Zalo copy button or parent message generator (the user explicitly asked to hold off on the Zalo button)." Grep search shows exact references in `index.php` (lines 210, 221) and `assets/js/main.js` (lines 25, 44-59). Therefore, deleting these lines from both files completely eliminates Zalo and modal dead code with zero side effects.
3. *From Observation 3 & 4 (Missing header/footer shells and role badge classes)*:
   Implementing `includes/header.php` and `includes/footer.php` with Stitch UI styling (`Plus Jakarta Sans`, active navigation highlighting, user info, role badges for `Quản trị viên` and `Giáo viên bộ môn`, and clean logout button) unifies the visual aesthetic across `index.php` and future views while eliminating duplicate head markup.
4. *From Observation 5 (Existing test suites passing 100%)*:
   Existing test suites `verify_m3.php` and `verify_m4.php` verify class matrix, batch assignment, student import, VnEdu 11-column spreadsheet table, and score calculations. New M5 UI changes must preserve table inputs, class names, and DOM identifiers used by prior milestones to avoid regressions.
5. *From Dispatch Task 3*:
   The new test suite `tests/verify_m5.php` must cover 7 distinct groups:
   (1) Early-warning classification logic; (2) Downward trend (`ck < gk - 1.5`) & failing GK+CK; (3) Stat counters and percentages; (4) Interactive filter tabs & DOM attributes; (5) Complete Zalo elimination; (6) Strict RBAC on `index.php`; (7) Stitch UI styling tokens.
   A 42-test automated suite using `TestClient` (cURL) against `http://localhost/my-web-project/` and MySQL `edu_radar` fulfills every requirement with zero dependencies.

## 3. Caveats
- `nhap_diem.php` currently has custom toolbar and sidebar styles that passed 39/39 tests in `verify_m4.php`. When applying the Stitch UI shell to `nhap_diem.php` or `admin.php`, the implementer should ensure all input IDs (`scores[id][tx1..ck]`), classes (`score-input`, `group-tx`, `group-gk`, `group-ck`), and 2D navigation attributes (`data-row`, `data-col`) remain unmodified.
- No source code in `D:\New folder\my-web-project\` was modified during this investigation (strictly adhering to read-only explorer constraints). All changes are proposed and ready for `worker_m5`.

## 4. Conclusion
The M5 investigation and design is complete. The architectural specifications and complete code draft for `tests/verify_m5.php` (42 test assertions) are fully documented in `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_3\analysis.md`. The design cleanly removes 100% of Zalo and modal dead code, establishes the Stitch UI aesthetic (tokens, header navigation, role badges, card elevation), resolves teacher subject RBAC, and specifies a deterministic early-warning classification engine.

## 5. Verification Method
To independently verify this report and run the test suite once implemented by worker:
1. Inspect the detailed design and verification code in:
   `D:\New folder\my-web-project\.agents\teamwork\explorer_m5_3\analysis.md`
2. Run the existing milestone verification suites to ensure baseline stability:
   - `D:\xam\php\php.exe tests/verify_m3.php` -> Expect 28/28 `[PASS]`.
   - `D:\xam\php\php.exe tests/verify_m4.php` -> Expect 39/39 `[PASS]`.
3. Once worker implements `tests/verify_m5.php` and M5 views, run:
   - `D:\xam\php\php.exe tests/verify_m5.php` -> Expect 42/42 `[PASS]` across all 7 test groups.
   - Run grep commands to verify 0 occurrences of 'zalo' and 'actionModal':
     - `git grep -i "zalo" index.php assets/js/main.js` -> 0 matches.
     - `git grep "actionModal" index.php assets/js/main.js` -> 0 matches.
