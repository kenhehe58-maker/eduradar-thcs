<?php
/**
 * =============================================================================
 * Zero-Dependency OpenXML (.xlsx) & CSV Engine (EduRadar THCS)
 * 
 * Complies with ECMA-376 / ISO/IEC 29500 OpenXML SpreadsheetML standard.
 * Uses native PHP 8.0+ ZipArchive and SimpleXML extensions (Zero Composer/vendor).
 * 
 * Provides:
 * - parse_xlsx(string $filepath): array
 * - create_xlsx(array $data, string $sheet_name = 'Sheet1'): string
 * - parse_csv(string $filepath): array
 * - normalize_excel_date(mixed $raw): ?string
 * - index_to_col_letter(int $index): string
 * - col_letter_to_index(string $col): int
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Convert 0-based column index to Excel column letters (0 -> 'A', 25 -> 'Z', 26 -> 'AA', etc.).
 */
function index_to_col_letter(int $index): string
{
    $letter = '';
    $index += 1; // Convert to 1-based
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $index = (int)(($index - 1) / 26);
    }
    return $letter;
}

/**
 * Convert Excel column letters to 0-based column index ('A' -> 0, 'Z' -> 25, 'AA' -> 26, etc.).
 */
function col_letter_to_index(string $col): int
{
    $col = strtoupper(trim($col));
    $len = strlen($col);
    $index = 0;
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($col[$i]) - 64);
    }
    return $index - 1;
}

/**
 * Normalize diverse date formats from Excel/CSV to MySQL standard YYYY-MM-DD.
 * Handles:
 * - ISO: 'YYYY-MM-DD', 'YYYY/MM/DD'
 * - Vietnamese/European: 'DD/MM/YYYY', 'DD-MM-YYYY', 'D/M/YYYY'
 * - Excel numeric serial date numbers (e.g. 42106 -> '2015-04-12')
 */
function normalize_excel_date($raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }

    $raw = trim((string)$raw);

    // 1. Numeric Excel date serial number (typically between 1000 and 100000)
    if (is_numeric($raw) && (float)$raw > 1000 && (float)$raw < 100000) {
        $days = (float)$raw;
        // Excel 1900 leap year bug: day 60 was phantom 1900-02-29
        if ($days > 60) {
            $days -= 1;
        }
        $unixTimestamp = ($days - 25568) * 86400;
        return gmdate('Y-m-d', (int)$unixTimestamp);
    }

    // 2. DD/MM/YYYY or DD-MM-YYYY or DD.MM.YYYY
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }

    // 3. YYYY-MM-DD or YYYY/MM/DD or YYYY.MM.DD
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }

    return null;
}

/**
 * Parse OpenXML (.xlsx) spreadsheet into a 2D array of rows and columns.
 * Guaranteed dense 0-indexed array where missing cells are filled with empty string ''.
 * 
 * @param string $filepath Path to the .xlsx file
 * @return array<int, array<int, mixed>> 2D array of rows
 */
