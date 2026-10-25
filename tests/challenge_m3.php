<?php
/**
 * =============================================================================
 * Milestone 3 Adversarial Challenge Suite (Empirical Test Harness)
 * 
 * Verifies:
 * - OpenXML Engine (includes/xlsx_helper.php):
 *   Malformed XML, empty files, non-ZIP inputs, corrupted sharedStrings,
 *   extreme column coordinates, sparse rows/cells, XML escaping, rich text,
 *   Vietnamese diacritics, worksheet name sanitizer, round-trip fidelity.
 * - CSV Engine:
 *   UTF-8 BOM stripping, delimiter auto-detection (; , \t), quoted strings.
 * - Date Normalization Engine:
 *   Leap years (2012, 2016, 2020, 2024, century 2000), non-leap years (2011, 2013, 1900),
 *   invalid calendar dates (31/04, month 13), empty/missing dates, Excel numeric serials.
 * - Admin Student Roster Import & Deduplication:
 *   Adversarial HTTP file uploads (0-byte, corrupt zip, empty sheet, no students),
 *   Repeated identical imports (1st, 2nd, 3rd, 4th CSV) -> exact MariaDB invariance,
 *   Zero duplicate entries verification via SQL GROUP BY,
 *   Fuzzy header detection across rows 0, 1, 2, 3, 4.
 * 
 * Execute via:
 *   D:\xam\php\php.exe tests/challenge_m3.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/xlsx_helper.php';

// ANSI Color formatting
const CLR_RESET  = "\033[0m";
const CLR_GREEN  = "\033[1;32m";
const CLR_RED    = "\033[1;31m";
const CLR_YELLOW = "\033[1;33m";
const CLR_CYAN   = "\033[1;36m";
const CLR_MAGENTA= "\033[1;35m";

$passedCount = 0;
$failedCount = 0;
$failures = [];

function assertChallenge(string $name, callable $testFn): void
{
    global $passedCount, $failedCount, $failures;
    echo CLR_CYAN . "[CHALLENGE] " . CLR_RESET . str_pad($name, 68, ".");
    try {
        $result = $testFn();
        if ($result === false) {
            throw new Exception("Assertion returned false");
        }
        $passedCount++;
        echo CLR_GREEN . " [PASS]" . CLR_RESET . PHP_EOL;
    } catch (Throwable $e) {
        $failedCount++;
        $failures[] = [
            'name' => $name,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
        echo CLR_RED . " [FAIL]" . CLR_RESET . PHP_EOL;
        echo CLR_RED . "            -> Error: " . $e->getMessage() . CLR_RESET . PHP_EOL;
    }
}

/**
 * HTTP Client supporting Session Cookies & Multipart File Uploads via cURL
 */
class ChallengerClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/chal_m3_cookie_' . bin2hex(random_bytes(6)) . '.txt';
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function clearCookies(): void
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function request(string $method, string $path, $data = [], bool $followRedirects = false): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 20,
        ];

        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $hasCurlFile = false;
            if (is_array($data)) {
                foreach ($data as $val) {
                    if ($val instanceof CURLFile) {
                        $hasCurlFile = true;
                        break;
                    }
                }
            }
            if ($hasCurlFile) {
                $options[CURLOPT_POSTFIELDS] = $data;
            } else {
                $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
            }
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL request to '$url' failed: $err");
        }

        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($ch);

        return [
            'status'       => (int)$info['http_code'],
            'redirect_url' => (string)($info['redirect_url'] ?? ''),
            'headers'      => $headers,
            'body'         => $body,
        ];
    }

    public function get(string $path, bool $followRedirects = false): array
    {
        return $this->request('GET', $path, [], $followRedirects);
    }

    public function post(string $path, array $data = [], bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $data, $followRedirects);
    }

    public function postMultipart(string $path, array $postFields, bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $postFields, $followRedirects);
    }

    public function login(string $username, string $password): array
    {
        return $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ], false);
    }
}

$client = new ChallengerClient('http://localhost/my-web-project');
$pdo = db();

echo PHP_EOL . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  EduRadar Milestone 3: EMPIRICAL ADVERSARIAL CHALLENGE SUITE" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// =============================================================================
// SUITE 1: OpenXML Engine Deep Robustness (includes/xlsx_helper.php)
// =============================================================================
echo CLR_MAGENTA . "--- SUITE 1: OpenXML Engine Deep Robustness (includes/xlsx_helper.php) ---" . CLR_RESET . PHP_EOL;

assertChallenge("1.1 parse_xlsx returns [] for non-existent file", function () {
    $res = parse_xlsx(__DIR__ . '/non_existent_' . uniqid() . '.xlsx');
    if ($res !== []) {
        throw new Exception("Expected [], got: " . var_export($res, true));
    }
    return true;
});

assertChallenge("1.2 parse_xlsx returns [] for 0-byte empty file", function () {
    $tmp = tempnam(sys_get_temp_dir(), 'empty_');
    file_put_contents($tmp, '');
    $res = parse_xlsx($tmp);
    @unlink($tmp);
    if ($res !== []) {
        throw new Exception("Expected [], got: " . var_export($res, true));
    }
    return true;
});

