<?php
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';


// Hàm hiển thị rating
function displayRating($rating) {
    return ($rating && $rating > 0) ? number_format($rating, 1) : 'N/A';
}

// Hàm kiểm tra xem người dùng đã đăng nhập hay chưa
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}


// Hàm lấy thông tin phim theo ID
function getMovieById($conn, $id) {
    $sql = "SELECT p.*, qg.ten_quoc_gia FROM phim p 
            JOIN quoc_gia qg ON p.id_quoc_gia = qg.id_quoc_gia 
            WHERE p.id_phim = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $movie = $result->fetch_assoc();
        $stmt->close();
        return $movie;
    }
    return null;
}

// Hàm lấy thể loại của phim
function getMovieGenres($conn, $movie_id) {
    $sql = "SELECT p.id_the_loai, t.ten_the_loai FROM phim_the_loai p
            JOIN the_loai t ON p.id_the_loai = t.id_the_loai
            WHERE id_phim = ?";
    $stmt = $conn->prepare($sql);
    $genres = [];
    if ($stmt) {
        $stmt->bind_param("i", $movie_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $genres[] = [
                'id_the_loai' => $row['id_the_loai'],
                'ten_the_loai' => $row['ten_the_loai']
            ];
        }
        $stmt->close();
    }
    return $genres;
}

// Hàm lấy tất cả thể loại
function getGenres($conn) {
    $sql = "SELECT * FROM the_loai ORDER BY ten_the_loai";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $genres = [];
    while ($row = $result->fetch_assoc()) {
        $genres[] = $row;
    }
    $stmt->close();
    return $genres;
}

// Hàm lấy tất cả quốc gia
function getCountries($conn) {
    $sql = "SELECT * FROM quoc_gia ORDER BY ten_quoc_gia";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $countries = [];
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row;
    }
    $stmt->close();
    return $countries;
}

// SỬA HÀM getMovieReviews - CẬP NHẬT ĐỂ SỬ DỤNG CỘT LIKES/DISLIKES TRỰC TIẾP
function getMovieReviews($conn, $movie_id) {
    try {
        $sql = "SELECT dg.*, nd.ten_nguoi_dung,
                       COALESCE(dg.likes, 0) as likes,
                       COALESCE(dg.dislikes, 0) as dislikes
                FROM danh_gia dg 
                LEFT JOIN nguoi_dung nd ON dg.id_nguoi_dung = nd.id_nguoi_dung
                WHERE dg.id_phim = ? 
                ORDER BY dg.thoi_gian DESC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $movie_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        $stmt->close();
        return $reviews;
    } catch (Exception $e) {
        error_log("getMovieReviews error: " . $e->getMessage());
        return [];
    }
}

// SỬA HÀM getCommentsForReviews - CẬP NHẬT ĐỂ LẤY LIKES/DISLIKES CỦA COMMENT
function getCommentsForReviews($conn, $review_ids) {
    if (empty($review_ids)) {
        return [];
    }
    
    try {
        $placeholders = str_repeat('?,', count($review_ids) - 1) . '?';
        $sql = "SELECT bl.*, u.ten_nguoi_dung,
                       COALESCE(bl.cmt_like, 0) as cmt_like,
                       COALESCE(bl.cmt_dislike, 0) as cmt_dislike
                FROM binh_luan bl 
                LEFT JOIN nguoi_dung u ON bl.id_nguoi_dung = u.id_nguoi_dung
                WHERE bl.id_danh_gia IN ($placeholders) 
                ORDER BY bl.parent_id ASC, bl.thoi_gian ASC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Tạo chuỗi kiểu dữ liệu cho bind_param
        $types = str_repeat('i', count($review_ids));
        $stmt->bind_param($types, ...$review_ids);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $comments = [];
        while ($row = $result->fetch_assoc()) {
            $comments[] = $row;
        }
        $stmt->close();
        return $comments;
    } catch (Exception $e) {
        error_log("getCommentsForReviews error: " . $e->getMessage());
        return [];
    }
}


// Hàm tổ chức bình luận thành cấu trúc cây
function organizeCommentsTree($comments) {
    $tree = [];
    $temp = [];
    
    // Tạo map để dễ truy cập
    foreach ($comments as $comment) {
        $temp[$comment['id_binh_luan']] = $comment;
        $temp[$comment['id_binh_luan']]['replies'] = [];
    }
    
    // Tổ chức thành cây
    foreach ($temp as $comment) {
        if ($comment['parent_id'] == null || $comment['parent_id'] == 0) {
            // Bình luận gốc (không có parent)
            $tree[] = &$temp[$comment['id_binh_luan']];
        } else {
            // Bình luận con (reply)
            if (isset($temp[$comment['parent_id']])) {
                $temp[$comment['parent_id']]['replies'][] = &$temp[$comment['id_binh_luan']];
            }
        }
    }
    
    return $tree;
}

