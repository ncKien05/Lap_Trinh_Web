<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/order-pricing.php';

requireStaff();

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$success = $_SESSION['voucher_success'] ?? '';
unset($_SESSION['voucher_success']);
$error = '';
$code = '';
$amount = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $code = is_string($_POST['code'] ?? null) ? strtoupper(trim($_POST['code'])) : '';
    $amount = is_string($_POST['discount_amount'] ?? null) ? trim($_POST['discount_amount']) : '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } elseif (isset($_POST['delete_voucher'])) {
        $voucherId = $_POST['delete_voucher'];
        if (!is_string($voucherId) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $voucherId)) {
            $error = 'Voucher được chọn không hợp lệ.';
        } else {
            try {
                $stmt = getDBConnection()->prepare('DELETE FROM vouchers WHERE id = :id');
                $stmt->execute([':id' => $voucherId]);
                if ($stmt->rowCount() === 0) {
                    $error = 'Voucher này không còn trong kho.';
                } else {
                    $_SESSION['voucher_success'] = 'Đã xóa voucher khỏi kho.';
                    header('Location: voucher-create.php', true, 303);
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Lỗi xóa voucher: ' . $e->getMessage());
                $error = 'Không thể xóa voucher. Vui lòng thử lại.';
            }
        }
    } elseif (!preg_match('/\A[A-Z0-9_-]{1,50}\z/', $code)) {
        $error = 'Mã voucher cần từ 1 đến 50 ký tự, gồm chữ cái, chữ số, dấu gạch ngang hoặc gạch dưới.';
    } elseif (!preg_match('/\A[1-9][0-9]{0,12}\z/', $amount)) {
        $error = 'Số tiền giảm giá phải là số nguyên từ 1 đến 9.999.999.999.999 ₫.';
    } else {
        try {
            $stmt = getDBConnection()->prepare('INSERT INTO vouchers (code, discount_amount) VALUES (:code, :amount)');
            $stmt->execute([':code' => $code, ':amount' => $amount]);
            $_SESSION['voucher_success'] = "Đã tạo voucher {$code}.";
            header('Location: voucher-create.php', true, 303);
            exit;
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $error = 'Mã voucher này đã tồn tại.';
            } else {
                error_log('Lỗi tạo voucher: ' . $e->getMessage());
                $error = 'Không thể lưu voucher. Vui lòng kiểm tra cấu hình cơ sở dữ liệu.';
            }
        }
    }
}

$voucherListError = false;
try {
    $vouchers = listVouchers(getDBConnection());
} catch (PDOException $e) {
    error_log('Lỗi tải kho voucher: ' . $e->getMessage());
    $vouchers = [];
    $voucherListError = true;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tạo voucher | Order Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: Inter, sans-serif; background: #f8fafc; }
    </style>
    <link rel="stylesheet" href="assets/forms.css?v=20261108-teal">
    <link rel="stylesheet" href="assets/orders-layout.css?v=20261108-teal">
</head>
<body>
<?php
$currentUser = getCurrentUser();
$activePage = 'promotions';
require __DIR__ . '/includes/orders-navigation.php';
?>
<main class="orders-main">
    <div class="orders-page-heading"><h1>Khuyến mãi</h1></div>
    <div class="row g-3 align-items-start">
        <div class="col-12 col-xl-5">
            <div class="card border-0 rounded-3 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h4 fw-bold">Tạo voucher</h2>
                    <p class="text-secondary mb-4">Đặt mã và số tiền giảm cố định cho voucher dùng khi lập đơn hàng.</p>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <form method="post" action="voucher-create.php" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="code">Mã voucher</label>
                            <input class="form-control text-uppercase" id="code" name="code" maxlength="50"
                                   pattern="[A-Za-z0-9_\-]{1,50}" placeholder="GIAM50K"
                                   value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold" for="discount_amount">Số tiền giảm giá (₫)</label>
                            <input class="form-control" type="number" id="discount_amount" name="discount_amount"
                                   min="1" max="9999999999999" step="1" placeholder="50000"
                                   value="<?= htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">Lưu voucher</button>
                            <a class="btn btn-outline-secondary" href="index.php">Về danh sách đơn hàng</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <section class="col-12 col-xl-7" aria-labelledby="voucher-stock-title">
            <div class="card border-0 rounded-3 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <h2 class="h4 fw-bold mb-0" id="voucher-stock-title">Kho voucher</h2>
                        <?php if (!$voucherListError): ?>
                            <span class="badge text-bg-light"><?= count($vouchers) ?> mã</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($voucherListError): ?>
                        <p class="text-danger mb-0" role="alert">Không thể tải kho voucher.</p>
                    <?php elseif (!$vouchers): ?>
                        <p class="text-secondary mb-0">Chưa có voucher. Tạo mã mới để dùng khi lập đơn hàng.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Mã voucher</th>
                                        <th scope="col" class="text-end">Mức giảm</th>
                                    <th scope="col" class="text-end">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vouchers as $voucher): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($voucher['code'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end text-nowrap"><?= number_format((float)$voucher['discount_amount'], 0, ',', '.') ?> ₫</td>
                                        <td class="text-end">
                                            <form method="post" action="voucher-create.php" onsubmit="return confirm('Xóa voucher này khỏi kho?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" name="delete_voucher" value="<?= (int)$voucher['id'] ?>">
                                                    <i class="bi bi-trash me-1" aria-hidden="true"></i>Xóa
                                                </button>
                                            </form>
                                        </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js" defer></script>
</body>
</html>