assertChallenge("1.3 parse_xlsx returns [] for non-ZIP random binary file", function () {
    $tmp = tempnam(sys_get_temp_dir(), 'corrupt_');
    file_put_contents($tmp, "NOT_A_ZIP_HEADER_" . random_bytes(256));
    $res = parse_xlsx($tmp);
    @unlink($tmp);
    if ($res !== []) {
        throw new Exception("Expected [], got: " . var_export($res, true));
    }
    return true;
});

assertChallenge("1.4 parse_xlsx returns [] for valid ZIP without worksheet XML", function () {
    $tmp = tempnam(sys_get_temp_dir(), 'no_sheet_') . '.zip';
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE);
    $zip->addFromString('hello.txt', 'This zip has no excel files');
    $zip->close();

    $res = parse_xlsx($tmp);
    @unlink($tmp);
    if ($res !== []) {
        throw new Exception("Expected [], got: " . var_export($res, true));
    }
    return true;
});

assertChallenge("1.5 parse_xlsx handles corrupted sheet1.xml gracefully without crash", function () {
    $tmp = tempnam(sys_get_temp_dir(), 'bad_xml_') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE);
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet><sheetData><unclosed_tag>');
    $zip->close();

    // Use libxml error suppression internally to test resilience
    $prev = libxml_use_internal_errors(true);
    $res = parse_xlsx($tmp);
    libxml_use_internal_errors($prev);
    @unlink($tmp);

    if ($res !== []) {
        throw new Exception("Expected [] on corrupted XML, got: " . var_export($res, true));
    }
    return true;
});

assertChallenge("1.6 parse_xlsx handles out-of-bounds shared string index safely", function () {
    $tmp = tempnam(sys_get_temp_dir(), 'bad_sst_') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE);
    // Shared strings has only 1 item (index 0)
    $sst = '<?xml version="1.0" encoding="UTF-8"?><sst count="1" uniqueCount="1"><si><t>Hợp Lệ</t></si></sst>';
    $zip->addFromString('xl/sharedStrings.xml', $sst);
    // Sheet references index 99999 for A1, and valid number 999 for B1
    $sheet = '<?xml version="1.0" encoding="UTF-8"?>
    <worksheet>
      <sheetData>
        <row r="1">
          <c r="A1" t="s"><v>99999</v></c>
          <c r="B1"><v>999</v></c>
        </row>
      </sheetData>
    </worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $res = parse_xlsx($tmp);
    @unlink($tmp);

    if (empty($res) || count($res) !== 1) {
        throw new Exception("Expected 1 row, got: " . var_export($res, true));
    }
    if ($res[0][0] !== '') {
        throw new Exception("Expected out-of-bounds index to resolve to empty string '', got: " . var_export($res[0][0], true));
    }
    if ($res[0][1] !== 999) {
        throw new Exception("Expected cell B1 to be 999, got: " . var_export($res[0][1], true));
    }
    return true;
});

assertChallenge("1.7 parse_xlsx handles lowercase cell refs (a1, b2) & missing r attributes", function () {
    $tmp = tempnam(sys_get_temp_dir(), 'case_r_') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE);
    $sheet = '<?xml version="1.0" encoding="UTF-8"?>
    <worksheet>
      <sheetData>
        <row r="1">
          <c r="a1"><v>100</v></c>
          <c r="b1"><v>200</v></c>
        </row>
        <row r="2">
          <c><v>300</v></c>
          <c><v>400</v></c>
        </row>
      </sheetData>
    </worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $res = parse_xlsx($tmp);
    @unlink($tmp);

    if (count($res) !== 2 || $res[0][0] !== 100 || $res[0][1] !== 200 || $res[1][0] !== 300 || $res[1][1] !== 400) {
        throw new Exception("Failed to parse lowercase or missing 'r' attributes: " . var_export($res, true));
    }
    return true;
});

assertChallenge("1.8 parse_xlsx normalizes sparse cells & rows without missing columns", function () {
    $tmp = tempnam(sys_get_temp_dir(), 'sparse_') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE);
    // Row 1 has A1 and D1; Row 3 has B3
    $sheet = '<?xml version="1.0" encoding="UTF-8"?>
    <worksheet>
      <sheetData>
        <row r="1">
          <c r="A1"><v>10</v></c>
          <c r="D1"><v>40</v></c>
        </row>
        <row r="3">
          <c r="B3"><v>25</v></c>
        </row>
      </sheetData>
    </worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $res = parse_xlsx($tmp);
    @unlink($tmp);

    // Expect 2 non-empty rows, each with 4 columns (indices 0..3)
    if (count($res) !== 2) {
        throw new Exception("Expected 2 dense rows, got: " . count($res));
    }
    // Row 1: [0 => 10, 1 => '', 2 => '', 3 => 40]
    if ($res[0][0] !== 10 || $res[0][1] !== '' || $res[0][2] !== '' || $res[0][3] !== 40) {
        throw new Exception("Row 1 sparse layout corrupted: " . var_export($res[0], true));
    }
    // Row 2: [0 => '', 1 => 25, 2 => '', 3 => '']
    if ($res[1][0] !== '' || $res[1][1] !== 25 || $res[1][2] !== '' || $res[1][3] !== '') {
        throw new Exception("Row 2 sparse layout corrupted: " . var_export($res[1], true));
    }
    return true;
});