// Hàm kiểm tra user đã đánh giá phim chưa
// SỬA HÀM hasUserReviewed
function hasUserReviewed($conn, $user_id, $movie_id) {
    try {
        $sql = "SELECT id FROM danh_gia WHERE id_nguoi_dung = ? AND id_phim = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $user_id, $movie_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    } catch (Exception $e) {
        error_log("hasUserReviewed error: " . $e->getMessage());
        return false;
    }
}

// Hàm gửi đánh giá
// SỬA HÀM submitReview - THÊM KHỞI TẠO LIKES/DISLIKES = 0
function submitReview($conn, $user_id, $movie_id, $rating, $content) {
    try {
        $sql = "INSERT INTO danh_gia (id_nguoi_dung, id_phim, diem_so, noi_dung, thoi_gian, likes, dislikes) VALUES (?, ?, ?, ?, NOW(), 0, 0)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("iiis", $user_id, $movie_id, $rating, $content);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        return $success;
    } catch (Exception $e) {
        error_log("submitReview error: " . $e->getMessage());
        return false;
    }
}


// Hàm gửi bình luận
// SỬA HÀM submitComment - THÊM KHỞI TẠO CMT_LIKE/CMT_DISLIKE = 0
function submitComment($conn, $user_id, $review_id, $content, $parent_id = null) {
    try {
        $sql = "INSERT INTO binh_luan (id_nguoi_dung, id_danh_gia, noi_dung, parent_id, thoi_gian, cmt_like, cmt_dislike) VALUES (?, ?, ?, ?, NOW(), 0, 0)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("iisi", $user_id, $review_id, $content, $parent_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        return $success;
    } catch (Exception $e) {
        error_log("submitComment error: " . $e->getMessage());
        return false;
    }
}

// Hàm xóa đánh giá
// SỬA HÀM deleteReview
function deleteReview($conn, $review_id, $user_id) {
    try {
        // Trước tiên xóa các bình luận của đánh giá này
        $delete_comments_sql = "DELETE FROM binh_luan WHERE id_danh_gia = ?";
        $stmt1 = $conn->prepare($delete_comments_sql);
        $stmt1->bind_param("i", $review_id);
        $stmt1->execute();
        $stmt1->close();
        
        // Cuối cùng xóa đánh giá
        $sql = "DELETE FROM danh_gia WHERE id = ? AND id_nguoi_dung = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $review_id, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        return $success;
    } catch (Exception $e) {
        error_log("deleteReview error: " . $e->getMessage());
        return false;
    }
}



// Xóa bình luận
function deleteComment() {
    global $conn, $user_id;
    
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    
    if ($comment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID bình luận không hợp lệ']);
        return;
    }
    
    // Kiểm tra quyền xóa
    $check_sql = "SELECT id_nguoi_dung FROM binh_luan WHERE id_binh_luan = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $comment_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bình luận']);
        return;
    }
    
    $comment = $result->fetch_assoc();
    
    if ($comment['id_nguoi_dung'] != $user_id && $_SESSION['role'] != 'admin') {
        echo json_encode(['success' => false, 'message' => 'Không có quyền xóa bình luận này']);
        return;
    }
    
    // Bắt đầu transaction
    $conn->begin_transaction();
    
    try {
        // Xóa tất cả bình luận con (replies)
        deleteCommentAndReplies($conn, $comment_id);
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Xóa bình luận thành công']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa bình luận: ' . $e->getMessage()]);
    }
}

// Hàm đệ quy xóa bình luận và các reply của nó
function deleteCommentAndReplies($conn, $comment_id) {
    // Tìm tất cả replies của comment này
    $find_replies_sql = "SELECT id_binh_luan FROM binh_luan WHERE parent_id = ?";
    $find_replies_stmt = $conn->prepare($find_replies_sql);
    $find_replies_stmt->bind_param('i', $comment_id);
    $find_replies_stmt->execute();
    $replies = $find_replies_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Đệ quy xóa tất cả replies
    foreach ($replies as $reply) {
        deleteCommentAndReplies($conn, $reply['id_binh_luan']);
    }
    
    // Xóa comment hiện tại
    $delete_sql = "DELETE FROM binh_luan WHERE id_binh_luan = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $comment_id);
    $delete_stmt->execute();
}

