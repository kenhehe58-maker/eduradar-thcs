<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Cấu hình ứng dụng và kết nối MySQL (XAMPP / MariaDB 10.4)
// Không sử dụng SQLite (Pure MySQL Architecture)
// =============================================================================

// Đường dẫn gốc ứng dụng trên XAMPP Apache
define('BASE_URL', '/my-web-project/');

// Cấu hình kết nối MySQL
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'edu_radar');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Tên định danh ứng dụng
define('APP_NAME', 'EduRadar');

// Ngưỡng cảnh báo học tập theo chuẩn THCS
define('PASS_SCORE', 5.0);

// Thư viện SVG icon chuẩn hóa (Monochrome vector icons)
require_once __DIR__ . '/icons.php';

