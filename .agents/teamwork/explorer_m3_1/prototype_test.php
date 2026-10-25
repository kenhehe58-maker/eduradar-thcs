<?php
/**
 * Prototype verification script for OpenXML parser & creator and CSV parser.
 * Runs in explorer workspace to prove correctness before handing off to implementer.
 */

// 1. Column index conversions
function index_to_col_letter(int $index): string {
    $letter = '';
    $index += 1; // 1-based
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $index = (int)(($index - 1) / 26);
    }
    return $letter;
}

function col_letter_to_index(string $col): int {
    $col = strtoupper(trim($col));
    $len = strlen($col);
    $index = 0;
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($col[$i]) - 64);
    }
    return $index - 1;
}

// 2. CSV Parser
function parse_csv(string $filepath): array {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return [];
    }

    $content = file_get_contents($filepath);
    if ($content === false || strlen($content) === 0) {
        return [];
    }

    // Strip UTF-8 BOM if present
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
        // Skip completely empty lines
        if (count($data) === 1 && $data[0] === null) {
            continue;
        }
        $rows[] = $data;
    }
    fclose($handle);

    return $rows;
}

// 3. OpenXML Creator
function create_xlsx(array $data, string $sheet_name = 'Sheet1'): string {
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create temporary zip archive: $tmp");
    }

    // Sanitize sheet name
    $clean_sheet_name = preg_replace('/[\\\\\/\?\*\[\]\:]/', '', $sheet_name);
    $clean_sheet_name = mb_substr($clean_sheet_name, 0, 31, 'UTF-8');
    if (empty($clean_sheet_name)) {
        $clean_sheet_name = 'Sheet1';
    }

    // Collect shared strings
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
                // empty cell
            } elseif (is_int($val) || is_float($val)) {
                $rowCellsXml[] = '<c r="' . $cellRef . '"><v>' . $val . '</v></c>';
            } elseif (is_numeric($val) && !preg_match('/^0\d+/', (string)$val)) {
                // Numeric value without leading zero
                $rowCellsXml[] = '<c r="' . $cellRef . '"><v>' . $val . '</v></c>';
            } else {
                // Text value (including phone numbers starting with '0')
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

    // 5. xl/styles.xml (Standard minimal styles)
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

// 4. OpenXML Parser
function parse_xlsx(string $filepath): array {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        return [];
    }

    // 1. Load shared strings
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
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

    // 2. Load worksheet (try sheet1.xml first)
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        // Search for any worksheet
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

    $rawRows = [];
    $maxColIndex = 0;

    foreach ($ws->sheetData->row as $row) {
        $rowNum = isset($row['r']) ? (int)$row['r'] : count($rawRows) + 1;
        $rowIdx = $rowNum - 1; // 0-based row index

        if (!isset($rawRows[$rowIdx])) {
            $rawRows[$rowIdx] = [];
        }

        $lastCol = -1;
        foreach ($row->c as $c) {
            $cellRef = (string)$c['r'];
            if (preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $matches)) {
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
                $val = (string)($c->is->t ?? '');
            } elseif ($type === 'b') {
                $val = ((string)$c->v === '1') ? 1 : 0;
            } else {
                // 'n', 'str', or default
                $val = (string)($c->v ?? '');
                if ($type === 'n' && is_numeric($val)) {
                    $val = (strpos($val, '.') !== false) ? (float)$val : (int)$val;
                }
            }

            $rawRows[$rowIdx][$colIdx] = $val;
        }
    }

    // Dense normalize: fill gaps up to max column, maintain 0-based sequential row indices
    if (empty($rawRows)) {
        return [];
    }

    ksort($rawRows);
    $denseRows = [];
    foreach ($rawRows as $rIdx => $cols) {
        $row = [];
        for ($c = 0; $c <= $maxColIndex; $c++) {
            $row[$c] = $cols[$c] ?? '';
        }
        $denseRows[] = $row;
    }

    return $denseRows;
}

// -------------------------------------------------------------
// TEST SUITE
// -------------------------------------------------------------
echo "Running Prototype Tests...\n";

// Test 1: Column conversion
assert(index_to_col_letter(0) === 'A');
assert(index_to_col_letter(1) === 'B');
assert(index_to_col_letter(25) === 'Z');
assert(index_to_col_letter(26) === 'AA');
assert(index_to_col_letter(27) === 'AB');
assert(col_letter_to_index('A') === 0);
assert(col_letter_to_index('B') === 1);
assert(col_letter_to_index('Z') === 25);
assert(col_letter_to_index('AA') === 26);
assert(col_letter_to_index('AB') === 27);
echo "[PASS] Test 1: Column letter <-> index conversion\n";

