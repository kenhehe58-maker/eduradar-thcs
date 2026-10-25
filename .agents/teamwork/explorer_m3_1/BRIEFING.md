# BRIEFING — 2026-10-17T08:55:00Z

## Mission
Design includes/xlsx_helper.php for zero-dependency OpenXML parsing and creation via native ZipArchive and SimpleXML, parse_csv with UTF-8 BOM detection, and standard sample_students.xlsx generation.

## 🔒 My Identity
- Archetype: explorer
- Roles: M3 OpenXML & CSV Helper Explorer, Technical Investigator
- Working directory: D:\New folder\my-web-project\.agents\teamwork\explorer_m3_1
- Original parent: 391812a0-32ec-4263-b097-6162669cc4f7
- Milestone: M3 (Admin Module & Student Import)

## 🔒 Key Constraints
- Read-only investigation — do NOT implement production source code directly in includes/
- Zero third-party / Composer dependencies (pure PHP 8.0 with ZipArchive and SimpleXML)
- Verify capabilities and test scripts using D:\xam\php\php.exe
- Strict OpenXML specification compliance (sharedStrings, workbook rels, content types, sheet structure)
- Handle UTF-8 BOM (\xEF\xBB\xBF) and delimiter auto-detection in parse_csv

## Current Parent
- Conversation ID: 391812a0-32ec-4263-b097-6162669cc4f7
- Updated: 2026-10-17T08:55:00Z

## Investigation State
- **Explored paths**:
  - `D:\New folder\my-web-project\.agents\teamwork\ORIGINAL_REQUEST.md`
  - `D:\New folder\my-web-project\.agents\teamwork\PROJECT.md`
  - `D:\New folder\my-web-project\database.sql`
  - `D:\New folder\my-web-project\admin.php`
  - `D:\New folder\my-web-project\nhap_diem.php`
  - `D:\New folder\my-web-project\sample_students.csv`
  - `D:\New folder\my-web-project\sample_vnedu.csv`
  - `D:\xam\php\php.exe` CLI runtime (PHP 8.0.30 ZTS x64)
- **Key findings**:
  - `zip`, `SimpleXML`, `dom`, and `xml` extensions are natively enabled in XAMPP's PHP 8.0.30. Zero Composer packages needed.
  - Minimal compliant OpenXML `.xlsx` package requires:
    `[Content_Types].xml`, `_rels/.rels`, `xl/_rels/workbook.xml.rels`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheet1.xml`.
  - Adding `xl/styles.xml` prevents Excel/LibreOffice "We found a problem with some content" recovery dialogs.
  - Text cells starting with '0' (such as Vietnamese phone numbers '0912345678' and student IDs '00123') must be stored as strings (`t="s"`) rather than numbers to prevent leading zero truncation.
  - Sparse rows and sparse cell coordinates (e.g. A1, C1) must be normalized into dense 0-indexed rows filled with `""` up to `maxColIndex` so downstream consumers can safely use `$row[0], $row[1]`.
  - CSV files frequently contain UTF-8 BOM (`\xEF\xBB\xBF`) when saved by Excel, and delimiters vary between comma `,` and semicolon `;`. Both must be handled cleanly.
  - Date normalization helper `normalize_excel_date()` handles ISO (YYYY-MM-DD), Vietnamese (DD/MM/YYYY), and Excel numeric serial date numbers (e.g., 42106 -> 2015-04-12).
- **Unexplored areas**: None. Prototype verified 100% with automated CLI tests.

## Key Decisions Made
- Implemented `proposed_xlsx_helper.php` containing:
  - `parse_xlsx(string $filepath): array`
  - `create_xlsx(array $data, string $sheet_name = 'Sheet1'): string`
  - `parse_csv(string $filepath): array`
  - `normalize_excel_date($raw): ?string`
  - `index_to_col_letter(int $index): string`
  - `col_letter_to_index(string $col): int`
- Designed and generated `sample_students.xlsx` with standard Vietnamese student roster columns (`STT, Mã học sinh, Họ và tên, Ngày sinh, Giới tính, Số điện thoại phụ huynh`).
- Designed and generated `sample_gradebook.xlsx` for upcoming M4 gradebook import/export.
- Provided clear integration snippets for `admin.php` and `nhap_diem.php`.

## Artifact Index
- `DISPATCH.md` — Mission instructions
- `BRIEFING.md` — Working memory and status
- `progress.md` — Liveness heartbeat
- `proposed_xlsx_helper.php` — Complete zero-dependency OpenXML & CSV helper
- `generate_sample_students.php` — Generator script for sample_students.xlsx
- `sample_students.xlsx` — Downloadable sample student roster file
- `generate_sample_gradebook.php` — Generator script for sample_gradebook.xlsx
- `sample_gradebook.xlsx` — Downloadable sample gradebook file
- `prototype_test.php` — Automated test suite verifying column conversion, CSV with BOM, OpenXML roundtrip
- `extended_test.php` — Automated test suite verifying date normalization and sample file generation
- `verify_zip_structure.php` — Script verifying ZIP package entries
- `analysis.md` — Comprehensive technical investigation report
- `handoff.md` — 5-component handoff report for worker/parent
