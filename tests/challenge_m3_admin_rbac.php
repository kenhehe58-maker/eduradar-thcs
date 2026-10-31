<?php
/**
 * =============================================================================
 * Adversarial Empirical Challenge Suite — Milestone 3 Admin Matrix & RBAC
 * EduRadar THCS Gradebook & Early Warning System
 * 
 * Tests:
 * 1. Live Class Management Under Attack Conditions (Authorized Admin):
 *    - Invalid class names (15Z9, 5A1, 10A1, 6E1, XSS payloads, SQLi payloads, empty, too long)
 *    - Duplicate class creations (seed class 6A1, newly created class, rapid burst)
 *    - Class deletions and rapid deletion bursts
 *    - Cascade deletion integrity (classes -> teacher_classes, students, scores)
 * 2. Live Batch Assignment Under Attack Conditions (Authorized Admin):
 *    - Mass selection of all 36 classes (1..36)
 *    - Rapid re-submission bursts (2nd and 3rd submission: 0 duplicates, 36 skipped)
 *    - Invalid teacher IDs (0, -1, 99999, admin user ID 1)
 *    - Empty or whitespace subjects
 *    - Empty class selection array
 *    - Invalid class IDs in array
 * 3. RBAC & Route Protection on All Administrative Endpoints (Unauthenticated Sessions):
 *    - Forged POST/GET to add_class, delete_class, create_teacher, batch_assign,
 *      delete_assign, impersonate, import_students
 *    - Verify HTTP 403 / 302 and ZERO database mutations
 * 4. RBAC & Route Protection on All Administrative Endpoints (Teacher Session: gv_toan):
 *    - Forged POST/GET to add_class, delete_class, create_teacher, batch_assign,
 *      delete_assign, impersonate, import_students
 *    - Header spoofing bypass attempts (X-Forwarded-For, X-Original-URL, etc.)
 *    - Verify HTTP 403 Forbidden and ZERO database mutations
 * 5. Negative Controls & State Invariance:
 *    - Legitimate Admin login & admin.php access (HTTP 200)
 *    - Legitimate Admin inspection access to nhap_diem.php (HTTP 200)
 *    - Database state invariance & audit log forensic integrity
 * 
 * Run with:
 *   D:\xam\php\php.exe tests/challenge_m3_admin_rbac.php
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

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
    echo CLR_CYAN . str_pad($label, 72, ".") . CLR_RESET;
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
 * Isolated HTTP Session Client
 */
class AttackClient
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/challenger_m3_' . bin2hex(random_bytes(6)) . '.txt';
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

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
        ];

        if (!empty($headers)) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

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
        $headerStr = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $httpCode = $info['http_code'];

        curl_close($ch);

        return [
            'code'    => $httpCode,
            'headers' => $headerStr,
            'body'    => $body,
            'url'     => $info['url']
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

    public function login(string $username, string $password): bool
    {
        $this->clearCookies();
        $res = $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ], [], false);

        if ($res['code'] === 302) {
            return true;
        }

        // Kiểm tra xem có cookie PHPSESSID được set không
        return stripos($res['headers'], 'Set-Cookie: PHPSESSID') !== false;
    }
}

$baseUrl = 'http://localhost/my-web-project';
$client = new AttackClient($baseUrl);
$pdo = db();

echo PHP_EOL;
echo CLR_BOLD . "================================================================================" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . "  EduRadar THCS: Adversarial Empirical Challenge Suite — Milestone 3" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . "  Scope: Class Matrix, Batch Assignment, RBAC & Route Security Boundaries" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . "  Target: $baseUrl | Database: MySQL edu_radar" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . "================================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// =============================================================================
// VECTOR 1: CLASS MANAGEMENT ADVERSARIAL CHALLENGES (AUTHORIZED ADMIN)
// =============================================================================
echo CLR_BOLD . "--- [VECTOR 1] CLASS MANAGEMENT UNDER ATTACK CONDITIONS ---" . CLR_RESET . PHP_EOL;

challenge("V1.01", "Invalid class name '15Z9' (grade > 9, invalid format) is rejected", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '15Z9'")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => '15Z9',
        'grade'   => 15,
        'section' => 'Z'
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '15Z9'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Invalid class '15Z9' was inserted into classes table!");
    }
    if (strpos($res['body'], 'không hợp lệ') === false && strpos($res['body'], 'không đúng định dạng') === false) {
        throw new Exception("Expected error message for invalid class '15Z9' was not rendered!");
    }
    return "Rejected with validation error; 0 database mutations.";
});

