# M4 Investigation & Technical Design: VnEdu Spreadsheet Gradebook Interface

**Author**: `explorer_m4_1`  
**Working Directory**: `D:\New folder\my-web-project\.agents\teamwork\explorer_m4_1`  
**Target Feature**: Milestone 4, Feature #12 (VnEdu Gradebook Spreadsheet Table, Keyboard Ergonomics, Real-time Validation, Stitch UI Styling)  
**Target File**: `D:\New folder\my-web-project\nhap_diem.php`  
**Target CSS/JS**: `assets/css/style.css`, `assets/js/gradebook_spreadsheet.js`  
**Date**: 2026-10-17  

---

## 1. Executive Summary & Problem Diagnosis

### 1.1 Current State in `nhap_diem.php`
An inspection of `nhap_diem.php` (lines 100–375) reveals critical legacy mismatches with the database schema overhauled in Milestone 1:
1. **Database Schema Disconnect**:
   - `database.sql` and live MySQL `edu_radar` define `scores` with 6 grade columns per Thông tư 22/2021/TT-BGDĐT: `tx1, tx2, tx3, tx4, gk, ck, avg_score`.
   - `nhap_diem.php` currently executes queries selecting and inserting obsolete columns: `m1, m2, p15_1, p15_2, t1_1, gk, ck`. Submitting `save_scores` on the current table causes SQL execution errors or data truncation because `m1..t1_1` columns do not exist in the database!
2. **Missing Essential Student Identity Columns**:
   - The current table only displays `Họ và Tên` and `SĐT phụ huynh`.
   - The VnEdu standard requires: `STT`, `Mã HS` (e.g. `HS6A101`), `Họ và tên`, and `Ngày sinh` (formatted `dd/mm/yyyy`). `students` table already stores `student_code` and `birthday` in MySQL.
3. **Defective Keyboard Ergonomics**:
   - Current inputs use `<input type="number">` without custom key event handlers.
   - When a user presses `ArrowDown` or `ArrowUp` inside `type="number"`, browsers natively increment or decrement the numeric score (e.g. `8.5` changes to `7.5`), rather than moving the cursor down to the next student row!
   - Pressing `Enter` triggers a premature form submission instead of navigating down the column (the universal spreadsheet data entry convention in Excel and VnEdu).
4. **Lack of Vietnamese Number Formatting & Normalization**:
   - Vietnamese teachers frequently use the numeric keypad or Vietnamese IME where the decimal separator is typed as a comma `,` (e.g. `8,5`).
   - In `<input type="number">`, typing `8,5` in standard English-locale browsers causes `input.value` to be blanked out or rejected.
5. **Absence of Real-Time Validation & Dynamic Feedback**:
   - No instant out-of-range visual detection (`< 0` or `> 10`).
   - No row average recalculation in the browser without submitting the form to the server.
   - No dirty-state indicator to warn teachers of unsaved edits before navigating away.
6. **Lack of Sticky Layout**:
   - Lower secondary classes have 35–45 students. Scrolling down loses table headers, making it impossible to identify which column is TX3 or GK without scrolling back up.

---

## 2. Technical Scope & Architecture

### 2.1 Visual Column Grouping & Table Structure
The table must display 11 columns organized into 4 semantic visual zones:
1. **Zone 1: Student Information (`group-info`)**
   - `STT`: Row counter (1, 2, 3...) — Width: 52px, Sticky Left: 0px.
   - `Mã HS`: Student Code (`student_code` or `HS` + padded ID) — Width: 95px.
   - `Họ và tên`: Student Full Name (`full_name`) — Width: 220px, Sticky Left: 52px.
   - `Ngày sinh`: Birthday formatted as `d/m/Y` — Width: 105px.
2. **Zone 2: Đánh Giá Thường Xuyên (`group-tx`)** (Hệ số 1)
   - `TX1`: ĐĐGtx1 (Miệng/15p) — Width: 65px.
   - `TX2`: ĐĐGtx2 (Miệng/15p) — Width: 65px.
   - `TX3`: ĐĐGtx3 (Miệng/15p) — Width: 65px.
   - `TX4`: ĐĐGtx4 (Miệng/15p) — Width: 65px.
   - Color accent: Soft Sky Blue (`#eff6ff`, border `#bfdbfe`, text `#1e40af`).
3. **Zone 3: Đánh Giá Giữa Kỳ (`group-gk`)** (Hệ số 2)
   - `ĐĐGgk`: Mid-term examination score — Width: 80px.
   - Color accent: Soft Amber (`#fffbeb`, border `#fde68a`, text `#92400e`).
4. **Zone 4: Đánh Giá Cuối Kỳ (`group-ck`)** (Hệ số 3)
   - `ĐĐGck`: Final semester examination score — Width: 80px.
   - Color accent: Soft Violet (`#f5f3ff`, border `#ddd6fe`, text `#5b21b6`).
5. **Zone 5: Tổng Kết Môn Học Kỳ (`group-avg`)**
   - `ĐTBmhk`: Auto-calculated semester average rounded to 1 decimal place — Width: 95px.
   - Color accent: Slate / Indigo badge with diagnostic warning colors (< 5.0 Red, 5.0–6.4 Amber, 6.5–7.9 Blue, >= 8.0 Green).