assertChallenge("1.9 parse_xlsx supports rich text (<si><r><t>...) and inlineStr", function () {
    $tmp = tempnam(sys_get_temp_dir(), 'rich_') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE);
    // Shared strings with rich text formatting
    $sst = '<?xml version="1.0" encoding="UTF-8"?>
    <sst count="1" uniqueCount="1">
      <si>
        <r><rPr><b/></rPr><t>Phần 1 - </t></r>
        <r><rPr><i/></rPr><t>Phần 2</t></r>
      </si>
    </sst>';
    $zip->addFromString('xl/sharedStrings.xml', $sst);

    // Sheet with inlineStr
    $sheet = '<?xml version="1.0" encoding="UTF-8"?>
    <worksheet>
      <sheetData>
        <row r="1">
          <c r="A1" t="s"><v>0</v></c>
          <c r="B1" t="inlineStr"><is><t>Chuỗi Trực Tiếp</t></is></c>
          <c r="C1" t="b"><v>1</v></c>
        </row>
      </sheetData>
    </worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $res = parse_xlsx($tmp);
    @unlink($tmp);

    if ($res[0][0] !== 'Phần 1 - Phần 2') {
        throw new Exception("Rich text concatenation failed: expected 'Phần 1 - Phần 2', got: '" . ($res[0][0] ?? '') . "'");
    }
    if ($res[0][1] !== 'Chuỗi Trực Tiếp') {
        throw new Exception("inlineStr failed: expected 'Chuỗi Trực Tiếp', got: '" . ($res[0][1] ?? '') . "'");
    }
    if ($res[0][2] !== 1) {
        throw new Exception("Boolean t='b' failed: expected 1, got: " . var_export($res[0][2], true));
    }
    return true;
});

assertChallenge("1.10 create_xlsx round-trips XML entity characters (<>&\"') and leading zeroes", function () {
    $specialData = [
        ['Tên', 'Ký Tự Đặc Biệt', 'SĐT Có Số 0'],
        ['Học Sinh & Bạn', '<script>alert("XSS")</script>', '0912345678'],
        ['"Kép" và \'Đơn\'', 'A & B > C < D', '00123']
    ];
    $binary = create_xlsx($specialData, 'Trang-1');
    $tmp = tempnam(sys_get_temp_dir(), 'xml_entity_') . '.xlsx';
    file_put_contents($tmp, $binary);

    $parsed = parse_xlsx($tmp);
    @unlink($tmp);

    if ($parsed[1][0] !== 'Học Sinh & Bạn') {
        throw new Exception("Entity & corrupted: " . $parsed[1][0]);
    }
    if ($parsed[1][1] !== '<script>alert("XSS")</script>') {
        throw new Exception("XSS chars corrupted: " . $parsed[1][1]);
    }
    if ($parsed[1][2] !== '0912345678') {
        throw new Exception("Leading zero phone dropped: expected '0912345678', got '" . $parsed[1][2] . "'");
    }
    if ($parsed[2][0] !== '"Kép" và \'Đơn\'') {
        throw new Exception("Quotes corrupted: " . $parsed[2][0]);
    }
    if ($parsed[2][1] !== 'A & B > C < D') {
        throw new Exception("Inequalities corrupted: " . $parsed[2][1]);
    }
    if ($parsed[2][2] !== '00123') {
        throw new Exception("Student code leading zero dropped: expected '00123', got '" . $parsed[2][2] . "'");
    }
    return true;
});

assertChallenge("1.11 create_xlsx sanitizes invalid sheet name characters (/\\?*[]:) and lengths", function () {
    $data = [['A', 'B'], [1, 2]];
    $badSheetName = 'Lớp/6A1?*:[Học Sinh]-Dài-Hơn-31-Ký-Tự-Rất-Nhiều';
    $binary = create_xlsx($data, $badSheetName);
    $tmp = tempnam(sys_get_temp_dir(), 'sheet_name_') . '.xlsx';
    file_put_contents($tmp, $binary);

    $zip = new ZipArchive();
    $zip->open($tmp);
    $wbXml = $zip->getFromName('xl/workbook.xml');
    $zip->close();
    @unlink($tmp);

    if ($wbXml === false) {
        throw new Exception("Workbook XML missing");
    }
    if (!preg_match('/<sheet name="([^"]+)"/', $wbXml, $m)) {
        throw new Exception("Sheet name attribute not found in workbook.xml");
    }
    $actualSheetName = $m[1];
    // Must not contain invalid characters: /\?*[]:
    if (preg_match('/[\\\\\/\?\*\[\]\:]/', $actualSheetName)) {
        throw new Exception("Sheet name contains unescaped invalid characters: " . $actualSheetName);
    }
    // Tab length must be <= 31 UTF-8 chars
    if (mb_strlen($actualSheetName, 'UTF-8') > 31) {
        throw new Exception("Sheet name exceeds 31 UTF-8 chars: " . mb_strlen($actualSheetName, 'UTF-8'));
    }
    return true;
});

