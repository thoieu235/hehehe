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

// Lấy dữ liệu quốc gia và thể loại
$countries = getCountries($conn);
$genres = getGenres($conn);

// Kiểm tra nếu form được gửi (người dùng bấm nút submit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['movieTitle']) && isset($_POST['country']) && isset($_POST['description']) && isset($_FILES['poster'])) {
    // Lấy dữ liệu từ form, nếu không có thì gán giá trị rỗng
    $ten_phim = isset($_POST['movieTitle']) ? trim($_POST['movieTitle']) : '';
    $quoc_gia = isset($_POST['country']) ? trim($_POST['country']) : '';
    $mo_ta = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Kiểm tra dữ liệu bắt buộc
    if (empty($ten_phim) || empty($quoc_gia) || empty($mo_ta)) {
        echo "<script>alert('Vui lòng điền đầy đủ thông tin bắt buộc!');</script>";
    } else {
        // Kiểm tra thể loại phim (phải chọn ít nhất 1 thể loại)
        if (!isset($_POST['genres']) || !is_array($_POST['genres']) || empty($_POST['genres'])) {
            echo "<script>alert('Vui lòng chọn ít nhất một thể loại phim!');</script>";
        } else {
            // Xử lý tải lên ảnh poster
            // Phần xử lý upload poster - CODE ĐÃ SỬA
            if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
                // Đường dẫn thư mục lưu ảnh (đường dẫn tuyệt đối)
                $upload_dir = 'C:/xamppp/htdocs/CINEMAT/photo/';
                
                // Kiểm tra và tạo thư mục nếu chưa có
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) {
                        echo "<script>alert('Không thể tạo thư mục lưu ảnh!');</script>";
                        exit();
                    }
                }
                
                // Kiểm tra loại file được phép
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                $file_extension = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_extension, $allowed_types)) {
                    echo "<script>alert('Chỉ cho phép tải lên file ảnh (JPG, JPEG, PNG, GIF)!');</script>";
                } else {
                    // Kiểm tra kích thước file (tối đa 5MB)
                    if ($_FILES['poster']['size'] > 5 * 1024 * 1024) {
                        echo "<script>alert('Kích thước file quá lớn! Tối đa 5MB.');</script>";
                    } else {
                        // Tạo tên file mới để tránh trùng lặp
                        $poster_name = time() . '_' . uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $poster_name;
                        
                        // Di chuyển file từ thư mục tạm vào thư mục lưu trữ
                        if (move_uploaded_file($_FILES['poster']['tmp_name'], $target_path)) {
                            $upload_success = true;
                            echo "<script>console.log('Upload thành công: " . $target_path . "');</script>";
                        } else {
                            echo "<script>alert('Lỗi khi tải lên ảnh poster! Vui lòng thử lại.');</script>";
                            $upload_success = false;
                        }
                    }
                }
            } else {
                // Xử lý các lỗi upload khác
                if (isset($_FILES['poster'])) {
                    $error_code = $_FILES['poster']['error'];
                    switch($error_code) {
                        case UPLOAD_ERR_NO_FILE:
                            echo "<script>alert('Vui lòng chọn ảnh poster!');</script>";
                            break;
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            echo "<script>alert('File quá lớn!');</script>";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            echo "<script>alert('File chỉ được upload một phần!');</script>";
                            break;
                        default:
                            echo "<script>alert('Lỗi upload không xác định!');</script>";
                    }
                }
                $upload_success = false;
            }
            
            // Chỉ tiếp tục thêm phim nếu upload ảnh thành công
            if ($upload_success && !empty($poster_name)) {
                // Tìm ID của quốc gia dựa trên tên quốc gia được chọn
                $sql_get_country_id = "SELECT id_quoc_gia FROM quoc_gia WHERE ten_quoc_gia = ?";
                $stmt = $conn->prepare($sql_get_country_id);
                
                if ($stmt) {
                    $stmt->bind_param("s", $quoc_gia);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $country_row = $result->fetch_assoc();
                    
                    // Nếu tìm thấy quốc gia
                    if ($country_row) {
                        $id_quoc_gia = $country_row['id_quoc_gia'];
                        
                        // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
                        $conn->begin_transaction();
                        
                        try {
                            // Thêm thông tin phim vào bảng phim
                            $sql_insert = "INSERT INTO phim (ten_phim, id_quoc_gia, mo_ta, poster) VALUES (?, ?, ?, ?)";
                            $stmt_insert = $conn->prepare($sql_insert);
                            
                            if ($stmt_insert) {
                                $stmt_insert->bind_param("siss", $ten_phim, $id_quoc_gia, $mo_ta, $poster_name);
                                
                                // Nếu thêm phim thành công
                                if ($stmt_insert->execute()) {
                                    $phim_id = $conn->insert_id;  // Lấy ID của phim vừa thêm
                                    
                                    // Xử lý thể loại phim
                                    $genre_success = true;
                                    foreach ($_POST['genres'] as $id_the_loai) {
                                        $id_the_loai = (int)$id_the_loai; // Ép kiểu an toàn
                                        
                                        // Chèn vào bảng phim_the_loai
                                        $sql_insert_genre = "INSERT INTO phim_the_loai (id_phim, id_the_loai) VALUES (?, ?)";
                                        $stmt_genre = $conn->prepare($sql_insert_genre);
                                        
                                        if ($stmt_genre) {
                                            $stmt_genre->bind_param("ii", $phim_id, $id_the_loai);
                                            if (!$stmt_genre->execute()) {
                                                $genre_success = false;
                                                break;
                                            }
                                            $stmt_genre->close();
                                        } else {
                                            $genre_success = false;
                                            break;
                                        }
                                    }
                                    
                                    if ($genre_success) {
                                        // Commit transaction
                                        $conn->commit();
                                        echo "<script>alert('Thêm phim thành công!'); window.location.href = 'admin_movie.php';</script>";
                                    } else {
                                        // Rollback transaction
                                        $conn->rollback();
                                        // Xóa file ảnh đã upload
                                        if (file_exists($target_path)) {
                                            unlink($target_path);
                                        }
                                        echo "<script>alert('Lỗi khi thêm thể loại phim!');</script>";
                                    }
                                } else {
                                    // Rollback transaction
                                    $conn->rollback();
                                    // Xóa file ảnh đã upload
                                    if (file_exists($target_path)) {
                                        unlink($target_path);
                                    }
                                    echo "<script>alert('Lỗi khi thêm phim: " . $conn->error . "');</script>";
                                }
                                $stmt_insert->close();
                            } else {
                                // Rollback transaction
                                $conn->rollback();
                                // Xóa file ảnh đã upload
                                if (file_exists($target_path)) {
                                    unlink($target_path);
                                }
                                echo "<script>alert('Lỗi khi chuẩn bị câu lệnh SQL!');</script>";
                            }
                        } catch (Exception $e) {
                            // Rollback transaction
                            $conn->rollback();
                            // Xóa file ảnh đã upload
                            if (file_exists($target_path)) {
                                unlink($target_path);
                            }
                            echo "<script>alert('Lỗi: " . $e->getMessage() . "');</script>";
                        }
                    } else {
                        // Xóa file ảnh đã upload nếu không tìm thấy quốc gia
                        if (file_exists($target_path)) {
                            unlink($target_path);
                        }
                        echo "<script>alert('Không tìm thấy quốc gia được chọn!');</script>";
                    }
                    $stmt->close();
                } else {
                    // Xóa file ảnh đã upload nếu lỗi SQL
                    if (file_exists($target_path)) {
                        unlink($target_path);
                    }
                    echo "<script>alert('Lỗi khi truy vấn cơ sở dữ liệu!');</script>";
                }
            }
        }
    }
}