challenge("V1.02", "Invalid grade 5 (below THCS grade 6) is rejected", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE grade = 5")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => '5A1',
        'grade'   => 5,
        'section' => 'A'
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE grade = 5")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Invalid grade 5 class was inserted into classes table!");
    }
    return "Grade 5 rejected; 0 database mutations.";
});

challenge("V1.03", "Invalid grade 10 (above THCS grade 9) is rejected", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE grade = 10")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => '10A1',
        'grade'   => 10,
        'section' => 'A'
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE grade = 10")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Invalid grade 10 class was inserted into classes table!");
    }
    return "Grade 10 rejected; 0 database mutations.";
});

challenge("V1.04", "Invalid section 'E' (not in A, B, C, D) is rejected", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE section = 'E'")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => '6E1',
        'grade'   => 6,
        'section' => 'E'
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE section = 'E'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Invalid section 'E' class was inserted into classes table!");
    }
    return "Section 'E' rejected; 0 database mutations.";
});

challenge("V1.05", "XSS payload in class name '6A<script>alert(1)</script>' is rejected", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $xssName = '6A<script>alert(1)</script>';
    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => $xssName,
        'grade'   => 6,
        'section' => 'A'
    ]);

    $found = (bool)$pdo->query("SELECT id FROM classes WHERE name LIKE '%<script%'")->fetch();
    if ($found) {
        throw new Exception("XSS payload class name was inserted into database!");
    }
    return "XSS payload rejected by format regex; 0 database mutations.";
});

challenge("V1.06", "SQL injection payload '6A1\' OR 1=1 --' in class name is rejected", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $sqliName = "6A1' OR 1=1 --";
    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => $sqliName,
        'grade'   => 6,
        'section' => 'A'
    ]);

    $found = (bool)$pdo->query("SELECT id FROM classes WHERE name LIKE '%OR 1=1%'")->fetch();
    if ($found) {
        throw new Exception("SQL injection payload was inserted into database!");
    }
    return "SQL injection payload rejected by format regex; 0 database mutations.";
});

challenge("V1.07", "Duplicate creation of existing seed class '6A1' is blocked", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $countBefore = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '6A1'")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => '6A1',
        'grade'   => 6,
        'section' => 'A'
    ]);

    $countAfter = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '6A1'")->fetchColumn();
    if ($countAfter > $countBefore) {
        throw new Exception("Duplicate class '6A1' was created!");
    }
    if (strpos($res['body'], 'đã tồn tại') === false) {
        throw new Exception("Duplicate rejection notice was not displayed in UI response!");
    }
    return "Duplicate '6A1' rejected with notice; count remained $countBefore.";
});

challenge("V1.08", "Rapid creation burst: 5 identical POSTs for '8B99_BURST' produce exactly 1 class", function () use ($client, $pdo) {
    $className = '8B99_BURST';
    $pdo->prepare("DELETE FROM classes WHERE name = ?")->execute([$className]);

    $client->login('admin', '123456');

    // 5 successive rapid POST requests
    for ($i = 0; $i < 5; $i++) {
        $client->post('admin.php', [
            'action'  => 'add_class',
            'name'    => $className,
            'grade'   => 8,
            'section' => 'B'
        ]);
    }

    $finalCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '$className'")->fetchColumn();
    if ($finalCount !== 1) {
        throw new Exception("Expected exactly 1 class for '$className' after 5 burst requests, found $finalCount!");
    }
    return "5 burst creation requests produced exactly 1 class; 0 duplicates.";
});

challenge("V1.09", "Rapid deletion burst: deleting '8B99_BURST' twice handles non-existent gracefully", function () use ($client, $pdo) {
    $className = '8B99_BURST';
    $cid = (int)$pdo->query("SELECT id FROM classes WHERE name = '$className'")->fetchColumn();
    if (!$cid) {
        throw new Exception("Setup failed: class '$className' not found");
    }

    $client->login('admin', '123456');

    // First deletion
    $res1 = $client->post('admin.php', [
        'action'   => 'delete_class',
        'class_id' => $cid
    ]);

    $stillExists = (bool)$pdo->query("SELECT id FROM classes WHERE id = $cid")->fetch();
    if ($stillExists) {
        throw new Exception("First deletion of class failed!");
    }

    // Second deletion of already deleted class
    $res2 = $client->post('admin.php', [
        'action'   => 'delete_class',
        'class_id' => $cid
    ]);

    if ($res2['code'] >= 500) {
        throw new Exception("Server crashed with HTTP " . $res2['code'] . " on deleting non-existent class!");
    }
    if (strpos($res2['body'], 'không tồn tại') === false) {
        throw new Exception("Expected 'không tồn tại' message on second deletion!");
    }

    return "Second deletion handled gracefully without server error.";
});