function parse_xlsx(string $filepath): array
{
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        return [];
    }

    // 1. Load Shared Strings (xl/sharedStrings.xml)
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml === false) {
        // Case-insensitive search
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strcasecmp($name, 'xl/sharedStrings.xml') === 0) {
                $sharedXml = $zip->getFromIndex($i);
                break;
            }
        }
    }

    if ($sharedXml !== false) {
        $sst = simplexml_load_string($sharedXml);
        if ($sst && isset($sst->si)) {
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $sharedStrings[] = $text;
                } else {
                    $sharedStrings[] = (string)$si;
                }
            }
        }
    }

    // 2. Locate Worksheet XML
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name)) {
                $sheetXml = $zip->getFromIndex($i);
                break;
            }
        }
    }

    $zip->close();

    if ($sheetXml === false) {
        return [];
    }

    $ws = simplexml_load_string($sheetXml);
    if (!$ws || !isset($ws->sheetData) || !isset($ws->sheetData->row)) {
        return [];
    }

    // 3. Parse Rows & Cells
    $rawRows = [];
    $maxColIndex = 0;

    foreach ($ws->sheetData->row as $row) {
        $rowNum = isset($row['r']) ? (int)$row['r'] : count($rawRows) + 1;
        $rowIdx = $rowNum - 1;

        if (!isset($rawRows[$rowIdx])) {
            $rawRows[$rowIdx] = [];
        }

        $lastCol = -1;
        foreach ($row->c as $c) {
            $cellRef = (string)$c['r'];
            if (preg_match('/^([A-Z]+)(\d+)$/i', $cellRef, $matches)) {
                $colIdx = col_letter_to_index($matches[1]);
            } else {
                $colIdx = $lastCol + 1;
            }
            $lastCol = $colIdx;
            if ($colIdx > $maxColIndex) {
                $maxColIndex = $colIdx;
            }

            $type = isset($c['t']) ? (string)$c['t'] : 'n';
            $val = '';

            if ($type === 's') {
                $sIdx = (int)$c->v;
                $val = $sharedStrings[$sIdx] ?? '';
            } elseif ($type === 'inlineStr') {
                if (isset($c->is->t)) {
                    $val = (string)$c->is->t;
                } elseif (isset($c->is->r)) {
                    $txt = '';
                    foreach ($c->is->r as $r) {
                        $txt .= (string)$r->t;
                    }
                    $val = $txt;
                } else {
                    $val = (string)$c->is;
                }
            } elseif ($type === 'b') {
                $val = ((string)$c->v === '1') ? 1 : 0;
            } else {
                $val = (string)($c->v ?? '');
                if ($type === 'n' && is_numeric($val)) {
                    $val = (strpos($val, '.') !== false) ? (float)$val : (int)$val;
                }
            }

            $rawRows[$rowIdx][$colIdx] = $val;
        }
    }

    if (empty($rawRows)) {
        return [];
    }

    // 4. Normalize to dense 0-indexed rows
    ksort($rawRows);
    $denseRows = [];
    foreach ($rawRows as $rIdx => $cols) {
        $row = [];
        $hasContent = false;
        for ($c = 0; $c <= $maxColIndex; $c++) {
            $cellVal = $cols[$c] ?? '';
            $row[$c] = $cellVal;
            if ($cellVal !== '' && $cellVal !== null) {
                $hasContent = true;
            }
        }
        if ($hasContent) {
            $denseRows[] = $row;
        }
    }

    return $denseRows;
}

/**
 * Generate binary OpenXML (.xlsx) content from a 2D array of data.
 * Zero external libraries: uses native ZipArchive and compliant SpreadsheetML XML.
 *
 * @param array<int, array<int, mixed>> $data 2D array of rows and cell values
 * @param string $sheet_name Name of the worksheet tab (max 31 chars)
 * @return string Binary contents of the .xlsx file
 */
