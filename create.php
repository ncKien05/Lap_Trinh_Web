<?php
/**
 * create.php
 * Trang thêm mới đơn hàng (Create)
 * ----------------------------------------
 * - Luôn mặc định nhập thông tin khách hàng (Tên, SĐT, Email, Địa chỉ)
 * - Chọn sản phẩm động, tính tiền tự động
 * - Thiết kế tối giản, màu basic trang nhã
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/order-pricing.php';

requireStaff();

// Tạo CSRF token nếu chưa có
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Lấy dữ liệu cũ nếu bị redirect về do lỗi
$oldInput  = $_SESSION['old_input'] ?? [];
$errorMsgs = $_SESSION['errors']    ?? [];
unset($_SESSION['old_input'], $_SESSION['errors']);

$vouchers = [];
try {
    $pdo = getDBConnection();
    $products = $pdo->query("SELECT id, name, sku, price, stock FROM products ORDER BY name ASC")->fetchAll();
    $vouchers = listVouchers($pdo);
} catch (PDOException $e) {
    error_log('Lỗi lấy danh sách sản phẩm: ' . $e->getMessage());
    $products = $products ?? [];
    $errorMsgs[] = 'Không thể tải đầy đủ danh sách sản phẩm và voucher.';
}

// Helper lấy giá trị cũ an toàn
function old(string $key, $default = '', array $data = []): string
{
    $value = $data[$key] ?? $default;
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$oldItems = (!empty($oldInput['items']) && is_array($oldInput['items'])) ? array_values($oldInput['items']) : [];
if (empty($oldItems)) {
    $oldItems = [
        ['product_id' => '', 'quantity' => 1, 'unit_price' => 0]
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Đơn hàng mới | Order Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/forms.css?v=20261108-teal">
    <link rel="stylesheet" href="assets/orders-layout.css?v=20261108-teal">
</head>
<body class="order-form-page">

<!-- Navbar -->
<nav class="navbar-main d-flex align-items-center justify-content-between text-white">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="text-white text-decoration-none fw-bold fs-5">
            <i class="bi bi-box-seam me-2"></i>Order Management
        </a>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-sm btn-outline-light">
            <i class="bi bi-arrow-left me-1"></i>Danh sách đơn hàng
        </a>
    </div>
</nav>

<div class="container py-4" style="max-width: 900px;">

    <?php if (!empty($errorMsgs)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2 px-3 small border-0 shadow-sm mb-3" role="alert">
        <strong>Vui lòng kiểm tra lại:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errorMsgs as $err): ?>
                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card card-custom p-4">
        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-plus-circle me-2 text-primary"></i>Thêm đơn hàng mới</h5>
            </div>
        </div>

        <form action="actions/store.php" method="POST" id="order-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <!-- 1. THÔNG TIN KHÁCH HÀNG (MẶC ĐỊNH NHẬP TRỰC TIẾP) -->
            <div class="section-title"><i class="bi bi-person me-2"></i>Thông tin Khách hàng</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="customer_name" class="form-label small fw-semibold">Tên khách hàng <span class="text-danger">*</span></label>
                    <input type="text"
                           class="form-control"
                           id="customer_name"
                           name="customer_name"
                           placeholder="Ví dụ: Nguyễn Văn An"
                           maxlength="150"
                           value="<?= old('customer_name', '', $oldInput) ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label for="customer_phone" class="form-label small fw-semibold">Số điện thoại <span class="text-danger">*</span></label>
                    <input type="tel"
                           class="form-control"
                           id="customer_phone"
                           name="customer_phone"
                           placeholder="0901234567"
                           maxlength="11"
                           pattern="[0-9]{9,11}"
                           value="<?= old('customer_phone', '', $oldInput) ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label for="customer_email" class="form-label small fw-semibold">Email</label>
                    <input type="email"
                           class="form-control"
                           id="customer_email"
                           name="customer_email"
                           placeholder="email@example.com"
                           maxlength="100"
                           value="<?= old('customer_email', '', $oldInput) ?>">
                </div>
                <div class="col-md-6">
                    <label for="customer_address" class="form-label small fw-semibold">Địa chỉ nhận hàng</label>
                    <input type="text"
                           class="form-control"
                           id="customer_address"
                           name="customer_address"
                           placeholder="Số nhà, đường, phường/xã, quận/huyện..."
                           value="<?= old('customer_address', '', $oldInput) ?>">
                </div>
            </div>

            <!-- 2. SẢN PHẨM TRONG ĐƠN HÀNG -->
            <?php $formItems = $oldItems; require __DIR__ . '/includes/order-fields.php'; ?>

            <!-- 4. TRẠNG THÁI & GHI CHÚ -->
            <div class="section-title"><i class="bi bi-sliders me-2"></i>Trạng thái & Ghi chú</div>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="status" class="form-label small fw-semibold">Trạng thái <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="pending"   <?= old('status', 'pending', $oldInput) === 'pending'   ? 'selected' : '' ?>>⏳ Chờ xử lý</option>
                        <option value="shipping"  <?= old('status', 'pending', $oldInput) === 'shipping'  ? 'selected' : '' ?>>🚚 Đang giao</option>
                        <option value="delivered" <?= old('status', 'pending', $oldInput) === 'delivered' ? 'selected' : '' ?>>✅ Đã giao</option>
                        <option value="cancelled" <?= old('status', 'pending', $oldInput) === 'cancelled' ? 'selected' : '' ?>>❌ Hủy</option>
                        <option value="returned" <?= old('status', 'pending', $oldInput) === 'returned' ? 'selected' : '' ?>>Đã hoàn trả</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label for="note" class="form-label small fw-semibold">Ghi chú đơn hàng</label>
                    <textarea class="form-control" id="note" name="note" rows="2" maxlength="500" placeholder="Ghi chú thêm về giao hàng, yêu cầu của khách..."><?= old('note', '', $oldInput) ?></textarea>
                </div>
            </div>

            <!-- 5. TỔNG TIỀN THANH TOÁN -->
            <div class="total-box mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Tổng tiền thanh toán</div>
                        <div class="fs-3 fw-bold text-dark" id="total-display">0₫</div>
                    </div>
                    <div class="text-muted small text-end">
                        <div>Tiền hàng: <span id="subtotal-display" class="fw-semibold text-dark">0₫</span></div>
                        <div id="voucher-discount-row" class="text-danger fw-semibold" style="display: none;">
                            Voucher giảm giá: -<span id="discount-display">0₫</span>
                        </div>
                        <div id="calc-detail" class="mt-1">Chưa có sản phẩm</div>
                    </div>
                </div>
            </div>

            <!-- Nút bấm -->
            <div class="d-flex gap-2 justify-content-end">
                <a href="index.php" data-close-order-form class="btn btn-outline-secondary px-3" style="border-radius: 8px;">
                    <i class="bi bi-x-lg me-1"></i>Hủy
                </a>
                <button type="submit" class="btn btn-submit" id="btn-submit-create">
                    <i class="bi bi-save me-1"></i>Lưu đơn hàng
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/order-form.js"></script>
<script src="assets/app.js" defer></script>
</body>
</html>
