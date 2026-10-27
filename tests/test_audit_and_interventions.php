<?php
/**
 * Test Suite: Score Audit Logs & Student Interventions
 * Verifies system-design-primer Audit Trail and Pedagogical Intervention Tracker
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

echo "========================================================================\n";
echo "  EDURADAR THCS — AUDIT LOG & INTERVENTION TRACKER TEST SUITE\n";
echo "========================================================================\n\n";

$pdo = db();
$pass = 0;
$fail = 0;

function assert_test($cond, $name) {
    global $pass, $fail;
    if ($cond) {
        echo "  [PASS] $name\n";
        $pass++;
    } else {
        echo "  [FAIL] $name\n";
        $fail++;
    }
}

// 1. Kiểm tra cấu trúc bảng & index
echo "--- SUITE 1: Table Schema & Index Verifications ---\n";
$auditTable = $pdo->query("SHOW TABLES LIKE 'score_audit_logs'")->fetch();
assert_test(!empty($auditTable), "Table score_audit_logs exists in database");

$intervTable = $pdo->query("SHOW TABLES LIKE 'student_interventions'")->fetch();
assert_test(!empty($intervTable), "Table student_interventions exists in database");

$idxScores = $pdo->query("SHOW INDEX FROM scores WHERE Key_name = 'idx_scores_student_subject'")->fetchAll();
assert_test(!empty($idxScores), "Performance index idx_scores_student_subject exists on scores");

// 2. Kiểm thử hàm log_score_audit
echo "\n--- SUITE 2: Audit Trail Event Logging ---\n";
$testStId = 87; // Mai Ngọc Khánh Băng
$testSub = 'Toán';
$now = date('Y-m-d H:i:s');

log_score_audit($testStId, $testSub, 'tx1', 3.6, 4.0, 1, 'admin', 'update', 'Kiểm thử Audit Log TX1');

$recentLog = $pdo->query("
    SELECT * FROM score_audit_logs 
    WHERE student_id = $testStId AND subject = '$testSub' AND score_field = 'tx1' 
    ORDER BY id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

assert_test(!empty($recentLog), "Audit log entry successfully created");
assert_test((float)$recentLog['old_value'] === 3.6, "Audit log preserves correct old_value (3.6)");
assert_test((float)$recentLog['new_value'] === 4.0, "Audit log preserves correct new_value (4.0)");
assert_test($recentLog['changed_by_name'] === 'admin', "Audit log records operator identity ('admin')");

// 3. Kiểm thử hàm record_student_intervention
echo "\n--- SUITE 3: Student Intervention Lifecycle ---\n";
$resInterv = record_student_intervention(
    $testStId,
    $testSub,
    'yellow',
    'Phụ đạo kèm cặp ngoài giờ',
    5.5,
    'in_progress',
    'Em tiến bộ sau khi đổi chỗ ngồi',
    1
);
assert_test($resInterv === true, "record_student_intervention returned true");

$intervList = get_student_interventions($testStId, $testSub);
assert_test(!empty($intervList), "get_student_interventions returned non-empty list");
assert_test($intervList[0]['action_type'] === 'Phụ đạo kèm cặp ngoài giờ', "Intervention action matches recorded plan");
assert_test($intervList[0]['status'] === 'in_progress', "Intervention status set to 'in_progress'");

// 4. Kiểm thử Simple Icons integration
echo "\n--- SUITE 4: Simple Icons Vector Integration ---\n";
require_once __DIR__ . '/../includes/icons.php';
$ghIcon = get_simple_icon('github', 20);
assert_test(strpos($ghIcon, '<svg') !== false && strpos($ghIcon, 'viewBox="0 0 24 24"') !== false, "Simple icon 'github' renders valid SVG");
$mysqlIcon = get_simple_icon('mysql', 16);
assert_test(strpos($mysqlIcon, '<svg') !== false, "Simple icon 'mysql' renders valid SVG");

echo "\n========================================================================\n";
echo "  TEST SUMMARY: $pass PASSED, $fail FAILED\n";
echo "========================================================================\n";

if ($fail > 0) exit(1);
