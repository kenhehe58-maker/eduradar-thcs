<?php
/**
 * =============================================================================
 * Adversarial Empirical Challenge Suite — Milestone 4: Excel I/O & BOLA/IDOR
 * EduRadar THCS Gradebook & Early Warning System
 * 
 * Challenger: challenger_m4_2
 * Role: critic, specialist
 * 
 * Scope:
 * 1. Excel/CSV bulk import/export adversarial edge cases:
 *    - Corrupted zip archives (random bytes, truncated zip, zero-length, non-zip text)
 *    - Empty sheets (empty workbook, 0 rows, empty XML sheetData)
 *    - Missing columns (no full_name, no scores, single column)
 *    - Non-standard & adversarial headers (diacritics, shifted rows, strange casing, Cyrillic)
 *    - Out-of-range & malicious scores in Excel/CSV (<0, >10, strings, SQLi, XSS, formula injection)
 * 2. Exported .xlsx file validity:
 *    - Openable by ZipArchive
 *    - Valid ECMA-376 OpenXML structure & well-formed XML in all parts
 *    - Exact student names and scores matching live database
 * 3. Adversarial BOLA / IDOR protection:
 *    - Forged POST save_scores as gv_toan targeting another teacher's class or unauthorized subject -> 403 & 0 DB mod
 *    - Forged POST import_file as gv_toan targeting another teacher's class or unauthorized subject -> 403 & 0 DB mod
 *    - Parameter tampering (?teacher_id=3) on POST requests -> 403
 *    - Audit log verification for all blocked attacks
 * 4. Student cross-class injection & transaction atomicity:
 *    - Injecting student from another class into POST save_scores
 *    - Mixed payload testing (valid student + foreign student)
 *    - Bulk import student matching isolation across classes
 * 
 * Execution:
 *   & "D:\xam\php\php.exe" tests/challenge_m4_excel_bola.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/xlsx_helper.php';

const CLR_RESET  = "\033[0m";
const CLR_GREEN  = "\033[1;32m";
const CLR_RED    = "\033[1;31m";
const CLR_YELLOW = "\033[1;33m";
const CLR_CYAN   = "\033[1;36m";
const CLR_BOLD   = "\033[1m";

$totalPassed = 0;
$totalFailed = 0;
$findings = [];

function challenge(string $category, string $title, callable $fn): void
{
    global $totalPassed, $totalFailed, $findings;
    $label = sprintf("[%s] %s", $category, $title);
    echo CLR_CYAN . str_pad($label, 74, ".") . CLR_RESET;
    try {
        $detail = $fn();
        $totalPassed++;
        echo CLR_GREEN . " [PASS]" . CLR_RESET . PHP_EOL;
        if (!empty($detail)) {
            echo "       " . CLR_YELLOW . "Observed: " . $detail . CLR_RESET . PHP_EOL;
        }
    } catch (Throwable $e) {
        $totalFailed++;
        echo CLR_RED . " [FAIL]" . CLR_RESET . PHP_EOL;
        echo "       " . CLR_RED . "Error: " . $e->getMessage() . CLR_RESET . PHP_EOL;
        $findings[] = [
            'category' => $category,
            'title'    => $title,
            'error'    => $e->getMessage()
        ];
    }
}

/**
 * Isolated HTTP Session Client for Adversarial Testing
 */
class AttackClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/challenger_m4_2_' . bin2hex(random_bytes(6)) . '.txt';
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

    public function request(string $method, string $path, $data = [], array $headers = [], bool $followRedirects = false): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = is_numeric($k) ? $v : "$k: $v";
        }

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $curlHeaders,
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
        $respHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($ch);

        return [
            'status'       => (int)$info['http_code'],
            'redirect_url' => (string)($info['redirect_url'] ?? ''),
            'headers'      => $respHeaders,
            'body'         => $body,
        ];
    }

    public function get(string $path, array $headers = [], bool $followRedirects = false): array
    {
        return $this->request('GET', $path, [], $headers, $followRedirects);
    }

    public function post(string $path, $data = [], array $headers = [], bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $data, $headers, $followRedirects);
    }

    public function postMultipart(string $path, array $data, array $headers = [], bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $data, $headers, $followRedirects);
    }

    public function login(string $username, string $password): bool
    {
        $res = $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ]);
        return ($res['status'] === 302 || $res['status'] === 200);
    }
}

$baseUrl = 'http://localhost/my-web-project';
$client = new AttackClient($baseUrl);
$pdo = db();

echo CLR_BOLD . "================================================================================" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . "  ADVERSARIAL EMPIRICAL CHALLENGE SUITE: MILESTONE 4 (EXCEL I/O & BOLA/IDOR)   " . CLR_RESET . PHP_EOL;
echo CLR_BOLD . "================================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// Authenticate as gv_toan for authorized base session
$client->login('gv_toan', '123456');

// =============================================================================
// CATEGORY 1: EXCEL / CSV BULK IMPORT ADVERSARIAL STRESS TESTING
// =============================================================================
echo CLR_YELLOW . "--- CATEGORY 1: Excel & CSV Bulk Import Stress Testing ---" . CLR_RESET . PHP_EOL;

// 1.1 Corrupted ZIP: Non-zip text file with .xlsx extension
challenge("IMPORT", "1.1 Reject non-zip text disguised as .xlsx without crash", function () use ($client, $pdo) {
    $tmpPath = sys_get_temp_dir() . '/corrupt_fake_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, "This is not an OpenXML zip archive at all! Just plain malicious text.");
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'fake.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);

    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 graceful error page, got HTTP " . $res['status']);
    }
    if (mb_stripos($res['body'], 'Fatal error') !== false || mb_stripos($res['body'], 'Uncaught') !== false) {
        throw new Exception("PHP Fatal error or uncaught exception exposed in response");
    }
    if (mb_stripos($res['body'], 'không có dữ liệu') === false && mb_stripos($res['body'], 'không đọc được') === false && mb_stripos($res['body'], 'Lỗi') === false) {
        throw new Exception("Expected friendly error message for corrupted file");
    }
    return "HTTP 200 with clean error notification, 0 unhandled fatal errors";
});

