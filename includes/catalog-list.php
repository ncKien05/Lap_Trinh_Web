<?php $currentUser = getCurrentUser(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pageTitle ?> | Order Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/orders-layout.css?v=20261108-teal">
</head>
<body class="orders-catalog-page">
<?php require __DIR__ . '/orders-navigation.php'; ?>
    <main class="orders-main" id="catalog-content">
        <div class="orders-page-heading">
            <h1><?= $pageTitle ?></h1>
            <?php if ($activePage === 'products'): ?>
                <a href="products.php?add=1" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Thêm sản phẩm</a>
            <?php endif; ?>
        </div>
        <?php if (isset($success)): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (isset($actionError)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($actionError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (isset($productForm)): ?>
            <?php require __DIR__ . '/product-form.php'; ?>
        <?php endif; ?>
        <?php if (isset($listError)): ?>
            <div class="alert alert-danger" role="alert"><?= $listError ?></div>
        <?php else: ?>
            <section class="card shadow-sm" aria-label="Danh sách <?= $pageTitle ?>">
                <div class="card-header bg-white text-secondary small py-3"><?= count($rows) ?> mục</div>
                <?php if (!$rows): ?>
                    <p class="text-secondary p-4 mb-0">Chưa có dữ liệu.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <?php foreach ($columns as $label): ?>
                                        <th scope="col" class="text-nowrap"><?= $label ?></th>
                                    <?php endforeach; ?>
                                    <th scope="col" class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $field => $label): ?>
                                            <?php $value = $row[$field] ?? ''; ?>
                                            <td class="<?= $field === 'name' ? 'fw-semibold' : '' ?>">
                                                <?= $field === 'price' ? number_format((float)$value, 2, ',', '.') . ' ₫' : htmlspecialchars($value === '' ? '—' : (string)$value, ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-end text-nowrap">
                                            <?php if ($activePage === 'customers'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                        data-bs-toggle="modal" data-bs-target="#customer-orders-modal"
                                                        data-customer-id="<?= (int)$row['id'] ?>" title="Xem đơn hàng" aria-label="Xem đơn hàng của khách #<?= (int)$row['id'] ?>">
                                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                                </button>
                                            <?php else: ?>
                                                <a class="btn btn-sm btn-outline-primary" href="products.php?edit=<?= (int)$row['id'] ?>"
                                                   title="Sửa sản phẩm" aria-label="Sửa sản phẩm #<?= (int)$row['id'] ?>">
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </a>
                                                <form method="post" action="products.php" class="d-inline" onsubmit="return confirm('Xóa sản phẩm này?')">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" name="delete_id" value="<?= (int)$row['id'] ?>"
                                                            title="Xóa sản phẩm" aria-label="Xóa sản phẩm #<?= (int)$row['id'] ?>">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php if ($activePage === 'customers'): ?>
    <div class="modal fade" id="customer-orders-modal" tabindex="-1" aria-labelledby="customer-orders-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="customer-orders-title">Đơn hàng của khách</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body" id="customer-orders-body" aria-live="polite"></div>
            </div>
        </div>
    </div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($activePage === 'customers'): ?>
    <script>
    const customerOrdersModal = document.getElementById('customer-orders-modal');
    const customerOrdersBody = document.getElementById('customer-orders-body');
    let customerOrdersRequest;

    customerOrdersModal.addEventListener('show.bs.modal', async function (event) {
        const customerId = event.relatedTarget.dataset.customerId;
        const request = new AbortController();
        customerOrdersRequest?.abort();
        customerOrdersRequest = request;
        customerOrdersBody.textContent = 'Đang tải đơn hàng…';
        try {
            const response = await fetch('actions/customer_orders.php?customer_id=' + encodeURIComponent(customerId), {
                signal: request.signal
            });
            if (!response.ok || response.redirected) throw new Error('Không thể tải dữ liệu');
            const html = await response.text();
            if (!request.signal.aborted) customerOrdersBody.innerHTML = html;
        } catch (error) {
            if (!request.signal.aborted) customerOrdersBody.textContent = 'Không thể tải đơn hàng. Vui lòng thử lại.';
        }
    });

    customerOrdersModal.addEventListener('hide.bs.modal', function () {
        customerOrdersRequest?.abort();
    });
    </script>
<?php endif; ?>
<script src="assets/app.js" defer></script>
</body>
</html>
