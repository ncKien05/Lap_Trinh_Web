<?php
/**
 * logout.php
 * Xử lý đăng xuất tài khoản
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['user_id'], $_SESSION['user']);
session_destroy();

session_start();
$_SESSION['success'] = 'Bạn đã đăng xuất an toàn khỏi hệ thống.';
header('Location: login.php');
exit;