assertChallenge("1.12 index_to_col_letter & col_letter_to_index bijectivity across boundaries", function () {
    $testCases = [
        0 => 'A',
        1 => 'B',
        25 => 'Z',
        26 => 'AA',
        27 => 'AB',
        51 => 'AZ',
        52 => 'BA',
        701 => 'ZZ',
        702 => 'AAA',
        16383 => 'XFD' // Excel max column
    ];
    foreach ($testCases as $idx => $expectedCol) {
        $actualCol = index_to_col_letter($idx);
        if ($actualCol !== $expectedCol) {
            throw new Exception("index_to_col_letter($idx): expected '$expectedCol', got '$actualCol'");
        }
        $actualIdx = col_letter_to_index($expectedCol);
        if ($actualIdx !== $idx) {
            throw new Exception("col_letter_to_index('$expectedCol'): expected $idx, got $actualIdx");
        }
        // Test lowercase resilience
        $lowerIdx = col_letter_to_index(strtolower($expectedCol));
        if ($lowerIdx !== $idx) {
            throw new Exception("col_letter_to_index(strtolower('$expectedCol')): expected $idx, got $lowerIdx");
        }
    }
    return true;
});

// =============================================================================
// SUITE 2: CSV Engine Adversarial Testing (includes/xlsx_helper.php)
// =============================================================================
echo PHP_EOL . CLR_MAGENTA . "--- SUITE 2: CSV Engine Adversarial Testing (parse_csv) ---" . CLR_RESET . PHP_EOL;

assertChallenge("2.1 parse_csv returns [] for non-existent and empty files", function () {
    if (parse_csv(__DIR__ . '/non_existent.csv') !== []) {
        throw new Exception("Non-existent CSV did not return []");
    }
    $tmp = tempnam(sys_get_temp_dir(), 'empty_csv_');
    file_put_contents($tmp, '');
    $res = parse_csv($tmp);
    @unlink($tmp);
    if ($res !== []) {
        throw new Exception("Empty CSV did not return []");
    }
    return true;
});

assertChallenge("2.2 parse_csv detects semicolon (;) and tab (\\t) delimiters automatically", function () {
    // Semicolon
    $semiContent = "STT;Họ và tên;Ngày sinh\n1;Vũ Đức Đam;2015-05-10\n2;Trần Thu Hà;2015-08-20";
    $tmp1 = tempnam(sys_get_temp_dir(), 'semi_') . '.csv';
    file_put_contents($tmp1, $semiContent);
    $res1 = parse_csv($tmp1);
    @unlink($tmp1);

    if (count($res1) !== 3 || $res1[1][1] !== 'Vũ Đức Đam') {
        throw new Exception("Semicolon delimiter failed: " . var_export($res1, true));
    }

    // Tab
    $tabContent = "STT\tHọ và tên\tNgày sinh\n1\tNguyễn Du\t2015-01-01\n2\tLê Lợi\t2015-02-02";
    $tmp2 = tempnam(sys_get_temp_dir(), 'tab_') . '.csv';
    file_put_contents($tmp2, $tabContent);
    $res2 = parse_csv($tmp2);
    @unlink($tmp2);

    if (count($res2) !== 3 || $res2[1][1] !== 'Nguyễn Du') {
        throw new Exception("Tab delimiter failed: " . var_export($res2, true));
    }
    return true;
});

assertChallenge("2.3 parse_csv handles quotes containing delimiters and newlines", function () {
    $quotedContent = "\"Họ và tên\",\"Ghi chú\"\n\"Nguyễn, Văn A\",\"Đạt giải Nhất; Toán\"\n\"Trần Thị B\",\"Dòng 1\nDòng 2\"";
    $tmp = tempnam(sys_get_temp_dir(), 'quoted_') . '.csv';
    file_put_contents($tmp, $quotedContent);
    $res = parse_csv($tmp);
    @unlink($tmp);

    if (count($res) !== 3) {
        throw new Exception("Quoted multi-line CSV row count mismatch: expected 3 rows, got " . count($res));
    }
    if ($res[1][0] !== 'Nguyễn, Văn A') {
        throw new Exception("Comma inside quotes was split: '" . $res[1][0] . "'");
    }
    if ($res[2][1] !== "Dòng 1\nDòng 2") {
        throw new Exception("Newline inside quotes corrupted: " . var_export($res[2][1], true));
    }
    return true;
});

// =============================================================================
// SUITE 3: Date Parsing & Normalization Engine (normalize_excel_date / normalize_vn_date)
// =============================================================================
echo PHP_EOL . CLR_MAGENTA . "--- SUITE 3: Date Parsing & Normalization Engine Exhaustive Verification ---" . CLR_RESET . PHP_EOL;

assertChallenge("3.1 Leap year dates (2012, 2016, 2020, 2024, 2000) normalize to YYYY-02-29", function () {
    $leapYears = [
        '29/02/2012' => '2012-02-29',
        '29/2/2012'  => '2012-02-29',
        '29-02-2016' => '2016-02-29',
        '29.02.2020' => '2020-02-29',
        '29/02/2024' => '2024-02-29',
        '29/02/2000' => '2000-02-29', // 400-year century leap year
        '2012-02-29' => '2012-02-29', // ISO
        '2016/02/29' => '2016-02-29',
    ];
    foreach ($leapYears as $raw => $expected) {
        $actual = normalize_excel_date($raw);
        if ($actual !== $expected) {
            throw new Exception("Leap year '$raw' failed: expected '$expected', got '$actual'");
        }
        $vnActual = normalize_vn_date($raw);
        if ($vnActual !== $expected) {
            throw new Exception("normalize_vn_date('$raw') failed: expected '$expected', got '$vnActual'");
        }
    }
    return true;
});