function create_xlsx(array $data, string $sheet_name = 'Sheet1'): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create temporary zip archive: $tmp");
    }

    // Clean sheet name
    $clean_sheet_name = preg_replace('/[\\\\\/\?\*\[\]\:]/', '', $sheet_name);
    $clean_sheet_name = mb_substr($clean_sheet_name, 0, 31, 'UTF-8');
    if (empty($clean_sheet_name)) {
        $clean_sheet_name = 'Sheet1';
    }

    // Process cells and build shared strings
    $sharedStrings = [];
    $sharedStringsMap = [];
    $totalStringRefs = 0;

    $sheetRowsXml = [];
    $rowNum = 1;

    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rowCellsXml = [];
        $colIdx = 0;
        foreach ($row as $val) {
            $colLetter = index_to_col_letter($colIdx);
            $cellRef = $colLetter . $rowNum;

            if ($val === null || $val === '') {
                // Empty cell: omit or empty tag
            } elseif (is_int($val) || is_float($val)) {
                $rowCellsXml[] = '<c r="' . $cellRef . '"><v>' . $val . '</v></c>';
            } elseif (is_numeric($val) && !preg_match('/^0\d+/', (string)$val)) {
                // Number without leading zero
                $rowCellsXml[] = '<c r="' . $cellRef . '"><v>' . $val . '</v></c>';
            } else {
                // Text string (including phone numbers starting with '0')
                $strVal = (string)$val;
                $totalStringRefs++;
                if (isset($sharedStringsMap[$strVal])) {
                    $sIndex = $sharedStringsMap[$strVal];
                } else {
                    $sIndex = count($sharedStrings);
                    $sharedStringsMap[$strVal] = $sIndex;
                    $sharedStrings[] = $strVal;
                }
                $rowCellsXml[] = '<c r="' . $cellRef . '" t="s"><v>' . $sIndex . '</v></c>';
            }
            $colIdx++;
        }

        if (!empty($rowCellsXml)) {
            $sheetRowsXml[] = '<row r="' . $rowNum . '">' . implode('', $rowCellsXml) . '</row>';
        }
        $rowNum++;
    }

    // 1. [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        '</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    // 2. _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);

    // 3. xl/_rels/workbook.xml.rels
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
        '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
        '</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

    // 4. xl/workbook.xml
    $safeSheetName = htmlspecialchars($clean_sheet_name, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="20480" windowHeight="10240"/></bookViews>' .
        '<sheets><sheet name="' . $safeSheetName . '" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbookXml);

    // 5. xl/styles.xml (Minimal valid styleSheet to satisfy MS Excel and LibreOffice)
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="1"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font></fonts>' .
        '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
        '</styleSheet>';
    $zip->addFromString('xl/styles.xml', $stylesXml);

    // 6. xl/sharedStrings.xml
    $uniqueCount = count($sharedStrings);
    $sstXmlParts = [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $totalStringRefs . '" uniqueCount="' . $uniqueCount . '">'
    ];
    foreach ($sharedStrings as $str) {
        $escaped = htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $sstXmlParts[] = '<si><t xml:space="preserve">' . $escaped . '</t></si>';
    }
    $sstXmlParts[] = '</sst>';
    $zip->addFromString('xl/sharedStrings.xml', implode('', $sstXmlParts));

    // 7. xl/worksheets/sheet1.xml
    $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<sheetData>' . implode('', $sheetRowsXml) . '</sheetData>' .
        '</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);

    $zip->close();

    $content = file_get_contents($tmp);
    unlink($tmp);

    return $content;
}

/**
 * Parse CSV file with automatic UTF-8 BOM stripping and delimiter detection (, vs ; vs tab).
 * 
 * @param string $filepath Path to the CSV file
 * @return array<int, array<int, string>> 2D array of rows
 */
function parse_csv(string $filepath): array
{
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return [];
    }

    $content = file_get_contents($filepath);
    if ($content === false || strlen($content) === 0) {
        return [];
    }

    // Strip UTF-8 BOM (\xEF\xBB\xBF) if present
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }

    // Auto-detect delimiter from the first non-empty line
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $firstLine = '';
    foreach ($lines as $line) {
        if (trim($line) !== '') {
            $firstLine = $line;
            break;
        }
    }

    $delimiters = [',', ';', "\t"];
    $delimiterCounts = [];
    foreach ($delimiters as $delim) {
        $delimiterCounts[$delim] = substr_count($firstLine, $delim);
    }
    arsort($delimiterCounts);
    $delimiter = key($delimiterCounts);
    if ($delimiterCounts[$delimiter] === 0) {
        $delimiter = ',';
    }

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $rows = [];
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        // Skip empty line
        if (count($data) === 1 && ($data[0] === null || trim($data[0]) === '')) {
            continue;
        }
        $rows[] = $data;
    }
    fclose($handle);

    return $rows;
}