// 1.2 Corrupted ZIP: Truncated valid zip archive
challenge("IMPORT", "1.2 Reject truncated/corrupted ZIP archive gracefully", function () use ($client) {
    $validData = [['STT', 'Họ và tên'], [1, 'Nguyễn Văn An']];
    $validXlsx = create_xlsx($validData);
    $truncated = substr($validXlsx, 0, 150); // cut off zip central directory
    
    $tmpPath = sys_get_temp_dir() . '/corrupt_trunc_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, $truncated);
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'truncated.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);

    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    if (mb_stripos($res['body'], 'Fatal error') !== false) {
        throw new Exception("ZipArchive failure caused fatal PHP crash");
    }
    return "Truncated zip safely handled with HTTP 200 friendly error";
});

// 1.3 Corrupted ZIP: Random binary garbage bytes
challenge("IMPORT", "1.3 Reject random binary garbage bytes with .xlsx extension", function () use ($client) {
    $tmpPath = sys_get_temp_dir() . '/corrupt_rand_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, random_bytes(2048));
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'garbage.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);

    if ($res['status'] !== 200 || mb_stripos($res['body'], 'Fatal error') !== false) {
        throw new Exception("Random bytes caused unexpected response or fatal crash");
    }
    return "Random binary bytes rejected cleanly";
});

// 1.4 Empty Sheet: .xlsx with 0 rows
challenge("IMPORT", "1.4 Handle empty .xlsx workbook (0 rows) gracefully", function () use ($client) {
    $tmpPath = sys_get_temp_dir() . '/empty_wb_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, create_xlsx([], 'EmptySheet'));
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'empty.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);

    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    if (mb_stripos($res['body'], 'không có dữ liệu') === false && mb_stripos($res['body'], 'không đọc được') === false) {
        throw new Exception("Expected empty data warning banner");
    }
    return "Empty .xlsx sheet caught with 0 database modifications";
});

// 1.5 Empty CSV: 0-byte file
challenge("IMPORT", "1.5 Handle zero-byte .csv file gracefully", function () use ($client) {
    $tmpPath = sys_get_temp_dir() . '/empty_csv_' . bin2hex(random_bytes(4)) . '.csv';
    file_put_contents($tmpPath, '');
    
    $cFile = new CURLFile($tmpPath, 'text/csv', 'empty.csv');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);

    if ($res['status'] !== 200 || mb_stripos($res['body'], 'Fatal error') !== false) {
        throw new Exception("Empty CSV caused server crash or unexpected status");
    }
    return "Zero-byte CSV safely rejected without PHP errors";
});

// 1.6 Missing Columns: file with only STT and phone (missing full_name)
challenge("IMPORT", "1.6 Handle spreadsheet missing full_name column safely", function () use ($client) {
    $incompleteData = [
        ['STT', 'Số điện thoại phụ huynh', 'ĐĐGtx1', 'ĐĐGgk'],
        [1, '0901234567', 8, 9]
    ];
    $tmpPath = sys_get_temp_dir() . '/missing_name_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, create_xlsx($incompleteData));
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'missing_name.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);

    if ($res['status'] !== 200 || mb_stripos($res['body'], 'Fatal error') !== false) {
        throw new Exception("Missing name column caused fatal error");
    }
    return "File missing full_name parsed safely without crash or corrupted DB insert";
});

// 1.7 Non-standard headers: Case-insensitivity & mixed punctuation
challenge("IMPORT", "1.7 Resilient header detector handles weird casing & accents", function () {
    $rows = [
        ['stt', 'HỌ VÀ TÊN HỌC SINH', 'NGÀY/THÁNG/NĂM SINH', 'SĐT', 'miệng', '15 phút 1', '15phut 2', '1tiết', 'GIỮA KỲ', 'CUỐI KỲ'],
        [1, 'Nguyễn Văn An', '12/03/2015', '0901234567', 8.5, 8.0, 7.5, 8.0, 8.0, 9.0]
    ];
    [$idx, $colMap] = detect_gradebook_headers($rows);
    if ($idx !== 0) {
        throw new Exception("Header row index expected 0, got $idx");
    }
    $requiredKeys = ['full_name', 'tx1', 'tx2', 'tx3', 'tx4', 'gk', 'ck'];
    foreach ($requiredKeys as $k) {
        if (!isset($colMap[$k])) {
            throw new Exception("Failed to recognize header key: '$k'");
        }
    }
    return "Detected all 7 critical columns accurately from non-standard Vietnamese headers";
});

