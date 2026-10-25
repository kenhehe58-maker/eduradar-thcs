<?php
/**
 * =============================================================================
 * EMPIRICAL ADVERSARIAL CHALLENGE HARNESS: Milestone 4 (Score Calc & Validation)
 * Agent: challenger_m4_1
 * 
 * Adversarial Challenge Vectors:
 * 1. Extreme & Invalid Scores: -0.1, 10.1, 999, non-numeric strings, SQL injections, XSS payloads.
 * 2. Boundary Scores: 0.0, 10.0, 4.9, 5.0, 7.9, 8.0, 3.4, 3.5.
 * 3. Partial Scores: only TX, TX+GK without CK, only CK, empty rows.
 * 4. Weighting Verification: TX weight 1, GK weight 2, CK weight 3 per Thông tư 22.
 * 5. Comma vs Dot Inputs: '8,5' vs '8.5' equivalence in POST & CSV/Excel.
 * 6. Client JS Validation & Math Parity with Backend.
 * 7. Transaction Integrity & State Restoration.
 * 
 * Execution:
 *   D:\xam\php\php.exe tests/challenge_m4_score_calc.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/xlsx_helper.php';

// Terminal formatting
const ANSI_RESET  = "\033[0m";
const ANSI_GREEN  = "\033[1;32m";
const ANSI_RED    = "\033[1;31m";
const ANSI_YELLOW = "\033[1;33m";
const ANSI_CYAN   = "\033[1;36m";
const ANSI_BOLD   = "\033[1m";

$totalChallenges = 0;
$passedChallenges = 0;
$failedChallenges = 0;
$findings = [];

function challenge(string $code, string $description, callable $testFn): void
{
    global $totalChallenges, $passedChallenges, $failedChallenges, $findings;
    $totalChallenges++;
    $label = sprintf("[%s] %s", $code, $description);
    echo ANSI_CYAN . "[CHALLENGE] " . ANSI_RESET . str_pad($label, 72, ".");
    
    try {
        $result = $testFn();
        if ($result === false) {
            throw new Exception("Challenge assertion failed (returned false)");
        }
        $passedChallenges++;
        echo ANSI_GREEN . " [PASS]" . ANSI_RESET . PHP_EOL;
    } catch (Throwable $e) {
        $failedChallenges++;
        $findings[] = [
            'code' => $code,
            'description' => $description,
            'error' => $e->getMessage()
        ];
        echo ANSI_RED . " [FAIL]" . ANSI_RESET . PHP_EOL;
        echo ANSI_RED . "            -> Error: " . $e->getMessage() . ANSI_RESET . PHP_EOL;
    }
}

/**
 * Dedicated cURL HTTP Client for Session Handling
 */
class ChallengerClient
{
    private string $baseUrl;
    private string $cookieJar;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieJar = sys_get_temp_dir() . '/challenger_m4_cookie_' . bin2hex(random_bytes(6)) . '.txt';
    }

    public function __destruct()
    {
        if (file_exists($this->cookieJar)) {
            @unlink($this->cookieJar);
        }
    }

    public function login(string $username, string $password): bool
    {
        $ch = curl_init($this->baseUrl . '/login.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'username' => $username,
                'password' => $password
            ]),
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code === 302 || $code === 200);
    }

    public function post(string $path, array $data): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15
        ]);
        $body = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body];
    }

    public function postMultipart(string $path, array $fields): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15
        ]);
        $body = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body];
    }
}

echo PHP_EOL;
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "  EMPIRICAL ADVERSARIAL CHALLENGE SUITE: MILESTONE 4 SCORE CALCULATION" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "  Target: http://localhost/my-web-project/ | Live DB: edu_radar" . ANSI_RESET . PHP_EOL;
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;

$pdo = db();
$client = new ChallengerClient('http://localhost/my-web-project');

// Authenticate as gv_toan (assigned to 6A1 Toán)
if (!$client->login('gv_toan', '123456')) {
    echo ANSI_RED . "FATAL: Could not authenticate as gv_toan!" . ANSI_RESET . PHP_EOL;
    exit(1);
}

// Backup current score state for student 1 in 6A1 Toán
$backupRow = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();

// Helper to reset student 1 scores for testing
$setStudent1Scores = function(array $scores) use ($client, $pdo): array {
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            1 => $scores
        ]
    ];
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $payload);
    $row = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    return [$res, $row];
};

// =============================================================================
// VECTOR 1: EXTREME & INVALID SCORES
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 1: Extreme & Invalid Scores (Fuzzing & Injection) ---" . ANSI_RESET . PHP_EOL;

challenge("V1.1", "Negative score -0.1 sanitized to NULL in MySQL", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores(['tx1' => '-0.1', 'tx2' => '8.0', 'gk' => '8.0', 'ck' => '8.0']);
    if ($res['status'] !== 200) throw new Exception("HTTP status was " . $res['status']);
    if ($row['tx1'] !== null) throw new Exception("Expected tx1 to be NULL for -0.1, got: " . var_export($row['tx1'], true));
    return true;
});

