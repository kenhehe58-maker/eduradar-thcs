<?php
require_once __DIR__ . '/includes/db.php';
$pdo = db();
$stmt = $pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 25');
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($logs as $l) {
    echo "[{$l['created_at']}] User: {$l['username']} ({$l['user_id']}) | Action: {$l['action']} | Details: {$l['details']}\n";
}