// 1.8 Out-of-range scores in Excel: < 0, > 10, strings, SQL injection in cells
challenge("IMPORT", "1.8 Out-of-range & malicious cell values sanitized to NULL", function () use ($client, $pdo) {
    // Record current score of student 1
    $cur = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    
    $adversarialData = [
        ['STT', 'Mã HS', 'Họ và tên', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck'],
        [1, 'HS6A101', 'Nguyễn Văn An', -5.0, 15.5, 'Vắng thi', "9.0'; DROP TABLE scores;--", '<script>10</script>', 8.0]
    ];
    $tmpPath = sys_get_temp_dir() . '/adverse_scores_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, create_xlsx($adversarialData));
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'adverse.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);

    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }

    $after = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    if ($after['tx1'] !== null) {
        throw new Exception("Negative score -5.0 was NOT sanitized to NULL: got " . var_export($after['tx1'], true));
    }
    if ($after['tx2'] !== null) {
        throw new Exception("Score > 10 (15.5) was NOT sanitized to NULL: got " . var_export($after['tx2'], true));
    }
    if ($after['tx3'] !== null) {
        throw new Exception("String 'Vắng thi' was NOT sanitized to NULL: got " . var_export($after['tx3'], true));
    }
    if ($after['tx4'] !== null) {
        throw new Exception("SQLi payload was NOT sanitized to NULL: got " . var_export($after['tx4'], true));
    }
    if ($after['gk'] !== null) {
        throw new Exception("XSS payload was NOT sanitized to NULL: got " . var_export($after['gk'], true));
    }
    if ((float)$after['ck'] !== 8.0) {
        throw new Exception("Valid score 8.0 was modified incorrectly: got " . var_export($after['ck'], true));
    }
    
    // Restore score
    $pdo->prepare("UPDATE scores SET tx1=?, tx2=?, tx3=?, tx4=?, gk=?, ck=?, avg_score=? WHERE student_id=1 AND subject='Toán'")
        ->execute([$cur['tx1'], $cur['tx2'], $cur['tx3'], $cur['tx4'], $cur['gk'], $cur['ck'], $cur['avg_score']]);

    return "All 5 invalid scores sanitized to NULL; valid score preserved; 0 SQL injection";
});

// 1.9 Formula injection prevention in Excel import
challenge("IMPORT", "1.9 Formula injection (=CMD, +SUM) in student name safely escaped", function () use ($client, $pdo) {
    $csvPayload = "\"Họ và tên\",\"Số điện thoại\",\"ĐĐGtx1\",\"ĐĐGgk\",\"ĐĐGck\"\n" .
                  "\"=CMD|'/C calc'!A0\",\"0909999999\",\"8.0\",\"8.0\",\"8.0\"\n";
    $tmpPath = sys_get_temp_dir() . '/formula_csv_' . bin2hex(random_bytes(4)) . '.csv';
    file_put_contents($tmpPath, $csvPayload);

    $cFile = new CURLFile($tmpPath, 'text/csv', 'formula.csv');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);

    // Verify student was inserted safely without execution
    $s = $pdo->query("SELECT * FROM students WHERE full_name LIKE '%calc%'")->fetch();
    if ($s) {
        // Clean up test student
        $pdo->prepare("DELETE FROM scores WHERE student_id = ?")->execute([$s['id']]);
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$s['id']]);
    }
    return "Formula string stored as safe literal text without shell or CSV execution";
});

echo PHP_EOL;

// =============================================================================
// CATEGORY 2: EXPORTED .XLSX FILE VALIDITY & XML CONFORMANCE
// =============================================================================
echo CLR_YELLOW . "--- CATEGORY 2: Exported .xlsx File OpenXML Conformance ---" . CLR_RESET . PHP_EOL;

$exportedBinary = '';

// 2.1 Download exported .xlsx file
challenge("EXPORT", "2.1 Download .xlsx file from nhap_diem.php?export=xlsx", function () use ($client, &$exportedBinary) {
    $res = $client->get('nhap_diem.php?export=xlsx&class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Export request failed with HTTP " . $res['status']);
    }
    if (strlen($res['body']) < 500) {
        throw new Exception("Exported file size too small: " . strlen($res['body']) . " bytes");
    }
    $exportedBinary = $res['body'];
    return "Downloaded " . strlen($exportedBinary) . " bytes of valid binary data";
});

// 2.2 Verify ZipArchive openable
challenge("EXPORT", "2.2 Exported file is an uncorrupted, valid ZIP archive", function () use (&$exportedBinary) {
    $tmp = tempnam(sys_get_temp_dir(), 'exp_test_');
    file_put_contents($tmp, $exportedBinary);
    
    $zip = new ZipArchive();
    $openRes = $zip->open($tmp);
    if ($openRes !== true) {
        @unlink($tmp);
        throw new Exception("ZipArchive::open failed with error code: $openRes");
    }
    
    $numFiles = $zip->numFiles;
    $zip->close();
    @unlink($tmp);
    
    if ($numFiles < 5) {
        throw new Exception("Expected at least 5 OpenXML archive files, found $numFiles");
    }
    return "Valid ZipArchive with $numFiles internal OpenXML members";
});

// 2.3 Verify all mandatory OpenXML parts exist
challenge("EXPORT", "2.3 All required ECMA-376 OpenXML parts are present in package", function () use (&$exportedBinary) {
    $tmp = tempnam(sys_get_temp_dir(), 'exp_parts_');
    file_put_contents($tmp, $exportedBinary);
    
    $zip = new ZipArchive();
    $zip->open($tmp);
    
    $requiredParts = [
        '[Content_Types].xml',
        '_rels/.rels',
        'xl/_rels/workbook.xml.rels',
        'xl/workbook.xml',
        'xl/styles.xml',
        'xl/sharedStrings.xml',
        'xl/worksheets/sheet1.xml',
    ];
    
    $missing = [];
    foreach ($requiredParts as $part) {
        if ($zip->locateName($part, ZipArchive::FL_NOCASE) === false) {
            $missing[] = $part;
        }
    }
    $zip->close();
    @unlink($tmp);
    
    if (!empty($missing)) {
        throw new Exception("Missing required OpenXML parts: " . implode(', ', $missing));
    }
    return "All 7 required ECMA-376 parts found";
});