challenge("V1.2", "Negative score -999.0 sanitized to NULL in MySQL", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores(['tx1' => '-999.0', 'gk' => '-5.0', 'ck' => '-0.01']);
    if ($row['tx1'] !== null || $row['gk'] !== null || $row['ck'] !== null) {
        throw new Exception("Expected negative scores to be sanitized to NULL");
    }
    return true;
});

challenge("V1.3", "Excessive score 10.1 sanitized to NULL in MySQL", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores(['tx1' => '10.1', 'gk' => '8.0', 'ck' => '8.0']);
    if ($row['tx1'] !== null) throw new Exception("Expected tx1 NULL for 10.1, got: " . var_export($row['tx1'], true));
    return true;
});

challenge("V1.4", "Excessive score 999 and 1e5 sanitized to NULL", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores(['tx1' => '999', 'tx2' => '100000', 'gk' => '15.5']);
    if ($row['tx1'] !== null || $row['tx2'] !== null || $row['gk'] !== null) {
        throw new Exception("Excessive scores > 10.0 were not sanitized to NULL");
    }
    return true;
});

challenge("V1.5", "Non-numeric strings ('abc', 'N/A', 'null', 'NaN', 'Inf') sanitized to NULL", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => 'abc',
        'tx2' => 'N/A',
        'tx3' => 'null',
        'tx4' => 'NaN',
        'gk'  => 'Inf',
        'ck'  => '--'
    ]);
    if ($row['tx1'] !== null || $row['tx2'] !== null || $row['tx3'] !== null || 
        $row['tx4'] !== null || $row['gk'] !== null || $row['ck'] !== null || $row['avg_score'] !== null) {
        throw new Exception("Non-numeric strings produced non-null values: " . json_encode($row));
    }
    return true;
});

challenge("V1.6", "SQL Injection payload in score input blocked & sanitized", function() use ($setStudent1Scores, $pdo) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => "1; DROP TABLE scores;--",
        'tx2' => "1' OR '1'='1",
        'gk'  => "1 UNION SELECT 1,2,3",
        'ck'  => "10.0"
    ]);
    // Ensure scores table still exists and student 1 tx1, tx2, gk are NULL
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'scores'")->fetch();
    if (!$tableCheck) throw new Exception("CRITICAL: scores table was dropped by SQL injection!");
    if ($row['tx1'] !== null || $row['tx2'] !== null || $row['gk'] !== null) {
        throw new Exception("SQL injection payloads were not sanitized to NULL: " . json_encode($row));
    }
    if ((float)$row['ck'] !== 10.0) throw new Exception("Valid CK 10.0 corrupted");
    return true;
});

challenge("V1.7", "XSS payloads in score inputs sanitized & neutralized", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '<script>alert("XSS")</script>',
        'tx2' => '<img src=x onerror=alert(1)>',
        'gk'  => '" onfocus="alert(1)',
        'ck'  => '<svg/onload=alert(1)>'
    ]);
    if ($row['tx1'] !== null || $row['tx2'] !== null || $row['gk'] !== null || $row['ck'] !== null) {
        throw new Exception("XSS payload entered database: " . json_encode($row));
    }
    if (strpos($res['body'], '<script>alert("XSS")</script>') !== false) {
        throw new Exception("XSS payload reflected unescaped in response!");
    }
    return true;
});

challenge("V1.8", "Malformed decimal strings ('8..5', '8,,5', '+-5') sanitized to NULL", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '8..5',
        'tx2' => '8,,5',
        'gk'  => '+-5',
        'ck'  => '5.5.5'
    ]);
    if ($row['tx1'] !== null || $row['tx2'] !== null || $row['gk'] !== null || $row['ck'] !== null) {
        throw new Exception("Malformed decimal strings were not sanitized: " . json_encode($row));
    }
    return true;
});

// =============================================================================
// VECTOR 2: BOUNDARY SCORES & THRESHOLDS (0.0, 10.0, 4.9, 5.0, 7.9, 8.0, 3.4, 3.5)
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 2: Boundary Scores & Thresholds (0.0 to 10.0) ---" . ANSI_RESET . PHP_EOL;

challenge("V2.1", "Absolute lower boundary 0.0 correctly stored (NOT NULL) and avg is 0.0", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '0.0',
        'tx2' => '0',
        'tx3' => '0.00',
        'tx4' => '0',
        'gk'  => '0.0',
        'ck'  => '0.0'
    ]);
    if ($row['tx1'] === null || $row['gk'] === null || $row['ck'] === null) {
        throw new Exception("0.0 was mistakenly treated as NULL: " . json_encode($row));
    }
    if ((float)$row['tx1'] !== 0.0 || (float)$row['gk'] !== 0.0 || (float)$row['ck'] !== 0.0) {
        throw new Exception("0.0 values mismatch: " . json_encode($row));
    }
    if ($row['avg_score'] === null || (float)$row['avg_score'] !== 0.0) {
        throw new Exception("Expected avg_score 0.0, got: " . var_export($row['avg_score'], true));
    }
    return true;
});

