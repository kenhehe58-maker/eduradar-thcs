# Technical Analysis: Zero-Dependency OpenXML (.xlsx) & CSV Engine

## 1. Executive Summary
This report presents the design, architecture, and verification of `includes/xlsx_helper.php` for the EduRadar THCS application. The engine provides zero-dependency OpenXML (.xlsx) parsing and creation alongside resilient CSV parsing (with UTF-8 BOM stripping and delimiter auto-detection) using only PHP 8.0's native `ZipArchive` and `SimpleXML` extensions. It eliminates all third-party Composer packages (like PhpSpreadsheet), keeping the deployment strictly vanilla PHP on XAMPP Apache/MySQL.

Automated verification tests run against `D:\xam\php\php.exe` confirm 100% round-trip fidelity, leading-zero preservation (for Vietnamese phone numbers and student IDs), sparse cell normalization, and seamless date parsing across ISO, Vietnamese (`DD/MM/YYYY`), and Excel numeric serial formats.

---

## 2. Environment & PHP Extension Capabilities

Execution environment verification via `D:\xam\php\php.exe`:
- **PHP Version**: `PHP 8.0.30 (cli) (built: Sep 1 2023 14:15:38) (ZTS Visual C++ 2019 x64)`
- **Native Extension Telemetry**:
  - `zip`: Loaded (`ZipArchive` enabled)
  - `SimpleXML`: Loaded
  - `dom`: Loaded
  - `xml`: Loaded
- **Conclusion**: The host environment possesses all necessary native capabilities for reading and constructing standard PKZip OpenXML packages without requiring Composer or external dependencies.

---

## 3. OpenXML Package Architecture & Specification Compliance

An `.xlsx` workbook is a PKZip archive adhering to ECMA-376 / ISO/IEC 29500 (OpenXML SpreadsheetML). To open without warnings or recovery dialogs across Microsoft Excel (desktop/web), LibreOffice Calc, Apple Numbers, and Google Sheets, the archive must contain seven coordinated XML components:

### 3.1 Component Structure
1. **`[Content_Types].xml`**: Declares MIME types for relations, workbook, worksheets, shared strings, and styles:
   - `application/vnd.openxmlformats-package.relationships+xml`
   - `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml`
   - `application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml`
   - `application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml`
   - `application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml`
2. **`_rels/.rels`**: Root relationship targeting `xl/workbook.xml`.
3. **`xl/_rels/workbook.xml.rels`**: Workbook relationships targeting `worksheets/sheet1.xml`, `sharedStrings.xml`, and `styles.xml`.
4. **`xl/workbook.xml`**: Defines workbook views and declares `<sheets><sheet name="..." sheetId="1" r:id="rId1"/></sheets>`.
5. **`xl/styles.xml`**: Minimal valid stylesheet with default fonts, fills, borders, and `cellXfs`. Without this file, Microsoft Excel frequently triggers the prompt: *"We found a problem with some content... Do you want us to try to recover as much as we can?"*. Including minimal valid styles guarantees silent, clean opening.
6. **`xl/sharedStrings.xml`**: Table of unique text strings indexed by integer (`<sst count="N" uniqueCount="M"><si><t xml:space="preserve">Text</t></si></sst>`).
7. **`xl/worksheets/sheet1.xml`**: Cell data grid (`<worksheet><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c>...</row></sheetData></worksheet>`).

### 3.2 Physical Package Verification
Inspection of the generated `sample_students.xlsx` via `ZipArchive::statIndex()` confirms exact compliance:
```
 - [Content_Types].xml            (size:  816, comp:  278)
 - _rels/.rels                    (size:  296, comp:  177)
 - xl/_rels/workbook.xml.rels     (size:  565, comp:  216)
 - xl/workbook.xml                (size:  396, comp:  245)
 - xl/styles.xml                  (size:  711, comp:  332)
 - xl/sharedStrings.xml           (size: 2464, comp:  550)
 - xl/worksheets/sheet1.xml       (size: 2199, comp:  475)
```

---

## 4. Design of `includes/xlsx_helper.php`

### 4.1 Function Signatures
```php
function parse_xlsx(string $filepath): array;
function create_xlsx(array $data, string $sheet_name = 'Sheet1'): string;
function parse_csv(string $filepath): array;
function normalize_excel_date(mixed $raw): ?string;
function index_to_col_letter(int $index): string;
function col_letter_to_index(string $col): int;
```

### 4.2 Critical Edge Cases Handled

#### A. Phone Numbers and Codes with Leading Zeros
- **Problem**: Vietnamese phone numbers (e.g., `0912345601`) and student IDs (e.g., `00123`) begin with `'0'`. If treated as numeric (`t="n"`), Excel truncates leading zeros, turning `0912345601` into `912345601`.
- **Solution in `create_xlsx()`**:
  ```php
  if (is_numeric($val) && !preg_match('/^0\d+/', (string)$val)) {
      // Plain number without leading zero: numeric cell <c r="A1"><v>8.5</v></c>
      $rowCellsXml[] = '<c r="' . $cellRef . '"><v>' . $val . '</v></c>';
  } else {
      // String or number with leading zero: shared string <c r="A1" t="s"><v>0</v></c>
      // Preserves "0912345601" verbatim
  }
  ```

#### B. Sparse Rows and Sparse Columns
- **Problem**: In OpenXML, empty cells are frequently omitted. For example, a row containing data only in column A and D produces `<c r="A1">` and `<c r="D1">`. Naive parsers pushing to an array would assign column D to index 1 instead of 3, corrupting table column alignment.
- **Solution in `parse_xlsx()`**:
  1. Parse cell reference via regex `/^([A-Z]+)(\d+)$/i`.
  2. Calculate 0-based column index via `col_letter_to_index()`.
  3. Record the maximum column index across the entire sheet.
  4. Fill gaps with empty string `""` from `0` to `$maxColIndex`.
  5. Downstream code is guaranteed uniform 0-based indices without undefined index warnings.

