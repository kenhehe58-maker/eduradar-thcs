<?php
/**
 * =============================================================================
 * Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar)
 * Module: Quét Nhanh 1 Chạm Sổ Điểm VnEdu (Chế Độ Khách / Guest Mode)
 * Tiêu chuẩn: Thông tư 22/2021/TT-BGDĐT
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

// HÀNH ĐỘNG 2A: NẠP SỔ ĐIỂM MẪU 10 HỌC SINH ĐA DẠNG ĐỘNG NĂNG
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
            'teacher_note' => 'Đang có đà bứt phá rất mạnh mẽ, chăm chỉ làm bài tập về nhà.'
        ],
        [
            'student_id' => 3,
            'student_code' => 'HS903',
            'full_name' => 'Lê Quốc Bảo',
            'birthday' => '2010-11-05',
            'phone' => '0903112233',
            'tx1' => 5.0, 'tx2' => 5.5, 'tx3' => 5.0, 'tx4' => 5.5, 'gk' => 5.5, 'ck' => 5.0,
            'teacher_note' => 'Học lực mức trung bình đang đi đều, duy trì ổn định nhưng cần tiếp sức để bứt phá lên khá.'
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

        // Phân bổ giáo dục thực tế
        if ($pct < 0.15) {
            // Nhóm 1: Bứt phá / Xuất sắc (15%)
            $tx1 = round(rand(85, 95) / 10, 1);
            $tx2 = round(rand(90, 100) / 10, 1);
            $tx3 = round(rand(90, 100) / 10, 1);
            $tx4 = round(rand(95, 100) / 10, 1);
            $gk  = round(rand(90, 100) / 10, 1);
            $ck  = round(rand(95, 100) / 10, 1);
            $note = 'Tư duy logic rất sắc bén, liên tục bứt phá đứng đầu môn học.';
        } elseif ($pct < 0.45) {
            // Nhóm 2: Đang tiến bộ (30%)
            $tx1 = round(rand(55, 65) / 10, 1);
            $tx2 = round(rand(65, 75) / 10, 1);
            $tx3 = round(rand(70, 80) / 10, 1);
            $tx4 = round(rand(75, 85) / 10, 1);
            $gk  = round(rand(70, 80) / 10, 1);
            $ck  = round(rand(80, 90) / 10, 1);
            $note = 'Có đà tiến bộ rất đáng khen, bài kiểm tra sau luôn cao hơn bài trước.';
        } elseif ($pct < 0.70) {
            // Nhóm 3: Duy trì đều (25%)
            $tx1 = round(rand(50, 60) / 10, 1);
            $tx2 = round(rand(55, 65) / 10, 1);
            $tx3 = round(rand(50, 60) / 10, 1);
            $tx4 = round(rand(55, 65) / 10, 1);
            $gk  = round(rand(55, 65) / 10, 1);
            $ck  = round(rand(55, 65) / 10, 1);
            $note = 'Học lực mức trung bình đi đều ổn định, cần tiếp sức thêm bài tập nâng cao.';
        } elseif ($pct < 0.85) {
            // Nhóm 4: Sa sút / Báo động tụt dốc (15%)
            $tx1 = round(rand(75, 85) / 10, 1);
            $tx2 = round(rand(70, 80) / 10, 1);
            $tx3 = round(rand(60, 70) / 10, 1);
            $tx4 = round(rand(50, 60) / 10, 1);
            $gk  = round(rand(65, 75) / 10, 1);
            $ck  = round(rand(40, 50) / 10, 1);
            $note = 'Điểm số tụt dần từ giữa kỳ đến cuối kỳ, giáo viên cần tìm hiểu lý do tâm lý.';
        } elseif ($pct < 0.95) {
            // Nhóm 5: Nguy cấp (10%)
            $tx1 = round(rand(30, 40) / 10, 1);
            $tx2 = round(rand(35, 45) / 10, 1);
            $tx3 = round(rand(30, 40) / 10, 1);
            $tx4 = round(rand(35, 45) / 10, 1);
            $gk  = round(rand(35, 45) / 10, 1);
            $ck  = round(rand(30, 40) / 10, 1);
            $note = 'Hổng kiến thức căn bản, thuộc diện bắt buộc phụ đạo tăng cường ngoài giờ.';
        } else {
            // Nhóm 6: Đang chờ điểm số (5%)
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gradebook_file'])) {
    $file = $_FILES['gradebook_file'];
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

            // Tự động nhận diện cột Tên Lớp nếu có trong file
            if ($headerIdx >= 0) {
                foreach ($rows[$headerIdx] as $cIdx => $cVal) {
                    $norm = mb_strtolower(trim((string)$cVal), 'UTF-8');
                    if ($norm === 'lớp' || $norm === 'lop' || $norm === 'class') {
                        $colMap['class_col'] = $cIdx;
                        break;
                    }
                }
            }

            // Tự động nhận diện tên lớp từ tên tệp (ví dụ: DanhSachHocSinh_7A6.xlsx -> 7A6)
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

                // Nhận diện lớp từ dòng học sinh
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

                // Tính ĐTB theo TT22
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

// Bổ sung phân tích toán học và dự báo điểm số mục tiêu TT22
foreach ($students as &$st) {
    if (!isset($st['math_profile'])) {
        $txList = $st['tx_list'] ?? [];
        $st['math_profile'] = diagnose_student_mathematical_profile($txList, $st['gk'] ?? null, $st['ck'] ?? null);
    }
}
unset($st);

// Chuẩn hóa tên lớp để tránh bị "Lớp Lớp 9A1"
$rawClassName = $guestData['class_name'] ?? '9A1';
$cleanClassName = preg_replace('/^Lớp\s+/iu', '', trim($rawClassName));

// Sử dụng hàm tính toán chuẩn calculateRadarStats
$stats = calculateRadarStats($students);

// Kiểm tra xem trong bộ dữ liệu có ít nhất 1 điểm số nào hay không
$hasAnyScoresInDataset = false;
foreach ($students as $st) {
    if (!empty($st['tx_list']) || $st['gk'] !== null || $st['ck'] !== null || $st['avg_score'] !== null) {
        $hasAnyScoresInDataset = true;
        break;
    }
}

$pageTitle = 'Quét Nhanh 1 Chạm VnEdu – Tuyến Khách';
?>
<?php
$pageTitle = 'Quét Nhanh 1 Chạm VnEdu – Phân Tích Độc Lập';
$activeNav = 'scan';
$extraHead = '
    <style>
        .guest-banner {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: #ffffff;
            padding: 16px 24px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15);
            flex-wrap: wrap;
            gap: 16px;
        }
        .guest-banner-title {
            font-size: 16px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .guest-banner-sub {
            font-size: 13px;
            color: #e0e7ff;
            margin-top: 4px;
        }
        .guest-banner-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-guest-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.15s ease;
            border: none;
            cursor: pointer;
        }
        .btn-guest-white { background: #ffffff; color: #4338ca; }
        .btn-guest-white:hover { background: #f8fafc; color: #3730a3; transform: translateY(-1px); }
        .btn-guest-pdf { background: #10b981; color: #ffffff; }
        .btn-guest-pdf:hover { background: #059669; }
        .btn-guest-excel { background: #0ea5e9; color: #ffffff; }
        .btn-guest-excel:hover { background: #0284c7; }
        .btn-guest-reset { background: rgba(255, 255, 255, 0.2); color: #ffffff; }
        .btn-guest-reset:hover { background: rgba(255, 255, 255, 0.3); }

        /* Lucian UX/UI Prompt #032 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#032 */
        .u032{position:relative;width:100%;max-width:680px;margin:20px auto 10px;}
        .u032-zone{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;height:190px;border:none !important;outline:none;padding:0;border-radius:24px;background:#ffffff !important;color:#0f172a !important;cursor:pointer;box-shadow:0 10px 30px rgba(15,23,42,.06);transition:transform .3s ease,background-color .3s ease}
        .u032-zone:focus-visible{outline:2px solid #4f46e5;outline-offset:4px}
        .u032-zone svg.u032-dash{position:absolute;left:1px;top:1px;width:calc(100% - 2px);height:calc(100% - 2px);overflow:visible;pointer-events:none}
        .u032-dash rect{fill:none;stroke:#cbd5e1;stroke-width:2;stroke-dasharray:8 7;transition:stroke .3s ease}
        .u032-zone:hover,.u032.is-over .u032-zone{background:#f8fafc !important}
        .u032-zone:hover .u032-dash rect,.u032.is-over .u032-dash rect{stroke:#4f46e5;animation:u032-march .9s linear infinite}
        @keyframes u032-march{to{stroke-dashoffset:-30}}
        .u032.is-over .u032-zone{transform:scale(1.02);background:#f1f5f9 !important}
        .u032-orb{width:56px;height:56px;border-radius:18px;display:grid;place-items:center;color:#fff;background:linear-gradient(140deg,#4f46e5,#818cf8);box-shadow:0 14px 26px -10px rgba(79,70,229,.5);transition:transform .3s ease}
        .u032-orb svg{width:26px;height:26px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .u032-zone:hover .u032-orb{transform:translateY(-3px)}
        .u032.is-over .u032-orb{transform:translateY(-6px) scale(1.1) rotate(-6deg)}
        .u032-zone b{margin-top:12px;font-size:15px;font-weight:800;letter-spacing:-.02em;color:#0f172a !important;}
        .u032-zone b em{font-style:normal;color:#4f46e5}
        .u032-zone small{margin-top:3px;font-size:12.5px;color:#64748b !important}
        .u032-file{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
        .u032-list{margin:12px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px}
        .u032-row{position:relative;display:flex;align-items:center;gap:12px;height:58px;padding:0 14px;border-radius:16px;background:#ffffff !important;border:1px solid #e2e8f0;box-shadow:0 4px 12px rgba(0,0,0,.04);overflow:hidden;animation:u032-in .3s ease}
        @keyframes u032-in{from{transform:translateY(-10px) scale(.96);opacity:0}}
        .u032-row.out{transition:transform .35s var(--ux-ease,cubic-bezier(.2,.8,.2,1)),opacity .3s;transform:translateX(30px);opacity:0}
        .u032-ic{width:36px;height:38px;flex:none;border-radius:8px;display:grid;place-items:end center;padding-bottom:5px;font-size:9px;font-weight:800;letter-spacing:.04em;color:#fff;background:var(--c,#4f46e5)}
        .u032-meta{flex:1;min-width:0}
        .u032-meta b{display:block;font-size:13.5px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .u032-meta small{display:block;margin-top:2px;font-size:11.5px;color:var(--ux-ink-3,#64748b);font-variant-numeric:tabular-nums}
        .u032-bar{position:absolute;left:0;bottom:0;height:3px;width:100%;background:var(--ux-accent,#4f46e5);transform-origin:0 50%;transform:scaleX(var(--p,0));transition:transform .2s linear,background-color .3s,opacity .5s .4s}
        .u032-row.done .u032-bar{background:var(--ux-mint,#10b981);opacity:0}
        .u032-row.fail .u032-bar{background:var(--ux-danger,#ef4444)}
        .u032-st{width:28px;height:28px;flex:none;border-radius:50%;display:grid;place-items:center}
        .u032-st svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round}
        .u032-row.done .u032-st{background:#10b981;color:#fff;animation:u032-pop .45s var(--ux-spring,cubic-bezier(.34,1.45,.64,1))}
        @keyframes u032-pop{from{transform:scale(.3)}}
        .u032-row.done .u032-st path{stroke-dasharray:16;stroke-dashoffset:16;animation:u032-draw .4s var(--ux-ease,cubic-bezier(.2,.8,.2,1)) .15s forwards}
        @keyframes u032-draw{to{stroke-dashoffset:0}}
        .u032-spin{width:18px;height:18px;border-radius:50%;border:2px solid #e2e8f0;border-top-color:var(--ux-accent,#4f46e5);animation:u032-rot .8s linear infinite}
        @keyframes u032-rot{to{transform:rotate(360deg)}}
        .upload-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            max-width: 600px;
            margin: 20px auto 0 auto;
            text-align: left;
        }
        .upload-input-group label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 6px;
            display: block;
        }
        .upload-input-group input, .upload-input-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13.5px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            max-width: 960px;
            margin: 36px auto 20px auto;
        }
        .feature-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px 22px;
            text-align: left;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .feature-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }
        .feature-icon {
            font-size: 26px;
            margin-bottom: 10px;
            display: inline-block;
        }
        .feature-title {
            font-size: 14.5px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }
        .feature-desc {
            font-size: 13px;
            color: #64748b;
            line-height: 1.55;
        }

        /* DARK THEME SUPPORT FOR QUET NHANH */
        [data-theme="dark"] .u032-zone {
            background: #131b2e !important;
            color: #f8fafc !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
        }
        [data-theme="dark"] .u032-zone:hover,
        [data-theme="dark"] .u032.is-over .u032-zone {
            background: #1a253c !important;
        }
        [data-theme="dark"] .u032-dash rect {
            stroke: #334466 !important;
        }
        [data-theme="dark"] .u032-zone b {
            color: #f8fafc !important;
        }
        [data-theme="dark"] .u032-zone small {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .u032-row {
            background: #131b2e !important;
            border-color: #23324d !important;
        }
        [data-theme="dark"] .u032-meta b {
            color: #f8fafc !important;
        }
        [data-theme="dark"] .upload-input-group label {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .upload-input-group input,
        [data-theme="dark"] .upload-input-group select {
            background: #1a253c !important;
            color: #f8fafc !important;
            border-color: #334466 !important;
        }
        [data-theme="dark"] .feature-item {
            background: #131b2e !important;
            border-color: #23324d !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important;
        }
        [data-theme="dark"] .feature-title {
            color: #f8fafc !important;
        }
        [data-theme="dark"] .feature-desc {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .sample-box-card {
            background: #131b2e !important;
            border-color: #23324d !important;
        }

        .no-scores-alert-banner {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
    </style>
';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- BANNER CHẾ ĐỘ KHÁCH HOẶC QUÉT NHANH ĐỘC LẬP -->
    <div class="guest-banner">
        <div>
            <div class="guest-banner-title">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <span>QUÉT & PHÂN TÍCH TỆP ĐỘC LẬP // SANDBOX</span>
                <?php else: ?>
                    <span>CHẾ ĐỘ KHÁCH // DÙNG THỬ 1 CHẠM VNEDU</span>
                <?php endif; ?>
            </div>
            <div class="guest-banner-sub">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    Tài khoản: <strong><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></strong> (<?= ($_SESSION['role'] ?? '') === 'admin' ? 'Quản trị viên' : 'Giáo viên bộ môn' ?>). Dữ liệu tính toán độc lập trong phiên làm việc, không ảnh hưởng đến cơ sở dữ liệu trường.
                <?php elseif ($hasData): ?>
                    Đang hiển thị kết quả phân tích: <strong>Lớp <?= htmlspecialchars($cleanClassName) ?></strong> &bull; Môn <strong><?= htmlspecialchars($guestData['subject'] ?? 'Toán học') ?></strong> (<?= $stats['total'] ?> học sinh). Dữ liệu tính toán tức thì trong phiên làm việc.
                <?php else: ?>
                    Tải lên file Excel (.xlsx) hoặc CSV kết xuất từ VnEdu/SMAS để hệ thống tự động chẩn đoán 2 khung năng lực ngay lập tức.
                <?php endif; ?>
            </div>
        </div>
        <div class="guest-banner-actions">
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="index.php" class="btn-guest-action btn-guest-white">
                    Về Sổ Điểm Lớp
                </a>
            <?php endif; ?>
            <?php if ($hasData): ?>
                <a href="bao_cao_slide_bgh.php?guest=1" target="_blank" class="btn-guest-action" style="background: #0f172a; color: #ffffff;">
                    Slide Báo Cáo BGH
                </a>
                <a href="in_bao_cao_bgh.php?guest=1&format=pdf" target="_blank" class="btn-guest-action btn-guest-pdf">
                    In Báo Cáo BGH (A4/PDF)
                </a>
                <a href="in_bao_cao_bgh.php?guest=1&format=xlsx" target="_blank" class="btn-guest-action btn-guest-excel">
                    Xuất Báo Cáo BGH (Excel)
                </a>
                <a href="quet_nhanh.php?action=clear" class="btn-guest-action btn-guest-reset">
                    Tải Tệp Khác
                </a>
            <?php else: ?>
                <a href="quet_nhanh.php?action=sample" class="btn-guest-action btn-guest-white">
                    Nạp Dữ Liệu Mẫu VnEdu
                </a>
                <?php if (empty($_SESSION['user_id'])): ?>
                    <a href="login.php" class="btn-guest-action btn-guest-reset">
                        Đăng Nhập
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($uploadError)): ?>
        <div style="background: #fee2e2; border: 1px solid #f87171; color: #991b1b; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600;">
            ⚠️ <?= htmlspecialchars($uploadError) ?>
        </div>
    <?php endif; ?>

    <?php if (!$hasData): ?>
        <!-- GIAO DIỆN KHI CHƯA CÓ FILE: VÙNG KÉO THẢ TỆP EXCEL VNEDU -->
        <div style="text-align: center; margin: 30px 0 20px 0;">
            <h1 style="font-size: 26px; font-weight: 800; color: var(--text-heading, #0f172a); margin-bottom: 8px;">
                Quét 1 Chạm Sổ Điểm Điện Tử VnEdu
            </h1>
            <p style="color: var(--text-muted, #64748b); font-size: 14px; max-width: 600px; margin: 0 auto;">
                Chẩn đoán sớm rủi ro học sinh sa sút, phân tích 2 khung năng lực và xuất báo cáo nộp BGH chỉ trong vài giây.
            </p>
        </div>

        <form action="quet_nhanh.php" method="POST" enctype="multipart/form-data" id="guestUploadForm">
            <!-- Lucian UX/UI Prompt #032 · MIT License (c) 2026 Seokwoooo · https://lucianlabs.dev/uxui-prompt/#032 -->
            <div class="ux u032" id="u032DropzoneWrap">
                <button class="u032-zone" type="button" id="u032ZoneBtn">
                    <svg class="u032-dash" aria-hidden="true"><rect width="100%" height="100%" rx="24"/></svg>
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
                    <label>Tên Lớp Học (Tùy chọn)</label>
                    <input type="text" name="class_name" placeholder="Ví dụ: 9A1, 7A6 (hoặc để trống để tự động nhận diện)">
                </div>
                <div class="upload-input-group">
                    <label>Môn Học</label>
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
                <button type="submit" class="btn-guest-action btn-guest-white" style="background: #2563eb; color: #ffffff; padding: 12px 28px; font-size: 15px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);">
                    🚀 Bắt Đầu Phân Tích Radar Ngay
                </button>
            </div>
        </form>

        <div style="text-align: center; margin: 28px 0 20px 0;">
            <span style="color: var(--text-muted, #94a3b8); font-size: 13px;">— Hoặc trải nghiệm ngay không cần chuẩn bị tệp —</span><br>
            <a href="quet_nhanh.php?action=sample" class="btn-guest-action btn-guest-white" style="margin-top: 12px; border: 1.5px solid var(--primary-border, #3b82f6); background: var(--card-bg, #ffffff); color: var(--primary, #2563eb); font-weight: 700; padding: 10px 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);">
                🎯 Nạp Sổ Điểm Mẫu VnEdu THCS (10 Học Sinh Đa Dạng Động Năng)
            </a>
        </div>

        <!-- 3-STEP INTELLIGENT PIPELINE (DRIBBLE-STYLE WORKFLOW CARDS) -->
        <div class="features-grid">
            <div class="feature-item">
                <span class="feature-icon">📥</span>
                <div class="feature-title">1. Trích Xuất Bảng Điểm 1 Chạm</div>
                <div class="feature-desc">
                    Tự động nhận diện chính xác các cột Họ và tên, Mã định danh, Ngày sinh, ĐĐGtx (1-4), Điểm GK, CK từ bảng tính Excel VnEdu và SMAS.
                </div>
            </div>
            <div class="feature-item">
                <span class="feature-icon">🧠</span>
                <div class="feature-title">2. Chẩn Đoán 2 Khung Sư Phạm</div>
                <div class="feature-desc">
                    Đối sánh kép: Khung Động Năng Học Tập (tiến bộ, duy trì, sa sút) kết hợp Khung Chuẩn Điểm Số Thông tư 22/2021/TT-BGDĐT.
                </div>
            </div>
            <div class="feature-item">
                <span class="feature-icon">📑</span>
                <div class="feature-title">3. Trình Chiếu & Báo Cáo BGH</div>
                <div class="feature-desc">
                    Mở Slide thuyết trình tương tác phục vụ họp giao ban, xuất báo cáo PDF chuẩn A4 hoặc trích xuất bảng tổng hợp gửi phụ huynh.
                </div>
            </div>
        </div>

        <!-- SECURITY & SPECS PILLS -->
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin: 10px auto 40px auto; max-width: 860px;">
            <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted, #64748b); padding: 6px 14px; border-radius: 999px; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0);">
                <span>🔒</span>
                <span>Sandbox cô lập: Dữ liệu không ghi đè vào CSDL trường</span>
            </div>
            <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted, #64748b); padding: 6px 14px; border-radius: 999px; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0);">
                <span>⚡</span>
                <span>Phân tích dưới 200ms cho 1,000 học sinh</span>
            </div>
            <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted, #64748b); padding: 6px 14px; border-radius: 999px; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0);">
                <span>🏆</span>
                <span>Chuẩn hóa Thông tư 22/2021/TT-BGDĐT</span>
            </div>
        </div>

    <?php else: ?>
        <!-- GIAO DIỆN KHI ĐÃ CÓ DỮ LIỆU: RADAR DASHBOARD ĐẦY ĐỦ -->

        <?php if (!$hasAnyScoresInDataset): ?>
            <!-- BANNER HƯỚNG DẪN KHI FILE TẢI LÊN LÀ DANH SÁCH HỌC SINH (CHƯA CÓ ĐIỂM) -->
            <div class="no-scores-alert-banner">
                <span style="font-size: 28px;">💡</span>
                <div style="flex: 1;">
                    <div style="font-weight: 800; font-size: 15px; color: #166534; margin-bottom: 4px;">
                        Tệp bạn vừa tải lên là <u>Danh Sách Học Sinh</u> (chưa có cột điểm số từ VnEdu)
                    </div>
                    <div style="font-size: 13px; color: #15803d; line-height: 1.5;">
                        Hệ thống đã nhận diện thành công <strong><?= $stats['total'] ?> học sinh</strong> lớp <strong><?= htmlspecialchars($cleanClassName) ?></strong>. Hiện tại các em đều ở trạng thái <strong>⏳ Chờ điểm</strong>. Bạn có thể chọn phương án trải nghiệm nhanh:
                    </div>
                    <div style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="quet_nhanh.php?action=simulate_scores" class="btn-guest-action" style="background: #16a34a; color: #ffffff; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);">
                            🎯 Mô Phỏng Điểm Số Cho <?= $stats['total'] ?> Học Sinh Này (Xem Ngay Động Năng Radar)
                        </a>
                        <a href="quet_nhanh.php?action=sample" class="btn-guest-action" style="background: #0ea5e9; color: #ffffff;">
                            📊 Nạp Sổ Điểm Mẫu THCS Chuẩn
                        </a>
                        <a href="quet_nhanh.php?action=clear" class="btn-guest-action" style="background: #ffffff; color: #475569; border: 1px solid #cbd5e1;">
                            🔄 Tải Lên Tệp Sổ Điểm Khác Có Điểm (.xlsx)
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- NHÓM 1: CÁC THẺ THỐNG KÊ KHUNG HIỆU SUẤT (ĐỨNG TRƯỚC & NỔI BẬT) -->
        <div style="margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                    ⚡ KHUNG 1: HIỆU SUẤT & ĐỘNG NĂNG HỌC TẬP (MOMENTUM)
                </span>
                <span class="muted small">Tăng trưởng · Đi đều · Sa sút · Chờ điểm</span>
            </div>
            <section class="radar-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 16px;">
                <div class="card stat-card trend-up">
                    <div class="card-header-row">
                        <span class="card-title">📈 Đang Tiến Bộ / Bứt Phá</span>
                        <span class="card-pct trend-up"><?= $stats['up_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['momentum_up'] ?></b>
                    <span class="card-sub">Bài sau cao hơn bài trước hoặc bứt phá</span>
                </div>

                <div class="card stat-card green">
                    <div class="card-header-row">
                        <span class="card-title">🔄 Đang Đi Đều / Duy Trì</span>
                        <span class="card-pct green"><?= $stats['steady_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['momentum_steady'] ?></b>
                    <span class="card-sub">Điểm số giữ đều đặn (cần tiếp sức bứt phá)</span>
                </div>

                <div class="card stat-card trend-down">
                    <div class="card-header-row">
                        <span class="card-title">📉 Đang Sa Sút / Tuột Dốc</span>
                        <span class="card-pct trend-down"><?= $stats['down_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['momentum_down'] ?></b>
                    <span class="card-sub">Chuỗi điểm TX tụt dốc hoặc thi CK sút giảm</span>
                </div>

                <div class="card stat-card" style="border-left: 4px solid #64748b;">
                    <div class="card-header-row">
                        <span class="card-title">⏳ Đang Chờ Điểm Số</span>
                        <span class="card-pct" style="color: #64748b;"><?= $stats['pending_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['momentum_pending'] ?></b>
                    <span class="card-sub">Chưa có bài kiểm tra ghi nhận</span>
                </div>
            </section>
        </div>

        <!-- NHÓM 2: CÁC THẺ THỐNG KÊ KHUNG ĐIỂM SỐ & PHÂN VÙNG CẢNH BÁO -->
        <div style="margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                    📊 KHUNG 2: ĐIỂM SỐ & PHÂN VÙNG CẢNH BÁO (THÔNG TƯ 22)
                </span>
                <span class="muted small">Nguy cấp · Cần chú ý · Ổn định</span>
            </div>
            <section class="radar-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 16px;">
                <div class="card stat-card red">
                    <div class="card-header-row">
                        <span class="card-title">🔴 Vùng Nguy Cấp</span>
                        <span class="card-pct red"><?= $stats['red_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['red'] ?></b>
                    <span class="card-sub">ĐTB &lt; 3.5 hoặc ĐĐGck &lt; 3.0 (Cần can thiệp khẩn)</span>
                </div>

                <div class="card stat-card yellow">
                    <div class="card-header-row">
                        <span class="card-title">🟡 Vùng Cần Chú Ý</span>
                        <span class="card-pct yellow"><?= $stats['yellow_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['yellow'] ?></b>
                    <span class="card-sub">ĐTB 3.5 - 4.9 hoặc sa sút cuối kỳ (Theo dõi sát)</span>
                </div>

                <div class="card stat-card green">
                    <div class="card-header-row">
                        <span class="card-title">🟢 Vùng Ổn Định / An Toàn</span>
                        <span class="card-pct green"><?= $stats['green_pct'] ?>%</span>
                    </div>
                    <b><?= $stats['green'] ?></b>
                    <span class="card-sub">ĐTB &ge; 5.0 và không có dấu hiệu sa sút</span>
                </div>

                <div class="card stat-card" style="border-left: 4px solid #8b5cf6;">
                    <div class="card-header-row">
                        <span class="card-title">⭐ Giỏi / Khá (8.0+)</span>
                        <span class="card-pct" style="background: #f5f3ff; color: #6d28d9;">
                            <?= $stats['total'] > 0 ? round((($stats['tier_excellent'] + $stats['tier_good']) / $stats['total']) * 100, 1) : 0 ?>%
                        </span>
                    </div>
                    <b><?= $stats['tier_excellent'] + $stats['tier_good'] ?></b>
                    <span class="card-sub">Nhóm bồi dưỡng phát triển năng khiếu</span>
                </div>
            </section>
        </div>

        <!-- 2. THANH BỘ LỌC ĐA CHIỀU & TÌM KIẾM HỌC SINH -->
        <div class="filter-bar">
            <div class="chips-group" id="filterChips">
                <button type="button" class="chip active" data-filter="all">
                    <span>Tất cả (<?= $stats['total'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="trend:up">
                    <span>🚀 Tiến bộ/Bứt phá</span>
                    <span>(<?= $stats['momentum_up'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="trend:steady">
                    <span>🔄 Duy trì</span>
                    <span>(<?= $stats['momentum_steady'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="trend:down">
                    <span>📉 Sa sút/Tuột dốc</span>
                    <span>(<?= $stats['momentum_down'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="trend:pending">
                    <span>⏳ Chờ điểm</span>
                    <span>(<?= $stats['momentum_pending'] ?>)</span>
                </button>

                <span style="color: #cbd5e1; font-weight: 300;">|</span>

                <button type="button" class="chip" data-filter="tier:high">
                    <span>⭐ Giỏi/Khá</span>
                    <span>(<?= $stats['tier_excellent'] + $stats['tier_good'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="tier:average">
                    <span>⚖️ Trung bình</span>
                    <span>(<?= $stats['tier_average'] ?>)</span>
                </button>
                <button type="button" class="chip" data-filter="tier:low">
                    <span>⚠️ Dưới chuẩn</span>
                    <span>(<?= $stats['tier_low'] ?>)</span>
                </button>

                <span style="color: #cbd5e1; font-weight: 300;">|</span>

                <button type="button" class="chip chip-red" data-filter="red">
                    <span>🔴 Nguy cấp (<?= $stats['red'] ?>)</span>
                </button>
                <button type="button" class="chip chip-yellow" data-filter="yellow">
                    <span>🟡 Cần chú ý (<?= $stats['yellow'] ?>)</span>
                </button>
                <button type="button" class="chip chip-green" data-filter="green">
                    <span>🟢 Ổn định (<?= $stats['green'] ?>)</span>
                </button>
            </div>

            <div class="search-box-wrap" style="margin-left: auto;">
                <span class="search-icon">🔍</span>
                <input type="text" id="search" placeholder="Tìm theo tên hoặc mã học sinh...">
            </div>
        </div>

        <!-- 3. BẢNG RADAR 2 KHUNG ĐÁNH GIÁ -->
        <div class="radar-table-wrap">
            <table class="radar-table" id="radarTable">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">STT</th>
                        <th style="width: 90px;">Mã HS</th>
                        <th style="min-width: 160px;">Họ và Tên Học Sinh</th>
                        <th style="min-width: 130px;">Lớp & Môn</th>
                        <th style="min-width: 135px; background: #1e1b4b; color: #e0e7ff;">⚡ 1. HIỆU SUẤT</th>
                        <th style="min-width: 120px; background: #0f172a;">📊 2. KHUNG ĐIỂM</th>
                        <th style="width: 75px; text-align: center;">ĐTB</th>
                        <th style="width: 135px; text-align: center;">ĐĐGtx (1-4)</th>
                        <th style="width: 55px; text-align: center;">GK</th>
                        <th style="width: 55px; text-align: center;">CK</th>
                        <th style="width: 110px;">Vùng Rủi Ro</th>
                        <th style="min-width: 250px;">Năng Lực Tổng Thể & Khuyến Nghị Sư Phạm</th>
                        <th style="width: 105px; text-align: center;">Hành Động</th>
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
                            <td style="text-align: center; color: var(--text-muted); font-weight: 600;"><?= $stt++ ?></td>
                            <td>
                                <code class="mono-code" style="font-weight: 600; color: #4338ca;">
                                    <?= htmlspecialchars(!empty($s['student_code']) ? $s['student_code'] : 'HS' . $s['student_id']) ?>
                                </code>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-heading);"><?= htmlspecialchars($s['full_name']) ?></div>
                                <?php if (!empty($s['phone'])): ?>
                                    <div class="muted small">📞 <?= htmlspecialchars($s['phone']) ?></div>
                                <?php endif; ?>
                                <span class="badge-has-note js-note-badge" style="<?= empty($s['teacher_note']) ? 'display: none;' : '' ?>" title="<?= htmlspecialchars(mb_substr($s['teacher_note'] ?? '', 0, 80)) ?>...">📝 Có ghi chú GV</span>
                            </td>
                            <td>
                                <span class="badge-class">Lớp <?= htmlspecialchars($s['class_name'] ?? $cleanClassName) ?></span>
                                <span class="badge-subject">Môn <?= htmlspecialchars($s['subject'] ?? $guestData['subject']) ?></span>
                            </td>

                            <!-- KHUNG 1: HIỆU SUẤT (ĐỨNG TRƯỚC) -->
                            <td>
                                <span class="badge-momentum badge-momentum-<?= $s['momentum'] ?>" title="<?= htmlspecialchars($s['trend_description'] ?: $s['trend_label']) ?>">
                                    <?= $s['trend_icon'] ?> <?= htmlspecialchars($s['trend_label']) ?>
                                </span>
                            </td>

                            <!-- KHUNG 2: ĐIỂM SỐ -->
                            <td>
                                <span class="badge-tier <?= $s['score_tier']['class'] ?>" title="<?= htmlspecialchars($s['score_tier']['threshold']) ?>">
                                    <?= htmlspecialchars($s['score_tier']['badge']) ?>
                                </span>
                            </td>

                            <!-- ĐTB -->
                            <td style="text-align: center;">
                                <?php if ($s['avg_score'] !== null && $s['avg_score'] !== ''): ?>
                                    <span class="avg-score-badge avg-<?= $s['level'] ?>">
                                        <?= number_format((float)$s['avg_score'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="avg-score-badge avg-empty">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- ĐĐGtx (1-4) -->
                            <td style="text-align: center; font-size: 13px; font-family: var(--font-mono);">
                                <?php
                                $txFormatted = [];
                                for ($i = 1; $i <= 4; $i++) {
                                    $val = $s["tx$i"];
                                    if ($val !== null && $val !== '') {
                                        $num = (float)$val;
                                        $formatted = number_format($num, 1);
                                        if ($num < 3.5) {
                                            $txFormatted[] = '<span class="tx-val-crit" title="Điểm TX nguy cấp (' . $formatted . ')">' . $formatted . '</span>';
                                        } elseif ($num < 5.0) {
                                            $txFormatted[] = '<span class="tx-val-warn" title="Điểm TX dưới trung bình (' . $formatted . ')">' . $formatted . '</span>';
                                        } else {
                                            $txFormatted[] = '<span>' . $formatted . '</span>';
                                        }
                                    } else {
                                        $txFormatted[] = '<span class="muted">-</span>';
                                    }
                                }
                                echo implode(' · ', $txFormatted);
                                ?>
                            </td>

                            <td style="text-align: center; font-weight: 700; font-family: var(--font-mono); color: <?= ($s['gk'] !== null && $s['gk'] < 5.0) ? 'var(--danger)' : 'inherit' ?>;">
                                <?= ($s['gk'] !== null && $s['gk'] !== '') ? number_format((float)$s['gk'], 1) : '-' ?>
                            </td>
                            <td style="text-align: center; font-weight: 700; font-family: var(--font-mono); color: <?= ($s['ck'] !== null && $s['ck'] < 5.0) ? 'var(--danger)' : 'inherit' ?>;">
                                <?= ($s['ck'] !== null && $s['ck'] !== '') ? number_format((float)$s['ck'], 1) : '-' ?>
                            </td>

                            <!-- VÙNG RỦI RO -->
                            <td>
                                <?php if ($s['level'] === 'red'): ?>
                                    <span class="status-badge badge-red">🔴 Nguy cấp</span>
                                <?php elseif ($s['level'] === 'yellow'): ?>
                                    <span class="status-badge badge-yellow">🟡 Cần chú ý</span>
                                <?php elseif ($s['level'] === 'pending' || $s['momentum'] === 'pending'): ?>
                                    <span class="status-badge" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">⏳ Chờ điểm</span>
                                <?php else: ?>
                                    <span class="status-badge badge-green">🟢 Ổn định</span>
                                <?php endif; ?>
                            </td>

                            <!-- NĂNG LỰC TỔNG THỂ & KHUYẾN NGHỊ -->
                            <td>
                                <div class="competency-cell">
                                    <div class="competency-title">
                                        <?= htmlspecialchars($s['competency']['competency_title']) ?>
                                    </div>
                                    <div class="competency-advice">
                                        <?= htmlspecialchars($s['competency']['pedagogical_advice']) ?>
                                    </div>
                                    <?php if (!empty($s['diagnostic'])): ?>
                                        <div class="muted" style="font-size: 11px; margin-top: 2px;">
                                            🔍 <?= htmlspecialchars($s['diagnostic']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td style="text-align: center; white-space: nowrap;">
                                <button type="button" class="btn-open-detail" title="Xem năng lực học tập & In phiếu">
                                    👁️ Chi tiết
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="radarEmptyRow" class="radar-filter-empty-row" style="display: none;">
                        <td colspan="13" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Không tìm thấy học sinh nào phù hợp với bộ lọc hoặc từ khóa tìm kiếm.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- MODAL CHI TIẾT NĂNG LỰC HỌC TẬP & SỔ TAY NHẬN XÉT SƯ PHẠM (CHẾ ĐỘ KHÁCH) -->
        <div id="competencyDetailModal" class="competency-modal-overlay" style="display: none;">
            <div class="competency-modal-card" role="dialog" aria-modal="true" aria-labelledby="modalStudentName">
                <!-- Header -->
                <div class="competency-modal-header">
                    <div class="modal-header-profile">
                        <div class="modal-avatar" id="modalStudentAvatar">HS</div>
                        <div class="modal-name-group">
                            <h2 class="modal-student-name" id="modalStudentName">Họ và Tên Học Sinh</h2>
                            <div class="modal-meta-badges">
                                <span class="mono-code" id="modalStudentCode" style="font-weight: 700; color: #4f46e5;">HS0001</span>
                                <span style="color: #cbd5e1;">•</span>
                                <span class="badge-class" id="modalStudentClass">Lớp <?= htmlspecialchars($cleanClassName) ?></span>
                                <span class="badge-subject" id="modalStudentSubject">Môn Toán</span>
                                <span id="modalStudentBirthday" class="muted small"></span>
                                <span id="modalStudentPhone" class="muted small"></span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="modal-close-btn" id="modalCloseBtn" title="Đóng cửa sổ">✕</button>
                </div>

                <!-- Body -->
                <div class="competency-modal-body">
                    <!-- 3 Thẻ Trạng Thái: Khung Hiệu Suất (Đứng trước), Khung Điểm Số & Vùng Rủi Ro -->
                    <div class="modal-badges-grid">
                        <div class="modal-badge-card" style="border-left: 4px solid #4f46e5;">
                            <span class="modal-badge-label">⚡ 1. Khung Hiệu Suất (Đứng trước)</span>
                            <div class="modal-badge-val" id="modalMomentumBadge">📈 Đang Tiến Bộ</div>
                        </div>
                        <div class="modal-badge-card" style="border-left: 4px solid #0284c7;">
                            <span class="modal-badge-label">📊 2. Khung Điểm Số (Học lực)</span>
                            <div class="modal-badge-val" id="modalScoreTierBadge">⭐ Xuất Sắc</div>
                        </div>
                        <div class="modal-badge-card" style="border-left: 4px solid #f59e0b;">
                            <span class="modal-badge-label">🎯 3. Phân Vùng Rủi Ro TT22</span>
                            <div class="modal-badge-val" id="modalRiskBadge">🟢 Ổn định</div>
                        </div>
                    </div>

                    <!-- Hành trình điểm số -->
                    <div>
                        <div class="timeline-section-title">
                            <span>📈 Chuỗi Điểm Số & Động Năng Tiến Bộ</span>
                            <span class="muted small" style="text-transform: none; font-weight: 600;">Thông tư 22/2021/TT-BGDĐT</span>
                        </div>
                        <div class="score-timeline-chips" id="modalScoreTimeline">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>

                    <!-- DỰ BÁO ĐIỂM SỐ MỤC TIÊU & CHỈ SỐ TOÁN HỌC (OLS REGRESSION & TT22 INVERSE SOLVER) -->
                    <div class="apple-pedagogical-card" style="margin-top: 14px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 16px;">📐</span>
                                <span style="font-weight: 800; font-size: 13.5px; color: #1e293b;">Trọng Tâm Toán Học &amp; Dự Báo Điểm Mục Tiêu</span>
                                <span id="modalMathAcademicBadge" style="font-size: 11px; font-weight: 700; background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 9999px;">Học kỳ I</span>
                            </div>
                            <span class="mono-code" style="font-size: 11px; background: #e0e7ff; color: #4338ca; padding: 3px 8px; border-radius: 6px; font-weight: 700;">Hồi quy OLS &amp; Giải ngược TT22</span>
                        </div>

                        <!-- Slope and Stability Metrics -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 12px;">
                            <div style="background: #f8fafc; padding: 10px 12px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">Động năng xu hướng (Hệ số góc k)</div>
                                <div id="modalMathSlope" style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 3px;">k = +0.00</div>
                                <div id="modalMathSlopeSummary" style="font-size: 11.5px; color: #64748b; margin-top: 2px;">Chuỗi điểm đang phân tích...</div>
                            </div>
                            <div style="background: #f8fafc; padding: 10px 12px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">Độ ổn định phong độ (Độ lệch chuẩn σ)</div>
                                <div id="modalMathStability" style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 3px;">σ = 0.00</div>
                                <div id="modalMathStabilityDesc" style="font-size: 11.5px; color: #64748b; margin-top: 2px;">Mức độ phân tán phong độ</div>
                            </div>
                        </div>

                        <!-- Target CK Projection Cards -->
                        <div style="background: #f8fafc; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0;">
                            <div id="modalMathTargetPrompt" style="font-size: 11.5px; color: #334155; font-weight: 700; margin-bottom: 8px; text-transform: uppercase;">
                                🎯 Điểm Cuối Kỳ (CK) tối thiểu cần đạt để hoàn thành mục tiêu học kỳ:
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;" id="modalTargetCardsGrid">
                                <!-- Populated dynamically via JS -->
                            </div>
                            <div id="modalMathTargetAlert" style="margin-top: 10px; font-size: 12px; font-weight: 700; color: #b91c1c; display: none; padding: 8px 12px; background: #fef2f2; border-radius: 8px; border: 1px solid #fecaca; line-height: 1.5;"></div>
                        </div>
                    </div>

                    <!-- Chẩn đoán chuyên sâu -->
                    <div class="modal-info-box diagnostic-box">
                        <div class="modal-info-title" style="color: #4338ca;">
                            <span>🔍 Chẩn Đoán Chi Tiết Từ Hệ Thống Radar:</span>
                        </div>
                        <div class="modal-info-content" id="modalDiagnosticText">
                            Đang tải chẩn đoán...
                        </div>
                    </div>

                    <!-- Năng lực tổng thể & Khuyến nghị sư phạm -->
                    <div class="modal-info-box advice-box">
                        <div class="modal-info-title" style="color: #1d4ed8;">
                            <span>💡 Năng Lực Tổng Thể & Khuyến Nghị Hành Động Sư Phạm:</span>
                        </div>
                        <div class="modal-info-content">
                            <b id="modalCompetencyTitle" style="color: #1e3a8a; display: block; margin-bottom: 4px;">Tiêu đề năng lực</b>
                            <div id="modalPedagogicalAdvice">Nội dung khuyến nghị sư phạm hành động...</div>
                        </div>
                    </div>

                    <!-- Sổ tay ghi chú & Nhận xét sư phạm của giáo viên -->
                    <div class="teacher-note-section">
                        <div class="teacher-note-header">
                            <div>
                                <div class="teacher-note-title">
                                    <span>📝 Ghi Chú & Kế Hoạch Sư Phạm Cho Học Sinh Này</span>
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
                                ✓ Đã lưu ghi chú vào phiên làm việc
                            </span>
                            <div style="margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" class="btn-save-teacher-note" id="btnSaveGuestNote">
                                    <span>💾 Lưu Ghi Chú</span>
                                </button>
                                <a href="#" target="_blank" id="btnGuestPrintPdf" class="btn-action btn-print" style="padding: 7px 14px; border-radius: 8px; font-weight: 700; font-size: 12.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; background: #4f46e5; color: #ffffff;">
                                    🖨️ In Phiếu Phụ Đạo (PDF)
                                </a>
                                <a href="#" target="_blank" id="btnGuestExportXlsx" class="btn-action btn-excel" style="padding: 7px 14px; border-radius: 8px; font-weight: 700; font-size: 12.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; background: #10b981; color: #ffffff;">
                                    📥 Tải Phiếu Excel (.xlsx)
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
            var badgeColor = TYPE[ext] || '#4f46e5';
            var extLabel = (ext.slice(0, 4).toUpperCase() || 'FILE');

            li.innerHTML = '<span class="u032-ic" style="--c:' + badgeColor + '" aria-hidden="true">' + extLabel + '</span>' +
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

    // Kết nối nút Lưu ghi chú trong Chế độ Khách
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
            btnSaveGuestNote.innerHTML = '<span>⏳ Đang lưu...</span>';

            fetch('quet_nhanh.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnSaveGuestNote.disabled = false;
                btnSaveGuestNote.innerHTML = '<span>💾 Lưu Ghi Chú</span>';
                if (data.success) {
                    if (statusEl) {
                        statusEl.style.display = 'inline-flex';
                        statusEl.textContent = '✓ ' + data.message;
                    }
                    // Cập nhật DOM
                    const row = document.querySelector(`tr[data-student-idx="${currentIdx}"]`);
                    if (row) {
                        row.dataset.note = noteVal;
                        const badge = row.querySelector('.js-note-badge');
                        if (badge) {
                            if (noteVal) {
                                badge.style.display = 'inline-flex';
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
                btnSaveGuestNote.innerHTML = '<span>💾 Lưu Ghi Chú</span>';
                alert('Lỗi kết nối: ' + err.message);
            });
        });
    }
</script>
<?php
$extraScripts = '<script src="assets/js/main.js"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