### 2.2 Input Control: `type="text" inputmode="decimal"`
To eliminate native number-spinner defects and enable smooth keyboard navigation and comma normalization:
- Inputs MUST be `<input type="text" inputmode="decimal">`.
- Attributes:
  - `class="score-input group-{tx|gk|ck}"`
  - `name="scores[<student_id>][<field>]"` (`field` in `tx1`, `tx2`, `tx3`, `tx4`, `gk`, `ck`)
  - `data-student-id="<student_id>"`
  - `data-field="<field>"`
  - `data-row="<row_index>"` (0 to N-1)
  - `data-col="<col_index>"` (0 to 5)
  - `maxlength="4"`
  - `placeholder="—"`
  - `autocomplete="off"`
  - `spellcheck="false"`

---

## 3. Keyboard Ergonomics Specification

Teachers enter grades in batches. The navigation engine must support standard spreadsheet behavior:

| Keystroke | Behavior | Implementation Detail |
|---|---|---|
| `Enter` | Move down to next student in the **same column** | `focusCell(row + 1, col)` + `preventDefault()` |
| `Shift + Enter` | Move up to previous student in the **same column** | `focusCell(row - 1, col)` + `preventDefault()` |
| `ArrowDown` | Move down to next student in the **same column** | `focusCell(row + 1, col)` + `preventDefault()` |
| `ArrowUp` | Move up to previous student in the **same column** | `focusCell(row - 1, col)` + `preventDefault()` |
| `Tab` (without Shift) | Move right to next score column in the same row; wrap to column 0 of next row if at column 5 (`ck`) | `focusCell(row, col + 1)` or `focusCell(row + 1, 0)`. If at last cell of last row, allow default Tab to reach Save button. |
| `Shift + Tab` | Move left to previous score column; wrap to column 5 (`ck`) of previous row if at column 0 (`tx1`) | `focusCell(row, col - 1)` or `focusCell(row - 1, 5)`. If at cell (0,0), allow default Shift+Tab. |
| `ArrowRight` | If caret at end of text (or text selected), jump to next column (`col + 1`) | Prevents trapping caret while allowing fast horizontal cell jumping |
| `ArrowLeft` | If caret at start of text (or text selected), jump to previous column (`col - 1`) | Fast horizontal cell jumping |
| `Focus` (Any method) | Select all text in input: `this.select()` | Allows immediate 1-keystroke overwriting of existing score (Excel standard) |
| `Escape` | Revert input to initial loaded value and clear error state | Cancels erroneous keystrokes |

---

## 4. Client-Side Validation & Format Normalization

### 4.1 Normalization Engine
On the `input` event:
1. **Comma Replacement**: Any comma `,` is automatically converted to dot `.` in real-time:
   ```javascript
   let val = input.value.replace(/,/g, '.');
   ```
2. **Whitespace Trimming**: Strip leading and trailing whitespace.
3. **Decimal Shorthand**: If the user types `.5` or `,5`, automatically expand to `0.5` on `blur` or calculation.

### 4.2 Range Validation Rule
A score is considered **VALID** if:
- `val === ''` (empty / null score, not yet assessed)
- OR:
  - `!isNaN(Number(val))`
  - `Number(val) >= 0.0`
  - `Number(val) <= 10.0`
  - Matches regex: `/^(10(\.0{1,2})?|[0-9](\.[0-9]{1,2})?)$/`

A score is considered **INVALID** if:
- `Number(val) < 0` or `Number(val) > 10` (e.g. `11`, `-1`, `10.5`)
- Non-numeric characters (e.g. `abc`, `8..5`, `8-5`)

### 4.3 Visual Error Feedback
When an input fails validation:
1. Add class `is-invalid` to the input element:
   - High-contrast Red border: `border: 1.5px solid #ef4444;`
   - Soft Red background: `background-color: #fef2f2;`
   - Red focus ring: `box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25);`
2. Display floating tooltip `#scoreTooltip` pointing directly at the input:
   - Content: `"Điểm không hợp lệ: phải từ 0.0 đến 10.0 (hoặc để trống)"`.
3. Update the table footer warning banner:
   - `"⚠️ Có [X] ô điểm không hợp lệ! Vui lòng sửa lại trước khi lưu."`
4. Form Submission Guard:
   - When the user clicks "Lưu Bảng Điểm", if any input has `.is-invalid`, intercept `submit`, scroll to and focus the first invalid input, and show a clear error toast.

---

## 5. Client-Side Real-Time Calculation Engine

Whenever any input in a student row changes and is valid, immediately recalculate that student's `ĐTBmhk` in the browser without reloading the page:

