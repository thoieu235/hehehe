<?php
require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';


// ---------- XỬ LÝ ĐÁNH GIÁ (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $movie_id = isset($_POST['movie_id']) ? (int)$_POST['movie_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $content = trim($_POST['content'] ?? '');

    if ($user_id <= 0 || $movie_id <= 0 || $rating < 1 || $rating > 5 || empty($content)) {
        http_response_code(400);
        exit('Dữ liệu không hợp lệ!');
    }

    // Kiểm tra đã đánh giá chưa
    $check = $conn->prepare("SELECT id_danh_gia FROM danh_gia WHERE id_nguoi_dung = ? AND id_phim = ?");
    $check->bind_param('ii', $user_id, $movie_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt = $conn->prepare("UPDATE danh_gia SET diem = ?, nhan_xet = ?, thoi_gian = NOW() WHERE id_danh_gia = ?");
        $stmt->bind_param('isi', $rating, $content, $row['id_danh_gia']);
    } else {
        $stmt = $conn->prepare("INSERT INTO danh_gia (id_nguoi_dung, id_phim, diem, nhan_xet, thoi_gian) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('iiis', $user_id, $movie_id, $rating, $content);
    }

    if ($stmt->execute()) {
        echo 'OK';
    } else {
        http_response_code(500);
        echo 'Lỗi hệ thống';
    }

    exit();
}