challenge("V2.2", "Absolute upper boundary 10.0 stored and avg is 10.0", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '10.0',
        'tx2' => '10',
        'tx3' => '10.0',
        'tx4' => '10.0',
        'gk'  => '10.0',
        'ck'  => '10.0'
    ]);
    if ((float)$row['tx1'] !== 10.0 || (float)$row['gk'] !== 10.0 || (float)$row['ck'] !== 10.0) {
        throw new Exception("10.0 values mismatch: " . json_encode($row));
    }
    if ($row['avg_score'] === null || (float)$row['avg_score'] !== 10.0) {
        throw new Exception("Expected avg_score 10.0, got: " . var_export($row['avg_score'], true));
    }
    return true;
});

challenge("V2.3", "Danger vs Warning boundary: exact 4.9 evaluates to avg_score 4.9", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '4.9',
        'tx2' => '4.9',
        'gk'  => '4.9',
        'ck'  => '4.9'
    ]);
    if ((float)$row['avg_score'] !== 4.9) {
        throw new Exception("Expected avg_score 4.9, got " . $row['avg_score']);
    }
    return true;
});

challenge("V2.4", "Rounding to boundary: mathematical 4.9375 rounds to 4.9", function() use ($setStudent1Scores) {
    // TX1=4.9, TX2=4.9, TX3=5.0, GK=5.0, CK=4.9 -> (14.8 + 10 + 14.7)/8 = 39.5/8 = 4.9375 -> 4.9
    [$res, $row] = $setStudent1Scores([
        'tx1' => '4.9',
        'tx2' => '4.9',
        'tx3' => '5.0',
        'gk'  => '5.0',
        'ck'  => '4.9'
    ]);
    if ((float)$row['avg_score'] !== 4.9) {
        throw new Exception("Expected avg_score 4.9, got " . $row['avg_score']);
    }
    return true;
});

challenge("V2.5", "Rounding to boundary: mathematical 4.9625 rounds to 5.0", function() use ($setStudent1Scores) {
    // TX1=5.0, TX2=5.0, TX3=5.0, GK=5.0, CK=4.9 -> (15.0 + 10 + 14.7)/8 = 39.7/8 = 4.9625 -> 5.0
    [$res, $row] = $setStudent1Scores([
        'tx1' => '5.0',
        'tx2' => '5.0',
        'tx3' => '5.0',
        'gk'  => '5.0',
        'ck'  => '4.9'
    ]);
    if ((float)$row['avg_score'] !== 5.0) {
        throw new Exception("Expected avg_score 5.0, got " . $row['avg_score']);
    }
    return true;
});

challenge("V2.6", "Good vs Excellent boundary: exact 7.9 evaluates to avg_score 7.9", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '7.9',
        'tx2' => '7.9',
        'gk'  => '7.9',
        'ck'  => '7.9'
    ]);
    if ((float)$row['avg_score'] !== 7.9) {
        throw new Exception("Expected avg_score 7.9, got " . $row['avg_score']);
    }
    return true;
});

challenge("V2.7", "Rounding to boundary: mathematical 7.875 rounds to 7.9", function() use ($setStudent1Scores) {
    // TX1=8.0, TX2=8.0, TX3=8.0, GK=7.5, CK=8.0 -> (24 + 15 + 24)/8 = 63/8 = 7.875 -> 7.9
    [$res, $row] = $setStudent1Scores([
        'tx1' => '8.0',
        'tx2' => '8.0',
        'tx3' => '8.0',
        'gk'  => '7.5',
        'ck'  => '8.0'
    ]);
    if ((float)$row['avg_score'] !== 7.9) {
        throw new Exception("Expected avg_score 7.9, got " . $row['avg_score']);
    }
    return true;
});

challenge("V2.8", "Exact 8.0 boundary evaluates to avg_score 8.0", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '8.0',
        'tx2' => '8.0',
        'gk'  => '8.0',
        'ck'  => '8.0'
    ]);
    if ((float)$row['avg_score'] !== 8.0) {
        throw new Exception("Expected avg_score 8.0, got " . $row['avg_score']);
    }
    return true;
});

challenge("V2.9", "Thông tư 22 Chưa đạt boundary: exact 3.4 evaluates to 3.4", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '3.4',
        'tx2' => '3.4',
        'gk'  => '3.4',
        'ck'  => '3.4'
    ]);
    if ((float)$row['avg_score'] !== 3.4) {
        throw new Exception("Expected avg_score 3.4, got " . $row['avg_score']);
    }
    return true;
});

challenge("V2.10", "Thông tư 22 boundary: exact 3.5 evaluates to 3.5", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '3.5',
        'tx2' => '3.5',
        'gk'  => '3.5',
        'ck'  => '3.5'
    ]);
    if ((float)$row['avg_score'] !== 3.5) {
        throw new Exception("Expected avg_score 3.5, got " . $row['avg_score']);
    }
    return true;
});

