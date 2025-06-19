<?php
// Kết nối file cấu hình và chức năng cần thiết
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';
require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';
require_once 'C:\xamppp\htdocs\CINEMAT\admin\layouts\header.php';

// Kiểm tra người dùng đã đăng nhập chưa
if (!isLoggedIn()) {
    redirect(SITE_URL . 'dangnhap.php');
}

// Chỉ cho phép admin truy cập trang này
if ($_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . 'index.php');
}

// Xử lý chặn người dùng
if (isset($_GET['action']) && $_GET['action'] == 'block' && isset($_GET['id'])) {
    $id = (int)$_GET['id']; // Ép kiểu số nguyên để bảo mật
    
    if ($id > 0) {
        // Chặn người dùng
        $blockSql = "UPDATE nguoi_dung SET is_blocked = 1 WHERE id_nguoi_dung = ?";
        $stmt = mysqli_prepare($conn, $blockSql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        $resultBlock = mysqli_stmt_execute($stmt);
        
        if ($resultBlock) {
            $message = "Chặn người dùng thành công!";
            $messageType = "success";
        } else {
            $message = "Lỗi khi chặn người dùng: " . mysqli_error($conn);
            $messageType = "error";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "ID người dùng không hợp lệ.";
        $messageType = "error";
    }
}
// Xử lý bỏ chặn người dùng
if (isset($_GET['action']) && $_GET['action'] == 'unblock' && isset($_GET['id'])) {
    $id = (int)$_GET['id']; // Ép kiểu số nguyên để bảo mật
    
    if ($id > 0) {
        // Bỏ chặn người dùng
        $unblockSql = "UPDATE nguoi_dung SET is_blocked = 0 WHERE id_nguoi_dung = ?";
        $stmt = mysqli_prepare($conn, $unblockSql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        $resultUnblock = mysqli_stmt_execute($stmt);
        
        if ($resultUnblock) {
            $message = "Bỏ chặn người dùng thành công!";
            $messageType = "success";
        } else {
            $message = "Lỗi khi bỏ chặn người dùng: " . mysqli_error($conn);
            $messageType = "error";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "ID người dùng không hợp lệ.";
        $messageType = "error";
    }
}

// Xử lý tìm kiếm
$searchQuery = '';
$whereClause = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    // Lấy từ khóa tìm kiếm và bảo mật
    $searchQuery = mysqli_real_escape_string($conn, trim($_GET['search']));
    // Kiểm tra xem từ khóa có rỗng hay không
    if (!empty($searchQuery)) {
        $whereClause = "WHERE n.ten_nguoi_dung LIKE '%" . $searchQuery . "%'";
    }
}

// Truy vấn lấy danh sách người dùng
$sql = "SELECT n.id_nguoi_dung, n.ten_nguoi_dung, n.email, n.created_at, n.role, n.is_blocked
          FROM nguoi_dung n
          " . $whereClause . "
          ORDER BY n.id_nguoi_dung DESC";

// Thực hiện truy vấn
$result = mysqli_query($conn, $sql);

// Kiểm tra lỗi truy vấn
if (!$result) {
    die("Lỗi truy vấn: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="vi">
<>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAT Admin Panel - Quản lý người dùng</title>
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
        }
        
        .primary-btn {
            background-color: var(--primary);
            color: white;
            transition: all 0.3s;
        }
        
        .primary-btn:hover {
            background-color: #c70812;
        }
        
        .badge-success {
            background-color: #10B981;
        }
        
        .badge-danger {
            background-color: #EF4444;
        }
        
        .table-row:hover {
            background-color: var(--light-gray);
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <main class="flex-grow pt-20 pb-10 px-4 md:px-6 lg:px-8">
        <div class="container mx-auto">
            <!-- Hiển thị thông báo -->
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Page title and search -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-center mb-6">QUẢN LÝ NGƯỜI DÙNG</h2>
                
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <!-- Search bar -->
                    <form method="GET" class="flex">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                            placeholder="Tìm kiếm theo tên người dùng..." 
                            class="px-4 py-2 border rounded-l-md focus:outline-none focus:ring-2 focus:ring-red-500">
                        <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-r-md hover:bg-gray-700">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        
            <!-- Users table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tên người dùng</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày đăng ký</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vai trò</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($user = mysqli_fetch_assoc($result)): ?>
                                    <tr class="table-row">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= $user['id_nguoi_dung'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($user['ten_nguoi_dung']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($user['email']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-800">
                                                <?= htmlspecialchars($user['role']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($user['is_blocked'] == 0): ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    Hoạt động
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                    Bị chặn
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if ($user['role'] !== 'admin'): // Không cho phép chặn admin ?>
                                                <?php if ($user['is_blocked'] == 0): ?>
                                                    <a href="?action=block&id=<?= $user['id_nguoi_dung'] ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
                                                    class="bg-orange-500 hover:bg-orange-600 text-white py-1 px-3 rounded-md text-xs inline-block"
                                                    onclick="return confirm('Bạn có chắc chắn muốn chặn người dùng này?')">
                                                        Chặn
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?action=unblock&id=<?= $user['id_nguoi_dung'] ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
                                                    class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md text-xs inline-block"
                                                    onclick="return confirm('Bạn có chắc chắn muốn bỏ chặn người dùng này?')">
                                                        Bỏ chặn
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">Không thể thao tác</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                        <?= !empty($searchQuery) ? 'Không tìm thấy người dùng nào.' : 'Không có người dùng nào.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

     <script>
        // Tự động ẩn thông báo sau 5 giây
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>

</body>
</html>