<?php
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';
require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';

// Kiểm tra xem có đang đăng nhập không
if (isLoggedIn()) {
    // Lưu thông tin cần thiết trước khi xóa session
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

    // Xóa tất cả session variables
    $_SESSION = array();

    // Xóa session cookie nếu có
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Hủy session
    session_destroy();

    // Bắt đầu session mới để lưu thông báo
    session_start();

    // Đặt thông báo thành công (không cần kiểm tra isLoggedIn() nữa vì đã logout)
    $_SESSION['logout_message'] = "Đã đăng xuất thành công. Hẹn gặp lại " . htmlspecialchars($username) . "!";

    // Chuyển hướng về trang chủ
    redirect(SITE_URL . 'index.php');
} else {
    // Nếu chưa đăng nhập, chuyển hướng về trang đăng nhập
    redirect(SITE_URL . 'index.php');
}
?>