#### C. Rich Text Runs in Shared Strings and Inline Strings
- **Problem**: Some versions of Excel wrap cell text in formatted runs: `<si><r><t>Part 1</t></r><r><t>Part 2</t></r></si>`. Reading only `<si><t>` returns empty.
- **Solution in `parse_xlsx()`**:
  Inspect both `<t>` and `<r>` children:
  ```php
  if (isset($si->t)) {
      $sharedStrings[] = (string)$si->t;
  } elseif (isset($si->r)) {
      $text = '';
      foreach ($si->r as $r) {
          $text .= (string)$r->t;
      }
      $sharedStrings[] = $text;
  }
  ```

#### D. CSV UTF-8 BOM (`\xEF\xBB\xBF`) and Delimiter Auto-Detection
- **Problem**: Microsoft Excel on Windows exports UTF-8 CSV files with a 3-byte Byte Order Mark (`\xEF\xBB\xBF`). Naive parsers read the first column header as `"\xEF\xBB\xBFHọ và tên"`, causing header matching and database inserts to include hidden characters. Furthermore, regional settings determine whether delimiters are commas (`,`) or semicolons (`;`).
- **Solution in `parse_csv()`**:
  1. Inspect the first 3 bytes: `if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);`.
  2. Inspect the first line and count `,`, `;`, and `\t`. Select the delimiter with the highest count.
  3. Parse lines via `fgetcsv($handle, 0, $delimiter)`.

#### E. Date Normalization (`normalize_excel_date`)
- **Problem**: Dates uploaded from rosters can be ISO (`2015-04-12`), Vietnamese (`12/04/2015`, `12-04-2015`), or Excel numeric serial day numbers (e.g., `42106`).
- **Solution**:
  - Serial date conversion: `days = 42106` -> adjustments for 1900 leap year bug -> `($days - 25568) * 86400` -> `gmdate('Y-m-d', ...)` -> `'2015-04-12'`.
  - Regex conversion for `DD/MM/YYYY` -> `'YYYY-MM-DD'`.
  - Pass-through for valid ISO format.

---

## 5. Sample Files & Downloader Integration

### 5.1 `sample_students.xlsx` Specification
- **Sheet Name**: `DanhSachHocSinh`
- **Columns**: `STT`, `Mã học sinh`, `Họ và tên`, `Ngày sinh`, `Giới tính`, `Số điện thoại phụ huynh`
- **Rows**: 10 standard Grade 6 student records (birthdays in 2015, realistic Vietnamese names, phone numbers `0912345601`..`0912345610`).
- **Binary Size**: 3,083 bytes.

### 5.2 `sample_gradebook.xlsx` Specification
- **Sheet Name**: `BangDiem`
- **Columns**: `STT`, `Họ và tên`, `Số điện thoại phụ huynh`, `ĐĐGtx1`, `ĐĐGtx2`, `ĐĐGtx3`, `ĐĐGtx4`, `ĐĐGgk`, `ĐĐGck`
- **Rows**: 5 student records with sample test scores (0.0 to 10.0).
- **Binary Size**: 2,816 bytes.

### 5.3 UI Download Links in `admin.php` and `nhap_diem.php`
```html
<!-- admin.php: Student Roster Download Links -->
<a href="sample_students.xlsx" download class="btn outline">Tải file mẫu (.xlsx)</a>
<a href="sample_students.csv" download class="btn outline">Tải file mẫu (.csv)</a>

<!-- nhap_diem.php: Gradebook Download Links -->
<a href="sample_gradebook.xlsx" download class="btn outline">Tải file mẫu (.xlsx)</a>
<a href="sample_vnedu.csv" download class="btn outline">Tải file mẫu (.csv)</a>
```

---

## 6. Verification Results

A 7-tier test suite was executed using `D:\xam\php\php.exe`:
| Test | Objective | Status | Telemetry |
|------|-----------|--------|-----------|
| 1 | Column letter <-> index conversion (`A`..`AB`) | PASS | Exact 0-based bijection |
| 2 | CSV Parsing on existing `sample_students.csv` | PASS | 6 rows, correct Vietnamese text |
| 3 | CSV Parsing with UTF-8 BOM & semicolon delimiter | PASS | BOM stripped, 3 rows parsed |
| 4 | OpenXML `create_xlsx` -> `parse_xlsx` round-trip | PASS | 2,848 bytes binary, phone numbers intact |
| 5 | Sparse rows and missing cells alignment | PASS | Dense matrix filled with `""` |
| 6 | Date normalization (`normalize_excel_date`) | PASS | ISO, Vietnamese, and Excel 42106 -> `2015-04-12` |
| 7 | Full `sample_students.xlsx` generation & verification | PASS | 3,083 bytes, 11 rows, 100% data fidelity |

---

## 7. Deliverables & Next Steps for Implementation

All prototype scripts, tests, and proposed production files are stored in `.agents/teamwork/explorer_m3_1/`:
1. `proposed_xlsx_helper.php` -> Drop-in implementation for `includes/xlsx_helper.php`.
2. `generate_sample_students.php` -> Generates `sample_students.xlsx` at project root.
3. `generate_sample_gradebook.php` -> Generates `sample_gradebook.xlsx` at project root.
4. `handoff.md` -> Complete 5-component handoff for `worker_m3` and `orchestrator_1_gen2`.