assertChallenge("3.2 Non-leap year 29/02 dates (2011, 2013, 2015, 1900) return null and sentinel", function () {
    $invalidLeapDays = ['29/02/2011', '29/02/2013', '29/02/2015', '29/02/1900', '29-02-2021', '29.02.2023'];
    foreach ($invalidLeapDays as $raw) {
        $actual = normalize_excel_date($raw);
        if ($actual !== null) {
            throw new Exception("Invalid leap day '$raw' was incorrectly accepted as: '$actual'");
        }
        $vnActual = normalize_vn_date($raw);
        if ($vnActual !== '1970-01-01') {
            throw new Exception("normalize_vn_date('$raw') did not fallback to sentinel '1970-01-01': got '$vnActual'");
        }
    }
    return true;
});

assertChallenge("3.3 Out-of-bounds calendar days (31/04, 32/01, month 13) rejected gracefully", function () {
    $impossibleDates = ['31/04/2015', '31/06/2015', '31/09/2015', '31/11/2015', '32/01/2015', '15/13/2015', '00/05/2015'];
    foreach ($impossibleDates as $raw) {
        $actual = normalize_excel_date($raw);
        if ($actual !== null) {
            throw new Exception("Impossible date '$raw' was incorrectly accepted as: '$actual'");
        }
        $vnActual = normalize_vn_date($raw);
        if ($vnActual !== '1970-01-01') {
            throw new Exception("normalize_vn_date('$raw') expected '1970-01-01', got '$vnActual'");
        }
    }
    return true;
});

assertChallenge("3.4 Missing, null, empty, whitespace dates fallback to '1970-01-01' sentinel", function () {
    $emptyInputs = [null, '', '   ', "\t", "\n"];
    foreach ($emptyInputs as $raw) {
        if (normalize_excel_date($raw) !== null) {
            throw new Exception("Empty input did not return null");
        }
        if (normalize_vn_date($raw) !== '1970-01-01') {
            throw new Exception("Empty input did not return '1970-01-01'");
        }
    }
    return true;
});

assertChallenge("3.5 Excel serial numbers (40968 = 2012-02-29, 42106 = 2015-04-12, 42075 = 2015-03-12)", function () {
    $serials = [
        40968 => '2012-02-29', // Leap day serial!
        '40968' => '2012-02-29',
        42106 => '2015-04-12',
        '42075' => '2015-03-12',
        43831 => '2020-01-01',
    ];
    foreach ($serials as $raw => $expected) {
        $actual = normalize_excel_date($raw);
        if ($actual !== $expected) {
            throw new Exception("Excel serial '$raw' failed: expected '$expected', got '$actual'");
        }
    }
    return true;
});

// =============================================================================
// SUITE 4: Adversarial HTTP File Uploads to admin.php
// =============================================================================
echo PHP_EOL . CLR_MAGENTA . "--- SUITE 4: Adversarial HTTP File Uploads to admin.php ---" . CLR_RESET . PHP_EOL;

assertChallenge("4.1 Upload 0-byte file with .xlsx extension handled without crash or DB change", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $tmp = tempnam(sys_get_temp_dir(), 'zero_') . '.xlsx';
    file_put_contents($tmp, '');

    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    $res = $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 1,
        'student_file'    => new CURLFile($tmp, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'zero.xlsx')
    ]);
    @unlink($tmp);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("0-byte file caused unexpected student row insertion!");
    }
    if ($res['status'] !== 200 && $res['status'] !== 302) {
        throw new Exception("HTTP status not 200/302: " . $res['status']);
    }
    if (strpos($res['body'], 'Fatal error') !== false || strpos($res['body'], 'Uncaught') !== false) {
        throw new Exception("Upload produced fatal PHP error: " . substr($res['body'], 0, 300));
    }
    return true;
});

assertChallenge("4.2 Upload corrupted non-ZIP file with .xlsx extension displays error safely", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $tmp = tempnam(sys_get_temp_dir(), 'corrupt_') . '.xlsx';
    file_put_contents($tmp, "CORRUPT_HEADER_" . random_bytes(512));

    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    $res = $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 1,
        'student_file'    => new CURLFile($tmp, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'corrupt.xlsx')
    ]);
    @unlink($tmp);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("Corrupt file caused student insertion!");
    }
    // Verify UI displays error feedback
    if (strpos($res['body'], 'không có nội dung') === false && strpos($res['body'], 'không thể đọc') === false) {
        throw new Exception("Expected error message for unreadable file not found in response HTML");
    }
    return true;
});

assertChallenge("4.3 Upload valid XLSX with empty worksheet (0 rows) reports no data", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $emptyXlsx = create_xlsx([]);
    $tmp = tempnam(sys_get_temp_dir(), 'empty_ws_') . '.xlsx';
    file_put_contents($tmp, $emptyXlsx);

    $res = $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 1,
        'student_file'    => new CURLFile($tmp, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'empty_sheet.xlsx')
    ]);
    @unlink($tmp);

    if (strpos($res['body'], 'không có nội dung') === false && strpos($res['body'], 'không thể đọc') === false) {
        throw new Exception("Expected error message for empty sheet not found in response");
    }
    return true;
});

