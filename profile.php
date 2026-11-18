<?php
/**
 * profile.php
 * Trang hồ sơ cá nhân và đổi mật khẩu
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

requireAuth();

$pdo = getDBConnection();
$userId = (int)$_SESSION['user_id'];
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$successMsg = $_SESSION['success'] ?? null;
$errorMsgs  = $_SESSION['errors']  ?? [];
unset($_SESSION['success'], $_SESSION['errors']);

// Xử lý POST cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        $errorMsgs[] = 'Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.';
        $action = '';
    }

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || mb_strlen($name, 'UTF-8') > 100) {
            $errorMsgs[] = 'Họ và tên cần từ 1 đến 100 ký tự.';
        } elseif (strlen($email) > 100 || (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errorMsgs[] = 'Địa chỉ email không hợp lệ.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id");
                $stmt->execute([
                    ':name'  => $name,
                    ':email' => $email ?: null,
                    ':id'    => $userId,
                ]);

                // Cập nhật lại session
                $_SESSION['user']['name']  = $name;
                $_SESSION['user']['email'] = $email;

                $_SESSION['success'] = 'Cập nhật thông tin cá nhân thành công!';
                header('Location: profile.php');
                exit;
            } catch (Throwable $e) {
                error_log('Lỗi cập nhật profile: ' . $e->getMessage());
                $errorMsgs[] = 'Có lỗi xảy ra khi lưu thông tin.';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMsgs[] = 'Vui lòng điền đầy đủ các ô mật khẩu.';
        } elseif (strlen($newPassword) < 6) {
            $errorMsgs[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMsgs[] = 'Xác nhận mật khẩu mới không khớp.';
        } else {
            try {
                $checkStmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
                $checkStmt->execute([':id' => $userId]);
                $userDb = $checkStmt->fetch();

                if (!$userDb || !password_verify($currentPassword, $userDb['password'])) {
                    $errorMsgs[] = 'Mật khẩu hiện tại không chính xác.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $upStmt = $pdo->prepare("UPDATE users SET password = :pwd, updated_at = NOW() WHERE id = :id");
                    $upStmt->execute([':pwd' => $newHash, ':id' => $userId]);

                    $_SESSION['success'] = 'Đổi mật khẩu thành công!';
                    header('Location: profile.php');
                    exit;
                }
            } catch (Throwable $e) {
                error_log('Lỗi đổi mật khẩu: ' . $e->getMessage());
                $errorMsgs[] = 'Có lỗi xảy ra khi đổi mật khẩu.';
            }
        }
    }
}

// Lấy thông tin người dùng mới nhất từ DB
$stmt = $pdo->prepare("SELECT id, username, name, email, role, created_at, updated_at FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$currentUser = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân | Quản lý Đơn hàng</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .card-custom {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0.6rem 0.85rem;
            font-size: 0.92rem;
        }
        .form-control:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }
        .avatar-box {
            width: 72px;
            height: 72px;
            background: #e2e8f0;
            color: #1e293b;
            font-weight: 700;
            font-size: 1.8rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-dark-custom {
            background-color: #1e293b;
            border-color: #1e293b;
            color: #ffffff;
            border-radius: 8px;
            font-weight: 500;
        }
        .btn-dark-custom:hover {
            background-color: #0f172a;
            border-color: #0f172a;
            color: #ffffff;
        }
    </style>
    <link rel="stylesheet" href="assets/forms.css?v=20261108-teal">
    <link rel="stylesheet" href="assets/orders-layout.css?v=20261108-teal">
</head>
<body>

<?php
$activePage = 'profile';
require __DIR__ . '/includes/orders-navigation.php';
?>
<main class="orders-main">
    <div class="orders-page-heading"><h1>Hồ sơ cá nhân</h1></div>
    <div style="max-width: 900px;">

    <!-- Alerts -->
    <?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 py-2 px-3 small border-0 shadow-sm" role="alert">
        <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errorMsgs)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2 px-3 small border-0 shadow-sm" role="alert">
        <strong>Có lỗi xảy ra:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errorMsgs as $err): ?>
                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- User Header Card -->
    <div class="card card-custom p-4 mb-4">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="avatar-box">
                <?= strtoupper(mb_substr($currentUser['name'] ?? $currentUser['username'], 0, 1, 'UTF-8')) ?>
            </div>
            <div>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                <div class="text-muted small">
                    <span class="badge bg-secondary me-2"><?= ['admin' => 'Quản trị viên', 'staff' => 'Nhân viên', 'customer' => 'Khách hàng'][$currentUser['role']] ?></span>
                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?> &bull; 
                    <i class="bi bi-calendar3 ms-2 me-1"></i>Tham gia: <?= date('d/m/Y', strtotime($currentUser['created_at'])) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Thông tin cá nhân -->
        <div class="col-md-6">
            <div class="card card-custom p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-person-lines-fill me-2 text-secondary"></i>Thông tin cá nhân</h5>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tên đăng nhập</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?>" readonly disabled>
                        <div class="form-text small">Tên đăng nhập không thể thay đổi.</div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Địa chỉ Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="name@example.com">
                    </div>

                    <button type="submit" class="btn btn-dark-custom">
                        <i class="bi bi-check2 me-1"></i>Lưu thông tin
                    </button>
                </form>
            </div>
        </div>

        <!-- Đổi mật khẩu -->
        <div class="col-md-6">
            <div class="card card-custom p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-secondary"></i>Đổi mật khẩu</h5>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label small fw-semibold">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label small fw-semibold">Mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Tối thiểu 6 ký tự" required>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label small fw-semibold">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-dark-custom">
                        <i class="bi bi-key me-1"></i>Cập nhật mật khẩu
                    </button>
                </form>
            </div>
        </div>
    </div>

    </div>
</main>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js" defer></script>
</body>
</html>
