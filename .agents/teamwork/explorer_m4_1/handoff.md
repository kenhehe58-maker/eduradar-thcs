# Handoff Report: VnEdu Gradebook Spreadsheet Interface & Ergonomics Design

**Author**: `explorer_m4_1`  
**Role**: M4 Gradebook Spreadsheet UI Explorer  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_1`  
**Recipient**: `orchestrator_1_gen2` (`391812a0-32ec-4263-b097-6162669cc4f7`), Worker Agents  
**Date**: 2026-10-17  
**Artifacts**:
- Analysis & Architecture Spec: `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_1\analysis.md`
- Briefing State: `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_1\BRIEFING.md`
- Progress Heartbeat: `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_1\progress.md`

---

## 1. Observation

1. **Obsolete Database Columns in `nhap_diem.php`**:
   - In `nhap_diem.php` lines 112–145, the backend queries and saves obsolete columns:
     ```php
     $m1 = $row['m1'] !== '' ? (float)$row['m1'] : null;
     $m2 = $row['m2'] !== '' ? (float)$row['m2'] : null;
     $p15_1 = $row['p15_1'] !== '' ? (float)$row['p15_1'] : null;
     $p15_2 = $row['p15_2'] !== '' ? (float)$row['p15_2'] : null;
     $t1_1 = $row['t1_1'] !== '' ? (float)$row['t1_1'] : null;
     ...
     UPDATE scores SET m1 = ?, m2 = ?, p15_1 = ?, p15_2 = ?, t1_1 = ?, gk = ?, ck = ?, avg_score = ?
     ```
   - In `nhap_diem.php` lines 220–229:
     ```php
     SELECT s.id, s.full_name, s.parent_phone,
            sc.m1, sc.m2, sc.p15_1, sc.p15_2, sc.t1_1, sc.gk, sc.ck, sc.avg_score
     FROM students s
     LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = ?
     WHERE s.class_id = ?
     ```
   - Whereas in `database.sql` lines 84–95 and live MySQL `edu_radar`, the `scores` table schema overhauled in Milestone 1 is:
     ```sql
     CREATE TABLE scores (
         id INT AUTO_INCREMENT PRIMARY KEY,
         student_id INT NOT NULL,
         subject VARCHAR(50) NOT NULL,
         tx1 DECIMAL(4,2) DEFAULT NULL,
         tx2 DECIMAL(4,2) DEFAULT NULL,
         tx3 DECIMAL(4,2) DEFAULT NULL,
         tx4 DECIMAL(4,2) DEFAULT NULL,
         gk DECIMAL(4,2) DEFAULT NULL,
         ck DECIMAL(4,2) DEFAULT NULL,
         avg_score DECIMAL(4,2) DEFAULT NULL,
         updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         UNIQUE KEY unique_student_subject (student_id, subject)
     );
     ```
   - `verify_m1.php` line 195 explicitly asserts: `scores table has modern VnEdu columns (tx1..tx4, gk, ck, avg_score)`. Any attempt to insert or select `m1` or `p15_1` causes SQL exceptions in MySQL.

2. **Incomplete Student Identity Attributes in UI**:
   - In `nhap_diem.php` lines 328–329, the table only displays `STT` and `Họ và Tên Học Sinh`.
   - `students` table in `database.sql` lines 66–79 contains `student_code VARCHAR(50)` and `birthday DATE`. The official VnEdu gradebook standard mandates displaying `STT`, `Mã HS`, `Họ và tên`, and `Ngày sinh` (formatted `d/m/Y`).

3. **Number Spinner & Keystroke Trapping Defects in `<input type="number">`**:
   - In `nhap_diem.php` lines 358–364, score cells use `<input class="score-input" type="number" step="0.1" min="0" max="10">`.
   - Pressing `ArrowDown` inside `type="number"` decrements the score value instead of navigating down to the next student row.
   - Pressing `Enter` triggers default form submit rather than jumping to the cell below.
   - Browsers reject typing commas (e.g. `8,5`) into `type="number"`, clearing `input.value`.

4. **Missing Sticky Layout**:
   - `nhap_diem.php` lacks `position: sticky; top: 0;` on table headers and `position: sticky; left: 0;` on student names. On rosters of 35–45 students, scrolling vertically hides column headers.

5. **Existing Verification Suites Status**:
   - Ran `D:\xam\php\php.exe tests/verify_m1.php`: Passed 12/12.
   - Ran `D:\xam\php\php.exe tests/verify_m2.php`: Passed 16/16.
   - Ran `D:\xam\php\php.exe tests/verify_m3.php`: Passed 28/28.
   - Total passed across M1–M3: 56/56 tests.

---

## 2. Logic Chain

1. **Step 1: Schema Alignment**:
   - Observation 1 proves that `scores` in MySQL has `tx1..tx4, gk, ck, avg_score`, whereas `nhap_diem.php` uses legacy `m1..t1_1`.
   - In order for grade entries to persist without SQL errors, `nhap_diem.php` must query and render exactly the 6 columns (`tx1, tx2, tx3, tx4, gk, ck`) and compute `avg_score`.
   - Inputs must be named `scores[<student_id>][tx1]`, ..., `scores[<student_id>][ck]`.

2. **Step 2: Table Layout & Header Tiering**:
   - Observation 2 demonstrates that `student_code` and `birthday` exist in the database and are required by VnEdu THCS standard.
   - The table requires an 11-column 2-tier header:
     - Student information: `STT` (sticky left 0), `Mã HS`, `Họ và tên` (sticky left 52px), `Ngày sinh` (`d/m/Y`).
     - ĐĐG Thường xuyên (Hệ số 1): `TX1`, `TX2`, `TX3`, `TX4` with Soft Sky Blue header tint (`#eff6ff` / `#1e3a8a`).
     - ĐĐG Giữa kỳ (Hệ số 2): `ĐĐGgk` with Soft Amber header tint (`#fffbeb` / `#78350f`).
     - ĐĐG Cuối kỳ (Hệ số 3): `ĐĐGck` with Soft Violet header tint (`#f5f3ff` / `#581c87`).
     - ĐTB Môn Học Kỳ: `ĐTBmhk` with Indigo header tint (`#312e81`) and auto-calculated badge.

