<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Nếu đã đăng nhập rồi thì vào thẳng trang danh sách
if (isLoggedIn()) {
    requireAuth();
    header('Location: ' . (canManageOrders() ? 'index.php' : 'customer.php'));
    exit;
}

$error = null;
$registrationSuccess = $_SESSION['registration_success'] ?? null;
unset($_SESSION['registration_success']);
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, password, name, email, role, customer_id FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user && in_array($user['role'], ['admin', 'staff', 'customer'], true) && password_verify($password, $user['password'])) {
                // Đăng nhập thành công
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user'] = [
                    'id'       => (int)$user['id'],
                    'username' => $user['username'],
                    'name'     => $user['name'],
                    'email'    => $user['email'],
                    'role'     => $user['role'],
                    'customer_id' => $user['customer_id'],
                ];
                $_SESSION['success'] = 'Xin chào ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '! Đăng nhập thành công.';
                header('Location: ' . (canManageOrders() ? 'index.php' : 'customer.php'));
                exit;
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
            }
        } catch (Throwable $e) {
            error_log('Lỗi đăng nhập: ' . $e->getMessage());
            $error = 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.';
        }
    }
}

$sessionError = $_SESSION['errors'][0] ?? null;
unset($_SESSION['errors']);
if (!$error && $sessionError) {
    $error = $sessionError;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập | Quản lý Đơn hàng</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            padding: 2.25rem;
        }
        .brand-icon {
            width: 48px;
            height: 48px;
            background: #1e293b;
            color: #ffffff;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0.65rem 0.9rem;
            font-size: 0.92rem;
        }
        .form-control:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }
        .btn-primary {
            border-radius: 8px;
            padding: 0.7rem;
            font-weight: 600;
        }
    </style>
    <link rel="stylesheet" href="assets/forms.css?v=20261108-teal">
    <link rel="stylesheet" href="assets/orders-layout.css?v=20261108-teal">
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <div class="brand-icon">
            <i class="bi bi-box-seam"></i>
        </div>
        <h4 class="fw-bold mb-1">Đăng nhập</h4>
        <p class="text-muted small mb-0">Hệ thống Quản lý Đơn hàng</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3" role="alert">
        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <?php if ($registrationSuccess): ?>
        <p class="alert alert-success" role="status"><?= htmlspecialchars($registrationSuccess, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="POST" action="login.php">
        <div class="mb-3">
            <label for="username" class="form-label small fw-semibold">Tên đăng nhập</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 8px 0 0 8px; border-color: #cbd5e1;">
                    <i class="bi bi-person"></i>
                </span>
                <input type="text"
                       class="form-control border-start-0"
                       style="border-radius: 0 8px 8px 0;"
                       id="username"
                       name="username"
                       placeholder="Nhập tên đăng nhập..."
                       value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                       required
                       autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label small fw-semibold">Mật khẩu</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 8px 0 0 8px; border-color: #cbd5e1;">
                    <i class="bi bi-lock"></i>
                </span>
                <input type="password"
                       class="form-control border-start-0"
                       style="border-radius: 0 8px 8px 0;"
                       id="password"
                       name="password"
                       placeholder="Nhập mật khẩu..."
                       required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mt-2">
            <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
        </button>
    </form>

    <div class="text-center mt-3">
        <a href="register.php">Chưa có tài khoản? Đăng ký khách hàng</a>
    </div>

    <div class="mt-4 pt-3 border-top text-center text-muted small">
        <div class="fw-semibold text-secondary mb-1">Tài khoản mặc định:</div>
        <div>Tên đăng nhập: <code>admin</code> &bull; Mật khẩu: <code>123456</code></div>
    </div>
</div>

<script src="assets/app.js" defer></script>
</body>
</html>
