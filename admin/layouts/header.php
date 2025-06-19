
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAT Admin Panel - Header</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: #E50914;
            --light-gray: #F5F5F5;
            --border: #DDDDDD;
        }
        
        * {
            font-family: 'Inter', 'Roboto', 'Helvetica', sans-serif;
        }
        
        body {
            background-color: #FFFFFF;
            margin: 0;
            padding: 0;
        }
        
        .primary-btn {
            background-color: var(--primary);
            color: white;
            transition: all 0.3s;
        }
        
        .primary-btn:hover {
            background-color: #c70812;
        }
        
        .nav-scroll {
            overflow-x: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        .nav-scroll::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        
        .nav-container {
            min-width: max-content;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="bg-white shadow-md fixed top-0 left-0 right-0 z-50">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center">
                <h1 class="text-2xl font-bold text-[#E50914]">CINEMAT</h1>
            </div>
            
            <!-- Navigation -->
            <nav class="hidden md:block nav-scroll">
                <div class="nav-container flex items-center space-x-1">
                    <a href="<?php echo SITE_URL; ?>admin/movies/admin_movie.php"
                        class="px-3 py-2 rounded-md text-sm font-medium transition duration-200 ease-in-out
                        <?php echo basename($_SERVER['PHP_SELF']) === 'admin_movie.php' ? 'bg-gray-200 text-[#E50914]' : 'text-gray-700 hover:bg-gray-100'; ?>">
                        Phim
                    </a>
                    <a href="<?php echo SITE_URL; ?>admin\users\admin_user.php" 
                        class="px-3 py-2 rounded-md text-sm font-medium transition duration-200 ease-in-out
                        <?php echo basename($_SERVER['PHP_SELF']) === 'admin_user.php' ? 'bg-gray-200 text-[#E50914]' : 'text-gray-700 hover:bg-gray-100'; ?>">
                        Người dùng
                    </a>
                    <a href="<?php echo SITE_URL; ?>admin\comments\admin_comment.php"
                        class="px-3 py-2 rounded-md text-sm font-medium transition duration-200 ease-in-out
                        <?php echo basename($_SERVER['PHP_SELF']) === 'admin_comment' ? 'bg-gray-200 text-[#E50914]' : 'text-gray-700 hover:bg-gray-100'; ?>">
                        Đánh giá
                    </a>
                    <a href="<?php echo SITE_URL; ?>admin\requires\require.php"
                        class="px-3 py-2 rounded-md text-sm font-medium transition duration-200 ease-in-out
                        <?php echo basename($_SERVER['PHP_SELF']) === 'require.php' ? 'bg-gray-200 text-[#E50914]' : 'text-gray-700 hover:bg-gray-100'; ?>">
                        Yêu cầu thêm phim
                    </a>
                </div>
            </nav>
            
            <!-- Admin dropdown -->
            <div class="relative">
                <button id="adminDropdown" class="flex items-center space-x-2 px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100">
                    <span>Admin</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                
                <div id="adminMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                    <a href="<?php echo SITE_URL; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Quay lại trang chủ</a>
                    <div class="border-t border-gray-200"></div>
                    <a href="<?php echo SITE_URL?>dangxuat.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Đăng xuất</a>
                </div>
            </div>
            
            <!-- Mobile menu button -->
            <button id="mobileMenuBtn" class="md:hidden flex items-center">
                <i class="bi bi-list text-2xl"></i>
            </button>
        </div>
    </header>
    
    <script>
        // Toggle admin dropdown
        const adminDropdown = document.getElementById('adminDropdown');
        const adminMenu = document.getElementById('adminMenu');
        
        adminDropdown.addEventListener('click', () => {
            adminMenu.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!adminDropdown.contains(e.target) && !adminMenu.contains(e.target)) {
                adminMenu.classList.add('hidden');
            }
        });
    </script>
</html>