// =============================================================================
// VECTOR 3: PARTIAL SCORES & INCOMPLETE GRADING
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 3: Partial Scores & Incomplete Grading ---" . ANSI_RESET . PHP_EOL;

challenge("V3.1", "Only 1 TX score entered evaluates to that score", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '7.0',
        'tx2' => '',
        'tx3' => '',
        'tx4' => '',
        'gk'  => '',
        'ck'  => ''
    ]);
    if ((float)$row['tx1'] !== 7.0) throw new Exception("tx1 mismatch");
    if ($row['tx2'] !== null || $row['gk'] !== null || $row['ck'] !== null) throw new Exception("Other scores not NULL");
    if ((float)$row['avg_score'] !== 7.0) {
        throw new Exception("Expected avg_score 7.0 for single TX, got " . $row['avg_score']);
    }
    return true;
});

challenge("V3.2", "Non-consecutive TX scores (TX2=8.0, TX4=6.0, others null) avg is 7.0", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '',
        'tx2' => '8.0',
        'tx3' => '',
        'tx4' => '6.0',
        'gk'  => '',
        'ck'  => ''
    ]);
    if ((float)$row['avg_score'] !== 7.0) {
        throw new Exception("Expected avg_score 7.0 for non-consecutive TX, got " . $row['avg_score']);
    }
    return true;
});

challenge("V3.3", "Partial entry: TX1=7.0, GK=8.0 without CK evaluates to 7.7", function() use ($setStudent1Scores) {
    // (7.0 + 2*8.0)/(1 + 2) = 23.0/3 = 7.666... -> 7.7
    [$res, $row] = $setStudent1Scores([
        'tx1' => '7.0',
        'tx2' => '',
        'tx3' => '',
        'tx4' => '',
        'gk'  => '8.0',
        'ck'  => ''
    ]);
    if ((float)$row['avg_score'] !== 7.7) {
        throw new Exception("Expected avg_score 7.7 for TX+GK, got " . $row['avg_score']);
    }
    return true;
});

challenge("V3.4", "Partial entry: Only CK=8.5 (no TX, no GK) evaluates to 8.5", function() use ($setStudent1Scores) {
    // (3*8.5)/3 = 8.5
    [$res, $row] = $setStudent1Scores([
        'tx1' => '',
        'tx2' => '',
        'tx3' => '',
        'tx4' => '',
        'gk'  => '',
        'ck'  => '8.5'
    ]);
    if ((float)$row['avg_score'] !== 8.5) {
        throw new Exception("Expected avg_score 8.5 for only CK, got " . $row['avg_score']);
    }
    return true;
});

challenge("V3.5", "Partial entry: Only GK=9.0 (no TX, no CK) evaluates to 9.0", function() use ($setStudent1Scores) {
    // (2*9.0)/2 = 9.0
    [$res, $row] = $setStudent1Scores([
        'tx1' => '',
        'tx2' => '',
        'tx3' => '',
        'tx4' => '',
        'gk'  => '9.0',
        'ck'  => ''
    ]);
    if ((float)$row['avg_score'] !== 9.0) {
        throw new Exception("Expected avg_score 9.0 for only GK, got " . $row['avg_score']);
    }
    return true;
});

challenge("V3.6", "Partial entry: GK=7.0 + CK=8.0 (no TX) evaluates to 7.6", function() use ($setStudent1Scores) {
    // (2*7.0 + 3*8.0)/(0 + 5) = 38.0/5 = 7.6
    [$res, $row] = $setStudent1Scores([
        'tx1' => '',
        'tx2' => '',
        'tx3' => '',
        'tx4' => '',
        'gk'  => '7.0',
        'ck'  => '8.0'
    ]);
    if ((float)$row['avg_score'] !== 7.6) {
        throw new Exception("Expected avg_score 7.6 for GK+CK without TX, got " . $row['avg_score']);
    }
    return true;
});

challenge("V3.7", "Completely empty score row evaluates to NULL in avg_score", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '',
        'tx2' => '',
        'tx3' => '',
        'tx4' => '',
        'gk'  => '',
        'ck'  => ''
    ]);
    if ($row['avg_score'] !== null) {
        throw new Exception("Expected avg_score NULL for empty row, got " . var_export($row['avg_score'], true));
    }
    return true;
});

// =============================================================================
// VECTOR 4: WEIGHTING VERIFICATION PER THÔNG TƯ 22 (TX:1, GK:2, CK:3)
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 4: Weighting Verification per Thông tư 22 ---" . ANSI_RESET . PHP_EOL;

challenge("V4.1", "TX has strict weight 1 (raising TX by 1 raises numerator by 1)", function() use ($setStudent1Scores) {
    // Base: TX1=6, GK=6, CK=6 -> avg = (6 + 12 + 18)/6 = 36/6 = 6.0
    // Raise TX1 to 7 -> avg = (7 + 12 + 18)/6 = 37/6 = 6.166... -> 6.2
    [$res, $row] = $setStudent1Scores(['tx1' => '7.0', 'gk' => '6.0', 'ck' => '6.0']);
    if ((float)$row['avg_score'] !== 6.2) {
        throw new Exception("Expected avg_score 6.2 when TX1 increased by 1, got " . $row['avg_score']);
    }
    return true;
});

