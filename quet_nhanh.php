<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Module: Quét Nhanh 1 Chạm Sổ Điểm VnEdu (Chế Độ Khách / Guest Sandbox)
 * Tiêu chuẩn: Thông tư 22/2021/TT-BGDĐT
 * Thiết kế: Ground-Up Redesign — Ngôn ngữ Hallmark v2 Cobalt Technical Telemetry
 * =============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/radar_helper.php';
require_once __DIR__ . '/includes/algorithms.php';
require_once __DIR__ . '/includes/ocr_pipeline.php';
require_once __DIR__ . '/includes/xlsx_helper.php';

// -----------------------------------------------------------------------------
// 1. AJAX ENDPOINT: LƯU GHI CHÚ GIÁO VIÊN TRONG CHẾ ĐỘ KHÁCH (LƯU VÀO SESSION)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_guest_note') {
    header('Content-Type: application/json; charset=utf-8');
    $idx = isset($_POST['student_idx']) ? (int)$_POST['student_idx'] : -1;
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';

    if ($idx >= 0 && isset($_SESSION['guest_scan_data']['students'][$idx])) {
        $_SESSION['guest_scan_data']['students'][$idx]['teacher_note'] = $note;
        echo json_encode([
            'success' => true,
            'message' => 'Đã lưu ghi chú vào phiên làm việc khách!',
            'idx' => $idx,
            'note' => $note,
            'updated_at' => date('d/m/Y H:i')
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Chỉ số học sinh không hợp lệ trong phiên làm việc.']);
    }
    exit;
}

// -----------------------------------------------------------------------------
// 2. XỬ LÝ HÀNH ĐỘNG RESET / NẠP DỮ LIỆU MẪU / MÔ PHỎNG ĐIỂM
// -----------------------------------------------------------------------------
$action = $_GET['action'] ?? '';

if ($action === 'clear') {
    unset($_SESSION['guest_scan_data']);
    header('Location: quet_nhanh.php');
    exit;
}

// HÀNH ĐỘNG 2A: NẠP SỔ ĐIỂM MẪU 10 HỌC SINH ĐA DẠNG XU HƯỚNG TIẾN BỘ
if ($action === 'sample') {
    $sampleStudents = [
        [
            'student_id' => 1,
            'student_code' => 'HS901',
            'full_name' => 'Nguyễn Hoàng Long',
            'birthday' => '2010-03-15',
            'phone' => '0912345678',
            'tx1' => 8.5, 'tx2' => 9.0, 'tx3' => 9.5, 'tx4' => 9.5, 'gk' => 9.0, 'ck' => 9.5,
            'teacher_note' => 'Học sinh có tư duy toán học logic xuất sắc, tiếp thu cực nhanh.'
        ],
        [
            'student_id' => 2,
            'student_code' => 'HS902',
            'full_name' => 'Trần Thị Mai Anh',
            'birthday' => '2010-07-22',
            'phone' => '0987654321',
            'tx1' => 5.0, 'tx2' => 6.0, 'tx3' => 7.0, 'tx4' => 8.0, 'gk' => 7.5, 'ck' => 8.5,
            'teacher_note' => 'Đang có đà tiến bộ rất rõ rệt, chăm chỉ làm bài tập về nhà.'
        ],
        [
            'student_id' => 3,
            'student_code' => 'HS903',
            'full_name' => 'Lê Quốc Bảo',
            'birthday' => '2010-11-05',
            'phone' => '0903112233',
            'tx1' => 5.0, 'tx2' => 5.5, 'tx3' => 5.0, 'tx4' => 5.5, 'gk' => 5.5, 'ck' => 5.0,
            'teacher_note' => 'Học lực mức trung bình đang đi đều, duy trì ổn định nhưng cần bồi dưỡng thêm để vươn lên khá.'
        ],
        [
            'student_id' => 4,
            'student_code' => 'HS904',
            'full_name' => 'Phạm Minh Đức',
            'birthday' => '2010-01-18',
            'phone' => '0944556677',
            'tx1' => 8.0, 'tx2' => 7.0, 'tx3' => 6.0, 'tx4' => 5.0, 'gk' => 5.5, 'ck' => 4.5,
            'teacher_note' => 'Có dấu hiệu sa sút rõ rệt từ giữa kỳ, phụ huynh cho biết học sinh thức khuya chơi game.'
        ],
        [
            'student_id' => 5,
            'student_code' => 'HS905',
            'full_name' => 'Võ Thị Kim Ngân',
            'birthday' => '2010-09-30',
            'phone' => '0933221100',
            'tx1' => 7.5, 'tx2' => 8.0, 'tx3' => 7.5, 'tx4' => 8.0, 'gk' => 8.0, 'ck' => 4.5,
            'teacher_note' => 'Báo động tụt dốc cuối kỳ (tụt 3.5 điểm so với GK). Cần gặp riêng tìm hiểu tâm lý phòng thi.'
        ],
        [
            'student_id' => 6,
            'student_code' => 'HS906',
            'full_name' => 'Đặng Hữu Phúc',
            'birthday' => '2010-05-12',
            'phone' => '0977889900',
            'tx1' => 3.0, 'tx2' => 3.5, 'tx3' => 4.0, 'tx4' => 3.5, 'gk' => 3.5, 'ck' => 3.0,
            'teacher_note' => 'Mất căn bản môn Toán từ lớp 8, bắt buộc phụ đạo lại phần phân thức đại số.'
        ],
        [
            'student_id' => 7,
            'student_code' => 'HS907',
            'full_name' => 'Bùi Gia Hân',
            'birthday' => '2010-08-14',
            'phone' => '0966554433',
            'tx1' => 7.0, 'tx2' => 7.5, 'tx3' => 7.0, 'tx4' => 7.5, 'gk' => 7.5, 'ck' => 7.5,
            'teacher_note' => 'Phong độ học tập rất ổn định, bài vở nộp đầy đủ.'
        ],
        [
            'student_id' => 8,
            'student_code' => 'HS908',
            'full_name' => 'Ngô Thành Đạt',
            'birthday' => '2010-12-03',
            'phone' => '0911223344',
            'tx1' => 6.0, 'tx2' => 6.5, 'tx3' => 7.0, 'tx4' => 7.5, 'gk' => 8.0, 'ck' => null,
            'teacher_note' => 'Tiến bộ từng ngày, đang chờ điểm kiểm tra cuối kỳ.'
        ],
        [
            'student_id' => 9,
            'student_code' => 'HS909',
            'full_name' => 'Hoàng Yến Nhi',
            'birthday' => '2010-04-25',
            'phone' => '0922334455',
            'tx1' => 9.5, 'tx2' => 10.0, 'tx3' => 9.5, 'tx4' => 10.0, 'gk' => 9.5, 'ck' => 10.0,
            'teacher_note' => 'Thành viên đội tuyển học sinh giỏi Toán cấp huyện.'
        ],
        [
            'student_id' => 10,
            'student_code' => 'HS910',
            'full_name' => 'Đỗ Minh Khang',
            'birthday' => '2010-10-10',
            'phone' => '0955667788',
            'tx1' => null, 'tx2' => null, 'tx3' => null, 'tx4' => null, 'gk' => null, 'ck' => null,
            'teacher_note' => 'Học sinh mới chuyển trường, đang chờ cập nhật điểm số.'
        ]
    ];

    $evaluated = [];
    foreach ($sampleStudents as $idx => $s) {
        $txList = [];
        foreach (['tx1', 'tx2', 'tx3', 'tx4'] as $col) {
            if ($s[$col] !== null) {
                $txList[] = (float)$s[$col];
            }
        }
        $gk = $s['gk'] !== null ? (float)$s['gk'] : null;
        $ck = $s['ck'] !== null ? (float)$s['ck'] : null;

        $avg = null;
        if (!empty($txList) && $gk !== null && $ck !== null) {
            $avg = round((array_sum($txList) + 2 * $gk + 3 * $ck) / (count($txList) + 5), 1);
        }

        $diag = evaluateRiskZone($avg, $gk, $ck, $txList);
        $s['idx'] = $idx;
        $s['avg_score'] = $avg;
        $s['class_name'] = '9A1';
        $s['subject'] = 'Toán học';
        $evaluated[] = array_merge($s, $diag, ['tx_list' => $txList]);
    }

    sortStudentsByRisk($evaluated);

    $_SESSION['guest_scan_data'] = [
        'class_name' => '9A1',
        'subject' => 'Toán học',
        'students' => $evaluated,
        'uploaded_at' => date('d/m/Y H:i:s'),
        'filename' => 'Sổ Điểm Mẫu THCS',
        'source' => 'sample'
    ];

    header('Location: quet_nhanh.php');
    exit;
}

// HÀNH ĐỘNG 2B: MÔ PHỎNG ĐIỂM SỐ CHO DANH SÁCH HỌC SINH VỪA TẢI LÊN
if ($action === 'simulate_scores' && isset($_SESSION['guest_scan_data']['students'])) {
    $stList = &$_SESSION['guest_scan_data']['students'];
    $totalSt = count($stList);

    foreach ($stList as $i => &$st) {
        $pct = ($totalSt > 1) ? ($i / ($totalSt - 1)) : 0.5;

        if ($pct < 0.15) {
            $tx1 = round(rand(85, 95) / 10, 1);
            $tx2 = round(rand(90, 100) / 10, 1);
            $tx3 = round(rand(90, 100) / 10, 1);
            $tx4 = round(rand(95, 100) / 10, 1);
            $gk  = round(rand(90, 100) / 10, 1);
            $ck  = round(rand(95, 100) / 10, 1);
            $note = 'Tư duy logic rất sắc bén, kết quả học tập luôn đứng đầu môn học.';
        } elseif ($pct < 0.45) {
            $tx1 = round(rand(55, 65) / 10, 1);
            $tx2 = round(rand(65, 75) / 10, 1);
            $tx3 = round(rand(70, 80) / 10, 1);
            $tx4 = round(rand(75, 85) / 10, 1);
            $gk  = round(rand(70, 80) / 10, 1);
            $ck  = round(rand(80, 90) / 10, 1);
            $note = 'Có đà tiến bộ rất đáng khen, bài kiểm tra sau luôn cao hơn bài trước.';
        } elseif ($pct < 0.70) {
            $tx1 = round(rand(50, 60) / 10, 1);
            $tx2 = round(rand(55, 65) / 10, 1);
            $tx3 = round(rand(50, 60) / 10, 1);
            $tx4 = round(rand(55, 65) / 10, 1);
            $gk  = round(rand(55, 65) / 10, 1);
            $ck  = round(rand(55, 65) / 10, 1);
            $note = 'Học lực mức trung bình đi đều ổn định, cần tiếp sức thêm bài tập nâng cao.';
        } elseif ($pct < 0.85) {
            $tx1 = round(rand(75, 85) / 10, 1);
            $tx2 = round(rand(70, 80) / 10, 1);
            $tx3 = round(rand(60, 70) / 10, 1);
            $tx4 = round(rand(50, 60) / 10, 1);
            $gk  = round(rand(65, 75) / 10, 1);
            $ck  = round(rand(40, 50) / 10, 1);
            $note = 'Điểm số tụt dần từ giữa kỳ đến cuối kỳ, giáo viên cần tìm hiểu lý do tâm lý.';
        } elseif ($pct < 0.95) {
            $tx1 = round(rand(30, 40) / 10, 1);
            $tx2 = round(rand(35, 45) / 10, 1);
            $tx3 = round(rand(30, 40) / 10, 1);
            $tx4 = round(rand(35, 45) / 10, 1);
            $gk  = round(rand(35, 45) / 10, 1);
            $ck  = round(rand(30, 40) / 10, 1);
            $note = 'Hổng kiến thức căn bản, thuộc diện bắt buộc phụ đạo tăng cường ngoài giờ.';
        } else {
            $tx1 = round(rand(60, 80) / 10, 1);
            $tx2 = null;
            $tx3 = null;
            $tx4 = null;
            $gk  = null;
            $ck  = null;
            $note = 'Mới có điểm kiểm tra thường xuyên đợt 1, đang chờ bổ sung điểm.';
        }

        $txList = [];
        foreach ([$tx1, $tx2, $tx3, $tx4] as $v) {
            if ($v !== null) $txList[] = $v;
        }

        $avg = null;
        if (!empty($txList) && $gk !== null && $ck !== null) {
            $avg = round((array_sum($txList) + 2 * $gk + 3 * $ck) / (count($txList) + 5), 1);
        }

        $diag = evaluateRiskZone($avg, $gk, $ck, $txList);

        $st['tx1'] = $tx1;
        $st['tx2'] = $tx2;
        $st['tx3'] = $tx3;
        $st['tx4'] = $tx4;
        $st['gk']  = $gk;
        $st['ck']  = $ck;
        $st['avg_score'] = $avg;
        $st['teacher_note'] = $note;
        $st['tx_list'] = $txList;
        foreach ($diag as $k => $v) {
            $st[$k] = $v;
        }
    }
    unset($st);

    sortStudentsByRisk($_SESSION['guest_scan_data']['students']);
    header('Location: quet_nhanh.php');
    exit;
}

// -----------------------------------------------------------------------------
// 3. XỬ LÝ UPLOAD TỆP EXCEL (.XLSX) HOẶC CSV TỪ VNEDU
// -----------------------------------------------------------------------------
$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['gradebook_file']) || isset($_FILES['file']))) {
    $file = $_FILES['gradebook_file'] ?? $_FILES['file'];
    $classNameInput = trim($_POST['class_name'] ?? '');
    $subjectInput = trim($_POST['subject'] ?? 'Toán học');

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'Lỗi tải tệp lên máy chủ (mã lỗi: ' . $file['error'] . '). Vui lòng thử lại.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $tmpPath = $file['tmp_name'];

        $rows = [];
        if ($ext === 'xlsx') {
            $rows = parse_xlsx($tmpPath);
        } elseif ($ext === 'csv') {
            $rows = parse_csv($tmpPath);
        } else {
            $uploadError = 'Định dạng tệp không được hỗ trợ. Vui lòng tải tệp .xlsx hoặc .csv.';
        }

        if (empty($rows) && empty($uploadError)) {
            $uploadError = 'Tệp rỗng hoặc không thể đọc cấu trúc bảng điểm.';
        }

        if (empty($uploadError)) {
            [$headerIdx, $colMap] = detect_gradebook_headers($rows);

            if ($headerIdx >= 0) {
                foreach ($rows[$headerIdx] as $cIdx => $cVal) {
                    $norm = mb_strtolower(trim((string)$cVal), 'UTF-8');
                    if ($norm === 'lớp' || $norm === 'lop' || $norm === 'class') {
                        $colMap['class_col'] = $cIdx;
                        break;
                    }
                }
            }

            if (empty($classNameInput) && preg_match('/_([0-9]+[A-Za-z0-9]*)/', $file['name'], $m)) {
                $classNameInput = $m[1];
            }

            $parsedStudents = [];
            $dataStart = $headerIdx >= 0 ? ($headerIdx + 1) : 1;

            for ($r = $dataStart; $r < count($rows); $r++) {
                $row = $rows[$r];
                if (!is_array($row) || empty($row)) continue;

                $fullName = '';
                if (isset($colMap['full_name']) && isset($row[$colMap['full_name']])) {
                    $fullName = trim((string)$row[$colMap['full_name']]);
                } elseif (isset($row[1]) && !is_numeric($row[1])) {
                    $fullName = trim((string)$row[1]);
                }

                if ($fullName === '' || mb_strtolower($fullName, 'UTF-8') === 'họ và tên') {
                    continue;
                }

                $studentCode = '';
                if (isset($colMap['student_code']) && isset($row[$colMap['student_code']])) {
                    $studentCode = trim((string)$row[$colMap['student_code']]);
                } else {
                    $studentCode = 'HS' . str_pad((string)(count($parsedStudents) + 1), 3, '0', STR_PAD_LEFT);
                }

                $phone = '';
                if (isset($colMap['phone']) && isset($row[$colMap['phone']])) {
                    $phone = trim((string)$row[$colMap['phone']]);
                }

                $birthday = '';
                if (isset($colMap['birthday']) && isset($row[$colMap['birthday']])) {
                    $birthday = normalize_excel_date($row[$colMap['birthday']]) ?? '';
                }

                $rowClass = $classNameInput;
                if (isset($colMap['class_col']) && isset($row[$colMap['class_col']])) {
                    $cVal = trim((string)$row[$colMap['class_col']]);
                    if (!empty($cVal)) {
                        $rowClass = $cVal;
                        if (empty($classNameInput)) {
                            $classNameInput = $cVal;
                        }
                    }
                }

                $txList = [];
                $txScores = [];
                for ($t = 1; $t <= 4; $t++) {
                    $colKey = 'tx' . $t;
                    $scoreVal = null;
                    if (isset($colMap[$colKey]) && isset($row[$colMap[$colKey]])) {
                        $v = trim((string)$row[$colMap[$colKey]]);
                        if ($v !== '' && is_numeric($v)) {
                            $scoreVal = max(0.0, min(10.0, round((float)$v, 1)));
                            $txList[] = $scoreVal;
                        }
                    }
                    $txScores['tx' . $t] = $scoreVal;
                }

                $gk = null;
                if (isset($colMap['gk']) && isset($row[$colMap['gk']])) {
                    $v = trim((string)$row[$colMap['gk']]);
                    if ($v !== '' && is_numeric($v)) {
                        $gk = max(0.0, min(10.0, round((float)$v, 1)));
                    }
                }

                $ck = null;
                if (isset($colMap['ck']) && isset($row[$colMap['ck']])) {
                    $v = trim((string)$row[$colMap['ck']]);
                    if ($v !== '' && is_numeric($v)) {
                        $ck = max(0.0, min(10.0, round((float)$v, 1)));
                    }
                }

                $avg = null;
                if (!empty($txList) && $gk !== null && $ck !== null) {
                    $avg = round((array_sum($txList) + 2 * $gk + 3 * $ck) / (count($txList) + 5), 1);
                }

                $diag = evaluateRiskZone($avg, $gk, $ck, $txList);
                $stRecord = array_merge([
                    'idx'          => count($parsedStudents),
                    'student_id'   => count($parsedStudents) + 1,
                    'student_code' => $studentCode,
                    'full_name'    => $fullName,
                    'class_name'   => !empty($rowClass) ? $rowClass : '9A1',
                    'subject'      => $subjectInput,
                    'birthday'     => $birthday,
                    'phone'        => $phone,
                    'tx1'          => $txScores['tx1'],
                    'tx2'          => $txScores['tx2'],
                    'tx3'          => $txScores['tx3'],
                    'tx4'          => $txScores['tx4'],
                    'gk'           => $gk,
                    'ck'           => $ck,
                    'avg_score'    => $avg,
                    'teacher_note' => '',
                    'tx_list'      => $txList
                ], $diag);

                $parsedStudents[] = $stRecord;
            }

            if (empty($parsedStudents)) {
                $uploadError = 'Không tìm thấy dòng học sinh nào trong tệp. Hãy kiểm tra định dạng cột tiêu đề VnEdu.';
            } else {
                if (empty($classNameInput)) {
                    $classNameInput = $parsedStudents[0]['class_name'] ?? '9A1';
                }

                sortStudentsByRisk($parsedStudents);

                $_SESSION['guest_scan_data'] = [
                    'class_name'  => $classNameInput,
                    'subject'     => $subjectInput,
                    'students'    => $parsedStudents,
                    'uploaded_at' => date('d/m/Y H:i:s'),
                    'filename'    => $file['name'],
                    'source'      => 'upload'
                ];
                header('Location: quet_nhanh.php');
                exit;
            }
        }
    }
}

