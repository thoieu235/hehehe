<?php
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';
require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';

// Nếu đã đăng nhập thì chuyển hướng về trang chủ hoặc trang hiện tại
if (isLoggedIn()) {
    redirect($_SERVER['REQUEST_URI']);
}


$error = '';
$username = '';
$password = '';

// Xử lý POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!";
    } else {
        $sql = "SELECT * FROM nguoi_dung WHERE ten_nguoi_dung = ? AND is_blocked = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Kiểm tra mật khẩu
            if ($password === $user['matkhau']) {
                $_SESSION['user_id'] = $user['id_nguoi_dung'];
                $_SESSION['username'] = $user['ten_nguoi_dung'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                $redirect = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : SITE_URL . 'index.php';
                unset($_SESSION['redirect_url']);
                redirect($redirect);
            } else {
                $error = "Sai mật khẩu!";
            }
        } else {
            $error = "Tên đăng nhập không tồn tại hoặc bị chặn!";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAT - Đăng nhập</title>
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
        
        /* Login Form */
        .login-container {
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
        
        /* Remember me checkbox */
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-me input[type="checkbox"] {
            display: none;
        }
        
        .remember-me label {
            display: flex;
            align-items: center;
            cursor: pointer;
            color: #aaa;
            font-size: 0.95rem;
        }
        
        .checkbox-custom {
            width: 18px;
            height: 18px;
            border: 2px solid #aaa;
            border-radius: 3px;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .checkbox-custom i {
            color: #fff;
            font-size: 0.8rem;
            display: none;
        }
        
        .remember-me input[type="checkbox"]:checked + label .checkbox-custom {
            background-color: #e50914;
            border-color: #e50914;
        }
        
        .remember-me input[type="checkbox"]:checked + label .checkbox-custom i {
            display: block;
        }
        
        .btn-login {
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
        
        .btn-login:hover {
            background-color: #f40612;
            box-shadow: 0 0 10px rgba(229, 9, 20, 0.7);
            transform: translateY(-2px);
        }
        
        .signup-link {
            text-align: center;
            margin-top: 20px;
            color: #aaa;
            font-size: 0.95rem;
        }
        
        .signup-link a {
            color: #e50914;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .signup-link a:hover {
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
            display: block;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .login-container {
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
            .login-container {
                width: 95%;
                padding: 20px;
            }
            
            .form-header h1 {
                font-size: 1.5rem;
            }
            
            .form-input {
                padding: 14px 14px 14px 40px;
            }
            
            .btn-login {
                padding: 14px;
            }
        }
    </style>
</head>
<body>
   
    <div class="background"></div>
    <div class="overlay"></div>
    
    <div class="logo">CINEMAT</div>
    
    <!-- Login Form -->
    <div class="login-container">
        <!-- Close Button -->
        <a href="<?php echo SITE_URL ?>" class="close-button" title="Quay về trang chủ">
            <i class="fas fa-times"></i>
        </a>
        
        <div class="form-header">
            <h1>Đăng nhập</h1>
            <p>Đăng nhập để trải nghiệm thế giới phim tuyệt vời</p>
        </div>
        
        <!-- Hiển thị lỗi PHP -->
        <?php if (!empty($error)): ?>
            <div style="color: #e87c03; margin-bottom: 15px; text-align: center; padding: 10px; background-color: rgba(232, 124, 3, 0.1); border-radius: 4px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form id="login-form" method="POST" action="dangnhap.php">
            <!-- Username -->
            <div class="form-group">
                <i class="fas fa-user form-icon"></i>
                <input type="text" class="form-input" id="username-email" name="username" placeholder="Email hoặc Tên đăng nhập" value="<?= htmlspecialchars($username) ?>" required>
                <div class="error-message" id="username-email-error" style="display: none;">Vui lòng nhập tên đăng nhập</div>
            </div>
            
            <!-- Password -->
            <div class="form-group">
                <i class="fas fa-lock form-icon"></i>
                <input type="password" class="form-input" id="password" name="password" placeholder="Mật khẩu" required>
                <div class="error-message" id="password-error" style="display: none;">Vui lòng nhập mật khẩu</div>
            </div>
            
            <!-- Remember Me -->
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">
                    <div class="checkbox-custom">
                        <i class="fas fa-check"></i>
                    </div>
                    Ghi nhớ đăng nhập
                </label>
            </div>
            
            <!-- Login Button -->
            <button type="submit" class="btn-login">Đăng nhập</button>
            
            <!-- Signup Link -->
            <div class="signup-link">
                Chưa có tài khoản? <a href="dangki.php">Đăng ký ngay</a>
            </div>
        </form>
    </div>

    <script>
        // Form validation
        const form = document.getElementById('login-form');
        const usernameEmail = document.getElementById('username-email');
        const password = document.getElementById('password');
        
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
        
        // Event listeners for input fields
        usernameEmail.addEventListener('blur', function() {
            if (usernameEmail.value.trim() === '') {
                showError(usernameEmail, 'Vui lòng nhập tên đăng nhập');
            } else {
                hideError(usernameEmail);
            }
        });
        
        password.addEventListener('blur', function() {
            if (password.value.trim() === '') {
                showError(password, 'Vui lòng nhập mật khẩu');
            } else {
                hideError(password);
            }
        }); 
        
        form.addEventListener('submit', function(event) {
            // Validate inputs
            let valid = true;
            
            if (usernameEmail.value.trim() === '') {
                showError(usernameEmail, 'Vui lòng nhập tên đăng nhập');
                valid = false;
            } else {
                hideError(usernameEmail);
            }
            
            if (password.value.trim() === '') {
                showError(password, 'Vui lòng nhập mật khẩu');
                valid = false;
            } else {
                hideError(password);
            }
            
            // Nếu không hợp lệ thì ngăn submit
            if (!valid) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>