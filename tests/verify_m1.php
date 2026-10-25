<?php
/**
 * Automated Milestone 1 Verification Suite (EduRadar)
 * Tests against live MySQL (edu_radar) and PHP CLI.
 * 
 * Run with:
 *   D:\xam\php\php.exe tests/verify_m1.php
 */

declare(strict_types=1);

// ANSI Color formatting
const CLR_RESET  = "\033[0m";
const CLR_GREEN  = "\033[1;32m";
const CLR_RED    = "\033[1;31m";
const CLR_YELLOW = "\033[1;33m";
const CLR_CYAN   = "\033[1;36m";

$passedCount = 0;
$failedCount = 0;

function assertTest(string $name, callable $testFn): void
{
    global $passedCount, $failedCount;
    echo CLR_CYAN . "[TEST] " . CLR_RESET . str_pad($name, 62, ".");
    try {
        $result = $testFn();
        if ($result === false) {
            throw new Exception("Assertion returned false");
        }
        $passedCount++;
        echo CLR_GREEN . " [PASS]" . CLR_RESET . PHP_EOL;
    } catch (Throwable $e) {
        $failedCount++;
        echo CLR_RED . " [FAIL]" . CLR_RESET . PHP_EOL;
        echo CLR_RED . "       -> Error: " . $e->getMessage() . CLR_RESET . PHP_EOL;
    }
}

echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "  EduRadar Milestone 1: Automated Verification Suite" . CLR_RESET . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

// -------------------------------------------------------------
// Test 1: Configuration & SQLite Elimination
// -------------------------------------------------------------
assertTest("1.1 File database.sqlite is deleted", function () {
    $sqlitePath = dirname(__DIR__) . '/database.sqlite';
    if (file_exists($sqlitePath)) {
        throw new Exception("database.sqlite exists at: $sqlitePath");
    }
    return true;
});

assertTest("1.2 includes/config.php loads cleanly without SQLite constants", function () {
    require_once dirname(__DIR__) . '/includes/config.php';
    if (defined('SQLITE_FILE')) {
        throw new Exception("SQLITE_FILE constant is still defined in config.php");
    }
    if (defined('DB_DRIVER')) {
        throw new Exception("DB_DRIVER constant is still defined in config.php");
    }
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('BASE_URL')) {
        throw new Exception("Missing core DB configuration constants or BASE_URL");
    }
    return true;
});

assertTest("1.3 includes/db.php establishes pure PDO MySQL singleton", function () {
    require_once dirname(__DIR__) . '/includes/db.php';
    if (!function_exists('db')) {
        throw new Exception("function db() is not declared");
    }
    if (function_exists('initSQLite')) {
        throw new Exception("Legacy initSQLite() function still exists in db.php");
    }
    $pdo1 = db();
    $pdo2 = db();
    if (!($pdo1 instanceof PDO) || $pdo1 !== $pdo2) {
        throw new Exception("db() did not return a valid singleton PDO instance");
    }
    $driver = $pdo1->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver !== 'mysql') {
        throw new Exception("Expected PDO driver 'mysql', got '$driver'");
    }
    return true;
});

// -------------------------------------------------------------
// Test 2: Database Schema & Constraints
// -------------------------------------------------------------
assertTest("2.1 All 6 required tables exist in edu_radar", function () {
    $pdo = db();
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'edu_radar'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required = ['users', 'classes', 'teacher_classes', 'students', 'scores', 'audit_logs'];
    $missing = array_diff($required, $tables);
    if (!empty($missing)) {
        throw new Exception("Missing tables: " . implode(', ', $missing));
    }
    return true;
});