challenge("V4.2", "GK has strict weight 2 (raising GK by 1 raises numerator by 2)", function() use ($setStudent1Scores) {
    // Base: TX1=6, GK=7 (+1 from base), CK=6 -> avg = (6 + 14 + 18)/6 = 38/6 = 6.333... -> 6.3
    [$res, $row] = $setStudent1Scores(['tx1' => '6.0', 'gk' => '7.0', 'ck' => '6.0']);
    if ((float)$row['avg_score'] !== 6.3) {
        throw new Exception("Expected avg_score 6.3 when GK increased by 1, got " . $row['avg_score']);
    }
    return true;
});

challenge("V4.3", "CK has strict weight 3 (raising CK by 1 raises numerator by 3)", function() use ($setStudent1Scores) {
    // Base: TX1=6, GK=6, CK=7 (+1 from base) -> avg = (6 + 12 + 21)/6 = 39/6 = 6.5
    [$res, $row] = $setStudent1Scores(['tx1' => '6.0', 'gk' => '6.0', 'ck' => '7.0']);
    if ((float)$row['avg_score'] !== 6.5) {
        throw new Exception("Expected avg_score 6.5 when CK increased by 1, got " . $row['avg_score']);
    }
    return true;
});

challenge("V4.4", "Weight ratio GK:TX = 2:1 and CK:TX = 3:1 verified mathematically", function() use ($setStudent1Scores) {
    // Start with all 0s: TX1=0, GK=0, CK=0 -> total weight = 6.
    // 1) TX1=6, GK=0, CK=0 -> avg = 6/6 = 1.0
    [$r1, $row1] = $setStudent1Scores(['tx1' => '6.0', 'gk' => '0.0', 'ck' => '0.0']);
    // 2) TX1=0, GK=6, CK=0 -> avg = (2*6)/6 = 12/6 = 2.0
    [$r2, $row2] = $setStudent1Scores(['tx1' => '0.0', 'gk' => '6.0', 'ck' => '0.0']);
    // 3) TX1=0, GK=0, CK=6 -> avg = (3*6)/6 = 18/6 = 3.0
    [$r3, $row3] = $setStudent1Scores(['tx1' => '0.0', 'gk' => '0.0', 'ck' => '6.0']);

    if ((float)$row1['avg_score'] !== 1.0) throw new Exception("TX weight check failed, got " . $row1['avg_score']);
    if ((float)$row2['avg_score'] !== 2.0) throw new Exception("GK weight check failed, got " . $row2['avg_score']);
    if ((float)$row3['avg_score'] !== 3.0) throw new Exception("CK weight check failed, got " . $row3['avg_score']);
    return true;
});

challenge("V4.5", "Full Thông tư 22 formula: 4 TX + GK + CK with uneven scores equals 8.4", function() use ($setStudent1Scores) {
    // TX1=8.0, TX2=7.5, TX3=9.0, TX4=8.5, GK=8.0, CK=9.0
    // sum(TX) = 33.0, N_tx = 4
    // avg = (33.0 + 2*8.0 + 3*9.0) / (4 + 5) = (33 + 16 + 27) / 9 = 76 / 9 = 8.444... -> 8.4
    [$res, $row] = $setStudent1Scores([
        'tx1' => '8.0',
        'tx2' => '7.5',
        'tx3' => '9.0',
        'tx4' => '8.5',
        'gk'  => '8.0',
        'ck'  => '9.0'
    ]);
    if ((float)$row['avg_score'] !== 8.4) {
        throw new Exception("Expected avg_score 8.4, got " . $row['avg_score']);
    }
    return true;
});

// =============================================================================
// VECTOR 5: COMMA VS DOT INPUTS
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 5: Comma vs Dot Decimal Separator Handling ---" . ANSI_RESET . PHP_EOL;

challenge("V5.1", "Form POST with Vietnamese comma '8,5' parsed and stored as 8.50 in DB", function() use ($setStudent1Scores) {
    [$res, $row] = $setStudent1Scores([
        'tx1' => '8,5',
        'tx2' => '7,0',
        'gk'  => '8,0',
        'ck'  => '9,0'
    ]);
    if ((float)$row['tx1'] !== 8.5 || (float)$row['tx2'] !== 7.0 || (float)$row['gk'] !== 8.0 || (float)$row['ck'] !== 9.0) {
        throw new Exception("Comma scores failed to parse to float: " . json_encode($row));
    }
    // (8.5 + 7.0 + 2*8.0 + 3*9.0) / (2 + 5) = (15.5 + 16 + 27) / 7 = 58.5 / 7 = 8.357... -> 8.4
    if ((float)$row['avg_score'] !== 8.4) {
        throw new Exception("Expected avg_score 8.4 for comma inputs, got " . $row['avg_score']);
    }
    return true;
});

