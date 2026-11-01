<?php
/**
 * =============================================================================
 * EduRadar THCS — Đồng Bộ Hồ Sơ Học Sinh Thực Tế (DanhSachHocSinh.xlsx)
 * & Mô Phỏng Toàn Diện Mọi Trường Hợp Điểm Số Thông Tư 22/2021/TT-BGDĐT + OLS
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/xlsx_helper.php';
require_once __DIR__ . '/../includes/radar_helper.php';

$excelRosterPath = 'C:\Users\Admin\Downloads\DanhSachHocSinh.xlsx';
if (!file_exists($excelRosterPath)) {
    fwrite(STDERR, "ERROR: File not found: {$excelRosterPath}\n");
    exit(1);
}

$pdo = db();
$pdo->beginTransaction();

try {
    // 1. Parse all 19 sheets from DanhSachHocSinh.xlsx
    $sheetsData = parse_all_sheets_xlsx($excelRosterPath);
    if (empty($sheetsData)) {
        throw new RuntimeException("Không đọc được sheet nào từ {$excelRosterPath}");
    }

    // Map class name -> class_id (create if missing)
    $stmtFindClass = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
    $stmtInsClass  = $pdo->prepare("INSERT INTO classes (name, grade, section) VALUES (?, ?, ?)");

    $activeClassIds = [];
    foreach ($sheetsData as $sheetName => $rows) {
        $cleanName = trim((string)$sheetName);
        if ($cleanName === '') continue;

        $stmtFindClass->execute([$cleanName]);
        $cid = $stmtFindClass->fetchColumn();
        if (!$cid) {
            preg_match('/^(\d+)([A-Za-z]+)(\d*)$/', $cleanName, $m);
            $grade = (int)($m[1] ?? 7);
            $section = strtoupper(($m[2] ?? 'A') . ($m[3] ?? ''));
            $stmtInsClass->execute([$cleanName, $grade, $section]);
            $cid = (int)$pdo->lastInsertId();
        } else {
            $cid = (int)$cid;
        }
        $activeClassIds[$cleanName] = $cid;
    }

    // 2. Clean old scores and old students (rebuild cleanly from Excel + 1 baseline student in Class 10 for E2E test T1.04/T4.10)
    $pdo->exec("DELETE FROM scores");
    $pdo->exec("DELETE FROM students");

    // Insert baseline test student in Class 10 (7A1) for E2E test compatibility
    $c7a1 = (int)$pdo->query("SELECT id FROM classes WHERE name = '7A1'")->fetchColumn();
    if ($c7a1 > 0) {
        $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday, gender, phone) VALUES (?, 'HS7A199', 'Học Sinh Khối 7', '2014-05-15', 'Nam', '0901234567')")
            ->execute([$c7a1]);
    }

    // 3. Insert all 798 real students from DanhSachHocSinh.xlsx
    $insStudent = $pdo->prepare("
        INSERT INTO students (class_id, student_code, full_name, birthday, gender, phone)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $totalStudentsInserted = 0;
    $classStudentsMap = []; // [class_id => [student_row, ...]]

    //Deterministic Vietnamese parent phone generator based on student code
    $genPhone = function(string $code, int $idx): string {
        $prefixes = ['090', '091', '093', '094', '096', '097', '098', '038', '039', '070', '079', '083'];
        $crc = abs(crc32($code . '_' . $idx));
        $p = $prefixes[$crc % count($prefixes)];
        $rest = str_pad((string)($crc % 10000000), 7, '0', STR_PAD_LEFT);
        return $p . $rest;
    };

    foreach ($sheetsData as $sheetName => $rows) {
        $cleanClassName = trim((string)$sheetName);
        $cid = $activeClassIds[$cleanClassName] ?? 0;
        if ($cid <= 0 || empty($rows)) continue;

        $classStudentsMap[$cid] = [];
        $seenNameBday = [];

        foreach ($rows as $rIdx => $row) {
            if ($rIdx === 0) continue; // Header: STT, Mã HS, Họ và Tên, Giới Tính, Ngày Sinh, Lớp, Email, Ghi Chú
            $code   = trim((string)($row[1] ?? ''));
            $name   = trim((string)($row[2] ?? ''));
            $gender = trim((string)($row[3] ?? ''));
            $rawDob = trim((string)($row[4] ?? ''));

            if ($name === '' || mb_stripos($name, 'tổng số') !== false) {
                continue;
            }

            // Normalize birthday; if Invalid Date, generate a realistic birth date for the grade
            $bday = normalize_excel_date($rawDob);
            if ($bday === null) {
                $gradeNum = (int)substr($cleanClassName, 0, 1);
                $birthYear = 2026 - ($gradeNum + 5); // Grade 7 -> 2014, Grade 8 -> 2013, Grade 9 -> 2012
                $h = abs(crc32($code . '_' . $name . '_' . $rIdx));
                $m = ($h % 12) + 1;
                $d = (($h >> 4) % 28) + 1;
                $bday = sprintf('%04d-%02d-%02d', $birthYear, $m, $d);
            }

            // Ensure (class_id, full_name, birthday) uniqueness
            $ukey = mb_strtolower($name, 'UTF-8') . '|' . $bday;
            if (isset($seenNameBday[$ukey])) {
                $dt = new DateTime($bday);
                $dt->modify('+' . ($seenNameBday[$ukey]) . ' days');
                $bday = $dt->format('Y-m-d');
                $seenNameBday[$ukey]++;
            } else {
                $seenNameBday[$ukey] = 1;
            }

            if (!in_array($gender, ['Nam', 'Nữ'], true)) {
                $gender = 'Nam';
            }
            $phone = $genPhone($code ?: (string)$rIdx, $rIdx);

            $insStudent->execute([$cid, $code, $name, $bday, $gender, $phone]);
            $sid = (int)$pdo->lastInsertId();
            $totalStudentsInserted++;

            $classStudentsMap[$cid][] = [
                'id'           => $sid,
                'student_code' => $code,
                'full_name'    => $name,
                'class_id'     => $cid,
                'class_name'   => $cleanClassName,
                'seq'          => count($classStudentsMap[$cid]) // 0-indexed position in class
            ];
        }
    }

    // 4. Ensure teacher accounts and teacher_classes assignments cover all 19 real classes & 6 subjects
    // While strictly preserving E2E test invariants (gv_toan is NOT assigned 6A1 Ngữ Văn and NOT assigned 6A2 Toán)
    $teacherMap = [];
    $uRows = $pdo->query("SELECT id, username FROM users WHERE role = 'teacher'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($uRows as $u) {
        $teacherMap[$u['username']] = (int)$u['id'];
    }

    $subjectsConfig = [
        'Toán'             => $teacherMap['gv_toan'] ?? 2,
        'Ngữ Văn'          => $teacherMap['gv_van']  ?? 3,
        'Tiếng Anh'        => $teacherMap['gv_anh']  ?? 169,
        'KHTN'             => $teacherMap['gv_khtn'] ?? 170,
        'Lịch Sử & Địa Lí' => $teacherMap['gv_su']   ?? 171,
        'Tin Học'          => $teacherMap['gv_tin']  ?? 172,
        'GDCD'             => $teacherMap['gv_gdcd'] ?? 173,
    ];

    // Clear existing teacher_classes and re-seed cleanly
    $pdo->exec("DELETE FROM teacher_classes");
    $insTc = $pdo->prepare("INSERT IGNORE INTO teacher_classes (teacher_id, class_id, subject) VALUES (?, ?, ?)");

    // Baseline assignments required by E2E tests:
    // gv_toan (ID 2): Class 1 (6A1) Toán, Class 10 (7A1) Toán, plus all 19 real classes (7A6..9A9) Toán
    $c6a1 = (int)$pdo->query("SELECT id FROM classes WHERE name = '6A1'")->fetchColumn();
    if ($c6a1 > 0) {
        $insTc->execute([$subjectsConfig['Toán'], $c6a1, 'Toán']);
    }
    if ($c7a1 > 0) {
        $insTc->execute([$subjectsConfig['Toán'], $c7a1, 'Toán']);
    }

    // Assign each subject teacher to all 19 active classes from DanhSachHocSinh.xlsx
    foreach ($activeClassIds as $cName => $cid) {
        foreach ($subjectsConfig as $subj => $tid) {
            if ($tid > 0) {
                $insTc->execute([$tid, $cid, $subj]);
            }
        }
    }

    // 5. Simulate Comprehensive Scores for EVERY Case (TT22 + OLS + Target Solver)
    // Helper to calculate exact TT22 avg_score
    $calcAvg = function(?float $tx1, ?float $tx2, ?float $tx3, ?float $tx4, ?float $gk, ?float $ck): ?float {
        $tx = array_values(array_filter([$tx1, $tx2, $tx3, $tx4], fn($v) => $v !== null));
        $nTx = count($tx);
        $sTx = array_sum($tx);
        if ($gk !== null && $ck !== null) {
            return round(($sTx + 2.0 * $gk + 3.0 * $ck) / ($nTx + 5), 1);
        }
        $w = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
        if ($w === 0) return null;
        return round(($sTx + 2.0 * ($gk ?? 0.0) + 3.0 * ($ck ?? 0.0)) / $w, 1);
    };

    // Helper to clamp score between 0.0 and 10.0 rounded to 0.1
    $clamp = function(float $val): float {
        return round(max(0.5, min(10.0, $val)), 1);
    };

    /**
     * Generate a score vector [tx1, tx2, tx3, tx4, gk, ck, avg_score, teacher_note]
     * given a scenario archetype (0..13) and a deterministic jitter seed.
     */
    $buildScoreProfile = function(int $archetype, int $seed, string $subject, string $studentName) use ($calcAvg, $clamp): array {
        // Small deterministic offset in [-0.2, 0.0, +0.2]
        $offsets = [-0.2, 0.0, 0.2, -0.1, 0.1];
        $d = $offsets[$seed % count($offsets)];

        $tx1 = $tx2 = $tx3 = $tx4 = $gk = $ck = null;
        $note = null;

        switch ($archetype) {
            case 0:
                // [CASE 1] Mức TỐT + Xu hướng TIẾN BỘ VƯỢT BẬC (surge, ĐTB >= 8.5, đầy đủ CK)
                $tx1 = $clamp(7.5 + $d);
                $tx2 = $clamp(8.3 + $d);
                $tx3 = $clamp(9.0 + $d);
                $tx4 = $clamp(9.5 + $d);
                $gk  = $clamp(9.0 + $d);
                $ck  = $clamp(9.5 + $d);
                $note = "Em có sức bật rất ấn tượng ở môn {$subject}, nắm vững kiến thức nâng cao và tích cực phát biểu.";
                break;

            case 1:
                // [CASE 2] Mức TỐT + Xu hướng DUY TRÌ ỔN ĐỊNH (steady, ĐTB >= 8.2, đầy đủ CK)
                $tx1 = $clamp(8.5 + $d);
                $tx2 = $clamp(8.5 - $d);
                $tx3 = $clamp(8.8 + $d);
                $tx4 = $clamp(8.5 + $d);
                $gk  = $clamp(8.5 + $d);
                $ck  = $clamp(8.8 - $d);
                break;

            case 2:
                // [CASE 3] Mức TỐT + ĐANG CHỜ THI CUỐI KỲ (ck = null, đã có TX1..4 + GK cao, dự báo CK mục tiêu Tốt rất khả thi)
                $tx1 = $clamp(8.0 + $d);
                $tx2 = $clamp(8.5 + $d);
                $tx3 = $clamp(8.8 + $d);
                $tx4 = $clamp(9.0 + $d);
                $gk  = $clamp(8.5 + $d);
                $ck  = null;
                break;

            case 3:
                // [CASE 4] Mức KHÁ + Xu hướng TIẾN BỘ RÕ RỆT (up, 6.5 <= ĐTB < 8.0, đầy đủ CK)
                $tx1 = $clamp(6.0 + $d);
                $tx2 = $clamp(6.8 + $d);
                $tx3 = $clamp(7.3 + $d);
                $tx4 = $clamp(7.8 + $d);
                $gk  = $clamp(7.3 + $d);
                $ck  = $clamp(7.8 + $d);
                $note = "Em có nhiều cố gắng cải thiện điểm số qua từng bài kiểm tra thường xuyên, cần phát huy để đạt mức Tốt.";
                break;

            case 4:
                // [CASE 5] Mức KHÁ + Xu hướng DUY TRÌ ỔN ĐỊNH (steady, 6.8 <= ĐTB <= 7.6, đầy đủ CK)
                $tx1 = $clamp(7.0 + $d);
                $tx2 = $clamp(7.3 - $d);
                $tx3 = $clamp(7.0 + $d);
                $tx4 = $clamp(7.5 + $d);
                $gk  = $clamp(7.3 + $d);
                $ck  = $clamp(7.3 - $d);
                break;

            case 5:
                // [CASE 6] Mức KHÁ + CHỜ THI CUỐI KỲ (ck = null, giải ngược mục tiêu: cần CK ~ 6.0 để Khá, ~ 9.2 để Tốt)
                $tx1 = $clamp(6.8 + $d);
                $tx2 = $clamp(7.0 + $d);
                $tx3 = $clamp(7.2 + $d);
                $tx4 = $clamp(7.0 - $d);
                $gk  = $clamp(7.0 + $d);
                $ck  = null;
                break;

            case 6:
                // [CASE 7] Mức KHÁ/TỐT NHƯNG SA SÚT PHONG ĐỘ (Cần chú ý - Yellow: TX đầu cao 9.0 -> 8.0 -> 6.8 -> 5.8, GK 6.5)
                $tx1 = $clamp(9.0 + $d);
                $tx2 = $clamp(8.0 + $d);
                $tx3 = $clamp(6.8 + $d);
                $tx4 = $clamp(5.5 + $d);
                $gk  = $clamp(6.5 + $d);
                $ck  = null;
                $note = "Điểm tích lũy đầu kỳ của em rất tốt nhưng các bài gần đây đang đi xuống rõ rệt. Thầy/cô đã nhắc nhở em tập trung hơn trên lớp.";
                break;

            case 7:
                // [CASE 8] Mức ĐẠT (5.0 <= ĐTB < 6.5) + Xu hướng DUY TRÌ ỔN ĐỊNH (Green/Average)
                $tx1 = $clamp(5.5 + $d);
                $tx2 = $clamp(5.8 - $d);
                $tx3 = $clamp(5.5 + $d);
                $tx4 = $clamp(6.0 + $d);
                $gk  = $clamp(5.8 + $d);
                $ck  = $clamp(5.8 - $d);
                break;

            case 8:
                // [CASE 9] Mức ĐẠT + Xu hướng TIẾN BỘ VƯỢT KHÓ (TX tăng từ 4.5 -> 5.5 -> 6.0 -> 6.5, GK 6.0, CK 6.5)
                $tx1 = $clamp(5.0 + $d);
                $tx2 = $clamp(5.5 + $d);
                $tx3 = $clamp(6.0 + $d);
                $tx4 = $clamp(6.5 + $d);
                $gk  = $clamp(6.0 + $d);
                $ck  = $clamp(6.3 + $d);
                $note = "Em nỗ lực vươn lên rất đáng khen, giữ vững đà ôn tập để bước vào nhóm Khá.";
                break;

            case 9:
                // [CASE 10] CẦN CHÚ Ý (Yellow) - Mức ĐẠT nhưng TUỘT DỐC & CHỜ CUỐI KỲ (TX 7.5 -> 6.5 -> 5.5 -> 4.5, GK 4.8)
                // Giải ngược điểm mục tiêu: Cần CK >= 5.0 để giữ chuẩn Đạt
                $tx1 = $clamp(7.5 + $d);
                $tx2 = $clamp(6.5 + $d);
                $tx3 = $clamp(5.2 + $d);
                $tx4 = $clamp(4.5 + $d);
                $gk  = $clamp(4.8 + $d);
                $ck  = null;
                $note = "Cảnh báo sớm OLS: Phong độ giảm liên tục qua 4 bài thường xuyên và Giữa kỳ dưới 5.0. Cần kèm cặp ôn tập trước kỳ thi Cuối kỳ.";
                break;

            case 10:
                // [CASE 11] CẦN CHÚ Ý (Yellow) - Mức CHƯA ĐẠT (3.5 <= ĐTB < 5.0) Đi đều mức thấp, đã có CK
                $tx1 = $clamp(4.3 + $d);
                $tx2 = $clamp(4.5 - $d);
                $tx3 = $clamp(4.2 + $d);
                $tx4 = $clamp(4.5 + $d);
                $gk  = $clamp(4.3 + $d);
                $ck  = $clamp(4.5 - $d);
                $note = "Nền tảng kiến thức môn {$subject} của em còn mỏng (ĐTB dưới 5.0). Đã phân công bạn học khá kèm cặp 1-1 và giao phiếu bài tập cơ bản.";
                break;

            case 11:
                // [CASE 12] CẦN CHÚ Ý (Yellow) - LỆCH PHA PHONG ĐỘ (TX tốt >= 7.8 nhưng thi GK/CK sụt giảm < 5.0)
                $tx1 = $clamp(8.0 + $d);
                $tx2 = $clamp(7.8 + $d);
                $tx3 = $clamp(8.0 - $d);
                $tx4 = $clamp(7.5 + $d);
                $gk  = $clamp(4.5 + $d);
                $ck  = $clamp(4.8 - $d);
                $note = "Em làm bài thường xuyên tốt nhưng tâm lý phòng thi chưa vững dẫn đến điểm định kỳ thấp. Cần rèn kỹ năng phân bổ thời gian làm đề tổng hợp.";
                break;

            case 12:
                // [CASE 13] NGUY CẤP (Red - Critical Zone) - ĐTB < 3.5 hoặc CK < 3.0 hoặc Báo động tuột dốc sâu
                if ($seed % 2 === 0) {
                    // Đã thi CK nhưng điểm rất thấp (< 3.0) và ĐTB < 3.5
                    $tx1 = $clamp(4.0 + $d);
                    $tx2 = $clamp(3.3 + $d);
                    $tx3 = $clamp(2.8 + $d);
                    $tx4 = $clamp(2.5 + $d);
                    $gk  = $clamp(3.0 + $d);
                    $ck  = $clamp(2.5 + $d);
                } else {
                    // Chưa thi CK nhưng điểm TX + GK cực thấp (cần CK > 10 mới Đạt -> Vượt khả năng, bắt buộc gỡ điểm TX)
                    $tx1 = $clamp(3.5 + $d);
                    $tx2 = $clamp(3.0 + $d);
                    $tx3 = $clamp(2.5 + $d);
                    $tx4 = $clamp(2.5 - $d);
                    $gk  = $clamp(2.8 + $d);
                    $ck  = null;
                }
                $note = "BÁO ĐỘNG ĐỎ: Học sinh mất gốc kiến thức trọng tâm môn {$subject}, cần tổ chức phụ đạo khẩn cấp và cho kiểm tra gỡ điểm thường xuyên.";
                break;

            case 13:
            default:
                // [CASE 14] CHỜ BỔ SUNG ĐIỂM (Pending - Chưa có điểm hoặc mới có 1 cột TX đầu kỳ, chưa thi GK/CK)
                if ($seed % 2 === 0) {
                    // Hoàn toàn chưa có điểm để hiển thị thẻ "Chờ bổ sung điểm" / "Chờ Điểm Số"
                    $tx1 = $tx2 = $tx3 = $tx4 = $gk = $ck = null;
                } else {
                    // Mới chỉ có 1 cột TX1, chưa có GK/CK (Tier: Chờ điểm, Trend: Duy trì khởi đầu)
                    $tx1 = $clamp(7.5 + $d);
                    $tx2 = $tx3 = $tx4 = $gk = $ck = null;
                }
                break;
        }

        $avg = $calcAvg($tx1, $tx2, $tx3, $tx4, $gk, $ck);
        return [$tx1, $tx2, $tx3, $tx4, $gk, $ck, $avg, $note];
    };

    /**
     * Map student index in a class (0..N-1) and subject index (0..6) to an archetype (0..13)
     * so that:
     * 1. Every single class + subject has a realistic, rich distribution across all 4 TT22 tiers,
     *    all 4 OLS trends (Tiến bộ, Duy trì, Sa sút, Chờ điểm), and all 3 Risk Zones (Ổn định, Cần chú ý, Nguy cấp).
     * 2. Multi-subject overall performance (Học lực chung toàn lớp TT22) also has:
     *    - Học sinh Tốt toàn diện (Tất cả các môn >= 6.5, có >= 6 môn >= 8.0)
     *    - Học sinh Khá toàn diện (Tất cả các môn >= 5.0, có môn >= 6.5)
     *    - Học sinh Thiên hướng Tự nhiên (Toán, KHTN, Tin Học rất cao >= 8.5; Văn, Sử, Anh mức Đạt ~ 5.8)
     *    - Học sinh Thiên hướng Xã hội (Văn, Anh, Sử, GDCD rất cao >= 8.5; Toán, KHTN mức Đạt ~ 5.8)
     *    - Học sinh المức Đạt chung (Điểm từ 5.0 - 6.4)
     *    - Học sinh Chưa Đạt / Nguy cấp cần hỗ trợ
     *    - Học sinh đang chờ bổ sung điểm
     */
    $insScore = $pdo->prepare("
        INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, teacher_note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $subjectNames = array_keys($subjectsConfig);
    $scienceSubjs = ['Toán', 'KHTN', 'Tin Học'];
    $socialSubjs  = ['Ngữ Văn', 'Tiếng Anh', 'Lịch Sử & Địa Lí', 'GDCD'];

    $totalScoresInserted = 0;

    foreach ($classStudentsMap as $cid => $studentsInClass) {
        $nStudents = count($studentsInClass);
        foreach ($studentsInClass as $sObj) {
            $sid  = $sObj['id'];
            $seq  = $sObj['seq']; // 0 .. nStudents-1
            $sName = $sObj['full_name'];
            $pct  = $seq / max(1, $nStudents);

            foreach ($subjectNames as $subIdx => $subj) {
                $seed = abs(crc32($sid . '_' . $subj . '_' . $seq));
                $isSci = in_array($subj, $scienceSubjs, true);

                // Assign student group based on position in class ($seq) so every class has all cases!
                if ($seq < 6) {
                    // Nhóm 1 (6 HS đầu): Học sinh Xuất sắc / Tốt toàn diện (Tiến bộ hoặc Duy trì hoặc Chờ CK)
                    $archPool = [0, 1, 2, 0, 1, 2];
                    $arch = $archPool[($seq + $subIdx) % count($archPool)];
                } elseif ($seq < 11) {
                    // Nhóm 2 (5 HS tiếp): Học sinh Thiên hướng Tự nhiên (Giỏi Toán/KHTN/Tin, Khá/Đạt Văn/Sử/Anh)
                    if ($isSci) {
                        $arch = ($seq + $subIdx) % 2 === 0 ? 0 : 1; // Tốt (8.5 - 9.3)
                    } else {
                        $arch = ($seq + $subIdx) % 2 === 0 ? 4 : 7; // Khá hoặc Đạt (5.8 - 7.2)
                    }
                } elseif ($seq < 16) {
                    // Nhóm 3 (5 HS tiếp): Học sinh Thiên hướng Xã hội (Giỏi Văn/Anh/Sử/GDCD, Khá/Đạt Toán/KHTN)
                    if (!$isSci) {
                        $arch = ($seq + $subIdx) % 2 === 0 ? 0 : 1; // Tốt (8.5 - 9.3)
                    } else {
                        $arch = ($seq + $subIdx) % 2 === 0 ? 4 : 7; // Khá hoặc Đạt (5.8 - 7.2)
                    }
                } elseif ($seq < 24) {
                    // Nhóm 4 (8 HS tiếp): Học sinh mức Khá (Đang tiến bộ, Duy trì ổn định, hoặc Chờ thi CK)
                    $archPool = [3, 4, 5, 3, 4, 5];
                    $arch = $archPool[($seq + $subIdx) % count($archPool)];
                } elseif ($seq < 30) {
                    // Nhóm 5 (6 HS tiếp): Học sinh mức Đạt (Duy trì nền tảng hoặc Tiến bộ vượt khó)
                    $archPool = [7, 8, 7, 8, 5];
                    $arch = $archPool[($seq + $subIdx) % count($archPool)];
                } elseif ($seq < 35) {
                    // Nhóm 6 (5 HS tiếp): Nhóm CẦN CHÚ Ý (Vàng) - Sa sút phong độ, lệch pha thi cử, hoặc ĐTB 3.8-4.8
                    $archPool = [6, 9, 10, 11, 9];
                    $arch = $archPool[($seq + $subIdx) % count($archPool)];
                } elseif ($seq < 38) {
                    // Nhóm 7 (3 HS tiếp): Nhóm NGUY CẤP (Đỏ) - ĐTB < 3.5 hoặc CK < 3.0 hoặc cần gỡ điểm TX gấp
                    if ($subIdx % 2 === ($seq % 2)) {
                        $arch = 12; // Nguy cấp ở một nửa số môn
                    } else {
                        $arch = 10; // Chưa đạt / Cần chú ý ở các môn còn lại
                    }
                } else {
                    // Nhóm 8 (Các HS cuối lớp, khoảng 2-4 HS): Chờ bổ sung điểm (Pending) để test thẻ Chờ Điểm Số
                    $arch = 13;
                }

                [$tx1, $tx2, $tx3, $tx4, $gk, $ck, $avg, $note] = $buildScoreProfile($arch, $seed, $subj, $sName);

                // Nếu archetype 13 hoàn toàn trống điểm (cả 6 cột null), ta không insert dòng score (hoặc insert dòng null)
                // Để LEFT JOIN trong index.php và nhap_diem.php nhận diện chính xác học sinh Chờ bổ sung điểm:
                if ($tx1 !== null || $tx2 !== null || $tx3 !== null || $tx4 !== null || $gk !== null || $ck !== null) {
                    $insScore->execute([$sid, $subj, $tx1, $tx2, $tx3, $tx4, $gk, $ck, $avg, $note]);
                    $totalScoresInserted++;
                }
            }
        }
    }

    $pdo->commit();

    echo "=================================================================\n";
    echo "  ĐỒNG BỘ HỌC SINH & MÔ PHỎNG ĐIỂM THÀNH CÔNG!\n";
    echo "=================================================================\n";
    echo "  - Số lớp thực tế từ Excel : " . count($activeClassIds) . " lớp (" . implode(', ', array_keys($activeClassIds)) . ")\n";
    echo "  - Số học sinh thực tế     : {$totalStudentsInserted} học sinh (+1 HS mẫu lớp 7A1 cho E2E Test)\n";
    echo "  - Số bản ghi điểm mô phỏng: {$totalScoresInserted} bảng điểm bộ môn\n";
    echo "=================================================================\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
