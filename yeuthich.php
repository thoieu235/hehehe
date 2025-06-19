<?php
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';
require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';

// Khởi tạo biến
$no_favorites = false;
$movies = [];

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    echo "<script>alert('Đăng nhập để có trải nghiệm tốt nhất với tính năng yêu thích phim!');</script>";
} else {
    // Lấy ID người dùng từ session
    $user_id = $_SESSION['user_id'];

    // Xử lý xóa phim khỏi danh sách yêu thích
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_favorite') {
        if (isset($_POST['movie_id'])) {
            $movie_id = (int)$_POST['movie_id']; // Ép kiểu và validate
            
            // Kiểm tra trạng thái yêu thích hiện tại
            $is_favorite = isMovieFavorite($user_id, $movie_id, $conn);

            if ($is_favorite) { 
                // Xóa phim khỏi danh sách yêu thích
                $delete_sql = "DELETE FROM yeu_thich WHERE id_phim = ? AND id_nguoi_dung = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("ii", $movie_id, $user_id);
                if ($delete_stmt->execute()) {
                } else {
                    $_SESSION['error_message'] = 'Lỗi khi xóa phim khỏi danh sách yêu thích';
                }
                $delete_stmt->close();
                
                // Chuyển hướng về trang yêu thích
                header('Location: yeuthich.php');
                exit();
            }
        }
    }

    // Lấy danh sách phim yêu thích của người dùng
    $sql = "SELECT * FROM yeu_thich WHERE id_nguoi_dung = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $movie = getMovieById($conn, $row['id_phim']);
        if ($movie) { // Kiểm tra movie có tồn tại không
            $movies[] = $movie;
        }
    }
    $stmt->close();

    // Kiểm tra nếu không có phim yêu thích
    $no_favorites = empty($movies);
}

require_once 'C:\xamppp\htdocs\CINEMAT\layouts\header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách phim yêu thích</title>
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
        
        /* Hiển thị dạng lưới (Grid) */
        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
        }

        /* Thông báo khi không có phim yêu thích */
        .no-favorites {
            text-align: center;
            padding: 50px 0;
            color: var(--text-secondary);
            font-size: 1.2rem;
            grid-column: 1 / -1; /* Chiếm toàn bộ grid */
        }
        
        /* Thẻ phim */
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
        
        /* Poster phim */
        .movie-poster {
            width: 100%;
            height: 300px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .movie-card:hover .movie-poster {
            transform: scale(1.05);
        }
        
        /* Lớp phủ khi hover vào poster */
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

        /* Nút xóa phim khỏi danh sách yêu thích */
        .remove-favorite {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 35px;
            height: 35px;
            background-color: rgba(229, 9, 20, 0.8);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 10;
            border: none;
            font-size: 0.9rem;
        }

        .movie-card:hover .remove-favorite {
            opacity: 1;
        }
        
        .remove-favorite:hover {
            background-color: var(--primary-color);
            transform: scale(1.1);
        }
        
        .movie-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
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
            gap: 8px;
        }
        
        .movie-btn {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
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
        
        .movie-btn-details {
            background-color: rgba(229, 9, 20, 0.7);
            color: white;
        }
        
        .movie-btn-details:hover {
            background-color: rgba(229, 9, 20, 0.9);
        }

        /* Hiển thị thông báo */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            position: relative;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #d4edda;
        }

        .alert-error {
            background-color: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #f8d7da;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Phần hiển thị danh sách phim -->
        <section class="movies-section">
            <!-- Tiêu đề -->
            <div class="movies-header">
                <h2 class="movies-title" style="padding: 50px 0px 0px 0px">Danh sách phim yêu thích</h2>
            </div>
            
            <!-- Hiển thị phim dạng lưới -->
            <div class="movies-grid">
                <?php if ($no_favorites): ?>
                    <div class="no-favorites">
                        <i class="far fa-heart" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <p>Bạn chưa có phim yêu thích nào</p>
                        <a href="movies.php" style="color: var(--primary-color); text-decoration: none; margin-top: 10px; display: inline-block;">Khám phá danh sách phim</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($movies as $movie): ?>
                        <div class="movie-card" data-movie-id="<?php echo $movie['id_phim']; ?>">
                            <img src="<?php echo SITE_URL ?>photo/<?php echo htmlspecialchars($movie['poster']); ?>" 
                                alt="<?php echo htmlspecialchars($movie['ten_phim']); ?>" class="movie-poster">
                            
                            <!-- Nút xóa khỏi yêu thích -->
                            <form action="" method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_favorite">
                                <input type="hidden" name="movie_id" value="<?php echo $movie['id_phim']; ?>">
                                <button type="submit" class="remove-favorite" title="Xóa khỏi yêu thích" 
                                        onclick="return confirm('Bạn có chắc muốn xóa phim này khỏi danh sách yêu thích?')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>

                            <div class="movie-overlay">
                                <h3 class="movie-title"><?php echo htmlspecialchars($movie['ten_phim']); ?></h3>
                                <div class="movie-rating">
                                    <i class="fas fa-star"></i>
                                    <span><?php echo displayRating($movie['diem_trung_binh']); ?>/5</span>
                                </div>
                                <div class="movie-actions">
                                    <a href="chitietphim/chitietphim.php?id=<?php echo $movie['id_phim']; ?>" class="movie-btn movie-btn-details">
                                        <i class="fas fa-info-circle"></i> Chi tiết
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        // Tự động ẩn thông báo sau 3 giây
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 3000);
    </script>
</body>
</html>