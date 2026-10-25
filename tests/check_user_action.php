<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$logs = $pdo->query("SELECT action, details, created_at FROM audit_logs WHERE action LIKE '%phân công%' ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "=== RECENT AUDIT LOGS FOR PHÂN CÔNG ===\n";
foreach ($logs as $l) {
    echo "[{$l['created_at']}] {$l['action']}: {$l['details']}\n";
}
echo "\n=== TEACHER CLASSES FOR gv_toan ===\n";
$tcs = $pdo->query("SELECT u.username, u.full_name, c.name as class_name, tc.subject FROM teacher_classes tc JOIN users u ON tc.teacher_id = u.id JOIN classes c ON tc.class_id = c.id WHERE u.username = 'gv_toan'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($tcs as $tc) {
    echo "{$tc['username']} -> Lớp {$tc['class_name']} ({$tc['subject']})\n";
}