// -----------------------------------------------------------------------------
// 4. KIỂM TRA DỮ LIỆU ĐANG CÓ TRONG PHIÊN LÀM VIỆC
// -----------------------------------------------------------------------------
$guestData = $_SESSION['guest_scan_data'] ?? null;
$students = $guestData['students'] ?? [];
$hasData = !empty($students);

foreach ($students as &$st) {
    if (!isset($st['math_profile'])) {
        $txList = $st['tx_list'] ?? [];
        $st['math_profile'] = diagnose_student_mathematical_profile($txList, $st['gk'] ?? null, $st['ck'] ?? null);
    }
}
unset($st);

$rawClassName = $guestData['class_name'] ?? '9A1';
$cleanClassName = preg_replace('/^Lớp\s+/iu', '', trim($rawClassName));

$stats = calculateRadarStats($students);

$hasAnyScoresInDataset = false;
foreach ($students as $st) {
    if (!empty($st['tx_list']) || $st['gk'] !== null || $st['ck'] !== null || $st['avg_score'] !== null) {
        $hasAnyScoresInDataset = true;
        break;
    }
}

$pageTitle = 'Quét Nhanh 1 Chạm VnEdu – Tuyến Khách';
$activeNav = 'scan';

// =============================================================================
// HALLMARK COBALT THEME STYLES & PRECISION KEYCAPS FOR QUÉT NHANH
// =============================================================================
$extraHead = '
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- COBALT TELEMETRY TOKENS --- */
        :root {
            --cobalt-paper: #f8fafc;
            --cobalt-surface: #ffffff;
            --cobalt-ink: #0f172a;
            --cobalt-ink-muted: #475569;
            --cobalt-rule: #e2e8f0;
            --cobalt-rule-strong: #cbd5e1;
            --cobalt-accent: #2563eb;
            --cobalt-accent-edge: #1d4ed8;
            --cobalt-accent-ink: #ffffff;
            --cobalt-key-face: #ffffff;
            --cobalt-key-edge: #cbd5e1;
            --cobalt-key-ink: #0f172a;
            --cobalt-mono: "JetBrains Mono", ui-monospace, monospace;
            --cobalt-display: "Familjen Grotesk", var(--font-sans), sans-serif;
        }
        [data-theme="dark"], body.dark-mode {
            --cobalt-paper: #0b1120;
            --cobalt-surface: #121a2d;
            --cobalt-ink: #f8fafc;
            --cobalt-ink-muted: #94a3b8;
            --cobalt-rule: #1e2c47;
            --cobalt-rule-strong: #334466;
            --cobalt-accent: #3b82f6;
            --cobalt-accent-edge: #1d4ed8;
            --cobalt-accent-ink: #ffffff;
            --cobalt-key-face: #1a253c;
            --cobalt-key-edge: #2e3e5c;
            --cobalt-key-ink: #f8fafc;
        }

        /* --- 3D KEYCAP BUTTONS (FITTS >= 48x48px, WCAG AA >= 4.5:1) --- */
        .key {
            border: 1px solid var(--cobalt-rule-strong);
            border-radius: 8px;
            padding: 0 18px;
            min-height: 48px;
            min-width: 48px;
            background: var(--cobalt-key-face);
            color: var(--cobalt-key-ink);
            font-family: var(--cobalt-display);
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 3px 0 0 var(--cobalt-key-edge), 0 6px 10px -4px rgba(15,23,42,0.12), inset 0 1px 0 rgba(255,255,255,0.8);
            transition: transform 90ms cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 90ms cubic-bezier(0.2, 0.8, 0.2, 1);
            text-decoration: none;
            user-select: none;
            box-sizing: border-box;
        }
        .key:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 0 0 var(--cobalt-key-edge), 0 8px 14px -4px rgba(15,23,42,0.16);
            color: var(--cobalt-key-ink);
        }
        .key:active {
            transform: translateY(3px);
            box-shadow: inset 0 2px 3px rgba(15,23,42,0.18);
        }
        .key--primary {
            background: var(--cobalt-accent);
            border-color: var(--cobalt-accent-edge);
            color: var(--cobalt-accent-ink) !important;
            box-shadow: 0 3px 0 0 var(--cobalt-accent-edge), 0 6px 12px -3px rgba(37,99,235,0.38), inset 0 1px 0 rgba(255,255,255,0.25);
        }
        .key--primary:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 0 0 #1e40af, 0 8px 16px -3px rgba(37,99,235,0.45);
        }
        .key--primary:active {
            transform: translateY(3px);
            box-shadow: inset 0 2px 4px rgba(15,23,42,0.3);
        }
        .key--sm {
            min-height: 40px;
            padding: 0 14px;
            font-size: 13px;
        }

        /* --- CUT-CORNER PANELS --- */
        .cut-card {
            background: var(--cobalt-surface);
            border: 1px solid var(--cobalt-rule);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(15,23,42,0.04);
            transition: border-color 0.2s ease;
        }
        @supports (corner-shape: bevel) {
            .cut-card {
                border-radius: 14px 4px 14px 4px;
                corner-shape: bevel round bevel round;
            }
        }

        /* --- GUEST MODE TELEMETRY BANNER (REPLACE CHEAP GRADIENT) --- */
        .guest-telemetry-banner {
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            border-left: 4px solid var(--cobalt-accent);
        }
        .guest-banner-title {
            font-family: var(--cobalt-display);
            font-size: 15px;
            font-weight: 700;
            color: var(--cobalt-ink);
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.01em;
        }
        .guest-banner-sub {
            font-size: 13px;
            color: var(--cobalt-ink-muted);
            margin-top: 4px;
            line-height: 1.5;
            max-width: 650px;
        }
        .guest-banner-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* --- OPTICAL SENSOR DROPZONE (LUCIAN #032 WITH MARCHING ANIMATION) --- */
        .u032 {
            position: relative;
            width: 100%;
            max-width: 700px;
            margin: 20px auto 14px;
        }
        .u032-zone {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 196px;
            border: none !important;
            outline: none;
            padding: 0;
            border-radius: 18px;
            background: var(--cobalt-surface) !important;
            color: var(--cobalt-ink) !important;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(15,23,42,0.06);
            transition: transform 0.2s ease, background-color 0.2s ease;
        }
        .u032-zone:focus-visible {
            outline: 2px solid var(--cobalt-accent);
            outline-offset: 4px;
        }
        .u032-zone svg.u032-dash {
            position: absolute;
            left: 2px;
            top: 2px;
            width: calc(100% - 4px);
            height: calc(100% - 4px);
            overflow: visible;
            pointer-events: none;
        }
        .u032-dash rect {
            fill: none;
            stroke: var(--cobalt-rule-strong);
            stroke-width: 2;
            stroke-dasharray: 8 6;
            transition: stroke 0.2s ease;
        }
        .u032-zone:hover, .u032.is-over .u032-zone {
            background: var(--cobalt-paper) !important;
        }
        .u032-zone:hover .u032-dash rect, .u032.is-over .u032-dash rect {
            stroke: var(--cobalt-accent);
            animation: u032-march 0.8s linear infinite;
        }
        @keyframes u032-march {
            to { stroke-dashoffset: -28px; }
        }
        .u032.is-over .u032-zone {
            transform: scale(1.015);
        }
        .u032-orb {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: #ffffff;
            background: var(--cobalt-accent);
            box-shadow: 0 8px 20px -4px rgba(37,99,235,0.4);
            transition: transform 0.2s ease;
        }
        .u032-orb svg {
            width: 28px;
            height: 28px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .u032-zone:hover .u032-orb {
            transform: translateY(-2px);
        }
        .u032.is-over .u032-orb {
            transform: translateY(-4px) scale(1.08);
        }
        .u032-zone b {
            margin-top: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: var(--cobalt-display);
            color: var(--cobalt-ink) !important;
        }
        .u032-zone b em {
            font-style: normal;
            color: var(--cobalt-accent);
            text-decoration: underline;
        }
        .u032-zone small {
            margin-top: 4px;
            font-size: 12.5px;
            color: var(--cobalt-ink-muted) !important;
        }
        .u032-file {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }
        .u032-list {
            margin: 12px 0 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .u032-row {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            height: 56px;
            padding: 0 14px;
            border-radius: 10px;
            background: var(--cobalt-surface) !important;
            border: 1px solid var(--cobalt-rule);
            box-shadow: 0 2px 6px rgba(15,23,42,0.03);
            overflow: hidden;
            animation: u032-in 0.25s ease;
        }
        @keyframes u032-in {
            from { transform: translateY(-8px); opacity: 0; }
        }
        .u032-ic {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: grid;
            place-items: center;
            font-size: 10px;
            font-weight: 700;
            font-family: var(--cobalt-mono);
            letter-spacing: 0.04em;
            color: #ffffff;
            background: var(--cobalt-accent);
        }
        .u032-meta {
            flex: 1;
            min-width: 0;
        }
        .u032-meta b {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--cobalt-ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .u032-meta small {
            display: block;
            margin-top: 2px;
            font-size: 11.5px;
            color: var(--cobalt-ink-muted);
            font-family: var(--cobalt-mono);
        }
        .u032-bar {
            position: absolute;
            left: 0;
            bottom: 0;
            height: 3px;
            width: 100%;
            background: var(--cobalt-accent);
            transform-origin: 0 50%;
            transform: scaleX(var(--p, 0));
            transition: transform 0.2s linear;
        }
        .u032-row.done .u032-bar {
            background: #10b981;
            opacity: 0;
        }
        .u032-st {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: grid;
            place-items: center;
        }
        .u032-st svg {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .u032-row.done .u032-st {
            background: #10b981;
            color: #ffffff;
        }
        .u032-spin {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid var(--cobalt-rule);
            border-top-color: var(--cobalt-accent);
            animation: u032-rot 0.7s linear infinite;
        }
        @keyframes u032-rot {
            to { transform: rotate(360deg); }
        }

        /* --- PARAMETERS GRID --- */
        .upload-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            max-width: 600px;
            margin: 18px auto 0;
            text-align: left;
        }
        .upload-input-group label {
            font-size: 11.5px;
            font-weight: 700;
            font-family: var(--cobalt-display);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--cobalt-ink-muted);
            margin-bottom: 6px;
            display: block;
        }
        .upload-input-group input, .upload-input-group select {
            width: 100%;
            height: 44px;
            padding: 0 12px;
            border: 1px solid var(--cobalt-rule-strong);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13.5px;
            background: var(--cobalt-key-face);
            color: var(--cobalt-ink);
            outline: none;
            box-shadow: 0 2px 0 0 var(--cobalt-key-edge);
            box-sizing: border-box;
        }

        /* --- THREE PILLARS SCIENTIFIC FEATURES --- */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            max-width: 960px;
            margin: 36px auto 20px;
        }
        .feature-item {
            padding: 22px;
            background: var(--cobalt-surface);
            border: 1px solid var(--cobalt-rule);
            border-radius: 12px;
        }
        .feature-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--cobalt-paper);
            border: 1px solid var(--cobalt-rule);
            margin-bottom: 12px;
        }
        .feature-title {
            font-family: var(--cobalt-display);
            font-size: 15px;
            font-weight: 700;
            color: var(--cobalt-ink);
            margin-bottom: 6px;
        }
        .feature-desc {
            font-size: 13px;
            color: var(--cobalt-ink-muted);
            line-height: 1.55;
        }

        /* --- FEATURE LEDGER TABLE --- */
        .ledger-table-wrap {
            border: 1px solid var(--cobalt-rule);
            border-radius: 12px;
            overflow-x: auto;
            background: var(--cobalt-surface);
            box-shadow: 0 2px 6px rgba(15,23,42,0.04);
            margin-bottom: 24px;
        }
        .radar-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-family: "IBM Plex Sans", system-ui, sans-serif;
            font-size: 13px;
        }
        .radar-table th {
            background: var(--cobalt-paper);
            color: var(--cobalt-ink);
            font-family: var(--cobalt-display);
            font-weight: 600;
            font-size: 12px;
            letter-spacing: -0.01em;
            text-transform: uppercase;
            padding: 11px 10px;
            border-bottom: 1px solid var(--cobalt-rule-strong);
            border-right: 1px solid var(--cobalt-rule);
            white-space: nowrap;
        }
        .radar-table td {
            padding: 9px 10px;
            border-bottom: 1px solid var(--cobalt-rule);
            border-right: 1px solid var(--cobalt-rule);
            vertical-align: middle;
            background: var(--cobalt-surface);
        }
        .radar-table tr:hover td {
            background: rgba(37,99,235,0.025);
        }

        /* Mono Code & Scores */
        .mono-code {
            font-family: var(--cobalt-mono);
            font-size: 12.5px;
            font-weight: 600;
        }
        .avg-score-badge {
            display: inline-block;
            font-family: var(--cobalt-mono);
            font-weight: 700;
            font-size: 13.5px;
            font-variant-numeric: tabular-nums;
            padding: 3px 8px;
            border-radius: 6px;
            text-align: center;
        }
        .avg-score-badge.avg-green {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .avg-score-badge.avg-yellow {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .avg-score-badge.avg-red {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .avg-score-badge.avg-empty {
            background: var(--cobalt-paper);
            color: var(--cobalt-ink-muted);
            border: 1px solid var(--cobalt-rule);
        }

        /* Badges */
        .badge-momentum {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            font-family: var(--cobalt-display);
        }
        .badge-momentum-up { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-momentum-steady { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
        .badge-momentum-down { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-momentum-pending { background: var(--cobalt-paper); color: var(--cobalt-ink-muted); border: 1px solid var(--cobalt-rule); }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            font-family: var(--cobalt-display);
        }
        .status-badge.badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .status-badge.badge-yellow { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .status-badge.badge-green { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

        .btn-open-detail {
            border: 1px solid var(--cobalt-rule-strong);
            background: var(--cobalt-key-face);
            color: var(--cobalt-ink);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            font-family: var(--cobalt-display);
            cursor: pointer;
            box-shadow: 0 2px 0 0 var(--cobalt-key-edge);
            transition: all 90ms ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-open-detail:hover {
            border-color: var(--cobalt-accent);
            color: var(--cobalt-accent);
            transform: translateY(-1px);
        }

        /* Filter Chips Bar */
        .filter-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 12px 18px;
            margin-bottom: 16px;
        }
        .chips-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .chip {
            border: 1px solid var(--cobalt-rule);
            background: var(--cobalt-key-face);
            color: var(--cobalt-ink-muted);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12.5px;
            font-weight: 600;
            font-family: var(--cobalt-display);
            cursor: pointer;
            box-shadow: 0 2px 0 0 var(--cobalt-key-edge);
            transition: all 90ms ease;
        }
        .chip:hover {
            color: var(--cobalt-ink);
            border-color: var(--cobalt-rule-strong);
        }
        .chip.active {
            background: var(--cobalt-accent);
            border-color: var(--cobalt-accent-edge);
            color: #ffffff;
            box-shadow: 0 2px 0 0 var(--cobalt-accent-edge);
        }
        .chip-red.active { background: #dc2626; border-color: #b91c1c; box-shadow: 0 2px 0 0 #b91c1c; }
        .chip-yellow.active { background: #d97706; border-color: #b45309; box-shadow: 0 2px 0 0 #b45309; }
        .chip-green.active { background: #059669; border-color: #047857; box-shadow: 0 2px 0 0 #047857; }

        .search-box-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .search-box-wrap input {
            height: 40px;
            padding: 0 14px 0 34px;
            border-radius: 6px;
            border: 1px solid var(--cobalt-rule-strong);
            background: var(--cobalt-key-face);
            color: var(--cobalt-ink);
            font-family: "IBM Plex Sans", system-ui, sans-serif;
            font-size: 13px;
            outline: none;
            width: 240px;
            box-shadow: inset 0 1px 2px rgba(15,23,42,0.06);
        }
        .search-box-wrap .search-icon {
            position: absolute;
            left: 10px;
            color: var(--cobalt-ink-muted);
            pointer-events: none;
        }
    </style>
';

$breadcrumbs = [
    ['title' => 'Trang Chủ', 'url' => 'welcome.php'],
    ['title' => 'Quét Nhanh Bảng Điểm 1 Chạm', 'url' => '']
];
require_once __DIR__ . '/includes/header.php';
?>

    <?php if ($hasData): ?>
    <!-- 1. GUEST MODE & SANDBOX BANNER (COBALT TELEMETRY STYLE - ONLY SHOWN WHEN DATA LOADED) -->
    <section class="cut-card guest-telemetry-banner" aria-label="Thông báo chế độ quét nhanh độc lập">
        <div>
            <div class="guest-banner-title">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <span>SANDBOX // QUAN TRẮC ĐỘC LẬP TỆP VNEDU</span>
                <?php else: ?>
                    <span>CHẾ ĐỘ KHÁCH // QUÉT 1 CHẠM VNEDU SANDBOX</span>
                <?php endif; ?>
            </div>
            <div class="guest-banner-sub">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <span data-i18n="scanner.auth_sub">Tài khoản: <strong><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></strong> (<?= ($_SESSION['role'] ?? '') === 'admin' ? 'Quản trị viên' : 'Giáo viên bộ môn' ?>). Dữ liệu tính toán độc lập trong phiên tạm, không ghi đè vào CSDL trường.</span>
                <?php elseif ($hasData): ?>
                    <span data-i18n="scanner.hasdata_sub">Đang hiển thị kết quả phân tích: <strong>Lớp <?= htmlspecialchars($cleanClassName) ?></strong> &bull; Môn <strong><?= htmlspecialchars($guestData['subject'] ?? 'Toán học') ?></strong> (<?= $stats['total'] ?> học sinh). Dữ liệu tính toán tức thì trong phiên làm việc.</span>
                <?php else: ?>
                    <span data-i18n="scanner.guest_sub">Kéo thả tệp bảng điểm Excel (.xlsx) hoặc CSV từ VnEdu/SMAS để hệ thống tự động phân tích 2 khung năng lực và cảnh báo sớm TT22 tức thì.</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="guest-banner-actions">
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="index.php" class="key key--sm">
                    <span data-i18n="scanner.btn_back_radar">Về Sổ Điểm Lớp</span>
                </a>
            <?php endif; ?>
            <?php if ($hasData): ?>
                <a href="in_bao_cao_bgh.php?guest=1&format=pdf" target="_blank" class="key key--primary key--sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    <span data-i18n="scanner.btn_export_pdf">In Báo Cáo BGH (PDF)</span>
                </a>
                <a href="in_bao_cao_bgh.php?guest=1&format=xlsx" target="_blank" class="key key--sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span data-i18n="scanner.btn_export_xlsx">Xuất Báo Cáo BGH (Excel)</span>
                </a>
                <a href="quet_nhanh.php?action=clear" class="key key--sm">
                    <span data-i18n="scanner.btn_clear_session">Tải Tệp Khác</span>
                </a>
            <?php else: ?>
                <a href="quet_nhanh.php?action=sample" class="key key--primary key--sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span data-i18n="scanner.btn_load_sample">Nạp Dữ Liệu Mẫu VnEdu</span>
                </a>
                <?php if (empty($_SESSION['user_id'])): ?>
                    <a href="login.php" class="key key--sm">
                        <span data-i18n="scanner.btn_login">Đăng Nhập</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($uploadError)): ?>
        <div style="background: rgba(239,68,68,0.12); border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <?= get_svg_icon('alert-triangle', 16) ?> <span><?= htmlspecialchars($uploadError) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$hasData): ?>
        <!-- 2. OPTICAL SENSOR DROPZONE (LUCIAN #032 WITH MARCHING ANIMATION) -->
        <div style="text-align: center; margin: 30px 0 20px;">
            <h1 style="font-family: var(--cobalt-display); font-size: 26px; font-weight: 700; color: var(--cobalt-ink); margin-bottom: 8px;" data-i18n="scanner.hero_title">
                Quét 1 Chạm Sổ Điểm Điện Tử VnEdu
            </h1>
            <p style="color: var(--cobalt-ink-muted); font-size: 14px; max-width: 600px; margin: 0 auto;" data-i18n="scanner.hero_sub">
                Chẩn đoán sớm rủi ro học sinh sa sút, phân tích 2 khung năng lực và xuất báo cáo nộp BGH chỉ trong vài giây.
            </p>
        </div>

        <form action="quet_nhanh.php" method="POST" enctype="multipart/form-data" id="guestUploadForm">
            <!-- HARD CONTRACT: #u032DropzoneWrap, #u032DropZone, .u032-dash, .u032-orb, #fileInput, #u032FileInput -->
            <div class="ux u032" id="u032DropzoneWrap">
                <button class="u032-zone" type="button" id="u032ZoneBtn">
                    <svg class="u032-dash" aria-hidden="true"><rect width="100%" height="100%" rx="18"/></svg>
                    <span class="u032-orb" aria-hidden="true">
                        <svg viewBox="0 0 28 28"><path d="M8.5 20.5H7a5 5 0 0 1-.6-10 7 7 0 0 1 13.4-1.6A5.5 5.5 0 0 1 21 20.5h-1.5M14 13v10M10.5 16.5L14 13l3.5 3.5"/></svg>
                    </span>
                    <b>Kéo & thả tệp bảng điểm VnEdu vào đây hoặc <em>chọn tệp</em></b>
                    <small>Hỗ trợ tệp <strong>.xlsx</strong> hoặc <strong>.csv</strong> chuẩn VnEdu / SMAS · tối đa 50 MB</small>
                </button>
                <input class="u032-file" type="file" name="gradebook_file" id="fileInput" accept=".xlsx,.csv" tabindex="-1" aria-hidden="true">
                <ul class="u032-list" id="u032List" aria-live="polite"></ul>
            </div>

            <div class="upload-form-grid">
                <div class="upload-input-group">
                    <label data-i18n="scanner.class_name_input">Tên Lớp Học (Tùy chọn)</label>
                    <input type="text" name="class_name" placeholder="Ví dụ: 9A1, 7A6 (để trống nếu tự nhận diện)">
                </div>
                <div class="upload-input-group">
                    <label data-i18n="scanner.subject_input">Môn Học</label>
                    <select name="subject">
                        <option value="Toán học" selected>Toán học</option>
                        <option value="Ngữ văn">Ngữ văn</option>
                        <option value="Tiếng Anh">Tiếng Anh</option>
                        <option value="Khoa học tự nhiên">Khoa học tự nhiên</option>
                        <option value="Lịch sử và Địa lí">Lịch sử và Địa lí</option>
                        <option value="Giáo dục công dân">Giáo dục công dân</option>
                        <option value="Tin học">Tin học</option>
                        <option value="Công nghệ">Công nghệ</option>
                    </select>
                </div>
            </div>

            <div style="text-align: center; margin-top: 24px;">
                <button type="submit" class="key key--primary">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                    <span data-i18n="scanner.btn_launch_radar">Bắt Đầu Phân Tích Ra-đa Ngay</span>
                </button>
            </div>
        </form>

        <div style="text-align: center; margin: 30px 0 20px;">
            <span style="color: var(--cobalt-ink-muted); font-size: 13px;" data-i18n="scanner.or_sample_prompt">— Hoặc trải nghiệm ngay không cần chuẩn bị tệp —</span><br>
            <a href="quet_nhanh.php?action=sample" class="key" style="margin-top: 12px;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span data-i18n="scanner.btn_load_sample_desc">Nạp Sổ Điểm Mẫu VnEdu THCS (10 Học Sinh Đa Dạng Năng Lực)</span>
            </a>
        </div>

        <!-- 3 SCIENTIFIC WORKFLOW CARDS -->
        <div class="features-grid">
            <div class="cut-card feature-item">
                <div class="feature-icon-badge" style="color: var(--cobalt-accent);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </div>
                <div class="feature-title" data-i18n="scanner.pillar1_title">Trích xuất bảng điểm tự động</div>
                <div class="feature-desc" data-i18n="scanner.pillar1_desc">
                    Tự động nhận diện chính xác các cột Họ và tên, Mã định danh, Ngày sinh, ĐĐGtx (1-4), Điểm GK, CK từ bảng tính Excel VnEdu và SMAS.
                </div>
            </div>
            <div class="cut-card feature-item">
                <div class="feature-icon-badge" style="color: #059669;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="feature-title" data-i18n="scanner.pillar2_title">Chẩn đoán xu hướng học lực</div>
                <div class="feature-desc" data-i18n="scanner.pillar2_desc">
                    Đối sánh kép: Xu hướng tiến bộ học tập (tiến bộ, duy trì, sa sút) kết hợp Khung Chuẩn Điểm Số Thông tư 22/2021/TT-BGDĐT.
                </div>
            </div>
            <div class="cut-card feature-item">
                <div class="feature-icon-badge" style="color: #d97706;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div class="feature-title" data-i18n="scanner.pillar3_title">Xuất phiếu & Báo cáo tổng hợp</div>
                <div class="feature-desc" data-i18n="scanner.pillar3_desc">
                    Xuất báo cáo tổng hợp PDF chuẩn A4 hoặc trích xuất phiếu kết quả học tập gửi phụ huynh nhanh chóng.
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- 3. GIAO DIỆN KHI ĐÃ CÓ DỮ LIỆU: RADAR FEATURE LEDGER -->

        <?php if (!$hasAnyScoresInDataset): ?>
            <!-- BANNER HƯỚNG DẪN KHI FILE TẢI LÊN CHƯA CÓ ĐIỂM -->
            <div class="no-scores-alert-banner"><div class="cut-card" style="padding: 16px 20px; margin-bottom: 24px; border-left: 4px solid #eab308; display: flex; align-items: flex-start; gap: 14px;">
                <span style="color: #d97706;"><?= get_svg_icon('bulb', 24) ?></span>
                <div style="flex: 1;">
                    <b style="font-size: 15px; color: var(--cobalt-ink);">
                        Tệp bạn vừa tải lên là <u>Danh Sách Học Sinh</u> (chưa có cột điểm số từ VnEdu)
                    </b>
                    <p style="font-size: 13px; color: var(--cobalt-ink-muted); margin-top: 4px; line-height: 1.5;">
                        Hệ thống đã nhận diện thành công <strong><?= $stats['total'] ?> học sinh</strong> lớp <strong><?= htmlspecialchars($cleanClassName) ?></strong>. Hiện tại các em đều ở trạng thái <strong>Chờ điểm</strong>. Bạn có thể chọn phương án trải nghiệm nhanh:
                    </p>
                    <div style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="quet_nhanh.php?action=simulate_scores" class="key key--primary key--sm">
                            <span data-i18n="scanner.btn_sim_scores">Mô Phỏng Điểm Số Cho <?= $stats['total'] ?> Học Sinh Này</span>
                        </a>
                        <a href="quet_nhanh.php?action=sample" class="key key--sm">
                            <span data-i18n="scanner.btn_sample_short">Nạp Sổ Điểm Mẫu THCS</span>
                        </a>
                        <a href="quet_nhanh.php?action=clear" class="key key--sm">
                            <span data-i18n="scanner.btn_clear_short">Tải Lên Tệp Sổ Điểm Khác</span>
                        </a>
                    </div>
                </div>
            </div></div>
        <?php endif; ?>

        <!-- TELEMETRY STATS BENTO -->
        <div style="margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <span style="font-family: var(--cobalt-display); font-size: 14px; font-weight: 700; color: var(--cobalt-ink); display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('trend-up', 16, '', 'color: var(--cobalt-accent);') ?>
                    <span data-i18n="scanner.momentum_group_title">Xu hướng tiến bộ học tập</span>
                </span>
                <span style="font-size: 12px; color: var(--cobalt-ink-muted);">Tiến bộ · Duy trì · Sa sút · Chờ điểm</span>
            </div>

            <section class="radar-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 16px;">
                <div class="card stat-card trend-up">
                    <div class="card-header-row">
                        <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                            <?= get_svg_icon('trend-up', 16) ?>
                            <span>Đang tiến bộ</span>
                        </span>
                        <?= render_circular_gauge((float)$stats['up_pct'], 'green') ?>
                        <span class="card-pct trend-up"><?= $stats['up_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['momentum_up'] ?></b>
                    <span class="card-sub">Bài sau cao hơn bài trước hoặc thi tiến bộ</span>
                </div>

                <div class="card stat-card green">
                    <div class="card-header-row">
                        <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                            <?= get_svg_icon('activity', 16) ?>
                            <span>Duy trì ổn định</span>
                        </span>
                        <?= render_circular_gauge((float)$stats['steady_pct'], 'yellow') ?>
                        <span class="card-pct green"><?= $stats['steady_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['momentum_steady'] ?></b>
                    <span class="card-sub">Điểm số giữ đều đặn, không sụt giảm phong độ</span>
                </div>

                <div class="card stat-card trend-down">
                    <div class="card-header-row">
                        <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                            <?= get_svg_icon('trend-down', 16) ?>
                            <span>Cần chú ý sa sút</span>
                        </span>
                        <?= render_circular_gauge((float)$stats['down_pct'], 'red') ?>
                        <span class="card-pct trend-down"><?= $stats['down_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['momentum_down'] ?></b>
                    <span class="card-sub">Chuỗi điểm TX tụt dốc hoặc thi CK sút giảm</span>
                </div>

                <div class="card stat-card" style="border-left: 4px solid #64748b;">
                    <div class="card-header-row">
                        <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                            <?= get_svg_icon('clock', 16) ?>
                            <span>Chờ bổ sung điểm</span>
                        </span>
                        <?= render_circular_gauge((float)$stats['pending_pct'], 'gray') ?>
                        <span class="card-pct" style="color: #64748b;"><?= $stats['pending_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['momentum_pending'] ?></b>
                    <span class="card-sub">Chưa có bài kiểm tra ghi nhận</span>
                </div>
            </section>
        </div>

        <div style="margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <span style="font-family: var(--cobalt-display); font-size: 14px; font-weight: 700; color: var(--cobalt-ink); display: flex; align-items: center; gap: 6px;">
                    <?= get_svg_icon('bar-chart', 16, '', 'color: #059669;') ?>
                    <span data-i18n="scanner.risk_group_title">Mức học lực & Phân vùng cảnh báo (TT 22)</span>
                </span>
                <span style="font-size: 12px; color: var(--cobalt-ink-muted);">Nguy cấp · Cần chú ý · Ổn định</span>
            </div>

            <section class="radar-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 16px;">
                <div class="card stat-card red">
                    <div class="card-header-row">
                        <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                            <?= get_svg_icon('alert-triangle', 16, '', 'color: #dc2626;') ?>
                            <span>Vùng Nguy Cấp</span>
                        </span>
                        <?= render_circular_gauge((float)$stats['red_pct'], 'red') ?>
                        <span class="card-pct red"><?= $stats['red_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['red'] ?></b>
                    <span class="card-sub">ĐTB &lt; 3.5 hoặc ĐĐGck &lt; 3.0</span>
                </div>

                <div class="card stat-card yellow">
                    <div class="card-header-row">
                        <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                            <?= get_svg_icon('alert-circle', 16, '', 'color: #d97706;') ?>
                            <span>Vùng Cần Chú Ý</span>
                        </span>
                        <?= render_circular_gauge((float)$stats['yellow_pct'], 'yellow') ?>
                        <span class="card-pct yellow"><?= $stats['yellow_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['yellow'] ?></b>
                    <span class="card-sub">ĐTB 3.5 - 4.9 hoặc sa sút cuối kỳ</span>
                </div>

                <div class="card stat-card green">
                    <div class="card-header-row">
                        <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                            <?= get_svg_icon('check-circle', 16, '', 'color: #10b981;') ?>
                            <span>Vùng Ổn Định</span>
                        </span>
                        <?= render_circular_gauge((float)$stats['green_pct'], 'green') ?>
                        <span class="card-pct green"><?= $stats['green_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['green'] ?></b>
                    <span class="card-sub">ĐTB &ge; 5.0 và không có dấu hiệu sa sút</span>
                </div>

                <div class="card stat-card" style="border-left: 4px solid #8b5cf6;">
                    <div class="card-header-row">
                        <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                            <?= get_svg_icon('award', 16, '', 'color: #7c3aed;') ?>
                            <span>Giỏi / Khá (6.5+)</span>
                        </span>
                        <?php $gkPct = $stats['total'] > 0 ? round((($stats['tier_excellent'] + $stats['tier_good']) / $stats['total']) * 100, 1) : 0; ?>
                        <?= render_circular_gauge((float)$gkPct, 'purple') ?>
                        <span class="card-pct" style="background: #f5f3ff; color: #6d28d9;">
                            <?= $gkPct ?>%
                        </span>
                    </div>
                    <b><?= $stats['tier_excellent'] + $stats['tier_good'] ?></b>
                    <span class="card-sub">Nhóm bồi dưỡng phát triển năng khiếu</span>
                </div>
            </section>
        </div>

        <!-- 4. FILTER BAR & AIRPORT INSTANT SEARCH -->
        <div class="cut-card filter-bar">
            <div class="chips-group" id="filterChips">
                <button type="button" class="chip active" data-filter="all">
                    <span>Tất cả (<?= $stats['total'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="trend:up">
                    <span>Tiến bộ rõ rệt (<?= $stats['momentum_up'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="trend:steady">
                    <span>Duy trì ổn định (<?= $stats['momentum_steady'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="trend:down">
                    <span>Có sa sút (<?= $stats['momentum_down'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="trend:pending">
                    <span>Chờ điểm (<?= $stats['momentum_pending'] ?>)</span>
                </button>

                <span style="color: var(--cobalt-rule-strong); font-weight: 300;">|</span>

                <button type="button" class="chip" data-filter="tier:high">
                    <span>Khá & Tốt (<?= $stats['tier_excellent'] + $stats['tier_good'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="tier:average">
                    <span>Trung bình (<?= $stats['tier_average'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="tier:low">
                    <span>Dưới chuẩn (<?= $stats['tier_low'] ?>)</span>
                </button>

                <span style="color: var(--cobalt-rule-strong); font-weight: 300;">|</span>

                <button type="button" class="chip chip-red" data-filter="red">
                    <span>Nguy cấp (<?= $stats['red'] ?>)</span>
                </button>
                <button type="button" class="chip chip-yellow" data-filter="yellow">
                    <span>Cần chú ý (<?= $stats['yellow'] ?>)</span>
                </button>
                <button type="button" class="chip chip-green" data-filter="green">
                    <span>Ổn định (<?= $stats['green'] ?>)</span>
                </button>
            </div>

            <div class="search-box-wrap">
                <span class="search-icon"><?= get_svg_icon('search', 14) ?></span>
                <input type="text" id="search" placeholder="Tìm tên, mã học sinh..." data-i18n-placeholder="scanner.search_placeholder">
            </div>
        </div>

        <!-- MOBILE SWITCHER -->
        <div class="mobile-view-switcher" id="mobileViewSwitcher">
            <button type="button" class="m-view-btn active" id="btnViewCards" onclick="switchMobileView('cards')">
                <?= get_svg_icon('users', 14) ?>
                <span>Dạng Thẻ Học Sinh</span>
            </button>
            <button type="button" class="m-view-btn" id="btnViewTable" onclick="switchMobileView('table')">
                <?= get_svg_icon('bar-chart', 14) ?>
                <span>Dạng Bảng Điểm</span>
            </button>
        </div>

        <!-- 5. FEATURE LEDGER RADAR TABLE -->
        <div class="ledger-table-wrap view-table-hidden">
            <table class="radar-table" id="radarTable">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">STT</th>
                        <th style="width: 85px;">Mã HS</th>
                        <th style="min-width: 170px;">Họ và Tên Học Sinh</th>
                        <th style="min-width: 120px;">Lớp & Môn</th>
                        <th style="min-width: 130px;">Xu hướng</th>
                        <th style="min-width: 120px;">Mức học lực</th>
                        <th style="width: 70px; text-align: center;">ĐTB</th>
                        <th style="width: 130px; text-align: center;">ĐĐGtx (1-4)</th>
                        <th style="width: 55px; text-align: center;">GK</th>
                        <th style="width: 55px; text-align: center;">CK</th>
                        <th style="width: 105px;">Vùng Rủi Ro</th>
                        <th style="min-width: 240px;">Năng Lực & Khuyến Nghị</th>
                        <th style="width: 95px; text-align: center;">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $stt = 1; foreach ($students as $s): ?>
                        <tr class="radar-row" 
                            data-student-id="<?= $s['student_id'] ?>"
                            data-student-idx="<?= $s['idx'] ?? ($stt - 1) ?>"
                            data-student-code="<?= htmlspecialchars(!empty($s['student_code']) ? $s['student_code'] : 'HS' . $s['student_id']) ?>"
                            data-full-name="<?= htmlspecialchars($s['full_name']) ?>"
                            data-class-name="<?= htmlspecialchars($s['class_name'] ?? $cleanClassName) ?>"
                            data-subject="<?= htmlspecialchars($s['subject'] ?? $guestData['subject']) ?>"
                            data-birthday="<?= htmlspecialchars($s['birthday'] ?? '') ?>"
                            data-phone="<?= htmlspecialchars($s['phone'] ?? '') ?>"
                            data-tx1="<?= $s['tx1'] !== null ? $s['tx1'] : '' ?>"
                            data-tx2="<?= $s['tx2'] !== null ? $s['tx2'] : '' ?>"
                            data-tx3="<?= $s['tx3'] !== null ? $s['tx3'] : '' ?>"
                            data-tx4="<?= $s['tx4'] !== null ? $s['tx4'] : '' ?>"
                            data-gk="<?= $s['gk'] !== null ? $s['gk'] : '' ?>"
                            data-ck="<?= $s['ck'] !== null ? $s['ck'] : '' ?>"
                            data-avg="<?= $s['avg_score'] !== null ? $s['avg_score'] : '' ?>"
                            data-level="<?= $s['level'] ?>" 
                            data-trend="<?= $s['trend'] ?>"
                            data-momentum="<?= $s['momentum'] ?>"
                            data-momentum-label="<?= htmlspecialchars($s['trend_label']) ?>"
                            data-momentum-icon="<?= htmlspecialchars($s['trend_icon']) ?>"
                            data-tier="<?= $s['score_tier']['tier'] ?>"
                            data-tier-label="<?= htmlspecialchars($s['score_tier']['label']) ?>"
                            data-tier-badge="<?= htmlspecialchars($s['score_tier']['badge']) ?>"
                            data-tier-class="<?= htmlspecialchars($s['score_tier']['class']) ?>"
                            data-txwarn="<?= !empty($s['has_tx_warning']) ? '1' : '0' ?>"
                            data-diagnostic="<?= htmlspecialchars($s['diagnostic']) ?>"
                            <?= format_math_data_attributes($s) ?>
                            data-advice="<?= htmlspecialchars($s['competency']['pedagogical_advice'] ?? '') ?>"
                            data-competency-title="<?= htmlspecialchars($s['competency']['competency_title'] ?? '') ?>"
                            data-note="<?= htmlspecialchars($s['teacher_note'] ?? '') ?>"
                            data-name="<?= htmlspecialchars(mb_strtolower($s['full_name'], 'UTF-8')) ?>" 
                            data-search="<?= htmlspecialchars(mb_strtolower(($s['student_code'] ?? '') . ' ' . $s['full_name'] . ' ' . ($s['class_name'] ?? '') . ' ' . ($s['subject'] ?? '') . ' ' . $s['diagnostic'] . ' ' . ($s['competency']['competency_title'] ?? '') . ' ' . ($s['teacher_note'] ?? ''), 'UTF-8')) ?>">
                            <td style="text-align: center; color: var(--cobalt-ink-muted); font-family: var(--cobalt-mono);"><?= $stt++ ?></td>
                            <td>
                                <code class="mono-code" style="color: var(--cobalt-accent);">
                                    <?= htmlspecialchars(!empty($s['student_code']) ? $s['student_code'] : 'HS' . $s['student_id']) ?>
                                </code>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--cobalt-ink);"><?= htmlspecialchars($s['full_name']) ?></div>
                                <?php if (!empty($s['phone'])): ?>
                                    <div style="font-size: 11.5px; color: var(--cobalt-ink-muted); font-family: var(--cobalt-mono);"><?= htmlspecialchars($s['phone']) ?></div>
                                <?php endif; ?>
                                <span class="js-note-badge" style="display: <?= empty($s['teacher_note']) ? 'none' : 'inline-block' ?>; font-size: 11px; background: rgba(37,99,235,0.08); color: var(--cobalt-accent); padding: 1px 6px; border-radius: 4px; margin-top: 2px;">Có ghi chú</span>
                            </td>
                            <td>
                                <span style="font-size: 12px; font-weight: 600; color: var(--cobalt-ink);">Lớp <?= htmlspecialchars($s['class_name'] ?? $cleanClassName) ?></span><br>
                                <span style="font-size: 11px; color: var(--cobalt-ink-muted);">Môn <?= htmlspecialchars($s['subject'] ?? $guestData['subject']) ?></span>
                            </td>

                            <!-- MOMENTUM -->
                            <td>
                                <span class="badge-momentum badge-momentum-<?= $s['momentum'] ?>">
                                    <?= htmlspecialchars($s['trend_label']) ?>
                                </span>
                            </td>

                            <!-- SCORE TIER -->
                            <td>
                                <span class="badge-momentum <?= $s['score_tier']['class'] ?>">
                                    <?= htmlspecialchars($s['score_tier']['badge']) ?>
                                </span>
                            </td>

                            <!-- AVG -->
                            <td style="text-align: center;">
                                <?php if ($s['avg_score'] !== null && $s['avg_score'] !== ''): ?>
                                    <span class="avg-score-badge avg-<?= $s['level'] ?>">
                                        <?= number_format((float)$s['avg_score'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="avg-score-badge avg-empty">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- TX 1-4 -->
                            <td style="text-align: center; font-size: 13px; font-family: var(--cobalt-mono);">
                                <?php
                                $txFormatted = [];
                                for ($i = 1; $i <= 4; $i++) {
                                    $val = $s["tx$i"];
                                    if ($val !== null && $val !== '') {
                                        $num = (float)$val;
                                        $txFormatted[] = number_format($num, 1);
                                    } else {
                                        $txFormatted[] = '—';
                                    }
                                }
                                echo implode(' · ', $txFormatted);
                                ?>
                            </td>

                            <!-- GK & CK -->
                            <td style="text-align: center; font-weight: 700; font-family: var(--cobalt-mono);">
                                <?= ($s['gk'] !== null && $s['gk'] !== '') ? number_format((float)$s['gk'], 1) : '—' ?>
                            </td>
                            <td style="text-align: center; font-weight: 700; font-family: var(--cobalt-mono);">
                                <?= ($s['ck'] !== null && $s['ck'] !== '') ? number_format((float)$s['ck'], 1) : '—' ?>
                            </td>

                            <!-- RISK ZONE -->
                            <td>
                                <?php if ($s['level'] === 'red'): ?>
                                    <span class="status-badge badge-red">Nguy cấp</span>
                                <?php elseif ($s['level'] === 'yellow'): ?>
                                    <span class="status-badge badge-yellow">Cần chú ý</span>
                                <?php elseif ($s['level'] === 'pending' || $s['momentum'] === 'pending'): ?>
                                    <span class="status-badge" style="background: var(--cobalt-paper); color: var(--cobalt-ink-muted); border: 1px solid var(--cobalt-rule);">Chờ điểm</span>
                                <?php else: ?>
                                    <span class="status-badge badge-green">Ổn định</span>
                                <?php endif; ?>
                            </td>

                            <!-- PEDAGOGY -->
                            <td>
                                <div style="font-weight: 600; font-size: 13px; color: var(--cobalt-ink);"><?= htmlspecialchars($s['competency']['competency_title']) ?></div>
                                <div style="font-size: 12px; color: var(--cobalt-ink-muted); margin-top: 2px; line-height: 1.4;"><?= htmlspecialchars($s['competency']['pedagogical_advice']) ?></div>
                            </td>

                            <!-- ACTIONS -->
                            <td style="text-align: center; white-space: nowrap;">
                                <button type="button" class="btn-open-detail" title="Xem năng lực học tập & In phiếu">
                                    <?= get_svg_icon('eye', 13) ?>
                                    <span>Chi tiết</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="radarEmptyRow" class="radar-filter-empty-row" style="display: none;">
                        <td colspan="13" style="text-align: center; padding: 36px; color: var(--cobalt-ink-muted);">
                            Không tìm thấy học sinh nào phù hợp với bộ lọc hoặc từ khóa tìm kiếm.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 6. MOBILE STUDENT CARDS FEED -->
        <div class="mobile-student-cards" id="mobileStudentCards">
            <?php foreach ($students as $s): ?>
                <?php
                    $mSid = (int)$s['student_id'];
                    $mAvg = ($s['avg_score'] !== null && $s['avg_score'] !== '') ? (float)$s['avg_score'] : null;
                    $mRisk = $s['level'];
                    $mRiskLabel = ($mRisk === 'red') ? 'Nguy cấp' : (($mRisk === 'yellow') ? 'Cần chú ý' : (($mRisk === 'green') ? 'Ổn định' : 'Chờ điểm'));
                    $nameParts = explode(' ', trim($s['full_name']));
                    $mInitials = mb_strtoupper(mb_substr(end($nameParts), 0, 2, 'UTF-8'), 'UTF-8');
                ?>
                <div class="m-student-card"
                     data-student-id="<?= $mSid ?>"
                     data-level="<?= $mRisk ?>"
                     data-trend="<?= $s['trend'] ?>"
                     data-momentum="<?= $s['momentum'] ?>"
                     data-tier="<?= $s['score_tier']['tier'] ?>"
                     data-txwarn="<?= !empty($s['has_tx_warning']) ? '1' : '0' ?>"
                     data-search="<?= htmlspecialchars(mb_strtolower(($s['student_code'] ?? '') . ' ' . $s['full_name'] . ' ' . ($s['class_name'] ?? '') . ' ' . ($s['subject'] ?? ''), 'UTF-8')) ?>">
                    
                    <div class="m-card-header">
                        <div class="m-avatar"><?= $mInitials ?></div>
                        <div class="m-info">
                            <div class="m-name"><?= htmlspecialchars($s['full_name']) ?></div>
                            <div class="m-meta">
                                <span class="m-code"><?= htmlspecialchars(!empty($s['student_code']) ? $s['student_code'] : 'HS' . $mSid) ?></span>
                                <span>•</span>
                                <span>Lớp <?= htmlspecialchars($s['class_name'] ?? $cleanClassName) ?></span>
                                <span>•</span>
                                <span>Môn <?= htmlspecialchars($s['subject'] ?? $guestData['subject']) ?></span>
                            </div>
                        </div>
                        <div class="m-badges">
                            <span class="badge-momentum <?= $s['score_tier']['class'] ?>"><?= htmlspecialchars($s['score_tier']['badge']) ?></span>
                        </div>
                    </div>

                    <div class="m-metrics-row">
                        <div class="m-metric">
                            <span class="m-metric-label">ĐTB Môn</span>
                            <span class="m-metric-val avg-<?= $mRisk ?>"><?= $mAvg !== null ? number_format($mAvg, 1) : '—' ?></span>
                        </div>
                        <div class="m-metric">
                            <span class="m-metric-label">Xu Hướng</span>
                            <span class="m-metric-val" style="font-size: 11.5px;"><?= htmlspecialchars($s['trend_label']) ?></span>
                        </div>
                        <div class="m-metric">
                            <span class="m-metric-label">Vùng Rủi Ro</span>
                            <span class="m-metric-val" style="color: <?= ($mRisk === 'red') ? '#dc2626' : (($mRisk === 'yellow') ? '#d97706' : '#16a34a') ?>; font-size: 11.5px;">
                                <?= $mRiskLabel ?>
                            </span>
                        </div>
                    </div>

                    <div class="m-pedagogy-box">
                        <div class="m-pedagogy-title">
                            <?= get_svg_icon('activity', 13) ?>
                            <span><?= htmlspecialchars($s['competency']['competency_title'] ?? 'Đang phân tích') ?></span>
                        </div>
                        <div class="m-pedagogy-desc">
                            <?= htmlspecialchars($s['competency']['pedagogical_advice'] ?? 'Đang cập nhật hướng dẫn hành động sư phạm.') ?>
                        </div>
                    </div>

                    <div class="m-card-actions">
                        <button type="button" class="btn-m-detail" onclick="openStudentModalFromId(<?= $mSid ?>)">
                            <?= get_svg_icon('eye', 14) ?>
                            <span>Chi Tiết &amp; Sổ Tay</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 7. MODAL CHI TIẾT NĂNG LỰC HỌC TẬP & SỔ TAY NHẬN XÉT SƯ PHẠM (CHẾ ĐỘ KHÁCH) -->
        <div id="competencyDetailModal" class="competency-modal-overlay" style="display: none;">
            <div class="competency-modal-card" role="dialog" aria-modal="true" aria-labelledby="modalStudentName">
                <div class="competency-modal-header">
                    <div class="modal-header-profile">
                        <div class="modal-avatar" id="modalStudentAvatar">HS</div>
                        <div class="modal-name-group">
                            <h2 class="modal-student-name" id="modalStudentName">Họ và Tên Học Sinh</h2>
                            <div class="modal-meta-badges">
                                <span class="mono-code" id="modalStudentCode" style="font-weight: 700; color: var(--cobalt-accent);">HS0001</span>
                                <span style="color: var(--cobalt-rule-strong);">•</span>
                                <span class="badge-class" id="modalStudentClass">Lớp <?= htmlspecialchars($cleanClassName) ?></span>
                                <span class="badge-subject" id="modalStudentSubject">Môn Toán</span>
                                <span id="modalStudentBirthday" class="muted small"></span>
                                <span id="modalStudentPhone" class="muted small"></span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="modal-close-btn" id="modalCloseBtn" title="Đóng cửa sổ" aria-label="Đóng"><?= get_svg_icon('close', 18) ?></button>
                </div>

                <div class="competency-modal-body">
                    <div class="modal-badges-grid">
                        <div class="modal-badge-card" style="border-left: 4px solid var(--cobalt-accent);">
                            <span class="modal-badge-label">Xu hướng tiến bộ</span>
                            <div class="modal-badge-val" id="modalMomentumBadge">Đang tiến bộ</div>
                        </div>
                        <div class="modal-badge-card" style="border-left: 4px solid #0284c7;">
                            <span class="modal-badge-label">Mức học lực (TT22)</span>
                            <div class="modal-badge-val" id="modalScoreTierBadge">Mức Tốt</div>
                        </div>
                        <div class="modal-badge-card" style="border-left: 4px solid #f59e0b;">
                            <span class="modal-badge-label">Trạng thái cảnh báo</span>
                            <div class="modal-badge-val" id="modalRiskBadge">Ổn định</div>
                        </div>
                    </div>

                    <div>
                        <div class="timeline-section-title">
                            <span style="display: flex; align-items: center; gap: 6px;">
                                <?= get_svg_icon('trend-up', 15, '', 'color: var(--cobalt-accent);') ?>
                                <span>Chuỗi Điểm Số & Diễn Biến Học Tập</span>
                            </span>
                            <span class="muted small" style="text-transform: none; font-weight: 600;">Thông tư 22/2021/TT-BGDĐT</span>
                        </div>
                        <div class="score-timeline-chips" id="modalScoreTimeline"></div>
                    </div>

                    <div class="cut-card" style="margin-top: 14px; padding: 14px 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <?= get_svg_icon('radar', 16, '', 'color: var(--cobalt-accent);') ?>
                                <span style="font-weight: 700; font-size: 13.5px; color: var(--cobalt-ink);">Dự Báo Tiến Bộ & Mục Tiêu Điểm Thi</span>
                                <span id="modalMathAcademicBadge" style="font-size: 11px; font-weight: 700; background: #fefce8; color: #b45309; border: 1px solid #fde047; padding: 2px 8px; border-radius: 9999px;">Học kỳ I</span>
                            </div>
                            <span style="font-size: 11px; background: rgba(16,185,129,0.1); color: #059669; border: 1px solid rgba(16,185,129,0.25); padding: 3px 8px; border-radius: 6px; font-weight: 700;">Dự báo Sư phạm TT22</span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 12px;">
                            <div class="cut-card" id="modalSlopeCard" style="padding: 12px; background: var(--cobalt-paper);">
                                <div style="font-size: 10.5px; color: var(--cobalt-ink-muted); font-weight: 700; text-transform: uppercase;">Xu hướng học tập</div>
                                <div id="modalMathSlope" style="font-size: 14px; font-weight: 800; margin-top: 4px;">Đang phân tích...</div>
                                <div id="modalMathSlopeSummary" style="font-size: 11.5px; color: var(--cobalt-ink); margin-top: 4px; line-height: 1.45;">Chuỗi điểm đang phân tích...</div>
                            </div>
                            <div class="cut-card" id="modalStabilityCard" style="padding: 12px; background: var(--cobalt-paper);">
                                <div style="font-size: 10.5px; color: var(--cobalt-ink-muted); font-weight: 700; text-transform: uppercase;">Tâm lý & độ ổn định phong độ</div>
                                <div id="modalMathStability" style="font-size: 14px; font-weight: 800; margin-top: 4px;">Đang phân tích...</div>
                                <div id="modalMathStabilityDesc" style="font-size: 11.5px; color: var(--cobalt-ink); margin-top: 4px; line-height: 1.45;">Mức độ phân tán phong độ</div>
                            </div>
                        </div>

                        <div class="cut-card" style="padding: 12px; background: var(--cobalt-paper);">
                            <div id="modalMathTargetPrompt" style="font-size: 11.5px; color: var(--cobalt-ink); font-weight: 700; margin-bottom: 8px; text-transform: uppercase;">
                                Điểm Cuối kỳ (CK) cần đạt để đạt mục tiêu:
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;" id="modalTargetCardsGrid"></div>
                            <div id="modalMathTargetAlert" style="margin-top: 10px; font-size: 12px; font-weight: 700; display: none; padding: 8px 12px; border-radius: 8px; line-height: 1.5;"></div>
                        </div>
                    </div>

                    <div class="modal-info-box diagnostic-box">
                        <div class="modal-info-title" style="color: var(--cobalt-accent);">
                            <span>Chẩn đoán chuyên môn:</span>
                        </div>
                        <div class="modal-info-content" id="modalDiagnosticText">
                            Đang tải chẩn đoán...
                        </div>
                    </div>

                    <div class="modal-info-box advice-box">
                        <div class="modal-info-title" style="color: #059669;">
                            <span>Đánh giá năng lực & Đề xuất sư phạm:</span>
                        </div>
                        <div class="modal-info-content">
                            <b id="modalCompetencyTitle" style="color: var(--cobalt-ink); display: block; margin-bottom: 4px;">Tiêu đề năng lực</b>
                            <div id="modalPedagogicalAdvice">Nội dung khuyến nghị sư phạm hành động...</div>
                        </div>
                    </div>

                    <!-- Ghi chú sư phạm -->
                    <div class="teacher-note-section">
                        <div class="teacher-note-header">
                            <div>
                                <div class="teacher-note-title">
                                    <span>Ghi chú chuyên môn học sinh</span>
                                </div>
                                <div class="teacher-note-sub">
                                    Ghi chú cá nhân hóa (lưu an toàn vào phiên làm việc để xuất kèm phiếu phụ đạo PDF/Excel)
                                </div>
                            </div>
                            <span id="teacherNoteSavedTime" class="muted small"></span>
                        </div>
                        <textarea id="modalTeacherNoteInput" class="teacher-note-textarea" placeholder="Nhập ghi chú sư phạm, kế hoạch phụ đạo hoặc điểm cần nhắc nhở phụ huynh..."></textarea>
                        <div class="teacher-note-actions">
                            <span id="teacherNoteStatus" class="teacher-note-status" style="display: none;">
                                Đã lưu ghi chú
                            </span>
                            <div style="margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" class="key key--primary key--sm" id="btnSaveGuestNote">
                                    <span>Lưu ghi chú</span>
                                </button>
                                <a href="#" target="_blank" id="btnGuestPrintPdf" class="key key--sm">
                                    <?= get_svg_icon('printer', 14) ?> <span>In phiếu PDF</span>
                                </a>
                                <a href="#" target="_blank" id="btnGuestExportXlsx" class="key key--sm">
                                    <?= get_svg_icon('download', 14) ?> <span>Tải bảng Excel</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

<script>
    // Lucian UX/UI Prompt #032 Drag-and-drop Interaction Handler
    (function () {
        var root = document.getElementById('u032DropzoneWrap');
        if (!root) return;
        var zone = document.getElementById('u032ZoneBtn');
        var pick = document.getElementById('fileInput');
        var list = document.getElementById('u032List');
        var rm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var TYPE = { xlsx: '#10b981', xls: '#10b981', csv: '#3b82f6', pdf: '#ef4444' };

        function formatBytes(b) {
            return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
        }

        function run(row, bytes, fail) {
            row.className = 'u032-row';
            var st = row.querySelector('.u032-st');
            var sm = row.querySelector('small');
            if (!st || !sm) return;
            st.innerHTML = '<span class="u032-spin" aria-hidden="true"></span>';
            var p = 0, t0 = performance.now(), D = rm ? 150 : 800 + Math.random() * 400;

            (function f(now) {
                if (!root.isConnected) return;
                p = Math.min(1, (now - t0) / D);
                var shown = fail ? Math.min(p, 0.62) : p;
                row.style.setProperty('--p', shown.toFixed(3));
                sm.textContent = formatBytes(bytes * shown) + ' / ' + formatBytes(bytes) + ' · ' + Math.round(shown * 100) + '%';

                if (p < 1) {
                    requestAnimationFrame(f);
                } else {
                    row.classList.add('done');
                    sm.textContent = formatBytes(bytes) + ' · Đã nạp thành công (Sẵn sàng phân tích)';
                    st.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.4l3 3 6-6.4"/></svg>';
                }
            })(t0);
        }

        function addFile(name, bytes, fail) {
            var ext = (name.split('.').pop() || '').toLowerCase();
            var li = document.createElement('li');
            li.className = 'u032-row';
            var badgeColor = TYPE[ext] || '#2563eb';
            var extLabel = (ext.slice(0, 4).toUpperCase() || 'FILE');

            li.innerHTML = '<span class="u032-ic" style="background:' + badgeColor + '" aria-hidden="true">' + extLabel + '</span>' +
                           '<span class="u032-meta"><b></b><small></small></span>' +
                           '<span class="u032-st"></span>' +
                           '<span class="u032-bar" aria-hidden="true"></span>';
            li.querySelector('b').textContent = name;
            list.insertBefore(li, list.firstChild);
            while (list.children.length > 3) list.lastElementChild.remove();
            run(li, bytes, fail);
        }

        if (zone && pick) {
            zone.addEventListener('click', function () { pick.click(); });
            pick.addEventListener('change', function () {
                if (pick.files && pick.files.length) {
                    Array.prototype.forEach.call(pick.files, function (f) { addFile(f.name, f.size, false); });
                }
            });

            var depth = 0;
            zone.addEventListener('dragenter', function (e) {
                e.preventDefault(); depth++;
                root.classList.add('is-over');
            });
            zone.addEventListener('dragover', function (e) { e.preventDefault(); });
            zone.addEventListener('dragleave', function () {
                if (--depth <= 0) { depth = 0; root.classList.remove('is-over'); }
            });
            zone.addEventListener('drop', function (e) {
                e.preventDefault(); depth = 0; root.classList.remove('is-over');
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    pick.files = e.dataTransfer.files;
                    Array.prototype.forEach.call(e.dataTransfer.files, function (f) { addFile(f.name, f.size, false); });
                }
            });
        }
    })();

    // Ghi chú học sinh trong Chế độ Khách
    const btnSaveGuestNote = document.getElementById('btnSaveGuestNote');
    if (btnSaveGuestNote) {
        btnSaveGuestNote.addEventListener('click', function() {
            const noteInput = document.getElementById('modalTeacherNoteInput');
            const statusEl = document.getElementById('teacherNoteStatus');
            const noteVal = (noteInput ? noteInput.value : '').trim();

            const modal = document.getElementById('competencyDetailModal');
            const currentIdx = modal ? modal.dataset.currentIdx : -1;

            if (currentIdx === undefined || currentIdx < 0) {
                alert('Không xác định được chỉ số học sinh trong phiên!');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'save_guest_note');
            formData.append('student_idx', currentIdx);
            formData.append('note', noteVal);

            btnSaveGuestNote.disabled = true;
            btnSaveGuestNote.innerHTML = '<span>Đang lưu...</span>';

            fetch('quet_nhanh.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnSaveGuestNote.disabled = false;
                btnSaveGuestNote.innerHTML = '<span>Lưu ghi chú</span>';
                if (data.success) {
                    if (statusEl) {
                        statusEl.style.display = 'inline-flex';
                        statusEl.textContent = data.message;
                    }
                    const row = document.querySelector(`tr[data-student-idx="${currentIdx}"]`);
                    if (row) {
                        row.dataset.note = noteVal;
                        const badge = row.querySelector('.js-note-badge');
                        if (badge) {
                            if (noteVal) {
                                badge.style.display = 'inline-block';
                                badge.title = noteVal.substring(0, 80) + '...';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                } else {
                    alert('Lỗi: ' + (data.error || 'Không thể lưu ghi chú'));
                }
            })
            .catch(err => {
                btnSaveGuestNote.disabled = false;
                btnSaveGuestNote.innerHTML = '<span>Lưu ghi chú</span>';
                alert('Lỗi kết nối: ' + err.message);
            });
        });
    }
</script>
<?php
$extraScripts = '<script src="assets/js/main.js?v=' . filemtime(__DIR__ . '/assets/js/main.js') . '"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