assertTest("2.2 UNIQUE constraints enforced on all required business keys", function () {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT tc.TABLE_NAME, tc.CONSTRAINT_NAME, kcu.COLUMN_NAME
        FROM information_schema.TABLE_CONSTRAINTS tc
        JOIN information_schema.KEY_COLUMN_USAGE kcu
          ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
        WHERE tc.TABLE_SCHEMA = 'edu_radar' AND tc.CONSTRAINT_TYPE = 'UNIQUE'
        ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
    ");
    $rows = $stmt->fetchAll();
    $uniques = [];
    foreach ($rows as $r) {
        $uniques[$r['TABLE_NAME']][$r['CONSTRAINT_NAME']][] = $r['COLUMN_NAME'];
    }

    // Verify users(username)
    $hasUser = false;
    foreach ($uniques['users'] ?? [] as $cols) {
        if ($cols === ['username']) $hasUser = true;
    }
    if (!$hasUser) throw new Exception("users table missing UNIQUE(username)");

    // Verify classes(name)
    $hasClass = false;
    foreach ($uniques['classes'] ?? [] as $cols) {
        if ($cols === ['name']) $hasClass = true;
    }
    if (!$hasClass) throw new Exception("classes table missing UNIQUE(name)");

    // Verify teacher_classes(teacher_id, class_id, subject)
    $hasTc = false;
    foreach ($uniques['teacher_classes'] ?? [] as $cols) {
        if ($cols === ['teacher_id', 'class_id', 'subject']) $hasTc = true;
    }
    if (!$hasTc) throw new Exception("teacher_classes missing UNIQUE(teacher_id, class_id, subject)");

    // Verify students(class_id, full_name, birthday)
    $hasStudent = false;
    foreach ($uniques['students'] ?? [] as $cols) {
        if ($cols === ['class_id', 'full_name', 'birthday']) $hasStudent = true;
    }
    if (!$hasStudent) throw new Exception("students table missing UNIQUE(class_id, full_name, birthday)");

    // Verify scores(student_id, subject)
    $hasScore = false;
    foreach ($uniques['scores'] ?? [] as $cols) {
        if ($cols === ['student_id', 'subject']) $hasScore = true;
    }
    if (!$hasScore) throw new Exception("scores table missing UNIQUE(student_id, subject)");

    return true;
});

assertTest("2.3 Foreign Keys with ON DELETE CASCADE configured correctly", function () {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT rc.CONSTRAINT_NAME, rc.TABLE_NAME, rc.REFERENCED_TABLE_NAME, rc.DELETE_RULE, kcu.COLUMN_NAME
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        JOIN information_schema.KEY_COLUMN_USAGE kcu
          ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
        WHERE rc.CONSTRAINT_SCHEMA = 'edu_radar'
    ");
    $fks = $stmt->fetchAll();
    $fkMap = [];
    foreach ($fks as $fk) {
        $fkMap[$fk['TABLE_NAME']][$fk['COLUMN_NAME']] = [
            'ref' => $fk['REFERENCED_TABLE_NAME'],
            'rule' => $fk['DELETE_RULE']
        ];
    }

    if (($fkMap['teacher_classes']['teacher_id']['ref'] ?? null) !== 'users' ||
        ($fkMap['teacher_classes']['teacher_id']['rule'] ?? null) !== 'CASCADE') {
        throw new Exception("teacher_classes.teacher_id does not reference users with CASCADE");
    }
    if (($fkMap['teacher_classes']['class_id']['ref'] ?? null) !== 'classes' ||
        ($fkMap['teacher_classes']['class_id']['rule'] ?? null) !== 'CASCADE') {
        throw new Exception("teacher_classes.class_id does not reference classes with CASCADE");
    }
    if (($fkMap['students']['class_id']['ref'] ?? null) !== 'classes' ||
        ($fkMap['students']['class_id']['rule'] ?? null) !== 'CASCADE') {
        throw new Exception("students.class_id does not reference classes with CASCADE");
    }
    if (($fkMap['scores']['student_id']['ref'] ?? null) !== 'students' ||
        ($fkMap['scores']['student_id']['rule'] ?? null) !== 'CASCADE') {
        throw new Exception("scores.student_id does not reference students with CASCADE");
    }
    return true;
});

assertTest("2.4 scores table has modern VnEdu columns (tx1..tx4, gk, ck, avg_score)", function () {
    $pdo = db();
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'edu_radar' AND TABLE_NAME = 'scores'");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredCols = ['tx1', 'tx2', 'tx3', 'tx4', 'gk', 'ck', 'avg_score', 'updated_at'];
    $diff = array_diff($requiredCols, $cols);
    if (!empty($diff)) {
        throw new Exception("scores table missing columns: " . implode(', ', $diff));
    }
    return true;
});