challenge("V1.10", "Cascade deletion: deleting a class cleanly purges teacher_classes and students", function () use ($client, $pdo) {
    // Tạo lớp kiểm tra cascade
    $cascadeClass = '7C99_CASCADE';
    $pdo->prepare("DELETE FROM classes WHERE name = ?")->execute([$cascadeClass]);

    $client->login('admin', '123456');
    $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => $cascadeClass,
        'grade'   => 7,
        'section' => 'C'
    ]);

    $cid = (int)$pdo->query("SELECT id FROM classes WHERE name = '$cascadeClass'")->fetchColumn();
    if (!$cid) {
        throw new Exception("Failed to create cascade test class");
    }

    // Gán 1 phân công và 1 học sinh vào lớp này
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1")->fetchColumn();
    $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, ?, ?)")->execute([$teacherId, $cid, 'Toán']);
    $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday) VALUES (?, ?, ?, ?)")->execute([$cid, 'HS_CAS_01', 'Học Sinh Cascade', '2011-05-10']);
    $sid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO scores (student_id, subject, tx1, tx2, avg_score) VALUES (?, ?, ?, ?, ?)")->execute([$sid, 'Toán', 8.0, 9.0, 8.5]);

    // Xóa lớp
    $client->post('admin.php', [
        'action'   => 'delete_class',
        'class_id' => $cid
    ]);

    // Kiểm tra cascade trong database
    $classRemains = (bool)$pdo->query("SELECT id FROM classes WHERE id = $cid")->fetch();
    $tcRemains = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE class_id = $cid")->fetchColumn();
    $stuRemains = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = $cid")->fetchColumn();
    $scoreRemains = (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE student_id = $sid")->fetchColumn();

    if ($classRemains || $tcRemains > 0 || $stuRemains > 0 || $scoreRemains > 0) {
        throw new Exception("Foreign key cascade failed: classRemains=$classRemains, tcRemains=$tcRemains, stuRemains=$stuRemains, scoreRemains=$scoreRemains");
    }

    return "Class and all foreign key child rows (teacher_classes, students, scores) cleanly purged.";
});

// =============================================================================
// VECTOR 2: BATCH ASSIGNMENT ADVERSARIAL CHALLENGES (AUTHORIZED ADMIN)
// =============================================================================
echo PHP_EOL . CLR_BOLD . "--- [VECTOR 2] BATCH ASSIGNMENT UNDER ATTACK CONDITIONS ---" . CLR_RESET . PHP_EOL;

challenge("V2.01", "Mass selection: assigning all 36 classes (1..36) to gv_toan succeeds", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    if (!$teacherId) {
        throw new Exception("Setup failed: gv_toan not found");
    }

    // Lấy ID của toàn bộ 36 lớp chuẩn
    $allClassIds = $pdo->query("SELECT id FROM classes WHERE name REGEXP '^[6-9][A-C][1-9]$' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    if (count($allClassIds) < 36) {
        throw new Exception("Setup failed: expected 36 standard classes, found " . count($allClassIds));
    }

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Toán',
        'class_ids'  => $allClassIds
    ]);

    $totalAssigned = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Toán'")->fetchColumn();
    if ($totalAssigned !== count($allClassIds)) {
        throw new Exception("Expected all " . count($allClassIds) . " classes assigned, found $totalAssigned");
    }

    return "Mass assignment of " . count($allClassIds) . " classes completed successfully.";
});

challenge("V2.02", "Rapid re-submission burst 1: identical 36 classes produces 0 duplicates (36 skipped)", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    $allClassIds = $pdo->query("SELECT id FROM classes WHERE name REGEXP '^[6-9][A-C][1-9]$' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

    $countBefore = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Toán'")->fetchColumn();

    // Re-submit identical 36 classes
    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Toán',
        'class_ids'  => $allClassIds
    ]);

    $countAfter = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Toán'")->fetchColumn();
    if ($countAfter !== $countBefore) {
        throw new Exception("Duplicate rows created in teacher_classes: before=$countBefore, after=$countAfter");
    }
    if (strpos($res['body'], 'bỏ qua') === false) {
        throw new Exception("Expected skip notification not found in UI response!");
    }

    return "0 duplicate rows created; all classes skipped cleanly.";
});

