<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAT - Movie Review Website</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Netflix Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #141414;
            color: #fff;
        }
        
        main {
            flex: 1;
            padding: 2rem;
        }
        
        .footer {
            background-color: #000000;
            color: #808080;
            border-top: 1px solid #333;
        }
        
        .netflix-red {
            color: #E50914;
        }
        
        .social-icon {
            transition: all 0.2s ease;
            color: #808080;
        }
        
        .social-icon:hover {
            color: #E50914;
            transform: scale(1.2);
        }
        
        @media (max-width: 640px) {
            .footer-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <footer class="footer py-8 px-6">
        <div class="container mx-auto max-w-6xl">
            <div class="footer-content flex justify-between items-center flex-wrap gap-6 mb-8">
                <div>
                    <h2 class="text-2xl font-bold mb-2 netflix-red">CINEMAT</h2>
                    <p class="text-sm max-w-xs">Đánh giá phim chuyên nghiệp, cập nhật tin tức điện ảnh mới nhất</p>
                </div>
                
                <div>
                    <div class="flex space-x-5">
                        <a href="#" class="social-icon text-2xl" aria-label="Facebook">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="#" class="social-icon text-2xl" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="#" class="social-icon text-2xl" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="text-center pt-6 border-t border-gray-800">
                <p class="text-sm">Bản quyền © 2025 <span class="font-medium text-white">CINEMAT</span>. Tất cả các quyền được bảo lưu.</p>
            </div>
        </div>
    </footer>
</body>
</html>