### 5.1 Calculation Algorithm
```javascript
function calculateStudentAverage(studentId) {
    const rowInputs = document.querySelectorAll(`.score-input[data-student-id="${studentId}"]`);
    let txScores = [];
    let gkScore = null;
    let ckScore = null;

    rowInputs.forEach(input => {
        const valStr = input.value.trim().replace(',', '.');
        if (valStr !== '' && !isNaN(valStr)) {
            const num = parseFloat(valStr);
            if (num >= 0 && num <= 10) {
                const field = input.dataset.field;
                if (field.startsWith('tx')) txScores.push(num);
                else if (field === 'gk') gkScore = num;
                else if (field === 'ck') ckScore = num;
            }
        }
    });

    const txCount = txScores.length;
    const txSum = txScores.reduce((a, b) => a + b, 0);

    let avg = null;
    if (gkScore !== null && ckScore !== null) {
        // Standard full formula
        avg = (txSum + 2 * gkScore + 3 * ckScore) / (txCount + 5);
    } else {
        // Dynamic weighted fallback for partial entries
        const totalWeight = txCount + (gkScore !== null ? 2 : 0) + (ckScore !== null ? 3 : 0);
        const weightedSum = txSum + (gkScore !== null ? 2 * gkScore : 0) + (ckScore !== null ? 3 * ckScore : 0);
        if (totalWeight > 0) {
            avg = weightedSum / totalWeight;
        }
    }

    const badge = document.querySelector(`.avg-badge[data-avg-student="${studentId}"]`);
    if (badge) {
        if (avg !== null) {
            const rounded = Math.round(avg * 10) / 10;
            badge.textContent = rounded.toFixed(1);
            badge.className = 'avg-badge ' + getScoreLevelClass(rounded);
        } else {
            badge.textContent = '—';
            badge.className = 'avg-badge level-empty';
        }
    }
}

function getScoreLevelClass(score) {
    if (score < 5.0) return 'level-danger';   // Red (< 5.0: Nguy cấp)
    if (score < 6.5) return 'level-warning';  // Amber (5.0 - 6.4: Cần chú ý)
    if (score < 8.0) return 'level-good';     // Blue (6.5 - 7.9: Khá)
    return 'level-excellent';                 // Green (>= 8.0: Tốt)
}
```

---

## 6. Ergonomic Controls & Dirty-State Management

1. **Dirty-State Indicator**:
   - Track initial form values upon page load.
   - When any input value changes from its initial state:
     - Set `isDirty = true`.
     - Update Save button badge:
       - Default: `<span class="dirty-badge clean">✓ Đã đồng bộ</span>`
       - Dirty: `<span class="dirty-badge dirty">● Có thay đổi chưa lưu</span>`
       - Add CSS pulse animation to the Save button.
2. **Accidental Navigation Guard**:
   ```javascript
   window.addEventListener('beforeunload', function (e) {
       if (isDirty) {
           e.preventDefault();
           e.returnValue = 'Bạn có thay đổi điểm chưa được lưu. Rời khỏi trang sẽ mất dữ liệu?';
           return e.returnValue;
       }
   });
   ```
   On valid form submission: `isDirty = false;` to allow clean navigation.
3. **Sticky Header & Frozen Student Column**:
   - Table wrapper: `.spreadsheet-container` with `max-height: 70vh; overflow: auto;`.
   - `thead th`: `position: sticky; top: 0; z-index: 20;` with opaque background `#0f172a`.
   - Sticky student columns:
     - `th.col-sticky-stt, td.col-sticky-stt`: `position: sticky; left: 0; z-index: 10;`
     - `th.col-sticky-name, td.col-sticky-name`: `position: sticky; left: 52px; z-index: 10;`
     - When scrolling horizontally, student names stay fixed on the left while score columns scroll underneath smoothly.

---

## 7. Concrete Drop-in Code Blueprints

### 7.1 PHP & HTML Blueprint for `nhap_diem.php`