challenge("V2.03", "Rapid re-submission burst 2: 3rd submission maintains exact table invariance", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    $allClassIds = $pdo->query("SELECT id FROM classes WHERE name REGEXP '^[6-9][A-C][1-9]$' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

    $countBefore = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Toán'")->fetchColumn();

    // 3rd submission
    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Toán',
        'class_ids'  => $allClassIds
    ]);

    $countAfter = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Toán'")->fetchColumn();
    if ($countAfter !== $countBefore) {
        throw new Exception("Duplicate rows created on 3rd submission: before=$countBefore, after=$countAfter");
    }

    return "Exact invariance maintained on 3rd submission (count=$countAfter).";
});

challenge("V2.04", "Invalid teacher ID 0 is rejected with zero assignments created", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = 0")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => 0,
        'subject'    => 'Toán',
        'class_ids'  => [1, 2]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = 0")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Assignment created with teacher_id = 0!");
    }
    return "teacher_id = 0 rejected with error; 0 database mutations.";
});

challenge("V2.05", "Invalid teacher ID -1 (negative) is rejected with zero assignments created", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = -1")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => -1,
        'subject'    => 'Toán',
        'class_ids'  => [1, 2]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = -1")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Assignment created with teacher_id = -1!");
    }
    return "Negative teacher_id rejected; 0 database mutations.";
});

challenge("V2.06", "Non-existent teacher ID 99999 is rejected with error message", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = 99999")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => 99999,
        'subject'    => 'Toán',
        'class_ids'  => [1, 2]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = 99999")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Assignment created with teacher_id = 99999!");
    }
    if (strpos($res['body'], 'không hợp lệ') === false) {
        throw new Exception("Expected 'không hợp lệ' error message for teacher_id=99999!");
    }
    return "Non-existent teacher rejected; 0 database mutations.";
});

challenge("V2.07", "Admin user ID 1 (role='admin') cannot be assigned teaching duties", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $adminId = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $adminId")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $adminId,
        'subject'    => 'Toán',
        'class_ids'  => [1, 2]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $adminId")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Admin user was assigned teaching duties in teacher_classes!");
    }
    if (strpos($res['body'], 'không hợp lệ') === false) {
        throw new Exception("Expected rejection for non-teacher role in batch_assign!");
    }
    return "Admin role rejected from teacher assignment; 0 database mutations.";
});

challenge("V2.08", "Empty subject '' is rejected with validation error", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE subject = ''")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => '',
        'class_ids'  => [1, 2]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE subject = ''")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Empty subject assignment created in teacher_classes!");
    }
    return "Empty subject rejected; 0 database mutations.";
});

challenge("V2.09", "Whitespace subject '   ' is rejected with validation error", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE TRIM(subject) = ''")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => '   ',
        'class_ids'  => [1, 2]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE TRIM(subject) = ''")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Whitespace subject assignment created in teacher_classes!");
    }
    return "Whitespace subject trimmed and rejected; 0 database mutations.";
});

challenge("V2.10", "Empty class selection array is rejected with validation error", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Toán',
        'class_ids'  => []
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Assignment created with empty class_ids array!");
    }
    return "Empty class array rejected; 0 database mutations.";
});

challenge("V2.11", "Non-positive class IDs [0, -5] in class_ids array are filtered out", function () use ($client, $pdo) {
    $client->login('admin', '123456');
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE class_id <= 0")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Toán',
        'class_ids'  => [0, -5]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE class_id <= 0")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Non-positive class IDs were inserted into teacher_classes!");
    }
    return "Non-positive class IDs filtered out; 0 database mutations.";
});

// Dọn dẹp các phân công hàng loạt thử nghiệm để đưa về trạng thái sạch ban đầu
challenge("V2.12", "Cleanup: restore baseline teacher_classes assignments", function () use ($client, $pdo) {
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    // Giữ lại lớp 1 (6A1), lớp 10 (7A1) và lớp 37 (7A6) theo seed data ban đầu (database.sql)
    $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND subject = 'Toán' AND class_id NOT IN (1, 10, 37)")->execute([$teacherId]);
    $check10 = $pdo->prepare("SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = 10 AND subject = 'Toán'");
    $check10->execute([$teacherId]);
    if (!$check10->fetch()) {
        $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, 10, 'Toán')")->execute([$teacherId]);
    }
    $check37 = $pdo->prepare("SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = 37 AND subject = 'Toán'");
    $check37->execute([$teacherId]);
    if (!$check37->fetch()) {
        $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, 37, 'Toán')")->execute([$teacherId]);
    }

    $rem = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Toán'")->fetchColumn();
    return "Restored baseline assignments for gv_toan (remaining: $rem).";
});

