# Milestone 4 Quality & Adversarial Review Report

**Reviewer**: `reviewer_m4_1`  
**Role**: Reviewer & Critic (UI & Ergonomics Specialist)  
**Date**: 2026-10-17  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\reviewer_m4_1`  
**Target**: Milestone 4 VnEdu Spreadsheet Gradebook (`nhap_diem.php`, `includes/xlsx_helper.php`, `assets/css/style.css`, `tests/verify_m4.php`)  

---

## 1. Review Summary

**Verdict**: **`APPROVE`**  
**Overall Risk Assessment**: **LOW**  
**Integrity Audit**: **CLEAN (0 integrity violations found)**  

Milestone 4 successfully implements a high-craft, VnEdu-standard spreadsheet gradebook in `nhap_diem.php` that adheres to Vietnamese educational grading regulations (Thông tư 22/2021/TT-BGDĐT), incorporates desktop-grade 2D keyboard ergonomics, implements real-time client-side validation and average calculation, guarantees transactional database persistence with strict anti-BOLA protection, provides zero-dependency OpenXML `.xlsx` and `.csv` import/export, and seamlessly aligns with the project's Stitch UI design system.

---

## 2. Review Dimensions & Detailed Evaluation

### 2.1 11-Column 2-Tier Sticky Spreadsheet Layout
- **Structure**: The table comprises 11 discrete columns arranged across a 2-tier sticky header:
  1. `STT` (Sticky col 1, `left: 0`, width: 50px, rowspan 2)
  2. `Mã HS` (Column 2, monospace font, rowspan 2)
  3. `Họ và Tên Học Sinh` (Sticky col 2, `left: 50px`, min-width: 200px, rowspan 2)
  4. `Ngày Sinh` (Column 4, formatted `d/m/Y`, monospace font, rowspan 2)
  5. `ĐĐG THƯỜNG XUYÊN (HỆ SỐ 1)` (Tier 1 super-group header, colspan 4), subdivided into Tier 2 columns `TX1`, `TX2`, `TX3`, `TX4`
  6. `GIỮA KỲ` (`ĐĐGgk` - Hệ số 2, rowspan 2)
  7. `CUỐI KỲ` (`ĐĐGck` - Hệ số 3, rowspan 2)
  8. `ĐTBmhk` (Điểm trung bình môn học kỳ - Tự động, rowspan 2)
- **Sticky Stacking Context**: 
  - Header tier 1: `position: sticky; top: 0; z-index: 20;`
  - Header tier 2: `position: sticky; top: 41px; z-index: 20;`
  - Body identity columns: `position: sticky; left: 0 / 50px; z-index: 10;`
  - Header-column intersection (STT & Name th): `z-index: 30;`
  - Result: No bleed-through or clipping during simultaneous vertical and horizontal scrolling inside `.spreadsheet-container` (`max-height: 70vh; overflow: auto;`).

### 2.2 2D Keyboard Navigation Ergonomics
- **No Number-Spinner Traps**: All score inputs use `<input type="text" inputmode="decimal" maxlength="4" ...>` instead of native `<input type="number">`. This eliminates browser stepper traps, allows free typing of decimal points, and prevents up/down arrow keys from inadvertently altering numeric values.
- **Directional Navigation**:
  - `Enter` or `ArrowDown`: Moves down to the exact same column for the next student (`focusCell(row + 1, col)`).
  - `Shift+Enter` or `ArrowUp`: Moves up to the exact same column for the previous student (`focusCell(row - 1, col)`).
  - `Tab`: Moves horizontally across columns (`col 0..5`); wraps from column 5 of row $N$ to column 0 of row $N+1$.
  - `Shift+Tab`: Moves backward across columns with reverse row wrapping.
- **Auto-Select on Focus**: Upon focus, `this.select()` is triggered, allowing rapid score entry where typing immediately replaces prior values without requiring backspace/deletion.

### 2.3 Real-Time Client-Side Validation & Comma Normalization
- **Vietnamese Comma-to-Dot Normalization**: When a user types `8,5`, the input listener immediately converts `,` to `.`, ensuring instant validity.
- **Range & Format Validation**: Regex `/^(10(\.0{1,2})?|[0-9](\.[0-9]{1,2})?)$/` and boundary checks enforce $0.0 \le \text{score} \le 10.0$ or empty string.
- **Visual Error Feedback**: Invalid inputs receive `.is-invalid` (red border `#ef4444`, soft red background `#fef2f2`, red glow), an absolute-positioned floating tooltip `#scoreTooltip`, and submit-guard blocking with an alert dialog.
- **Dirty State Tracking**: `#saveStatusBadge` toggles dynamically between `"✓ Dữ liệu đã lưu"` and `"● Có thay đổi chưa lưu"`. A `beforeunload` guard warns teachers if they try to leave the page with unsaved modifications.

### 2.4 Calculation Engine Conformance (Thông tư 22)
- **Standard Formula**: When GK and CK are present:
  $$\text{ĐTBmhk} = \text{round}\left(\frac{\sum \text{TX} + 2 \times \text{GK} + 3 \times \text{CK}}{N_{\text{TX}} + 5}, 1\right)$$
- **Dynamic Partial Formula**: When periodic exams are not yet completed:
  $$\text{ĐTBmhk} = \text{round}\left(\frac{\sum \text{TX} + 2 \times \text{GK} + 3 \times \text{CK}}{N_{\text{TX}} + W_{\text{GK}} + W_{\text{CK}}}, 1\right)$$
