<?php
/**
 * config/database.php
 * Cấu hình kết nối PDO đến MySQL
*/

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'order_management');
define('DB_USER', getenv('DB_USER') ?: 'root'); // Docker truyền cấu hình qua environment
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''); // XAMPP mặc định để trống
define('DB_CHARSET', 'utf8mb4');

/**
 * Hàm getDBConnection()
 * Trả về một đối tượng PDO đã được cấu hình.
 * Sử dụng Singleton pattern đơn giản để tránh tạo nhiều kết nối.
 *
 * @return PDO
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Kết nối database thất bại: ' . $e->getMessage());

            if (php_sapi_name() === 'cli') {
                die("Lỗi kết nối CSDL: " . $e->getMessage() . "\n");
            }

            // Giao diện thân thiện hiển thị trên trình duyệt khi có lỗi kết nối
            ?>
            <!DOCTYPE html>
            <html lang="vi">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Lỗi kết nối Cơ sở dữ liệu | Order Management</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
                <style>
                    body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
                </style>
            </head>
            <body class="d-flex align-items-center justify-content-center min-vh-100 p-3">
                <div class="card shadow-lg border-0 rounded-4 p-4 text-center" style="max-width: 520px; width: 100%;">
                    <div class="mb-3 text-warning" style="font-size: 3.5rem;">⚠️</div>
                    <h4 class="fw-bold text-danger mb-2">Không thể kết nối Cơ sở dữ liệu!</h4>
                    <p class="text-muted small mb-3">
                        Hệ thống không thể kết nối tới MySQL với thông số trong file <code>config/database.php</code>.
                    </p>
                    <div class="alert alert-warning text-start small mb-3 rounded-3">
                        <strong class="d-block mb-1">Hướng dẫn khắc phục nhanh:</strong>
                        <ul class="mb-0 ps-3">
                            <li>Mở <strong>XAMPP Control Panel</strong> (hoặc Laragon) và bấm <strong>Start</strong> dịch vụ MySQL.</li>
                            <li>Đảm bảo đã import file <code>database.sql</code> vào phpMyAdmin (Database: <code>order_management</code>).</li>
                            <li>Kiểm tra lại <code>DB_USER</code> và <code>DB_PASS</code> trong <code>config/database.php</code>.</li>
                        </ul>
                    </div>
                    <div class="text-muted small text-break mb-3">
                        Chi tiết lỗi: <code><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                    <div>
                        <button onclick="location.reload()" class="btn btn-primary px-4 rounded-3 fw-semibold">
                            Thử kết nối lại
                        </button>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }

    return $pdo;
}
