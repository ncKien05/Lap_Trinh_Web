<?php
/**
 * config/auth.php
 * Quản lý phiên đăng nhập và bảo vệ các trang yêu cầu quyền truy cập
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

/**
 * Kiểm tra xem người dùng đã đăng nhập hay chưa
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Bắt buộc đăng nhập, nếu chưa đăng nhập thì chuyển hướng về trang login.php
 */
function requireAuth(): void
{
    $loginPath = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/actions/') ? '../login.php' : 'login.php';
    if (!isLoggedIn()) {
        $_SESSION['errors'] = ['Vui lòng đăng nhập để tiếp tục sử dụng hệ thống.'];
        header('Location: ' . $loginPath);
        exit;
    }

    $stmt = getDBConnection()->prepare('SELECT id, username, name, email, role, customer_id FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !in_array($user['role'], ['admin', 'staff', 'customer'], true)) {
        unset($_SESSION['user_id'], $_SESSION['user']);
        $_SESSION['errors'] = ['Phiên đăng nhập không còn hợp lệ. Vui lòng đăng nhập lại.'];
        header('Location: ' . $loginPath);
        exit;
    }
    $_SESSION['user'] = $user;
}

function canManageOrders(): bool
{
    return in_array($_SESSION['user']['role'] ?? '', ['admin', 'staff'], true);
}

function requireStaff(): void
{
    requireAuth();
    if (!canManageOrders()) {
        http_response_code(403);
        exit('Bạn không có quyền thực hiện chức năng này.');
    }
}

/**
 * Lấy thông tin tài khoản đang đăng nhập
 */
function getCurrentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}