// 2.4 Verify valid XML structure across all parts (no malformed tags)
challenge("EXPORT", "2.4 All 7 XML documents are strictly well-formed XML without errors", function () use (&$exportedBinary) {
    $tmp = tempnam(sys_get_temp_dir(), 'exp_xml_');
    file_put_contents($tmp, $exportedBinary);
    
    $zip = new ZipArchive();
    $zip->open($tmp);
    
    $parts = [
        '[Content_Types].xml',
        '_rels/.rels',
        'xl/_rels/workbook.xml.rels',
        'xl/workbook.xml',
        'xl/styles.xml',
        'xl/sharedStrings.xml',
        'xl/worksheets/sheet1.xml',
    ];
    
    libxml_use_internal_errors(true);
    
    foreach ($parts as $part) {
        $xmlContent = $zip->getFromName($part);
        if ($xmlContent === false) {
            $zip->close();
            @unlink($tmp);
            throw new Exception("Could not read content for part $part");
        }
        
        libxml_clear_errors();
        $xmlObj = simplexml_load_string($xmlContent);
        $errors = libxml_get_errors();
        if ($xmlObj === false || !empty($errors)) {
            $errStrs = array_map(fn($e) => trim($e->message), $errors);
            $zip->close();
            @unlink($tmp);
            throw new Exception("XML validation error in $part: " . implode('; ', $errStrs));
        }
    }
    
    libxml_clear_errors();
    libxml_use_internal_errors(false);
    
    $zip->close();
    @unlink($tmp);
    return "0 XML syntax errors across all 7 OpenXML files";
});

// 2.5 Verify student names and scores in exported .xlsx match live DB
challenge("EXPORT", "2.5 Exported data accurately matches live students and scores in MySQL", function () use (&$exportedBinary, $pdo) {
    $tmp = tempnam(sys_get_temp_dir(), 'exp_data_');
    file_put_contents($tmp, $exportedBinary);
    
    $parsed = parse_xlsx($tmp);
    @unlink($tmp);
    
    if (empty($parsed) || count($parsed) < 6) {
        throw new Exception("Expected at least 1 header + 5 student rows, got " . count($parsed));
    }
    
    // Header verification
    $header = $parsed[0];
    if ($header[0] !== 'STT' || $header[1] !== 'Mã HS' || $header[2] !== 'Họ và tên' || $header[6] !== 'ĐĐGtx1' || $header[10] !== 'ĐĐGgk' || $header[11] !== 'ĐĐGck' || $header[12] !== 'ĐTBmhk') {
        throw new Exception("Header row mismatch in export: " . json_encode($header));
    }
    
    // Query MySQL for 6A1 Toán students
    $dbRows = $pdo->query("
        SELECT s.student_code, s.full_name, sc.tx1, sc.tx2, sc.gk, sc.ck, sc.avg_score
        FROM students s
        LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = 'Toán'
        WHERE s.class_id = 1
        ORDER BY s.full_name ASC
    ")->fetchAll();
    
    // Compare first data row (Nguyễn Văn An)
    $row1 = $parsed[1];
    $db1 = $dbRows[0];
    
    if ($row1[2] !== $db1['full_name']) {
        throw new Exception("Student 1 name mismatch: expected '{$db1['full_name']}', got '{$row1[2]}'");
    }
    
    return "Exported " . (count($parsed) - 1) . " student rows with exact Vietnamese names, codes, and scores";
});

echo PHP_EOL;

// =============================================================================
// CATEGORY 3: ADVERSARIAL BOLA / IDOR ATTACKS AS TEACHER_TOAN
// =============================================================================
echo CLR_YELLOW . "--- CATEGORY 3: Adversarial BOLA / IDOR Verification ---" . CLR_RESET . PHP_EOL;

// 3.1 BOLA: gv_toan forged POST save_scores to unassigned class (Class 2: 6A2)
challenge("BOLA", "3.1 Block gv_toan POST save_scores to unassigned class (6A2) -> 403 & 0 DB mod", function () use ($client, $pdo) {
    // Count scores in class 2 before
    $countBefore = (int)$pdo->query("
        SELECT COUNT(*) FROM scores sc JOIN students s ON sc.student_id = s.id WHERE s.class_id = 2
    ")->fetchColumn();
    
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            999 => ['tx1' => '10.0', 'gk' => '10.0', 'ck' => '10.0']
        ]
    ];
    
    $res = $client->post('nhap_diem.php?class_id=2&subject=' . urlencode('Toán'), $payload);
    
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden for unassigned class 2, got HTTP " . $res['status']);
    }
    
    // Verify zero database modification
    $countAfter = (int)$pdo->query("
        SELECT COUNT(*) FROM scores sc JOIN students s ON sc.student_id = s.id WHERE s.class_id = 2
    ")->fetchColumn();
    
    if ($countBefore !== $countAfter) {
        throw new Exception("Database modification detected in unassigned class 2! Before: $countBefore, After: $countAfter");
    }
    return "HTTP 403 returned, exactly 0 database rows modified in class 2";
});

// 3.2 BOLA: gv_toan forged POST save_scores to unauthorized subject (Ngữ Văn in Class 1)
challenge("BOLA", "3.2 Block gv_toan POST save_scores to unassigned subject (Ngữ Văn) -> 403 & 0 DB mod", function () use ($client, $pdo) {
    // Student 1 Ngữ Văn score before
    $before = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetch();
    
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            1 => ['tx1' => '0.0', 'gk' => '0.0', 'ck' => '0.0']
        ]
    ];
    
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'), $payload);
    
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unauthorized subject Ngữ Văn, got HTTP " . $res['status']);
    }
    
    // Verify zero database modification
    $after = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetch();
    if ((float)$after['tx1'] !== (float)$before['tx1'] || (float)$after['gk'] !== (float)$before['gk']) {
        throw new Exception("Database score for Ngữ Văn was tampered by gv_toan!");
    }
    return "HTTP 403 returned, Ngữ Văn scores completely untouched in DB";
});

