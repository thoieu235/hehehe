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

// Xử lý chặn phim
if (isset($_GET['action']) && $_GET['action'] == 'block' && isset($_GET['id'])) {
    $id = (int)$_GET['id']; // Ép kiểu số nguyên để bảo mật
    
    if ($id > 0) {
        // Chặn phim
        $deleteSql = "UPDATE phim SET trang_thai = 'an' WHERE id_phim = ?";
        $stmt = mysqli_prepare($conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        $resultDelete = mysqli_stmt_execute($stmt);
        
        if ($resultDelete) {
            $message = "Ẩn phim thành công!";
            $messageType = "success";
        } else {
            $message = "Lỗi khi ẩn phim: " . mysqli_error($conn);
            $messageType = "error";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "ID phim không hợp lệ.";
        $messageType = "error";
    }
}

// Xử lý bỏ chặn phim
if (isset($_GET['action']) && $_GET['action'] == 'unblock' && isset($_GET['id'])) {
    $id = (int)$_GET['id']; // Ép kiểu số nguyên để bảo mật
    
    if ($id > 0) {
        // Bỏ chặn phim
        $unblockSql = "UPDATE phim SET trang_thai = 'hien' WHERE id_phim = ?";
        $stmt = mysqli_prepare($conn, $unblockSql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        $resultUnblock = mysqli_stmt_execute($stmt);
        
        if ($resultUnblock) {
            $message = "Bỏ chặn phim thành công!";
            $messageType = "success";
        } else {
            $message = "Lỗi khi bỏ chặn phim: " . mysqli_error($conn);
            $messageType = "error";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "ID phim không hợp lệ.";
        $messageType = "error";
    }
}

// Xử lý tìm kiếm phim
$searchQuery = '';
$whereClause = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    // Lấy từ khóa tìm kiếm và bảo mật
    $searchQuery = mysqli_real_escape_string($conn, trim($_GET['search']));
    // Kiểm tra xem từ khóa có rỗng hay không
    if (!empty($searchQuery)) {
        $whereClause = "WHERE p.ten_phim LIKE '%" . $searchQuery . "%'";
    }
}

// Lấy danh sách phim từ cơ sở dữ liệu
$sql = "SELECT p.id_phim, p.ten_phim, p.trang_thai, p.poster, p.diem_trung_binh, 
               GROUP_CONCAT(t.ten_the_loai SEPARATOR ', ') AS the_loai
        FROM phim p
        LEFT JOIN phim_the_loai pt ON p.id_phim = pt.id_phim
        LEFT JOIN the_loai t ON pt.id_the_loai = t.id_the_loai
        " . $whereClause . "
        GROUP BY p.id_phim, p.ten_phim, p.trang_thai, p.poster
        ORDER BY p.id_phim DESC";

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
    <title>CINEMAT Admin Panel - Quản lý phim</title>
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
    <!-- Header -->
    <!-- Main content -->
    <main class="flex-grow pt-20 pb-10 px-4 md:px-6 lg:px-8">
        <div class="container mx-auto">
            <!-- Hiển thị thông báo -->
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Page title and tools -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-center mb-6">QUẢN LÝ PHIM</h2>
                
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
                    
                    <!-- Add movie button -->
                    <a href="<?php echo SITE_URL; ?>admin/movies/admin_movie_post.php" class="primary-btn flex items-center gap-2 px-4 py-2 rounded-md text-decoration-none">
                        <i class="bi bi-plus-lg"></i>
                        <span>Thêm phim</span>
                    </a>
                </div>
            </div>
            
            <!-- Movies table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poster</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tên phim</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thể loại</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Đánh giá</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr class="table-row">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($row['poster'])): ?>
                                            <img src="<?php echo SITE_URL ?>photo\<?php echo htmlspecialchars($row['poster']); ?>" alt="Movie poster" class="w-12 h-18 object-cover rounded">
                                        <?php else: ?>
                                            <div class="w-12 h-18 bg-gray-200 rounded flex items-center justify-center">
                                                <i class="bi bi-image text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['ten_phim']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['the_loai'] ?? 'Chưa phân loại'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['diem_trung_binh']) ?? 'Chưa đánh giá'; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($row['trang_thai'] == 'hien'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full badge-success text-white">
                                                Hoạt động
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full badge-danger text-white">
                                                Đã chặn
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a  href="admin_movie_edit.php?id=<?php echo $row['id_phim']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-md text-xs mr-2 inline-block">
                                            Sửa
                                        </a>
                                        <?php if ($row['trang_thai'] == 'hien'): ?>
                                            <a href="?action=block&id=<?php echo $row['id_phim']; ?>" 
                                               class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md text-xs inline-block"
                                               onclick="return confirm('Bạn có chắc chắn muốn chặn phim này?')">
                                                Chặn
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=unblock&id=<?php echo $row['id_phim']; ?>" 
                                            class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-xs inline-block"
                                            onclick="return confirm('Bạn có chắc chắn muốn bỏ chặn phim này?')">
                                                Bỏ chặn
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        <?php if (!empty($searchQuery)): ?>
                                            Không tìm thấy phim nào với từ khóa "<?php echo htmlspecialchars($searchQuery); ?>"
                                        <?php else: ?>
                                            Không có phim nào được tìm thấy.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="px-6 py-3 flex items-center justify-between border-t border-gray-200">
                    <p class="text-sm text-gray-700">
                        Hiển thị <span class="font-medium">1</span> đến <span class="font-medium"><?php echo mysqli_num_rows($result); ?></span> của <span class="font-medium"><?php echo mysqli_num_rows($result); ?></span> kết quả
                        <?php if (!empty($searchQuery)): ?>
                            cho từ khóa "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
                        <?php endif; ?>
                    </p>
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