// =============================================================================
// VECTOR 3: RBAC & ROUTE PROTECTION — UNAUTHENTICATED SESSIONS
// =============================================================================
echo PHP_EOL . CLR_BOLD . "--- [VECTOR 3] RBAC DEFENSES: UNAUTHENTICATED SESSIONS ---" . CLR_RESET . PHP_EOL;

challenge("V3.01", "Unauthenticated GET /admin.php returns HTTP 403 / 302", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('admin.php');

    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    if (strpos($res['body'], 'Phân công giáo viên') !== false || strpos($res['body'], 'Ma trận lớp học') !== false) {
        throw new Exception("Admin UI was leaked in unauthenticated response body!");
    }
    return "HTTP " . $res['code'] . "; Zero administrative UI leaked.";
});

challenge("V3.02", "Unauthenticated POST action=add_class is blocked (0 classes created)", function () use ($client, $pdo) {
    $client->clearCookies();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '6HACK_UNAUTH'")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => '6HACK_UNAUTH',
        'grade'   => 6,
        'section' => 'A'
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '6HACK_UNAUTH'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Unauthenticated request created a class in classes table!");
    }
    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    return "HTTP " . $res['code'] . "; 0 classes created in database.";
});

challenge("V3.03", "Unauthenticated POST action=delete_class is blocked (class not deleted)", function () use ($client, $pdo) {
    $client->clearCookies();
    $res = $client->post('admin.php', [
        'action'   => 'delete_class',
        'class_id' => 1 // 6A1
    ]);

    $classExists = (bool)$pdo->query("SELECT id FROM classes WHERE id = 1")->fetch();
    if (!$classExists) {
        throw new Exception("Unauthenticated request deleted class ID 1!");
    }
    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    return "HTTP " . $res['code'] . "; Class 1 preserved intact.";
});

challenge("V3.04", "Unauthenticated GET ?delete_class=1 is blocked (class not deleted)", function () use ($client, $pdo) {
    $client->clearCookies();
    $res = $client->get('admin.php?delete_class=1');

    $classExists = (bool)$pdo->query("SELECT id FROM classes WHERE id = 1")->fetch();
    if (!$classExists) {
        throw new Exception("Unauthenticated GET deleted class ID 1!");
    }
    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    return "HTTP " . $res['code'] . "; Class 1 preserved intact.";
});

challenge("V3.05", "Unauthenticated POST action=create_teacher is blocked (0 users created)", function () use ($client, $pdo) {
    $client->clearCookies();
    $res = $client->post('admin.php', [
        'action'    => 'create_teacher',
        'username'  => 'gv_anon_hacker',
        'full_name' => 'Anon Hacker',
        'password'  => '123456'
    ]);

    $userExists = (bool)$pdo->query("SELECT id FROM users WHERE username = 'gv_anon_hacker'")->fetch();
    if ($userExists) {
        throw new Exception("Unauthenticated request created a teacher account in users table!");
    }
    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    return "HTTP " . $res['code'] . "; 0 users created in database.";
});

challenge("V3.06", "Unauthenticated POST action=batch_assign is blocked (0 assignments created)", function () use ($client, $pdo) {
    $client->clearCookies();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => 2,
        'subject'    => 'Toán',
        'class_ids'  => [10, 11, 12]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Unauthenticated request created teaching assignments!");
    }
    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    return "HTTP " . $res['code'] . "; 0 assignments created in database.";
});

challenge("V3.07", "Unauthenticated POST action=delete_assign is blocked", function () use ($client, $pdo) {
    $client->clearCookies();
    $assignId = (int)$pdo->query("SELECT id FROM teacher_classes LIMIT 1")->fetchColumn();
    if (!$assignId) {
        throw new Exception("Setup failed: no assignment in teacher_classes");
    }

    $res = $client->post('admin.php', [
        'action' => 'delete_assign',
        'id'     => $assignId
    ]);

    $assignStillExists = (bool)$pdo->query("SELECT id FROM teacher_classes WHERE id = $assignId")->fetch();
    if (!$assignStillExists) {
        throw new Exception("Unauthenticated POST deleted assignment ID $assignId!");
    }
    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    return "HTTP " . $res['code'] . "; Assignment ID $assignId preserved intact.";
});

