<?php
/**
 * edit.php
 * Trang chỉnh sửa đơn hàng (Update)
 * ----------------------------------------
 * - Mặc định nhập trực tiếp thông tin khách hàng (Tên, SĐT, Email, Địa chỉ)
 * - Chỉnh sửa danh sách sản phẩm, tự động tính tổng tiền
 * - Cập nhật trạng thái và hiển thị Timeline lịch sử trạng thái
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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['errors'] = ['ID đơn hàng không hợp lệ.'];
    header('Location: index.php');
    exit;
}

try {
    $pdo = getDBConnection();

    // Lấy thông tin đơn hàng cùng thông tin khách hàng
    $orderStmt = $pdo->prepare("
        SELECT 
            o.id,
            o.customer_id,
            o.total_amount,
            o.voucher_code,
            o.discount_amount,
            o.cod_amount,
            o.cod_status,
            o.status,
            o.note,
            o.created_at,
            o.updated_at,
            c.name AS customer_name,
            c.phone AS customer_phone,
            c.email AS customer_email,
            c.address AS customer_address
        FROM orders o
        INNER JOIN customers c ON o.customer_id = c.id
        WHERE o.id = :id
        LIMIT 1
    ");
    $orderStmt->execute([':id' => $id]);
    $order = $orderStmt->fetch();

    if (!$order) {
        $_SESSION['errors'] = ["Đơn hàng #{$id} không tồn tại hoặc đã bị xóa."];
        header('Location: index.php');
        exit;
    }

    // Lấy chi tiết các mặt hàng trong đơn
    $itemsStmt = $pdo->prepare("
        SELECT 
            oi.id AS item_id,
            oi.product_id,
            oi.quantity,
            oi.unit_price,
            oi.total_price,
            p.name AS product_name,
            p.sku AS product_sku,
            p.stock AS product_stock
        FROM order_items oi
        INNER JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ");
    $itemsStmt->execute([':order_id' => $id]);
    $orderItems = $itemsStmt->fetchAll();

    // Danh sách sản phẩm
    $products = $pdo->query("SELECT id, name, sku, price, stock FROM products ORDER BY name ASC")->fetchAll();
    $vouchers = listVouchers($pdo);

    // Lịch sử trạng thái
    $historyStmt = $pdo->prepare("
        SELECT id, old_status, new_status, note, changed_at
        FROM order_status_history
        WHERE order_id = :order_id
        ORDER BY changed_at DESC, id DESC
    ");
    $historyStmt->execute([':order_id' => $id]);
    $statusHistories = $historyStmt->fetchAll();

} catch (PDOException $e) {
    error_log('Lỗi lấy đơn hàng: ' . $e->getMessage());
    $_SESSION['errors'] = ['Không thể tải dữ liệu đơn hàng.'];
    header('Location: index.php');
    exit;
}

$oldInput  = $_SESSION['old_input'] ?? [];
$errorMsgs = $_SESSION['errors']    ?? [];
unset($_SESSION['old_input'], $_SESSION['errors']);

if (!empty($oldInput['items']) && is_array($oldInput['items'])) {
    $displayItems = array_values($oldInput['items']);
} else {
    $displayItems = array_map(function($item) {
        return [
            'product_id' => $item['product_id'],
            'quantity'   => $item['quantity'],
            'unit_price' => $item['unit_price'],
        ];
    }, $orderItems);
}

if (empty($displayItems)) {
    $displayItems = [['product_id' => '', 'quantity' => 1, 'unit_price' => 0]];
}

function getVal(string $key, array $oldInput, array $order, $default = ''): string
{
    $val = $oldInput[$key] ?? $order[$key] ?? $default;
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

function getStatusLabel(string $status): string
{
    $labels = [
        'pending'   => 'Chờ xử lý',
        'shipping'  => 'Đang giao',
        'delivered' => 'Đã giao',
        'cancelled' => 'Đã hủy',
        'returned'  => 'Đã hoàn trả',
    ];
    return $labels[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa Đơn hàng #<?= $id ?> | Order Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .history-timeline {
            position: relative;
            padding-left: 20px;
        }
        .history-timeline::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            background: #e2e8f0;
        }
        .history-item {
            position: relative;
            margin-bottom: 1rem;
        }
        .history-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 6px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #0f766e;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 1px #cbd5e1;
        }
    </style>
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
        <span class="badge bg-secondary ms-2">Đơn #<?= $id ?></span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-sm btn-outline-light">
            <i class="bi bi-arrow-left me-1"></i>Về danh sách
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

    <div class="card card-custom p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2 text-primary"></i>Chỉnh sửa Đơn hàng #<?= $id ?></h5>
                <p class="text-muted small mb-0">Cập nhật thông tin khách hàng, sản phẩm và trạng thái</p>
            </div>
            <div class="text-muted small">
                <span><i class="bi bi-calendar3 me-1"></i>Ngày tạo: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
            </div>
        </div>

        <form action="actions/update.php" method="POST" id="order-form" novalidate>
            <input type="hidden" name="id" value="<?= $id ?>">
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
                           maxlength="150"
                           value="<?= getVal('customer_name', $oldInput, $order) ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label for="customer_phone" class="form-label small fw-semibold">Số điện thoại <span class="text-danger">*</span></label>
                    <input type="tel"
                           class="form-control"
                           id="customer_phone"
                           name="customer_phone"
                           maxlength="11"
                           pattern="[0-9]{9,11}"
                           value="<?= getVal('customer_phone', $oldInput, $order) ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label for="customer_email" class="form-label small fw-semibold">Email</label>
                    <input type="email"
                           class="form-control"
                           id="customer_email"
                           name="customer_email"
                           maxlength="100"
                           value="<?= getVal('customer_email', $oldInput, $order) ?>">
                </div>
                <div class="col-md-6">
                    <label for="customer_address" class="form-label small fw-semibold">Địa chỉ nhận hàng</label>
                    <input type="text"
                           class="form-control"
                           id="customer_address"
                           name="customer_address"
                           value="<?= getVal('customer_address', $oldInput, $order) ?>">
                </div>
            </div>

            <!-- 2. SẢN PHẨM TRONG ĐƠN HÀNG -->
            <?php $formItems = $displayItems; require __DIR__ . '/includes/order-fields.php'; ?>

            <!-- 4. TRẠNG THÁI & GHI CHÚ -->
            <div class="section-title"><i class="bi bi-sliders me-2"></i>Trạng thái & Lịch sử cập nhật</div>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="status" class="form-label small fw-semibold">Trạng thái mới <span class="text-danger">*</span></label>
                    <?php $currStatus = $oldInput['status'] ?? $order['status']; ?>
                    <select class="form-select" id="status" name="status" required>
                        <option value="pending"   <?= $currStatus === 'pending'   ? 'selected' : '' ?>>⏳ Chờ xử lý</option>
                        <option value="shipping"  <?= $currStatus === 'shipping'  ? 'selected' : '' ?>>🚚 Đang giao</option>
                        <option value="delivered" <?= $currStatus === 'delivered' ? 'selected' : '' ?>>✅ Đã giao</option>
                        <option value="cancelled" <?= $currStatus === 'cancelled' ? 'selected' : '' ?>>❌ Hủy</option>
                        <option value="returned" <?= $currStatus === 'returned' ? 'selected' : '' ?>>Đã hoàn trả</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label for="status_note" class="form-label small fw-semibold">Ghi chú đổi trạng thái (Lưu vết vào lịch sử)</label>
                    <input type="text" class="form-control" id="status_note" name="status_note" placeholder="Ví dụ: Đã gửi mã vận đơn, giao thành công..." value="<?= getVal('status_note', $oldInput, [], '') ?>">
                </div>
                <div class="col-12">
                    <label for="note" class="form-label small fw-semibold">Ghi chú đơn hàng chung</label>
                    <textarea class="form-control" id="note" name="note" rows="2" maxlength="500"><?= getVal('note', $oldInput, $order) ?></textarea>
                </div>
            </div>

            <!-- 5. TỔNG TIỀN THANH TOÁN -->
            <div class="total-box mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Tổng tiền thanh toán</div>
                        <div class="fs-3 fw-bold text-dark" id="total-display"><?= number_format($order['total_amount'], 0, ',', '.') ?>₫</div>
                    </div>
                    <div class="text-muted small text-end">
                        <div>Tiền hàng: <span id="subtotal-display" class="fw-semibold text-dark">0₫</span></div>
                        <div id="voucher-discount-row" class="text-danger fw-semibold" style="display: none;">
                            Voucher giảm giá: -<span id="discount-display">0₫</span>
                        </div>
                        <div id="calc-detail" class="mt-1">Tự động tính theo sản phẩm</div>
                    </div>
                </div>
            </div>

            <!-- Nút bấm -->
            <div class="d-flex gap-2 justify-content-end">
                <a href="index.php" data-close-order-form class="btn btn-outline-secondary px-3" style="border-radius: 8px;">
                    <i class="bi bi-x-lg me-1"></i>Hủy
                </a>
                <button type="submit" class="btn btn-submit" id="btn-submit-edit">
                    <i class="bi bi-check2-circle me-1"></i>Cập nhật đơn hàng
                </button>
            </div>
        </form>
    </div>

    <!-- TIMELINE LỊCH SỬ TRẠNG THÁI -->
    <?php if (!empty($statusHistories)): ?>
    <div class="card card-custom p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Lịch sử trạng thái đơn hàng</h6>
        <div class="history-timeline">
            <?php foreach ($statusHistories as $h): ?>
            <div class="history-item">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="badge bg-secondary"><?= getStatusLabel($h['new_status']) ?></span>
                    <?php if ($h['old_status']): ?>
                    <span class="text-muted small">(chuyển từ <strong><?= getStatusLabel($h['old_status']) ?></strong>)</span>
                    <?php endif; ?>
                    <span class="text-muted small ms-auto">
                        <i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i:s', strtotime($h['changed_at'])) ?>
                    </span>
                </div>
                <?php if (!empty($h['note'])): ?>
                <div class="small text-secondary bg-light px-3 py-2 rounded-2 mt-1 border">
                    <i class="bi bi-chat-left-dots me-1 text-muted"></i><?= htmlspecialchars($h['note'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/order-form.js"></script>
<script src="assets/app.js" defer></script>
</body>
</html>