```php
<!-- SPREADSHEET TOOLBAR -->
<div class="spreadsheet-toolbar">
    <div class="toolbar-info">
        <span class="toolbar-title">Sổ Điểm Môn <?= htmlspecialchars($selectedSubject) ?> — Lớp <?= htmlspecialchars($curClassName) ?></span>
        <span class="badge" style="background: #e0e7ff; color: #3730a3; font-weight: 700;">
            Sĩ số: <?= count($studentList) ?> học sinh
        </span>
    </div>
    <div class="toolbar-actions">
        <span id="saveStatusBadge" class="dirty-badge clean">✓ Dữ liệu đã lưu</span>
        <button type="button" id="btnResetChanges" class="btn outline" style="display: none;">
            ↺ Hủy thay đổi
        </button>
        <button type="submit" id="btnSaveScores" class="btn primary">
            💾 Lưu Bảng Điểm
        </button>
    </div>
</div>

<!-- SPREADSHEET TABLE CONTAINER -->
<div class="spreadsheet-container">
    <table class="spreadsheet-table" id="gradebookTable">
        <thead>
            <!-- TIER 1 HEADER: SUPER-GROUPS -->
            <tr class="header-tier-1">
                <th rowspan="2" class="col-sticky-stt">STT</th>
                <th rowspan="2" class="col-code">Mã HS</th>
                <th rowspan="2" class="col-sticky-name">Họ và Tên Học Sinh</th>
                <th rowspan="2" class="col-dob">Ngày Sinh</th>
                <th colspan="4" class="group-header group-tx">
                    <span class="group-pill pill-tx">ĐĐG THƯỜNG XUYÊN (HỆ SỐ 1)</span>
                </th>
                <th rowspan="2" class="group-header group-gk">
                    <span class="group-pill pill-gk">GIỮA KỲ</span>
                    <span class="weight-tag">HS 2</span>
                </th>
                <th rowspan="2" class="group-header group-ck">
                    <span class="group-pill pill-ck">CUỐI KỲ</span>
                    <span class="weight-tag">HS 3</span>
                </th>
                <th rowspan="2" class="group-header group-avg">
                    <span class="group-pill pill-avg">ĐTBmhk</span>
                    <span class="weight-tag">TỰ ĐỘNG</span>
                </th>
            </tr>
            <!-- TIER 2 HEADER: TX SUB-COLUMNS -->
            <tr class="header-tier-2">
                <th class="sub-tx">TX1</th>
                <th class="sub-tx">TX2</th>
                <th class="sub-tx">TX3</th>
                <th class="sub-tx">TX4</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($studentList)): ?>
                <tr>
                    <td colspan="11" class="empty-state">
                        Lớp học chưa có học sinh. Admin hoặc Giáo viên vui lòng sử dụng công cụ nhập danh sách bên dưới.
                    </td>
                </tr>
            <?php else: ?>
                <?php $r = 0; foreach ($studentList as $s): ?>
                    <?php 
                        $sId = (int)$s['id'];
                        $code = !empty($s['student_code']) ? htmlspecialchars($s['student_code']) : ('HS' . str_pad((string)$sId, 4, '0', STR_PAD_LEFT));
                        $dob = !empty($s['birthday']) ? date('d/m/Y', strtotime($s['birthday'])) : '—';
                        $avg = $s['avg_score'] !== null ? number_format((float)$s['avg_score'], 1) : null;
                        
                        $avgClass = 'level-empty';
                        if ($avg !== null) {
                            $fAvg = (float)$avg;
                            if ($fAvg < 5.0) $avgClass = 'level-danger';
                            elseif ($fAvg < 6.5) $avgClass = 'level-warning';
                            elseif ($fAvg < 8.0) $avgClass = 'level-good';
                            else $avgClass = 'level-excellent';
                        }
                    ?>
                    <tr data-student-id="<?= $sId ?>" data-row-idx="<?= $r ?>">
                        <td class="col-sticky-stt text-center"><?= $r + 1 ?></td>
                        <td class="col-code font-mono text-center"><?= $code ?></td>
                        <td class="col-sticky-name">
                            <div class="student-name font-semibold"><?= htmlspecialchars($s['full_name']) ?></div>
                            <?php if (!empty($s['phone'])): ?>
                                <div class="student-phone muted small"><?= htmlspecialchars($s['phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="col-dob text-center font-mono muted"><?= $dob ?></td>
                        
                        <!-- 4 CỘT TX (HỆ SỐ 1) -->
                        <td class="col-score cell-tx">
                            <input type="text" inputmode="decimal" maxlength="4" class="score-input group-tx"
                                   name="scores[<?= $sId ?>][tx1]"
                                   value="<?= $s['tx1'] !== null ? (float)$s['tx1'] : '' ?>"
                                   data-student-id="<?= $sId ?>" data-field="tx1"
                                   data-row="<?= $r ?>" data-col="0" placeholder="—" autocomplete="off">
                        </td>
                        <td class="col-score cell-tx">
                            <input type="text" inputmode="decimal" maxlength="4" class="score-input group-tx"
                                   name="scores[<?= $sId ?>][tx2]"
                                   value="<?= $s['tx2'] !== null ? (float)$s['tx2'] : '' ?>"
                                   data-student-id="<?= $sId ?>" data-field="tx2"
                                   data-row="<?= $r ?>" data-col="1" placeholder="—" autocomplete="off">
                        </td>
                        <td class="col-score cell-tx">
                            <input type="text" inputmode="decimal" maxlength="4" class="score-input group-tx"
                                   name="scores[<?= $sId ?>][tx3]"
                                   value="<?= $s['tx3'] !== null ? (float)$s['tx3'] : '' ?>"
                                   data-student-id="<?= $sId ?>" data-field="tx3"
                                   data-row="<?= $r ?>" data-col="2" placeholder="—" autocomplete="off">
                        </td>
                        <td class="col-score cell-tx">
                            <input type="text" inputmode="decimal" maxlength="4" class="score-input group-tx"
                                   name="scores[<?= $sId ?>][tx4]"
                                   value="<?= $s['tx4'] !== null ? (float)$s['tx4'] : '' ?>"
                                   data-student-id="<?= $sId ?>" data-field="tx4"
                                   data-row="<?= $r ?>" data-col="3" placeholder="—" autocomplete="off">
                        </td>
                        
                        <!-- CỘT GIỮA KỲ (HỆ SỐ 2) -->
                        <td class="col-score cell-gk">
                            <input type="text" inputmode="decimal" maxlength="4" class="score-input group-gk"
                                   name="scores[<?= $sId ?>][gk]"
                                   value="<?= $s['gk'] !== null ? (float)$s['gk'] : '' ?>"
                                   data-student-id="<?= $sId ?>" data-field="gk"
                                   data-row="<?= $r ?>" data-col="4" placeholder="—" autocomplete="off">
                        </td>
                        
                        <!-- CỘT CUỐI KỲ (HỆ SỐ 3) -->
                        <td class="col-score cell-ck">
                            <input type="text" inputmode="decimal" maxlength="4" class="score-input group-ck"
                                   name="scores[<?= $sId ?>][ck]"
                                   value="<?= $s['ck'] !== null ? (float)$s['ck'] : '' ?>"
                                   data-student-id="<?= $sId ?>" data-field="ck"
                                   data-row="<?= $r ?>" data-col="5" placeholder="—" autocomplete="off">
                        </td>
                        
                        <!-- CỘT ĐTB MÔN HỌC KỲ (TỰ ĐỘNG) -->
                        <td class="col-avg cell-avg text-center">
                            <span class="avg-badge <?= $avgClass ?>" data-avg-student="<?= $sId ?>">
                                <?= $avg !== null ? $avg : '—' ?>
                            </span>
                        </td>
                    </tr>
                <?php $r++; endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- FLOATING TOOLTIP FOR ERROR HINTS -->
<div id="scoreTooltip" class="score-tooltip" style="display: none;"></div>
```

