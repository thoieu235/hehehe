<?php
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';

// Xử lý yêu cầu thêm phim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_movie') {
    // Kiểm tra user đã đăng nhập
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = 'Vui lòng đăng nhập để sử dụng tính năng này';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    $user_id = intval($_SESSION['user_id']);
    $movie_name = trim($_POST['movie_name']);
    $genre = trim($_POST['genre']);
    
    // Validate input
    if (empty($movie_name) || empty($genre)) {
        $_SESSION['error_message'] = 'Vui lòng điền đầy đủ thông tin';
    } else {
        // Thêm yêu cầu vào database (giả sử có bảng movie_requests)
        $insert_sql = "INSERT INTO yeu_cau_them_phim (id_nguoi_dung, ten_phim, the_loai) VALUES (?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        
        if ($insert_stmt) {
            mysqli_stmt_bind_param($insert_stmt, "iss", $user_id, $movie_name, $genre);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $_SESSION['success_message'] = 'Yêu cầu của bạn đã được gửi thành công!';
            } else {
                $_SESSION['error_message'] = 'Có lỗi xảy ra khi gửi yêu cầu';
            }
            mysqli_stmt_close($insert_stmt);
        }
    }
    
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// Xử lý thêm/xóa yêu thích khi có POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
    // Kiểm tra user đã đăng nhập
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = 'Vui lòng đăng nhập để sử dụng tính năng này';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    $user_id = intval($_SESSION['user_id']);
    $movie_id = intval($_POST['movie_id']);
    
    // Validate input
    if ($user_id <= 0 || $movie_id <= 0) {
        $_SESSION['error_message'] = 'Dữ liệu không hợp lệ';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Kiểm tra phim có tồn tại không
    $check_movie_sql = "SELECT id_phim FROM phim WHERE id_phim = ? AND trang_thai = 'hien'";
    $check_movie_stmt = mysqli_prepare($conn, $check_movie_sql);
    
    if ($check_movie_stmt) {
        mysqli_stmt_bind_param($check_movie_stmt, "i", $movie_id);
        mysqli_stmt_execute($check_movie_stmt);
        $movie_result = mysqli_stmt_get_result($check_movie_stmt);
        
        if (mysqli_num_rows($movie_result) === 0) {
            $_SESSION['error_message'] = 'Phim không tồn tại';
            mysqli_stmt_close($check_movie_stmt);
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }
        mysqli_stmt_close($check_movie_stmt);
    }
    
    // Kiểm tra trạng thái yêu thích hiện tại
    $is_favorite = isMovieFavorite($user_id, $movie_id, $conn);
    
    if ($is_favorite) {
        // Xóa khỏi danh sách yêu thích
        $delete_sql = "DELETE FROM yeu_thich WHERE id_nguoi_dung = ? AND id_phim = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        
        if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, "ii", $user_id, $movie_id);
            
            if (mysqli_stmt_execute($delete_stmt)) {

            } else {
                $_SESSION['error_message'] = 'Có lỗi xảy ra khi xóa khỏi yêu thích: ' . mysqli_error($conn);
            }
            
            mysqli_stmt_close($delete_stmt);
        } else {
            $_SESSION['error_message'] = 'Lỗi chuẩn bị câu lệnh xóa';
        }
    } else {
        // Thêm vào danh sách yêu thích
        $insert_sql = "INSERT INTO yeu_thich (id_nguoi_dung, id_phim, thoi_gian) VALUES (?, ?, NOW())";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        
        if ($insert_stmt) {
            mysqli_stmt_bind_param($insert_stmt, "ii", $user_id, $movie_id);
            
            if (mysqli_stmt_execute($insert_stmt)) {

            } else {
                $_SESSION['error_message'] = 'Có lỗi xảy ra khi thêm vào yêu thích: ' . mysqli_error($conn);
            }
            
            mysqli_stmt_close($insert_stmt);
        } else {
            $_SESSION['error_message'] = 'Lỗi chuẩn bị câu lệnh thêm';
        }
    }
    
    // Redirect về trang trước
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

require_once 'C:\xamppp\htdocs\CINEMAT\layouts\header.php';

// Lấy danh sách yêu thích của user hiện tại
$user_favorites = array();
if (isset($_SESSION['user_id'])) {
    $user_favorites = getUserFavorites($_SESSION['user_id'], $conn);
}


