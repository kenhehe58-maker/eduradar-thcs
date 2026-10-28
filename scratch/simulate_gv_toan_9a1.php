<?php
// Simulating teacher gv_toan viewing 9A1 on index.php
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/radar_helper.php';
require_once dirname(__DIR__) . '/includes/algorithms.php';

$pdo = db();
$students = $pdo->query("
    SELECT s.id, s.full_name, s.student_code, c.name as class_name,
           sc.subject, sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score, sc.teacher_note
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN scores sc ON s.id = sc.student_id
    WHERE c.name = '9A1' AND sc.subject = 'Toán'
    ORDER BY s.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total 9A1 Math records: " . count($students) . "\n";

$trendCounts = [];
$tierCounts = [];

foreach ($students as $st) {
    $tx = [$st['tx1'], $st['tx2'], $st['tx3'], $st['tx4']];
    $trend = analyzeLearningTrend($st['avg_score'], $st['gk'], $st['ck'], $tx);
    $tier = determineScoreTier($st['avg_score'], $st['gk'], $st['ck'], $tx);
    
    $trendCounts[$trend['trend_label']] = ($trendCounts[$trend['trend_label']] ?? 0) + 1;
    $tierCounts[$tier['label']] = ($tierCounts[$tier['label']] ?? 0) + 1;
}

echo "\nTREND BREAKDOWN:\n";
print_r($trendCounts);

echo "\nTIER BREAKDOWN:\n";
print_r($tierCounts);
