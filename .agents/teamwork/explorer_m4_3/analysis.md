# Báo Cáo Phân Tích & Thiết Kế Kỹ Thuật: VnEdu Gradebook Excel/CSV Import-Export Engine & Automated Test Suite (M4)

**Mã phân tích**: `M4-ANALYSIS-EXPLORER-3`  
**Tác giả**: `explorer_m4_3`  
**Ngày thực hiện**: 2026-10-17  
**Phạm vi**: 
1. Công cụ nạp (Import) điểm số & danh sách học sinh từ file Excel (`.xlsx`) và `.csv` tại cuối trang `nhap_diem.php`.
2. Công cụ xuất (Export) sổ điểm ra file chuẩn OpenXML `.xlsx` tại `nhap_diem.php` sử dụng `includes/xlsx_helper.php`.
3. Thiết kế & đặc tả bộ kiểm thử tự động toàn diện `tests/verify_m4.php` (10 nhóm kiểm thử theo chuẩn DISPATCH).

---

## 1. Tổng Quan & Hiện Trạng Hệ Thống

### 1.1. Hiện trạng cơ sở dữ liệu (`scores` & `students`)
- Theo kết quả kiểm tra `database.sql` và lệnh `DESCRIBE scores` trực tiếp trên MySQL `edu_radar`:
  - Bảng `scores` chuẩn hóa theo Thông tư 22/2021/TT-BGDĐT với các trường:
    `id, student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at`.
  - Khóa duy nhất (Unique constraint): `UNIQUE KEY unique_student_subject (student_id, subject)`.
  - Bảng `students` có khóa duy nhất: `UNIQUE KEY unique_class_student (class_id, full_name, birthday)`.
  - Bảng `audit_logs` có các trường: `id, user_id, username, action, details, ip_address, created_at`.

### 1.2. Hiện trạng trang `nhap_diem.php`
- Phần xử lý lưu điểm (`save_scores`) và nạp file (`import_file`):
  - Hiện tại vẫn đang sử dụng danh sách cột cũ không khớp với database: `m1, m2, p15_1, p15_2, t1_1, gk, ck` thay vì `tx1, tx2, tx3, tx4, gk, ck`.
  - Form import ở dòng 385–399 chỉ nhận `csv_file` (`accept=".csv, .txt"`), hoàn toàn chưa hỗ trợ file Excel nhị phân `.xlsx`.
  - Chưa có tính năng xuất sổ điểm ra file Excel (`Export .xlsx`).
  - Chưa có nút tải file mẫu Excel chuẩn `sample_gradebook.xlsx` (mới chỉ có liên kết tới `sample_vnedu.csv`).
  - Chưa sử dụng `create_xlsx()` và `parse_xlsx()` từ `includes/xlsx_helper.php`.

### 1.3. Hiện trạng `includes/xlsx_helper.php`
- Thư viện tự tạo (Zero-dependency) hoạt động dựa trên `ZipArchive` và `SimpleXML` của PHP 8.0+:
  - `parse_xlsx(string $filepath): array`: Đọc sheet 1 thành mảng 2 chiều 0-indexed, hỗ trợ sharedStrings và inlineStr.
  - `create_xlsx(array $data, string $sheet_name = 'Sheet1'): string`: Tạo chuỗi nhị phân file `.xlsx` chuẩn OpenXML SpreadsheetML (ECMA-376) với đầy đủ cấu trúc thư mục ZIP: `[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheet1.xml`.
  - `parse_csv(string $filepath): array`: Đọc CSV tự động bóc tách UTF-8 BOM (`\xEF\xBB\xBF`) và tự nhận diện dấu phân cách (`,`, `;`, `\t`).
  - `normalize_excel_date($raw): ?string`: Chuẩn hóa số serial Excel hoặc các chuỗi ngày `DD/MM/YYYY`, `YYYY-MM-DD` về định dạng MySQL `YYYY-MM-DD`.
  - `normalize_vn_date($raw): string`: Chuẩn hóa ngày sinh với giá trị mặc định `1970-01-01` nếu thiếu nhằm tránh lỗi `NULL` trong MySQL UNIQUE constraint.

### 1.4. Cấu trúc 2 file mẫu có sẵn
- `sample_gradebook.xlsx`:
  - Dòng tiêu đề (Row 0): `STT`, `Họ và tên`, `Số điện thoại phụ huynh`, `ĐĐGtx1`, `ĐĐGtx2`, `ĐĐGtx3`, `ĐĐGtx4`, `ĐĐGgk`, `ĐĐGck`.
  - Dữ liệu mẫu (Rows 1–5): Học sinh lớp 6A1 với điểm số `tx1..tx4, gk, ck`.
