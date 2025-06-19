<?php
    session_start();
    
    // Đường dẫn đến file cấu hình
    require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';   
    require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';

    // Khởi tạo biến lỗi và thông báo
    $errors = array();
    $success_message = '';

    // Xử lý form đăng ký
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Lấy dữ liệu từ form
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate dữ liệu

        if (empty($confirm_password)) {
            $errors['confirm_password'] = 'Xác nhận mật khẩu không được để trống';
        } elseif ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Mật khẩu xác nhận không khớp';
        }

        // Kiểm tra username và email đã tồn tại chưa
        if (empty($errors['username'])) {
            $check_username = mysqli_query($conn, "SELECT id_nguoi_dung FROM nguoi_dung WHERE ten_nguoi_dung = '" . mysqli_real_escape_string($conn, $username) . "'");
            if (mysqli_num_rows($check_username) > 0) {
                $errors['username'] = 'Tên đăng nhập đã tồn tại';
            }
        }

        if (empty($errors['email'])) {
            $check_email = mysqli_query($conn, "SELECT id_nguoi_dung FROM nguoi_dung WHERE email = '" . mysqli_real_escape_string($conn, $email) . "'");
            if (mysqli_num_rows($check_email) > 0) {
                $errors['email'] = 'Email đã được sử dụng';
            }
        }

        // Nếu không có lỗi, tiến hành đăng ký
        if (empty($errors)) {
            // Thời gian tạo tài khoản
            $created_at = date('Y-m-d H:i:s');
            
            // Thêm user vào database - Không mã hóa mật khẩu
            $sql = "INSERT INTO nguoi_dung (ten_nguoi_dung, email, matkhau, role, created_at, is_blocked) 
                    VALUES (?, ?, ?, 'user', ?, 0)";
                    
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $password, $created_at);
            
            if (mysqli_stmt_execute($stmt)) {
                $user_id = mysqli_insert_id($conn);
                
                // Đặt thông báo thành công
                $_SESSION['register_success'] = 'Đăng ký thành công! Vui lòng đăng nhập để tiếp tục.';
                
                // Chuyển hướng về trang đăng nhập
                redirect(SITE_URL . 'dangnhap.php');
            } else {
                $errors['general'] = 'Có lỗi xảy ra trong quá trình đăng ký. Vui lòng thử lại.';
            }
            
            mysqli_stmt_close($stmt);
        }
    }
?>