assertChallenge("4.4 Upload file with headers only (no student rows) rejected without insert", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $headersOnly = create_xlsx([
        ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại']
    ]);
    $tmp = tempnam(sys_get_temp_dir(), 'headers_only_') . '.xlsx';
    file_put_contents($tmp, $headersOnly);

    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    $res = $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 1,
        'student_file'    => new CURLFile($tmp, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'headers_only.xlsx')
    ]);
    @unlink($tmp);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("Headers-only file created student rows!");
    }
    if (strpos($res['body'], 'Không tìm thấy dữ liệu học sinh') === false) {
        throw new Exception("Expected 'Không tìm thấy dữ liệu học sinh' error, got response: " . substr($res['body'], 0, 300));
    }
    return true;
});

// =============================================================================
// SUITE 5: Roster Deduplication Invariance in MariaDB
// =============================================================================
echo PHP_EOL . CLR_MAGENTA . "--- SUITE 5: Student Roster Deduplication & MariaDB Invariance ---" . CLR_RESET . PHP_EOL;

$dedupTestClassId = 36; // Use class 9C9 (id 36) or dedicated class for tests
// Clean class 36 before starting deduplication tests
$pdo->prepare("DELETE FROM students WHERE class_id = ?")->execute([$dedupTestClassId]);

// Build a challenging roster with leap years, missing birthdays, serial dates, and in-file duplicates
$adversarialRoster = [
    ['STT', 'Mã định danh', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại'],
    ['1', 'HS9C901', 'Nguyễn Hải Đăng', '29/02/2012', 'Nam', '0911223344'], // Leap year 2012!
    ['2', 'HS9C902', 'Trần Thu Hà', '15/05/2012', 'Nữ', '0922334455'],
    ['3', 'HS9C903', 'Lê Hoàng Phúc', '42075', 'Nam', '0933445566'],         // Excel serial (2015-03-12)
    ['4', 'HS9C904', 'Phạm Minh Tuấn', '', 'Nam', '0944556677'],              // Missing birthday -> '1970-01-01'
    ['5', 'HS9C905', 'Phạm Minh Tuấn', '10/10/2012', 'Nam', '0955667788'],    // Same name, distinct birthday!
    ['6', 'HS9C901', 'Nguyễn Hải Đăng', '29/02/2012', 'Nam', '0911223344'], // IN-FILE DUPLICATE OF ROW 1!
    ['7', 'HS9C907', 'Đặng Thảo Vy', '2012-12-01', 'Nữ', '0966778899'],      // ISO date
    ['8', 'HS9C908', 'Vũ Gia Huy', '29/02/2016', 'Nam', '0977889900'],        // Leap year 2016!
];

$adversarialXlsx = create_xlsx($adversarialRoster, 'DSHS_9C9');
$adversarialXlsxPath = sys_get_temp_dir() . '/adversarial_roster_9c9.xlsx';
file_put_contents($adversarialXlsxPath, $adversarialXlsx);

assertChallenge("5.1 First Import: inserts exactly 7 unique students (in-file duplicate skipped)", function () use ($client, $pdo, $dedupTestClassId, $adversarialXlsxPath) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $res = $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => $dedupTestClassId,
        'student_file'    => new CURLFile($adversarialXlsxPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'adversarial_roster.xlsx')
    ]);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $dedupTestClassId")->fetchColumn();
    // 8 rows minus 1 in-file duplicate = 7 unique students
    if ($count !== 7) {
        throw new Exception("Expected exactly 7 students inserted on first import, found $count");
    }
    return true;
});

assertChallenge("5.2 Second Import (identical XLSX): 0 new rows, student count strictly invariant", function () use ($client, $pdo, $dedupTestClassId, $adversarialXlsxPath) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $dedupTestClassId")->fetchColumn();

    $res = $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => $dedupTestClassId,
        'student_file'    => new CURLFile($adversarialXlsxPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'adversarial_roster.xlsx')
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $dedupTestClassId")->fetchColumn();
    if ($beforeCount !== 7 || $afterCount !== 7) {
        throw new Exception("Deduplication failed on 2nd import: before=$beforeCount, after=$afterCount (expected 7)");
    }
    // Verify feedback message
    if (strpos($res['body'], 'bỏ qua 7') === false && strpos($res['body'], 'bỏ qua 8') === false && strpos($res['body'], 'đã có sẵn') === false && strpos($res['body'], 'đã tồn tại') === false) {
        throw new Exception("Feedback message did not report skipped duplicates");
    }
    return true;
});

assertChallenge("5.3 Third Import (identical XLSX): 0 new rows, count remains strictly 7", function () use ($client, $pdo, $dedupTestClassId, $adversarialXlsxPath) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $res = $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => $dedupTestClassId,
        'student_file'    => new CURLFile($adversarialXlsxPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'adversarial_roster.xlsx')
    ]);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $dedupTestClassId")->fetchColumn();
    if ($count !== 7) {
        throw new Exception("Count mutated on 3rd import: $count");
    }
    return true;
});