//Xử lý thêm thể loại mới
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['genreName'])) {
    $genreName = trim($_POST['genreName']);
    
    // Kiểm tra tên thể loại không rỗng
    if (empty($genreName)) {
        echo "<script>alert('Vui lòng nhập tên thể loại!');</script>";
    } else {
        // Thêm thể loại mới vào cơ sở dữ liệu
        $sql_add_genre = "INSERT INTO the_loai(ten_the_loai) VALUES (?)";
        $stmt_add_genre = $conn->prepare($sql_add_genre);
        
        if ($stmt_add_genre) {
            $stmt_add_genre->bind_param("s", $genreName);
            if ($stmt_add_genre->execute()) {
                echo "<script>alert('Thêm thể loại thành công!');</script>";
                // Cập nhật lại danh sách thể loại
                $genres = getGenres($conn);
            } else {
                echo "<script>alert('Lỗi khi thêm thể loại: " . $conn->error . "');</script>";
            }
            $stmt_add_genre->close();
        } else {
            echo "<script>alert('Lỗi khi chuẩn bị câu lệnh SQL!');</script>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAT Admin Panel - Thêm phim mới</title>
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
        
        /* Custom checkbox */
        .custom-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }
        
        .custom-checkbox input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .checkmark {
            height: 20px;
            width: 20px;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .custom-checkbox:hover input ~ .checkmark {
            background-color: #f1f1f1;
        }
        
        .custom-checkbox input:checked ~ .checkmark {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .checkmark:after {
            content: "";
            display: none;
        }
        
        .custom-checkbox input:checked ~ .checkmark:after {
            display: block;
        }
        
        .custom-checkbox .checkmark:after {
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        /* Image preview */
        .image-preview {
            width: 100%;
            height: 300px;
            background-color: #f8f8f8;
            border: 2px dashed #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .image-preview.has-image {
            border: none;
        }
        
        .image-preview .placeholder {
            text-align: center;
            color: #888;
        }
        
        .image-preview .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .image-preview:hover .remove-btn {
            opacity: 1;
        }

         /* Add genre button */
        .add-genre-btn {
            display: flex;
            align-items: center;
            color: var(--primary);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .add-genre-btn:hover {
            color: #c70812;
        }

         /* Modal animation */
        .modal-enter {
            animation: fadeIn 0.3s ease-out;
        }
        
        .modal-content-enter {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen bg-white">
    <!-- Main content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Page title -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-center mt-[50px] mb-6">THÊM PHIM MỚI</h2>
        </div>
        
        <!-- Add movie form -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden p-6">
            <form id="addMovieForm" class="space-y-6" method="POST" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Left column - Poster upload -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Poster phim <span class="text-red-500">*</span></label>
                        <div class="image-preview" id="posterPreview">
                            <div class="placeholder">
                                <i class="bi bi-image text-4xl mb-2"></i>
                                <p>Chọn ảnh poster</p>
                                <p class="text-xs mt-1">Kích thước đề xuất: 500x750px</p>
                                <p class="text-xs mt-1">Tối đa: 5MB</p>
                            </div>
                            <div class="remove-btn" id="removePoster">
                                <i class="bi bi-x"></i>
                            </div>
                        </div>
                        <input type="file" id="posterUpload" name="poster" accept="image/*" class="hidden" required>
                        <button type="button" id="uploadBtn" class="mt-3 w-full py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#E50914]">
                            Chọn ảnh từ máy tính
                        </button>
                    </div>
                    
                    <!-- Right column - Movie details -->
                    <div class="md:col-span-2 space-y-6">
                        <!-- Movie title -->
                        <div>
                            <label for="movieTitle" class="block text-sm font-medium text-gray-700 mb-1">Tên phim <span class="text-red-500">*</span></label>
                            <input type="text" id="movieTitle" name="movieTitle" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#E50914] focus:border-[#E50914]">
                        </div>
                        
                        <!-- Country -->
                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Quốc gia <span class="text-red-500">*</span></label>
                            <select id="country" name="country" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#E50914] focus:border-[#E50914]">
                                <option value="">Chọn quốc gia</option>
                                <?php if (!empty($countries)): ?>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?= htmlspecialchars($country['ten_quoc_gia']) ?>">
                                            <?= htmlspecialchars($country['ten_quoc_gia']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>Không có dữ liệu quốc gia</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <!-- Genres -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-sm font-medium text-gray-700">Thể loại <span class="text-red-500">*</span></label>
                                <div class="add-genre-btn" id="addGenreBtn">
                                    <i class="bi bi-plus-circle mr-1"></i>
                                    <span>Thêm thể loại mới</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <?php if (!empty($genres)): ?>
                                    <?php foreach ($genres as $genre): ?>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" name="genres[]" value="<?= (int)$genre['id_the_loai'] ?>">
                                            <span class="checkmark"></span>
                                            <?= htmlspecialchars($genre['ten_the_loai']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-gray-500">Không có dữ liệu thể loại</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Mô tả phim <span class="text-red-500">*</span></label>
                            <textarea id="description" name="description" rows="5" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#E50914] focus:border-[#E50914]"></textarea>
                        </div>
                    </div>
                </div>                
                <!-- Form actions -->
                <div class="flex justify-end space-x-3 border-t border-gray-200 pt-6">
                    <button type="button" id="cancelButton" class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Hủy
                    </button>
                    <button type="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#E50914] hover:bg-[#c70812] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#E50914]">
                        Thêm phim
                    </button>
                </div>
            </form>
        </div>
    </main>

     <!-- Thêm thể loại -->
    <div id="addGenreModal" class="fixed inset-0 z-50 hidden modal-enter">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="relative bg-white rounded-lg max-w-md w-full p-6 overflow-hidden shadow-xl transform transition-all modal-content-enter">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Thêm thể loại mới</h3>
                    <p class="text-sm text-gray-500 mt-1">Nhập thông tin thể loại phim mới</p>
                </div>
                
                <form id="addGenreForm" class="space-y-4" acyion="" method="POST">
                    <!-- Genre name -->
                    <div>
                        <label for="genreName" class="block text-sm font-medium text-gray-700 mb-1">Tên thể loại <span class="text-red-500">*</span></label>
                        <input type="text" id="genreName" name="genreName" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#E50914] focus:border-[#E50914]">
                    </div>

                    <!-- Form actions -->
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" id="cancelGenreBtn" class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Hủy
                        </button>
                        <button type="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#E50914] hover:bg-[#c70812] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#E50914]">
                            Thêm thể loại
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>

        // Poster upload functionality
        // Poster upload functionality
        const posterUpload = document.getElementById('posterUpload');
        const uploadBtn = document.getElementById('uploadBtn');
        const posterPreview = document.getElementById('posterPreview');
        const removePoster = document.getElementById('removePoster');
        
        uploadBtn.addEventListener('click', () => {
            posterUpload.click();
        });
        
        posterUpload.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                // Kiểm tra kích thước file (5MB = 5 * 1024 * 1024 bytes)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Kích thước file quá lớn! Tối đa 5MB.');
                    this.value = '';
                    return;
                }
                
                // Kiểm tra định dạng file
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Chỉ cho phép tải lên file ảnh (JPG, JPEG, PNG, GIF)!');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Create image element
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.id = 'previewImage';
                    
                    // Clear placeholder and add image
                    posterPreview.innerHTML = '';
                    posterPreview.appendChild(img);
                    posterPreview.classList.add('has-image');
                    
                    // Add remove button
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-btn';
                    removeBtn.innerHTML = '<i class="bi bi-x"></i>';
                    removeBtn.addEventListener('click', removePosterImage);
                    posterPreview.appendChild(removeBtn);
                }
                
                reader.readAsDataURL(file);
            }
        });

        
        function removePosterImage(e) {
            e.stopPropagation();
            
            // Reset file input
            posterUpload.value = '';
            
            // Reset preview
            posterPreview.classList.remove('has-image');
            posterPreview.innerHTML = `
                <div class="placeholder">
                    <i class="bi bi-image text-4xl mb-2"></i>
                    <p>Chọn ảnh poster</p>
                    <p class="text-xs mt-1">Kích thước đề xuất: 500x750px</p>
                    <p class="text-xs mt-1">Tối đa: 5MB</p>
                </div>
                <div class="remove-btn" id="removePoster">
                    <i class="bi bi-x"></i>
                </div>
            `;
            
            // Re-add event listener to new remove button
            document.getElementById('removePoster').addEventListener('click', removePosterImage);
        }
        
        // Initial setup for remove button
        removePoster.addEventListener('click', removePosterImage);
        
        // Cancel button - chuyển về trang quản lý phim
        document.getElementById('cancelButton').addEventListener('click', function() {
            if (confirm('Bạn có chắc chắn muốn hủy? Mọi thông tin đã nhập sẽ bị mất.')) {
                window.location.href = 'admin_movies.php';
            }
        });
        
        // Form validation
        document.getElementById('addMovieForm').addEventListener('submit', function(e) {
            // Kiểm tra thể loại
            const genreCheckboxes = document.querySelectorAll('input[name="genres[]"]:checked');
            if (genreCheckboxes.length === 0) {
                e.preventDefault();
                alert('Vui lòng chọn ít nhất một thể loại phim!');
                return false;
            }
            
            // Kiểm tra ảnh poster
            const posterFile = document.getElementById('posterUpload').files[0];
            if (!posterFile) {
                e.preventDefault();
                alert('Vui lòng chọn ảnh poster!');
                return false;
            }
            
            // Kiểm tra kích thước file
            if (posterFile.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('Kích thước file quá lớn! Tối đa 5MB.');
                return false;
            }
            
            // Kiểm tra định dạng file
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(posterFile.type)) {
                e.preventDefault();
                alert('Chỉ cho phép tải lên file ảnh (JPG, JPEG, PNG, GIF)!');
                return false;
            }
        });
    
        // Hiển thị modal thêm thể loại
        document.getElementById('addGenreBtn').addEventListener('click', function() {
            document.getElementById('addGenreModal').classList.remove('hidden');
        });
        // Đóng modal khi bấm nút hủy
        document.getElementById('cancelGenreBtn').addEventListener('click', function() {
            document.getElementById('addGenreModal').classList.add('hidden');
        });

    </script>
</body>
</html>
<?php
// Đóng kết nối
$conn->close();
?>