// Test 2: CSV Parsing on sample_students.csv
$root = dirname(__DIR__, 3);
$studentsCsv = parse_csv($root . '/sample_students.csv');
echo "[INFO] sample_students.csv row count: " . count($studentsCsv) . "\n";
assert(count($studentsCsv) === 6); // 1 header + 5 rows
assert($studentsCsv[0][0] === 'Họ và tên');
assert($studentsCsv[1][0] === 'Nguyễn Tuấn Kiệt');
assert($studentsCsv[1][2] === '0912345601');
echo "[PASS] Test 2: parse_csv with sample_students.csv\n";

// Test 3: CSV Parsing with UTF-8 BOM and Semicolon delimiter
$tmpCsv = tempnam(sys_get_temp_dir(), 'bom_test_') . '.csv';
$bomContent = "\xEF\xBB\xBF" . "STT;Họ và tên;Điểm GK;Điểm CK\n1;Lê Thị Hương;8,5;9,0\n2;Nguyễn Văn An;7,0;8,0\n";
file_put_contents($tmpCsv, $bomContent);
$parsedBomCsv = parse_csv($tmpCsv);
unlink($tmpCsv);
assert(count($parsedBomCsv) === 3);
assert($parsedBomCsv[0][0] === 'STT');
assert($parsedBomCsv[0][1] === 'Họ và tên');
assert($parsedBomCsv[1][1] === 'Lê Thị Hương');
assert($parsedBomCsv[1][2] === '8,5');
echo "[PASS] Test 3: parse_csv with UTF-8 BOM and semicolon auto-detection\n";

// Test 4: OpenXML create_xlsx and parse_xlsx round-trip
$testData = [
    ['STT', 'Mã học sinh', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'SĐT phụ huynh', 'Điểm TB'],
    [1, 'HS001', 'Nguyễn Tuấn Kiệt', '2015-04-12', 'Nam', '0912345601', 8.5],
    [2, 'HS002', 'Trần Phương Linh', '2015-08-23', 'Nữ', '0912345602', 9.2],
    [3, 'HS003', 'Vũ Đức Đam', '2015-01-19', 'Nam', '0912345603', 7.8],
    [4, 'HS004', 'Lê Hồng Nhung', '2015-11-05', 'Nữ', '0912345604', 6.5],
    [5, 'HS005', 'Phan Gia Hưng', '2015-06-30', 'Nam', '0912345605', 10.0]
];

$xlsxBinary = create_xlsx($testData, 'DanhSachHocSinh');
echo "[INFO] Generated XLSX binary size: " . strlen($xlsxBinary) . " bytes\n";
assert(strlen($xlsxBinary) > 1000);

$tmpXlsx = tempnam(sys_get_temp_dir(), 'test_roundtrip_') . '.xlsx';
file_put_contents($tmpXlsx, $xlsxBinary);

$parsedXlsx = parse_xlsx($tmpXlsx);
unlink($tmpXlsx);

assert(count($parsedXlsx) === 6);
assert($parsedXlsx[0][2] === 'Họ và tên');
assert($parsedXlsx[1][2] === 'Nguyễn Tuấn Kiệt');
assert($parsedXlsx[1][5] === '0912345601'); // Crucial: preserved leading zero in phone number!
assert($parsedXlsx[1][6] == 8.5);
assert($parsedXlsx[5][2] === 'Phan Gia Hưng');
assert($parsedXlsx[5][6] == 10.0);
echo "[PASS] Test 4: create_xlsx -> parse_xlsx round-trip successful\n";

// Test 5: Sparse rows and missing cells
$sparseData = [
    ['A', 'B', 'C', 'D'],
    ['valA2', '', 'valC2', 'valD2'],
    ['valA3', 'valB3']
];
$sparseBinary = create_xlsx($sparseData);
$tmpSparse = tempnam(sys_get_temp_dir(), 'test_sparse_') . '.xlsx';
file_put_contents($tmpSparse, $sparseBinary);
$parsedSparse = parse_xlsx($tmpSparse);
unlink($tmpSparse);

assert(count($parsedSparse) === 3);
assert($parsedSparse[1][0] === 'valA2');
assert($parsedSparse[1][1] === ''); // empty cell preserved
assert($parsedSparse[1][2] === 'valC2');
assert($parsedSparse[1][3] === 'valD2');
assert($parsedSparse[2][2] === ''); // dense array filled
echo "[PASS] Test 5: Sparse rows & empty cells correctly aligned\n";

echo "\n>>> ALL 5 TESTS PASSED SUCCESSFULLY! <<<\n";
