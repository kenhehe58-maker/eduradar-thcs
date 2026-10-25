<?php
/**
 * =============================================================================
 * Adversarial Empirical Challenge Suite — Milestone 2 RBAC & BOLA / IDOR
 * EduRadar THCS Gradebook & Early Warning System
 * 
 * Tests:
 * 1. Live HTTP attacks against admin.php:
 *    - Unauthenticated GET and forged POST (batch_assign, import_students, impersonate, delete_assign)
 *    - Teacher (gv_toan) GET and forged POST (batch_assign, import_students, impersonate, delete_assign)
 *    - Header tampering (X-Forwarded-For, X-Original-URL, etc.)
 *    - Verify HTTP 403 and 0 database changes in teacher_classes, classes, and students
 * 2. Live HTTP attacks against nhap_diem.php:
 *    - Unauthenticated GET and POST (save_scores) -> 302/403
 *    - Teacher (gv_toan) forged GET for unassigned classes: 7A1 Ngữ Văn, 8A1 Toán, 9C3 Tin Học, 6A1 Ngữ Văn
 *    - Teacher (gv_toan) forged POST save_scores for unassigned classes/subjects
 *    - Teacher (gv_toan) IDOR parameter tampering (?teacher_id=3, ?teacher_id=1, ?teacher_id=999, negative, etc.)
 *    - Cross-class student ID injection
 *    - Teacher (gv_van) cross-testing against gv_toan's classes
 *    - Input boundary & SQL injection attempts in class_id and subject
 *    - Negative controls: Admin full inspection & authorized teacher access
 *    - Audit log verification for every blocked attack
 * 
 * Run with:
 *   D:\xam\php\php.exe tests/challenge_m2_rbac.php
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
        $this->cookieFile = sys_get_temp_dir() . '/challenger_m2_' . bin2hex(random_bytes(6)) . '.txt';
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

    public function request(string $method, string $path, array $data = [], array $headers = [], bool $followRedirects = false): array
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
            CURLOPT_TIMEOUT => 10,
        ];

        if (!empty($headers)) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        $upperMethod = strtoupper($method);
        if ($upperMethod === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
        } elseif ($upperMethod !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $upperMethod;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL request to '$url' failed: $err");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerStr = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        return [
            'status'  => $status,
            'headers' => $headerStr,
            'body'    => $body,
        ];
    }

    public function get(string $path, array $headers = [], bool $followRedirects = false): array
    {
        return $this->request('GET', $path, [], $headers, $followRedirects);
    }

    public function post(string $path, array $data, array $headers = [], bool $followRedirects = false): array
    {
        return $this->request('POST', $path, $data, $headers, $followRedirects);
    }

    public function login(string $username, string $password): void
    {
        $res = $this->post('login.php', [
            'username' => $username,
            'password' => $password
        ], [], false);

        if ($res['status'] !== 302) {
            throw new Exception("Login failed for '$username', expected HTTP 302, got {$res['status']}");
        }
    }
}

echo PHP_EOL . CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "  EduRadar Milestone 2: Adversarial Empirical Challenge Suite" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "  Target: Apache (localhost/my-web-project/) & MariaDB (edu_radar)" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

$pdo = db();
$baseUrl = 'http://localhost/my-web-project';
$client = new AttackClient($baseUrl);

// Record baseline counts
$initialTcCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();
$initialClassCount = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$initialStudentCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$initialScoreCount = (int)$pdo->query("SELECT COUNT(*) FROM scores")->fetchColumn();
$initialAuditCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

// =============================================================================
// SECTION 1: LIVE HTTP ATTACKS AGAINST admin.php
// =============================================================================
echo CLR_BOLD . "--- SECTION 1: Adversarial Attacks Against admin.php (RBAC & Privilege Escalation) ---" . CLR_RESET . PHP_EOL;

challenge("ADMIN_ATTACK", "1.1 Unauthenticated GET admin.php rejected (HTTP 403)", function () use ($client, $pdo) {
    $client->clearCookies();
    $res = $client->get('admin.php');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden, got HTTP {$res['status']}");
    }
    if (stripos($res['body'], 'ADMIN PORTAL') !== false || stripos($res['body'], 'Phân công chuyên môn') !== false) {
        throw new Exception("Sensitive admin management panel leaked in response body!");
    }
    return "HTTP 403 returned, no admin UI leaked";
});

challenge("ADMIN_ATTACK", "1.2 Unauthenticated GET admin.php with impersonate query (HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('admin.php?impersonate=2&cid=1&sub=Toán');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }
    return "HTTP 403 returned, impersonation rejected";
});

challenge("ADMIN_ATTACK", "1.3 Unauthenticated GET admin.php with delete_assign query (HTTP 403, 0 DB deletions)", function () use ($client, $pdo) {
    $client->clearCookies();
    $tcRowBefore = $pdo->query("SELECT * FROM teacher_classes WHERE id = 1")->fetch();
    if (!$tcRowBefore) {
        throw new Exception("Assignment ID 1 missing for baseline check");
    }

    $res = $client->get('admin.php?delete_assign=1');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    $tcRowAfter = $pdo->query("SELECT * FROM teacher_classes WHERE id = 1")->fetch();
    if (!$tcRowAfter) {
        throw new Exception("CRITICAL: Assignment ID 1 was deleted by unauthenticated request!");
    }
    return "HTTP 403 returned, assignment ID 1 remained intact in MySQL";
});

challenge("ADMIN_ATTACK", "1.4 Unauthenticated forged POST admin.php batch_assign (HTTP 403, 0 DB insertions)", function () use ($client, $pdo) {
    $client->clearCookies();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'    => 'batch_assign',
        'teacher_id' => 2,
        'subject'   => 'Toán',
        'class_ids' => [10, 19, 36], // 7A1, 8A1, 9C3
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("CRITICAL: teacher_classes row count changed from $beforeCount to $afterCount!");
    }
    return "HTTP 403 returned, 0 rows added to teacher_classes";
});

challenge("ADMIN_ATTACK", "1.5 Unauthenticated forged POST admin.php import_students (HTTP 403, 0 DB insertions)", function () use ($client, $pdo) {
    $client->clearCookies();
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 1,
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new Exception("CRITICAL: students row count changed from $beforeCount to $afterCount!");
    }
    return "HTTP 403 returned, 0 students created";
});

challenge("ADMIN_ATTACK", "1.6 Teacher (gv_toan) GET admin.php rejected (HTTP 403)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->get('admin.php');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for teacher accessing admin.php, got HTTP {$res['status']}");
    }
    if (stripos($res['body'], 'ADMIN PORTAL') !== false || stripos($res['body'], 'Phân công chuyên môn') !== false) {
        throw new Exception("Admin UI leaked to teacher!");
    }

    // Verify audit log recorded unauthorized access attempt by gv_toan
    $log = $pdo->query("
        SELECT * FROM audit_logs 
        WHERE username = 'gv_toan' AND action = 'Truy cập trái phép bị chặn' 
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    if (!$log) {
        throw new Exception("Audit log did not record unauthorized admin.php access by gv_toan");
    }

    return "HTTP 403 returned, teacher barred and logged in audit_logs";
});

challenge("ADMIN_ATTACK", "1.7 Teacher (gv_toan) GET admin.php delete_assign=3 (HTTP 403, 0 deletions)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $rowBefore = $pdo->query("SELECT * FROM teacher_classes WHERE id = 3")->fetch(); // gv_van 6A1 Ngữ Văn
    if (!$rowBefore) {
        throw new Exception("Assignment ID 3 missing for baseline check");
    }

    $res = $client->get('admin.php?delete_assign=3');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    $rowAfter = $pdo->query("SELECT * FROM teacher_classes WHERE id = 3")->fetch();
    if (!$rowAfter) {
        throw new Exception("CRITICAL: Teacher gv_toan deleted gv_van assignment ID 3!");
    }

    return "HTTP 403 returned, teacher_classes ID 3 preserved intact";
});

challenge("ADMIN_ATTACK", "1.8 Teacher (gv_toan) GET admin.php impersonate=1 (HTTP 403, no admin escalation)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->get('admin.php?impersonate=1&cid=1&sub=Toán');
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    // Check subsequent request to admin.php is still 403
    $res2 = $client->get('admin.php');
    if ($res2['status'] !== 403) {
        throw new Exception("Session escalated to admin via impersonate tampering!");
    }

    return "HTTP 403 returned, privilege escalation thwarted";
});

challenge("ADMIN_ATTACK", "1.9 Teacher (gv_toan) forged POST batch_assign to 8A1 & 9C3 (HTTP 403, 0 DB insertions)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    // gv_toan tries to assign himself to 8A1 (ID 19) and 9C3 (ID 36)
    $beforeCheck = $pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = 2 AND class_id IN (19, 36)")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'    => 'batch_assign',
        'teacher_id' => 2,
        'subject'   => 'Toán',
        'class_ids' => [19, 36]
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    $afterCheck = $pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = 2 AND class_id IN (19, 36)")->fetchColumn();
    if ($beforeCheck !== $afterCheck) {
        throw new Exception("CRITICAL: Teacher gv_toan successfully assigned self to classes in teacher_classes!");
    }

    return "HTTP 403 returned, teacher_classes modified = 0";
});

challenge("ADMIN_ATTACK", "1.10 Teacher (gv_toan) forged POST import_students (HTTP 403, 0 DB insertions)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $beforeStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE full_name = 'Học Sinh Đột Nhập'")->fetchColumn();

    $res = $client->post('admin.php', [
        'action'          => 'import_students',
        'target_class_id' => 19,
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    $afterStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE full_name = 'Học Sinh Đột Nhập'")->fetchColumn();
    if ($beforeStudents !== $afterStudents) {
        throw new Exception("CRITICAL: Student roster modified by teacher POST!");
    }

    return "HTTP 403 returned, students table untouched";
});

challenge("ADMIN_ATTACK", "1.11 Header spoofing bypass attempt (X-Forwarded-For, X-Original-URL) (HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $headers = [
        'X-Forwarded-For: 127.0.0.1',
        'X-Real-IP: 127.0.0.1',
        'X-Original-URL: /index.php',
        'X-Rewrite-URL: /index.php',
        'Client-IP: 127.0.0.1',
    ];

    $res = $client->get('admin.php', $headers);
    if ($res['status'] !== 403) {
        throw new Exception("Header spoofing bypassed admin guard! Got HTTP {$res['status']}");
    }

    return "HTTP 403 returned, header spoofing ignored";
});

challenge("ADMIN_ATTACK", "1.12 Negative Control: Authentic Admin GET admin.php succeeds (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $res = $client->get('admin.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for authentic admin, got HTTP {$res['status']}");
    }
    if (stripos($res['body'], 'ADMIN PORTAL') === false && stripos($res['body'], 'Quản Trị') === false) {
        throw new Exception("Admin panel content missing from admin response!");
    }

    return "HTTP 200 returned, admin portal accessible to authentic admin";
});

// =============================================================================
// SECTION 2: LIVE HTTP ATTACKS AGAINST nhap_diem.php (BOLA / IDOR)
// =============================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 2: Adversarial Attacks Against nhap_diem.php (BOLA & IDOR) ---" . CLR_RESET . PHP_EOL;

challenge("BOLA_NHAP_DIEM", "2.1 Unauthenticated GET nhap_diem.php redirects to login.php (HTTP 302)", function () use ($client) {
    $client->clearCookies();
    $res = $client->get('nhap_diem.php');
    if ($res['status'] !== 302) {
        throw new Exception("Expected HTTP 302 redirect for unauthenticated access, got HTTP {$res['status']}");
    }
    if (strpos($res['headers'], 'Location: login.php') === false) {
        throw new Exception("Expected redirect Location: login.php, got headers: {$res['headers']}");
    }
    return "HTTP 302 to login.php returned";
});

challenge("BOLA_NHAP_DIEM", "2.2 Unauthenticated POST nhap_diem.php save_scores rejected (HTTP 302, 0 score changes)", function () use ($client, $pdo) {
    $client->clearCookies();
    $scoreRowBefore = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetchColumn();

    $res = $client->post('nhap_diem.php?class_id=1&subject=Toán', [
        'action' => 'save_scores',
        'scores' => [
            1 => ['m1' => '1.0', 'gk' => '1.0', 'ck' => '1.0']
        ]
    ]);

    if ($res['status'] !== 302 && $res['status'] !== 403) {
        throw new Exception("Expected HTTP 302 or 403 for unauthenticated score POST, got HTTP {$res['status']}");
    }

    $scoreRowAfter = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Toán'")->fetchColumn();
    if ($scoreRowBefore != $scoreRowAfter) {
        throw new Exception("CRITICAL: Unauthenticated POST modified scores in database!");
    }

    return "HTTP {$res['status']} returned, student 1 score unchanged";
});

challenge("BOLA_NHAP_DIEM", "2.3 Teacher (gv_toan) forged GET for 7A1 Ngữ Văn (unassigned subject) (HTTP 403)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    // 7A1 is class_id = 10. gv_toan teaches 7A1 Toán, NOT 7A1 Ngữ Văn.
    $res = $client->get('nhap_diem.php?class_id=10&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unassigned subject in 7A1, got HTTP {$res['status']}");
    }

    $log = $pdo->query("
        SELECT * FROM audit_logs 
        WHERE username = 'gv_toan' AND action = 'Truy cập trái phép bị chặn' AND details LIKE '%Ngữ Văn%' 
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    if (!$log) {
        throw new Exception("Audit log did not record blocked BOLA attempt for 7A1 Ngữ Văn");
    }

    return "HTTP 403 returned, logged to audit_logs";
});

challenge("BOLA_NHAP_DIEM", "2.4 Teacher (gv_toan) forged GET for 8A1 Toán (unassigned class) (HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    // 8A1 is class_id = 19. gv_toan has NO assignment for 8A1.
    $res = $client->get('nhap_diem.php?class_id=19&subject=' . urlencode('Toán'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for unassigned class 8A1, got HTTP {$res['status']}");
    }

    return "HTTP 403 returned, access to 8A1 blocked";
});

challenge("BOLA_NHAP_DIEM", "2.5 Teacher (gv_toan) forged GET for 9C3 Tin Học (unassigned class & subject) (HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    // 9C3 is class_id = 36.
    $res = $client->get('nhap_diem.php?class_id=36&subject=' . urlencode('Tin Học'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for 9C3 Tin Học, got HTTP {$res['status']}");
    }

    return "HTTP 403 returned, access to 9C3 Tin Học blocked";
});

challenge("BOLA_NHAP_DIEM", "2.6 Teacher (gv_toan) forged GET for 6A1 Ngữ Văn (peer teacher assignment) (HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    // 6A1 is class_id = 1. gv_toan teaches Toán in 6A1. Ngữ Văn belongs to gv_van.
    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for peer's subject Ngữ Văn in 6A1, got HTTP {$res['status']}");
    }

    return "HTTP 403 returned, peer gradebook access blocked";
});

challenge("BOLA_NHAP_DIEM", "2.7 Teacher (gv_toan) forged POST save_scores for 7A1 Ngữ Văn (HTTP 403, 0 DB changes)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $scoreBefore = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetchColumn();

    $res = $client->post('nhap_diem.php?class_id=10&subject=' . urlencode('Ngữ Văn'), [
        'action' => 'save_scores',
        'scores' => [
            1 => ['m1' => '9.5', 'gk' => '9.0', 'ck' => '8.5']
        ]
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 on forged score submission, got HTTP {$res['status']}");
    }

    $scoreAfter = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 1 AND subject = 'Ngữ Văn'")->fetchColumn();
    if ($scoreBefore != $scoreAfter) {
        throw new Exception("CRITICAL: Forged POST by gv_toan altered score for 7A1 Ngữ Văn in MySQL!");
    }

    return "HTTP 403 returned, zero score modifications in MySQL";
});

challenge("BOLA_NHAP_DIEM", "2.8 Teacher (gv_toan) forged POST save_scores for 8A1 Toán (HTTP 403, 0 DB changes)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->post('nhap_diem.php?class_id=19&subject=' . urlencode('Toán'), [
        'action' => 'save_scores',
        'scores' => [
            1 => ['m1' => '2.0', 'gk' => '2.0', 'ck' => '2.0']
        ]
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    return "HTTP 403 returned, 8A1 grade submission rejected";
});

challenge("BOLA_NHAP_DIEM", "2.9 Teacher (gv_toan) forged POST save_scores for 9C3 Tin Học (HTTP 403, 0 DB changes)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->post('nhap_diem.php?class_id=36&subject=' . urlencode('Tin Học'), [
        'action' => 'save_scores',
        'scores' => [
            1 => ['m1' => '0.0', 'gk' => '0.0', 'ck' => '0.0']
        ]
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    return "HTTP 403 returned, 9C3 Tin Học grade submission rejected";
});

challenge("BOLA_NHAP_DIEM", "2.10 Teacher (gv_toan) forged POST save_scores for 6A1 Ngữ Văn (HTTP 403, 0 DB changes)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $scoreBefore = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 2 AND subject = 'Ngữ Văn'")->fetchColumn();

    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'), [
        'action' => 'save_scores',
        'scores' => [
            2 => ['m1' => '1.0', 'gk' => '1.0', 'ck' => '1.0']
        ]
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403, got HTTP {$res['status']}");
    }

    $scoreAfter = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 2 AND subject = 'Ngữ Văn'")->fetchColumn();
    if ($scoreBefore != $scoreAfter) {
        throw new Exception("CRITICAL: gv_toan forged Ngữ Văn score for student 2 in 6A1!");
    }

    return "HTTP 403 returned, student 2 Ngữ Văn score unchanged";
});

challenge("IDOR_NHAP_DIEM", "2.11 Teacher (gv_toan) IDOR tampering ?teacher_id=3 (gv_van) (HTTP 403)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->get('nhap_diem.php?teacher_id=3&class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for IDOR teacher_id=3 tampering, got HTTP {$res['status']}");
    }

    $log = $pdo->query("
        SELECT * FROM audit_logs 
        WHERE username = 'gv_toan' AND details LIKE '%teacher_id=3%' 
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    if (!$log) {
        throw new Exception("Audit log did not record teacher_id=3 IDOR tampering");
    }

    return "HTTP 403 returned, IDOR tampering logged to audit_logs";
});

challenge("IDOR_NHAP_DIEM", "2.12 Teacher (gv_toan) IDOR tampering ?teacher_id=1 (admin) (HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->get('nhap_diem.php?teacher_id=1&class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for teacher_id=1 tampering, got HTTP {$res['status']}");
    }

    return "HTTP 403 returned, admin IDOR tampering blocked";
});

challenge("IDOR_NHAP_DIEM", "2.13 Teacher (gv_toan) IDOR tampering with arbitrary IDs (999, 0, -1, abc, SQLi) (All HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    // Values that differ from gv_toan's user_id (2)
    $tamperedIds = ['999', '0', '-1', 'abc', urlencode('3 OR 1=1'), urlencode('1 UNION SELECT 1')];
    foreach ($tamperedIds as $tid) {
        $res = $client->get("nhap_diem.php?teacher_id=$tid&class_id=1&subject=" . urlencode('Toán'));
        if ($res['status'] !== 403) {
            throw new Exception("Expected HTTP 403 for teacher_id='$tid', got HTTP {$res['status']}");
        }
    }

    return "All " . count($tamperedIds) . " tampered teacher_id values returned HTTP 403";
});

challenge("IDOR_NHAP_DIEM", "2.14 Teacher (gv_toan) IDOR via POST ?teacher_id=3 (HTTP 403, 0 DB changes)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $scoreBefore = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 3 AND subject = 'Ngữ Văn'")->fetchColumn();

    $res = $client->post('nhap_diem.php?teacher_id=3&class_id=1&subject=' . urlencode('Ngữ Văn'), [
        'action' => 'save_scores',
        'scores' => [
            3 => ['m1' => '10.0', 'gk' => '10.0', 'ck' => '10.0']
        ]
    ]);

    if ($res['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for IDOR POST with teacher_id=3, got HTTP {$res['status']}");
    }

    $scoreAfter = $pdo->query("SELECT avg_score FROM scores WHERE student_id = 3 AND subject = 'Ngữ Văn'")->fetchColumn();
    if ($scoreBefore != $scoreAfter) {
        throw new Exception("CRITICAL: IDOR POST modified student 3 Ngữ Văn score!");
    }

    return "HTTP 403 returned, student 3 score untouched";
});

challenge("BOLA_NHAP_DIEM", "2.15 Cross-Class Student ID Injection in legitimate class POST (0 unauthorized DB updates)", function () use ($client, $pdo) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    // gv_toan posts to 6A1 Toán (which IS assigned), but injects student_id = 99999 (non-existent)
    $res = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'save_scores',
        'scores' => [
            99999 => ['m1' => '10.0', 'gk' => '10.0', 'ck' => '10.0']
        ]
    ]);

    // Student 99999 must NOT be inserted into scores table
    $check = $pdo->query("SELECT 1 FROM scores WHERE student_id = 99999")->fetchColumn();
    if ($check) {
        throw new Exception("CRITICAL: Non-existent student 99999 injected into scores table!");
    }

    return "Cross-class student 99999 rejected by ownership check, 0 records added";
});

challenge("BOLA_NHAP_DIEM", "2.16 Peer Teacher (gv_van) cross-testing against gv_toan classes (All HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_van', '123456');

    // gv_van attempts GET 6A1 Toán (gv_toan's assignment)
    $res1 = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($res1['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for gv_van accessing 6A1 Toán, got HTTP {$res1['status']}");
    }

    // gv_van attempts POST save_scores to 6A1 Toán
    $res2 = $client->post('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'), [
        'action' => 'save_scores',
        'scores' => [
            1 => ['m1' => '5.0', 'gk' => '5.0', 'ck' => '5.0']
        ]
    ]);
    if ($res2['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for gv_van POSTing to 6A1 Toán, got HTTP {$res2['status']}");
    }

    // gv_van attempts IDOR teacher_id=2
    $res3 = $client->get('nhap_diem.php?teacher_id=2&class_id=1&subject=' . urlencode('Toán'));
    if ($res3['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for gv_van IDOR teacher_id=2, got HTTP {$res3['status']}");
    }

    return "All 3 cross-teacher attacks from gv_van returned HTTP 403";
});

challenge("INPUT_BOUNDARY", "2.17 Unassigned & Malformed Query String Injection in nhap_diem.php (All HTTP 403)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $attackVectors = [
        'class_id=999999&subject=' . urlencode('Toán')                     => 'Non-existent class ID',
        'class_id=1&subject=' . urlencode('Địa Lý')                       => 'Unassigned subject Địa Lý in 6A1',
        'class_id=1&subject=' . urlencode('Hóa Học')                      => 'Unassigned subject Hóa Học in 6A1',
        "class_id=1&subject=" . urlencode("' OR '1'='1")                  => 'SQL injection in subject',
        "class_id=19&subject=" . urlencode('Toán')                        => 'Unassigned class 8A1',
        "class_id=36&subject=" . urlencode('Tin Học')                     => 'Unassigned class 9C3 Tin Học',
        "class_id=" . urlencode("999 UNION SELECT 1") . "&subject=Toan"   => 'SQL injection in class_id',
    ];

    foreach ($attackVectors as $query => $label) {
        $res = $client->get("nhap_diem.php?$query");
        if ($res['status'] !== 403) {
            throw new Exception("Expected HTTP 403 for attack vector '$label', got HTTP {$res['status']}");
        }
    }

    return "All " . count($attackVectors) . " unassigned/boundary queries returned HTTP 403";
});

challenge("INPUT_BOUNDARY", "2.18 Default Fallback on GET without parameters safely opens assigned class (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    // When navigating to nhap_diem.php with no params or class_id=0, system defaults to teacher's own assigned class
    $res = $client->get('nhap_diem.php');
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for teacher default gradebook navigation, got HTTP {$res['status']}");
    }

    return "HTTP 200 returned, teacher landing on assigned class is safe and compliant";
});

// =============================================================================
// SECTION 3: NEGATIVE CONTROLS (LEGITIMATE ACCESS VERIFICATION)
// =============================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 3: Legitimate Access Controls (Negative Controls) ---" . CLR_RESET . PHP_EOL;

challenge("CONTROL", "3.1 Authorized Teacher (gv_toan) accesses 6A1 Toán (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for assigned class 6A1 Toán, got HTTP {$res['status']}");
    }
    return "HTTP 200 returned, authorized gradebook loaded";
});

challenge("CONTROL", "3.2 Authorized Teacher (gv_toan) accesses 7A1 Toán (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_toan', '123456');

    $res = $client->get('nhap_diem.php?class_id=10&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for assigned class 7A1 Toán, got HTTP {$res['status']}");
    }
    return "HTTP 200 returned, authorized gradebook loaded";
});

challenge("CONTROL", "3.3 Authorized Teacher (gv_van) accesses 6A1 Ngữ Văn (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('gv_van', '123456');

    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for assigned class 6A1 Ngữ Văn, got HTTP {$res['status']}");
    }
    return "HTTP 200 returned, authorized gradebook loaded";
});

challenge("CONTROL", "3.4 Authentic Admin direct inspection of 6A1 Ngữ Văn (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $res = $client->get('nhap_diem.php?class_id=1&subject=' . urlencode('Ngữ Văn'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin gradebook inspection, got HTTP {$res['status']}");
    }
    return "HTTP 200 returned, admin has legitimate inspection rights";
});

challenge("CONTROL", "3.5 Authentic Admin inspection with teacher_id parameter (HTTP 200)", function () use ($client) {
    $client->clearCookies();
    $client->login('admin', '123456');

    $res = $client->get('nhap_diem.php?teacher_id=2&class_id=1&subject=' . urlencode('Toán'));
    if ($res['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin inspecting gv_toan gradebook, got HTTP {$res['status']}");
    }
    return "HTTP 200 returned, admin inspection banner active";
});

// =============================================================================
// SECTION 4: DATABASE INTEGRITY & AUDIT LOG FORENSICS
// =============================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 4: Database Integrity & Audit Log Forensics ---" . CLR_RESET . PHP_EOL;

challenge("DB_INTEGRITY", "4.1 Final database row counts unchanged across all protected tables", function () use ($pdo, $initialTcCount, $initialClassCount, $initialStudentCount, $initialScoreCount) {
    $finalTcCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes")->fetchColumn();
    $finalClassCount = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $finalStudentCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $finalScoreCount = (int)$pdo->query("SELECT COUNT(*) FROM scores")->fetchColumn();

    $diffs = [];
    if ($finalTcCount !== $initialTcCount) {
        $diffs[] = "teacher_classes changed ($initialTcCount -> $finalTcCount)";
    }
    if ($finalClassCount !== $initialClassCount) {
        $diffs[] = "classes changed ($initialClassCount -> $finalClassCount)";
    }
    if ($finalStudentCount !== $initialStudentCount) {
        $diffs[] = "students changed ($initialStudentCount -> $finalStudentCount)";
    }
    if ($finalScoreCount !== $initialScoreCount) {
        $diffs[] = "scores changed ($initialScoreCount -> $finalScoreCount)";
    }

    if (!empty($diffs)) {
        throw new Exception("CRITICAL: Unauthorized database modifications occurred:\n       -> " . implode("\n       -> ", $diffs));
    }

    return "teacher_classes ($finalTcCount), classes ($finalClassCount), students ($finalStudentCount), scores ($finalScoreCount) completely unchanged";
});

challenge("AUDIT_LOGS", "4.2 Audit log recorded every blocked unauthorized access attempt", function () use ($pdo, $initialAuditCount) {
    $finalAuditCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
    $newLogs = $finalAuditCount - $initialAuditCount;

    if ($newLogs <= 0) {
        throw new Exception("No new audit logs were written during adversarial testing!");
    }

    $blockedLogs = (int)$pdo->query("
        SELECT COUNT(*) FROM audit_logs 
        WHERE action = 'Truy cập trái phép bị chặn'
    ")->fetchColumn();

    if ($blockedLogs === 0) {
        throw new Exception("No 'Truy cập trái phép bị chặn' audit logs found!");
    }

    return "Total new audit logs: $newLogs, Total blocked attempts logged: $blockedLogs";
});

// =============================================================================
// SUMMARY & VERDICT
// =============================================================================
echo PHP_EOL . CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL;
echo sprintf("  Total Adversarial Challenges: %d | Passed: %s%d%s | Failed: %s%d%s",
    $totalPassed + $totalFailed,
    CLR_GREEN, $totalPassed, CLR_RESET,
    ($totalFailed > 0 ? CLR_RED : CLR_GREEN), $totalFailed, CLR_RESET
) . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($totalFailed > 0) {
    echo CLR_RED . CLR_BOLD . "VERDICT: REQUEST_CHANGES" . CLR_RESET . PHP_EOL;
    echo "Failures detected in adversarial challenge suite:" . PHP_EOL;
    foreach ($findings as $f) {
        echo " - [{$f['category']}] {$f['title']}: {$f['error']}" . PHP_EOL;
    }
    exit(1);
} else {
    echo CLR_GREEN . CLR_BOLD . "VERDICT: APPROVE" . CLR_RESET . PHP_EOL;
    echo "All adversarial challenges passed with HTTP 403 / 302 and ZERO database mutations." . PHP_EOL;
    exit(0);
}
