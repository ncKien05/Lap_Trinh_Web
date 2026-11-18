<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/order-pricing.php';

requireStaff();

// Tạo CSRF token nếu chưa có
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$currentUser = getCurrentUser();

// Thông báo từ session
$successMsg = $_SESSION['success'] ?? null;
$errorMsgs  = $_SESSION['errors']  ?? [];
unset($_SESSION['success'], $_SESSION['errors']);

// ── Thu thập các tham số tìm kiếm & lọc ─────────────────────────────────────────
$searchKeyword = trim($_GET['search'] ?? '');
$customerId = filter_var($_GET['customer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$filterStatus  = trim($_GET['status'] ?? '');
$filterDate    = trim($_GET['date'] ?? '');
$sortBy        = trim($_GET['sort'] ?? 'created_desc');

$orders = [];
$itemsByOrder    = [];

try {
    $pdo = getDBConnection();

    // 2. Xây dựng điều kiện lọc
    $conditions = [];
    $params     = [];
    if ($customerId) {
        $conditions[] = 'o.customer_id = :customer_id';
        $params[':customer_id'] = $customerId;
    }

    if ($searchKeyword !== '') {
        $cleanSearch = ltrim($searchKeyword, '#');
        $searchPattern = '%' . $searchKeyword . '%';
        $params[':search_name'] = $searchPattern;
        $params[':search_phone'] = $searchPattern;
        if (is_numeric($cleanSearch)) {
            $conditions[] = "(o.id = :search_id OR c.phone LIKE :search_phone OR c.name LIKE :search_name)";
            $params[':search_id'] = (int)$cleanSearch;
        } else {
            $conditions[] = "(c.name LIKE :search_name OR c.phone LIKE :search_phone OR EXISTS (
                SELECT 1 FROM order_items oi
                INNER JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = o.id AND p.name LIKE :search_product
            ))";
            $params[':search_product'] = $searchPattern;
        }
    }

    $allowedStatuses = ['pending', 'shipping', 'delivered', 'cancelled', 'returned'];
    if ($filterStatus !== '' && in_array($filterStatus, $allowedStatuses, true)) {
        $conditions[] = "o.status = :status";
        $params[':status'] = $filterStatus;
    }

    if (!empty($filterDate)) {
        $conditions[] = "DATE(o.created_at) = :filter_date";
        $params[':filter_date'] = $filterDate;
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Sắp xếp
    $orderClause = "ORDER BY o.created_at DESC";
    if ($sortBy === 'created_asc') {
        $orderClause = "ORDER BY o.created_at ASC";
    } elseif ($sortBy === 'amount_desc') {
        $orderClause = "ORDER BY o.total_amount DESC";
    } elseif ($sortBy === 'amount_asc') {
        $orderClause = "ORDER BY o.total_amount ASC";
    }

    // 4. Query lấy danh sách đơn hàng
    $sql = "SELECT
                o.id,
                o.total_amount,
                o.voucher_code,
                o.discount_amount,
                o.cod_amount,
                o.cod_status,
                o.status,
                o.note,
                o.created_at,
                o.updated_at,
                c.id AS customer_id,
                c.name AS customer_name,
                c.phone AS customer_phone,
                c.email AS customer_email,
                c.address AS customer_address
            FROM orders o
            INNER JOIN customers c ON o.customer_id = c.id
            {$whereClause}
            {$orderClause}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // 5. Chi tiết sản phẩm theo đơn hàng (dành cho Modal xem chi tiết và in)
    $orderIds = array_column($orders, 'id');
    if (!empty($orderIds)) {
        $inClause = implode(',', array_map('intval', $orderIds));
        $itemsSql = "
            SELECT 
                oi.order_id,
                oi.product_id,
                oi.quantity,
                oi.unit_price,
                oi.total_price,
                p.name AS product_name,
                COALESCE(p.sku, '') AS product_sku
            FROM order_items oi
            INNER JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id IN ({$inClause})
            ORDER BY oi.id ASC
        ";
        $rawOrderItems = $pdo->query($itemsSql)->fetchAll();
        foreach ($rawOrderItems as $it) {
            $itemsByOrder[$it['order_id']][] = [
                'name'        => $it['product_name'],
                'sku'         => $it['product_sku'],
                'quantity'    => (int)$it['quantity'],
                'unit_price'  => (float)$it['unit_price'],
                'total_price' => (float)$it['total_price'],
            ];
        }
    }

} catch (PDOException $e) {
    error_log('Lỗi truy vấn danh sách đơn hàng: ' . $e->getMessage());
    $orders = [];
}

// Helper badge trạng thái (màu basic tinh tế)
function getStatusBadge(string $status): string
{
    $map = [
        'pending'   => ['label' => 'Chờ xử lý', 'class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
        'shipping'  => ['label' => 'Đang giao',  'class' => 'bg-info-subtle text-info-emphasis border border-info-subtle'],
        'delivered' => ['label' => 'Đã giao',    'class' => 'bg-success-subtle text-success-emphasis border border-success-subtle'],
        'cancelled' => ['label' => 'Đã hủy',     'class' => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle'],
        'returned'  => ['label' => 'Đã hoàn trả', 'class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
    ];
    $info  = $map[$status] ?? ['label' => 'Không rõ', 'class' => 'bg-secondary-subtle text-secondary-emphasis'];
    $label = htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8');
    $class = htmlspecialchars($info['class'], ENT_QUOTES, 'UTF-8');
    return "<span class=\"badge {$class}\" style=\"font-weight: 500;\">{$label}</span>";
}

function formatVND(float $amount): string
{
    return number_format($amount, 0, ',', '.') . '₫';
}

// ── Dữ liệu cho Biểu đồ thống kê ──────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Đơn hàng | Order Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .card-custom {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }
        #orders-table th, #orders-table td { white-space: nowrap; }
        #orders-table th { text-transform: none; letter-spacing: normal; }
        #orders-table td { font-size: 0.875rem; }
        #orders-table .btn-link { font-size: inherit; }
        .table thead th {
            background-color: #f1f5f9;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            border-bottom: 1px solid #cbd5e1;
            padding: 0.75rem 0.9rem;
            white-space: nowrap;
        }
        .table tbody td {
            vertical-align: middle;
            padding: 0.8rem 0.9rem;
            border-color: #f1f5f9;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0.5rem 0.75rem;
            font-size: 0.88rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }
        /* Click để copy khách hàng */
        .copy-customer {
            cursor: pointer;
            border-radius: 6px;
            padding: 3px 6px;
            margin: -3px -6px;
            transition: background-color 0.15s;
        }
        .copy-customer:hover {
            background-color: #f1f5f9;
        }
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        /* Toast thông báo copy */
        #copyToast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1090;
            min-width: 250px;
        }
        /* Vùng in ấn */
        @media print {
            body * {
                visibility: hidden;
            }
            #print-section, #print-section * {
                visibility: visible;
            }
            #print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: #ffffff;
                padding: 10px;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/forms.css?v=20261108-teal">
    <link rel="stylesheet" href="assets/orders-layout.css?v=20261108-teal">
</head>
<body>

<?php
$activePage = 'orders';
require __DIR__ . '/includes/orders-navigation.php';
?>
    <main class="orders-main">
        <div class="orders-page-heading">
            <h1>Danh sách đơn hàng</h1>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary bg-white" id="btn-print-orders">
                    <i class="bi bi-printer me-1"></i>In danh sách (<span class="print-label">toàn bộ</span>)
                </button>
                <a href="create.php" data-order-form class="btn btn-primary fw-semibold"><i class="bi bi-plus-lg me-1"></i>Tạo đơn</a>
            </div>
        </div>
        <?php if ($customerId): ?>
            <div class="alert alert-light border d-flex flex-wrap justify-content-between gap-2">
                <span>Đơn hàng của khách hàng #<?= $customerId ?></span>
                <a href="customers.php">Về danh sách khách hàng</a>
            </div>
        <?php endif; ?>

    <!-- Thông báo -->
    <?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 py-2 px-3 small border-0 shadow-sm" role="alert">
        <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errorMsgs)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2 px-3 small border-0 shadow-sm" role="alert">
        <strong>Có lỗi xảy ra:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errorMsgs as $err): ?>
                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ── BẢNG ĐƠN HÀNG VÀ BỘ LỌC ── -->
    <div class="card card-custom">
        <div class="card-header bg-white border-bottom p-3">
            <nav class="order-tabs" aria-label="Lọc nhanh theo trạng thái">
                <?php foreach (['' => 'Tất cả', 'pending' => 'Chờ xử lý', 'shipping' => 'Đang giao', 'delivered' => 'Đã giao', 'returned' => 'Đã hoàn trả', 'cancelled' => 'Đã hủy'] as $tabStatus => $tabLabel): ?>
                <?php $tabQuery = http_build_query(['search' => $searchKeyword, 'status' => $tabStatus, 'date' => $filterDate, 'sort' => $sortBy, 'customer_id' => $customerId ?: null]); ?>
                <a href="index.php?<?= htmlspecialchars($tabQuery, ENT_QUOTES, 'UTF-8') ?>"
                   <?= $filterStatus === $tabStatus ? 'class="active" aria-current="page"' : '' ?>><?= $tabLabel ?></a>
                <?php endforeach; ?>
            </nav>
            <form method="GET" action="index.php" id="filter-form">
                <?php if ($customerId): ?>
                    <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                <?php endif; ?>
                <div class="order-search">
                    <label for="filter-search" class="visually-hidden">Từ khóa</label>
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="filter-search" name="search" class="form-control"
                           placeholder="Tìm mã đơn, tên khách, SĐT, sản phẩm"
                           value="<?= htmlspecialchars($searchKeyword, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-outline-secondary">Tìm kiếm</button>
                </div>
                <div class="mt-3">
                    <div class="row g-2 mt-1 align-items-end">
                        <!-- Lọc trạng thái -->
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filter-status" class="form-label small fw-semibold">Trạng thái</label>
                            <select id="filter-status" name="status" class="form-select">
                                <option value="">Tất cả trạng thái</option>
                                <option value="pending"   <?= $filterStatus === 'pending'   ? 'selected' : '' ?>>Chờ xử lý</option>
                                <option value="shipping"  <?= $filterStatus === 'shipping'  ? 'selected' : '' ?>>Đang giao</option>
                                <option value="delivered" <?= $filterStatus === 'delivered' ? 'selected' : '' ?>>Đã giao</option>
                                <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                                <option value="returned" <?= $filterStatus === 'returned' ? 'selected' : '' ?>>Đã hoàn trả</option>
                            </select>
                        </div>
                        
                        <!-- Lọc theo ngày -->
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filter-date" class="form-label small fw-semibold">Ngày tạo</label>
                            <input type="date" id="filter-date"
                                   name="date"
                                   class="form-control"
                                   title="Lọc theo ngày cụ thể"
                                   value="<?= htmlspecialchars($filterDate, ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <!-- Sắp xếp -->
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filter-sort" class="form-label small fw-semibold">Sắp xếp</label>
                            <select id="filter-sort" name="sort" class="form-select">
                                <option value="created_desc" <?= $sortBy === 'created_desc' ? 'selected' : '' ?>>Mới nhất trước</option>
                                <option value="created_asc"  <?= $sortBy === 'created_asc'  ? 'selected' : '' ?>>Cũ nhất trước</option>
                                <option value="amount_desc"  <?= $sortBy === 'amount_desc'  ? 'selected' : '' ?>>Tiền cao ➜ thấp</option>
                                <option value="amount_asc"   <?= $sortBy === 'amount_asc'   ? 'selected' : '' ?>>Tiền thấp ➜ cao</option>
                            </select>
                        </div>

                        <!-- Nút bấm Lọc & Reset -->
                        <div class="col-12 col-sm-6 col-lg-2 d-flex gap-2">
                            <button type="submit" class="btn btn-dark px-3">
                                <i class="bi bi-funnel me-1"></i>Lọc
                            </button>
                            <?php if ($searchKeyword || $filterStatus || $filterDate || $sortBy !== 'created_desc'): ?>
                            <a href="index.php<?= $customerId ? '?customer_id=' . $customerId : '' ?>" class="btn btn-outline-secondary" title="Xóa bộ lọc" aria-label="Xóa bộ lọc">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>

            <div class="mt-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Đã chọn: <strong id="selected-count" class="text-primary">0</strong> đơn hàng</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-select-all-toggle">
                        <i class="bi bi-check-all me-1"></i>Chọn tất cả
                    </button>
                </div>

            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($orders)): ?>
            <div class="p-5 text-center text-muted">
                <div class="fs-1 text-secondary mb-2"><i class="bi bi-inbox"></i></div>
                <h6 class="fw-semibold">Không tìm thấy đơn hàng nào</h6>
                <p class="small mb-3">Vui lòng thử lại với bộ lọc khác hoặc thêm đơn hàng mới.</p>
                <a href="create.php" data-order-form class="btn btn-sm btn-dark">
                    <i class="bi bi-plus-lg me-1"></i>Thêm đơn hàng
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="orders-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="text-center">
                                <input type="checkbox" class="form-check-input" id="check-all" title="Chọn tất cả">
                            </th>
                            <th>Mã đơn hàng</th>
                            <th>Ngày tạo</th>
                            <th>Khách hàng</th>
                            <th>Giao hàng</th>
                            <th>COD</th>
                            <th class="text-end">Tổng tiền</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): 
                            $orderItemsData = $itemsByOrder[$order['id']] ?? [];
                            $orderJson = json_encode([
                                'id'               => $order['id'],
                                'customer_name'   => $order['customer_name'],
                                'customer_phone'  => $order['customer_phone'],
                                'customer_email'  => $order['customer_email'],
                                'customer_address'=> $order['customer_address'],
                                'status'          => $order['status'],
                                'status_badge'    => getStatusBadge($order['status']),
                                'total_amount'    => formatVND((float)$order['total_amount']),
                                'cod'             => formatOrderCod($order),
                                'total_raw'       => (float)$order['total_amount'],
                                'voucher_code'    => $order['voucher_code'] ?? '',
                                'discount_amount' => (float)($order['discount_amount'] ?? 0),
                                'discount_fmt'    => formatVND((float)($order['discount_amount'] ?? 0)),
                                'note'            => $order['note'],
                                'created_at'      => date('d/m/Y H:i:s', strtotime($order['created_at'])),
                                'items'           => $orderItemsData,
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <tr data-order-id="<?= (int)$order['id'] ?>">
                            <!-- Checkbox -->
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input order-checkbox" value="<?= (int)$order['id'] ?>">
                            </td>

                            <td>
                                <button type="button" class="btn btn-link p-0 text-decoration-none btn-view-order"
                                        data-order='<?= $orderJson ?>' data-bs-toggle="modal" data-bs-target="#viewDetailModal"
                                        aria-label="Xem đơn hàng #<?= (int)$order['id'] ?>">
                                    #<?= (int)$order['id'] ?>
                                </button>
                            </td>
                            <td class="text-muted text-nowrap"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                            <!-- Khách hàng (Click để sao chép Tên & SĐT) -->
                            <td>
                                <div class="copy-customer"
                                     data-name="<?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>"
                                     data-phone="<?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?>"
                                     title="Nhấn để sao chép Tên & SĐT">
                                    <div class="fw-semibold text-dark d-flex align-items-center gap-1">
                                        <span><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <i class="bi bi-copy text-muted opacity-50 small" style="font-size: 0.75rem;"></i>
                                    </div>
                                </div>
                            </td>

                            <td><?= getStatusBadge($order['status']) ?></td>
                            <td>
                                <?php if ($order['cod_status'] === 'none'): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <span class="badge <?= $order['cod_status'] === 'collected' ? 'text-bg-success' : 'bg-warning-subtle text-warning-emphasis' ?>">
                                        <?= htmlspecialchars(formatOrderCod($order), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <!-- Tổng tiền -->
                            <td class="text-end">
                                <div class="fw-bold text-dark"><?= formatVND((float)$order['total_amount']) ?></div>
                                <?php if (!empty($order['voucher_code'])): ?>
                                <div class="mt-1">
                                    <span class="badge bg-light text-dark border small" title="Voucher: <?= htmlspecialchars($order['voucher_code'], ENT_QUOTES, 'UTF-8') ?> (-<?= formatVND((float)$order['discount_amount']) ?>)">
                                        <i class="bi bi-ticket-perforated text-success me-1"></i><?= htmlspecialchars($order['voucher_code'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </td>

                            <!-- Thao tác -->
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button type="button" class="btn btn-outline-secondary btn-action btn-view-order"
                                            data-order='<?= $orderJson ?>' data-bs-toggle="modal" data-bs-target="#viewDetailModal"
                                            title="Xem đơn hàng" aria-label="Xem đơn hàng #<?= (int)$order['id'] ?>">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>

                                    <!-- Sửa -->
                                    <a href="edit.php?id=<?= (int)$order['id'] ?>" data-order-form
                                       class="btn btn-outline-primary btn-action"
                                       title="Chỉnh sửa đơn hàng">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <!-- Xóa -->
                                    <button type="button"
                                            class="btn btn-outline-danger btn-action btn-delete-trigger"
                                            title="Xóa đơn hàng"
                                            data-order-id="<?= (int)$order['id'] ?>"
                                            data-customer="<?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top d-flex align-items-center justify-content-between text-muted small flex-wrap gap-2">
                <div>Hiển thị <strong><?= count($orders) ?></strong> đơn hàng</div>
                <div>Tổng doanh thu danh sách: <strong><?= formatVND((float)array_sum(array_column($orders, 'total_amount'))) ?></strong></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    </main>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL XEM CHI TIẾT & IN HÓA ĐƠN TỪNG ĐƠN
═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="orderFormModal" tabindex="-1" aria-labelledby="orderFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="orderFormTitle">Tạo đơn hàng</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body order-form-page">
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewDetailModal" tabindex="-1" aria-labelledby="viewDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold" id="viewDetailModalLabel">
                    <i class="bi bi-receipt me-2 text-primary"></i>Chi tiết Đơn hàng <span id="view-order-id">#</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body p-4" id="printable-single-area">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold mb-1 text-dark">HÓA ĐƠN BÁN HÀNG</h4>
                        <div class="text-muted small">Thời gian tạo: <span id="view-created-at" class="fw-semibold"></span></div>
                    </div>
                    <div class="text-end">
                        <div id="view-status-badge" class="mb-1"></div>
                        <div class="text-muted small">Mã đơn: <strong id="view-order-code" class="text-dark"></strong></div>
                    </div>
                </div>

                <div class="bg-light p-3 rounded mb-3 border">
                    <h6 class="fw-bold text-secondary mb-2 small text-uppercase"><i class="bi bi-person me-1"></i>Thông tin Khách hàng</h6>
                    <div class="row g-2 small">
                        <div class="col-md-6"><strong>Khách hàng:</strong> <span id="view-customer-name"></span></div>
                        <div class="col-md-6"><strong>Số điện thoại:</strong> <span id="view-customer-phone"></span></div>
                        <div class="col-md-6"><strong>Email:</strong> <span id="view-customer-email"></span></div>
                        <div class="col-md-6"><strong>Địa chỉ:</strong> <span id="view-customer-address"></span></div>
                        <div class="col-md-6"><strong>COD:</strong> <span id="view-cod"></span></div>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Sản phẩm</th>
                                <th>Mã SKU</th>
                                <th class="text-end">Đơn giá</th>
                                <th class="text-center">Số lượng</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody id="view-items-body"></tbody>
                        <tfoot>
                            <tr id="view-voucher-row" style="display: none;">
                                <th colspan="5" class="text-end text-muted fw-normal">Giảm giá voucher (<span id="view-voucher-code" class="text-success fw-bold"></span>):</th>
                                <th class="text-end text-danger fw-semibold" id="view-discount-amount">-0₫</th>
                            </tr>
                            <tr>
                                <th colspan="5" class="text-end fw-bold">Tổng thanh toán:</th>
                                <th class="text-end fw-bold fs-5 text-dark" id="view-total-amount">0₫</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="small p-2 rounded bg-light border">
                    <strong>Ghi chú:</strong> <span id="view-note" class="text-muted">Không có</span>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-between">
                <button type="button" class="btn btn-outline-dark btn-sm" onclick="printSingleOrder()">
                    <i class="bi bi-printer me-1"></i>In đơn này
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                    <a href="#" id="view-edit-link" data-order-form class="btn btn-dark btn-sm">
                        <i class="bi bi-pencil me-1"></i>Chỉnh sửa
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     KHU VỰC IN DANH SÁCH TỔNG HỢP (PRINT SHEET)
═══════════════════════════════════════════════════════════════════ -->
<div id="print-section" class="d-none">
    <div class="p-3">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
            <div>
                <h4 class="fw-bold mb-0">DANH SÁCH ĐƠN HÀNG</h4>
                <div class="small text-muted">Thời điểm in: <?= date('d/m/Y H:i:s') ?></div>
            </div>
            <div class="text-end">
                <div class="fw-semibold">Hệ thống Quản lý Đơn hàng</div>
                <div class="small text-muted">Người in: <?= htmlspecialchars($currentUser['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <table class="table table-bordered align-middle small" style="width: 100%;">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">Mã</th>
                    <th>Thời gian</th>
                    <th>Khách hàng</th>
                    <th>Số điện thoại</th>
                    <th>Địa chỉ nhận</th>
                    <th>Chi tiết mặt hàng</th>
                    <th class="text-end">Tổng tiền</th>
                    <th class="text-center">Trạng thái</th>
                </tr>
            </thead>
            <tbody id="print-table-body">
                <!-- Chèn nội dung in qua JS -->
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6" class="text-end fw-bold">Tổng cộng:</th>
                    <th class="text-end fw-bold" id="print-total-amount">0₫</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL XÁC NHẬN XÓA
═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="actions/delete.php" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" id="delete-order-id">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-danger" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>Xác nhận xóa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4 text-center">
                <p class="mb-1">Bạn có chắc chắn muốn xóa đơn hàng <strong id="modal-order-id" class="text-danger"></strong>?</p>
                <p class="mb-0 text-muted small">Khách hàng: <strong id="modal-customer-name"></strong></p>
                <div class="small text-muted mt-2">Dữ liệu chi tiết mặt hàng và lịch sử liên quan sẽ bị xóa vĩnh viễn.</div>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Hủy bỏ</button>
                <button type="submit" class="btn btn-danger btn-sm px-3" id="btn-confirm-delete">Xác nhận xóa</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST THÔNG BÁO SAO CHÉP -->
<div class="toast align-items-center text-bg-dark border-0 shadow" id="copyToast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
        <div class="toast-body small py-2">
            <i class="bi bi-check2-circle text-success me-1"></i>
            <span id="copyToastText">Đã sao chép vào bộ nhớ tạm!</span>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(() => {
    const allOrdersData = {};

    // 1. Lưu trữ dữ liệu JSON của các đơn hàng trên trang
    document.querySelectorAll('.btn-view-order').forEach(btn => {
        try {
            const data = JSON.parse(btn.getAttribute('data-order'));
            allOrdersData[data.id] = data;
        } catch (e) {}
    });

    // 2. Chức năng Click để sao chép Tên & SĐT khách hàng
    const toastEl = document.getElementById('copyToast');
    const toastBs = new bootstrap.Toast(toastEl, { delay: 2500 });
    const toastText = document.getElementById('copyToastText');

    document.querySelectorAll('.copy-customer').forEach(el => {
        el.addEventListener('click', function () {
            const name = this.getAttribute('data-name');
            const phone = this.getAttribute('data-phone');
            const copyContent = `${name} - ${phone}`;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(copyContent).then(() => {
                    toastText.textContent = `Đã sao chép: ${copyContent}`;
                    toastBs.show();
                });
            } else {
                // Fallback nếu trình duyệt cũ
                const input = document.createElement('input');
                input.value = copyContent;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                toastText.textContent = `Đã sao chép: ${copyContent}`;
                toastBs.show();
            }
        });
    });

    // 3. Xử lý Checkbox & chọn nhiều đơn hàng
    const checkAll = document.getElementById('check-all');
    const orderCheckboxes = document.querySelectorAll('.order-checkbox');
    const selectedCountEl = document.getElementById('selected-count');
    const printLabel = document.querySelector('.print-label');
    const btnSelectAllToggle = document.getElementById('btn-select-all-toggle');

    function updateSelectionStatus() {
        const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
        const count = checkedBoxes.length;
        selectedCountEl.textContent = count;

        if (count > 0) {
            printLabel.textContent = `${count} đơn đã chọn`;
        } else {
            printLabel.textContent = 'toàn bộ';
        }

        if (checkAll) {
            checkAll.checked = (count > 0 && count === orderCheckboxes.length);
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            orderCheckboxes.forEach(cb => cb.checked = checkAll.checked);
            updateSelectionStatus();
        });
    }

    if (btnSelectAllToggle) {
        btnSelectAllToggle.addEventListener('click', function () {
            const hasUnchecked = Array.from(orderCheckboxes).some(cb => !cb.checked);
            orderCheckboxes.forEach(cb => cb.checked = hasUnchecked);
            if (checkAll) checkAll.checked = hasUnchecked;
            updateSelectionStatus();
        });
    }

    orderCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectionStatus);
    });

    // 4. Modal Xem chi tiết
    const viewModal = document.getElementById('viewDetailModal');
    let currentDetailData = null;

    viewModal.addEventListener('show.bs.modal', function (event) {
        const triggerBtn = event.relatedTarget;
        const rawData = triggerBtn.getAttribute('data-order');
        if (!rawData) return;

        const data = JSON.parse(rawData);
        currentDetailData = data;

        document.getElementById('view-order-id').textContent = '#' + data.id;
        document.getElementById('view-order-code').textContent = '#' + data.id;
        document.getElementById('view-created-at').textContent = data.created_at;
        document.getElementById('view-status-badge').innerHTML = data.status_badge;

        document.getElementById('view-customer-name').textContent = data.customer_name || '—';
        document.getElementById('view-customer-phone').textContent = data.customer_phone || '—';
        document.getElementById('view-customer-email').textContent = data.customer_email || '—';
        document.getElementById('view-customer-address').textContent = data.customer_address || '—';

        document.getElementById('view-cod').textContent = data.cod;
        document.getElementById('view-total-amount').textContent = data.total_amount;

        const voucherRow = document.getElementById('view-voucher-row');
        if (data.voucher_code) {
            voucherRow.style.display = '';
            document.getElementById('view-voucher-code').textContent = data.voucher_code;
            document.getElementById('view-discount-amount').textContent = '-' + data.discount_fmt;
        } else {
            voucherRow.style.display = 'none';
        }

        document.getElementById('view-note').textContent = data.note || 'Không có ghi chú';
        document.getElementById('view-edit-link').href = `edit.php?id=${encodeURIComponent(data.id)}`;

        const tbody = document.getElementById('view-items-body');
        tbody.innerHTML = '';

        if (data.items && data.items.length > 0) {
            data.items.forEach(function (item, idx) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${idx + 1}</td>
                    <td class="fw-medium">${escapeHtml(item.name)}</td>
                    <td><code>${escapeHtml(item.sku || '—')}</code></td>
                    <td class="text-end">${Number(item.unit_price).toLocaleString('vi-VN')}₫</td>
                    <td class="text-center">${item.quantity}</td>
                    <td class="text-end fw-semibold">${Number(item.total_price).toLocaleString('vi-VN')}₫</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Không có dữ liệu mặt hàng</td></tr>';
        }
    });

    window.printSingleOrder = function () {
        window.print();
    };

    // 5. In danh sách (toàn bộ hoặc các đơn được chọn)
    const btnPrintOrders = document.getElementById('btn-print-orders');
    btnPrintOrders.addEventListener('click', function () {
        const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
        let targetOrders = [];

        if (checkedBoxes.length > 0) {
            checkedBoxes.forEach(cb => {
                const orderId = cb.value;
                if (allOrdersData[orderId]) {
                    targetOrders.push(allOrdersData[orderId]);
                }
            });
        } else {
            targetOrders = Object.values(allOrdersData);
        }

        if (targetOrders.length === 0) {
            alert('Không có đơn hàng nào để in.');
            return;
        }

        // Render vào bảng in
        const printTbody = document.getElementById('print-table-body');
        printTbody.innerHTML = '';
        let grandTotal = 0;

        targetOrders.forEach(o => {
            grandTotal += (o.total_raw || 0);
            const itemsStr = (o.items || []).map(i => `${escapeHtml(i.name)} (x${i.quantity})`).join(', ') || '—';
            const voucherText = o.voucher_code ? `<br><small class="text-muted">(Voucher: ${escapeHtml(o.voucher_code)} -${o.discount_fmt})</small>` : '';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-bold">#${o.id}</td>
                <td>${o.created_at}</td>
                <td class="fw-semibold">${escapeHtml(o.customer_name)}</td>
                <td>${escapeHtml(o.customer_phone)}</td>
                <td>${escapeHtml(o.customer_address || '—')}</td>
                <td>${itemsStr}</td>
                <td class="text-end fw-semibold">${o.total_amount}${voucherText}</td>
                <td class="text-center">${o.status_badge}</td>
            `;
            printTbody.appendChild(tr);
        });

        document.getElementById('print-total-amount').textContent = Number(grandTotal).toLocaleString('vi-VN') + '₫';

        const printSection = document.getElementById('print-section');
        printSection.classList.remove('d-none');
        window.print();
        printSection.classList.add('d-none');
    });

    // 6. Modal xác nhận xóa
    const deleteModal   = document.getElementById('deleteModal');
    const modalOrderId  = document.getElementById('modal-order-id');
    const modalCustomer = document.getElementById('modal-customer-name');
    const deleteOrderId = document.getElementById('delete-order-id');

    deleteModal.addEventListener('show.bs.modal', function (event) {
        const triggerBtn   = event.relatedTarget;
        const orderId      = triggerBtn.getAttribute('data-order-id');
        const customerName = triggerBtn.getAttribute('data-customer');

        modalOrderId.textContent  = '#' + orderId;
        modalCustomer.textContent = customerName;
        deleteOrderId.value = orderId;
    });

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

})();
</script>
<script src="assets/app.js" defer></script>
</body>
</html>