// -------------------------------------------------------------
// Test 3: Deduplication & Seed Verification
// -------------------------------------------------------------
assertTest("3.1 Zero duplicate rows exist across all tables", function () {
    $pdo = db();
    $queries = [
        'users'           => "SELECT username, COUNT(*) c FROM users GROUP BY username HAVING c > 1",
        'classes'         => "SELECT name, COUNT(*) c FROM classes GROUP BY name HAVING c > 1",
        'teacher_classes' => "SELECT teacher_id, class_id, subject, COUNT(*) c FROM teacher_classes GROUP BY teacher_id, class_id, subject HAVING c > 1",
        'students'        => "SELECT class_id, full_name, birthday, COUNT(*) c FROM students GROUP BY class_id, full_name, birthday HAVING c > 1",
        'scores'          => "SELECT student_id, subject, COUNT(*) c FROM scores GROUP BY student_id, subject HAVING c > 1",
    ];
    foreach ($queries as $tbl => $sql) {
        $duplicates = $pdo->query($sql)->fetchAll();
        if (count($duplicates) > 0) {
            throw new Exception("Duplicate rows found in table '$tbl'");
        }
    }
    return true;
});

assertTest("3.2 36 Classes seeded covering Grades 6-9 and Sections A-C", function () {
    $pdo = db();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    if ($count !== 36) {
        throw new Exception("Expected 36 classes, found $count");
    }
    return true;
});

// -------------------------------------------------------------
// Test 4: Authentication & Bcrypt Verification
// -------------------------------------------------------------
assertTest("4.1 Passwords for admin, gv_toan, gv_van verify correctly with '123456'", function () {
    $pdo = db();
    $accounts = ['admin', 'gv_toan', 'gv_van'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
    foreach ($accounts as $acc) {
        $stmt->execute([$acc]);
        $hash = $stmt->fetchColumn();
        if (!$hash) {
            throw new Exception("Account '$acc' not found in users table");
        }
        if (!password_verify('123456', $hash)) {
            throw new Exception("password_verify('123456') failed for account '$acc'");
        }
        if (password_verify('invalid_pwd_test', $hash)) {
            throw new Exception("password_verify unexpectedly returned true for wrong password on '$acc'");
        }
    }
    return true;
});

// -------------------------------------------------------------
// Test 5: Audit Logging Helper
// -------------------------------------------------------------
assertTest("5.1 log_action() successfully writes audit record with IP and prepared stmt", function () {
    $token = 'M1_TEST_' . bin2hex(random_bytes(6));
    $details = 'Verification test payload created at ' . date('c');
    log_action(1, 'admin', $token, $details);

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$token]);
    $log = $stmt->fetch();
    if (!$log) {
        throw new Exception("log_action() did not write record to audit_logs table");
    }
    if ((int)$log['user_id'] !== 1 || $log['username'] !== 'admin' || $log['details'] !== $details) {
        throw new Exception("audit_logs record content does not match expected payload");
    }
    if (empty($log['ip_address'])) {
        throw new Exception("ip_address was not captured in audit_logs record");
    }
    return true;
});

assertTest("5.2 Backward-compatible logAction() wrapper works seamlessly", function () {
    if (!function_exists('logAction')) {
        throw new Exception("logAction() wrapper function is not declared in db.php");
    }
    $token = 'WRAPPER_TEST_' . bin2hex(random_bytes(6));
    logAction($token, 'Wrapper details test');

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$token]);
    $log = $stmt->fetch();
    if (!$log) {
        throw new Exception("logAction() wrapper failed to write to audit_logs");
    }
    return true;
});

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo PHP_EOL . CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL;
echo sprintf("  Total Tests: %d | Passed: %s%d%s | Failed: %s%d%s",
    $passedCount + $failedCount,
    CLR_GREEN, $passedCount, CLR_RESET,
    ($failedCount > 0 ? CLR_RED : CLR_GREEN), $failedCount, CLR_RESET
) . PHP_EOL;
echo CLR_YELLOW . "============================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($failedCount > 0) {
    exit(1);
}
exit(0);
