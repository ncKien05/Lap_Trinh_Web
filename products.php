<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

requireStaff();

$activePage = 'products';
$pageTitle = 'Sản phẩm';
$columns = [
    'id' => 'Mã sản phẩm',
    'name' => 'Tên sản phẩm',
    'sku' => 'SKU',
    'price' => 'Giá bán',
    'stock' => 'Tồn kho',
    'description' => 'Mô tả',
];

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$success = $_SESSION['product_success'] ?? null;
unset($_SESSION['product_success']);
$pdo = getDBConnection();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        $actionError = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            if (isset($_POST['delete_id'])) {
                $id = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$id) {
                    throw new InvalidArgumentException('Mã sản phẩm không hợp lệ.');
                }
                $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
                $stmt->execute([$id]);
                if (!$stmt->rowCount()) {
                    throw new InvalidArgumentException('Sản phẩm này không còn trong danh sách.');
                }
                $successMessage = 'Đã xóa sản phẩm.';
            } else {
                $productForm = ['id' => '', 'name' => '', 'sku' => '', 'price' => '', 'stock' => '', 'description' => '', 'original_stock' => ''];
                foreach ($productForm as $field => $value) {
                    $productForm[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
                }
                $id = $productForm['id'];
                $stock = filter_var($productForm['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
                if ($productForm['name'] === '' || mb_strlen($productForm['name'], 'UTF-8') > 255 ||
                    mb_strlen($productForm['sku'], 'UTF-8') > 50 ||
                    !preg_match('/\A[0-9]{1,13}(?:\.[0-9]{1,2})?\z/', $productForm['price']) ||
                    $stock === false || strlen($productForm['description']) > 65535) {
                    throw new InvalidArgumentException('Vui lòng kiểm tra tên, SKU, giá bán, tồn kho và mô tả.');
                }
                $values = [$productForm['name'], $productForm['sku'] === '' ? null : $productForm['sku'],
                    $productForm['price'], $stock, $productForm['description'] === '' ? null : $productForm['description']];
                if ($id === '') {
                    $stmt = $pdo->prepare('INSERT INTO products (name, sku, price, stock, description) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute($values);
                    $successMessage = 'Đã thêm sản phẩm.';
                } else {
                    $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    $originalStock = filter_var($productForm['original_stock'], FILTER_VALIDATE_INT);
                    if (!$id || $originalStock === false) {
                        throw new InvalidArgumentException('Thông tin sản phẩm không hợp lệ.');
                    }
                    $stmt = $pdo->prepare('UPDATE products SET name = ?, sku = ?, price = ?, stock = ?, description = ? WHERE id = ? AND stock = ?');
                    $stmt->execute([...$values, $id, $originalStock]);
                    if (!$stmt->rowCount()) {
                        $check = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
                        $check->execute([$id]);
                        $currentStock = $check->fetchColumn();
                        if ($currentStock === false || (int)$currentStock !== $originalStock) {
                            throw new InvalidArgumentException('Sản phẩm hoặc tồn kho đã thay đổi. Vui lòng mở lại form sửa.');
                        }
                    }
                    $successMessage = 'Đã cập nhật sản phẩm.';
                }
            }
            $_SESSION['product_success'] = $successMessage;
            header('Location: products.php', true, 303);
            exit;
        } catch (InvalidArgumentException $e) {
            $actionError = $e->getMessage();
        } catch (PDOException $e) {
            $actionError = match ((int)($e->errorInfo[1] ?? 0)) {
                1062 => 'Mã SKU đã được dùng cho sản phẩm khác.',
                1451 => 'Sản phẩm đã có trong đơn hàng nên không thể xóa.',
                default => 'Không thể lưu thay đổi. Vui lòng thử lại.',
            };
            error_log('Lỗi cập nhật sản phẩm: ' . $e->getMessage());
        }
    }
}

try {
    $rows = $pdo->query('SELECT id, name, sku, price, stock, description FROM products ORDER BY name, id')->fetchAll();
    if (isset($_GET['edit']) && !isset($productForm)) {
        $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
        foreach ($rows as $product) {
            if ((int)$product['id'] === $editId) {
                $productForm = $product + ['original_stock' => $product['stock']];
                break;
            }
        }
        if (!isset($productForm)) {
            $actionError = 'Không tìm thấy sản phẩm.';
        }
    } elseif (isset($_GET['add']) && !isset($productForm)) {
        $productForm = ['id' => '', 'name' => '', 'sku' => '', 'price' => '', 'stock' => '0', 'description' => '', 'original_stock' => ''];
    }
} catch (PDOException $e) {
    error_log('Lỗi tải sản phẩm: ' . $e->getMessage());
    $listError = 'Không thể tải danh sách sản phẩm.';
}

require __DIR__ . '/includes/catalog-list.php';
