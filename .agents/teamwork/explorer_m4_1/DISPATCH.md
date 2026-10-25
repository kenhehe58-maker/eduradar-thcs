# DISPATCH — 2026-10-17T09:55:00Z

## Identity
- Role: M4 Gradebook Spreadsheet UI Explorer
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m4_1
- Parent: orchestrator_1_gen2 (391812a0-32ec-4263-b097-6162669cc4f7)

## Mission
Investigate and design the modern VnEdu spreadsheet gradebook interface in `nhap_diem.php`: 6 grade columns (`tx1..tx4, gk, ck, avg_score`), keyboard navigation, real-time validation, and Stitch UI styling.

## Mandatory Reading (Authoritative Sources)
1. `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md` (Read FIRST)
2. `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md` (Milestone 4, Feature #12)
3. `D:\New folder\my-web-project\nhap_diem.php`

## Technical Scope
1. **Spreadsheet Table Layout**:
   - Columns: `STT, Mã HS, Họ và tên, Ngày sinh, ĐĐGtx1, ĐĐGtx2, ĐĐGtx3, ĐĐGtx4, ĐĐGgk (HS 2), ĐĐGck (HS 3), ĐTBmhk`.
   - Distinct visual column group styling (e.g., light blue for TX, light yellow for GK, light purple for CK, bold for ĐTBmhk).
2. **Keyboard Ergonomics**:
   - `Tab` / `Shift+Tab`: move horizontally between score inputs.
   - `ArrowUp` / `ArrowDown`: move vertically to the same score column for adjacent students.
   - `Enter`: move down to the next student in the same column (standard spreadsheet data entry convention).
3. **Client-Side Validation & Feedback**:
   - Scores must be between `0.0` and `10.0` or empty (nullable).
   - Instant visual indicator (red border/highlight and tooltip warning) on out-of-range input (`< 0` or `> 10` or non-numeric).
   - Format normalization: accept comma `,` or dot `.` as decimal separator (e.g. `8,5` -> `8.5`).
4. **Ergonomic Controls**:
   - Sticky table header so column names stay visible on long student rosters.
   - Save button with dirty-state indicator (e.g. "Có thay đổi chưa lưu").

## Output
Write `analysis.md` and `handoff.md` in your working directory. Send a message to your parent when done.
