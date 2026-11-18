<?php
/**
 * actions/delete.php
 * Xử lý xóa đơn hàng bằng POST với ID và CSRF trong body.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['errors'] = ['ID đơn hàng không hợp lệ.'];
    header('Location: ../index.php');
    exit;
}

// Xác minh CSRF token
$tokenFromRequest = $_POST['csrf_token'] ?? '';
$tokenFromSession = $_SESSION['csrf_token'] ?? '';

if (empty($tokenFromRequest) || !hash_equals($tokenFromSession, $tokenFromRequest)) {
    $_SESSION['errors'] = ['Yêu cầu không hợp lệ hoặc phiên làm việc đã hết hạn. Vui lòng thử lại.'];
    header('Location: ../index.php');
    exit;
}

try {
    $pdo = getDBConnection();

    // Lấy thông tin tên khách hàng
    $checkStmt = $pdo->prepare("
        SELECT o.id, c.name AS customer_name 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        WHERE o.id = :id 
        LIMIT 1
    ");
    $checkStmt->execute([':id' => $id]);
    $order = $checkStmt->fetch();

    if (!$order) {
        $_SESSION['errors'] = ['Đơn hàng không tồn tại hoặc đã bị xóa.'];
        header('Location: ../index.php');
        exit;
    }

    $customerName = $order['customer_name'] ?? 'Khách lẻ';

    $pdo->beginTransaction();

    // Xóa đơn hàng (CASCADE xóa order_items và order_status_history)
    $deleteStmt = $pdo->prepare("DELETE FROM orders WHERE id = :id");
    $deleteStmt->execute([':id' => $id]);

    $pdo->commit();

    $_SESSION['success'] = "Đã xóa đơn hàng #{$id} của khách \"{$customerName}\" thành công.";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Lỗi xóa đơn hàng: ' . $e->getMessage());
    $_SESSION['errors'] = ['Đã xảy ra lỗi khi xóa đơn hàng. Vui lòng thử lại.'];
}

header('Location: ../index.php');
exit;