assertChallenge("5.4 Fourth Import via CSV: 0 new rows, cross-format deduplication preserved", function () use ($client, $pdo, $dedupTestClassId) {
    $client->clearCookies();
    $client->login('admin', '123456');

    // Create CSV equivalent of the same roster
    $csvContent = "STT,Mã định danh,Họ và tên,Ngày sinh,Giới tính,Số điện thoại\n" .
        "1,HS9C901,Nguyễn Hải Đăng,2012-02-29,Nam,0911223344\n" .
        "2,HS9C902,Trần Thu Hà,2012-05-15,Nữ,0922334455\n" .
        "3,HS9C903,Lê Hoàng Phúc,2015-03-12,Nam,0933445566\n" .
        "4,HS9C904,Phạm Minh Tuấn,,Nam,0944556677\n" .
        "5,HS9C905,Phạm Minh Tuấn,2012-10-10,Nam,0955667788\n" .
        "7,HS9C907,Đặng Thảo Vy,2012-12-01,Nữ,0966778899\n" .
        "8,HS9C908,Vũ Gia Huy,2016-02-29,Nam,0977889900\n";
    $csvTmp = sys_get_temp_dir() . '/adversarial_roster_9c9.csv';
    file_put_contents($csvTmp, $csvContent);

    $res = $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => $dedupTestClassId,
        'student_file'    => new CURLFile($csvTmp, 'text/csv', 'adversarial_roster_9c9.csv')
    ]);
    @unlink($csvTmp);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $dedupTestClassId")->fetchColumn();
    if ($count !== 7) {
        throw new Exception("Count mutated on CSV re-import: expected 7, got $count");
    }
    return true;
});

assertChallenge("5.5 SQL Verification: MariaDB students table contains ZERO duplicate rows", function () use ($pdo, $dedupTestClassId) {
    $stmt = $pdo->prepare("
        SELECT full_name, birthday, COUNT(*) as cnt 
        FROM students 
        WHERE class_id = ? 
        GROUP BY full_name, birthday 
        HAVING cnt > 1
    ");
    $stmt->execute([$dedupTestClassId]);
    $duplicates = $stmt->fetchAll();

    if (!empty($duplicates)) {
        throw new Exception("CRITICAL DEDUPLICATION FAILURE: Duplicate students found in MariaDB! " . json_encode($duplicates));
    }
    return true;
});

assertChallenge("5.6 Missing birthday student is stored with '1970-01-01' and never duplicated", function () use ($pdo, $dedupTestClassId) {
    $stmt = $pdo->prepare("SELECT id, birthday FROM students WHERE class_id = ? AND full_name = 'Phạm Minh Tuấn' AND birthday = '1970-01-01'");
    $stmt->execute([$dedupTestClassId]);
    $rows = $stmt->fetchAll();

    if (count($rows) !== 1) {
        throw new Exception("Expected exactly 1 record for missing-birthday student, found " . count($rows));
    }
    return true;
});

assertChallenge("5.7 Leap year students stored with exact YYYY-02-29 dates in MariaDB", function () use ($pdo, $dedupTestClassId) {
    $s1 = $pdo->query("SELECT birthday FROM students WHERE class_id = $dedupTestClassId AND full_name = 'Nguyễn Hải Đăng'")->fetchColumn();
    if ($s1 !== '2012-02-29') {
        throw new Exception("Leap year birthday 2012-02-29 mismatch: '$s1'");
    }
    $s2 = $pdo->query("SELECT birthday FROM students WHERE class_id = $dedupTestClassId AND full_name = 'Vũ Gia Huy'")->fetchColumn();
    if ($s2 !== '2016-02-29') {
        throw new Exception("Leap year birthday 2016-02-29 mismatch: '$s2'");
    }
    return true;
});

assertChallenge("5.8 In-place phone update on re-import without creating duplicate rows", function () use ($client, $pdo, $dedupTestClassId) {
    $client->clearCookies();
    $client->login('admin', '123456');

    // Update phone of Trần Thu Hà from '0922334455' to '0988776655'
    $updateData = [
        ['STT', 'Họ và tên', 'Ngày sinh', 'Số điện thoại'],
        ['1', 'Trần Thu Hà', '15/05/2012', '0988776655']
    ];
    $tmp = sys_get_temp_dir() . '/update_phone_' . uniqid() . '.xlsx';
    file_put_contents($tmp, create_xlsx($updateData));

    $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => $dedupTestClassId,
        'student_file'    => new CURLFile($tmp, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'update.xlsx')
    ]);
    @unlink($tmp);

    // Total count must still be 7
    $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $dedupTestClassId")->fetchColumn();
    if ($count !== 7) {
        throw new Exception("Updating student phone mutated total student count: $count (expected 7)");
    }
    // Phone must be updated in database
    $phone = $pdo->query("SELECT phone FROM students WHERE class_id = $dedupTestClassId AND full_name = 'Trần Thu Hà'")->fetchColumn();
    if ($phone !== '0988776655') {
        throw new Exception("Phone was not updated in DB: expected '0988776655', got '$phone'");
    }
    return true;
});

@unlink($adversarialXlsxPath);

// =============================================================================
// SUITE 6: Fuzzy Header Detection on Diverse Rows (0, 1, 2, 3, 4)
// =============================================================================
echo PHP_EOL . CLR_MAGENTA . "--- SUITE 6: Fuzzy Header Detection across Rows 0, 1, 2, 3, 4 ---" . CLR_RESET . PHP_EOL;

$headerPositions = [
    0 => 'Header on Row 0 (Immediate data table)',
    1 => 'Header on Row 1 (1 title line above)',
    2 => 'Header on Row 2 (School name + Report title)',
    3 => 'Header on Row 3 (School + Department + Academic year)',
    4 => 'Header on Row 4 (4 metadata banner rows)',
];