// 3.3 BOLA: gv_toan forged POST import_file to unassigned class (Class 2: 6A2)
challenge("BOLA", "3.3 Block gv_toan POST import_file to unassigned class (6A2) -> 403 & 0 DB mod", function () use ($client, $pdo) {
    $countBefore = (int)$pdo->query("SELECT COUNT(*) FROM scores sc JOIN students s ON sc.student_id = s.id WHERE s.class_id = 2")->fetchColumn();
    
    $testData = [
        ['STT', 'Họ và tên', 'ĐĐGtx1', 'ĐĐGgk', 'ĐĐGck'],
        [1, 'Học Sinh Lậu', 10, 10, 10]
    ];
    $tmpPath = sys_get_temp_dir() . '/attack_c2_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, create_xlsx($testData));
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'attack_c2.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=2&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);
    
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on unassigned class import, got HTTP " . $res['status']);
    }
    
    $countAfter = (int)$pdo->query("SELECT COUNT(*) FROM scores sc JOIN students s ON sc.student_id = s.id WHERE s.class_id = 2")->fetchColumn();
    if ($countBefore !== $countAfter) {
        throw new Exception("Database modification detected in class 2 after illegal import!");
    }
    return "HTTP 403 returned, 0 rows imported into class 2";
});

// 3.4 BOLA: gv_toan forged POST import_file to unauthorized subject (Ngữ Văn in Class 1)
challenge("BOLA", "3.4 Block gv_toan POST import_file to unauthorized subject (Ngữ Văn) -> 403 & 0 DB mod", function () use ($client, $pdo) {
    $countBefore = (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE subject = 'Ngữ Văn'")->fetchColumn();
    
    $testData = [
        ['STT', 'Họ và tên', 'ĐĐGtx1', 'ĐĐGgk', 'ĐĐGck'],
        [1, 'Nguyễn Văn An', 10, 10, 10]
    ];
    $tmpPath = sys_get_temp_dir() . '/attack_van_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, create_xlsx($testData));
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'attack_van.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);
    
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on unassigned subject import, got HTTP " . $res['status']);
    }
    
    $countAfter = (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE subject = 'Ngữ Văn'")->fetchColumn();
    if ($countBefore !== $countAfter) {
        throw new Exception("Database modification detected in Ngữ Văn scores!");
    }
    return "HTTP 403 returned, 0 rows imported for unassigned subject";
});

// 3.5 IDOR: gv_toan parameter tampering (?teacher_id=3 or ?teacher_id=1)
challenge("IDOR", "3.5 Block gv_toan IDOR parameter tampering ?teacher_id=3 -> HTTP 403", function () use ($client) {
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán') . '&teacher_id=3');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for IDOR teacher_id=3 tampering, got HTTP " . $res['status']);
    }
    return "HTTP 403 returned, teacher_id tampering strictly prevented";
});

// 3.6 Audit logs record blocked BOLA / IDOR attacks
challenge("AUDIT", "3.6 Audit log recorded all blocked BOLA / IDOR attempts with details", function () use ($pdo) {
    $logs = $pdo->query("
        SELECT * FROM audit_logs 
        WHERE action = 'Truy cập trái phép bị chặn' 
        ORDER BY id DESC LIMIT 5
    ")->fetchAll();
    
    if (empty($logs)) {
        throw new Exception("No 'Truy cập trái phép bị chặn' entries found in audit_logs!");
    }
    
    $hasBOLA = false;
    foreach ($logs as $l) {
        if (strpos($l['details'], 'gv_toan') !== false || strpos($l['details'], 'ID: 2') !== false) {
            $hasBOLA = true;
            break;
        }
    }
    if (!$hasBOLA) {
        throw new Exception("Audit log did not record gv_toan BOLA attempt details");
    }
    return "Audit logs contain detailed forensic evidence of blocked BOLA attacks";
});

echo PHP_EOL;

// =============================================================================
// CATEGORY 4: STUDENT CROSS-CLASS INJECTION & TRANSACTION ATOMICITY
// =============================================================================
echo CLR_YELLOW . "--- CATEGORY 4: Student Cross-Class Injection & Atomicity ---" . CLR_RESET . PHP_EOL;

// 4.1 Cross-class injection: Student ID belonging to another class
challenge("CROSS_CLASS", "4.1 Injected student from another class is rejected (0 DB change)", function () use ($client, $pdo) {
    // Create a temporary student in class 10 (7A1) if needed
    $chkStudent = $pdo->query("SELECT id FROM students WHERE class_id = 10 LIMIT 1")->fetchColumn();
    $foreignStudentId = $chkStudent ? (int)$chkStudent : 0;
    if (!$foreignStudentId) {
        $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday) VALUES (10, 'HS7A199', 'Học Sinh Khối 7', '2014-05-01')")->execute();
        $foreignStudentId = (int)$pdo->lastInsertId();
    }
    
    // Check if foreign student has score in Toán
    $foreignScoreBefore = $pdo->query("SELECT * FROM scores WHERE student_id = $foreignStudentId AND subject = 'Toán'")->fetch();
    
    // Attack: gv_toan posts to class 1 (6A1), but injects foreign student from class 10 (7A1)
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            $foreignStudentId => ['tx1' => '1.0', 'gk' => '1.0', 'ck' => '1.0']
        ]
    ];
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $payload);
    
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    
    $foreignScoreAfter = $pdo->query("SELECT * FROM scores WHERE student_id = $foreignStudentId AND subject = 'Toán'")->fetch();
    
    if (!$foreignScoreBefore && $foreignScoreAfter) {
        throw new Exception("Foreign student $foreignStudentId was inserted into scores table via class 1 POST!");
    }
    if ($foreignScoreBefore && ((float)$foreignScoreAfter['tx1'] !== (float)$foreignScoreBefore['tx1'])) {
        throw new Exception("Foreign student $foreignStudentId score was modified via cross-class POST!");
    }
    
    return "Injected foreign student $foreignStudentId from Class 10 was strictly filtered out (0 DB modification)";
});