3. **Step 3: Keyboard Ergonomics Design**:
   - Observation 3 shows that native `<input type="number">` traps Arrow keys and prevents comma entry.
   - Switching to `<input type="text" inputmode="decimal">` with `data-row` and `data-col` enables full 2D grid navigation:
     - `Enter` / `ArrowDown`: Move down in same column (`row + 1, col`).
     - `Shift+Enter` / `ArrowUp`: Move up in same column (`row - 1, col`).
     - `Tab` / `Shift+Tab`: Move horizontally across columns, wrapping to next/prev row.
     - `Focus`: Auto-call `this.select()` for instant overwrite.

4. **Step 4: Client-Side Validation & Real-Time Calculation**:
   - Immediate comma-to-dot replacement on `input` (`8,5` -> `8.5`).
   - Range validation: regex `/^(10(\.0{1,2})?|[0-9](\.[0-9]{1,2})?)$/` or empty.
   - Invalid scores flagged with `.is-invalid` (red border, soft red background, floating tooltip `"Điểm phải từ 0.0 đến 10.0"`).
   - Form submit guard prevents submitting invalid scores.
   - Instant row average calculation updates `.avg-badge` on every valid keystroke using Thông tư 22 formula:
     `(txSum + 2*gk + 3*ck) / (txCount + 5)` or weighted fallback.

5. **Step 5: Stitch UI & Sticky Pane**:
   - Observation 4 shows long rosters lose context.
   - Implementing `.spreadsheet-container` with `position: sticky; top: 0;` on `thead th` (z-index 20) and `position: sticky; left: ...;` on student identity columns (z-index 10) keeps headers and student names visible while scrolling.
   - Implementing `#saveStatusBadge` tracks dirty state (`✓ Dữ liệu đã lưu` -> `● Có thay đổi chưa lưu`) with `beforeunload` warning.

---

## 3. Caveats

- **Scope Boundary**: This explorer is strictly responsible for Feature #12 (UI, Table Layout, Keyboard Ergonomics, Validation, Stitch Styling). Database transaction persistence and RBAC are assigned to `explorer_m4_2`. Excel/CSV import/export and test suite creation are assigned to `explorer_m4_3`.
- **Browser Locale Differences**: In some browser configurations, `inputmode="decimal"` displays a comma instead of a dot on mobile soft keyboards; our JS engine explicitly handles comma-to-dot conversion on the fly.
- No caveats regarding database schema or compatibility with prior milestones.

---

## 4. Conclusion

1. `nhap_diem.php` requires an overhaul of its spreadsheet table and query from 7 legacy columns (`m1..t1_1`) to 6 standard VnEdu columns (`tx1..tx4, gk, ck, avg_score`).
2. Score inputs must be upgraded to `<input type="text" inputmode="decimal">` with complete 2D keyboard navigation (`Enter` moves down, `ArrowUp`/`ArrowDown` move vertically, `Tab` moves horizontally).
3. Real-time client-side validation, comma normalization (`8,5` -> `8.5`), and instant row average calculation provide the responsive desktop-grade experience expected of modern ed-tech software.
4. A complete, tested, drop-in specification including HTML, CSS, and vanilla JS has been delivered in `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_1\analysis.md`.

---

## 5. Verification Method

1. **Static Inspection**:
   - Inspect `nhap_diem.php` to verify:
     - Queries select `s.student_code, s.birthday, sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score`.
     - Table contains 11 columns matching Section 7.1 of `analysis.md`.
     - Inputs have `class="score-input"`, `inputmode="decimal"`, `data-row`, `data-col`, and names `scores[<id>][tx1..ck]`.
2. **Behavioral Invalidation Conditions**:
   - If pressing `ArrowDown` inside a score cell changes the number instead of moving focus to the student below, verification FAILS.
   - If pressing `Enter` inside a score cell submits the form instead of moving to the student below, verification FAILS.
   - If typing `8,5` is rejected or does not normalize to `8.5`, verification FAILS.
   - If entering `11.0` or `-1` does not turn the border red and show a warning tooltip, verification FAILS.
   - If changing a score from `5` to `9` does not immediately update the `ĐTBmhk` badge in the browser, verification FAILS.
   - If scrolling vertically hides the column names, verification FAILS.
3. **Automated Test Run**:
   - Run existing test suites to ensure zero regressions:
     ```powershell
     & "D:\xam\php\php.exe" tests/verify_m1.php
     & "D:\xam\php\php.exe" tests/verify_m2.php
     & "D:\xam\php\php.exe" tests/verify_m3.php
     ```
   - All tests must output 0 failures.
