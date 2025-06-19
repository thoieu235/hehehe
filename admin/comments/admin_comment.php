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
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id']; // Ép kiểu số nguyên để bảo mật
    
    if ($id > 0) {
        // xóa đánh giá
        $blockSql = "DELETE FROM danh_gia WHERE id_danh_gia = ?";
        $stmt = mysqli_prepare($conn, $blockSql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        $resultBlock = mysqli_stmt_execute($stmt);
        
        if ($resultBlock) {
            $message = "Xóa đánh giá thành công!";
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

// Xử lý tìm kiếm
$searchQuery = '';
$whereClause = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    // Lấy từ khóa tìm kiếm và bảo mật
    $searchQuery = mysqli_real_escape_string($conn, trim($_GET['search']));
    // Kiểm tra xem từ khóa có rỗng hay không
    if (!empty($searchQuery)) {
        $whereClause = "WHERE n.ten_nguoi_dung LIKE '%" . $searchQuery . "%'
                        OR dg.nhan_xet LIKE '%" . $searchQuery . "%'
                        OR p.ten_phim LIKE '%" . $searchQuery . "%'
                        OR t.ten_the_loai LIKE '%" . $searchQuery . "%'
                        OR q.ten_quoc_gia LIKE '%" . $searchQuery . "%'";
    }
}

// Truy vấn lấy danh sách đánh gia
$sql = "SELECT dg.*, 
               n.ten_nguoi_dung, 
               n.role, 
               p.ten_phim, 
               GROUP_CONCAT(t.ten_the_loai SEPARATOR ', ') AS ten_the_loai, 
               q.ten_quoc_gia
        FROM danh_gia dg
        JOIN nguoi_dung n ON dg.id_nguoi_dung = n.id_nguoi_dung
        JOIN phim p ON dg.id_phim = p.id_phim
        JOIN phim_the_loai tp ON p.id_phim = tp.id_phim
        JOIN the_loai t ON tp.id_the_loai = t.id_the_loai
        JOIN quoc_gia q ON p.id_quoc_gia = q.id_quoc_gia
        " . $whereClause . "
        GROUP BY dg.id_danh_gia
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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAT Admin Panel - Quản lý bình luận</title>
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
            font-family: 'Inter', 'Roboto', sans-serif;
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
        
        .table-row:hover {
            background-color: var(--light-gray);
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
        
        /* Comment content max height */
        .comment-content {
            max-height: 80px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="min-h-screen bg-white">
    <!-- Main content -->
    <main class="container mx-auto px-4 py-8" style='padding: 80px 36px;'>
        <!-- Page title and tools -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-center mb-6">QUẢN LÝ ĐÁNH GIÁ</h1>
            
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <!-- Search bar -->
                <form method="GET" class="flex">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                            placeholder="Tìm kiếm theo tên phim..." 
                            class="px-4 py-2 border rounded-l-md focus:outline-none focus:ring-2 focus:ring-red-500">
                        <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-r-md hover:bg-gray-700">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
            </div>
        </div>
        
        <!-- Comments table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Người dùng</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phim</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Điểm</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nội dung</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thời gian</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="table-row">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"> <?php echo htmlspecialchars($row['id_danh_gia']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 bg-gray-200 rounded-full flex items-center justify-center">
                                            <span class="text-[10px] text-gray-600"><?php echo htmlspecialchars($row['role']); ?></span>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900"> <?php echo htmlspecialchars($row['ten_nguoi_dung']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"> <?php echo htmlspecialchars($row['ten_phim']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 comment-content">
                                     <?php echo htmlspecialchars($row['diem']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 comment-content">
                                         <?php echo htmlspecialchars($row['nhan_xet']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500"> <?php echo htmlspecialchars($row['thoi_gian']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">                            
                                    <a href="?action=delete&id=<?php echo $row['id_danh_gia']; ?>" 
                                    onclick="return confirm('Bạn có chắc chắn muốn xóa yêu cầu này?')"
                                    class="bg-red-500 hover:bg-red-600 text-white py-1 px-2 rounded-md text-xs delete-btn">Xóa</a>
                                </td>
                            </tr>   
                        <?php endwhile; ?>                 
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-3 flex items-center justify-between border-t border-gray-200">
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Hiển thị <span class="font-medium">1</span> đến <span class="font-medium"><?php echo mysqli_num_rows($result); ?></span> của <span class="font-medium"><?php echo mysqli_num_rows($result); ?></span>
                            <?php if (!empty($searchQuery)): ?>
                                cho từ khóa "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    </body>
</html>