---

### 7.2 Complete CSS Blueprint for Stitch UI Styling

```css
/* ==========================================================================
   STITCH UI - SPREADSHEET GRADEBOOK SYSTEM
   ========================================================================== */

/* TOOLBAR & CONTROLS */
.spreadsheet-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    padding: 14px 20px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: 16px;
    gap: 16px;
    flex-wrap: wrap;
}

.toolbar-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.toolbar-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-heading);
    letter-spacing: -0.3px;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* DIRTY STATE BADGE */
.dirty-badge {
    font-size: 12.5px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: var(--radius-full);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.dirty-badge.clean {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.dirty-badge.dirty {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    animation: pulseBadge 1.8s infinite;
}

@keyframes pulseBadge {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.85; transform: scale(1.02); }
}

/* SPREADSHEET TABLE CONTAINER WITH STICKY SCROLL */
.spreadsheet-container {
    max-height: calc(100vh - 290px);
    min-height: 420px;
    overflow: auto;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    background: #ffffff;
    box-shadow: var(--shadow-sm);
    position: relative;
    margin-bottom: 24px;
}

.spreadsheet-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13.5px;
}

/* HEADER BASE */
.spreadsheet-table thead tr th {
    background: #090d16;
    color: #ffffff;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 8px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    position: sticky;
    z-index: 20;
    user-select: none;
}

.spreadsheet-table thead tr.header-tier-1 th {
    top: 0;
    height: 42px;
    z-index: 22;
}

.spreadsheet-table thead tr.header-tier-2 th {
    top: 42px;
    height: 34px;
    z-index: 21;
    background: #111827;
}

/* STICKY STUDENT COLUMNS (FROZEN PANE) */
.col-sticky-stt {
    width: 52px;
    min-width: 52px;
    max-width: 52px;
    position: sticky !important;
    left: 0;
    z-index: 10;
    background: #ffffff;
    text-align: center;
}

.col-sticky-name {
    width: 220px;
    min-width: 200px;
    position: sticky !important;
    left: 52px;
    z-index: 10;
    background: #ffffff;
    box-shadow: 3px 0 6px -2px rgba(0, 0, 0, 0.06);
    text-align: left !important;
    padding-left: 14px !important;
}

/* THEAD OVERRIDES FOR STICKY PANE */
thead tr th.col-sticky-stt {
    z-index: 32 !important;
    background: #090d16 !important;
}

thead tr th.col-sticky-name {
    z-index: 32 !important;
    background: #090d16 !important;
    box-shadow: 3px 0 6px -2px rgba(0, 0, 0, 0.2);
}

/* ROW & CELL STYLES */
.spreadsheet-table tbody tr {
    transition: background 0.1s ease;
}

.spreadsheet-table tbody tr:hover td {
    background-color: #f8fafc;
}

.spreadsheet-table tbody tr:hover td.col-sticky-stt,
.spreadsheet-table tbody tr:hover td.col-sticky-name {
    background-color: #f1f5f9;
}

.spreadsheet-table tbody td {
    padding: 8px 6px;
    border-bottom: 1px solid var(--border-color);
    border-right: 1px solid #f1f5f9;
    vertical-align: middle;
}

/* VISUAL GROUP ACCENTS */
/* 1. TX Group */
.group-header.group-tx {
    background: #1e3a8a !important;
    border-right: 2px solid #3b82f6 !important;
}
.group-pill.pill-tx {
    background: #dbeafe;
    color: #1e40af;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
}
.cell-tx {
    background: #f8fbff;
}
.score-input.group-tx:focus {
    border-color: #3b82f6 !important;
    background: #eff6ff !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25) !important;
}

/* 2. GK Group */
.group-header.group-gk {
    background: #78350f !important;
    border-right: 2px solid #f59e0b !important;
}
.group-pill.pill-gk {
    background: #fef3c7;
    color: #92400e;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
}
.cell-gk {
    background: #fffdf5;
}
.score-input.group-gk:focus {
    border-color: #f59e0b !important;
    background: #fffbeb !important;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25) !important;
}

/* 3. CK Group */
.group-header.group-ck {
    background: #581c87 !important;
    border-right: 2px solid #8b5cf6 !important;
}
.group-pill.pill-ck {
    background: #ede9fe;
    color: #5b21b6;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
}
.cell-ck {
    background: #faf7ff;
}
.score-input.group-ck:focus {
    border-color: #8b5cf6 !important;
    background: #f5f3ff !important;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.25) !important;
}

/* 4. Average Group */
.group-header.group-avg {
    background: #312e81 !important;
}
.group-pill.pill-avg {
    background: #e0e7ff;
    color: #3730a3;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
}
.cell-avg {
    background: #f5f7ff;
    border-left: 2px solid #c7d2fe !important;
}

.weight-tag {
    display: block;
    font-size: 10px;
    color: #94a3b8;
    margin-top: 3px;
    font-weight: 600;
}

/* SPREADSHEET INPUT CELLS */
.score-input {
    width: 62px;
    height: 36px;
    margin: 0 auto;
    display: block;
    padding: 4px 6px;
    border: 1px solid #cbd5e1;
    border-radius: var(--radius-sm);
    text-align: center;
    font-weight: 700;
    font-family: 'JetBrains Mono', monospace;
    font-size: 15px;
    color: #0f172a;
    background: #ffffff;
    outline: none;
    transition: all 0.15s ease;
}

.score-input::placeholder {
    color: #cbd5e1;
    font-weight: 400;
}

/* INVALID INPUT HIGHLIGHTING */
.score-input.is-invalid {
    border-color: #ef4444 !important;
    background-color: #fef2f2 !important;
    color: #b91c1c !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important;
    animation: shakeInput 0.3s ease;
}

@keyframes shakeInput {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-3px); }
    75% { transform: translateX(3px); }
}

/* AVERAGE SCORE BADGES */
.avg-badge {
    display: inline-block;
    min-width: 52px;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 800;
    font-family: 'JetBrains Mono', monospace;
    font-size: 15px;
    text-align: center;
    transition: all 0.2s ease;
}

.avg-badge.level-danger {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fca5a5;
}

.avg-badge.level-warning {
    background: #fef3c7;
    color: #d97706;
    border: 1px solid #fcd34d;
}

.avg-badge.level-good {
    background: #dbeafe;
    color: #2563eb;
    border: 1px solid #93c5fd;
}

.avg-badge.level-excellent {
    background: #dcfce7;
    color: #16a34a;
    border: 1px solid #86efac;
}

.avg-badge.level-empty {
    color: #94a3b8;
    font-weight: 500;
}

/* FLOATING SCORE TOOLTIP */
.score-tooltip {
    position: absolute;
    background: #090d16;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 6px;
    box-shadow: var(--shadow-lg);
    pointer-events: none;
    z-index: 1000;
    white-space: nowrap;
    border: 1px solid rgba(239, 68, 68, 0.6);
}

.score-tooltip::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px 5px 0;
    border-style: solid;
    border-color: #090d16 transparent;
    display: block;
    width: 0;
}
```