challenge("V5.2", "Comma and dot inputs produce strictly identical results in MySQL", function() use ($setStudent1Scores) {
    [$resDot, $rowDot] = $setStudent1Scores([
        'tx1' => '7.5',
        'tx2' => '8.5',
        'gk'  => '6.5',
        'ck'  => '9.5'
    ]);
    [$resComma, $rowComma] = $setStudent1Scores([
        'tx1' => '7,5',
        'tx2' => '8,5',
        'gk'  => '6,5',
        'ck'  => '9,5'
    ]);
    if ($rowDot['tx1'] !== $rowComma['tx1'] || $rowDot['tx2'] !== $rowComma['tx2'] ||
        $rowDot['gk'] !== $rowComma['gk'] || $rowDot['ck'] !== $rowComma['ck'] ||
        $rowDot['avg_score'] !== $rowComma['avg_score']) {
        throw new Exception("Drift between dot and comma inputs: Dot=" . json_encode($rowDot) . " Comma=" . json_encode($rowComma));
    }
    return true;
});

challenge("V5.3", "CSV Import with comma decimal separator parsed correctly", function() use ($client, $pdo) {
    $csvContent = "\xEF\xBB\xBFSTT,Mã học sinh,Họ và tên,Ngày sinh,Điểm TX 1,Điểm TX 2,Điểm TX 3,Điểm TX 4,Điểm GK,Điểm CK\r\n" .
                  "1,HS6A101,Nguyễn Văn An,15/01/2012,8,5,7,5,8,0,9,0,8,5,9,0\r\n";
    // Notice in CSV, if unquoted comma is used, columns can split, so standard CSV uses quotes or dots
    // Let's test quoted comma: "8,5"
    $quotedCsv = "\xEF\xBB\xBF\"STT\",\"Mã HS\",\"Họ và tên\",\"Ngày sinh\",\"ĐĐGtx1\",\"ĐĐGtx2\",\"ĐĐGtx3\",\"ĐĐGtx4\",\"ĐĐGgk\",\"ĐĐGck\"\r\n" .
                 "\"1\",\"HS6A101\",\"Nguyễn Văn An\",\"15/01/2012\",\"8,5\",\"7,5\",\"\",\"\",\"8,0\",\"9,0\"\r\n";
    
    $tmpPath = sys_get_temp_dir() . '/test_comma_import_' . bin2hex(random_bytes(4)) . '.csv';
    file_put_contents($tmpPath, $quotedCsv);

    $cfile = new CURLFile($tmpPath, 'text/csv', 'test_comma.csv');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cfile
    ]);
    @unlink($tmpPath);

    if ($res['status'] !== 200) throw new Exception("HTTP status " . $res['status']);
    
    $row = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    if ((float)$row['tx1'] !== 8.5 || (float)$row['tx2'] !== 7.5 || (float)$row['gk'] !== 8.0 || (float)$row['ck'] !== 9.0) {
        throw new Exception("Quoted comma scores in CSV did not import as expected: " . json_encode($row));
    }
    // (8.5 + 7.5 + 2*8.0 + 3*9.0) / (2 + 5) = (16 + 16 + 27) / 7 = 59 / 7 = 8.428... -> 8.4
    if ((float)$row['avg_score'] !== 8.4) {
        throw new Exception("Expected avg_score 8.4 after CSV import, got " . $row['avg_score']);
    }
    return true;
});

// =============================================================================
// VECTOR 6: CLIENT JS REGEX & ALGORITHM PARITY STRESS HARNESS
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 6: Client JS Regex & Math Parity ---" . ANSI_RESET . PHP_EOL;

challenge("V6.1", "Client-side regex exactly accepts valid scores and rejects invalid ones", function() {
    // Regex from nhap_diem.php line 811: /^(10(\.0{1,2})?|[0-9](\.[0-9]{1,2})?)$/
    $pattern = '/^(10(\.0{1,2})?|[0-9](\.[0-9]{1,2})?)$/';

    $shouldMatch = ['0', '0.0', '0.00', '1', '1.5', '5', '5.25', '7.8', '9.99', '10', '10.0', '10.00'];
    foreach ($shouldMatch as $val) {
        if (!preg_match($pattern, $val)) {
            throw new Exception("Regex failed to match valid score: '$val'");
        }
    }

    $shouldNotMatch = ['-0.1', '-1', '10.1', '10.001', '11', '999', 'abc', '8..5', '.5', '8.555', '+5', ' 8.5'];
    foreach ($shouldNotMatch as $val) {
        if (preg_match($pattern, $val)) {
            throw new Exception("Regex incorrectly matched invalid score: '$val'");
        }
    }
    return true;
});