- **Dual Engine Consistency**: Both PHP server-side (`nhap_diem.php` lines 257–265) and client-side JavaScript (`nhap_diem.php` lines 861–873) execute this exact formula with matching rounding logic (`round($avg, 1)` and `Math.round(avg * 10) / 10`).

### 2.5 Security, Persistence & Anti-BOLA
- **Transaction Safety**: `save_scores` and `import_file` wrap database mutations in `$pdo->beginTransaction()`, `$pdo->commit()`, and `$pdo->rollBack()`.
- **Anti-BOLA Student Whitelist**: Scores POSTing verifies every submitted student ID against `students WHERE class_id = ?` using an in-memory map (`array_flip`). Injected student IDs (e.g., student 99999) are strictly ignored.
- **Strict Route Guard**: Teachers attempting to access or save scores for unassigned `(class_id, subject)` pairs receive HTTP 403 and are logged to `audit_logs`.

### 2.6 UI Consistency with Stitch Design System
- Modern typography (`Plus Jakarta Sans` for body/headings, `JetBrains Mono` for student codes and birthdates).
- Distinct assessment color accents:
  - TX Group: Sky Blue (`#eff6ff` / `#1e3a8a` / `#bfdbfe`)
  - GK Group: Amber (`#fffbeb` / `#78350f` / `#fde68a`)
  - CK Group: Soft Violet (`#f5f3ff` / `#581c87` / `#ddd6fe`)
  - ĐTBmhk: Indigo (`#e0e7ff` / `#312e81` / `#c7d2fe`)
- Grading tier badges:
  - Danger (< 5.0): `#fee2e2` / `#b91c1c`
  - Warning (5.0–6.4): `#fef3c7` / `#b45309`
  - Good (6.5–7.9): `#dbeafe` / `#1d4ed8`
  - Excellent (8.0–10.0): `#dcfce7` / `#15803d`
  - Empty (no scores): `#f1f5f9` / `#94a3b8`

---

## 3. Adversarial Stress-Testing & Edge Cases

| Scenario / Attack | Expected Result | Actual Observed Result | Status |
|---|---|---|---|
| Comma decimal entry (`8,5`) | Normalized to `8.5`, valid | Converted on `input` event, recalculates row average to 8.5 | **PASS** |
| Out-of-bounds score (`10.5`, `-1`, `15`) | Marked `.is-invalid`, tooltip displayed, submit blocked | Input bordered in red, submit prevented with alert dialog | **PASS** |
| Boundary score values (`0.0`, `10.0`, `9.9`) | Validated as legitimate scores | Accepted, average updated accurately | **PASS** |
| Empty score cells (un-entered) | Treated as `null`, not 0.0 | Count excluded from denominator, weighted average intact | **PASS** |
| Arrow navigation at table boundaries | No JavaScript errors, no form submission | Keydown `preventDefault()` keeps focus within bounds | **PASS** |
| Unsaved changes tab closing | Warning prompt displayed | `beforeunload` displays navigation confirmation warning | **PASS** |
| Cross-class student ID injection (BOLA) | Foreign student ID ignored | Whitelist check ignores ID 99999, 0 database writes | **PASS** |
| Teacher IDOR tampering (`?teacher_id=3`) | HTTP 403 Forbidden | Blocked with HTTP 403, logged to `audit_logs` | **PASS** |
| Re-import identical Excel file | 0 duplicate records created | Atomic `ON DUPLICATE KEY UPDATE` updates cleanly | **PASS** |
| Excel import with row-2 headers | Automatically detected | Header scanner finds columns on row 2, imports cleanly | **PASS** |

---

## 4. Verification Evidence & Test Results

All test suites were independently executed using `D:\xam\php\php.exe` against the live Apache server (`http://localhost/my-web-project/`) and MariaDB database (`edu_radar`):

1. **`tests/verify_m4.php`**: **39 / 39 PASS (0 failures)**
   - Group 1 (UI Layout & Headers): 4/4 PASS
   - Group 2 (Calculation Engine): 4/4 PASS
   - Group 3 (Keyboard Ergonomics): 4/4 PASS
   - Group 4 (Score Persistence & Transactions): 3/3 PASS
   - Group 5 (Sanitization & Anti-BOLA): 2/2 PASS
   - Group 6 (Excel .xlsx Import & Upsert): 5/5 PASS
   - Group 7 (CSV Import): 4/4 PASS
   - Group 8 (Excel Export & Samples): 5/5 PASS
   - Group 9 (RBAC & Route Guards): 6/6 PASS
   - Group 10 (Audit Log Forensics): 2/2 PASS

2. **Full Regression Test Matrix**:
   - `tests/verify_m1.php`: **12 / 12 PASS**
   - `tests/verify_m2.php`: **16 / 16 PASS**
   - `tests/verify_m3.php`: **28 / 28 PASS**
   - `tests/challenge_m2_rbac.php`: **37 / 37 PASS**
   - `tests/challenge_m3_admin_rbac.php`: **49 / 49 PASS**
   - **Cumulative Total Across All Suites**: **181 / 181 PASS (100% pass rate, 0 failures)**

---

## 5. Conclusion & Recommendation

The Milestone 4 implementation by `worker_m4` meets and exceeds all acceptance criteria set out in `ORIGINAL_REQUEST.md` and `PROJECT.md`. There are zero regressions against Milestones 1, 2, and 3.

**Recommendation**: Proceed to Milestone 5 (Early-Warning Radar in `index.php` and final Stitch UI cleanup).