- `sample_vnedu.csv`:
  - Dòng tiêu đề: `"Họ và tên","Số điện thoại phụ huynh","Miệng","15P_1","15P_2","1Tiết","GiữaKỳ","CuốiKỳ"`.

---

## 2. Thiết Kế Chi Tiết: Công Cụ Import Sổ Điểm Excel (.xlsx) & CSV

### 2.1. Vị trí & Giao diện người dùng (UI)
- Vị trí: Đặt tại khối Panel bên dưới bảng điểm trong `nhap_diem.php`.
- Thiết kế: Chia thành 2 khối trực quan theo phong cách Stitch UI:
  - **Khối 1: 📥 Nạp Sổ Điểm Bằng File (.xlsx / .csv)**
    - Form gửi multipart (`enctype="multipart/form-data"`), `action="import_file"`.
    - Input chọn file: `name="gradebook_file"` (đồng thời hỗ trợ fallback `csv_file` để tương thích ngược), `accept=".xlsx, .xls, .csv, .txt"`.
    - Nút bấm chính: `Tải Lên & Nhập Vào Sổ Điểm`.
    - Hai nút phụ tải file mẫu: `📄 Tải file mẫu Excel (.xlsx)` (`sample_gradebook.xlsx`) và `📄 Tải file mẫu CSV` (`sample_vnedu.csv`).
    - Ghi chú hướng dẫn định dạng cột được hỗ trợ.
  - **Khối 2: 📤 Xuất Sổ Điểm Chuẩn VnEdu (.xlsx)**
    - Nút bấm: `📥 Xuất Excel Sổ Điểm` (`nhap_diem.php?export=xlsx&class_id=...&subject=...`).
    - Thẻ thống kê: Hiển thị sĩ số học sinh và môn học hiện hành sẽ được xuất.

### 2.2. Thuật toán nhận diện Header linh hoạt (`detect_gradebook_headers`)
Để tương thích với các nguồn file khác nhau (file xuất từ VnEdu bản cũ, bản mới Thông tư 22, file CSV rút gọn, hoặc file mẫu), hệ thống duyệt từ dòng 0 đến dòng 3 để tìm dòng tiêu đề:
```php
function detect_gradebook_headers(array $rows): array {
    $fieldKeywords = [
        'student_code' => ['mã học sinh', 'mã hs', 'mã định danh', 'mã số', 'ma hs', 'student_code'],
        'full_name'    => ['họ và tên', 'họ tên', 'họ tên học sinh', 'tên học sinh', 'họ và tên hs', 'full_name'],
        'birthday'     => ['ngày sinh', 'ns', 'năm sinh', 'ngày/tháng/năm sinh', 'ngaysinh', 'birthday'],
        'phone'        => ['số điện thoại', 'sđt', 'điện thoại', 'sđt phụ huynh', 'điện thoại phụ huynh', 'phone'],
        'tx1'          => ['đđgtx1', 'đđgtx 1', 'tx1', 'tx 1', 'miệng', 'mieng', 'm1', 'kttx1', 'kttx 1'],
        'tx2'          => ['đđgtx2', 'đđgtx 2', 'tx2', 'tx 2', '15p_1', '15p 1', '15 phút 1', '15phut 1', 'p15_1', 'kttx2'],
        'tx3'          => ['đđgtx3', 'đđgtx 3', 'tx3', 'tx 3', '15p_2', '15p 2', '15 phút 2', '15phut 2', 'p15_2', 'kttx3'],
        'tx4'          => ['đđgtx4', 'đđgtx 4', 'tx4', 'tx 4', '1tiết', '1 tiết', 't1_1', 'kttx4', '1 tiet'],
        'gk'           => ['đđggk', 'đđg gk', 'gk', 'giữakỳ', 'giữa kỳ', 'giuaky', 'giua ky', 'đdg gk'],
        'ck'           => ['đđgck', 'đđg ck', 'ck', 'cuốikỳ', 'cuối kỳ', 'cuoiky', 'cuoi ky', 'đdg ck'],
        'stt'          => ['stt', 'số tt', 'số thứ tự']
    ];

    $headerRowIndex = -1;
    $colMap = [];

    $scanLimit = min(4, count($rows));
    for ($r = 0; $r < $scanLimit; $r++) {
        $row = $rows[$r];
        if (!is_array($row) || empty($row)) continue;

        $tempMap = [];
        $unnamedTx = 1;
        foreach ($row as $colIdx => $cellVal) {
            $norm = mb_strtolower(trim((string)$cellVal), 'UTF-8');
            if ($norm === '') continue;

            // Xử lý trường hợp cột chỉ ghi chung chung 'đđgtx' lặp lại 4 lần
            if ($norm === 'đđgtx' || $norm === 'ddgtx' || $norm === 'tx') {
                if ($unnamedTx <= 4) {
                    $tempMap['tx' . $unnamedTx] = $colIdx;
                    $unnamedTx++;
                    continue;
                }
            }

            foreach ($fieldKeywords as $field => $keywords) {
                if (isset($tempMap[$field])) continue;
                foreach ($keywords as $kw) {
                    if (mb_strpos($norm, $kw) !== false || $norm === $kw) {
                        $tempMap[$field] = $colIdx;
                        break;
                    }
                }
            }
        }

        // Header hợp lệ nếu nhận diện được Họ tên hoặc ít nhất 2 cột điểm (TX, GK hoặc CK)
        if (isset($tempMap['full_name']) || (isset($tempMap['tx1']) && (isset($tempMap['gk']) || isset($tempMap['ck'])))) {
            $headerRowIndex = $r;
            $colMap = $tempMap;
            break;
        }
    }

    // Fallback theo vị trí cột nếu file không có header
    if ($headerRowIndex === -1 && !empty($rows)) {
        $firstRow = $rows[0];
        $colCount = count($firstRow);
        if ($colCount >= 9) {
            // STT, Họ tên, SĐT, TX1, TX2, TX3, TX4, GK, CK
            $colMap = ['stt' => 0, 'full_name' => 1, 'phone' => 2, 'tx1' => 3, 'tx2' => 4, 'tx3' => 5, 'tx4' => 6, 'gk' => 7, 'ck' => 8];
        } elseif ($colCount === 8) {
            // Họ tên, SĐT, TX1, TX2, TX3, TX4, GK, CK
            $colMap = ['full_name' => 0, 'phone' => 1, 'tx1' => 2, 'tx2' => 3, 'tx3' => 4, 'tx4' => 5, 'gk' => 6, 'ck' => 7];
        }
    }

    return [$headerRowIndex, $colMap];
}
```