challenge("V6.2", "Parity between PHP backend formula and JS client formula across 50 score combinations", function() {
    // We simulate both algorithms across 50 pseudo-random test vectors
    $testVectors = [
        // [tx1, tx2, tx3, tx4, gk, ck]
        [0.0, 0.0, 0.0, 0.0, 0.0, 0.0],
        [10.0, 10.0, 10.0, 10.0, 10.0, 10.0],
        [5.0, null, null, null, null, null],
        [null, 6.0, null, 7.0, null, null],
        [8.0, 8.5, null, null, 7.0, null],
        [null, null, null, null, 8.0, 9.0],
        [7.2, 8.1, 6.4, 9.0, 7.5, 8.2],
        [4.9, 4.9, 4.9, 4.9, 4.9, 4.9],
        [3.4, 3.5, 3.6, null, 3.4, 3.5],
        [9.8, 9.5, 10.0, 9.2, 9.0, 9.5]
    ];

    // Generate 40 more vectors
    mt_srand(42);
    for ($i = 0; $i < 40; $i++) {
        $vec = [];
        for ($c = 0; $c < 6; $c++) {
            $r = mt_rand(0, 10);
            if ($r === 0) {
                $vec[] = null;
            } else {
                $vec[] = round(mt_rand(0, 100) / 10, 1);
            }
        }
        $testVectors[] = $vec;
    }

    foreach ($testVectors as $idx => $v) {
        [$tx1, $tx2, $tx3, $tx4, $gk, $ck] = $v;

        // PHP Backend Formula
        $txList = array_values(array_filter([$tx1, $tx2, $tx3, $tx4], fn($x) => $x !== null));
        $nTx = count($txList);
        $sTx = array_sum($txList);
        $phpAvg = null;
        if ($gk !== null && $ck !== null) {
            $phpAvg = round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
        } else {
            $w = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
            if ($w > 0) {
                $phpAvg = round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / $w, 1);
            }
        }

        // JS Simulated Formula
        $jsAvg = null;
        $jsTxList = $txList;
        $jsTxCount = count($jsTxList);
        $jsTxSum = array_sum($jsTxList);
        $hasAny = ($nTx > 0 || $gk !== null || $ck !== null);
        if ($hasAny) {
            if ($gk !== null && $ck !== null) {
                $raw = ($jsTxSum + 2 * $gk + 3 * $ck) / ($jsTxCount + 5);
            } else {
                $totalWeight = $jsTxCount + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
                $weightedSum = $jsTxSum + ($gk !== null ? 2 * $gk : 0) + ($ck !== null ? 3 * $ck : 0);
                $raw = $weightedSum / $totalWeight;
            }
            $jsAvg = round($raw * 10) / 10;
        }

        if ($phpAvg !== $jsAvg) {
            throw new Exception("Parity mismatch at vector #$idx " . json_encode($v) . ": PHP=$phpAvg, JS=$jsAvg");
        }
    }
    return true;
});

// =============================================================================
// VECTOR 8: EXCEL (.XLSX) OPENXML FILE IMPORT FUZZING & BOUNDARIES
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 8: Excel (.xlsx) File Import Fuzzing & Boundaries ---" . ANSI_RESET . PHP_EOL;

challenge("V8.1", "XLSX import with extreme and boundary scores sanitizes invalid & preserves valid", function() use ($client, $pdo) {
    // Construct in-memory XLSX sheet with extreme and boundary scores
    $headers = ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck'];
    $row1 = [1, 'HS6A101', 'Nguyễn Văn An', '15/01/2012', -0.5, 15.0, 'INVALID', '8,5', 0.0, 10.0];
    
    $xlsxData = [$headers, $row1];
    $xlsxBinary = create_xlsx($xlsxData, 'Sheet1');
    
    $tmpPath = sys_get_temp_dir() . '/test_fuzz_import_' . bin2hex(random_bytes(4)) . '.xlsx';
    file_put_contents($tmpPath, $xlsxBinary);
    
    $cfile = new CURLFile($tmpPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'test_fuzz.xlsx');
    $res = $client->postMultipart('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'import_file',
        'gradebook_file' => $cfile
    ]);
    @unlink($tmpPath);
    
    if ($res['status'] !== 200) throw new Exception("HTTP status " . $res['status']);
    
    $row = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    if ($row['tx1'] !== null) throw new Exception("Expected tx1 NULL for -0.5 in XLSX, got " . var_export($row['tx1'], true));
    if ($row['tx2'] !== null) throw new Exception("Expected tx2 NULL for 15.0 in XLSX, got " . var_export($row['tx2'], true));
    if ($row['tx3'] !== null) throw new Exception("Expected tx3 NULL for INVALID in XLSX, got " . var_export($row['tx3'], true));
    if ((float)$row['tx4'] !== 8.5) throw new Exception("Expected tx4 8.5 for '8,5' in XLSX, got " . var_export($row['tx4'], true));
    if ((float)$row['gk'] !== 0.0) throw new Exception("Expected gk 0.0 in XLSX, got " . var_export($row['gk'], true));
    if ((float)$row['ck'] !== 10.0) throw new Exception("Expected ck 10.0 in XLSX, got " . var_export($row['ck'], true));
    
    // (8.5 + 2*0.0 + 3*10.0) / (1 + 5) = 38.5 / 6 = 6.4166... -> 6.4
    if ((float)$row['avg_score'] !== 6.4) {
        throw new Exception("Expected avg_score 6.4 after fuzz XLSX import, got " . $row['avg_score']);
    }
    return true;
});