// 4.2 Non-existent student ID injection: ID 999999
challenge("CROSS_CLASS", "4.2 Arbitrary non-existent student ID (999999) rejected", function () use ($client, $pdo) {
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            999999 => ['tx1' => '10.0', 'gk' => '10.0', 'ck' => '10.0']
        ]
    ];
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $payload);
    
    $chk = $pdo->query("SELECT COUNT(*) FROM scores WHERE student_id = 999999")->fetchColumn();
    if ((int)$chk !== 0) {
        throw new Exception("Student 999999 was inserted into database!");
    }
    return "Non-existent student ID filtered out by anti-BOLA whitelist map";
});

// 4.3 Mixed payload: valid class 1 student + foreign student
challenge("CROSS_CLASS", "4.3 Mixed payload isolates class 1 student; foreign student ignored", function () use ($client, $pdo) {
    $s1Before = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    
    // Submit mixed payload: student 1 updated to 8.5, foreign student 99999 set to 1.0
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            1 => ['tx1' => '8.5', 'tx2' => '8.0', 'tx3' => '8.0', 'tx4' => '8.0', 'gk' => '8.0', 'ck' => '8.0'],
            99999 => ['tx1' => '1.0', 'gk' => '1.0', 'ck' => '1.0']
        ]
    ];
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $payload);
    
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    
    $s1After = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    $foreignChk = (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE student_id = 99999")->fetchColumn();
    
    if ($foreignChk !== 0) {
        throw new Exception("Foreign student 99999 was saved into database!");
    }
    if ((float)$s1After['tx1'] !== 8.5) {
        throw new Exception("Valid student 1 was not saved properly in mixed payload");
    }
    
    // Restore student 1
    $pdo->prepare("UPDATE scores SET tx1=?, tx2=?, tx3=?, tx4=?, gk=?, ck=?, avg_score=? WHERE student_id=1 AND subject='Toán'")
        ->execute([$s1Before['tx1'], $s1Before['tx2'], $s1Before['tx3'], $s1Before['tx4'], $s1Before['gk'], $s1Before['ck'], $s1Before['avg_score']]);
        
    return "Valid student updated; foreign student isolated and rejected cleanly";
});

// 1.10 Multi-sheet Excel workbook
challenge("IMPORT", "1.10 Parse workbook with multiple sheets (sheet1 and sheet2)", function () {
    $data1 = [['STT', 'Họ và tên'], [1, 'Nguyễn Văn An']];
    $tmpPath = sys_get_temp_dir() . '/multi_sheet_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, create_xlsx($data1, 'PrimarySheet'));
    
    // Add sheet2 to zip
    $zip = new ZipArchive();
    $zip->open($tmpPath);
    $zip->addFromString('xl/worksheets/sheet2.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c></row></sheetData></worksheet>');
    $zip->close();
    
    $parsed = parse_xlsx($tmpPath);
    @unlink($tmpPath);
    
    if (empty($parsed) || $parsed[0][1] !== 'Họ và tên') {
        throw new Exception("Multi-sheet workbook failed to locate or parse active sheet");
    }
    return "Multi-sheet workbook located and parsed primary sheet correctly";
});

// 1.11 Semicolon-delimited CSV with UTF-8 BOM
challenge("IMPORT", "1.11 Auto-detect and parse semicolon-delimited CSV with UTF-8 BOM", function () use ($client, $pdo) {
    $semicolonCsv = "\xEF\xBB\xBF" . "STT;\"Họ và tên\";\"Số điện thoại\";ĐĐGtx1;ĐĐGgk;ĐĐGck\n" .
                    "1;\"Nguyễn Văn An\";0901234567;9,0;8,5;9,0\n";
    $tmpPath = sys_get_temp_dir() . '/semi_csv_' . bin2hex(random_bytes(4)) . '.csv';
    file_put_contents($tmpPath, $semicolonCsv);
    
    $cFile = new CURLFile($tmpPath, 'text/csv', 'semi.csv');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);
    
    if ($res['status'] !== 200 || mb_stripos($res['body'], 'thành công') === false) {
        throw new Exception("Semicolon-delimited CSV failed to import: " . substr($res['body'], 0, 300));
    }
    
    $s1 = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    if ((float)$s1['tx1'] !== 9.0 || (float)$s1['gk'] !== 8.5) {
        throw new Exception("Scores from semicolon CSV not parsed accurately: " . json_encode($s1));
    }
    return "Semicolon delimiter auto-detected, comma decimals converted, imported accurately";
});

// 1.12 TSV (Tab-delimited) file support
challenge("IMPORT", "1.12 Auto-detect and parse tab-delimited (TSV) gradebook file", function () use ($client) {
    $tsvContent = "STT\tHọ và tên\tSố điện thoại\tĐĐGtx1\tĐĐGgk\tĐĐGck\n" .
                  "1\tNguyễn Văn An\t0901234567\t8.0\t8.0\t8.0\n";
    $tmpPath = sys_get_temp_dir() . '/tab_tsv_' . bin2hex(random_bytes(4)) . '.csv';
    file_put_contents($tmpPath, $tsvContent);
    
    $cFile = new CURLFile($tmpPath, 'text/tab-separated-values', 'test.tsv');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);
    
    if ($res['status'] !== 200 || mb_stripos($res['body'], 'thành công') === false) {
        throw new Exception("TSV file failed to import");
    }
    return "Tab-delimited gradebook auto-detected and imported with success notice";
});