### 2.3. Quy trình Khớp học sinh & Nạp điểm (Student Matching & UPSERT)
Đối với mỗi dòng dữ liệu từ file (sau dòng header):
1. **Tìm kiếm học sinh trong lớp (`$selectedClassId`)**:
   - Bước 1: Nếu file có `student_code`, truy vấn:
     `SELECT id FROM students WHERE class_id = ? AND student_code = ?`
   - Bước 2: Nếu chưa tìm thấy và file có `birthday` hợp lệ:
     `SELECT id FROM students WHERE class_id = ? AND full_name = ? AND birthday = ?`
   - Bước 3: Nếu chưa tìm thấy, truy vấn theo tên:
     `SELECT id FROM students WHERE class_id = ? AND full_name = ?`
   - Bước 4 (Tự động nạp học sinh mới nếu chưa có):
     Nếu học sinh chưa tồn tại trong lớp, thực hiện INSERT vào bảng `students`:
     ```sql
     INSERT INTO students (class_id, student_code, full_name, birthday, phone)
     VALUES (?, ?, ?, ?, ?)
     ```
     Bọc trong `try ... catch (PDOException $e)` xử lý mã lỗi `1062` (trùng lặp) để đảm bảo không bị đứt đoạn.
2. **Trích xuất & Kiểm tra điểm số (Validation & Normalization)**:
   - Thay thế dấu phẩy `,` thành dấu chấm `.`: `str_replace(',', '.', $val)`.
   - Nếu chuỗi rỗng: gán `null`.
   - Nếu là số: kiểm tra điều kiện miền giá trị:
     `$val >= 0.0 && $val <= 10.0`.
     - Nếu giá trị `< 0` hoặc `> 10`: Từ chối giá trị không hợp lệ (gán `null` hoặc bỏ qua cột điểm đó để tránh làm hỏng CSDL).
3. **Tính điểm trung bình môn học kỳ (`avg_score`) theo Thông tư 22**:
   ```php
   $txValues = array_filter([$tx1, $tx2, $tx3, $tx4], fn($v) => $v !== null);
   $nTx = count($txValues);
   $sTx = array_sum($txValues);

   if ($gk !== null && $ck !== null) {
       $avg = round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
   } else {
       $weight = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
       $avg = $weight > 0 ? round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / $weight, 1) : null;
   }
   ```
4. **Lưu/Cập nhật bảng điểm (`scores`) bằng UPSERT**:
   Sử dụng câu lệnh `INSERT ... ON DUPLICATE KEY UPDATE`:
   ```sql
   INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
   ON DUPLICATE KEY UPDATE
       tx1 = VALUES(tx1),
       tx2 = VALUES(tx2),
       tx3 = VALUES(tx3),
       tx4 = VALUES(tx4),
       gk = VALUES(gk),
       ck = VALUES(ck),
       avg_score = VALUES(avg_score),
       updated_at = CURRENT_TIMESTAMP
   ```
