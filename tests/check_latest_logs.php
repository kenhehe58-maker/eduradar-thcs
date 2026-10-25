<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$logs = $pdo->query("SELECT id, username, action, details, created_at FROM audit_logs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
echo "=== LATEST 20 AUDIT LOGS ===\n";
foreach ($logs as $l) {
    echo "#{$l['id']} [{$l['created_at']}] {$l['username']}: {$l['action']} - {$l['details']}\n";
}
