<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/order-pricing.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}
requireStaff();

$customerId = filter_var($_GET['customer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$customerId) {
    http_response_code(400);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT name FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);
    $customerName = $stmt->fetchColumn();
    if ($customerName === false) {
        http_response_code(404);
        exit;
    }
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT o.id, o.created_at, o.status, o.total_amount, o.cod_amount, o.cod_status,
               (SELECT GROUP_CONCAT(CONCAT(p.name, ' × ', oi.quantity) ORDER BY oi.id SEPARATOR ', ')
                FROM order_items oi JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = o.id) AS items
        FROM orders o WHERE o.customer_id = ? ORDER BY o.created_at DESC, o.id DESC
    SQL);
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Lỗi tải đơn hàng khách: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

$statuses = ['pending' => 'Chờ xử lý', 'shipping' => 'Đang giao', 'delivered' => 'Đã giao',
    'cancelled' => 'Đã hủy', 'returned' => 'Đã hoàn trả'];
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<p class="fw-semibold"><?= $escape($customerName) ?> <span class="text-secondary fw-normal">· <?= count($orders) ?> đơn hàng</span></p>
<?php if (!$orders): ?>
    <p class="text-secondary mb-0">Khách hàng này chưa có đơn hàng.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Mã đơn</th>
                    <th scope="col">Ngày tạo</th>
                    <th scope="col">Sản phẩm</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">COD</th>
                    <th scope="col" class="text-end">Tổng tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= (int)$order['id'] ?></td>
                        <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                        <td><?= $escape($order['items'] ?? '—') ?></td>
                        <td class="text-nowrap"><?= $escape($statuses[$order['status']] ?? '—') ?></td>
                        <td class="text-nowrap"><?= $escape(formatOrderCod($order)) ?></td>
                        <td class="text-end text-nowrap"><?= number_format((float)$order['total_amount'], 0, ',', '.') ?> ₫</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
