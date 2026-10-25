<?php
require_once __DIR__ . '/includes/db.php';
$pdo = db();
$stmt = $pdo->query("SELECT u.username, u.full_name, tc.subject, COUNT(tc.class_id) as class_count, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') as classes FROM teacher_classes tc JOIN users u ON tc.teacher_id = u.id JOIN classes c ON tc.class_id = c.id GROUP BY u.id, tc.subject");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "Teacher: {$r['full_name']} ({$r['username']}) | Subject: {$r['subject']} | Classes ({$r['class_count']}): {$r['classes']}\n\n";
}
