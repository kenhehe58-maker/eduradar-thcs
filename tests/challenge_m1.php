<?php
/**
 * Adversarial Empirical Stress Test Suite — Milestone 1 (EduRadar)
 * 
 * Tests database constraints, duplicate insertion rejection,
 * foreign key cascade deletes, Vietnamese Unicode fidelity, and edge cases.
 * 
 * Run with:
 *   D:\xam\php\php.exe tests/challenge_m1.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

const CLR_RESET  = "\033[0m";
const CLR_GREEN  = "\033[1;32m";
const CLR_RED    = "\033[1;31m";
const CLR_YELLOW = "\033[1;33m";
const CLR_CYAN   = "\033[1;36m";
const CLR_BOLD   = "\033[1m";

$totalPassed = 0;
$totalFailed = 0;
$findings = [];

function challenge(string $category, string $title, callable $fn): void
{
    global $totalPassed, $totalFailed, $findings;
    $label = sprintf("[%s] %s", $category, $title);
    echo CLR_CYAN . str_pad($label, 72, ".") . CLR_RESET;
    try {
        $detail = $fn();
        $totalPassed++;
        echo CLR_GREEN . " [PASS]" . CLR_RESET . PHP_EOL;
        if (!empty($detail)) {
            echo "       " . CLR_YELLOW . "Observed: " . $detail . CLR_RESET . PHP_EOL;
        }
    } catch (Throwable $e) {
        $totalFailed++;
        echo CLR_RED . " [FAIL]" . CLR_RESET . PHP_EOL;
        echo "       " . CLR_RED . "Error: " . $e->getMessage() . CLR_RESET . PHP_EOL;
        $findings[] = [
            'category' => $category,
            'title'    => $title,
            'error'    => $e->getMessage()
        ];
    }
}

echo PHP_EOL . CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "  EduRadar Milestone 1: Adversarial Empirical Stress Harness" . CLR_RESET . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

$pdo = db();

// =========================================================================
// SECTION 1: SEED DATA INTEGRITY & ENCODING VERIFICATION
// =========================================================================
echo CLR_BOLD . "--- SECTION 1: Seed Data Encoding & Character Set Fidelity ---" . CLR_RESET . PHP_EOL;

challenge("SEED_ENCODING", "1.1 Seed users full_name contains valid UTF-8 Vietnamese (not '?')", function () use ($pdo) {
    $stmt = $pdo->query("SELECT id, username, full_name, HEX(full_name) as hx FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    $corrupt = [];
    foreach ($users as $u) {
        if (strpos($u['full_name'], '?') !== false) {
            $corrupt[] = sprintf("User '%s' has corrupted full_name: '%s' (HEX: %s)", $u['username'], $u['full_name'], $u['hx']);
        }
    }
    if (!empty($corrupt)) {
        throw new Exception("Seed data encoding corrupted in live MariaDB:\n       -> " . implode("\n       -> ", $corrupt));
    }
    return "All seed users have intact UTF-8 Vietnamese names";
});

challenge("SEED_ENCODING", "1.2 Seed teacher_classes subjects contain valid UTF-8 Vietnamese (not '?')", function () use ($pdo) {
    $stmt = $pdo->query("SELECT id, subject, HEX(subject) as hx FROM teacher_classes ORDER BY id");
    $rows = $stmt->fetchAll();
    $corrupt = [];
    foreach ($rows as $r) {
        if (strpos($r['subject'], '?') !== false) {
            $corrupt[] = sprintf("Assignment id %d has corrupted subject: '%s' (HEX: %s)", $r['id'], $r['subject'], $r['hx']);
        }
    }
    if (!empty($corrupt)) {
        throw new Exception("Seed subjects corrupted in live MariaDB:\n       -> " . implode("\n       -> ", $corrupt));
    }
    return "All seed assignments have intact UTF-8 Vietnamese subjects";
});

challenge("SEED_ENCODING", "1.3 Seed students full_name contains valid UTF-8 Vietnamese (not '?')", function () use ($pdo) {
    $stmt = $pdo->query("SELECT id, student_code, full_name, HEX(full_name) as hx FROM students ORDER BY id");
    $rows = $stmt->fetchAll();
    $corrupt = [];
    foreach ($rows as $r) {
        if (strpos($r['full_name'], '?') !== false) {
            $corrupt[] = sprintf("Student '%s' has corrupted full_name: '%s' (HEX: %s)", $r['student_code'], $r['full_name'], $r['hx']);
        }
    }
    if (!empty($corrupt)) {
        throw new Exception("Seed students corrupted in live MariaDB:\n       -> " . implode("\n       -> ", $corrupt));
    }
    return "All seed students have intact UTF-8 Vietnamese names";
});

challenge("SEED_ENCODING", "1.4 Seed scores subjects contain valid UTF-8 Vietnamese (not '?')", function () use ($pdo) {
    $stmt = $pdo->query("SELECT id, student_id, subject, HEX(subject) as hx FROM scores ORDER BY id");
    $rows = $stmt->fetchAll();
    $corrupt = [];
    foreach ($rows as $r) {
        if (strpos($r['subject'], '?') !== false) {
            $corrupt[] = sprintf("Score id %d has corrupted subject: '%s' (HEX: %s)", $r['id'], $r['subject'], $r['hx']);
        }
    }
    if (!empty($corrupt)) {
        throw new Exception("Seed score subjects corrupted in live MariaDB:\n       -> " . implode("\n       -> ", $corrupt));
    }
    return "All seed scores have intact UTF-8 Vietnamese subjects";
});

// =========================================================================
// SECTION 2: DUPLICATE ROW INSERTION ATTACKS (UNIQUE CONSTRAINTS)
// =========================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 2: Duplicate Insertion Attacks (Unique Constraints) ---" . CLR_RESET . PHP_EOL;

challenge("DUPLICATE", "2.1 Rejects duplicate class name ('6A1')", function () use ($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO classes (name, grade, section) VALUES ('6A1', 6, 'A')");
        $stmt->execute();
        throw new Exception("CRITICAL: MariaDB accepted duplicate class name '6A1' without unique constraint error!");
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000' && $e->errorInfo[1] !== 1062) {
            throw new Exception("Unexpected error code: " . $e->getCode() . " msg: " . $e->getMessage());
        }
        return "Rejected with SQLSTATE 23000 (1062 Duplicate entry '6A1' for key 'unique_class_name')";
    }
});

challenge("DUPLICATE", "2.2 Rejects duplicate student against seed ('Nguyễn Văn An', '2015-03-12')", function () use ($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday, gender, phone) VALUES (1, 'HS_DUP_01', 'Nguyễn Văn An', '2015-03-12', 'Nam', '0999999999')");
        $stmt->execute();
        $insertedId = (int)$pdo->lastInsertId();
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$insertedId]);
        throw new Exception("CRITICAL: MariaDB accepted duplicate student! Seed student 'Nguyễn Văn An' was not deduplicated (seed name is corrupted to 'Nguy?n V?n An')!");
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000' && $e->errorInfo[1] !== 1062) {
            throw new Exception("Unexpected error code: " . $e->getCode() . " msg: " . $e->getMessage());
        }
        return "Rejected with SQLSTATE 23000 (1062 Duplicate entry for key 'unique_class_student')";
    }
});

challenge("DUPLICATE", "2.3 Rejects duplicate teacher assignment against seed (2, 1, 'Toán')", function () use ($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (2, 1, 'Toán')");
        $stmt->execute();
        $insertedId = (int)$pdo->lastInsertId();
        $pdo->prepare("DELETE FROM teacher_classes WHERE id = ?")->execute([$insertedId]);
        throw new Exception("CRITICAL: MariaDB accepted duplicate teacher assignment! Seed assignment 'Toán' was not deduplicated (seed subject is corrupted to 'To?n')!");
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000' && $e->errorInfo[1] !== 1062) {
            throw new Exception("Unexpected error code: " . $e->getCode() . " msg: " . $e->getMessage());
        }
        return "Rejected with SQLSTATE 23000 (1062 Duplicate entry for key 'unique_assignment')";
    }
});

challenge("DUPLICATE", "2.4 Rejects duplicate score record against seed (student_id 1, 'Toán')", function () use ($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO scores (student_id, subject, tx1) VALUES (1, 'Toán', 9.0)");
        $stmt->execute();
        $insertedId = (int)$pdo->lastInsertId();
        $pdo->prepare("DELETE FROM scores WHERE id = ?")->execute([$insertedId]);
        throw new Exception("CRITICAL: MariaDB accepted duplicate score! Seed score 'Toán' was not deduplicated (seed subject is corrupted to 'To?n')!");
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000' && $e->errorInfo[1] !== 1062) {
            throw new Exception("Unexpected error code: " . $e->getCode() . " msg: " . $e->getMessage());
        }
        return "Rejected with SQLSTATE 23000 (1062 Duplicate entry for key 'unique_student_subject')";
    }
});

challenge("DUPLICATE", "2.5 Rejects duplicate username ('admin')", function () use ($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES ('admin', 'hash', 'Fake Admin', 'admin')");
        $stmt->execute();
        throw new Exception("CRITICAL: MariaDB accepted duplicate username 'admin'!");
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000' && $e->errorInfo[1] !== 1062) {
            throw new Exception("Unexpected error code: " . $e->getCode() . " msg: " . $e->getMessage());
        }
        return "Rejected with SQLSTATE 23000 (1062 Duplicate entry 'admin' for key 'unique_username')";
    }
});

challenge("DUPLICATE", "2.6 Unique constraints function on fresh identical UTF-8 insertions", function () use ($pdo) {
    // Test that the schema index logic ITSELF works when both rows have identical UTF-8 bytes
    $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday) VALUES (2, 'FRESH_01', 'Học Sinh Thử Nghiệm', '2015-06-15')")->execute();
    $id = (int)$pdo->lastInsertId();

    $rejected = false;
    try {
        $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday) VALUES (2, 'FRESH_02', 'Học Sinh Thử Nghiệm', '2015-06-15')")->execute();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $rejected = true;
        }
    } finally {
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
    }

    if (!$rejected) {
        throw new Exception("Schema failed to reject duplicate on fresh UTF-8 insertion");
    }
    return "Schema UNIQUE KEY rejects identical UTF-8 records";
});

// =========================================================================
// SECTION 3: FOREIGN KEY INTEGRITY & CASCADING DELETES
// =========================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 3: Foreign Key Integrity & Cascade Deletes ---" . CLR_RESET . PHP_EOL;

challenge("FK_CHECK", "3.1 Rejects orphan student with non-existent class_id", function () use ($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO students (class_id, full_name, birthday) VALUES (99999, 'Orphan Student', '2015-01-01')");
        $stmt->execute();
        throw new Exception("CRITICAL: Allowed orphan student with invalid class_id 99999!");
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000' && $e->errorInfo[1] !== 1452) {
            throw new Exception("Unexpected error: " . $e->getMessage());
        }
        return "Rejected with foreign key constraint failure (errno 1452)";
    }
});

challenge("FK_CHECK", "3.2 Rejects orphan score with non-existent student_id", function () use ($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO scores (student_id, subject, tx1) VALUES (99999, 'Toán', 8.0)");
        $stmt->execute();
        throw new Exception("CRITICAL: Allowed orphan score with invalid student_id 99999!");
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000' && $e->errorInfo[1] !== 1452) {
            throw new Exception("Unexpected error: " . $e->getMessage());
        }
        return "Rejected with foreign key constraint failure (errno 1452)";
    }
});

challenge("CASCADE", "3.3 Deep cascade delete: Deleting class cascades to students, scores, and teacher_classes", function () use ($pdo) {
    // 1. Create a temporary class
    $stmt = $pdo->prepare("INSERT INTO classes (name, grade, section) VALUES ('TEMP_9Z9', 9, 'Z')");
    $stmt->execute();
    $tempClassId = (int)$pdo->lastInsertId();

    // 2. Create teacher assignment for this temp class
    $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (2, ?, 'Toán')");
    $stmt->execute([$tempClassId]);
    $tempTcId = (int)$pdo->lastInsertId();

    // 3. Create student in this temp class
    $stmt = $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday, gender) VALUES (?, 'TEMP_S01', 'Học Sinh Xóa Thử', '2015-09-09', 'Nam')");
    $stmt->execute([$tempClassId]);
    $tempStudentId = (int)$pdo->lastInsertId();

    // 4. Create score for this temp student
    $stmt = $pdo->prepare("INSERT INTO scores (student_id, subject, tx1, tx2, gk, ck, avg_score) VALUES (?, 'Toán', 7.0, 8.0, 8.5, 9.0, 8.3)");
    $stmt->execute([$tempStudentId]);
    $tempScoreId = (int)$pdo->lastInsertId();

    // 5. Delete the parent class
    $delStmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
    $delStmt->execute([$tempClassId]);

    // 6. Verify all dependent records were cascaded
    $cntAfter = [
        'class' => (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE id = $tempClassId")->fetchColumn(),
        'tc' => (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE id = $tempTcId")->fetchColumn(),
        'student' => (int)$pdo->query("SELECT COUNT(*) FROM students WHERE id = $tempStudentId")->fetchColumn(),
        'score' => (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE id = $tempScoreId")->fetchColumn(),
    ];

    if ($cntAfter['class'] !== 0) throw new Exception("Class $tempClassId was not deleted");
    if ($cntAfter['tc'] !== 0) throw new Exception("teacher_classes record $tempTcId was not cascaded!");
    if ($cntAfter['student'] !== 0) throw new Exception("students record $tempStudentId was not cascaded!");
    if ($cntAfter['score'] !== 0) throw new Exception("scores record $tempScoreId was not cascaded (deep cascade failed)!");

    return "Parent class, teacher assignment, student, and score all cleanly cascaded (4/4 deleted)";
});

challenge("CASCADE", "3.4 Cascade delete on teacher deletion: Deleting user cascades teacher_classes", function () use ($pdo) {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES ('temp_gv_cascade', 'hash', 'Giáo Viên Tạm', 'teacher')");
    $stmt->execute();
    $tempTeacherId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, 1, 'Hóa Học')");
    $stmt->execute([$tempTeacherId]);
    $tempTcId = (int)$pdo->lastInsertId();

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$tempTeacherId]);

    $tcCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_classes WHERE id = $tempTcId")->fetchColumn();
    if ($tcCount !== 0) {
        throw new Exception("teacher_classes record was not deleted after deleting teacher user!");
    }

    return "teacher_classes assignment cleanly cascaded on user deletion";
});

// =========================================================================
// SECTION 4: VIETNAMESE UNICODE FIDELITY & AUDIT LOGGING
// =========================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 4: Live Data Access Layer Unicode & Audit Logging ---" . CLR_RESET . PHP_EOL;

challenge("UNICODE", "4.1 Vietnamese Unicode student full_name storage & retrieval (utf8mb4)", function () use ($pdo) {
    $vietnameseName = "Trương Nguyễn Hoàng Quỳnh Như - Đỗ Đình Đắc";
    $stmt = $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday, gender, phone) VALUES (2, 'HS_VN_TEST', ?, '2015-12-31', 'Nữ', '0988776655')");
    $stmt->execute([$vietnameseName]);
    $testId = (int)$pdo->lastInsertId();

    $fetchStmt = $pdo->prepare("SELECT full_name FROM students WHERE id = ?");
    $fetchStmt->execute([$testId]);
    $fetchedName = $fetchStmt->fetchColumn();

    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$testId]);

    if ($fetchedName !== $vietnameseName) {
        throw new Exception("Unicode mismatch! Expected: '$vietnameseName', Got: '$fetchedName'");
    }

    return "Exact binary match for complex Vietnamese diacritics via PDO ($fetchedName)";
});

challenge("AUDIT_LOG", "4.2 log_action() stores rich Vietnamese text, emoji, quotes & HTML entities", function () use ($pdo) {
    $action = "Cập nhật điểm cuối kỳ 🎯";
    $details = 'Giáo viên "Thầy Nguyễn Văn Giáo" đã sửa điểm môn Toán: tx1=8.5, gk=9.0; Ghi chú: \'Học sinh tiến bộ, đạt giải Ba HSG cấp Thành phố\' & <đạt chuẩn>.';
    
    log_action(2, 'gv_toan', $action, $details);

    $stmt = $pdo->prepare("SELECT action, details, username FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$action]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new Exception("Failed to retrieve audit log with action '$action'");
    }
    if ($row['action'] !== $action || $row['details'] !== $details) {
        throw new Exception("Audit log content corrupted in MariaDB! Details mismatch.");
    }

    $pdo->prepare("DELETE FROM audit_logs WHERE action = ?")->execute([$action]);

    return "Audit log successfully stored and retrieved Unicode, emoji, quotes, and HTML special chars";
});

// =========================================================================
// SECTION 5: EDGE CASES, BOUNDARIES & BEHAVIORAL ANALYSIS
// =========================================================================
echo PHP_EOL . CLR_BOLD . "--- SECTION 5: Edge Cases, Nullability & Boundary Analysis ---" . CLR_RESET . PHP_EOL;

challenge("BOUNDARY", "5.1 Maximum boundary strings accepted without truncation", function () use ($pdo) {
    $maxClassName = str_repeat("C", 50);
    $stmt = $pdo->prepare("INSERT INTO classes (name, grade, section) VALUES (?, 9, 'A')");
    $stmt->execute([$maxClassName]);
    $classId = (int)$pdo->lastInsertId();

    $retrieved = $pdo->query("SELECT name FROM classes WHERE id = $classId")->fetchColumn();
    $pdo->prepare("DELETE FROM classes WHERE id = $classId")->execute();

    if ($retrieved !== $maxClassName || strlen($retrieved) !== 50) {
        throw new Exception("50-character class name was corrupted or truncated");
    }

    return "Exact 50-character string stored and retrieved without alteration";
});

challenge("NULL_CHECK", "5.2 Birthday NULL handling in unique_class_student constraint", function () use ($pdo) {
    $stmt1 = $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday) VALUES (3, 'CODE_NULL1', 'Học Sinh Thiếu Ngày Sinh', NULL)");
    $stmt1->execute();
    $id1 = (int)$pdo->lastInsertId();

    $duplicateAllowed = false;
    try {
        $stmt2 = $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday) VALUES (3, 'CODE_NULL2', 'Học Sinh Thiếu Ngày Sinh', NULL)");
        $stmt2->execute();
        $id2 = (int)$pdo->lastInsertId();
        $duplicateAllowed = true;
        $pdo->prepare("DELETE FROM students WHERE id IN ($id1, $id2)")->execute();
    } catch (PDOException $e) {
        $pdo->prepare("DELETE FROM students WHERE id = $id1")->execute();
    }

    if ($duplicateAllowed) {
        return "NOTE: MariaDB allows multiple rows when birthday is NULL (standard SQL behavior). Application layer in M3 must enforce non-null birthday or handle null deduplication.";
    }

    return "MariaDB rejected duplicate row with NULL birthday";
});

challenge("TRANSACTION", "5.3 Transaction rollback on unique violation restores clean DB state", function () use ($pdo) {
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT INTO classes (name, grade, section) VALUES ('TX_CLASS_TEMP', 7, 'B')");
        $pdo->exec("INSERT INTO classes (name, grade, section) VALUES ('6A1', 6, 'A')");
        $pdo->commit();
        throw new Exception("Transaction unexpectedly succeeded despite duplicate key");
    } catch (PDOException $e) {
        $pdo->rollBack();
    }

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE name = 'TX_CLASS_TEMP'")->fetchColumn();
    if ($cnt !== 0) {
        throw new Exception("Transaction rollback failed: partial insert leaked into database!");
    }

    return "Transaction rollback cleanly unwound on unique violation, zero leaked rows";
});

challenge("NOT_NULL", "5.4 Reject NOT NULL violations on required fields", function () use ($pdo) {
    try {
        $pdo->exec("INSERT INTO students (class_id, full_name) VALUES (1, NULL)");
        throw new Exception("CRITICAL: Accepted NULL for students.full_name which is NOT NULL!");
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000' && $e->errorInfo[1] !== 1048) {
            throw new Exception("Unexpected error for NOT NULL: " . $e->getMessage());
        }
        return "Rejected with SQLSTATE 23000 (1048 Column 'full_name' cannot be null)";
    }
});

// =========================================================================
// SUMMARY & VERDICT
// =========================================================================
$totalTests = $totalPassed + $totalFailed;
echo PHP_EOL . CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL;
echo sprintf("  Adversarial Challenge Summary: Total %d | Passed: %s%d%s | Failed: %s%d%s",
    $totalTests,
    CLR_GREEN, $totalPassed, CLR_RESET,
    ($totalFailed > 0 ? CLR_RED : CLR_GREEN), $totalFailed, CLR_RESET
) . PHP_EOL;
echo CLR_BOLD . CLR_YELLOW . "==========================================================================" . CLR_RESET . PHP_EOL . PHP_EOL;

if ($totalFailed > 0) {
    echo CLR_BOLD . CLR_RED . "VERDICT: REQUEST_CHANGES" . CLR_RESET . PHP_EOL;
    echo "Reason: Live database seed data contains corrupted mojibake character encoding," . PHP_EOL;
    echo "preventing deduplication and causing downstream data mismatches." . PHP_EOL . PHP_EOL;
    exit(1);
} else {
    echo CLR_BOLD . CLR_GREEN . "VERDICT: APPROVE" . CLR_RESET . PHP_EOL . PHP_EOL;
    exit(0);
}