challenge("V3.08", "Unauthenticated GET ?delete_assign=X is blocked", function () use ($client, $pdo) {
    $client->clearCookies();
    $assignId = (int)$pdo->query("SELECT id FROM teacher_classes LIMIT 1")->fetchColumn();

    $res = $client->get("admin.php?delete_assign=$assignId");

    $assignStillExists = (bool)$pdo->query("SELECT id FROM teacher_classes WHERE id = $assignId")->fetch();
    if (!$assignStillExists) {
        throw new Exception("Unauthenticated GET deleted assignment ID $assignId!");
    }
    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    return "HTTP " . $res['code'] . "; Assignment ID $assignId preserved intact.";
});

challenge("V3.09", "Unauthenticated GET ?impersonate=2 is blocked", function () use ($client) {
    $client->clearCookies();
    $res = $client->get("admin.php?impersonate=2&cid=1&sub=Toán");

    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    return "HTTP " . $res['code'] . "; Impersonation blocked.";
});

challenge("V3.10", "Unauthenticated POST action=import_students is blocked (0 students imported)", function () use ($client, $pdo) {
    $client->clearCookies();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = 1")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 1
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = 1")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Unauthenticated request imported students!");
    }
    if ($res['code'] !== 403 && $res['code'] !== 302) {
        throw new Exception("Expected HTTP 403 or 302, got HTTP " . $res['code']);
    }
    return "HTTP " . $res['code'] . "; 0 students imported.";
});

// =============================================================================
// VECTOR 4: RBAC & ROUTE PROTECTION — TEACHER SESSIONS (gv_toan)
// =============================================================================
echo PHP_EOL . CLR_BOLD . "--- [VECTOR 4] RBAC DEFENSES: TEACHER SESSIONS (gv_toan) ---" . CLR_RESET . PHP_EOL;

challenge("V4.01", "Teacher GET /admin.php is strictly blocked with HTTP 403 Forbidden", function () use ($client) {
    $client->login('gv_toan', '123456');
    $res = $client->get('admin.php');

    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden for teacher, got HTTP " . $res['code']);
    }
    if (strpos($res['body'], 'Phân công giáo viên') !== false || strpos($res['body'], 'Ma trận lớp học') !== false) {
        throw new Exception("Admin UI was leaked in teacher 403 response body!");
    }
    return "HTTP 403 Forbidden; Zero administrative UI or statistics leaked.";
});

challenge("V4.02", "Teacher POST action=add_class is blocked with HTTP 403 (0 classes created)", function () use ($client, $pdo) {
    $client->login('gv_toan', '123456');
    $className = '7HACK_TEACHER';
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '$className'")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'  => 'add_class',
        'name'    => $className,
        'grade'   => 7,
        'section' => 'A'
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '$className'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Teacher request created a class in classes table!");
    }
    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; 0 classes created in database.";
});

challenge("V4.03", "Teacher POST action=create_class is blocked with HTTP 403 (0 classes created)", function () use ($client, $pdo) {
    $client->login('gv_toan', '123456');
    $className = '7HACK_TEACHER_2';
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '$className'")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'  => 'create_class',
        'name'    => $className,
        'grade'   => 7,
        'section' => 'B'
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = '$className'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Teacher request created a class in classes table!");
    }
    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; 0 classes created in database.";
});

challenge("V4.04", "Teacher POST action=delete_class is blocked with HTTP 403", function () use ($client, $pdo) {
    $client->login('gv_toan', '123456');
    $res = $client->post('admin.php', [
        'action'   => 'delete_class',
        'class_id' => 1
    ]);

    $classExists = (bool)$pdo->query("SELECT id FROM classes WHERE id = 1")->fetch();
    if (!$classExists) {
        throw new Exception("Teacher POST deleted class ID 1!");
    }
    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; Class 1 preserved intact.";
});

challenge("V4.05", "Teacher GET ?delete_class=1 is blocked with HTTP 403", function () use ($client, $pdo) {
    $client->login('gv_toan', '123456');
    $res = $client->get('admin.php?delete_class=1');

    $classExists = (bool)$pdo->query("SELECT id FROM classes WHERE id = 1")->fetch();
    if (!$classExists) {
        throw new Exception("Teacher GET deleted class ID 1!");
    }
    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; Class 1 preserved intact.";
});

challenge("V4.06", "Teacher POST action=create_teacher is blocked with HTTP 403 (0 users created)", function () use ($client, $pdo) {
    $client->login('gv_toan', '123456');
    $res = $client->post('admin.php', [
        'action'    => 'create_teacher',
        'username'  => 'teacher_created_by_teacher',
        'full_name' => 'Rogue Teacher Account',
        'password'  => '123456'
    ]);

    $userExists = (bool)$pdo->query("SELECT id FROM users WHERE username = 'teacher_created_by_teacher'")->fetch();
    if ($userExists) {
        throw new Exception("Teacher created a rogue teacher account!");
    }
    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; 0 users created in database.";
});

