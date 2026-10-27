<?php
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
echo "=== MIGRATION: AUDIT LOGS & INTERVENTIONS ===\n";

// 1. Bảng score_audit_logs
$pdo->exec("
CREATE TABLE IF NOT EXISTS score_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject VARCHAR(50) NOT NULL,
    score_field VARCHAR(10) NOT NULL,
    old_value DECIMAL(3,1) NULL,
    new_value DECIMAL(3,1) NULL,
    changed_by_user_id INT NULL,
    changed_by_name VARCHAR(100) NULL,
    action_type VARCHAR(20) NOT NULL DEFAULT 'update',
    note VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_student_subject (student_id, subject),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "1. Table score_audit_logs created/ready.\n";

// 2. Bảng student_interventions (Sổ theo dõi can thiệp học sinh)
$pdo->exec("
CREATE TABLE IF NOT EXISTS student_interventions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject VARCHAR(50) NOT NULL,
    risk_level VARCHAR(20) NOT NULL DEFAULT 'yellow',
    action_type VARCHAR(50) NOT NULL,
    target_score DECIMAL(3,1) DEFAULT 5.0,
    status VARCHAR(30) NOT NULL DEFAULT 'in_progress',
    teacher_notes TEXT NULL,
    created_by_user_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_interv_student (student_id),
    INDEX idx_interv_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "2. Table student_interventions created/ready.\n";

// 3. Tối ưu hóa Database Indexing theo system-design-primer
$indexes = [
    ['scores', 'idx_scores_student_subject', 'student_id, subject'],
    ['scores', 'idx_scores_avg', 'avg_score'],
    ['students', 'idx_students_class', 'class_id'],
    ['teacher_classes', 'idx_tc_class_subject', 'class_id, subject']
];

foreach ($indexes as $item) {
    list($table, $idxName, $cols) = $item;
    try {
        $check = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$idxName'")->fetchAll();
        if (empty($check)) {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$idxName` ($cols)");
            echo "3. Added index $idxName on $table ($cols).\n";
        } else {
            echo "3. Index $idxName on $table already exists.\n";
        }
    } catch (Exception $e) {
        echo "3. Index $idxName notice: " . $e->getMessage() . "\n";
    }
}

echo "=== MIGRATION COMPLETE! ===\n";