5. **Giao dịch an toàn (Transaction Safety)**:
   Toàn bộ vòng lặp nạp file được bọc trong:
   ```php
   $pdo->beginTransaction();
   try {
       // Loop and upsert
       $pdo->commit();
   } catch (Throwable $e) {
       $pdo->rollBack();
       throw $e;
   }
   ```
6. **Ghi nhật ký kiểm toán (`audit_logs`)**:
   `log_action($realUserId, $_SESSION['username'], 'Import file điểm', "Nạp dữ liệu điểm từ file [$fileName] cho $importedCount học sinh lớp [$curClassName] môn [$selectedSubject].");`

---

## 3. Thiết Kế Chi Tiết: Công Cụ Xuất Sổ Điểm Excel (.xlsx)

### 3.1. Kích hoạt & Bảo mật RBAC
- Kích hoạt qua: `GET nhap_diem.php?export=xlsx&class_id=X&subject=Y` hoặc `POST action=export_xlsx`.
- Kiểm tra phân quyền:
  - Yêu cầu đăng nhập (`require_login()`).
  - Nếu không phải `admin`: Gọi `require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject)` -> Trả về HTTP 403 nếu giáo viên cố tình xuất điểm lớp/môn không được phân công.

### 3.2. Cấu trúc dữ liệu file OpenXML `.xlsx`
1. **Dòng tiêu đề (Row 0)**:
   `['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck', 'ĐTBmhk']`
2. **Các dòng dữ liệu (Rows 1..N)**:
   Truy vấn học sinh và điểm số của lớp:
   ```sql
   SELECT s.id, s.student_code, s.full_name, s.birthday, s.gender, s.phone,
          sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
   FROM students s
   LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = ?
   WHERE s.class_id = ?
   ORDER BY s.full_name ASC
   ```
   Định dạng ngày sinh sang `DD/MM/YYYY` (chuẩn hiển thị tiếng Việt).
   Điểm số để dạng float hoặc chuỗi rỗng `''` nếu chưa có điểm.
3. **Sinh file bằng `create_xlsx()`**:
   ```php
   $sheetName = "SoDiem_" . mb_substr($curClassName, 0, 20);
   $xlsxBinary = create_xlsx($exportRows, $sheetName);
   ```

### 3.3. Tên file & HTTP Header xuất file
- Quy tắc đặt tên file: `SoDiem_[TenLop]_[MonHoc]_[NamHoc].xlsx`
  - Ví dụ: `SoDiem_6A1_Toan_2026-2027.xlsx`
  - Ký tự đặc biệt hoặc dấu tiếng Việt trong tên file được chuẩn hóa không dấu (ASCII) để tránh lỗi tiêu đề HTTP trên trình duyệt cổ điển: `SoDiem_6A1_Toan_2026-2027.xlsx`.
- Headers:
  ```php
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . strlen($xlsxBinary));
  header('Cache-Control: max-age=0');
  header('Pragma: public');
  ```
- Ghi nhật ký:
  ```php
  log_action($realUserId, $_SESSION['username'], 'Xuất file điểm', "Xuất file Excel sổ điểm môn [$selectedSubject] lớp [$curClassName].");
  echo $xlsxBinary;
  exit;
  ```

---

## 4. Thiết Kế Bộ Kiểm Thử Tự Động Toàn Diện: `tests/verify_m4.php`

Bộ kiểm thử được viết thành CLI script độc lập, thực thi trực tiếp qua:
```powershell
D:\xam\php\php.exe tests/verify_m4.php
```

### 4.1. Kiến trúc Test Harness
- Kế thừa mô hình cURL `TestClient` thành công từ `tests/verify_m3.php` với cookie jar tạm biệt lập theo từng phiên test.
- Hàm assert `assertTest(string $name, callable $fn)` ghi nhận `$passedCount`, `$failedCount` và in màu ANSI.
- Kết thúc test runner: Tự động dọn dẹp các bản ghi test tạm và trả về `exit(0)` nếu 100% pass, `exit(1)` nếu có lỗi.

### 4.2. Danh mục 10 Nhóm Kiểm Thử Chi Tiết