<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAT - Đăng ký tài khoản</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #000;
            color: #fff;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        /* Background image with overlay */
        .background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://via.placeholder.com/1920x1080/333333');
            background-size: cover;
            background-position: center;
            filter: blur(8px);
            z-index: -2;
        }
        
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }
        
        /* Logo */
        .logo {
            position: absolute;
            top: 30px;
            left: 50px;
            font-size: 2rem;
            font-weight: 700;
            color: #e50914;
            letter-spacing: 1px;
        }
        
        /* Registration Form */
        .registration-container {
            width: 450px;
            background-color: rgba(20, 20, 20, 0.9);
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        
        /* Close Button */
        .close-button {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: rgba(128, 128, 128, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .close-button:hover {
            background-color: rgba(229, 9, 20, 0.7);
            transform: scale(1.1);
        }
        
        .close-button i {
            color: #fff;
            font-size: 1rem;
        }
        
        .form-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-header h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: #aaa;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 1.1rem;
        }
        
        .form-input {
            width: 100%;
            padding: 16px 16px 16px 45px;
            border: none;
            border-radius: 4px;
            background-color: #333;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            background-color: #444;
            outline: none;
            box-shadow: 0 0 0 2px rgba(229, 9, 20, 0.5);
        }
        
        .form-input::placeholder {
            color: #aaa;
        }
        
        .btn-register {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 4px;
            background-color: #e50914;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-register:hover {
            background-color: #f40612;
            box-shadow: 0 0 10px rgba(229, 9, 20, 0.7);
            transform: translateY(-2px);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #aaa;
            font-size: 0.95rem;
        }
        
        .login-link a {
            color: #e50914;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* Form validation styling */
        .form-input.error {
            border: 1px solid #e87c03;
        }
        
        .error-message {
            color: #e87c03;
            font-size: 0.85rem;
            margin-top: 6px;
            padding-left: 4px;
            display: none;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .registration-container {
                width: 90%;
                padding: 30px;
            }
            
            .logo {
                top: 20px;
                left: 20px;
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .registration-container {
                width: 95%;
                padding: 20px;
            }
            
            .form-header h1 {
                font-size: 1.5rem;
            }
            
            .form-input {
                padding: 14px 14px 14px 40px;
            }
            
            .btn-register {
                padding: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Background with overlay -->
    <div class="background"></div>
    <div class="overlay"></div>
    
    <!-- Logo -->
    <div class="logo">CINEMAT</div>
    
    <!-- Registration Form -->
    <div class="registration-container">
        <!-- Close Button -->
        <a href="<?php echo SITE_URL ?>" class="close-button" title="Quay về trang chủ">
            <i class="fas fa-times"></i>
        </a>
        
        <div class="form-header">
            <h1>Đăng ký tài khoản</h1>
            <p>Tạo tài khoản để trải nghiệm thế giới phim tuyệt vời</p>
        </div>
        
        <form id="registration-form" method ="POST" action="">
            
            <!-- Email -->
            <div class="form-group">
                <i class="fas fa-envelope form-icon"></i>
                <input type="email" class="form-input" id="email" name="email" placeholder="Email" required>
                <div class="error-message" id="email-error">Vui lòng nhập email hợp lệ</div>
            </div>
            
            <!-- Username -->
            <div class="form-group">
                <i class="fas fa-id-card form-icon"></i>
                <input type="text" class="form-input" id="username" name="username" placeholder="Tên đăng nhập" required>
                <div class="error-message" id="username-error">Tên đăng nhập phải có ít nhất 4 ký tự</div>
            </div>
            
            <!-- Password -->
            <div class="form-group">
                <i class="fas fa-lock form-icon"></i>
                <input type="password" class="form-input" id="password" name="password" placeholder="Mật khẩu" required>
                <div class="error-message" id="password-error">Mật khẩu phải có ít nhất 6 ký tự</div>
            </div>
            
            <!-- Confirm Password -->
            <div class="form-group">
                <i class="fas fa-lock form-icon"></i>
                <input type="password" class="form-input" id="confirm-password" name="confirm_password" placeholder="Xác nhận mật khẩu" required>
                <div class="error-message" id="confirm-password-error">Mật khẩu không khớp</div>
            </div>
            
            <!-- Register Button -->
            <button type="submit" class="btn-register">Đăng ký</button>
            
            <!-- Login Link -->
            <div class="login-link">
                Bạn đã có tài khoản? <a href="<?php echo SITE_URL?>dangnhap.php">Đăng nhập</a>
            </div>
            <?php if (!empty($errors)): ?>
                <div style="color: #e87c03; margin-bottom: 15px;">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        // Form validation
        const form = document.getElementById('registration-form');
        const fullname = document.getElementById('fullname');
        const email = document.getElementById('email');
        const username = document.getElementById('username');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm-password');
        
        // Show error message
        function showError(input, message) {
            const formGroup = input.parentElement;
            const errorMessage = formGroup.querySelector('.error-message');
            
            input.classList.add('error');
            errorMessage.style.display = 'block';
            errorMessage.textContent = message;
        }
        
        // Hide error message
        function hideError(input) {
            const formGroup = input.parentElement;
            const errorMessage = formGroup.querySelector('.error-message');
            
            input.classList.remove('error');
            errorMessage.style.display = 'none';
        }
        
        // Check email is valid
        function isValidEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
        
        // Event listeners for input fields
        
        email.addEventListener('blur', function() {
            if (email.value.trim() === '') {
                showError(email, 'Vui lòng nhập email của bạn');
            } else if (!isValidEmail(email.value.trim())) {
                showError(email, 'Email không hợp lệ');
            } else {
                hideError(email);
            }
        });
        
        username.addEventListener('blur', function() {
            if (username.value.trim() === '') {
                showError(username, 'Vui lòng nhập tên đăng nhập');
            } else if (username.value.trim().length < 4) {
                showError(username, 'Tên đăng nhập phải có ít nhất 4 ký tự');
            } else {
                hideError(username);
            }
        });
        
        password.addEventListener('blur', function() {
            if (password.value.trim() === '') {
                showError(password, 'Vui lòng nhập mật khẩu');
            } else if (password.value.trim().length < 6) {
                showError(password, 'Mật khẩu phải có ít nhất 6 ký tự');
            } else {
                hideError(password);
            }
        });
        
        confirmPassword.addEventListener('blur', function() {
            if (confirmPassword.value.trim() === '') {
                showError(confirmPassword, 'Vui lòng xác nhận mật khẩu');
            } else if (confirmPassword.value.trim() !== password.value.trim()) {
                showError(confirmPassword, 'Mật khẩu không khớp');
            } else {
                hideError(confirmPassword);
            }
        });
    </script>

