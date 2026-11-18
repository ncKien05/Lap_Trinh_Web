<?php
/**
 * actions/store.php
 * Xử lý thêm mới đơn hàng (POST request từ create.php)
 * -------------------------------------------------------
 * - Nhập thông tin khách hàng trực tiếp, tự động lưu hoặc cập nhật vào bảng customers
 * - Lưu orders, order_items, order_status_history và trừ kho products
 * - Sử dụng Database Transaction
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/order-pricing.php';

requireStaff();
$createUrl = '../create.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// Xác thực CSRF token
$tokenFromRequest = $_POST['csrf_token'] ?? '';
$tokenFromSession = $_SESSION['csrf_token'] ?? '';

if (empty($tokenFromRequest) || !hash_equals($tokenFromSession, $tokenFromRequest)) {
    $_SESSION['errors'] = ['Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ. Vui lòng thử lại.'];
    $_SESSION['old_input'] = $_POST;
    header('Location: ' . $createUrl);
    exit;
}

// 1. Thu thập dữ liệu khách hàng
$customerName    = trim($_POST['customer_name'] ?? '');
$customerPhone   = trim($_POST['customer_phone'] ?? '');
$customerEmail   = trim($_POST['customer_email'] ?? '');
$customerAddress = trim($_POST['customer_address'] ?? '');

$rawItems = $_POST['items'] ?? [];
$status   = trim($_POST['status'] ?? 'pending');
$note     = trim($_POST['note'] ?? '');

$voucherSelection = $_POST['voucher_id'] ?? '';

$errors = [];

// Validate khách hàng
if (empty($customerName)) {
    $errors[] = 'Vui lòng nhập tên khách hàng.';
}
if (empty($customerPhone) || !preg_match('/^[0-9]{9,11}$/', $customerPhone)) {
    $errors[] = 'Số điện thoại khách hàng không hợp lệ (cần 9 - 11 chữ số).';
}
if (!empty($customerEmail) && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Địa chỉ email khách hàng không hợp lệ.';
}

// Validate trạng thái
$allowedStatuses = ['pending', 'shipping', 'delivered', 'cancelled', 'returned'];
if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = 'Trạng thái đơn hàng không hợp lệ.';
}

// Validate danh sách mặt hàng
$validItems = [];
$totalAmount = 0.0;

if (!is_array($rawItems) || empty($rawItems)) {
    $errors[] = 'Đơn hàng phải chứa ít nhất một sản phẩm.';
} else {
    foreach ($rawItems as $idx => $item) {
        $prodId = (int)($item['product_id'] ?? 0);
        $qty    = (int)($item['quantity'] ?? 0);
        $price  = (float)($item['unit_price'] ?? 0.0);

        if ($prodId <= 0) {
            continue;
        }

        if ($qty <= 0) {
            $errors[] = "Sản phẩm ở dòng " . ($idx + 1) . " có số lượng phải lớn hơn 0.";
            continue;
        }

        if ($price < 0) {
            $errors[] = "Sản phẩm ở dòng " . ($idx + 1) . " có đơn giá không hợp lệ.";
            continue;
        }

        $subtotal = $qty * $price;
        $totalAmount += $subtotal;

        $validItems[] = [
            'product_id'  => $prodId,
            'quantity'    => $qty,
            'unit_price'  => $price,
            'total_price' => $subtotal,
        ];
    }

    if (empty($validItems) && empty($errors)) {
        $errors[] = 'Vui lòng chọn ít nhất một sản phẩm hợp lệ cho đơn hàng.';
    }
}

if (!empty($errors)) {
    $_SESSION['errors']    = $errors;
    $_SESSION['old_input'] = $_POST;
    header('Location: ' . $createUrl);
    exit;
}

// 2. Thực hiện lưu vào CSDL bằng Transaction
try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    [$voucherCode, $discountAmount] = resolveOrderVoucher($pdo, $voucherSelection);
    $finalTotal = max(0, $totalAmount - $discountAmount);
    [$codStatus, $codAmount] = readOrderCod($_POST, $finalTotal);

    // A. Xử lý khách hàng (tìm theo phone hoặc tạo mới)
    $findCust = $pdo->prepare("SELECT id FROM customers WHERE phone = :phone LIMIT 1 FOR UPDATE");
    $findCust->execute([':phone' => $customerPhone]);
    $existingCust = $findCust->fetch();

    if ($existingCust) {
        $customerId = (int)$existingCust['id'];
        // Cập nhật thông tin khách hàng nếu có cập nhật mới
        $upCust = $pdo->prepare("
            UPDATE customers 
            SET name = :name,
                email = COALESCE(NULLIF(:email, ''), email),
                address = COALESCE(NULLIF(:address, ''), address)
            WHERE id = :id
        ");
        $upCust->execute([
            ':name'    => $customerName,
            ':email'   => $customerEmail ?: null,
            ':address' => $customerAddress ?: null,
            ':id'      => $customerId,
        ]);
    } else {
        $insCust = $pdo->prepare("
            INSERT INTO customers (name, phone, email, address)
            VALUES (:name, :phone, :email, :address)
        ");
        $insCust->execute([
            ':name'    => $customerName,
            ':phone'   => $customerPhone,
            ':email'   => $customerEmail ?: null,
            ':address' => $customerAddress ?: null,
        ]);
        $customerId = (int)$pdo->lastInsertId();
    }

    // B. Tạo đơn hàng vào bảng orders (tổng tiền sau khi trừ giảm giá voucher)
    $orderSql = "INSERT INTO orders (customer_id, total_amount, voucher_code, discount_amount, cod_amount, cod_status, status, note)
                 VALUES (:customer_id, :total_amount, :voucher_code, :discount_amount, :cod_amount, :cod_status, :status, :note)";
    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([
        ':customer_id'     => $customerId,
        ':total_amount'    => $finalTotal,
        ':voucher_code'    => $voucherCode,
        ':discount_amount' => $discountAmount,
        ':cod_amount'      => $codAmount,
        ':cod_status'      => $codStatus,
        ':status'          => $status,
        ':note'            => $note ?: null,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // C. Lưu order_items & cập nhật tồn kho
    $itemSql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
                VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price)";
    $itemStmt = $pdo->prepare($itemSql);

    $stockSql = "UPDATE products SET stock = GREATEST(0, stock - :quantity) WHERE id = :product_id";
    $stockStmt = $pdo->prepare($stockSql);

    foreach ($validItems as $item) {
        $itemStmt->execute([
            ':order_id'    => $orderId,
            ':product_id'  => $item['product_id'],
            ':quantity'    => $item['quantity'],
            ':unit_price'  => $item['unit_price'],
            ':total_price' => $item['total_price'],
        ]);

        if ($status !== 'cancelled') {
            $stockStmt->execute([
                ':quantity'   => $item['quantity'],
                ':product_id' => $item['product_id'],
            ]);
        }
    }

    // D. Ghi lịch sử trạng thái
    $historySql = "INSERT INTO order_status_history (order_id, old_status, new_status, note)
                   VALUES (:order_id, NULL, :new_status, :note)";
    $historyStmt = $pdo->prepare($historySql);
    $historyStmt->execute([
        ':order_id'   => $orderId,
        ':new_status' => $status,
        ':note'       => 'Đơn hàng mới được tạo trên hệ thống',
    ]);

    $pdo->commit();

    $_SESSION['success'] = "Đơn hàng #{$orderId} đã được tạo thành công!";
    header('Location: ../index.php');
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Lỗi thêm đơn hàng: ' . $e->getMessage());
    $_SESSION['errors']    = ['Đã xảy ra lỗi khi lưu đơn hàng: ' . $e->getMessage()];
    $_SESSION['old_input'] = $_POST;
    header('Location: ' . $createUrl);
    exit;
}
