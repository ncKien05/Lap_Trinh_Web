<?php
/**
 * actions/update.php
 * Xử lý cập nhật thông tin đơn hàng (POST request từ edit.php)
 * -------------------------------------------------------------
 * - Cập nhật thông tin khách hàng trực tiếp
 * - Cập nhật chi tiết sản phẩm, tổng tiền
 * - Ghi log lịch sử trạng thái nếu có thay đổi
 * - Sử dụng Database Transaction
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/order-pricing.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['errors'] = ['ID đơn hàng không hợp lệ.'];
    header('Location: ../index.php');
    exit;
}

// Xác thực CSRF token
$tokenFromRequest = $_POST['csrf_token'] ?? '';
$tokenFromSession = $_SESSION['csrf_token'] ?? '';

if (empty($tokenFromRequest) || !hash_equals($tokenFromSession, $tokenFromRequest)) {
    $_SESSION['errors'] = ['Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ. Vui lòng thử lại.'];
    header("Location: ../edit.php?id={$id}");
    exit;
}

// 1. Thu thập dữ liệu
$customerName    = trim($_POST['customer_name'] ?? '');
$customerPhone   = trim($_POST['customer_phone'] ?? '');
$customerEmail   = trim($_POST['customer_email'] ?? '');
$customerAddress = trim($_POST['customer_address'] ?? '');

$voucherSelection = $_POST['voucher_id'] ?? '';

$rawItems   = $_POST['items'] ?? [];
$status     = trim($_POST['status'] ?? 'pending');
$statusNote = trim($_POST['status_note'] ?? '');
$note       = trim($_POST['note'] ?? '');

$errors = [];

// Validate khách hàng
if (empty($customerName)) {
    $errors[] = 'Tên khách hàng không được để trống.';
}
if (empty($customerPhone) || !preg_match('/^[0-9]{9,11}$/', $customerPhone)) {
    $errors[] = 'Số điện thoại khách hàng không hợp lệ (cần 9 - 11 chữ số).';
}
if (!empty($customerEmail) && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Địa chỉ email không hợp lệ.';
}

// Validate trạng thái
$allowedStatuses = ['pending', 'shipping', 'delivered', 'cancelled', 'returned'];
if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = 'Trạng thái đơn hàng không hợp lệ.';
}

// Validate danh sách sản phẩm
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

        $subtotal = $qty * round($price, 2);
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
    header("Location: ../edit.php?id={$id}");
    exit;
}

// 2. Cập nhật Database bằng Transaction
try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Kiểm tra đơn hàng tồn tại
    $checkStmt = $pdo->prepare("SELECT id, customer_id, status, voucher_code, discount_amount FROM orders WHERE id = :id FOR UPDATE");
    $checkStmt->execute([':id' => $id]);
    $currentOrder = $checkStmt->fetch();

    if (!$currentOrder) {
        throw new Exception("Đơn hàng #{$id} không tồn tại hoặc đã bị xóa.");
    }

    [$voucherCode, $discountAmount] = resolveOrderVoucher($pdo, $voucherSelection, $currentOrder);
    $finalTotal = max(0, $totalAmount - $discountAmount);
    [$codStatus, $codAmount] = readOrderCod($_POST, $finalTotal);

    $oldStatus  = $currentOrder['status'];
    $customerId = (int)$currentOrder['customer_id'];

    // Cập nhật thông tin khách hàng
    $updateCustStmt = $pdo->prepare("
        UPDATE customers 
        SET name = :name,
            phone = :phone,
            email = :email,
            address = :address
        WHERE id = :id
    ");
    $updateCustStmt->execute([
        ':name'    => $customerName,
        ':phone'   => $customerPhone,
        ':email'   => $customerEmail ?: null,
        ':address' => $customerAddress ?: null,
        ':id'      => $customerId,
    ]);

    // A. Cập nhật bảng orders

    $updateOrderSql = "
        UPDATE orders 
        SET total_amount    = :total_amount,
            voucher_code    = :voucher_code,
            discount_amount = :discount_amount,
            cod_amount      = :cod_amount,
            cod_status      = :cod_status,
            status          = :status,
            note            = :note,
            updated_at      = NOW()
        WHERE id = :id
    ";
    $updateStmt = $pdo->prepare($updateOrderSql);
    $updateStmt->execute([
        ':total_amount'    => $finalTotal,
        ':voucher_code'    => $voucherCode,
        ':discount_amount' => $discountAmount,
        ':cod_amount'      => $codAmount,
        ':cod_status'      => $codStatus,
        ':status'          => $status,
        ':note'            => $note ?: null,
        ':id'              => $id,
    ]);

    // B. Cập nhật các mặt hàng trong order_items
    $deleteItemsStmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = :order_id");
    $deleteItemsStmt->execute([':order_id' => $id]);

    $insertItemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
        VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price)
    ");
    foreach ($validItems as $item) {
        $insertItemStmt->execute([
            ':order_id'    => $id,
            ':product_id'  => $item['product_id'],
            ':quantity'    => $item['quantity'],
            ':unit_price'  => $item['unit_price'],
            ':total_price' => $item['total_price'],
        ]);
    }

    // C. Ghi lịch sử trạng thái nếu có thay đổi hoặc có ghi chú trạng thái
    if ($oldStatus !== $status || !empty($statusNote)) {
        $historyNote = !empty($statusNote) 
            ? $statusNote 
            : "Chuyển trạng thái từ \"{$oldStatus}\" sang \"{$status}\"";

        $historySql = "
            INSERT INTO order_status_history (order_id, old_status, new_status, note)
            VALUES (:order_id, :old_status, :new_status, :note)
        ";
        $historyStmt = $pdo->prepare($historySql);
        $historyStmt->execute([
            ':order_id'   => $id,
            ':old_status' => $oldStatus,
            ':new_status' => $status,
            ':note'       => $historyNote,
        ]);
    }

    $pdo->commit();

    $_SESSION['success'] = "Cập nhật đơn hàng #{$id} thành công!";
    header('Location: ../index.php');
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Lỗi cập nhật đơn hàng: ' . $e->getMessage());
    $_SESSION['errors']    = ['Đã xảy ra lỗi khi cập nhật: ' . $e->getMessage()];
    $_SESSION['old_input'] = $_POST;
    header("Location: ../edit.php?id={$id}");
    exit;
}