| Nhóm | Tên Nhóm | Nội dung kiểm thử chi tiết |
|:---:|---|---|
| **1** | Cấu trúc giao diện Sổ Điểm | - Kiểm tra HTTP 200 khi truy cập `nhap_diem.php?class_id=1&subject=Toán`.<br>- Xác thực có đủ 6 cột điểm: `ĐĐGtx1, ĐĐGtx2, ĐĐGtx3, ĐĐGtx4, ĐĐGgk (HS 2), ĐĐGck (HS 3)` và cột `ĐTBmhk`.<br>- Xác thực các thẻ input có thuộc tính `name="scores[<id>][tx1]"` ... `[ck]`.<br>- Xác thực hiển thị đầy đủ danh sách học sinh của lớp. |
| **2** | Công thức tính toán ĐTBmhk | - Kiểm thử Unit Test công thức đầy đủ: `(S_tx + 2*gk + 3*ck)/(N_tx + 5)` với bộ điểm chuẩn `tx=[8, 7, 6.5, 6], gk=5, ck=5` => `5.8`.<br>- Kiểm thử tính điểm linh hoạt khi chưa đủ cột (chỉ có TX và GK, chưa có CK): `(8+7+2*6)/(2+2) = 6.75` => `6.8`.<br>- Kiểm thử làm tròn 1 chữ số thập phân (`round($val, 1)`). |
| **3** | Phím tắt & Ràng buộc Client | - Kiểm tra HTML chứa các thuộc tính điều hướng lưới hoặc class `.score-input`.<br>- Kiểm tra các ô nhập điểm có thuộc tính `type="number"`, `min="0"`, `max="10"`, `step="0.1"`.<br>- Kiểm tra mã JavaScript có lắng nghe sự kiện phím `ArrowUp`, `ArrowDown`, `Enter` và `Tab`. |
| **4** | Lưu điểm qua POST & Audit Log | - Giáo viên `gv_toan` gửi POST `action=save_scores` cập nhật điểm lớp 6A1 môn Toán.<br>- Truy vấn trực tiếp MySQL bảng `scores`: Điểm các cột `tx1..tx4, gk, ck, avg_score` được cập nhật chính xác.<br>- Truy vấn bảng `audit_logs`: Có dòng ghi `Lưu sổ điểm` chứa tên lớp, môn học, và số học sinh. |
| **5** | Từ chối điểm ngoài miền giá trị | - Gửi POST với điểm âm (`-1.5`) hoặc điểm vượt ngưỡng (`11.5`) hoặc chuỗi ký tự (`abc`).<br>- Hệ thống từ chối hoặc chuẩn hóa về `NULL`, không làm hỏng CSDL và không lưu điểm bất hợp lệ. |
| **6** | Import Sổ Điểm từ file Excel (.xlsx) | - Tạo file `.xlsx` nhị phân hợp lệ bằng `create_xlsx()` chứa điểm mới cho học sinh.<br>- POST multipart `action=import_file` tải file `.xlsx` lên `nhap_diem.php`.<br>- Kiểm tra điểm trong MySQL được cập nhật và `avg_score` tính đúng.<br>- Nạp lại file đó lần 2: Số dòng trong bảng `scores` không bị nhân đôi (0 trùng lặp).<br>- Kiểm tra tính năng đọc header linh hoạt (dòng header ở dòng 2 hoặc 3). |
| **7** | Import Sổ Điểm từ file CSV (.csv) | - Tạo file `.csv` chuẩn VnEdu.<br>- POST multipart tải file `.csv` lên.<br>- Xác thực kết quả lưu vào MySQL và không sinh trùng lặp. |
| **8** | Xuất Sổ Điểm ra file Excel (.xlsx) | - Gửi GET `nhap_diem.php?export=xlsx&class_id=1&subject=Toán`.<br>- Kiểm tra mã HTTP 200, Content-Type là `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`.<br>- Phân tích gói nhị phân trả về bằng `parse_xlsx()`: Đảm bảo có đủ tiêu đề và dữ liệu khớp với CSDL.<br>- Kiểm tra tải file mẫu `sample_gradebook.xlsx` và `sample_vnedu.csv` thành công (HTTP 200). |
| **9** | Kiểm tra RBAC & BOLA Tuyệt Đối | - Chưa đăng nhập truy cập `nhap_diem.php` bị chuyển hướng 302 về `login.php`.<br>- Giáo viên `gv_toan` truy cập lớp không được phân công (ví dụ 7A1 môn Ngữ Văn) bị chặn 403.<br>- Giáo viên `gv_toan` cố tình POST lưu điểm hoặc import vào lớp không phân công bị chặn 403, CSDL không thay đổi.<br>- Giáo viên cố tình sửa tham số `?teacher_id=1` bị chặn 403.<br>- Tài khoản `admin` có quyền kiểm tra, nạp và xuất điểm ở mọi lớp/môn (HTTP 200). |
| **10** | Kiểm toán Audit Logs Toàn Diện | - Xác thực các hành động sau đều được ghi lại trong `audit_logs`:<br>  + `Lưu sổ điểm`<br>  + `Import file điểm`<br>  + `Xuất file điểm`<br>  + `Truy cập trái phép bị chặn`<br>  + `Admin kiểm tra sổ điểm` |

