// Xử lý yêu cầu thêm phim
if (isset($_POST['request_movie'])) {
    // Đảm bảo đã bắt đầu session
    session_start();

    // Kết nối DB (giả sử $conn là kết nối đã khai báo từ trước)
    $user_id = $_SESSION['user_id'];
    $movieTitle = trim($_POST['movieTitle']);
    $movieGenre = trim($_POST['movieGenre']);

    // Escape dữ liệu để an toàn
    $movieTitle = mysqli_real_escape_string($conn, $movieTitle);
    $movieGenre = mysqli_real_escape_string($conn, $movieGenre);

    // Tạo truy vấn
    $request = "INSERT INTO yeu_cau_them_phim (id_nguoi_dung, ten_phim, the_loai)
                VALUES ('$user_id', '$movieTitle', '$movieGenre')";

    // Thực thi truy vấn
    if (mysqli_query($conn, $request)) {
        $requestSuccess = true;
    } else {
        $requestSuccess = false;
        $errorMessage = "Lỗi khi gửi yêu cầu: " . mysqli_error($conn);
    }
}