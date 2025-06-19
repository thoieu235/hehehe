<?php
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';
require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';

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


// Lấy phim nổi bật cho slideshow (3 phim có rating cao nhất)
$query_featured = "SELECT p.*,
                    COALESCE(AVG(d.diem), 0) as diem_trung_binh,
                    COUNT(d.id_danh_gia) as luot_danh_gia
                   FROM phim p
                   LEFT JOIN danh_gia d ON p.id_phim = d.id_phim
                   WHERE p.trang_thai = 'hien'
                   GROUP BY p.id_phim
                   ORDER BY diem_trung_binh DESC, luot_danh_gia DESC
                   LIMIT 3";

$result_featured = $conn->query($query_featured);
$featured_movies = [];

if ($result_featured) {
    while ($row = $result_featured->fetch_assoc()) {
        $featured_movies[] = $row;
    }
} else {
    echo "Lỗi query featured: " . $conn->error;
}

// Lấy phim đáng xem (8 phim có rating cao)
$query_trending = "SELECT p.*,
                    COALESCE(AVG(d.diem), 0) as diem_trung_binh,
                    COUNT(d.id_danh_gia) as luot_danh_gia
                   FROM phim p
                   LEFT JOIN danh_gia d ON p.id_phim = d.id_phim
                   WHERE p.trang_thai = 'hien'
                   GROUP BY p.id_phim
                   ORDER BY diem_trung_binh DESC, luot_danh_gia DESC
                   LIMIT 12";

$result_trending = $conn->query($query_trending);
$trending_movies = [];

if ($result_trending) {
    while ($row = $result_trending->fetch_assoc()) {
        $trending_movies[] = $row;
    }
} else {
    echo "Lỗi query trending: " . $conn->error;
}

// Lấy phim mới cập nhật (8 phim mới nhất)
$query_new = "SELECT p.*,
               COALESCE(AVG(d.diem), 0) as diem_trung_binh,
               COUNT(d.id_danh_gia) as luot_danh_gia
               FROM phim p
               LEFT JOIN danh_gia d ON p.id_phim = d.id_phim
               WHERE p.trang_thai = 'hien'
               GROUP BY p.id_phim
               ORDER BY p.id_phim DESC
               LIMIT 12";

$result_new = $conn->query($query_new);
$new_movies = [];

if ($result_new) {
    while ($row = $result_new->fetch_assoc()) {
        $new_movies[] = $row;
    }
} else {
    echo "Lỗi query new movies: " . $conn->error;
}

// Hàm cắt mô tả - sửa để hỗ trợ UTF-8
function truncateDescription($text, $length = 200) {
    if (!$text) return '';
    
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length, 'UTF-8') . '...';
}

showMessage();