// Xử lý tìm kiếm phim và lọc
$searchQuery = '';
$where = [];

// Xử lý tìm kiếm và lọc từ GET parameters
if (!empty($_GET['search'])) {
    $searchQuery = mysqli_real_escape_string($conn, trim($_GET['search']));
    // $where[] = "p.ten_phim LIKE '%$searchQuery%' ";
     $where[] = "(p.ten_phim LIKE '%$searchQuery%' 
                OR p.mo_ta LIKE '%$searchQuery%' 
                OR t.ten_the_loai LIKE '%$searchQuery%' 
                OR q.ten_quoc_gia LIKE '%$searchQuery%')"; 
}

if (isset($_GET['the_loai']) && $_GET['the_loai'] != 'all' && !empty($_GET['the_loai'])) {
    $genre = mysqli_real_escape_string($conn, $_GET['the_loai']);
    $where[] = "t.ten_the_loai = '$genre'";
}

if (isset($_GET['quoc_gia']) && $_GET['quoc_gia'] != 'all' && !empty($_GET['quoc_gia'])) {
    $country = mysqli_real_escape_string($conn, $_GET['quoc_gia']);
    $where[] = "q.ten_quoc_gia = '$country'";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$searchTitle = $searchQuery ? 'Kết quả tìm kiếm cho: "' . htmlspecialchars($searchQuery) . '"' : 'Tất cả phim';
// Đếm tổng số phim
$countSql = "SELECT COUNT(DISTINCT p.id_phim) as total
             FROM phim p
             LEFT JOIN phim_the_loai pt ON p.id_phim = pt.id_phim
             LEFT JOIN the_loai t ON pt.id_the_loai = t.id_the_loai
             LEFT JOIN quoc_gia q ON p.id_quoc_gia = q.id_quoc_gia
             $whereClause";


$countResult = mysqli_query($conn, $countSql);
$totalMovies = mysqli_fetch_assoc($countResult)['total'];

// Lấy danh sách phim từ cơ sở dữ liệu
$sql = "SELECT p.id_phim, p.ten_phim, p.trang_thai, p.poster, p.diem_trung_binh, p.mo_ta,
               GROUP_CONCAT(DISTINCT t.ten_the_loai SEPARATOR ', ') AS the_loai, q.ten_quoc_gia
        FROM phim p
        LEFT JOIN phim_the_loai pt ON p.id_phim = pt.id_phim
        LEFT JOIN the_loai t ON pt.id_the_loai = t.id_the_loai
        LEFT JOIN quoc_gia q ON p.id_quoc_gia = q.id_quoc_gia
        $whereClause
        GROUP BY p.id_phim, p.ten_phim, p.trang_thai, p.poster, p.diem_trung_binh, q.ten_quoc_gia
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
    <title>Danh sách phim</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #e50914;
            --secondary-color: #141414;
            --text-color: #fff;
            --text-secondary: #aaa;
            --bg-color: #141414;
            --card-bg: #222;
            --overlay-color: rgba(0, 0, 0, 0.7);
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
            padding: 20px;
        }
        
       .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
        }
        
        /* Phần tiêu đề danh sách phim */
        .movies-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .movies-title {
            font-size: 1.8rem;
            font-weight: 600;
        }

        /* Nút yêu cầu thêm phim */
        .request-movie-btn {
            top: 75px;
            right: 30px;
            background:rgb(203, 25, 34);
            color: white;
            border: none;
            padding: 15px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .request-movie-btn:hover {
            transform: translateY(-3px);
            background: rgb(222, 14, 24);
        }

        .request-movie-btn i {
            font-size: 16px;
        }

        /* Modal overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        /* Modal content */
        .modal-content {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 500px;
            animation: slideIn 0.3s ease;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(229, 9, 20, 0.3);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            color: #aaa;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            color: rgb(203, 25, 34);
            background: rgba(229, 9, 20, 0.1);
        }

        /* Form styles */
        .request-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-weight: 600;
            color: #e5e5e5;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-input, .form-select {
            padding: 15px 18px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #e50914;
            background: rgba(229, 9, 20, 0.1);
            box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.2);
        }

        .form-input::placeholder {
            color: #888;
        }

        .form-select option {
            background: #2d2d2d;
            color: #fff;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .btn {
            padding: 15px 25px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: rgb(203, 25, 34);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: rgb(208, 15, 25);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #aaa;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Success/Error messages */
        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #4caf50;
        }

        .message.error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.3);
            color: #f44336;
        }
        
        /* Movie Grid Section - CSS */
        .movie-section {
            padding: 2rem 0;
        }

        .section-header {
            padding: 0 4%;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #e5e5e5;
        }

        .movie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
        }

        .movie-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .movie-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .movie-poster {
            width: 100%;
            height: 300px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .movie-card:hover .movie-poster {
            transform: scale(1.05);
        }

        .movie-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.5) 60%, transparent 100%);
            padding: 15px;
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            height: 100%;
        }

        .movie-card:hover .movie-overlay {
            opacity: 1;
        }

        .movie-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .movie-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .movie-rating i {
            color: #ffc107;
        }

        .movie-actions {
            display: flex;
            gap: 6px;
        }

        .movie-btn {
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all 0.2s ease;
            flex: 1;
            border: none;
            text-decoration: none;
        }

        .btn.favorited {
            background-color:rgba(229, 9, 20, 0.75);
            border-color:rgb(255, 255, 255);
        }

        .movie-btn-favorite {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .movie-btn-favorite:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .movie-btn-favorite.active {
            background-color: rgba(229, 9, 20, 0.7);
        }

        .movie-btn-favorite.active i {
            color:rgb(255, 255, 255);
        }

        .movie-btn-details {
            background-color: rgba(229, 9, 20, 0.7);
            color: white;
        }

        .movie-btn-details:hover {
            background-color: rgba(229, 9, 20, 0.9);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .request-movie-btn {
                right: 15px;
                top: 100px;
                padding: 12px 16px;
                font-size: 12px;
            }

            .modal-content {
                margin: 20px;
                padding: 25px;
            }

            .modal-title {
                font-size: 1.3rem;
            }

            .form-actions {
                flex-direction: column;
            }
        }

        .dropdown-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            max-width: 1200px;
            /* margin: 0 auto; */
        }

        .dropdown {
            position: relative;
            background-color: #404040;
            border: 1px solid #555;
            color: white;
            min-width: 150px;
        }

        .dropdown:not(:last-child) {
            border-right: none;
        }

        .dropdown select {
            width: 100%;
            padding: 12px 35px 12px 15px;
            background-color: transparent;
            border: none;
            color: white;
            font-size: 14px;
            font-weight: 400;
            cursor: pointer;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .dropdown::after {
            content: '▼';
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc;
            font-size: 10px;
            pointer-events: none;
        }

        .dropdown:hover {
            background-color: #4a4a4a;
        }

        .dropdown select:focus {
            background-color: #4a4a4a;
        }

        .dropdown select option {
            background-color: #404040;
            color: white;
            padding: 8px;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .dropdown-container {
                flex-direction: column;
                gap: -1px;
            }
            
            .dropdown {
                border-right: 1px solid #555;
            }
            
            .dropdown:not(:last-child) {
                border-bottom: none;
            }
        }
        .btn_filter{
            position: relative;
            background-color: #404040;
            border: 1px solid #555;
            color: white;
            min-width: 80px;
        }

        .nav_filter{
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px
        }
    </style>
</head>
<body>
    <div class="container">
        

        <!-- Modal yêu cầu thêm phim -->
        <div class="modal-overlay" id="requestModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">
                        <i class="fas fa-film"></i>
                        Yêu cầu thêm phim
                    </h3>
                    <button class="modal-close" onclick="closeRequestModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <form class="request-form" method="POST" action="">
                    <input type="hidden" name="action" value="request_movie">
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-film"></i>
                            Tên phim *
                        </label>
                        <input type="text" name="movie_name" class="form-input" 
                               placeholder="Nhập tên phim bạn muốn yêu cầu..." required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tags"></i>
                            Thể loại *
                        </label>
                        <select name="genre" class="form-select" required>
                            <option value="">Chọn thể loại phim</option>
                            <option value="Hành động">Hành động</option>
                            <option value="Phiêu lưu">Phiêu lưu</option>
                            <option value="Hài kịch">Hài kịch</option>
                            <option value="Chính kịch">Chính kịch</option>
                            <option value="Kinh dị">Kinh dị</option>
                            <option value="Lãng mạn">Lãng mạn</option>
                            <option value="Khoa học viễn tưởng">Khoa học viễn tưởng</option>
                            <option value="Hoạt hình">Hoạt hình</option>
                            <option value="Tài liệu">Tài liệu</option>
                            <option value="Gia đình">Gia đình</option>
                            <option value="Thể thao">Thể thao</option>
                            <option value="Chiến tranh">Chiến tranh</option>
                            <option value="Tội phạm">Tội phạm</option>
                            <option value="Bí ẩn">Bí ẩn</option>
                            <option value="Âm nhạc">Âm nhạc</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeRequestModal()">
                            <i class="fas fa-times"></i>
                            Hủy bỏ
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Gửi yêu cầu
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <section class="movies-section">
            <!-- Tiêu đề -->
            <div class="movies-header">
                <h2 class="movies-title" style="padding: 50px 0px 0px 0px"><?php echo $totalMovies; ?> phim được tìm thấy cho từ khóa"<?=$searchQuery?>"</h2>             
            </div>
                <div class="nav_filter">
                    <form method="GET" class="dropdown-container">
                        <?php if (!empty($searchQuery)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <?php endif; ?>

                        <div class="dropdown">
                            <select name = "the_loai">
                                <option value="all" <?= (isset($_GET['the_loai']) && $_GET['the_loai'] == 'all') ? 'selected' : '' ?>>Thể loại</option>
                                 <?php
                                    $show = mysqli_query($conn, "SELECT * from the_loai ");
                                    if(mysqli_num_rows($show) > 0){
                                            $i = 0;
                                            while($row = mysqli_fetch_assoc($show)){  
                                ?>
                                <option value="<?php echo $row['ten_the_loai']; ?>" 
                                    <?= (isset($_GET['the_loai']) && $_GET['the_loai'] == $row['ten_the_loai']) ? 'selected' : '' ?>>
                                    <?php echo $row['ten_the_loai']; ?>
                                </option>

                                <?php
                                $i ++; }}
                                ?>
                            </select>
                        </div>

                        <div class="dropdown">
                            <select name = "quoc_gia">
                                <option value="all" <?= (isset($_GET['quoc_gia']) && $_GET['quoc_gia'] == 'all') ? 'selected' : '' ?>>Quốc gia</option>
                                <?php
                                    $show = mysqli_query($conn, "SELECT * from quoc_gia ");
                                    if(mysqli_num_rows($show) > 0){
                                            $i = 0;
                                            while($row = mysqli_fetch_assoc($show)){  
                                ?>
                                <option value="<?php echo $row['ten_quoc_gia']; ?>" 
                                    <?= (isset($_GET['quoc_gia']) && $_GET['quoc_gia'] == $row['ten_quoc_gia']) ? 'selected' : '' ?>>
                                    <?php echo $row['ten_quoc_gia']; ?>
                                </option>

                                <?php
                                $i ++; }}
                                ?>
                            </select>
                        </div>
                        <button class="btn_filter" style="background-color: #5a5a5a;" type ="submit">Lọc phim</button>
                    </form>
                    <button style="display: block; margin-left: auto;" class="request-movie-btn" onclick="openRequestModal()" title="Yêu cầu thêm phim mới">
                        <i class="fas fa-plus"></i>
                        <span>Yêu cầu phim</span>
                    </button>
                </div>
            
            <!-- Hiển thị phim dạng lưới -->
            <div class="grid-container">
                <div class="movie-grid" id="trending-grid">
                    <?php if (!empty($result)): ?>
                        <?php while ($movie = mysqli_fetch_assoc($result)): ?>
                            <div class="movie-card" data-movie-id="<?php echo $movie['id_phim']; ?>">
                                <img src="<?php echo SITE_URL ?>photo\<?php echo htmlspecialchars($movie['poster']); ?>" 
                                    alt="<?php echo htmlspecialchars($movie['ten_phim']); ?>" class="movie-poster">
                                <div class="movie-overlay">
                                    <h3 class="movie-title"><?php echo htmlspecialchars($movie['ten_phim']); ?></h3>
                                    <div class="movie-rating">
                                        <i class="fas fa-star"></i>
                                        <span><?php echo displayRating($movie['diem_trung_binh']); ?>/5</span>
                                    </div>
                                    <div class="movie-actions">
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <form method="POST" action="" style="display: flex;">
                                                <input type="hidden" name="action" value="toggle_favorite">
                                                <input type="hidden" name="movie_id" value="<?php echo $movie['id_phim']; ?>">
                                                <button type="submit" class="movie-btn movie-btn-favorite <?php echo in_array($movie['id_phim'], $user_favorites) ? 'active' : ''; ?>">
                                                    <i class="<?php echo in_array($movie['id_phim'], $user_favorites) ? 'fas' : 'far'; ?> fa-heart"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a href="#" class="movie-btn movie-btn-favorite" onclick="alert('Vui lòng đăng nhập để sử dụng tính năng này');">
                                                <i class="far fa-heart"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo SITE_URL ?>chitietphim/chitietphim.php?id=<?php echo $movie['id_phim']; ?>" class="movie-btn movie-btn-details">
                                            <i class="fas fa-info-circle"></i> Chi tiết
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="movie-card">
                            <div class="movie-overlay">
                                <h3 class="movie-title">Danh sách phim trống, yêu cầu thêm phim</h3>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
    
    <script>
        // Modal functions
        function openRequestModal() {
            <?php if (!isset($_SESSION['user_id'])): ?>
                alert('Vui lòng đăng nhập để sử dụng tính năng này');
                return;
            <?php endif; ?>
            
            document.getElementById('requestModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeRequestModal() {
            document.getElementById('requestModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            
            // Clear form
            document.querySelector('.request-form').reset();
        }

        // Close modal when clicking overlay
        document.getElementById('requestModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRequestModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('requestModal').classList.contains('active')) {
                closeRequestModal();
            }
        });

        // Auto close success/error messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                message.style.transform = 'translateY(-20px)';
                setTimeout(function() {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 300);
            });
        }, 5000);

        // Favorite button functionality
        const favoriteButtons = document.querySelectorAll('.favorite-btn, .movie-btn-favorite');
        
        favoriteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Toggle active class
                this.classList.toggle('active');
                
                // Change icon
                const icon = this.querySelector('i');
                if (this.classList.contains('active')) {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    
                    // If it's the hero section button, change text
                    if (this.classList.contains('btn-secondary')) {
                        this.innerHTML = '<i class="fas fa-heart"></i> Đã thêm vào yêu thích';
                    }
                } else {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    
                    // If it's the hero section button, change text back
                    if (this.classList.contains('btn-secondary')) {
                        this.innerHTML = '<i class="far fa-heart"></i> Thêm vào yêu thích';
                    }
                }
            });
        });

        // Movie card click functionality
        const movieCards = document.querySelectorAll('.movie-card');
        
        movieCards.forEach(card => {
            card.addEventListener('click', function() {
                const movieTitle = this.querySelector('.movie-title').textContent;
                console.log(`Navigating to details page for: ${movieTitle}`);
                // In a real app, this would navigate to the movie details page
                // window.location.href = `/movie/${movieId}`;
            });
        });

        // Form validation
        document.querySelector('.request-form').addEventListener('submit', function(e) {
            const movieName = document.querySelector('input[name="movie_name"]').value.trim();
            const genre = document.querySelector('select[name="genre"]').value;
            
            if (!movieName) {
                e.preventDefault();
                alert('Vui lòng nhập tên phim');
                return;
            }
            
            if (!genre) {
                e.preventDefault();
                alert('Vui lòng chọn thể loại phim');
                return;
            }
        });

        // Smooth scroll effect for request button
        let lastScrollTop = 0;
        const requestBtn = document.querySelector('.request-movie-btn');
        
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                requestBtn.style.transform = 'translateX(100px)';
                requestBtn.style.opacity = '0.7';
            } else {
                // Scrolling up or at top
                requestBtn.style.transform = 'translateX(0)';
                requestBtn.style.opacity = '1';
            }
            
            lastScrollTop = scrollTop;
        });

    </script>
<script>(function(){function c(){var b=a.contentDocument||a.contentWindow.document;if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'945953e610750f18',t:'MTc0ODIyMTE0NC4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>