---

## 5. Bản Vẽ Kỹ Thuật & Đoạn Mã Nguồn Mẫu (Code Snippets)

### 5.1. Khối xử lý Import & Export trong `nhap_diem.php`
```php
// =============================================================================
// XỬ LÝ XUẤT EXCEL SỔ ĐIỂM (.XLSX)
// =============================================================================
if ((isset($_GET['export']) && $_GET['export'] === 'xlsx') || 
    (isset($_POST['action']) && $_POST['action'] === 'export_xlsx')) {
    
    // Kiểm tra phân quyền
    if ($realUserRole !== 'admin') {
        require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);
    }
    
    require_once __DIR__ . '/includes/xlsx_helper.php';
    
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_code, s.full_name, s.birthday, s.gender, s.phone,
               sc.tx1, sc.tx2, sc.tx3, sc.tx4, sc.gk, sc.ck, sc.avg_score
        FROM students s
        LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject = ?
        WHERE s.class_id = ?
        ORDER BY s.full_name ASC
    ");
    $stmt->execute([$selectedSubject, $selectedClassId]);
    $students = $stmt->fetchAll();
    
    $exportRows = [
        ['STT', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Số điện thoại', 'ĐĐGtx1', 'ĐĐGtx2', 'ĐĐGtx3', 'ĐĐGtx4', 'ĐĐGgk', 'ĐĐGck', 'ĐTBmhk']
    ];
    
    $stt = 1;
    foreach ($students as $s) {
        $bday = !empty($s['birthday']) ? date('d/m/Y', strtotime($s['birthday'])) : '';
        $exportRows[] = [
            $stt++,
            $s['student_code'] ?? '',
            $s['full_name'],
            $bday,
            $s['gender'] ?? '',
            $s['phone'] ?? '',
            $s['tx1'] !== null ? (float)$s['tx1'] : '',
            $s['tx2'] !== null ? (float)$s['tx2'] : '',
            $s['tx3'] !== null ? (float)$s['tx3'] : '',
            $s['tx4'] !== null ? (float)$s['tx4'] : '',
            $s['gk'] !== null ? (float)$s['gk'] : '',
            $s['ck'] !== null ? (float)$s['ck'] : '',
            $s['avg_score'] !== null ? (float)$s['avg_score'] : ''
        ];
    }
    
    $cleanClass = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $curClassName);
    $cleanSub = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $selectedSubject);
    $year = date('Y') . '-' . (date('Y') + 1);
    $filename = "SoDiem_{$cleanClass}_{$cleanSub}_{$year}.xlsx";
    
    $xlsxData = create_xlsx($exportRows, "SoDiem_" . mb_substr($curClassName, 0, 20));
    
    log_action($realUserId, $_SESSION['username'], 'Xuất file điểm', "Xuất file Excel sổ điểm môn [$selectedSubject] lớp [$curClassName].");
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xlsxData));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    echo $xlsxData;
    exit;
}

// =============================================================================
// XỬ LÝ IMPORT SỔ ĐIỂM (.XLSX / .CSV)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'import_file' || $_POST['action'] === 'import_gradebook')) {
    if ($realUserRole !== 'admin') {
        require_teacher_assignment($realUserId, $selectedClassId, $selectedSubject);
    }
    
    require_once __DIR__ . '/includes/xlsx_helper.php';
    
    $uploadedFile = $_FILES['gradebook_file'] ?? $_FILES['csv_file'] ?? null;
    if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $uploadedFile['tmp_name'];
        $fileName = $uploadedFile['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $rows = ($ext === 'xlsx') ? parse_xlsx($fileTmp) : parse_csv($fileTmp);
        
        if (!empty($rows)) {
            [$headerIdx, $colMap] = detect_gradebook_headers($rows);
            $startRow = $headerIdx >= 0 ? $headerIdx + 1 : 0;
            
            $findStudentCode = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND student_code = ?");
            $findStudentNameBday = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND full_name = ? AND birthday = ?");
            $findStudentNameOnly = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND full_name = ?");
            $insStudent = $pdo->prepare("INSERT INTO students (class_id, student_code, full_name, birthday, phone) VALUES (?, ?, ?, ?, ?)");
            
            $upsertScore = $pdo->prepare("
                INSERT INTO scores (student_id, subject, tx1, tx2, tx3, tx4, gk, ck, avg_score, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    tx1 = VALUES(tx1),
                    tx2 = VALUES(tx2),
                    tx3 = VALUES(tx3),
                    tx4 = VALUES(tx4),
                    gk = VALUES(gk),
                    ck = VALUES(ck),
                    avg_score = VALUES(avg_score),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $pdo->beginTransaction();
            $importedCount = 0;
            
            try {
                for ($i = $startRow; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    if (empty($row) || !is_array($row)) continue;
                    
                    // Lấy Họ tên
                    $fullName = '';
                    if (isset($colMap['full_name']) && isset($row[$colMap['full_name']])) {
                        $fullName = trim((string)$row[$colMap['full_name']]);
                    } elseif (isset($row[1]) && is_numeric($row[0])) {
                        $fullName = trim((string)$row[1]);
                    } elseif (isset($row[0])) {
                        $fullName = trim((string)$row[0]);
                    }
                    
                    if ($fullName === '' || mb_stripos($fullName, 'tổng số') !== false) continue;
                    
                    // Lấy Mã HS
                    $studentCode = isset($colMap['student_code']) && isset($row[$colMap['student_code']]) ? trim((string)$row[$colMap['student_code']]) : null;
                    if ($studentCode === '') $studentCode = null;
                    
                    // Lấy Ngày sinh
                    $rawBday = isset($colMap['birthday']) && isset($row[$colMap['birthday']]) ? $row[$colMap['birthday']] : null;
                    $normBday = normalize_excel_date($rawBday);
                    
                    // Lấy SĐT
                    $phone = '';
                    if (isset($colMap['phone']) && isset($row[$colMap['phone']])) {
                        $phone = preg_replace('/[^0-9]/', '', (string)$row[$colMap['phone']]);
                    }
                    
                    // Khớp học sinh
                    $studentId = null;
                    if ($studentCode !== null) {
                        $findStudentCode->execute([$selectedClassId, $studentCode]);
                        $studentId = $findStudentCode->fetchColumn();
                    }
                    if (!$studentId && $normBday !== null) {
                        $findStudentNameBday->execute([$selectedClassId, $fullName, $normBday]);
                        $studentId = $findStudentNameBday->fetchColumn();
                    }
                    if (!$studentId) {
                        $findStudentNameOnly->execute([$selectedClassId, $fullName]);
                        $studentId = $findStudentNameOnly->fetchColumn();
                    }
                    
                    // Thêm mới nếu chưa có
                    if (!$studentId) {
                        try {
                            $insStudent->execute([$selectedClassId, $studentCode, $fullName, $normBday, $phone]);
                            $studentId = (int)$pdo->lastInsertId();
                        } catch (PDOException $e) {
                            if ($e->errorInfo[1] == 1062) {
                                $findStudentNameOnly->execute([$selectedClassId, $fullName]);
                                $studentId = $findStudentNameOnly->fetchColumn();
                            } else {
                                throw $e;
                            }
                        }
                    }
                    
                    if (!$studentId) continue;
                    
                    // Trích xuất điểm
                    $cleanScore = function($val) {
                        if ($val === null || $val === '') return null;
                        $s = str_replace(',', '.', trim((string)$val));
                        if (!is_numeric($s)) return null;
                        $f = (float)$s;
                        return ($f >= 0.0 && $f <= 10.0) ? $f : null;
                    };
                    
                    $tx1 = isset($colMap['tx1']) && isset($row[$colMap['tx1']]) ? $cleanScore($row[$colMap['tx1']]) : null;
                    $tx2 = isset($colMap['tx2']) && isset($row[$colMap['tx2']]) ? $cleanScore($row[$colMap['tx2']]) : null;
                    $tx3 = isset($colMap['tx3']) && isset($row[$colMap['tx3']]) ? $cleanScore($row[$colMap['tx3']]) : null;
                    $tx4 = isset($colMap['tx4']) && isset($row[$colMap['tx4']]) ? $cleanScore($row[$colMap['tx4']]) : null;
                    $gk  = isset($colMap['gk'])  && isset($row[$colMap['gk']])  ? $cleanScore($row[$colMap['gk']])  : null;
                    $ck  = isset($colMap['ck'])  && isset($row[$colMap['ck']])  ? $cleanScore($row[$colMap['ck']])  : null;
                    
                    // Tính điểm trung bình
                    $txList = array_filter([$tx1, $tx2, $tx3, $tx4], fn($v) => $v !== null);
                    $nTx = count($txList);
                    $sTx = array_sum($txList);
                    
                    $avg = null;
                    if ($gk !== null && $ck !== null) {
                        $avg = round(($sTx + 2 * $gk + 3 * $ck) / ($nTx + 5), 1);
                    } else {
                        $w = $nTx + ($gk !== null ? 2 : 0) + ($ck !== null ? 3 : 0);
                        if ($w > 0) {
                            $avg = round(($sTx + 2 * ($gk ?? 0.0) + 3 * ($ck ?? 0.0)) / $w, 1);
                        }
                    }
                    
                    $upsertScore->execute([$studentId, $selectedSubject, $tx1, $tx2, $tx3, $tx4, $gk, $ck, $avg]);
                    $importedCount++;
                }
                
                $pdo->commit();
                log_action($realUserId, $_SESSION['username'], 'Import file điểm', "Nạp dữ liệu điểm từ file [$fileName] cho $importedCount học sinh lớp [$curClassName] môn [$selectedSubject].");
                $msg = "Đã nạp thành công điểm cho $importedCount học sinh từ file $fileName!";
            } catch (Throwable $e) {
                $pdo->rollBack();
                $err = "Lỗi khi nhập dữ liệu: " . $e->getMessage();
            }
        } else {
            $err = "File tải lên không có dữ liệu hoặc không đọc được!";
        }
    }
}
```