// ---------- XỬ LÝ BÌNH LUẬN (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment') {

    $user_id   = $_SESSION['user_id'] ?? 0;
    $movie_id  = isset($_POST['movie_id']) ? (int)$_POST['movie_id'] : 0;
    $review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
    $content   = trim($_POST['content'] ?? '');

    // parent_id có thể là null
    $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;

    // Kiểm tra dữ liệu đầu vào
    if ($user_id <= 0 || $movie_id <= 0 || $review_id <= 0 || empty($content)) {
        http_response_code(400);
        echo 'Dữ liệu không hợp lệ!';
        exit();
    }

    // Chuẩn bị câu lệnh chèn bình luận
    if ($parent_id === null) {
        // Không có bình luận cha
        $stmt = $conn->prepare("INSERT INTO binh_luan (id_phim, id_nguoi_dung, id_danh_gia, parent_id, noi_dung, thoi_gian, bi_chan, cmt_like, cmt_dislike) 
                                VALUES (?, ?, ?, NULL, ?, NOW(), 0, 0, 0)");
        $stmt->bind_param('iiis', $movie_id, $user_id, $review_id, $content);
    } else {
        // Có bình luận cha
        $stmt = $conn->prepare("INSERT INTO binh_luan (id_phim, id_nguoi_dung, id_danh_gia, parent_id, noi_dung, thoi_gian, bi_chan, cmt_like, cmt_dislike) 
                                VALUES (?, ?, ?, ?, ?, NOW(), 0, 0, 0)");
        $stmt->bind_param('iiiis', $movie_id, $user_id, $review_id, $parent_id, $content);
    }

    // Thực thi và phản hồi
    if ($stmt->execute()) {
        echo 'OK';
    } else {
        http_response_code(500);
        echo 'Lỗi hệ thống khi lưu bình luận!';
    }

    exit();
}

// ---------- HIỂN THỊ PHIM (GET) ----------
$movie_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($movie_id <= 0) {
    echo "<script>alert('ID phim không hợp lệ!'); window.location.href = '../index.php';</script>";
    exit();
}



// Lấy thông tin phim hiện tại
$current_movie = getMovieById($conn, $movie_id);
if (!$current_movie) {
    echo "<script>alert('Không tìm thấy phim!'); window.location.href = 'admin_movie.php';</script>";
    exit();
}

// Lấy thể loại hiện tại của phim
$current_genres = getMovieGenres($conn, $movie_id);

// Lấy dữ liệu quốc gia và thể loại
$countries = getCountries($conn);
$genres = getGenres($conn);

// Lấy danh sách phim liên quan
$related_movies = getRelatedMovies($conn, $movie_id, $current_movie['id_quoc_gia'], $current_genres);

// Khởi tạo biến user_favorites
$user_favorites = [];

// Lấy danh sách phim yêu thích của user nếu đã đăng nhập
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $user_favorites = getUserFavorites($user_id, $conn);
    if ($user_favorites === false) {
        $_SESSION['error_message'] = 'Lỗi khi lấy danh sách yêu thích: ' . mysqli_error($conn);
    }
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
        $_SESSION['error_message'] = $movie_id . ' - Dữ liệu không hợp lệ';
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
    $is_favorite = in_array($movie_id, $user_favorites);
    
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

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết phim - CINEMAT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #141414;
            color: #fff;
            overflow-x: hidden;
        }
        
        .container {
            width: 100%;
        }
        
        
        /* Banner toàn màn hình - Enhanced */
        .hero-section {
            height: 100vh;
            width: 100%;
            position: relative;
            overflow: hidden;
            background-color: #000; /* Fallback background */
        }

        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            /* Fallback background cho trường hợp ảnh không load */
            background-color: #1a1a1a;
        }

        /* Overlay gradient */
        .slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to right, 
                rgb(0, 0, 0) 10%, 
                rgba(0, 0, 0, 0.64) 50%, 
                rgba(0, 0, 0, 0.2) 70%,
                rgba(0, 0, 0, 0.14) 100%
            );
            z-index: 1;
        }

        .slide-content {
            padding-top: 5%;
            position: relative;
            z-index: 2;
            max-width: 50%;
            padding-left: 5%;
            color: white;
        }

        .slide-title {
            margin-top: 20px;
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 25px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
            line-height: 1.1;
        }

        .movie-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 14px;
            align-items: center;
        }

        .movie-meta span {
            color: #ccc;
        }

        .movie-meta a {
            color: #fff;
            text-decoration: none;
            transition: color 0.3s;
            font-weight: 500;
        }

        .movie-meta a:hover {
            color: #e50914;
        }

        .movie-rating {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 16px;
            color: white;
        }

        .star {
            color: #ffc107;
            margin-right: 5px;
        }

        /* Buttons styling */
        .slide-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        /* Nút yêu thích - trạng thái mặc định */
        .btn-secondary {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            text-decoration: none;
        }

        /* Nút yêu thích khi đã thích */
        .btn-secondary.favorited {
            background-color: rgba(229, 9, 20, 0.75) !important;
            border: 2px solid #e50914 !important;
            color: white !important;
        }

        .btn-secondary.favorited:hover {
            background-color: rgba(229, 9, 20, 0.9) !important;
            border-color: #e50914 !important;
            color: white !important;
        }

        /* Nút review */
        .btn-review {
            background-color: #e50914;
            color: #fff;
            border: 2px solid #e50914;
        }

        .btn-review:hover {
            background-color: #b2070f;
            border-color: #b2070f;
            color: #fff;
            text-decoration: none;
        }

        /* Form styling */
        form {
            display: inline-block;
            margin: 0;
        }
        .slide-description {
            margin-top: 30px;
            font-size: 16px;
            line-height: 1.6;
            color: #ddd;
        }
        /* Alert messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }

        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
                    
        /* Sections */
        .section {
            padding: 60px 5% 0px 5%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            font-size: 28px;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        /* Reviews Section */
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .average-rating {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .big-rating {
            font-size: 48px;
            font-weight: 700;
        }
        
        .star-big {
            color: #ffc107;
            font-size: 24px;
        }
        
        .rating-count {
            color: #aaa;
            font-size: 14px;
        }

        #review-form {
            width: 100%;
            max-width: 100%;
            margin-top: 20px;
        }
        
        .write-review {
            background-color: #1f1f1f;
            padding: 0px 25px 25px 25px;
            border-radius: 8px;
            margin-bottom: 40px;
        }
        
        #review-form textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            resize: vertical;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 10px;
            background-color: #141414;
            color: white;
        }
        
        .review-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .star-rating {
            display: flex;
            gap: 5px;
        }
        
        .star-rating i {
            font-size: 24px;
            color: #ccc; /* màu xám mặc định */
            cursor: pointer;
            transition: color 0.2s;
        }

        .star-rating i.fas {
        color: #ffc107; 
        }
        
        .star-rating i:hover,
        .star-rating i.active {
            color: #ffc107;
        }
        
        .submit-review {
            background-color: #e50914;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .submit-review:hover {
            background-color: #b2070f;
        }
        


       
        
        /* Related Movies */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .view-toggle {
            display: flex;
            gap: 10px;
        }
        
        .toggle-btn {
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
            font-size: 18px;
            transition: color 0.3s;
            padding: 5px;
        }
        
        .toggle-btn.active {
            color: #e50914;
        }
        
        .movie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .movie-card {
            position: relative;
            border-radius: 6px;
            overflow: hidden;
            transition: transform 0.3s;
            height: 100%;
            cursor: pointer;
        }
        
        .movie-card:hover {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5);
            z-index: 10;
        }