// VIẾT LẠI HÀM toggleReviewLike ĐỂ SỬ DỤNG CỘT LIKES/DISLIKES TRỰC TIẾP
function toggleReviewLike($conn, $user_id, $review_id, $type) {
    try {
        // Tạo bảng tạm để lưu trữ lượt thích của user (nếu chưa có)
        $create_temp_table = "CREATE TABLE IF NOT EXISTS user_review_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            review_id INT NOT NULL,
            like_type ENUM('like', 'dislike') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_review (user_id, review_id)
        )";
        $conn->query($create_temp_table);
        
        // Kiểm tra user đã like/dislike review này chưa
        $check_sql = "SELECT like_type FROM user_review_likes WHERE user_id = ? AND review_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $review_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        $action = '';
        if ($result->num_rows > 0) {
            $current_type = $result->fetch_assoc()['like_type'];
            
            if ($current_type === $type) {
                // Nếu đã like/dislike rồi thì bỏ like/dislike (xóa record)
                $delete_sql = "DELETE FROM user_review_likes WHERE user_id = ? AND review_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("ii", $user_id, $review_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                $action = 'remove_' . $type;
            } else {
                // Nếu đang like mà click dislike (hoặc ngược lại) thì chuyển đổi
                $update_sql = "UPDATE user_review_likes SET like_type = ? WHERE user_id = ? AND review_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sii", $type, $user_id, $review_id);
                $update_stmt->execute();
                $update_stmt->close();
                $action = 'switch_to_' . $type;
            }
        } else {
            // Chưa có thì thêm mới
            $insert_sql = "INSERT INTO user_review_likes (user_id, review_id, like_type) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iis", $user_id, $review_id, $type);
            $insert_stmt->execute();
            $insert_stmt->close();
            $action = 'add_' . $type;
        }
        
        $check_stmt->close();
        
        // Cập nhật số lượng likes/dislikes trong bảng danh_gia
        $count_likes_sql = "SELECT COUNT(*) as count FROM user_review_likes WHERE review_id = ? AND like_type = 'like'";
        $count_dislikes_sql = "SELECT COUNT(*) as count FROM user_review_likes WHERE review_id = ? AND like_type = 'dislike'";
        
        $likes_stmt = $conn->prepare($count_likes_sql);
        $likes_stmt->bind_param("i", $review_id);
        $likes_stmt->execute();
        $likes_count = $likes_stmt->get_result()->fetch_assoc()['count'];
        $likes_stmt->close();
        
        $dislikes_stmt = $conn->prepare($count_dislikes_sql);
        $dislikes_stmt->bind_param("i", $review_id);
        $dislikes_stmt->execute();
        $dislikes_count = $dislikes_stmt->get_result()->fetch_assoc()['count'];
        $dislikes_stmt->close();
        
        // Cập nhật vào bảng danh_gia
        $update_review_sql = "UPDATE danh_gia SET likes = ?, dislikes = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_review_sql);
        $update_stmt->bind_param("iii", $likes_count, $dislikes_count, $review_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        return ['success' => true, 'likes' => $likes_count, 'dislikes' => $dislikes_count, 'action' => $action];
    } catch (Exception $e) {
        error_log("toggleReviewLike error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
    }
}

