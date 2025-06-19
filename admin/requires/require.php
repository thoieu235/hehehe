<?php
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';
require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';
require_once 'C:\xamppp\htdocs\CINEMAT\admin\layouts\header.php';

// Kiểm tra xem người dùng đã đăng nhập hay chưa
if (!isLoggedIn()) {
    redirect(SITE_URL . 'dangnhap.php');
}

// Kiểm tra quyền truy cập của người dùng
if ($_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . 'index.php');
}

// Xử lý xóa đánh giá
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {

    $id = (int)$_GET['id']; // Ép kiểu số nguyên để bảo mật
    
    if ($id > 0) {
        // Chặn phim
        $deleteSql = "DELETE FROM yeu_cau_them_phim WHERE id_yeu_cau = ? ";
        $stmt = mysqli_prepare($conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        $resultDelete = mysqli_stmt_execute($stmt);
        
        if ($resultDelete) {
            $message = "Xóa thành công!";
            $messageType = "success";
        } else {
            $message = "Lỗi khi xóa phim: " . mysqli_error($conn);
            $messageType = "error";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "ID phim không hợp lệ.";
        $messageType = "error";
    }
}

// Lấy danh sách từ database 
$sql = "SELECT *
        FROM yeu_cau_them_phim";

$result = mysqli_query($conn, $sql);

// Kiểm tra lỗi truy vấn
if (!$result) {
    die("Lỗi truy vấn: " . mysqli_error($conn));
}

?>


<!DOCTYPE html>
<html lang="vi">
<head>
    <<meta charset="UTF-8">
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

        
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        
        .status-processed {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .delete-btn {
            background-color: #FEF2F2;
            color: #DC2626;
            transition: all 0.2s;
        }
        
        .delete-btn:hover {
            background-color: #FEE2E2;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-8" style = 'padding: 80px 32px'>
        <h2 class="text-2xl font-bold text-center mb-6"  >QUẢN LÝ YÊU CẦU THÊM PHIM</h2>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tên phim
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Thể loại
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Trạng thái
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="requestsTableBody">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($row['ten_phim']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?php echo htmlspecialchars($row['the_loai']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if ($row['trang_thai'] === 'da_xu_ly'): ?>
                                    <span class="status-badge status-processed">Đã xử lý</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">Chờ xử lý</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <a href="?action=delete&id=<?php echo $row['id_yeu_cau']; ?>" 
                                onclick="return confirm('Bạn có chắc chắn muốn xóa yêu cầu này?')"
                                class="delete-btn px-3 py-1 rounded-md font-medium">Xóa</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>

            </table>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="fixed inset-0 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="relative bg-white rounded-lg max-w-md w-full p-6 overflow-hidden shadow-xl">
                <div class="text-center">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Xác nhận xóa</h3>
                    <p class="text-sm text-gray-500 mb-6" id="deleteConfirmText">
                        Bạn có chắc chắn muốn xóa yêu cầu này?
                    </p>
                    <div class="flex justify-center gap-3">
                        <button id="cancelDeleteBtn" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 py-2 px-4 rounded-md">
                            Hủy
                        </button>
                        <button action id="confirmDeleteBtn" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-md">
                            Xóa
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</html>