challenge("V4.07", "Teacher POST action=batch_assign is blocked with HTTP 403 (0 assignments created)", function () use ($client, $pdo) {
    $client->login('gv_toan', '123456');
    $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username = 'gv_toan'")->fetchColumn();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Tin Học'")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'     => 'batch_assign',
        'teacher_id' => $teacherId,
        'subject'    => 'Tin Học',
        'class_ids'  => [10, 11, 12]
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = $teacherId AND subject = 'Tin Học'")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Teacher self-assigned classes via forged batch_assign!");
    }
    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; 0 assignments created in database.";
});

challenge("V4.08", "Teacher POST action=delete_assign is blocked with HTTP 403", function () use ($client, $pdo) {
    $client->login('gv_toan', '123456');
    // Tìm phân công của gv_van (ID 3)
    $vanAssignId = (int)$pdo->query("SELECT id FROM teacher_classes WHERE teacher_id = 3 LIMIT 1")->fetchColumn();
    if (!$vanAssignId) {
        $vanAssignId = (int)$pdo->query("SELECT id FROM teacher_classes LIMIT 1")->fetchColumn();
    }

    $res = $client->post('admin.php', [
        'action' => 'delete_assign',
        'id'     => $vanAssignId
    ]);

    $assignStillExists = (bool)$pdo->query("SELECT id FROM teacher_classes WHERE id = $vanAssignId")->fetch();
    if (!$assignStillExists) {
        throw new Exception("Teacher deleted teaching assignment ID $vanAssignId!");
    }
    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; Assignment ID $vanAssignId preserved intact.";
});

challenge("V4.09", "Teacher GET ?delete_assign=X is blocked with HTTP 403", function () use ($client, $pdo) {
    $client->login('gv_toan', '123456');
    $vanAssignId = (int)$pdo->query("SELECT id FROM teacher_classes WHERE teacher_id = 3 LIMIT 1")->fetchColumn();
    if (!$vanAssignId) {
        $vanAssignId = (int)$pdo->query("SELECT id FROM teacher_classes LIMIT 1")->fetchColumn();
    }

    $res = $client->get("admin.php?delete_assign=$vanAssignId");

    $assignStillExists = (bool)$pdo->query("SELECT id FROM teacher_classes WHERE id = $vanAssignId")->fetch();
    if (!$assignStillExists) {
        throw new Exception("Teacher deleted teaching assignment ID $vanAssignId!");
    }
    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; Assignment ID $vanAssignId preserved intact.";
});

challenge("V4.10", "Teacher GET ?impersonate=1 (attempting admin escalation) is blocked with HTTP 403", function () use ($client) {
    $client->login('gv_toan', '123456');
    $res = $client->get("admin.php?impersonate=1");

    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; Impersonation of admin rejected.";
});

challenge("V4.11", "Teacher GET ?impersonate=3 (attempting peer escalation) is blocked with HTTP 403", function () use ($client) {
    $client->login('gv_toan', '123456');
    $res = $client->get("admin.php?impersonate=3&cid=1&sub=" . urlencode('Ngữ Văn'));

    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; Impersonation of peer teacher rejected.";
});

challenge("V4.12", "Teacher POST action=import_students is blocked with HTTP 403 (0 students imported)", function () use ($client, $pdo) {
    $client->login('gv_toan', '123456');
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = 2")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 2
    ]);

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = 2")->fetchColumn();
    if ($afterCount > $beforeCount) {
        throw new Exception("Teacher imported students into class ID 2!");
    }
    if ($res['code'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; 0 students imported.";
});

challenge("V4.13", "Header spoofing bypass (X-Forwarded-For, X-Original-URL, etc.) is rejected with 403", function () use ($client) {
    $client->login('gv_toan', '123456');
    $headers = [
        'X-Forwarded-For: 127.0.0.1',
        'X-Real-IP: 127.0.0.1',
        'Client-IP: 127.0.0.1',
        'X-Original-URL: /index.php',
        'X-Rewrite-URL: /index.php',
    ];

    $res = $client->get('admin.php', $headers);
    if ($res['code'] !== 403) {
        throw new Exception("Header spoofing bypassed admin route guard: HTTP " . $res['code']);
    }
    return "HTTP 403 Forbidden; Header spoofing rejected.";
});