// THÊM HÀM toggleCommentLike MỚI
function toggleCommentLike($conn, $user_id, $comment_id, $type) {
    try {
        // Tạo bảng tạm để lưu trữ lượt thích comment của user (nếu chưa có)
        $create_temp_table = "CREATE TABLE IF NOT EXISTS user_comment_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            comment_id INT NOT NULL,
            like_type ENUM('like', 'dislike') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_comment (user_id, comment_id)
        )";
        $conn->query($create_temp_table);
        
        // Kiểm tra user đã like/dislike comment này chưa
        $check_sql = "SELECT like_type FROM user_comment_likes WHERE user_id = ? AND comment_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $comment_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        $action = '';
        if ($result->num_rows > 0) {
            $current_type = $result->fetch_assoc()['like_type'];
            
            if ($current_type === $type) {
                // Nếu đã like/dislike rồi thì bỏ like/dislike (xóa record)
                $delete_sql = "DELETE FROM user_comment_likes WHERE user_id = ? AND comment_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("ii", $user_id, $comment_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                $action = 'remove_' . $type;
            } else {
                // Nếu đang like mà click dislike (hoặc ngược lại) thì chuyển đổi
                $update_sql = "UPDATE user_comment_likes SET like_type = ? WHERE user_id = ? AND comment_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sii", $type, $user_id, $comment_id);
                $update_stmt->execute();
                $update_stmt->close();
                $action = 'switch_to_' . $type;
            }
        } else {
            // Chưa có thì thêm mới
            $insert_sql = "INSERT INTO user_comment_likes (user_id, comment_id, like_type) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iis", $user_id, $comment_id, $type);
            $insert_stmt->execute();
            $insert_stmt->close();
            $action = 'add_' . $type;
        }
        
        $check_stmt->close();
        
        // Cập nhật số lượng likes/dislikes trong bảng binh_luan
        $count_likes_sql = "SELECT COUNT(*) as count FROM user_comment_likes WHERE comment_id = ? AND like_type = 'like'";
        $count_dislikes_sql = "SELECT COUNT(*) as count FROM user_comment_likes WHERE comment_id = ? AND like_type = 'dislike'";
        
        $likes_stmt = $conn->prepare($count_likes_sql);
        $likes_stmt->bind_param("i", $comment_id);
        $likes_stmt->execute();
        $likes_count = $likes_stmt->get_result()->fetch_assoc()['count'];
        $likes_stmt->close();
        
        $dislikes_stmt = $conn->prepare($count_dislikes_sql);
        $dislikes_stmt->bind_param("i", $comment_id);
        $dislikes_stmt->execute();
        $dislikes_count = $dislikes_stmt->get_result()->fetch_assoc()['count'];
        $dislikes_stmt->close();
        
        // Cập nhật vào bảng binh_luan
        $update_comment_sql = "UPDATE binh_luan SET cmt_like = ?, cmt_dislike = ? WHERE id_binh_luan = ?";
        $update_stmt = $conn->prepare($update_comment_sql);
        $update_stmt->bind_param("iii", $likes_count, $dislikes_count, $comment_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        return ['success' => true, 'likes' => $likes_count, 'dislikes' => $dislikes_count, 'action' => $action];
    } catch (Exception $e) {
        error_log("toggleCommentLike error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
    }
}
?>

<?php
// XỬ LÝ YÊU THÍCH PHIM

// Hàm kiểm tra phim có trong danh sách yêu thích không
function isMovieFavorite($user_id, $movie_id, $conn) {
    $sql = "SELECT id_yeu_thich FROM yeu_thich WHERE id_nguoi_dung = ? AND id_phim = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $movie_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $is_favorite = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $is_favorite;
    }
    
    return false;
}

// Hàm lấy danh sách phim yêu thích của user
function getUserFavorites($user_id, $conn) {
    $favorites = array();
    $sql = "SELECT id_phim FROM yeu_thich WHERE id_nguoi_dung = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $favorites[] = $row['id_phim'];
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return $favorites;
}

//Hàm lấy danh sách phim liên quan dựa trên thể loại và quốc gia
function getRelatedMovies($conn, $movie_id, $country_id, $genre_ids) {
    if (empty($genre_ids)) return []; // Không có thể loại thì không lấy được phim liên quan

    // Tạo placeholders cho mảng thể loại
    $placeholders = implode(',', array_fill(0, count($genre_ids), '?'));

    $sql = "SELECT DISTINCT p.*, qg.ten_quoc_gia 
            FROM phim p 
            JOIN quoc_gia qg ON p.id_quoc_gia = qg.id_quoc_gia 
            WHERE p.id_phim != ? 
              AND p.id_quoc_gia = ? 
              AND p.id_phim IN (
                  SELECT id_phim FROM phim_the_loai 
                  WHERE id_the_loai IN ($placeholders)
              ) 
            ORDER BY p.luot_danh_gia DESC, p.diem_trung_binh DESC 
            LIMIT 6";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Gom tất cả tham số lại: [id phim, id quốc gia, danh sách id thể loại]
        $params = array_merge([$movie_id, $country_id], $genre_ids);
        $types = str_repeat('i', count($params)); // tất cả đều là số nguyên

        // Gọi bind_param bằng biến tham chiếu
        $stmt->bind_param(...array_merge([$types], refValues($params)));

        $stmt->execute();
        $result = $stmt->get_result();

        $movies = [];
        while ($row = $result->fetch_assoc()) {
            $movies[] = $row;
        }
        $stmt->close();
        return $movies;
    }
    return [];
}

// Hàm phụ trợ giúp bind_param với số lượng tham số động
function refValues($arr) {
    $refs = [];
    foreach ($arr as $key => $value) {
        $refs[$key] = &$arr[$key];
    }
    return $refs;
}

// Hàm hiển thị thông báo
function showMessage() {
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
        unset($_SESSION['success_message']);
    }
    
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-error">' . $_SESSION['error_message'] . '</div>';
        unset($_SESSION['error_message']);
    }
}
?>
