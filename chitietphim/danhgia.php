<?php
require_once 'C:\xamppp\htdocs\CINEMAT\config\function.php';
require_once 'C:\xamppp\htdocs\CINEMAT\config\config.php';

$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : null;



// Lấy thông tin phim
function getMovieInfo($conn, $id_phim) {
    $sql = "SELECT * FROM phim WHERE id_phim = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_phim);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Lấy danh sách đánh giá với bình luận
function getDanhGiaWithComments($conn, $id_phim, $filter = 'all', $sort = 'newest') {
    $where_condition = "WHERE dg.id_phim = ?";
    $params = [$id_phim];
    $param_types = 'i';
    
    // Thêm điều kiện lọc theo rating
    if ($filter !== 'all' && is_numeric($filter)) {
        $where_condition .= " AND dg.diem = ?";
        $params[] = (int)$filter;
        $param_types .= 'i';
    }
    
    // Thêm điều kiện sắp xếp
    $order_by = "ORDER BY dg.thoi_gian DESC";
    switch($sort) {
        case 'oldest':
            $order_by = "ORDER BY dg.thoi_gian ASC";
            break;
        case 'most_liked':
            $order_by = "ORDER BY dg.like DESC";
            break;
        case 'highest_rating':
            $order_by = "ORDER BY dg.diem DESC, dg.thoi_gian DESC";
            break;
        case 'lowest_rating':
            $order_by = "ORDER BY dg.diem ASC, dg.thoi_gian DESC";
            break;
    }
    
    $sql = "SELECT dg.*, nd.ten_nguoi_dung 
            FROM danh_gia dg 
            JOIN nguoi_dung nd ON dg.id_nguoi_dung = nd.id_nguoi_dung 
            $where_condition
            $order_by";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $danhgias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Với mỗi đánh giá, lấy bình luận phân cấp
    foreach ($danhgias as &$dg) {
        $dg['binh_luan'] = getCommentTree($conn, $dg['id_danh_gia']);
    }

    return $danhgias;
}

// Truy vấn bình luận theo id_danh_gia và xây dựng cây
function getCommentTree($conn, $id_danh_gia) {
    $sql = "SELECT bl.*, nd.ten_nguoi_dung 
            FROM binh_luan bl 
            JOIN nguoi_dung nd ON bl.id_nguoi_dung = nd.id_nguoi_dung 
            WHERE bl.id_danh_gia = ? AND bl.bi_chan = 0
            ORDER BY bl.thoi_gian ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_danh_gia);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $tree = [];
    foreach ($rows as $row) {
        $tree[$row['parent_id']][] = $row;
    }
    return $tree;
}