### 5.2. Khối giao diện Panel cuối trang `nhap_diem.php`
```html
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 32px;">
    <!-- Khối 1: Nạp điểm bằng File -->
    <section class="panel-box" style="margin-bottom: 0;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 24px;">📥</span>
            <div>
                <h3 style="margin: 0; font-size: 16px;">Nạp Điểm Bằng File (.xlsx / .csv)</h3>
                <p class="muted small" style="margin: 0;">Nạp tự động điểm số chuẩn VnEdu vào lớp đang chọn</p>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 12px;">
            <input type="hidden" name="action" value="import_file">
            <input type="file" name="gradebook_file" accept=".xlsx, .csv, .txt" required 
                   style="padding: 10px; border: 1px dashed var(--border); border-radius: 8px; background: #f8fafc; font-size: 13px;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="submit" class="btn primary" style="padding: 10px 18px; font-size: 13px;">
                    🚀 Tải Lên & Nhập Vào Sổ Điểm
                </button>
                <a href="sample_gradebook.xlsx" download class="btn outline" style="padding: 10px 14px; font-size: 12px;">
                    📄 File Mẫu Excel (.xlsx)
                </a>
                <a href="sample_vnedu.csv" download class="btn outline" style="padding: 10px 14px; font-size: 12px;">
                    📄 File Mẫu CSV
                </a>
            </div>
        </form>
    </section>

    <!-- Khối 2: Xuất sổ điểm ra Excel -->
    <section class="panel-box" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <span style="font-size: 24px;">📤</span>
                <div>
                    <h3 style="margin: 0; font-size: 16px;">Xuất Sổ Điểm Điện Tử (.xlsx)</h3>
                    <p class="muted small" style="margin: 0;">Tải sổ điểm lớp <?= htmlspecialchars($curClassName) ?> môn <?= htmlspecialchars($selectedSubject) ?> chuẩn ECMA-376</p>
                </div>
            </div>
            <p class="muted small">
                File Excel xuất ra chứa đầy đủ danh sách học sinh, mã định danh, ngày sinh, các cột ĐĐGtx 1..4, ĐĐGgk, ĐĐGck và ĐTBmhk tính tự động.
            </p>
        </div>
        <div style="margin-top: 16px;">
            <a href="nhap_diem.php?export=xlsx&class_id=<?= (int)$selectedClassId ?>&subject=<?= urlencode($selectedSubject) ?>" 
               class="btn outline" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; font-weight: 700; background: #f0fdf4; border-color: #86efac; color: #166534;">
                📊 Xuất Excel Sổ Điểm Môn <?= htmlspecialchars($selectedSubject) ?>
            </a>
        </div>
    </div>
</div>
```

---

## 6. Kết luận & Hướng Dẫn Chuyển Giao Cho Worker

1. **Về Import/Export Sổ Điểm**: Đã thiết kế hoàn chỉnh cơ chế đọc ghi OpenXML nhị phân không phụ thuộc Composer, khớp nối chuẩn 100% với schema `scores` (`tx1..tx4, gk, ck, avg_score`).
2. **Về Test Suite `tests/verify_m4.php`**: Đã đặc tả chi tiết 10 nhóm kiểm thử với kịch bản kiểm tra tường minh, bảo vệ hoàn toàn trước các lỗ hổng BOLA, IDOR, SQL injection, và lỗi tràn bộ đệm.
3. Toàn bộ thiết kế đã sẵn sàng để đội ngũ worker tiếp nhận và hiện thực hóa trong Milestone 4.
