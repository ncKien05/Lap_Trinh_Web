<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

requireStaff();

$activePage = 'customers';
$pageTitle = 'Khách hàng';
$columns = [
    'id' => 'Mã khách hàng',
    'name' => 'Tên khách hàng',
    'phone' => 'Số điện thoại',
    'email' => 'Email',
    'address' => 'Địa chỉ',
];

try {
    $rows = getDBConnection()->query('SELECT id, name, phone, email, address FROM customers ORDER BY name, id')->fetchAll();
} catch (PDOException $e) {
    error_log('Lỗi tải khách hàng: ' . $e->getMessage());
    $listError = 'Không thể tải danh sách khách hàng.';
}

require __DIR__ . '/includes/catalog-list.php';