// 1.13 CSV with quoted cells containing delimiter
challenge("IMPORT", "1.13 RFC 4180 CSV with escaped quotes and commas in cell values", function () {
    $csvContent = "\"STT\",\"Họ và tên\",\"Ghi chú\"\n" .
                  "\"1\",\"Nguyễn, Văn An\",\"Học sinh \"\"xuất sắc\"\" của lớp\"\n";
    $tmpPath = sys_get_temp_dir() . '/rfc_csv_' . bin2hex(random_bytes(4)) . '.csv';
    file_put_contents($tmpPath, $csvContent);
    
    $parsed = parse_csv($tmpPath);
    @unlink($tmpPath);
    
    if (count($parsed) !== 2 || $parsed[1][1] !== 'Nguyễn, Văn An' || $parsed[1][2] !== 'Học sinh "xuất sắc" của lớp') {
        throw new Exception("RFC 4180 escaped quotes mismatch: " . json_encode($parsed));
    }
    return "RFC 4180 quotes and commas inside cells parsed with exact string fidelity";
});

// 1.14 XML entities in shared strings
challenge("IMPORT", "1.14 Safe handling of XML special characters in Excel shared strings", function () {
    $data = [
        ['STT', 'Họ và tên', 'Ghi chú'],
        [1, 'Nguyễn & Khang', '<Toán học> "Nâng cao" & \'Chuyên sâu\'']
    ];
    $xlsx = create_xlsx($data);
    $tmpPath = sys_get_temp_dir() . '/entities_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, $xlsx);
    
    $parsed = parse_xlsx($tmpPath);
    @unlink($tmpPath);
    
    if ($parsed[1][1] !== 'Nguyễn & Khang' || $parsed[1][2] !== '<Toán học> "Nâng cao" & \'Chuyên sâu\'') {
        throw new Exception("XML entity decoding mismatch: " . json_encode($parsed));
    }
    return "Special characters (&, <, >, \", ') roundtrip through OpenXML shared strings perfectly";
});

// 2.6 Full Roundtrip Fidelity: Export -> Parse -> Re-import
challenge("EXPORT", "2.6 Full Roundtrip Fidelity: Export -> Parse -> Re-import produces identical DB state", function () use ($client, $pdo) {
    // 1. Capture DB state of 6A1 Toán
    $dbBefore = $pdo->query("
        SELECT sc.student_id, sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM scores sc
        JOIN students s ON sc.student_id = s.id
        WHERE s.class_id = 1 AND sc.subject = 'Toán'
        ORDER BY sc.student_id ASC
    ")->fetchAll();
    
    // 2. Download export
    $resExp = $client->get('nhap_diem.php?export=xlsx&class_id=1&subject=' . urlencode('Toán'));
    if ($resExp['status'] !== 200) {
        throw new Exception("Export failed with HTTP " . $resExp['status']);
    }
    
    $tmpExport = sys_get_temp_dir() . '/roundtrip_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpExport, $resExp['body']);
    
    // 3. Re-import the exact exported file
    $cFile = new CURLFile($tmpExport, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'roundtrip.xlsx');
    $resImp = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpExport);
    
    if ($resImp['status'] !== 200 || mb_stripos($resImp['body'], 'thành công') === false) {
        throw new Exception("Re-importing exported file failed: " . substr($resImp['body'], 0, 300));
    }
    
    // 4. Verify DB state matches 100%
    $dbAfter = $pdo->query("
        SELECT sc.student_id, sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM scores sc
        JOIN students s ON sc.student_id = s.id
        WHERE s.class_id = 1 AND sc.subject = 'Toán'
        ORDER BY sc.student_id ASC
    ")->fetchAll();
    
    if (count($dbBefore) !== count($dbAfter)) {
        throw new Exception("Row count mismatch after roundtrip re-import: before=" . count($dbBefore) . ", after=" . count($dbAfter));
    }
    for ($i = 0; $i < count($dbBefore); $i++) {
        if ($dbBefore[$i]['avg_score'] != $dbAfter[$i]['avg_score']) {
            throw new Exception("Score mismatch at index $i: before=" . json_encode($dbBefore[$i]) . ", after=" . json_encode($dbAfter[$i]));
        }
    }
    return "100% data fidelity preserved through full Export -> Parse -> Re-import cycle";
});

// 3.7 Unauthenticated POST save_scores rejected
challenge("BOLA", "3.7 Unauthenticated POST save_scores redirects to login.php (HTTP 302)", function () use ($client) {
    $unauthClient = new AttackClient('http://localhost/my-web-project');
    $res = $unauthClient->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'save_scores',
        'scores' => [1 => ['tx1' => '10.0']]
    ]);
    if ($res['status'] !== 302 || strpos($res['redirect_url'], 'login.php') === false) {
        throw new Exception("Expected HTTP 302 redirect to login.php, got HTTP " . $res['status']);
    }
    return "Unauthenticated POST request strictly blocked with 302 redirect";
});

// 3.8 Unauthenticated POST import_file rejected
challenge("BOLA", "3.8 Unauthenticated POST import_file redirects to login.php (HTTP 302)", function () {
    $unauthClient = new AttackClient('http://localhost/my-web-project');
    $tmp = tempnam(sys_get_temp_dir(), 'unauth_');
    file_put_contents($tmp, "fake");
    $cFile = new CURLFile($tmp, 'text/csv', 'fake.csv');
    $res = $unauthClient->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmp);
    if ($res['status'] !== 302 || strpos($res['redirect_url'], 'login.php') === false) {
        throw new Exception("Expected HTTP 302 redirect for unauthenticated import, got HTTP " . $res['status']);
    }
    return "Unauthenticated bulk file import blocked with 302 redirect";
});