// Đóng kết nối khi script kết thúc
register_shutdown_function(function() use ($conn) {
    if ($conn && !mysqli_connect_errno()) {
        mysqli_close($conn);
    }
});
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAT - Đánh giá phim chuyên nghiệp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Reset và thiết lập cơ bản */
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
        
        /* Hero Section - Slideshow */
        .hero-section {
            position: relative;
            height: 80vh;
            width: 100%;
            overflow: hidden;
        }
        
        .slideshow {
            width: 100%;
            height: 100%;
            position: relative;
        }
        
        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
        }
        
        .slide.active {
            opacity: 1;
        }
        
        .slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.4) 50%, rgba(0, 0, 0, 0.1) 100%);
        }
        
        .slide-content {
            position: relative;
            z-index: 2;
            max-width: 50%;
            padding-left: 5%;
        }
        
        .slide-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .slide-description {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            color: #e5e5e5;
            max-width: 80%;
        }
        
        .slide-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.8rem 1.8rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: #e50914;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #f40612;
            transform: scale(1.05);
        }
        
        .btn-secondary {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.7);
        }
        
        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        /* Slideshow Controls */
        .slideshow-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 10;
        }
        
        .slideshow-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .slideshow-dot.active {
            background-color: #e50914;
            transform: scale(1.2);
        }
        
        /* Movie Carousel Section */
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

        
        .carousel::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        /* Đảm bảo carousel có thể cuộn */
        .carousel {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none;
            gap: 10px;
            padding: 20px 0;
            /* Thêm này để đảm bảo có thể cuộn */
            -webkit-overflow-scrolling: touch;
        }

        /* Đảm bảo movie-card không co lại */
        .movie-card {
            flex: 0 0 auto; /* Quan trọng: không cho phép co lại */
            width: 180px;
            height: 270px;
            min-width: 180px; /* Thêm min-width */
            border-radius: 6px;
            overflow: hidden;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        /* Đảm bảo carousel container có kích thước đúng */
        .carousel-container {
            position: relative;
            padding: 0 4%;
            width: 100%; /* Thêm này */
        }
        
        
        .movie-card:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5);
            z-index: 10;
        }
        
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
        
        /* Carousel Navigation */
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 5;
            transition: all 0.3s ease;
            opacity: 0;
        }
        
        .carousel-container:hover .carousel-nav {
            opacity: 1;
        }
        
        .carousel-nav:hover {
            background-color: rgba(229, 9, 20, 0.8);
        }
        
        .carousel-nav.prev {
            left: 1%;
        }
        
        .carousel-nav.next {
            right: 1%;
        }
        
        .carousel-nav i {
            color: white;
            font-size: 1.5rem;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .slide-title {
                font-size: 2.5rem;
            }
            
            .slide-content {
                max-width: 60%;
            }
        }
        
        @media (max-width: 768px) {
            .hero-section {
                height: 60vh;
            }
            
            .slide-title {
                font-size: 2rem;
            }
            
            .slide-content {
                max-width: 80%;
            }
            
            .slide-description {
                font-size: 1rem;
            }
            
            .movie-card {
                width: 140px;
                height: 210px;
            }
        }
        
        @media (max-width: 480px) {
            .hero-section {
                height: 50vh;
            }
            
            .slide-title {
                font-size: 1.5rem;
            }
            
            .slide-content {
                max-width: 90%;
            }
            
            .slide-description {
                font-size: 0.9rem;
                margin-bottom: 1rem;
            }
            
            .slide-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
            
            .movie-card {
                width: 120px;
                height: 180px;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section - Slideshow -->
    <section class="hero-section">
        <div class="slideshow">
            <?php if (!empty($featured_movies)): ?>
                <?php foreach ($featured_movies as $index => $movie): ?>
                    <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>"
                        style="background-image: url('<?php echo !empty($movie['poster']) ? 'photo/' . htmlspecialchars($movie['poster']) : 'https://via.placeholder.com/1920x1080/333333'; ?>');">
                        <div class="slide-content">
                            <h1 class="slide-title"><?php echo htmlspecialchars($movie['ten_phim']); ?></h1>
                            <p class="slide-description"><?php echo htmlspecialchars(truncateDescription($movie['mo_ta'])); ?></p>
                            <div class="slide-buttons">
                                <a href="<?php echo SITE_URL ?>chitietphim/chitietphim.php?id=<?php echo $movie['id_phim']; ?>" class="btn btn-primary">
                                    <i class="fas fa-play"></i> Chi tiết
                                </a>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_favorite">
                                        <input type="hidden" name="movie_id" value="<?php echo $movie['id_phim']; ?>">
                                        <button type="submit" class="btn btn-secondary <?php echo in_array($movie['id_phim'], $user_favorites) ? 'favorited' : ''; ?>">
                                            <i class="<?php echo in_array($movie['id_phim'], $user_favorites) ? 'fas' : 'far'; ?> fa-heart"></i> 
                                            <?php echo in_array($movie['id_phim'], $user_favorites) ? 'Đã yêu thích' : 'Thêm vào yêu thích'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="#" class="btn btn-secondary" onclick="alert('Vui lòng đăng nhập để sử dụng tính năng này');">
                                        <i class="far fa-heart"></i> Thêm vào yêu thích
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="slide active" style="background-image: url('https://via.placeholder.com/1920x1080/333333');">
                    <div class="slide-content">
                        <h1 class="slide-title">Chào mừng đến với CINEMAT</h1>
                        <p class="slide-description">Khám phá thế giới điện ảnh cùng chúng tôi</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Slideshow Controls -->
        <?php if (!empty($featured_movies)): ?>
        <div class="slideshow-controls">
            <?php foreach ($featured_movies as $index => $movie): ?>
            <div class="slideshow-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    
    <!-- Phim đáng xem -->
    <section class="movie-section">
        <div class="section-header">
            <h2 class="section-title">Phim đáng xem</h2>
        </div>
        
        <div class="carousel-container">
            <div class="carousel" id="trending-carousel">
                <?php if (!empty($trending_movies)): ?>
                    <?php foreach ($trending_movies as $movie): ?>
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
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="movie-card">
                        <img src="https://via.placeholder.com/180x270/333333" alt="No movies" class="movie-poster">
                        <div class="movie-overlay">
                            <h3 class="movie-title">Chưa có phim</h3>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="carousel-nav prev" id="trending-prev">
                <i class="fas fa-chevron-left"></i>
            </div>
            <div class="carousel-nav next" id="trending-next">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
    </section>
    
    <!-- Phim mới cập nhật -->
    <section class="movie-section">
        <div class="section-header">
            <h2 class="section-title">Phim mới cập nhật</h2>
        </div>
        
        <div class="carousel-container">
            <div class="carousel" id="new-carousel">
                <?php if (!empty($new_movies)): ?>
                    <?php foreach ($new_movies as $movie): ?>
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
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="movie-card">
                        <img src="https://via.placeholder.com/180x270/333333" alt="No movies" class="movie-poster">
                        <div class="movie-overlay">
                            <h3 class="movie-title">Chưa có phim mới</h3>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="carousel-nav prev" id="new-prev">
                <i class="fas fa-chevron-left"></i>
            </div>
            <div class="carousel-nav next" id="new-next">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
    </section>

    <?php include 'C:\xamppp\htdocs\CINEMAT\layouts\footer.php'; ?>

    <script>
        // ========== 1. SLIDESHOW (BANNER CHÍNH) ==========
        let currentSlide = 0; // Vị trí slide hiện tại
        const slides = document.querySelectorAll('.slide'); // Lấy tất cả slide
        const dots = document.querySelectorAll('.slideshow-dot'); // Lấy các chấm điều hướng
        const totalSlides = slides.length; // Tổng số slide

        // Hàm hiển thị slide theo index
        function showSlide(index) {
            if (totalSlides === 0) return; // Nếu không có slide thì thoát
            
            // Ẩn tất cả slide
            slides.forEach(slide => {
                slide.classList.remove('active');
            });
            
            // Tắt tất cả chấm điều hướng
            dots.forEach(dot => {
                dot.classList.remove('active');
            });
            
            // Hiển thị slide được chọn và bật chấm tương ứng
            if (slides[index]) {
                slides[index].classList.add('active');
            }
            if (dots[index]) {
                dots[index].classList.add('active');
            }
            
            // Cập nhật vị trí slide hiện tại
            currentSlide = index;
        }

        // Tự động chuyển slide sau 5 giây
        let slideInterval;
        if (totalSlides > 1) {
            slideInterval = setInterval(() => {
                let nextSlide = (currentSlide + 1) % totalSlides; // Quay vòng về slide đầu
                showSlide(nextSlide);
            }, 5000);
        }

        // Xử lý khi click vào chấm điều hướng
        dots.forEach(dot => {
            dot.addEventListener('click', function() {
                // Dừng tự động chuyển slide
                if (slideInterval) {
                    clearInterval(slideInterval);
                }
                
                // Lấy vị trí slide từ data-index
                const slideIndex = parseInt(this.getAttribute('data-index'));
                showSlide(slideIndex);
                
                // Khởi động lại tự động chuyển slide
                if (totalSlides > 1) {
                    slideInterval = setInterval(() => {
                        let nextSlide = (currentSlide + 1) % totalSlides;
                        showSlide(nextSlide);
                    }, 5000);
                }
            });
        });

        // ========== 2. CAROUSEL PHIM (CUỘN NGANG) ==========
        function setupCarousel(carouselId, prevId, nextId) {
            const carousel = document.getElementById(carouselId); // Khung chứa phim
            const prevBtn = document.getElementById(prevId); // Nút lùi
            const nextBtn = document.getElementById(nextId); // Nút tiến
            
            // Nếu không tìm thấy element thì thoát
            if (!carousel || !prevBtn || !nextBtn) return;
            
            // Khoảng cách cuộn mỗi lần (80% chiều rộng khung)
            const scrollAmount = carousel.offsetWidth * 0.8;
            
            // Xử lý click nút lùi
            prevBtn.addEventListener('click', () => {
                carousel.scrollBy({
                    left: -scrollAmount, // Cuộn về trái
                    behavior: 'smooth' // Cuộn mượt
                });
            });
            
            // Xử lý click nút tiến
            nextBtn.addEventListener('click', () => {
                carousel.scrollBy({
                    left: scrollAmount, // Cuộn về phải
                    behavior: 'smooth' // Cuộn mượt
                });
            });
            
            // ========== XỬ LÝ CẢM ỨNG ==========
            let startX, endX;
            
            // Khi bắt đầu chạm
            carousel.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX; // Lưu vị trí x ban đầu
            });
            
            // Khi kết thúc chạm
            carousel.addEventListener('touchend', (e) => {
                endX = e.changedTouches[0].clientX; // Lưu vị trí x cuối
                
                // Nếu vuốt trái hơn 50px - cuộn về phải
                if (startX - endX > 50) {
                    carousel.scrollBy({
                        left: scrollAmount,
                        behavior: 'smooth'
                    });
                } 
                // Nếu vuốt phải hơn 50px - cuộn về trái
                else if (endX - startX > 50) {
                    carousel.scrollBy({
                        left: -scrollAmount,
                        behavior: 'smooth'
                    });
                }
            });
        }

        // Khởi tạo tất cả carousel
        setupCarousel('trending-carousel', 'trending-prev', 'trending-next'); 
        setupCarousel('new-carousel', 'new-prev', 'new-next'); 
    </script>
</body>
</html>