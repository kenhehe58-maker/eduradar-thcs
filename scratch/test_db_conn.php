<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES:\n" . implode("\n", $tables) . "\n\n";

$users = $pdo->query("SELECT id, username, full_name, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
echo "USERS:\n";
print_r($users);

$classes = $pdo->query("SELECT id, name, grade FROM classes")->fetchAll(PDO::FETCH_ASSOC);
echo "CLASSES COUNT: " . count($classes) . "\n";

$students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
echo "STUDENTS COUNT: " . $students . "\n";

$scores = $pdo->query("SELECT COUNT(*) FROM scores")->fetchColumn();
echo "SCORES COUNT: " . $scores . "\n";