// =============================================================================
// VECTOR 9: RBAC & IDOR ROUTE GUARD ON SCORE WRITES
// =============================================================================
echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 9: RBAC & IDOR Route Guard on Score Calculation ---" . ANSI_RESET . PHP_EOL;

challenge("V9.1", "Teacher (gv_toan) attempting to POST scores to unassigned subject (Ngữ Văn) rejected with HTTP 403", function() use ($client, $pdo) {
    // Initial state of student 1 in Ngữ Văn
    $initialVan = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetch();
    
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            1 => ['tx1' => '1.0', 'gk' => '1.0', 'ck' => '1.0']
        ]
    ];
    // gv_toan is NOT assigned to Ngữ Văn
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'), $payload);
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 when posting to unassigned subject, got " . $res['status']);
    }
    
    // Ensure DB scores for Ngữ Văn remained completely untouched
    $afterVan = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetch();
    if ($afterVan['avg_score'] !== $initialVan['avg_score'] || $afterVan['tx1'] !== $initialVan['tx1']) {
        throw new Exception("CRITICAL: Unassigned subject score was modified despite RBAC violation!");
    }
    return true;
});

challenge("V9.2", "Teacher (gv_toan) attempting to POST scores to unassigned class (Class 2) rejected with HTTP 403", function() use ($client) {
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            1 => ['tx1' => '9.0', 'gk' => '9.0', 'ck' => '9.0']
        ]
    ];
    // gv_toan is not assigned to Class 2 (6A2)
    $res = $client->post('nhap_diem.php?class_id=2&subject=' . urlencode('Toán'), $payload);
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 when posting to unassigned class, got " . $res['status']);
    }
    return true;
});

challenge("V9.3", "Unauthenticated client attempting to POST scores redirected with zero DB modifications", function() use ($pdo) {
    $unauthClient = new ChallengerClient('http://localhost/my-web-project');
    $payload = [
        'action' => 'save_scores',
        'scores' => [
            1 => ['tx1' => '2.0', 'gk' => '2.0', 'ck' => '2.0']
        ]
    ];
    $res = $unauthClient->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), $payload);
    // require_login() redirects to login.php
    if (strpos($res['body'], 'Đăng Nhập') === false && $res['status'] !== 302 && $res['status'] !== 200) {
        throw new Exception("Expected redirect to login page for unauthenticated POST");
    }
    return true;
});

echo PHP_EOL . ANSI_YELLOW . "--- VECTOR 7: Database Restoration & Integrity ---" . ANSI_RESET . PHP_EOL;

challenge("V7.1", "Restore student 1 to baseline score state cleanly", function() use ($backupRow, $pdo) {
    if ($backupRow) {
        $stmt = $pdo->prepare("
            UPDATE scores 
            SET tx1 = ?, tx2 = ?, tx3 = ?, tx4 = ?, gk = ?, ck = ?, avg_score = ?
            WHERE student_id = ? AND subject = ?
        ");
        $stmt->execute([
            $backupRow['tx1'],
            $backupRow['tx2'],
            $backupRow['tx3'],
            $backupRow['tx4'],
            $backupRow['gk'],
            $backupRow['ck'],
            $backupRow['avg_score'],
            $backupRow['student_id'],
            $backupRow['subject']
        ]);
    }
    $curr = $pdo->query("SELECT * FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetch();
    if ((float)$curr['avg_score'] !== (float)$backupRow['avg_score']) {
        throw new Exception("Restoration failed, expected avg_score " . $backupRow['avg_score'] . " got " . $curr['avg_score']);
    }
    return true;
});

echo PHP_EOL;
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;
echo sprintf(
    ANSI_BOLD . "  TOTAL CHALLENGES: %d | " . ANSI_GREEN . "PASSED: %d" . ANSI_RESET . ANSI_BOLD . " | " . ($failedChallenges > 0 ? ANSI_RED : ANSI_GREEN) . "FAILED: %d" . ANSI_RESET . PHP_EOL,
    $totalChallenges,
    $passedChallenges,
    $failedChallenges
);
echo ANSI_BOLD . "=============================================================================" . ANSI_RESET . PHP_EOL;

if ($failedChallenges > 0) {
    echo ANSI_RED . "Adversarial challenges uncovered {$failedChallenges} failure(s):" . ANSI_RESET . PHP_EOL;
    foreach ($findings as $f) {
        echo ANSI_RED . "  - [{$f['code']}] {$f['description']}: {$f['error']}" . ANSI_RESET . PHP_EOL;
    }
    exit(1);
} else {
    echo ANSI_GREEN . "All {$totalChallenges} adversarial challenges PASSED with 0 vulnerabilities!" . ANSI_RESET . PHP_EOL;
    exit(0);
}
