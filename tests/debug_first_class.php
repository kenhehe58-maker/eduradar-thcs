<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$targetTeacherId = 2; // gv_toan

$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.grade, tc.subject,
           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count
    FROM teacher_classes tc 
    JOIN classes c ON tc.class_id = c.id 
    WHERE tc.teacher_id = ?
    ORDER BY (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) DESC, c.grade ASC, c.name ASC
");
$stmt->execute([$targetTeacherId]);
$rawAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$assignedClasses = [];
foreach ($rawAssignments as $row) {
    $cid = (int)$row['id'];
    if (!isset($assignedClasses[$cid])) {
        $assignedClasses[$cid] = [
            'id' => $cid,
            'name' => $row['name'],
            'grade' => (int)$row['grade'],
            'student_count' => (int)$row['student_count'],
            'subjects' => []
        ];
    }
    if (!in_array($row['subject'], $assignedClasses[$cid]['subjects'])) {
        $assignedClasses[$cid]['subjects'][] = $row['subject'];
    }
}

$firstCid = array_key_first($assignedClasses);
echo "First Class: ID $firstCid ({$assignedClasses[$firstCid]['name']}) - Students: {$assignedClasses[$firstCid]['student_count']}\n";
echo "Subjects for First Class: " . implode(', ', $assignedClasses[$firstCid]['subjects']) . "\n";

// And what about class 7A6?
$stmt7A6 = $pdo->query("SELECT id FROM classes WHERE name = '7A6'")->fetchColumn();
if ($stmt7A6 && isset($assignedClasses[$stmt7A6])) {
    echo "\nClass 7A6 (ID $stmt7A6):\n";
    echo "Subjects: " . implode(', ', $assignedClasses[$stmt7A6]['subjects']) . "\n";
}
