-- =========================================================================
-- HỆ THỐNG QUẢN LÝ ĐIỂM & CẢNH BÁO SỚM HỌC SINH THCS (EDURADAR)
-- Chuẩn quy chế đánh giá học sinh THCS (Thông tư 22/2021/TT-BGDĐT & VnEdu)
-- Hệ cơ sở dữ liệu: MySQL 8.0 / MariaDB 10.4 (XAMPP Port 3306)
-- =========================================================================

CREATE DATABASE IF NOT EXISTS edu_radar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edu_radar;

-- Tắt kiểm tra khóa ngoại tạm thời để xóa và tái tạo bảng an toàn
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS scores;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teacher_classes;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS attendance;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------------------
-- 1. BẢNG TÀI KHOẢN (users)
-- -------------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 2. BẢNG LỚP HỌC (classes)
-- -------------------------------------------------------------------------
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    grade INT NOT NULL,
    section VARCHAR(10) NOT NULL DEFAULT 'A',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_name (name),
    KEY idx_grade (grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 3. BẢNG PHÂN CÔNG GIẢNG DẠY (teacher_classes)
-- -------------------------------------------------------------------------
CREATE TABLE teacher_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    subject VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (teacher_id, class_id, subject),
    KEY idx_teacher_id (teacher_id),
    KEY idx_class_id (class_id),
    CONSTRAINT fk_tc_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 4. BẢNG HỌC SINH (students)
-- -------------------------------------------------------------------------
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_code VARCHAR(50) DEFAULT NULL,
    full_name VARCHAR(100) NOT NULL,
    birthday DATE DEFAULT NULL,
    gender VARCHAR(10) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_student (class_id, full_name, birthday),
    KEY idx_class_id (class_id),
    KEY idx_student_code (student_code),
    CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 5. BẢNG ĐIỂM SỐ CHUẨN VNEDU THÔNG TƯ 22 (scores)
-- -------------------------------------------------------------------------
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject VARCHAR(50) NOT NULL,
    tx1 DECIMAL(4,2) DEFAULT NULL,
    tx2 DECIMAL(4,2) DEFAULT NULL,
    tx3 DECIMAL(4,2) DEFAULT NULL,
    tx4 DECIMAL(4,2) DEFAULT NULL,
    gk DECIMAL(4,2) DEFAULT NULL,
    ck DECIMAL(4,2) DEFAULT NULL,
    avg_score DECIMAL(4,2) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_subject (student_id, subject),
    KEY idx_student_id (student_id),
    CONSTRAINT fk_scores_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 6. BẢNG NHẬT KÝ HỆ THỐNG (audit_logs)
-- -------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(50) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_action (action),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- DỮ LIỆU KHỞI TẠO TEST CHUẨN (SEED DATA)
-- Mật khẩu mặc định: '123456'
-- Bcrypt Hash: $2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG
-- =========================================================================

-- 1. Tài khoản mẫu
INSERT INTO users (id, username, password, full_name, role) VALUES 
(1, 'admin', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Quản Trị Viên Trường', 'admin'),
(2, 'gv_toan', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Thầy Nguyễn Văn Giáo', 'teacher'),
(3, 'gv_van', '$2y$10$Huov5BMeiGHCxhgfaTkauuthlWkvzJguIrvV6vWU1SQsJDrk6PlMG', 'Cô Lê Thị Hương', 'teacher');

-- 2. Danh sách 36 lớp học chuẩn THCS (Khối 6-9, Ban A-C, Lớp 1-3)
INSERT INTO classes (id, name, grade, section) VALUES
-- Khối 6 (9 lớp)
(1, '6A1', 6, 'A'), (2, '6A2', 6, 'A'), (3, '6A3', 6, 'A'),
(4, '6B1', 6, 'B'), (5, '6B2', 6, 'B'), (6, '6B3', 6, 'B'),
(7, '6C1', 6, 'C'), (8, '6C2', 6, 'C'), (9, '6C3', 6, 'C'),
-- Khối 7 (9 lớp)
(10, '7A1', 7, 'A'), (11, '7A2', 7, 'A'), (12, '7A3', 7, 'A'),
(13, '7B1', 7, 'B'), (14, '7B2', 7, 'B'), (15, '7B3', 7, 'B'),
(16, '7C1', 7, 'C'), (17, '7C2', 7, 'C'), (18, '7C3', 7, 'C'),
-- Khối 8 (9 lớp)
(19, '8A1', 8, 'A'), (20, '8A2', 8, 'A'), (21, '8A3', 8, 'A'),
(22, '8B1', 8, 'B'), (23, '8B2', 8, 'B'), (24, '8B3', 8, 'B'),
(25, '8C1', 8, 'C'), (26, '8C2', 8, 'C'), (27, '8C3', 8, 'C'),
-- Khối 9 (9 lớp)
(28, '9A1', 9, 'A'), (29, '9A2', 9, 'A'), (30, '9A3', 9, 'A'),
(31, '9B1', 9, 'B'), (32, '9B2', 9, 'B'), (33, '9B3', 9, 'B'),
(34, '9C1', 9, 'C'), (35, '9C2', 9, 'C'), (36, '9C3', 9, 'C'),
-- Lớp thực tế từ file VnEdu
(37, '7A6', 7, 'A');

-- 3. Phân công giảng dạy
INSERT INTO teacher_classes (id, teacher_id, class_id, subject) VALUES
(1, 2, 37, 'Toán'),
(2, 3, 37, 'Ngữ Văn'),
(3, 2, 1, 'Toán');

-- 4. Danh sách 41 học sinh thật lớp 7A6 (Nhập từ file VnEdu DanhSachHocSinh.xlsx)
INSERT INTO students (id, class_id, student_code, full_name, birthday, gender, phone) VALUES
(1, 37, '2307360827', 'Trần Nhị Phương Anh', NULL, 'Nữ', ''),
(2, 37, '2200868590', 'Sơn Thị Bích Chi', NULL, 'Nữ', ''),
(3, 37, '2308865877', 'Trần Trí Dỉ', NULL, 'Nam', ''),
(4, 37, '2200868401', 'Lâm Ngọc Tâm Đoan', NULL, 'Nữ', ''),
(5, 37, '2200868400', 'Hồng Gia Hảo', '2014-05-06', 'Nam', ''),
(6, 37, '2307360978', 'Cao Gia Hân', NULL, 'Nữ', ''),
(7, 37, '2307209553', 'Huỳnh Khả Hân', '2014-10-11', 'Nữ', ''),
(8, 37, '2309633484', 'Huỳnh Phú Hiển', '2014-11-03', 'Nam', ''),
(9, 37, '2308864459', 'Sơn Minh Hiếu', '2014-07-07', 'Nam', ''),
(10, 37, '2507238307', 'Trần Quốc Huy', '2014-12-06', 'Nam', ''),
(11, 37, '2200868554', 'Diệp Phú Hưng', NULL, 'Nam', ''),
(12, 37, '2200868556', 'Hàn Tuấn Khang', NULL, 'Nam', ''),
(13, 37, '2307209352', 'Ngô Lành', '2014-03-10', 'Nam', ''),
(14, 37, '2200868563', 'Huỳnh Kim Lộc', NULL, 'Nam', ''),
(15, 37, '2200868534', 'Dương Trần Tú My', '2014-12-04', 'Nữ', ''),
(16, 37, '2200868535', 'Phạm Bảo Nguyên', NULL, 'Nam', ''),
(17, 37, '2407893367', 'Trần Trọng Nhân', '2014-10-05', 'Nam', ''),
(18, 37, '2301573400', 'La Bảo Nhi', NULL, 'Nữ', ''),
(19, 37, '2308864471', 'Sơn Thị Mỹ Nhi', NULL, 'Nữ', ''),
(20, 37, '2200868537', 'Dương Gia Phú', '2014-11-12', 'Nam', ''),
(21, 37, '2200868538', 'Mã Kiến Phúc', NULL, 'Nam', ''),
(22, 37, '2308864474', 'Tăng Hoàng Phúc', NULL, 'Nam', ''),
(23, 37, '2200868575', 'Huỳnh Kim Quý', NULL, 'Nam', ''),
(24, 37, '2200868539', 'Lưu Thị Thanh Quyên', NULL, 'Nữ', ''),
(25, 37, '2308864476', 'Trần Minh Tài', '2014-03-10', 'Nam', ''),
(26, 37, '2200868512', 'Ngô Thái Tâm', '2013-01-12', 'Nam', ''),
(27, 37, '2200868609', 'Thạch Thị Thu Thanh', '2014-06-10', 'Nữ', ''),
(28, 37, '2308865897', 'Lý Minh Thuận', '2014-04-04', 'Nam', ''),
(29, 37, '2200868645', 'Thạch Minh Thuận', '2014-10-04', 'Nam', ''),
(30, 37, '2200868613', 'Vưu Phú Thuận', '2014-05-07', 'Nam', ''),
(31, 37, '2507238367', 'Trần Thị Thanh Thuý', '2014-10-11', 'Nữ', ''),
(32, 37, '2200868614', 'Lương Minh Thư', '2014-09-09', 'Nữ', ''),
(33, 37, '2200868542', 'Trần Ngọc Tiên', '2013-10-06', 'Nữ', ''),
(34, 37, '2507240867', 'Trần Thuỳ Trang', NULL, 'Nữ', ''),
(35, 37, '2200868543', 'Vương Trần Bảo Trân', NULL, 'Nữ', ''),
(36, 37, '2200868617', 'Lý Hữu Trí', '2014-08-10', 'Nam', ''),
(37, 37, '2200868544', 'Lê Ngọc Trung', '2014-10-07', 'Nam', ''),
(38, 37, '2308864520', 'Ong Thanh Vân', NULL, 'Nữ', ''),
(39, 37, '2200787382', 'Phan Ái Vy', '2014-05-12', 'Nữ', ''),
(40, 37, '2507240987', 'Quách Thuý Vy', '2014-01-08', 'Nữ', ''),
(41, 37, '2309520883', 'Lê Thị Yến', '2011-01-01', 'Nữ', '');

-- 5. Bảng điểm (sẵn sàng cho giáo viên nhập điểm hoặc import file)

-- 6. Nhật ký khởi tạo hệ thống
INSERT INTO audit_logs (id, user_id, username, action, details, ip_address, created_at) VALUES
(1, 1, 'admin', 'Khởi tạo hệ thống', 'Khởi tạo cơ sở dữ liệu EduRadar chuẩn VnEdu Thông tư 22/2021/TT-BGDĐT với 41 học sinh thật lớp 7A6.', '127.0.0.1', CURRENT_TIMESTAMP);