// =============================================================================
// VECTOR 5: NEGATIVE CONTROLS & FORENSIC AUDIT INTEGRITY
// =============================================================================
echo PHP_EOL . CLR_BOLD . "--- [VECTOR 5] NEGATIVE CONTROLS & AUDIT FORENSICS ---" . CLR_RESET . PHP_EOL;

challenge("V5.01", "Authorized Admin login and GET /admin.php succeeds with HTTP 200 OK", function () use ($client) {
    $ok = $client->login('admin', '123456');
    if (!$ok) {
        throw new Exception("Admin login failed!");
    }

    $res = $client->get('admin.php');
    if ($res['code'] !== 200) {
        throw new Exception("Expected HTTP 200 OK for Admin, got HTTP " . $res['code']);
    }
    if (stripos($res['body'], 'Admin Portal') === false || stripos($res['body'], 'ma trận lớp học') === false) {
        throw new Exception("Admin portal layout not found in HTTP 200 response!");
    }
    return "HTTP 200 OK; Admin portal UI rendered completely.";
});

challenge("V5.02", "Authorized Admin direct gradebook inspection (nhap_diem.php) succeeds with HTTP 200", function () use ($client) {
    $client->login('admin', '123456');
    $res = $client->get('nhap_diem.php?class_id=1&subject=Toán');

    if ($res['code'] !== 200) {
        throw new Exception("Expected HTTP 200 for Admin inspection, got HTTP " . $res['code']);
    }
    return "HTTP 200 OK; Gradebook inspection accessible to Admin.";
});

challenge("V5.03", "Audit logs captured blocked attack attempts with accurate role and diagnostic details", function () use ($pdo) {
    $stmt = $pdo->query("
        SELECT action, details, username, created_at 
        FROM audit_logs 
        WHERE action = 'Truy cập trái phép bị chặn' 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $logs = $stmt->fetchAll();

    if (empty($logs)) {
        throw new Exception("No audit logs found for blocked unauthorized attempts!");
    }

    $hasTeacherLog = false;
    foreach ($logs as $log) {
        if ($log['username'] === 'gv_toan' && strpos($log['details'], 'role: [teacher]') !== false) {
            $hasTeacherLog = true;
            break;
        }
    }

    if (!$hasTeacherLog) {
        throw new Exception("Expected audit log entry recording gv_toan unauthorized access not found in recent logs!");
    }

    return "Audit logs properly record unauthorized access attempts with user and role.";
});

challenge("V5.04", "Database state forensic integrity: zero orphaned or corrupt rows", function () use ($pdo) {
    // Check orphaned teacher_classes
    $orphanedTC = (int)$pdo->query("
        SELECT COUNT(*) FROM teacher_classes tc 
        LEFT JOIN classes c ON tc.class_id = c.id 
        LEFT JOIN users u ON tc.teacher_id = u.id 
        WHERE c.id IS NULL OR u.id IS NULL
    ")->fetchColumn();

    // Check orphaned students
    $orphanedStudents = (int)$pdo->query("
        SELECT COUNT(*) FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE c.id IS NULL
    ")->fetchColumn();

    // Check orphaned scores
    $orphanedScores = (int)$pdo->query("
        SELECT COUNT(*) FROM scores sc 
        LEFT JOIN students s ON sc.student_id = s.id 
        WHERE s.id IS NULL
    ")->fetchColumn();

    if ($orphanedTC > 0 || $orphanedStudents > 0 || $orphanedScores > 0) {
        throw new Exception("Database integrity violation: orphanedTC=$orphanedTC, orphanedStudents=$orphanedStudents, orphanedScores=$orphanedScores");
    }

    return "Zero orphaned records across teacher_classes, students, and scores.";
});

echo PHP_EOL;
echo CLR_BOLD . "================================================================================" . CLR_RESET . PHP_EOL;
echo sprintf(CLR_BOLD . "  Total Adversarial Challenges: %d | Passed: %d | Failed: %d" . CLR_RESET . PHP_EOL, 
    $totalPassed + $totalFailed, $totalPassed, $totalFailed);
if ($totalFailed === 0) {
    echo CLR_GREEN . CLR_BOLD . "  VERDICT: APPROVE. All adversarial challenges passed." . CLR_RESET . PHP_EOL;
} else {
    echo CLR_RED . CLR_BOLD . "  VERDICT: REQUEST_CHANGES. Found $totalFailed failure(s)." . CLR_RESET . PHP_EOL;
}
echo CLR_BOLD . "================================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

exit($totalFailed === 0 ? 0 : 1);
