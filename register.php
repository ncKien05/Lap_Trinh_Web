<?php

require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    requireAuth();
    header('Location: ' . (canManageOrders() ? 'index.php' : 'customer.php'));
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error = '';
$values = ['name' => '', 'phone' => '', 'email' => '', 'username' => ''];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($values as $field => $value) {
        $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    $token = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        $error = 'Phiên làm việc hết hạn. Vui lòng tải lại trang.';
    } elseif ($values['name'] === '' || mb_strlen($values['name'], 'UTF-8') > 100 ||
              !preg_match('/\A[0-9]{9,11}\z/', $values['phone']) ||
              !preg_match('/\A[a-zA-Z0-9_.-]{3,50}\z/', $values['username']) ||
              strlen($values['email']) > 100 ||
              ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL))) {
        $error = 'Vui lòng kiểm tra họ tên, số điện thoại, email và tên đăng nhập (3–50 ký tự chữ, số, dấu chấm, gạch ngang hoặc gạch dưới).';
    } elseif (!is_string($password) || strlen($password) < 8 || strlen($password) > 72 || $password !== $confirm) {
        $error = 'Mật khẩu cần từ 8 đến 72 ký tự và khớp với mật khẩu xác nhận.';
    } else {
        $pdo = getDBConnection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$values['phone']]);
            if ($stmt->fetch()) {
                throw new InvalidArgumentException('Số điện thoại đã có hồ sơ. Vui lòng liên hệ quản trị viên để được cấp tài khoản.');
            }
            $stmt = $pdo->prepare('INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)');
            $stmt->execute([$values['name'], $values['phone'], $values['email'] ?: null]);
            $customerId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, email, role, customer_id) VALUES (?, ?, ?, ?, 'customer', ?)");
            $stmt->execute([$values['username'], password_hash($password, PASSWORD_DEFAULT), $values['name'], $values['email'] ?: null, $customerId]);
            $pdo->commit();
            $_SESSION['registration_success'] = 'Đăng ký thành công. Bạn có thể đăng nhập bằng tài khoản vừa tạo.';
            header('Location: login.php', true, 303);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Lỗi đăng ký khách hàng: ' . $e->getMessage());
            if ($e instanceof InvalidArgumentException) {
                $error = $e->getMessage();
            } else {
                $error = $e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062
                    ? 'Tên đăng nhập đã tồn tại.' : 'Không thể đăng ký. Vui lòng thử lại.';
            }
        }
    }
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng ký khách hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/orders-layout.css?v=20261108-teal" rel="stylesheet">
    <link href="assets/forms.css?v=20261108-teal" rel="stylesheet">
</head>
<body class="orders-catalog-page">
<main class="container py-5" style="max-width: 620px;">
    <div class="card shadow-sm"><div class="card-body p-4">
        <h1 class="h4 fw-bold mb-4">Đăng ký khách hàng</h1>
        <?php if ($error !== ''): ?><p class="alert alert-danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>
        <form method="post" action="register.php">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <?php foreach (['name' => ['Họ và tên', 'text', 100], 'phone' => ['Số điện thoại', 'tel', 11], 'email' => ['Email (không bắt buộc)', 'email', 100], 'username' => ['Tên đăng nhập', 'text', 50]] as $field => [$label, $type, $max]): ?>
                <div class="mb-3">
                    <label for="<?= $field ?>" class="form-label"><?= $label ?></label>
                    <input class="form-control" id="<?= $field ?>" name="<?= $field ?>" type="<?= $type ?>" maxlength="<?= $max ?>"
                           value="<?= $escape($values[$field]) ?>" <?= $field === 'email' ? '' : 'required' ?>>
                </div>
            <?php endforeach; ?>
            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <input class="form-control" id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>
            <div class="mb-4">
                <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Tạo tài khoản</button>
        </form>
        <p class="text-center mt-3 mb-0"><a href="login.php">Đã có tài khoản? Đăng nhập</a></p>
    </div></div>
</main>
<script src="assets/app.js" defer></script>
</body>
</html>
