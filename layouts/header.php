<?php 
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';
require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';

$searchQuery = $searchQuery ?? ''; 

?>

<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAT - Trang phim trực tuyến</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #e50914;
            --secondary-color: #141414;
            --text-color: #fff;
            --text-secondary: #aaa;
            --bg-color: #141414;
            --header-bg: rgba(20, 20, 20, 0.95);
            --dropdown-bg: rgba(0, 0, 0, 0.9);
            --hover-bg: rgba(255, 255, 255, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
        }
        
        /* Header chính */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: var(--header-bg);
            padding: 0 4%;
            z-index: 1000;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            height: 70px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        /* Hiệu ứng khi cuộn trang */
        .header.scrolled {
            background-color: var(--header-bg);
        }
        
        /* Logo */
        .logo {
            display: flex;
            align-items: center;
            margin-right: 25px;
        }
        
        .logo a {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            text-decoration: none;
            letter-spacing: 1px;
        }
        
        /* Thanh điều hướng */
        .nav-menu {
            display: flex;
            align-items: center;
            flex-grow: 1;
        }
        
        .nav-list {
            display: flex;
            list-style: none;
        }
        
        .nav-item {
            position: relative;
            margin-right: 20px;
        }
        
        .nav-link {
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.95rem;
            padding: 10px 0;
            transition: color 0.3s ease;
            display: block;
        }
        
        .nav-link:hover {
            color: var(--primary-color);
        }
        
        /* Dropdown menu */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background-color: var(--dropdown-bg);
            min-width: 200px;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 100;
            padding: 10px 0;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .nav-item:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            padding: 10px 20px;
            display: block;
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.3s ease;
        }
        
        .dropdown-item:hover {
            background-color: var(--hover-bg);
            color: var(--primary-color);
        }
        
        /* Phần tìm kiếm */
        .search-box {
            position: relative;
            margin-right: 15px;
        }
        
        .search-input {
            background-color: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-color);
            padding: 8px 15px;
            padding-right: 40px;
            border-radius: 4px;
            font-size: 0.9rem;
            width: 240px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            background-color: rgba(0, 0, 0, 0.8);
            border-color: var(--primary-color);
            outline: none;
            width: 280px;
        }
        
        .search-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1rem;
        }
        
        .search-btn:hover {
            color: var(--primary-color);
        }
        
        /* Phần người dùng */
        .user-menu {
            margin-left: auto;
            position: relative;
        }
        
        .login-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .login-btn:hover {
            background-color: #f40612;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            overflow: hidden;
            margin-right: 10px;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-name {
            color: var(--text-color);
            font-size: 0.9rem;
            margin-right: 5px;
        }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--dropdown-bg);
            min-width: 200px;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 100;
            padding: 10px 0;
            margin-top: 10px;
        }
        
        .user-menu:hover .user-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .user-dropdown-item {
            padding: 10px 20px;
            display: flex;
            align-items: center;
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.3s ease;
        }
        
        .user-dropdown-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .user-dropdown-item:hover {
            background-color: var(--hover-bg);
            color: var(--primary-color);
        }
        
        .user-dropdown-divider {
            height: 1px;
            background-color: rgba(255, 255, 255, 0.1);
            margin: 5px 0;
        }
        
        
        /* Phần nội dung chính (để demo) */
        .main-content {
            padding-top: 90px;
            padding-bottom: 50px;
            max-width: 1200px;
            margin: 0 auto;
            padding-left: 20px;
            padding-right: 20px;
        }
        
        .content-title {
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .content-text {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Header chính -->
    <header class="header" id="header">
        
        <!-- Logo -->
        <div class="logo">
            <a href="<?php echo SITE_URL; ?>">CINEMAT</a>
        </div>
        
        <!-- Thanh điều hướng -->
        <nav class="nav-menu">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?php echo SITE_URL; ?>" class="nav-link">Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">Thể loại <i class="fas fa-chevron-down" style="font-size: 0.8rem; margin-left: 5px;"></i></a>
                    <div class="dropdown-menu">
                        <!-- PHP: Hiển thị danh sách thể loại từ cơ sở dữ liệu -->
                        <?php 
                        // Truy vấn lấy danh sách thể loại
                        $sql = "SELECT id_the_loai, ten_the_loai FROM the_loai ORDER BY ten_the_loai ASC";
                        $result = $conn->query($sql);

                        // Kiểm tra kết quả truy vấn
                        if ($result->num_rows > 0) {
                            // Lặp qua từng thể loại và hiển thị
                            while($row = $result->fetch_assoc()) {
                               echo '<a href="' . SITE_URL . 'danhsachphim.php?search=' . urlencode($row['ten_the_loai']) . '" class="dropdown-item">' . htmlspecialchars($row['ten_the_loai']) . '</a>';
                            }
                        } else {
                            echo '<a href="#" class="dropdown-item">Không có thể loại</a>';
                        }
                        ?>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">Quốc gia <i class="fas fa-chevron-down" style="font-size: 0.8rem; margin-left: 5px;"></i></a>
                    <div class="dropdown-menu">
                        <!-- PHP: Hiển thị danh sách quốc gia từ cơ sở dữ liệu -->
                        <?php
                        // Truy vấn lấy danh sách quốc gia
                        $sql = "SELECT id_quoc_gia, ten_quoc_gia FROM quoc_gia ORDER BY ten_quoc_gia ASC";
                        $result = $conn->query($sql);
                        // Kiểm tra kết quả truy vấn
                        if ($result->num_rows > 0) {
                            // Lặp qua từng quốc gia và hiển thị
                            while($row = $result->fetch_assoc()) {
                                echo '<a href="' . SITE_URL . 'danhsachphim.php?search=' . urlencode($row['ten_quoc_gia']) . '" class="dropdown-item">' . htmlspecialchars($row['ten_quoc_gia']) . '</a>';
                            }
                        } else {
                            echo '<a href="#" class="dropdown-item">Không có quốc gia</a>';
                        }
                        ?>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="<?php echo SITE_URL; ?>yeuthich.php" class="nav-link">Danh sách yêu thích</a>
                </li>
            </ul>
        </nav>
        
        <!-- Phần tìm kiếm -->
        <div class="search-box">
            <form method="GET" action='<?php echo SITE_URL ?>danhsachphim.php'>
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" class="search-input" placeholder="Tìm kiếm bằng tên phim..." required>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        
        <!-- Phần người dùng (chưa đăng nhập) -->
        <!-- PHP: Kiểm tra trạng thái đăng nhập -->
        
        <?php if(!isset($_SESSION['user_id'])): ?>
        
        <div class="user-menu" id="guestMenu">
            <a href="<?php echo SITE_URL ?>dangnhap.php" class="login-btn">Đăng nhập</a>
        </div>
         <?php else: ?> 
        
        <!-- Phần người dùng (đã đăng nhập) -->
        <div class="user-menu" id="userMenu">
            <div class="user-profile">
                <div class="user-avatar">
                    <!-- PHP: Hiển thị ảnh đại diện người dùng -->
                    <img src="<?php echo SITE_URL ?>photo\avatar-mac-dinh-7.jpg" alt="Avatar">
                </div>
                <span class="user-name">
                    <!-- PHP: Hiển thị tên người dùng -->
                    <?php echo $_SESSION['username']; ?>
                </span>
                <i class="fas fa-chevron-down" style="font-size: 0.8rem; color: var(--text-secondary);"></i>
            </div>
            <div class="user-dropdown">
                <a href="yeuthich.php" class="user-dropdown-item">
                    <i class="fas fa-heart"></i> Phim yêu thích
                </a>
                
                <!-- PHP: Hiển thị menu quản trị nếu là admin -->
                <?php if($_SESSION['role'] == 'admin'): ?> 
                <div class="user-dropdown-divider"></div>
                <a href="<?php echo SITE_URL; ?>admin/movies/admin_movie.php" class="user-dropdown-item">
                    <i class="fas fa-cog"></i> Quản trị
                </a>
                <?php endif; ?> 
                
                <div class="user-dropdown-divider"></div>
                <a href="<?php echo SITE_URL?>dangxuat.php" class="user-dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </div>
        </div>
        <?php endif; ?>
    </header>
    

    
   
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Xử lý hiệu ứng cuộn trang
            const header = document.getElementById('header');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 10) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        });

    </script>
</body>
</html>
