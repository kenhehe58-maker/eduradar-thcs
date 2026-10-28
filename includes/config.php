<?php
// =============================================================================
// Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm THCS (EduRadar / Chuẩn VnEdu)
// Cấu hình ứng dụng và kết nối MySQL (XAMPP / MariaDB 10.4)
// Không sử dụng SQLite (Pure MySQL Architecture)
// =============================================================================

// Đường dẫn gốc ứng dụng trên XAMPP Apache
define('BASE_URL', '/my-web-project/');

// Cấu hình kết nối MySQL (Tự động nhận diện biến môi trường Cloud như Render / Railway / PlanetScale / Aiven)
define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1'));
define('DB_PORT', (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306)));
define('DB_NAME', getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'edu_radar'));
define('DB_USER', getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: (getenv('PORT') ? 'edu_user' : 'root')));
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : (getenv('PORT') ? 'EduRadar2026!' : '')));
define('DB_CHARSET', 'utf8mb4');

// Tên định danh ứng dụng & Tên trường THCS
define('APP_NAME', 'EduRadar');
define('SCHOOL_NAME', 'TRƯỜNG THCS GIẢNG VÕ');

// Ngưỡng cảnh báo học tập theo chuẩn THCS
define('PASS_SCORE', 5.0);

// Thư viện SVG icon chuẩn hóa (Monochrome vector icons)
require_once __DIR__ . '/icons.php';

