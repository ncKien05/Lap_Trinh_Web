<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

requireAuth();
$currentUser = getCurrentUser();
$ownerId = (int)($currentUser['customer_id'] ?? 0);

$lookupToken = $_SESSION['order_lookup_token'] ??= bin2hex(random_bytes(32));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $token = $_POST['lookup_token'] ?? '';
    $orderId = is_string($_POST['order_id'] ?? null) ? ltrim(trim($_POST['order_id']), '#') : '';
    if (!is_string($token) || !hash_equals($lookupToken, $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Vui lòng tải lại trang và thử lại.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $orderId)) {
        http_response_code(422);
        echo json_encode(['error' => 'Mã đơn hàng không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('
            SELECT o.id, o.created_at, o.status, o.total_amount, o.voucher_code, o.discount_amount,
                   o.cod_status, o.cod_amount
            FROM orders o JOIN customers c ON c.id = o.customer_id
            WHERE o.id = :id AND o.customer_id = :customer_id
        ');
        $stmt->execute([':id' => $orderId, ':customer_id' => $ownerId]);
        $order = $stmt->fetch();
        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Không tìm thấy đơn hàng khớp với thông tin đã nhập.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $pdo->prepare('
            SELECT p.name, p.sku, oi.quantity, oi.unit_price, oi.total_price
            FROM order_items oi JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ? ORDER BY oi.id
        ');
        $stmt->execute([$order['id']]);
        echo json_encode(['order' => $order, 'items' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log('Lỗi xem đơn hàng khách: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['error' => 'Không thể tra cứu đơn hàng. Vui lòng thử lại.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$isAjax = ($_GET['ajax'] ?? '') === '1';
$products = [];
$customerOrders = [];
$loadError = false;

try {
    $stmt = getDBConnection()->prepare('SELECT id, created_at, status, total_amount FROM orders WHERE customer_id = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$ownerId]);
    $customerOrders = $stmt->fetchAll();
    $stmt = getDBConnection()->prepare('
        SELECT name, sku, price, stock, description FROM products
        WHERE name LIKE :name OR sku LIKE :sku ORDER BY name, id
    ');
    $stmt->execute([':name' => '%' . $search . '%', ':sku' => '%' . $search . '%']);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Lỗi tải danh mục công khai: ' . $e->getMessage());
    $loadError = true;
    http_response_code(503);
}
header('Content-Type: text/html; charset=UTF-8');
$escape = static fn(?string $value): string => htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
?>
<?php if (!$isAjax): ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Khách hàng | Order Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/forms.css?v=20261108-teal">
    <link rel="stylesheet" href="assets/orders-layout.css?v=20261108-teal">
</head>
<body class="orders-catalog-page">
<header class="orders-topbar d-flex justify-content-between">
    <a href="customer.php" class="orders-brand">Order Management</a>
    <div class="d-flex gap-2">
        <?php if (canManageOrders()): ?><a href="index.php" class="btn btn-outline-secondary btn-sm">Quản trị</a><?php endif; ?>
        <a href="profile.php" class="btn btn-outline-primary btn-sm">Hồ sơ cá nhân</a>
        <a href="logout.php" class="btn btn-outline-secondary btn-sm">Đăng xuất</a>
    </div>
</header>
<main class="container py-4">
    <nav class="nav nav-tabs mb-4" role="tablist" aria-label="Trang khách hàng">
        <button class="nav-link active" id="customer-orders-tab" data-bs-toggle="tab" data-bs-target="#customer-orders" type="button"
                role="tab" aria-controls="customer-orders" aria-selected="true">Đơn hàng đã đặt</button>
        <button class="nav-link" id="customer-products-tab" data-bs-toggle="tab" data-bs-target="#customer-products" type="button"
                role="tab" aria-controls="customer-products" aria-selected="false">Sản phẩm</button>
    </nav>
    <div class="tab-content">
    <section class="tab-pane fade show active" id="customer-orders" role="tabpanel" aria-labelledby="customer-orders-tab" tabindex="0">
        <h1 class="h3 fw-bold">Đơn hàng đã đặt</h1>
        <p class="text-secondary">Các đơn hàng gắn với tài khoản của bạn.</p>
        <?php if (!$customerOrders): ?>
            <p class="alert alert-light border">Bạn chưa có đơn hàng nào.</p>
        <?php else: ?>
            <div class="table-responsive bg-white rounded border mb-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Mã đơn</th><th>Ngày tạo</th><th>Trạng thái</th><th>Tổng tiền</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    <?php $statuses = ['pending' => 'Chờ xử lý', 'shipping' => 'Đang giao', 'delivered' => 'Đã giao', 'returned' => 'Đã hoàn trả', 'cancelled' => 'Đã hủy']; ?>
                    <?php foreach ($customerOrders as $placedOrder): ?>
                        <tr>
                            <td>#<?= (int)$placedOrder['id'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($placedOrder['created_at'])) ?></td>
                            <td><?= $escape($statuses[$placedOrder['status']] ?? '—') ?></td>
                            <td><?= number_format((float)$placedOrder['total_amount'], 0, ',', '.') ?> ₫</td>
                            <td><button type="button" class="btn btn-outline-primary btn-sm view-my-order"
                                        data-order-id="<?= (int)$placedOrder['id'] ?>" title="Xem chi tiết" aria-label="Xem đơn #<?= (int)$placedOrder['id'] ?>">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p id="lookup-message" class="text-secondary" role="status"></p>
    </section>
    <section class="tab-pane fade" id="customer-products" role="tabpanel" aria-labelledby="customer-products-tab" tabindex="0">
    <h1 class="h3 fw-bold">Sản phẩm</h1>
    <p class="text-secondary">Xem danh mục, giá bán và tình trạng hàng.</p>
    <form method="get" action="customer.php" id="shop-search" class="d-flex gap-2 mb-4">
        <label for="shop-keyword" class="visually-hidden">Tên sản phẩm hoặc SKU</label>
        <input type="search" id="shop-keyword" name="search" class="form-control"
               placeholder="Tìm tên sản phẩm hoặc SKU" value="<?= $escape($search) ?>">
        <button type="submit" class="btn btn-primary text-nowrap">Tìm kiếm</button>
    </form>
    <div id="shop-results" aria-live="polite">
<?php endif; ?>
        <?php if ($loadError): ?>
            <p class="alert alert-danger" role="alert">Không thể tải sản phẩm. Vui lòng thử lại.</p>
        <?php elseif (!$products): ?>
            <p class="text-secondary">Không tìm thấy sản phẩm phù hợp.</p>
        <?php else: ?>
            <p class="text-secondary small"><?= count($products) ?> sản phẩm</p>
            <div class="row g-3">
                <?php foreach ($products as $product): ?>
                    <article class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h2 class="h5"><?= $escape($product['name']) ?></h2>
                                <p class="text-secondary small">SKU <?= $escape($product['sku'] ?: '—') ?></p>
                                <p class="text-primary fw-bold fs-5"><?= number_format((float)$product['price'], 0, ',', '.') ?> ₫</p>
                                <p class="badge <?= (int)$product['stock'] > 0 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= (int)$product['stock'] > 0 ? 'Còn hàng' : 'Hết hàng' ?>
                                </p>
                                <p class="text-secondary mb-0"><?= nl2br($escape($product['description'])) ?></p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
<?php if (!$isAjax): ?>
    </div>
    </section>
    </div>
</main>
<div class="modal fade" id="lookup-modal" tabindex="-1" aria-labelledby="lookup-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="lookup-title">Chi tiết đơn hàng</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <span id="lookup-date" class="text-secondary"></span>
                    <span id="lookup-status" class="badge text-bg-primary"></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light"><tr>
                            <th>Sản phẩm</th><th>SKU</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th>
                        </tr></thead>
                        <tbody id="lookup-items"></tbody>
                    </table>
                </div>
                <p id="lookup-voucher" class="text-secondary"></p>
                <p id="lookup-cod" class="text-secondary"></p>
                <div class="text-end fw-bold fs-5">Tổng thanh toán <span id="lookup-total"></span></div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.view-my-order').forEach(button => button.addEventListener('click', async function () {
    const message = document.getElementById('lookup-message');
    button.disabled = true;
    message.textContent = 'Đang tra cứu…';
    try {
        const body = new FormData();
        body.set('lookup_token', '<?= $lookupToken ?>');
        body.set('order_id', this.dataset.orderId);
        const response = await fetch('customer.php', {method: 'POST', body});
        if (response.redirected) throw new Error('Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.');
        const data = await response.json();
        if (!this.isConnected) return;
        if (!response.ok) throw new Error(data.error || 'Không thể tra cứu đơn hàng.');
        const order = data.order;
        const money = value => Number(value).toLocaleString('vi-VN', {maximumFractionDigits: 2}) + ' ₫';
        const statuses = {pending: 'Chờ xử lý', shipping: 'Đang giao', delivered: 'Đã giao', cancelled: 'Đã hủy', returned: 'Đã hoàn trả'};
        document.getElementById('lookup-title').textContent = 'Chi tiết đơn hàng #' + order.id;
        document.getElementById('lookup-date').textContent = 'Ngày tạo ' + order.created_at;
        document.getElementById('lookup-status').textContent = statuses[order.status] || '—';
        document.getElementById('lookup-total').textContent = money(order.total_amount);
        document.getElementById('lookup-voucher').textContent = order.voucher_code
            ? 'Voucher ' + order.voucher_code + ' · Giảm ' + money(order.discount_amount) : '';
        document.getElementById('lookup-cod').textContent = order.cod_status === 'none'
            ? 'Không thu COD' : 'COD ' + money(order.cod_amount) + (order.cod_status === 'collected' ? ' · Đã thu' : ' · Chưa thu');
        const items = document.getElementById('lookup-items');
        items.replaceChildren();
        for (const item of data.items) {
            const row = document.createElement('tr');
            for (const value of [item.name, item.sku || '—', item.quantity, money(item.unit_price), money(item.total_price)]) {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.append(cell);
            }
            items.append(row);
        }
        message.textContent = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('lookup-modal')).show();
    } catch (error) {
        message.textContent = error.message;
    } finally {
        button.disabled = false;
    }
}));

const shopForm = document.getElementById('shop-search');
const shopResults = document.getElementById('shop-results');
shopForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    const button = this.querySelector('button');
    button.disabled = true;
    shopResults.setAttribute('aria-busy', 'true');
    try {
        const params = new URLSearchParams(new FormData(this));
        params.set('ajax', '1');
        const response = await fetch('customer.php?' + params);
        if (!response.ok) throw new Error('Request failed');
        shopResults.innerHTML = await response.text();
    } catch (error) {
        shopResults.textContent = 'Không thể tải sản phẩm. Vui lòng thử lại.';
    } finally {
        button.disabled = false;
        shopResults.removeAttribute('aria-busy');
    }
});
</script>
<script src="assets/app.js" defer></script>
</body>
</html>
<?php endif; ?>