foreach ($headerPositions as $headerRow => $desc) {
    assertChallenge("6." . ($headerRow + 1) . " Fuzzy header tolerance: $desc", function () use ($client, $pdo, $headerRow) {
        $client->clearCookies();
        $client->login('admin', '123456');

        $testClassId = 35; // 9C8
        $pdo->prepare("DELETE FROM students WHERE class_id = ?")->execute([$testClassId]);

        $rows = [];
        // Add metadata lines
        for ($m = 0; $m < $headerRow; $m++) {
            $rows[] = ["DÒNG TIÊU ĐỀ THÔNG TIN TRƯỜNG PHÒNG GD SỐ " . ($m + 1)];
        }
        // Add header row with varied Vietnamese phrasing
        $rows[] = ['Số TT', 'Mã số', 'Họ tên học sinh', 'Ngày/tháng/năm sinh', 'Phái', 'Điện thoại phụ huynh'];
        // Add student records
        $rows[] = ['1', 'HS9C801', 'Hoàng Minh Châu', '2012-04-18', 'Nữ', '0912111222'];
        $rows[] = ['2', 'HS9C802', 'Lý Hải Đăng', '2012-09-30', 'Nam', '0912333444'];

        $tmp = sys_get_temp_dir() . '/test_header_row_' . $headerRow . '.xlsx';
        file_put_contents($tmp, create_xlsx($rows));

        $client->postMultipart('admin.php', [
            'action'          => 'import_students',
            'target_class_id' => $testClassId,
            'student_file'    => new CURLFile($tmp, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'header_test.xlsx')
        ]);
        @unlink($tmp);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $testClassId")->fetchColumn();
        if ($count !== 2) {
            throw new Exception("Header on row $headerRow failed: expected 2 students in class $testClassId, found $count");
        }
        $name = $pdo->query("SELECT full_name FROM students WHERE class_id = $testClassId ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($name !== 'Hoàng Minh Châu') {
            throw new Exception("First student name mismatch: '$name'");
        }
        return true;
    });
}

// =============================================================================
// SUITE 7: RBAC, Audit Logging & Final Cleanup
// =============================================================================
echo PHP_EOL . CLR_MAGENTA . "--- SUITE 7: RBAC Guard, Audit Logging & System Invariants ---" . CLR_RESET . PHP_EOL;

assertChallenge("7.1 Unauthenticated student import upload is strictly blocked (302/403)", function () use ($client, $pdo) {
    $client->clearCookies();

    $dummyXlsx = create_xlsx([['STT', 'Họ và tên'], [1, 'Hacker Student']]);
    $tmp = tempnam(sys_get_temp_dir(), 'hack_') . '.xlsx';
    file_put_contents($tmp, $dummyXlsx);

    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE full_name = 'Hacker Student'")->fetchColumn();

    $res = $client->postMultipart('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 1,
        'student_file'    => new CURLFile($tmp, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'hack.xlsx')
    ]);
    @unlink($tmp);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE full_name = 'Hacker Student'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("CRITICAL SECURITY FLAW: Unauthenticated request inserted a student!");
    }
    if ($res['status'] !== 302 && $res['status'] !== 403) {
        throw new Exception("Expected HTTP 302 or 403, got " . $res['status']);
    }
    return true;
});

assertChallenge("7.2 Audit log records action='Import học sinh' with details on every import", function () use ($pdo) {
    $log = $pdo->query("SELECT * FROM audit_logs WHERE action = 'Import học sinh' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$log) {
        throw new Exception("No audit log entry found for 'Import học sinh'");
    }
    if (empty($log['details']) || strpos($log['details'], 'thêm mới') === false) {
        throw new Exception("Audit log details missing metrics: " . json_encode($log));
    }
    return true;
});

// Clean up test students in test classes (classes 35 and 36)
try {
    $pdo->prepare("DELETE FROM students WHERE class_id IN (35, 36)")->execute();
} catch (Throwable $e) {
    // Ignore cleanup error
}

// =============================================================================
// SUMMARY & VERDICT GENERATION
// =============================================================================
echo PHP_EOL . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL;
echo sprintf("  Total Adversarial Challenges: %d | Passed: %s%d%s | Failed: %s%d%s",
    $passedCount + $failedCount,
    CLR_GREEN, $passedCount, CLR_RESET,
    ($failedCount > 0 ? CLR_RED : CLR_GREEN), $failedCount, CLR_RESET
) . PHP_EOL;

if ($failedCount === 0) {
    echo CLR_GREEN . "  VERDICT: APPROVE" . CLR_RESET . PHP_EOL;
    echo "  All adversarial edge cases passed with zero duplicate records and zero fatal errors." . PHP_EOL;
} else {
    echo CLR_RED . "  VERDICT: REQUEST_CHANGES" . CLR_RESET . PHP_EOL;
    echo "  $failedCount adversarial challenge(s) failed." . PHP_EOL;
    foreach ($failures as $f) {
        echo CLR_RED . "  - {$f['name']}: {$f['error']} in {$f['file']}:{$f['line']}" . CLR_RESET . PHP_EOL;
    }
}
echo CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($failedCount > 0) {
    exit(1);
}
exit(0);