// Hiển thị cây bình luận đệ quy
function renderCommentTree($commentTree, $parent_id = null, $level = 0, $is_logged_in = false, $current_user_id = null) {
    if (!isset($commentTree[$parent_id])) {
        return '';
    }
    
    $html = '';
    foreach ($commentTree[$parent_id] as $comment) {
        $margin_class = $level > 0 ? 'ml-6' : 'ml-11';
        $avatar_size = $level > 0 ? 'w-7 h-7' : 'w-8 h-8';
        $text_size = $level > 0 ? 'text-xs' : 'text-sm';
        
        $html .= '<div class="mt-' . ($level > 0 ? '3' : '4') . '">';
        $html .= '<div class="flex justify-between items-start">';
        $html .= '<div class="flex gap-3">';
        $html .= '<div class="' . $avatar_size . ' user-avatar avatar-border">';
        $html .= '<i class="fas fa-user ' . $text_size . '"></i>';
        $html .= '</div>';
        $html .= '<div>';
        $html .= '<div class="font-bold">' . htmlspecialchars($comment['ten_nguoi_dung']) . '</div>';
        $html .= '<span class="text-[#b3b3b3] text-sm">' . timeAgo($comment['thoi_gian']) . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Menu dropdown chỉ hiển thị cho chủ sở hữu comment hoặc admin
        if ($is_logged_in && ($current_user_id == $comment['id_nguoi_dung'] || $_SESSION['role'] == 'admin')) {
            $html .= '<div class="relative">';
            $html .= '<button class="text-[#b3b3b3] hover:text-white p-1 comment-menu-btn" data-comment-id="' . $comment['id_binh_luan'] . '">';
            $html .= '<i class="fas fa-ellipsis-v"></i>';
            $html .= '</button>';
            $html .= '<div class="absolute right-0 mt-2 w-48 bg-[#252525] rounded shadow-lg z-10 dropdown-menu hidden" id="commentMenu' . $comment['id_binh_luan'] . '">';
            $html .= '<ul class="py-1">';
            if ($current_user_id == $comment['id_nguoi_dung'] || $_SESSION['role'] == 'admin') {
                $html .= '<li class="dropdown-item px-4 py-2 cursor-pointer text-[#e50914] delete-comment" data-comment-id="' . $comment['id_binh_luan'] . '">Xóa</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        $html .= '<div class="mt-1 ' . $margin_class . '">';
        $html .= '<p class="text-[#d2d2d2] text-sm">' . nl2br(htmlspecialchars($comment['noi_dung'])) . '</p>';
        
        $html .= '<div class="flex items-center gap-4 mt-2">';
        $html .= '<button class="flex items-center gap-1 text-[#b3b3b3] hover:text-white text-sm comment-like-btn" data-comment-id="' . $comment['id_binh_luan'] . '" data-count="' . $comment['cmt_like'] . '">';
        $html .= '<i class="far fa-thumbs-up"></i> <span class="like-count">' . $comment['cmt_like'] . '</span>';
        $html .= '</button>';
        $html .= '<button class="flex items-center gap-1 text-[#b3b3b3] hover:text-white text-sm comment-dislike-btn" data-comment-id="' . $comment['id_binh_luan'] . '" data-count="' . $comment['cmt_dislike'] . '">';
        $html .= '<i class="far fa-thumbs-down"></i> <span class="dislike-count">' . $comment['cmt_dislike'] . '</span>';
        $html .= '</button>';
        
        if ($is_logged_in && $level < 3) { // Giới hạn 3 cấp độ reply
            $html .= '<button class="text-[#b3b3b3] hover:text-white text-sm reply-comment-btn" data-comment-id="' . $comment['id_binh_luan'] . '" data-review-id="' . $comment['id_danh_gia'] . '">';
            $html .= 'Trả lời';
            $html .= '</button>';
        }
        $html .= '</div>';
        
        // Form reply (ẩn mặc định)

        if ($is_logged_in && $level < 3) {
            $html .= '<div class="mt-3 hidden comment-reply-form" id="commentReplyForm' . $comment['id_binh_luan'] . '">';
            $html .= '<div class="flex gap-3">';
            $html .= '<div class="w-7 h-7 user-avatar avatar-border">';
            $html .= '<i class="fas fa-user text-xs"></i>';
            $html .= '</div>';
            // SỬA: data-parent-id và data-review-id thành camelCase cho JavaScript
            $html .= '<form class="comment-form flex-1" data-review-id="' . $comment['id_danh_gia'] . '" data-parent-id="' . $comment['id_binh_luan'] . '">';
            $html .= '<textarea rows="2" class="comment-content netflix-textarea w-full text-sm" placeholder="Viết phản hồi của bạn..."></textarea>';
            $html .= '<div class="flex justify-end gap-2 mt-2">';
            $html .= '<button type="button" class="text-[#b3b3b3] hover:text-white px-3 py-1 text-sm cancel-comment-reply" data-comment-id="' . $comment['id_binh_luan'] . '">Hủy</button>';
            $html .= '<button type="submit" class="netflix-btn netflix-btn-red px-3 py-1 rounded text-sm">Gửi</button>';
            $html .= '</div>';
            $html .= '</form>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Hiển thị replies đệ quy
        $html .= renderCommentTree($commentTree, $comment['id_binh_luan'], $level + 1, $is_logged_in, $current_user_id);
        
        $html .= '</div>';
        $html .= '</div>';
    }
    
    return $html;
}

// Hàm tính thời gian đã qua
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'vừa xong';
    if ($time < 3600) return floor($time/60) . ' phút trước';
    if ($time < 86400) return floor($time/3600) . ' giờ trước';
    if ($time < 2592000) return floor($time/86400) . ' ngày trước';
    if ($time < 31536000) return floor($time/2592000) . ' tháng trước';
    return floor($time/31536000) . ' năm trước';
}

// Lấy thông tin phim
$movie = getMovieInfo($conn, $movie_id);
if (!$movie) {
    die("Không tìm thấy phim.");
}

// Lấy tham số filter và sort
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Lấy danh sách đánh giá
$danhgias = getDanhGiaWithComments($conn, $movie_id, $filter, $sort);




?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Netflix+Sans:wght@300;400;500;700;900&display=swap');
        
        :root {
            --netflix-black: #141414;
            --netflix-dark: #181818;
            --netflix-red: #e50914;
            --netflix-light-red: #f40612;
            --netflix-gray: #808080;
            --netflix-light-gray: #b3b3b3;
            --netflix-yellow: #f8b739;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Netflix Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: var(--netflix-black);
            color: #ffffff;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }
        
        .review-item {
            border-left: 3px solid var(--netflix-red);
            transition: all 0.3s ease;
        }
        
        .review-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .reply-container {
            margin-left: 40px;
            border-left: 1px solid #333;
            padding-left: 12px;
        }
        
        .truncate-text {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .expanded {
            -webkit-line-clamp: unset;
        }
        
        .netflix-btn {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .netflix-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            opacity: 0;
            transition: transform 0.5s, opacity 0.3s;
        }
        
        .netflix-btn:hover::after {
            transform: translate(-50%, -50%) scale(2);
            opacity: 1;
        }
        
        .netflix-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .netflix-btn-red {
            background-color: var(--netflix-red);
            color: white;
        }
        
        .netflix-btn-red:hover {
            background-color: var(--netflix-light-red);
        }
        
        .netflix-textarea {
            background-color: #333;
            border: none;
            border-radius: 4px;
            color: white;
            padding: 10px 15px;
            transition: all 0.3s;
            resize: none;
        }
        
        .netflix-textarea:focus {
            background-color: #444;
            outline: none;
            box-shadow: 0 0 0 2px rgba(229, 9, 20, 0.5);
        }
        
        .avatar-border {
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .avatar-border:hover {
            border-color: var(--netflix-red);
        }
        
        .like-animation {
            animation: likeEffect 0.5s;
        }
        
        @keyframes likeEffect {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.3);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .dropdown-menu {
            transform-origin: top right;
            transform: scale(0.95);
            opacity: 0;
            visibility: hidden;
            transition: transform 0.2s, opacity 0.2s, visibility 0.2s;
        }
        
        .dropdown-menu.show {
            display: block;
            transform: scale(1);
            opacity: 1;
            visibility: visible;
        }
        
        .dropdown-item {
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background-color: rgba(255,255,255,0.1);
            padding-left: 20px;
        }
        
        .user-avatar {
            background-color: #333;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.5s forwards;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-in {
            opacity: 0;
            animation: slideIn 0.5s forwards;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .filter-option {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-option:hover {
            color: white;
        }
        
        .filter-option.active {
            color: white;
            font-weight: bold;
        }
        
        .filter-option.active::after {
            content: '';
            display: block;
            width: 100%;
            height: 2px;
            background-color: var(--netflix-red);
            margin-top: 2px;
        }
        
        .review-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--netflix-light-gray);
        }
        
        .review-badge.verified {
            background-color: rgba(0, 128, 0, 0.2);
            color: #4ade80;
        }
        
        .review-badge.top {
            background-color: rgba(229, 9, 20, 0.2);
            color: var(--netflix-red);
        }
        
        .review-stats {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--netflix-light-gray);
            font-size: 12px;
        }
        
        .review-stats-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .review-date {
            color: var(--netflix-light-gray);
            font-size: 12px;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .review-sort {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--netflix-light-gray);
        }
        
        .review-sort-label {
            font-size: 14px;
        }
        
        .review-sort-select {
            background-color: #333;
            border: none;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .review-sort-select:focus {
            outline: none;
        }
        
        .review-filter {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
            border-bottom: 1px solid #333;
            padding-bottom: 8px;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
            color: var(--netflix-light-gray);
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state-text {
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .empty-state-subtext {
            font-size: 14px;
            max-width: 400px;
            text-align: center;
        }
    </style>

    <div class="container mx-auto px-4 py-8">
        <!-- Danh sách đánh giá -->
        <div class="fade-in" style="animation-delay: 0.2s;">
            <div class="review-header">
                <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-star text-yellow-400"></i> 
                    Tất cả đánh giá (<?php echo count($danhgias); ?>)
                </h2>
            </div>
            
            <div class="review-filter">
                <div class="filter-option <?php echo $filter == 'all' ? 'active' : ''; ?>" data-filter="all">Tất cả</div>
                <div class="filter-option <?php echo $filter == '5' ? 'active' : ''; ?>" data-filter="5">5 sao</div>
                <div class="filter-option <?php echo $filter == '4' ? 'active' : ''; ?>" data-filter="4">4 sao</div>
                <div class="filter-option <?php echo $filter == '3' ? 'active' : ''; ?>" data-filter="3">3 sao</div>
                <div class="filter-option <?php echo $filter == '2' ? 'active' : ''; ?>" data-filter="2">2 sao</div>
                <div class="filter-option <?php echo $filter == '1' ? 'active' : ''; ?>" data-filter="1">1 sao</div>
            </div>
            
            <!-- Danh sách đánh giá -->
            <div class="space-y-6" id="reviewsList">
                <?php if (empty($danhgias)): ?>
                    <div class="empty-state fade-in">
                        <div class="empty-state-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="empty-state-text">Chưa có đánh giá nào</div>
                        <div class="empty-state-subtext">Hãy là người đầu tiên đánh giá bộ phim này!</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($danhgias as $index => $danhgia): ?>
                        <div class="bg-[#181818] p-6 rounded-lg review-item slide-in" 
                             data-rating="<?php echo $danhgia['diem']; ?>" 
                             style="animation-delay: <?php echo ($index * 0.1); ?>s;">
                            
                            <div class="flex justify-between items-start">
                                <div class="flex gap-3">
                                    <div class="w-12 h-12 user-avatar avatar-border">
                                        <i class="fas fa-user text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold flex items-center gap-2">
                                            <?php echo htmlspecialchars($danhgia['ten_nguoi_dung']); ?>
                                        </div>
                                        <div class="flex text-yellow-400">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span><?php echo $i <= $danhgia['diem'] ? '★' : '☆'; ?></span>
                                            <?php endfor; ?>
                                            <span class="text-[#b3b3b3] ml-2"><?php echo timeAgo($danhgia['thoi_gian']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($is_logged_in && ($current_user_id == $danhgia['id_nguoi_dung'] || $_SESSION['role'] == 'admin')): ?>
                                <div class="relative group">
                                    <button class="text-[#b3b3b3] hover:text-white p-1 review-menu-btn" data-review-id="<?php echo $danhgia['id_danh_gia']; ?>">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="absolute right-0 mt-2 w-48 bg-[#252525] rounded shadow-lg z-10 dropdown-menu" id="reviewMenu<?php echo $danhgia['id_danh_gia']; ?>">
                                        <ul class="py-1">
                                            <?php if ($current_user_id == $danhgia['id_nguoi_dung'] || $_SESSION['role'] == 'admin'): ?>
                                                <li class="dropdown-item px-4 py-2 cursor-pointer text-[#e50914] delete-review" data-review-id="<?php echo $danhgia['id_danh_gia']; ?>">Xóa</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($danhgia['nhan_xet'])): ?>
                            <div class="mt-3">
                                <p class="text-[#d2d2d2] review-text truncate-text" id="review<?php echo $danhgia['id_danh_gia']; ?>">
                                    <?php echo nl2br(htmlspecialchars($danhgia['nhan_xet'])); ?>
                                </p>
                                <?php if (strlen($danhgia['nhan_xet']) > 300): ?>
                                <button class="text-[#e50914] hover:text-[#f40612] mt-2 text-sm font-medium toggle-review" data-review="review<?php echo $danhgia['id_danh_gia']; ?>">Xem thêm</button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center gap-4 mt-4">
                                <button class="flex items-center gap-1 text-[#b3b3b3] hover:text-white review-like-btn" 
                                        data-review-id="<?php echo $danhgia['id_danh_gia']; ?>" 
                                        data-count="<?php echo $danhgia['like']; ?>">
                                    <i class="far fa-thumbs-up"></i> 
                                    <span class="like-count"><?php echo $danhgia['like']; ?></span>
                                </button>
                                <button class="flex items-center gap-1 text-[#b3b3b3] hover:text-white review-dislike-btn" 
                                        data-review-id="<?php echo $danhgia['id_danh_gia']; ?>" 
                                        data-count="<?php echo $danhgia['dislike']; ?>">
                                    <i class="far fa-thumbs-down"></i> 
                                    <span class="dislike-count"><?php echo $danhgia['dislike']; ?></span>
                                </button>
                                
                                <?php if ($is_logged_in): ?>
                                <button class="text-[#b3b3b3] hover:text-white reply-btn" data-review-id="<?php echo $danhgia['id_danh_gia']; ?>">
                                    Trả lời
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Form bình luận (ẩn mặc định) -->
                            <?php if ($is_logged_in): ?>
                            <div class="mt-4 hidden" id="replyForm<?php echo $danhgia['id_danh_gia']; ?>">
                                <div class="flex gap-3">
                                    <div class="w-8 h-8 user-avatar avatar-border">
                                        <i class="fas fa-user text-xs"></i>
                                    </div>
                                    <form id="comment-form" class="flex-1">
                                        <textarea 
                                            id="comment-content"
                                            rows="3" 
                                            class="netflix-textarea w-full text-sm"
                                            placeholder="Viết bình luận của bạn..."
                                            data-review-id="<?php echo $danhgia['id_danh_gia']; ?>"
                                        ></textarea>
                                        <input type="hidden" id="review-id" value="<?php echo $comment['id_danh_gia']; ?>">
                                        <input type="hidden" id="parent-id" value="<?php echo $comment['parent-id']; ?>"> <!-- Nếu là bình luận gốc thì để trống -->
                                        <div class="flex justify-end gap-2 mt-2">
                                            <button class="text-[#b3b3b3] hover:text-white px-3 py-1 text-sm cancel-reply" data-review-id="<?php echo $danhgia['id_danh_gia']; ?>">Hủy</button>
                                            <button type='submit' class="netflix-btn netflix-btn-red px-3 py-1 rounded text-sm submit-comment" data-review-id="<?php echo $danhgia['id_danh_gia']; ?>">Gửi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Bình luận -->
                            <?php if (isset($danhgia['binh_luan'][null]) && !empty($danhgia['binh_luan'][null])): ?>
                            <div class="reply-container mt-4 fade-in" style="animation-delay: <?php echo ($index * 0.1 + 0.3); ?>s;">
                                <?php echo renderCommentTree($danhgia['binh_luan'], null, 0, $is_logged_in, $current_user_id); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    console.log('Movie ID:', movieId); // Debug
    </script>


    <script>
        // DOM loaded events
        document.addEventListener('DOMContentLoaded', function() {
            initializeAnimations();
            initializeDropdowns();
            initializeReviewInteractions();
            initializeCommentInteractions();
            initializeFilters();
        });




        // Initialize animations
        function initializeAnimations() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach(element => {
                setTimeout(() => {
                    element.style.opacity = '1';
                }, 100);
            });
            
            const slideElements = document.querySelectorAll('.slide-in');
            slideElements.forEach(element => {
                setTimeout(() => {
                    element.style.opacity = '1';
                }, 300);
            });
        }

        // Initialize dropdown menus
        function initializeDropdowns() {
            // Review menu dropdowns (code cũ giữ nguyên)
            document.querySelectorAll('.review-menu-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const reviewId = this.getAttribute('data-review-id');
                    const menu = document.getElementById(`reviewMenu${reviewId}`);
                    
                    // Close all other menus
                    document.querySelectorAll('.dropdown-menu').forEach(m => {
                        if (m.id !== `reviewMenu${reviewId}`) m.classList.remove('show');
                    });
                    
                    menu.classList.toggle('show');
                });
            });

            // Comment menu dropdowns (code cũ giữ nguyên)
            document.querySelectorAll('.comment-menu-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const commentId = this.getAttribute('data-comment-id');
                    const menu = document.getElementById(`commentMenu${commentId}`);
                    
                    // Close all other menus
                    document.querySelectorAll('.dropdown-menu').forEach(m => {
                        if (m.id !== `commentMenu${commentId}`) m.classList.remove('show');
                    });
                    
                    menu.classList.toggle('show');
                });
            });

            // THÊM MỚI: Xử lý xóa đánh giá
            document.querySelectorAll('.delete-review').forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.getAttribute('data-review-id');
                    if (confirm('Bạn có chắc chắn muốn xóa đánh giá này?')) {
                        deleteReview(reviewId);
                    }
                });
            });

            // THÊM MỚI: Xử lý xóa bình luận
            document.querySelectorAll('.delete-comment').forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    if (confirm('Bạn có chắc chắn muốn xóa bình luận này?')) {
                        deleteComment(commentId);
                    }
                });
            });

            // Close dropdowns when clicking outside (code cũ giữ nguyên)
            document.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            });
        }

        // Initialize review interactions
        function initializeReviewInteractions() {
            // Toggle review text expansion
            document.querySelectorAll('.toggle-review').forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.getAttribute('data-review');
                    const reviewElement = document.getElementById(reviewId);
                    
                    reviewElement.classList.toggle('expanded');
                    
                    if (reviewElement.classList.contains('expanded')) {
                        this.textContent = 'Thu gọn';
                    } else {
                        this.textContent = 'Xem thêm';
                    }
                });
            });

            // Review like/dislike
            document.querySelectorAll('.review-like-btn').forEach(button => {
                button.addEventListener('click', function() {
                    handleReviewLike(this, 'like');
                });
            });

            document.querySelectorAll('.review-dislike-btn').forEach(button => {
                button.addEventListener('click', function() {
                    handleReviewLike(this, 'dislike');
                });
            });

            // Toggle reply forms
            document.querySelectorAll('.reply-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.getAttribute('data-review-id');
                    const replyForm = document.getElementById(`replyForm${reviewId}`);
                    
                    // Hide all other reply forms
                    document.querySelectorAll('[id^="replyForm"]').forEach(form => {
                        if (form.id !== `replyForm${reviewId}`) {
                            form.classList.add('hidden');
                        }
                    });
                    
                    if (replyForm.classList.contains('hidden')) {
                        replyForm.classList.remove('hidden');
                        animateShow(replyForm);
                    } else {
                        animateHide(replyForm);
                    }
                });
            });

            // Cancel reply
            document.querySelectorAll('.cancel-reply').forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.getAttribute('data-review-id');
                    const replyForm = document.getElementById(`replyForm${reviewId}`);
                    animateHide(replyForm);
                });
            });

            // Submit comment
            document.querySelectorAll('.submit-comment').forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.getAttribute('data-review-id');
                    const textarea = document.querySelector(`textarea[data-review-id="${reviewId}"]`);
                    const content = textarea.value.trim();
                    
                    if (content) {
                        submitComment(reviewId, content, null);
                        textarea.value = '';
                        const replyForm = document.getElementById(`replyForm${reviewId}`);
                        animateHide(replyForm);
                    }
                });
            });
        }

        // SỬA LẠI HÀM submitComment - thay thế hàm cũ
        function submitComment(reviewId, content, parentId = null) {
            const formData = new FormData();
            formData.append('action', 'add_comment');
            formData.append('review_id', reviewId);
            formData.append('content', content);
            if (parentId) {
                formData.append('parent_id', parentId);
            }

            fetch('ajax_handlers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Thêm bình luận thành công!', 'success');
                    // Reload trang để hiển thị bình luận mới
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Có lỗi xảy ra!', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Có lỗi xảy ra khi gửi bình luận!', 'error');
            });
        }

        // Initialize comment interactions
        function initializeCommentInteractions() {
            // Comment like/dislike
            document.querySelectorAll('.comment-like-btn').forEach(button => {
                button.addEventListener('click', function() {
                    handleCommentLike(this, 'like');
                });
            });

            document.querySelectorAll('.comment-dislike-btn').forEach(button => {
                button.addEventListener('click', function() {
                    handleCommentLike(this, 'dislike');
                });
            });

            // Reply to comment
            document.querySelectorAll('.reply-comment-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    const replyForm = document.getElementById(`commentReplyForm${commentId}`);
                    
                    // Hide all other comment reply forms
                    document.querySelectorAll('.comment-reply-form').forEach(form => {
                        if (form.id !== `commentReplyForm${commentId}`) {
                            form.classList.add('hidden');
                        }
                    });
                    
                    if (replyForm.classList.contains('hidden')) {
                        replyForm.classList.remove('hidden');
                        animateShow(replyForm);
                    } else {
                        animateHide(replyForm);
                    }
                });
            });

            // Cancel comment reply
            document.querySelectorAll('.cancel-comment-reply').forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    const replyForm = document.getElementById(`commentReplyForm${commentId}`);
                    animateHide(replyForm);
                });
            });

            // Submit comment reply
            document.querySelectorAll('.submit-comment-reply').forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    const textarea = document.querySelector(`#commentReplyForm${commentId} textarea`);
                    const content = textarea.value.trim();
                    const reviewId = textarea.getAttribute('data-review-id');
                    const parentId = textarea.getAttribute('data-parent-id');
                    
                    if (content) {
                        submitComment(reviewId, content, parentId);
                        textarea.value = '';
                        const replyForm = document.getElementById(`commentReplyForm${commentId}`);
                        animateHide(replyForm);
                    }
                });
            });
        }

        // Initialize filters
        function initializeFilters() {
            // Filter options
            document.querySelectorAll('.filter-option').forEach(option => {
                option.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    updateFilter(filter);
                });
            });

            // Sort select
            document.getElementById('sortSelect').addEventListener('change', function() {
                const sort = this.value;
                updateSort(sort);
            });
        }

        // SỬA LẠI HÀM updateReviewReaction - thay thế hàm cũ
        function updateReviewReaction(reviewId, type, isActive) {
            const formData = new FormData();
            formData.append('action', 'update_review_reaction');
            formData.append('review_id', reviewId);
            formData.append('type', type);
            formData.append('is_active', isActive);

            fetch('ajax_handlers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Cập nhật số lượng hiển thị
                    const button = document.querySelector(`[data-review-id="${reviewId}"].review-${type}-btn`);
                    if (button) {
                        button.setAttribute('data-count', data.count);
                        button.querySelector(`.${type}-count`).textContent = data.count;
                    }
                } else {
                    showNotification(data.message || 'Có lỗi xảy ra!', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Có lỗi xảy ra!', 'error');
            });
        }


        
    // SỬA LẠI HÀM updateCommentReaction - thay thế hàm cũ  
    function updateCommentReaction(commentId, type, isActive) {
        const formData = new FormData();
        formData.append('action', 'update_comment_reaction');
        formData.append('comment_id', commentId);
        formData.append('type', type);
        formData.append('is_active', isActive);

        fetch('ajax_handlers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Cập nhật số lượng hiển thị
                const button = document.querySelector(`[data-comment-id="${commentId}"].comment-${type}-btn`);
                if (button) {
                    button.setAttribute('data-count', data.count);
                    button.querySelector(`.${type}-count`).textContent = data.count;
                }
            } else {
                showNotification(data.message || 'Có lỗi xảy ra!', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Có lỗi xảy ra!', 'error');
        });
    }

    // THÊM MỚI: Xóa đánh giá
    function deleteReview(reviewId) {
        const formData = new FormData();
        formData.append('action', 'delete_review');
        formData.append('review_id', reviewId);

        fetch('ajax_handlers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Xóa đánh giá thành công!', 'success');
                // Xóa element khỏi DOM với animation
                const reviewElement = document.querySelector(`[data-review-id="${reviewId}"]`).closest('.review-item');
                reviewElement.style.transition = 'opacity 0.5s, transform 0.5s';
                reviewElement.style.opacity = '0';
                reviewElement.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    reviewElement.remove();
                    // Cập nhật số lượng đánh giá hiển thị
                    updateReviewCount();
                }, 500);
            } else {
                showNotification(data.message || 'Có lỗi xảy ra khi xóa đánh giá!', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Có lỗi xảy ra khi xóa đánh giá!', 'error');
        });
    }

    // THÊM MỚI: Xóa bình luận
    function deleteComment(commentId) {
        const formData = new FormData();
        formData.append('action', 'delete_comment');
        formData.append('comment_id', commentId);

        fetch('ajax_handlers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Xóa bình luận thành công!', 'success');
                // Xóa element khỏi DOM với animation
                const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`).closest('.mt-3, .mt-4');
                if (commentElement) {
                    commentElement.style.transition = 'opacity 0.5s, transform 0.5s';
                    commentElement.style.opacity = '0';
                    commentElement.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        commentElement.remove();
                    }, 500);
                }
            } else {
                showNotification(data.message || 'Có lỗi xảy ra khi xóa bình luận!', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Có lỗi xảy ra khi xóa bình luận!', 'error');
        });
    }

    // THÊM MỚI: Hiển thị thông báo
    function showNotification(message, type = 'info') {
        // Tạo element thông báo
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-500 transform translate-x-full`;
        
        // Thêm class theo loại thông báo
        switch(type) {
            case 'success':
                notification.classList.add('bg-green-600', 'text-white');
                break;
            case 'error':
                notification.classList.add('bg-red-600', 'text-white');
                break;
            default:
                notification.classList.add('bg-blue-600', 'text-white');
        }
        
        notification.innerHTML = `
            <div class="flex items-center gap-2">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animation hiển thị
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);
        
        // Tự động ẩn sau 3 giây
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 500);
        }, 3000);
    }


        // Handle review like/dislike
        function handleReviewLike(button, type) {
            const reviewId = button.getAttribute('data-review-id');
            const countElement = button.querySelector(type === 'like' ? '.like-count' : '.dislike-count');
            let count = parseInt(button.getAttribute('data-count'));
            
            if (button.classList.contains('active')) {
                // Remove like/dislike
                count--;
                button.classList.remove('active');
                button.querySelector('i').className = `far fa-thumbs-${type === 'like' ? 'up' : 'down'}`;
            } else {
                // Add like/dislike
                count++;
                button.classList.add('active');
                button.querySelector('i').className = `fas fa-thumbs-${type === 'like' ? 'up' : 'down'} like-animation`;
                
                // Remove opposite reaction if active
                const oppositeType = type === 'like' ? 'dislike' : 'like';
                const oppositeBtn = button.parentElement.querySelector(`.review-${oppositeType}-btn`);
                if (oppositeBtn && oppositeBtn.classList.contains('active')) {
                    let oppositeCount = parseInt(oppositeBtn.getAttribute('data-count'));
                    oppositeCount--;
                    oppositeBtn.setAttribute('data-count', oppositeCount);
                    oppositeBtn.querySelector(`.${oppositeType}-count`).textContent = oppositeCount;
                    oppositeBtn.classList.remove('active');
                    oppositeBtn.querySelector('i').className = `far fa-thumbs-${oppositeType === 'like' ? 'up' : 'down'}`;
                }
            }
            
            button.setAttribute('data-count', count);
            countElement.textContent = count;
            
            // Send AJAX request to update database
            updateReviewReaction(reviewId, type, button.classList.contains('active'));
        }

        // Handle comment like/dislike
        function handleCommentLike(button, type) {
            const commentId = button.getAttribute('data-comment-id');
            const countElement = button.querySelector(type === 'like' ? '.like-count' : '.dislike-count');
            let count = parseInt(button.getAttribute('data-count'));
            
            if (button.classList.contains('active')) {
                count--;
                button.classList.remove('active');
                button.querySelector('i').className = `far fa-thumbs-${type === 'like' ? 'up' : 'down'}`;
            } else {
                count++;
                button.classList.add('active');
                button.querySelector('i').className = `fas fa-thumbs-${type === 'like' ? 'up' : 'down'} like-animation`;
                
                // Remove opposite reaction if active
                const oppositeType = type === 'like' ? 'dislike' : 'like';
                const oppositeBtn = button.parentElement.querySelector(`.comment-${oppositeType}-btn`);
                if (oppositeBtn && oppositeBtn.classList.contains('active')) {
                    let oppositeCount = parseInt(oppositeBtn.getAttribute('data-count'));
                    oppositeCount--;
                    oppositeBtn.setAttribute('data-count', oppositeCount);
                    oppositeBtn.querySelector(`.${oppositeType}-count`).textContent = oppositeCount;
                    oppositeBtn.classList.remove('active');
                    oppositeBtn.querySelector('i').className = `far fa-thumbs-${oppositeType === 'like' ? 'up' : 'down'}`;
                }
            }
            
            button.setAttribute('data-count', count);
            countElement.textContent = count;
            
            // Send AJAX request to update database
            updateCommentReaction(commentId, type, button.classList.contains('active'));
        }

        // Animation helpers
        function animateShow(element) {
            element.style.opacity = '0';
            setTimeout(() => {
                element.style.transition = 'opacity 0.3s ease';
                element.style.opacity = '1';
            }, 10);
        }

        function animateHide(element) {
            element.style.opacity = '0';
            setTimeout(() => {
                element.classList.add('hidden');
            }, 300);
        }

        // Update filter
        function updateFilter(filter) {
            const url = new URL(window.location);
            url.searchParams.set('filter', filter);
            window.location.href = url.toString();
        }
</script>