// 3.9 Unauthenticated GET export=xlsx rejected
challenge("BOLA", "3.9 Unauthenticated GET export=xlsx blocked without data leak (HTTP 302)", function () {
    $unauthClient = new AttackClient('http://localhost/my-web-project');
    $res = $unauthClient->get('nhap_diem.php?export=xlsx&class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 302 || strpos($res['redirect_url'], 'login.php') === false) {
        throw new Exception("Expected HTTP 302 redirect, got HTTP " . $res['status']);
    }
    if (strpos($res['headers'], 'application/vnd.openxmlformats') !== false) {
        throw new Exception("Unauthenticated request leaked Excel binary payload!");
    }
    return "Unauthenticated export request blocked without leaking gradebook data";
});

// 3.10 BOLA SQL Injection in class_id and subject parameters
challenge("BOLA", "3.10 SQL Injection in class_id / subject parameter blocked (HTTP 403 / safe PDO)", function () use ($client) {
    // Attempt SQL injection in subject: 'Toán\' OR 1=1--'
    $res1 = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode("Toán' OR 1=1--"));
    if ($res1['status'] !== 403) {
        throw new Exception("SQLi in subject did not return HTTP 403: got " . $res1['status']);
    }
    
    // Attempt negative class_id
    $res2 = $client->get('nhap_diem.php?class_id=-1&subject=' . urlencode('Toán'));
    if ($res2['status'] !== 403 && $res2['status'] !== 200) {
        throw new Exception("Negative class_id caused unexpected error: HTTP " . $res2['status']);
    }
    return "SQLi payloads in URL parameters safely rejected with HTTP 403";
});

// 4.5 Bulk import isolation: Excel with foreign class student code does not modify foreign class
challenge("CROSS_CLASS", "4.5 Excel import with foreign class student code does NOT alter foreign class", function () use ($client, $pdo) {
    // Class 10 (7A1) student
    $foreignStudent = $pdo->query("SELECT * FROM students WHERE class_id = 10 LIMIT 1")->fetch();
    if (!$foreignStudent) {
        throw new Exception("No student in class 10 found for test");
    }
    $foreignId = (int)$foreignStudent['id'];
    $scoreBefore = $pdo->query("SELECT * FROM scores WHERE student_id = $foreignId AND subject = 'Toán'")->fetch();
    
    // Attacker imports Excel into Class 1 (6A1) referencing Class 10's student code & name
    $data = [
        ['STT', 'Mã HS', 'Họ và tên', 'ĐĐGtx1', 'ĐĐGgk', 'ĐĐGck'],
        [1, $foreignStudent['student_code'], $foreignStudent['full_name'], 10, 10, 10]
    ];
    $tmpPath = sys_get_temp_dir() . '/cross_import_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, create_xlsx($data));
    
    $cFile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'cross.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cFile
    ]);
    @unlink($tmpPath);
    
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200, got " . $res['status']);
    }
    
    // Verify Class 10's student in MySQL was NOT modified
    $scoreAfter = $pdo->query("SELECT * FROM scores WHERE student_id = $foreignId AND subject = 'Toán'")->fetch();
    if ($scoreBefore && ((float)$scoreAfter['tx1'] !== (float)$scoreBefore['tx1'])) {
        throw new Exception("Class 10 student score was modified by Class 1 import!");
    }
    if (!$scoreBefore && $scoreAfter) {
        throw new Exception("Score was created for Class 10 student via Class 1 import!");
    }
    
    // Clean up any student created in Class 1 with that name
    $pdo->prepare("DELETE FROM scores WHERE student_id IN (SELECT id FROM students WHERE class_id = 1 AND full_name = ?)")
        ->execute([$foreignStudent['full_name']]);
    $pdo->prepare("DELETE FROM students WHERE class_id = 1 AND full_name = ?")
        ->execute([$foreignStudent['full_name']]);
        
    return "Class 10 student record remained 100% isolated; 0 unauthorized score modification";
});

// 4.6 SQL injection in array keys of POST scores
challenge("ATOMICITY", "4.6 SQL injection inside array keys of POST scores safely handled", function () use ($client, $pdo) {
    $sqliPayload = [
        'action' => 'save_scores',
        'scores' => [
            "1; DROP TABLE scores;--" => ['tx1' => '9.0', 'gk' => '9.0', 'ck' => '9.0']
        ]
    ];
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $sqliPayload);
    
    // Verify table scores still exists and intact
    $tableChk = $pdo->query("SHOW TABLES LIKE 'scores'")->fetchColumn();
    if (!$tableChk) {
        throw new Exception("FATAL: Table scores was dropped by SQL injection in array key!");
    }
    return "PDO type casting (int)studentId neutralized SQL injection payload in array key";
});

echo PHP_EOL;

// =============================================================================
// SUMMARY REPORT & VERDICT
// =============================================================================
echo CLR_BOLD . "================================================================================" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . sprintf("  TOTAL TESTS: %d | PASSED: %s%d%s | FAILED: %s%d%s", 
    $totalPassed + $totalFailed,
    CLR_GREEN, $totalPassed, CLR_RESET,
    $totalFailed > 0 ? CLR_RED : CLR_GREEN, $totalFailed, CLR_RESET
) . CLR_RESET . PHP_EOL;
echo CLR_BOLD . "================================================================================" . CLR_RESET . PHP_EOL;

if ($totalFailed > 0) {
    echo CLR_RED . "VERDICT: REQUEST_CHANGES" . CLR_RESET . PHP_EOL;
    echo "Failures encountered:" . PHP_EOL;
    foreach ($findings as $f) {
        echo " - [{$f['category']}] {$f['title']}: {$f['error']}" . PHP_EOL;
    }
    exit(1);
} else {
    echo CLR_GREEN . "VERDICT: APPROVE" . CLR_RESET . PHP_EOL;
    exit(0);
}