---

### 7.3 Complete JavaScript Engine Blueprint (`gradebook_spreadsheet.js`)

```javascript
/**
 * =============================================================================
 * VnEdu Gradebook Spreadsheet Engine (EduRadar THCS)
 * - Full 2D Keyboard Ergonomics (Enter, Shift+Enter, Arrows, Tab, Shift+Tab)
 * - Comma & Dot Normalization (8,5 -> 8.5)
 * - Real-Time Range Validation (0.0 - 10.0 or nullable)
 * - Instant Row ĐTBmhk Live Calculation
 * - Dirty-State Tracking & Navigation Guard
 * =============================================================================
 */

(function () {
    'use strict';

    let isDirty = false;
    const initialValues = new Map();

    const table = document.getElementById('gradebookTable');
    if (!table) return;

    const inputs = Array.from(document.querySelectorAll('.score-input'));
    const form = table.closest('form');
    const saveBadge = document.getElementById('saveStatusBadge');
    const btnReset = document.getElementById('btnResetChanges');
    const tooltip = document.getElementById('scoreTooltip');

    // Store initial values to detect real dirty changes
    inputs.forEach(inp => {
        initialValues.set(inp, inp.value.trim());
    });

    /**
     * Map inputs by row and col for fast O(1) grid navigation
     */
    const grid = {};
    let maxRow = -1;
    let maxCol = -1;

    inputs.forEach(inp => {
        const r = parseInt(inp.dataset.row, 10);
        const c = parseInt(inp.dataset.col, 10);
        if (!grid[r]) grid[r] = {};
        grid[r][c] = inp;
        if (r > maxRow) maxRow = r;
        if (c > maxCol) maxCol = c;
    });

    function focusCell(r, c) {
        if (grid[r] && grid[r][c]) {
            const target = grid[r][c];
            target.focus();
            target.select();
            return true;
        }
        return false;
    }

    /**
     * Mark dirty state and update UI
     */
    function updateDirtyState() {
        let changed = false;
        for (const [inp, initial] of initialValues.entries()) {
            if (inp.value.trim() !== initial) {
                changed = true;
                break;
            }
        }
        isDirty = changed;

        if (saveBadge) {
            if (isDirty) {
                saveBadge.className = 'dirty-badge dirty';
                saveBadge.textContent = '● Có thay đổi chưa lưu';
                if (btnReset) btnReset.style.display = 'inline-flex';
            } else {
                saveBadge.className = 'dirty-badge clean';
                saveBadge.textContent = '✓ Dữ liệu đã lưu';
                if (btnReset) btnReset.style.display = 'none';
            }
        }
    }

    /**
     * Show / hide floating error tooltip
     */
    function showTooltip(input, message) {
        if (!tooltip) return;
        const rect = input.getBoundingClientRect();
        tooltip.textContent = message;
        tooltip.style.display = 'block';
        tooltip.style.top = `${window.scrollY + rect.top - tooltip.offsetHeight - 8}px`;
        tooltip.style.left = `${window.scrollX + rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
    }

    function hideTooltip() {
        if (tooltip) tooltip.style.display = 'none';
    }

    /**
     * Validate and normalize a single cell
     */
    function validateInput(input) {
        // Real-time comma replacement
        input.value = input.value.replace(/,/g, '.');
        const val = input.value.trim();

        if (val === '') {
            input.classList.remove('is-invalid');
            hideTooltip();
            return true;
        }

        const num = Number(val);
        const isValid = !isNaN(num) && num >= 0.0 && num <= 10.0 && /^(10(\.0{1,2})?|[0-9](\.[0-9]{1,2})?)$/.test(val);

        if (!isValid) {
            input.classList.add('is-invalid');
            showTooltip(input, 'Điểm phải từ 0.0 đến 10.0');
            return false;
        } else {
            input.classList.remove('is-invalid');
            hideTooltip();
            return true;
        }
    }

    /**
     * Real-time student average calculation (Thông tư 22)
     */
    function recalculateStudent(studentId) {
        const studentInputs = document.querySelectorAll(`.score-input[data-student-id="${studentId}"]`);
        const txScores = [];
        let gk = null;
        let ck = null;

        studentInputs.forEach(inp => {
            const val = inp.value.trim().replace(/,/g, '.');
            if (val !== '' && !isNaN(val)) {
                const n = parseFloat(val);
                if (n >= 0 && n <= 10) {
                    const f = inp.dataset.field;
                    if (f.startsWith('tx')) txScores.push(n);
                    else if (f === 'gk') gk = n;
                    else if (f === 'ck') ck = n;
                }
            }
        });

        const txCount = txScores.length;
        const txSum = txScores.reduce((a, b) => a + b, 0);

        let avg = null;
        if (gk !== null && ck !== null) {
            avg = (txSum + 2 * gk + 3 * ck) / (txCount + 5);
        } else {
            const totalWeight = txCount + (gk !== null ? 2 : 0) + (ck !== null ? 3 : 0);
            const weightedSum = txSum + (gk !== null ? 2 * gk : 0) + (ck !== null ? 3 * ck : 0);
            if (totalWeight > 0) {
                avg = weightedSum / totalWeight;
            }
        }

        const badge = document.querySelector(`.avg-badge[data-avg-student="${studentId}"]`);
        if (badge) {
            if (avg !== null) {
                const rounded = Math.round(avg * 10) / 10;
                badge.textContent = rounded.toFixed(1);

                badge.className = 'avg-badge ';
                if (rounded < 5.0) badge.classList.add('level-danger');
                else if (rounded < 6.5) badge.classList.add('level-warning');
                else if (rounded < 8.0) badge.classList.add('level-good');
                else badge.classList.add('level-excellent');
            } else {
                badge.textContent = '—';
                badge.className = 'avg-badge level-empty';
            }
        }
    }

    /**
     * Attach Event Listeners to each score cell
     */
    inputs.forEach(input => {
        // 1. Focus event: auto-select text for fast overwrite
        input.addEventListener('focus', function () {
            this.select();
            if (this.classList.contains('is-invalid')) {
                showTooltip(this, 'Điểm phải từ 0.0 đến 10.0');
            }
        });

        input.addEventListener('blur', function () {
            hideTooltip();
            // Optional normalize: e.g. ".5" -> "0.5"
            if (this.value.startsWith('.')) {
                this.value = '0' + this.value;
                validateInput(this);
                recalculateStudent(this.dataset.studentId);
                updateDirtyState();
            }
        });

        // 2. Input event: normalization, validation, live calculation
        input.addEventListener('input', function () {
            validateInput(this);
            recalculateStudent(this.dataset.studentId);
            updateDirtyState();
        });

        // 3. Keydown event: 2D keyboard navigation
        input.addEventListener('keydown', function (e) {
            const r = parseInt(this.dataset.row, 10);
            const c = parseInt(this.dataset.col, 10);

            // ENTER: Move DOWN in same column (standard spreadsheet data entry)
            if (e.key === 'Enter') {
                e.preventDefault();
                if (e.shiftKey) {
                    // Shift+Enter: Move UP
                    if (!focusCell(r - 1, c)) {
                        focusCell(0, c);
                    }
                } else {
                    // Enter: Move DOWN
                    if (!focusCell(r + 1, c)) {
                        // At bottom of column: focus next column or stay
                        focusCell(r, c);
                    }
                }
                return;
            }

            // ARROW DOWN: Move DOWN in same column
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                focusCell(r + 1, c);
                return;
            }

            // ARROW UP: Move UP in same column
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                focusCell(r - 1, c);
                return;
            }

            // TAB: Horizontal navigation with row wrap
            if (e.key === 'Tab') {
                if (!e.shiftKey) {
                    // TAB (Forward)
                    if (c < maxCol) {
                        e.preventDefault();
                        focusCell(r, c + 1);
                    } else if (r < maxRow) {
                        // Wrap to next row, column 0
                        e.preventDefault();
                        focusCell(r + 1, 0);
                    }
                    // At (maxRow, maxCol), allow default tab to focus Save button
                } else {
                    // SHIFT + TAB (Backward)
                    if (c > 0) {
                        e.preventDefault();
                        focusCell(r, c - 1);
                    } else if (r > 0) {
                        // Wrap to previous row, last column
                        e.preventDefault();
                        focusCell(r - 1, maxCol);
                    }
                }
                return;
            }

            // ARROW RIGHT: Cell jump when caret is at the end or text selected
            if (e.key === 'ArrowRight') {
                if (this.selectionStart === this.value.length || this.selectionStart === 0 && this.selectionEnd === this.value.length) {
                    if (c < maxCol) {
                        e.preventDefault();
                        focusCell(r, c + 1);
                    } else if (r < maxRow) {
                        e.preventDefault();
                        focusCell(r + 1, 0);
                    }
                }
                return;
            }

            // ARROW LEFT: Cell jump when caret is at the start or text selected
            if (e.key === 'ArrowLeft') {
                if (this.selectionEnd === 0 || this.selectionStart === 0 && this.selectionEnd === this.value.length) {
                    if (c > 0) {
                        e.preventDefault();
                        focusCell(r, c - 1);
                    } else if (r > 0) {
                        e.preventDefault();
                        focusCell(r - 1, maxCol);
                    }
                }
                return;
            }

            // ESCAPE: Revert to initial value
            if (e.key === 'Escape') {
                this.value = initialValues.get(this) || '';
                validateInput(this);
                recalculateStudent(this.dataset.studentId);
                updateDirtyState();
            }
        });
    });

    // 4. Form Submit Guard: prevent submitting invalid scores
    if (form) {
        form.addEventListener('submit', function (e) {
            const invalidInputs = Array.from(document.querySelectorAll('.score-input.is-invalid'));
            if (invalidInputs.length > 0) {
                e.preventDefault();
                invalidInputs[0].focus();
                invalidInputs[0].select();
                showTooltip(invalidInputs[0], 'Vui lòng sửa các ô điểm không hợp lệ trước khi lưu!');
                alert('Không thể lưu: Có ' + invalidInputs.length + ' ô điểm không hợp lệ (phải từ 0.0 đến 10.0)!');
                return false;
            }
            // Allow clean submission
            isDirty = false;
        });
    }

    // 5. Reset button handler
    if (btnReset) {
        btnReset.addEventListener('click', function () {
            if (!confirm('Bạn có chắc muốn hủy các thay đổi chưa lưu?')) return;
            inputs.forEach(inp => {
                inp.value = initialValues.get(inp) || '';
                validateInput(inp);
                recalculateStudent(inp.dataset.studentId);
            });
            updateDirtyState();
        });
    }

    // 6. Navigation Guard (beforeunload)
    window.addEventListener('beforeunload', function (e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = 'Bạn có thay đổi chưa lưu. Bạn có chắc muốn rời đi?';
            return e.returnValue;
        }
    });

})();
```

---

## 8. Alignment with Milestone 4 Peer Explorers

1. **Alignment with `explorer_m4_2` (Calculation Engine & Persistence)**:
   - Server-side and client-side calculations use the identical Thông tư 22 formula and weighted fallback.
   - Form input fields map cleanly to `scores[student_id][tx1..tx4, gk, ck]`, enabling `explorer_m4_2` to iterate and execute atomic transaction upserts.
2. **Alignment with `explorer_m4_3` (Excel/CSV Import/Export)**:
   - The spreadsheet table structure matches the exact column headers expected in `sample_gradebook.xlsx` and `sample_vnedu.csv`: `STT, Mã HS, Họ và tên, Ngày sinh, TX1, TX2, TX3, TX4, ĐĐGgk, ĐĐGck, ĐTBmhk`.
   - Exported Excel worksheets mirror the visual column sequence of the table.

---

## 9. Next Steps for Implementer

1. Replace legacy database query in `nhap_diem.php` with the 6-column query: `tx1, tx2, tx3, tx4, gk, ck, avg_score`.
2. Replace the HTML `table` element in `nhap_diem.php` with the 2-tier sticky spreadsheet structure defined in Section 7.1.
3. Append spreadsheet CSS styles to `assets/css/style.css`.
4. Include the spreadsheet JavaScript engine into `assets/js/gradebook_spreadsheet.js` (or inline inside `nhap_diem.php`).
5. Run the full verification suite (`tests/verify_m1.php`, `tests/verify_m2.php`, `tests/verify_m3.php`) to confirm zero regressions on existing RBAC and database tests.
