<?php
// Cấu hình kết nối database
define('DB_HOST', 'localhost');
define('DB_NAME', 'cinemat');
define('DB_USER', 'root');
define('DB_PASS', '');

// Tạo kết nối
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Đường dẫn website
define('SITE_URL', 'http://localhost:8080/CINEMAT/');

// Cấu hình múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khởi động session nếu chưa có
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