/* */
        
        .movie-poster {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .movie-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.5) 60%, transparent 100%);
            padding: 10px;
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

        /* Responsive */
        @media (max-width: 768px) {
            .slide-title {
                font-size: 2.5rem;
            }
            
            .slide-content {
                max-width: 80%;
            }
            
            .slide-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn {
                justify-content: center;
            }
            
            .section {
                padding: 40px 5%;
            }
            
            .movie-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Hiển thị thông báo -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['success_message']); 
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Banner toàn màn hình -->
        <section class="hero-section">
            <?php 
                // Xử lý đường dẫn ảnh theo cấu trúc thực tế
                if (!empty($current_movie['poster'])) {
                    $background = '/CINEMAT/photo/' . htmlspecialchars($current_movie['poster']);
                } else {
                    $background = 'https://via.placeholder.com/1920x1080/333333/ffffff?text=No+Image+Available';
                }
            ?>
            <div class="slide" style="background-image: url('<?php echo $background; ?>');" 
                data-bg="<?php echo $background; ?>">
                <div class="slide-content">
                    <h1 class="slide-title"><?php echo htmlspecialchars($current_movie['ten_phim']); ?></h1>
                    
                    <?php if (!empty($current_movie['ten_quoc_gia'])): ?>
                        <div class="movie-meta">
                            <span>Quốc gia: </span>
                            <a href="#"><?php echo htmlspecialchars($current_movie['ten_quoc_gia']); ?></a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($current_genres)): ?>
                        <div class="movie-meta">
                            <span>Thể loại: </span>
                            <?php foreach ($current_genres as $index => $genre): ?>
                                <a href="#"><?php echo htmlspecialchars($genre['ten_the_loai']); ?></a>
                                <?php if ($index < count($current_genres) - 1): ?>
                                    <span>, </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="movie-rating">
                        <i class="fas fa-star star"></i>
                        <span><?php echo $current_movie['diem_trung_binh'] > 0 ? $current_movie['diem_trung_binh'] : '0'; ?>/5</span>
                        <span style="margin-left: 10px; color: #aaa;">(từ <?php echo $current_movie['luot_danh_gia']; ?> người dùng)</span>
                    </div>
                    
                    <div class="slide-buttons">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_favorite">
                                <input type="hidden" name="movie_id" value="<?php echo $current_movie['id_phim']; ?>">
                                <button type="submit" class="btn btn-secondary <?php echo in_array($current_movie['id_phim'], $user_favorites) ? 'favorited' : ''; ?>">
                                    <i class="<?php echo in_array($current_movie['id_phim'], $user_favorites) ? 'fas' : 'far'; ?> fa-heart"></i> 
                                    <?php echo in_array($current_movie['id_phim'], $user_favorites) ? 'Đã yêu thích' : 'Thêm vào yêu thích'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-secondary">
                                <i class="far fa-heart"></i> Thêm vào yêu thích
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="scrollToSection()" class="btn btn-review" id="scroll-to-review">
                            <i class="fas fa-pen"></i> Viết review
                        </button>
                    </div>
                    <p class="slide-description">
                        <?php 
                        if (!empty($current_movie['mo_ta'])) {
                            echo nl2br(htmlspecialchars($current_movie['mo_ta']));
                        } else {
                            echo "Chưa có mô tả cho phim này.";
                        }
                        ?>
                    </p>
                </div>
            </div>
        </section>

        <!-- Đánh giá và nhận xét -->
        <section class="section" id="reviews-section">
            <h2 class="section-title">Đánh giá và nhận xét</h2>
            
            <div class="rating-summary">
                <div class="average-rating">
                    <div class="big-rating"><?php echo number_format($current_movie['diem_trung_binh'], 1) ?></div>
                    <div>
                        <?php 
                        $rating = $current_movie['diem_trung_binh'];
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= floor($rating)) {
                                echo '<i class="fas fa-star star-big"></i>';
                            } elseif ($i <= ceil($rating)) {
                                echo '<i class="fas fa-star-half-alt star-big"></i>';
                            } else {
                                echo '<i class="far fa-star star-big"></i>';
                            }
                        }
                        ?>
                    </div>
                    <div class="rating-count"><?php echo $current_movie['luot_danh_gia'] ?> đánh giá</div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="write-review">
                <form id="review-form">
                    <input type="hidden" name="movie_id" value="<?php echo $current_movie['id_phim']; ?>">
                    <textarea id="review-content" name="content" placeholder="Chia sẻ cảm nghĩ của bạn về bộ phim..."></textarea>
                    <div class="review-actions">
                        <div class="star-rating" id="star-rating">
                            <i class="far fa-star" data-value="1"></i>
                            <i class="far fa-star" data-value="2"></i>
                            <i class="far fa-star" data-value="3"></i>
                            <i class="far fa-star" data-value="4"></i>
                            <i class="far fa-star" data-value="5"></i>
                        </div>
                        <button type="submit" class="submit-review">Gửi đánh giá</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="login-prompt">
                <p><a href="<?php echo SITE_URL ?>dangnhap.php">Đăng nhập</a> để có thể đánh giá và bình luận về phim</p>
            </div>
            <?php endif; ?>
        </section>

        <section class="section">
        <!-- Danh sách đánh giá -->
        <?php include 'C:\xamppp\htdocs\CINEMAT\chitietphim\danhgia.php'; ?>
        </section>
    

        <!-- Phim liên quan -->
        <section class="section" style='padding-bottom: 60px'>
            <div class="section-header">
                <h2 class="section-title">Phim liên quan</h2>
            </div>
            
            <div class="movie-grid" id="grid-container">
                <?php if (!empty($related_movies)): ?>
                    <?php foreach ($related_movies as $movie): ?>
                    <div class="movie-card" data-movie-id="<?php echo $movie['id_phim']; ?>">
                        <img src="<?php echo SITE_URL ?>photo\<?php echo htmlspecialchars($movie['poster']); ?>"
                             alt="<?php echo htmlspecialchars($movie['ten_phim']); ?>" class="movie-poster">
                        <div class="movie-overlay">
                            <h3 class="movie-title"><?php echo htmlspecialchars($movie['ten_phim']); ?></h3>
                            <div class="movie-rating">
                                <i class="fas fa-star"></i>
                                <span><?php echo $movie['diem_trung_binh']; ?>/5</span>
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
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Không có phim liên quan nào.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
<?php require_once 'C:\xamppp\htdocs\CINEMAT\layouts\footer.php'; ?>
</body>


 
    <script>
    // chọn sao và gửi form bằng AJAX
    // Biến lưu số sao người dùng chọn
    let currentRating = 0;
    const stars = document.querySelectorAll('.star-rating i');

    stars.forEach(star => {
        star.addEventListener('mouseover', function () {
            const value = parseInt(this.getAttribute('data-value'));
            highlightStars(value);
        });

        star.addEventListener('mouseout', function () {
            highlightStars(currentRating); // giữ sao sau khi chọn
        });

        star.addEventListener('click', function () {
            currentRating = parseInt(this.getAttribute('data-value'));
            highlightStars(currentRating);
            updateRatingDisplay();
        });
    });

    function highlightStars(value) {
        stars.forEach(star => {
            const starValue = parseInt(star.getAttribute('data-value'));
            if (starValue <= value) {
                star.classList.remove('far');
                star.classList.add('fas');
            } else {
                star.classList.remove('fas');
                star.classList.add('far');
            }
        });
    }

    function updateRatingDisplay() {
        // Hiển thị số sao đã chọn (có thể thêm một div để hiển thị)
        const ratingTexts = {
            0: '',
            1: 'Rất tệ',
            2: 'Tệ', 
            3: 'Bình thường',
            4: 'Tốt',
            5: 'Xuất sắc'
        };
        
        // Tìm hoặc tạo div hiển thị rating text
        let ratingDisplay = document.getElementById('rating-display');
        if (!ratingDisplay) {
            ratingDisplay = document.createElement('div');
            ratingDisplay.id = 'rating-display';
            ratingDisplay.style.marginTop = '5px';
            ratingDisplay.style.fontSize = '14px';
            ratingDisplay.style.color = '#666';
            
            // Thêm sau star-rating
            const starRating = document.getElementById('star-rating');
            starRating.parentNode.insertBefore(ratingDisplay, starRating.nextSibling);
        }
        
        // Cập nhật text hiển thị
        if (currentRating > 0) {
            ratingDisplay.textContent = `${currentRating}/5 - ${ratingTexts[currentRating]}`;
            ratingDisplay.style.fontWeight = 'bold';
            ratingDisplay.style.color = '#ff6b35';
        } else {
            ratingDisplay.textContent = '';
        }
    }

    // Cuộn xuống phàn đánh giá
    function scrollToSection() {
        const section = document.getElementById('reviews-section');
        section.scrollIntoView({ behavior: 'smooth' }); // cuộn mượt
    }

    // Bắt sự kiện khi submit form đánh giá
    document.getElementById('review-form').addEventListener('submit', function (e) {
        e.preventDefault(); // chặn hành vi submit mặc định (reload trang)

        // Kiểm tra đã chọn sao chưa
        if (currentRating === 0) {
            alert('Bạn cần đánh giá sao trước khi gửi!');
            return;
        }

        // Lấy nội dung nhận xét
        const content = document.getElementById('review-content').value.trim();
        if (!content) {
            alert('Vui lòng nhập nội dung đánh giá!');
            return;
        }

        // Tạo yêu cầu AJAX gửi đến chính file này
        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true); // gửi POST đến chính file hiện tại
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

        // Xử lý kết quả trả về
        xhr.onload = function () {
            if (xhr.status === 200) {
                const response = xhr.responseText.trim();
                if (response === 'OK') {
                    document.getElementById('review-form').reset(); // xóa nội dung form
                    currentRating = 0; // reset số sao
                    highlightStars(0); // reset hiển thị sao
                    // Reload trang để hiển thị đánh giá mới
                    location.reload();
                } else {
                    alert('Lỗi: ' + response);
                }
            } else {
                alert('Lỗi gửi đánh giá! Status: ' + xhr.status);
            }
        };

        xhr.onerror = function() {
            alert('Lỗi kết nối!');
        };

        // Tạo chuỗi dữ liệu gửi đi
        const params = `action=review&rating=${currentRating}&content=${encodeURIComponent(content)}&movie_id=<?php echo $current_movie['id_phim']; ?>`;
console.log("Đang gửi request đánh giá:", params); // THÊM DÒNG NÀY
        xhr.send(params); // gửi dữ liệu đi
    });



        // Gắn sự kiện submit vào form có class là 'comment-form'
        document.querySelectorAll('.comment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const content = this.querySelector('.comment-content').value.trim();
                
                // Lấy dữ liệu từ data attributes (chuyển từ kebab-case sang camelCase)
                const reviewId = parseInt(this.dataset.reviewId);
                const parentId = parseInt(this.dataset.parentId || 0);

                console.log('Debug data:', {
                    content: content,
                    reviewId: reviewId,
                    parentId: parentId,
                    movieId: movieId
                });

                // Kiểm tra dữ liệu đầu vào
                if (!content) {
                    alert('Vui lòng nhập nội dung bình luận!');
                    return;
                }

                if (!reviewId || !movieId) {
                    alert('Thiếu thông tin bình luận!');
                    console.log('Missing data:', { reviewId, movieId });
                    return;
                }

                // Tạo yêu cầu AJAX để gửi dữ liệu bình luận đến server
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

                // Xử lý phản hồi từ server
                xhr.onload = function() {
                    console.log('Response status:', xhr.status);
                    console.log('Response text:', xhr.responseText);
                    
                    if (xhr.status === 200) {
                        const response = xhr.responseText.trim();

                        if (response === 'OK') {
                            alert('Bình luận đã được gửi thành công!');
                            form.reset(); // Reset form hiện tại
                            location.reload(); // Tải lại trang để hiển thị bình luận mới
                        } else {
                            alert('Lỗi: ' + response); // Hiển thị lỗi từ server
                        }
                    } else {
                        alert('Lỗi gửi bình luận! Mã lỗi: ' + xhr.status);
                    }
                };

                // Xử lý khi xảy ra lỗi kết nối mạng
                xhr.onerror = function() {
                    alert('Lỗi kết nối!');
                };

                // Chuẩn bị dữ liệu gửi đi - KHÔNG gửi tham số 'id'
                const params = `action=comment&content=${encodeURIComponent(content)}&movie_id=${movieId}&review_id=${reviewId}&parent_id=${parentId}`;
                
                console.log('Sending params:', params);
                
                // Gửi dữ liệu
                xhr.send(params);
            });
        });
</script>


