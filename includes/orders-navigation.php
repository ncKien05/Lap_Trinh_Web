<?php
$navigationTitles = [
    'orders' => 'Danh sách đơn hàng',
    'customers' => 'Khách hàng',
    'products' => 'Sản phẩm',
    'promotions' => 'Khuyến mãi',
    'reports' => 'Báo cáo',
    'profile' => 'Hồ sơ cá nhân',
];
?>
<header class="orders-topbar">
    <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" data-bs-toggle="offcanvas"
            data-bs-target="#orders-sidebar" aria-controls="orders-sidebar" aria-label="Mở điều hướng">
        <i class="bi bi-list"></i>
    </button>
    <a href="<?= canManageOrders() ? 'index.php' : 'customer.php' ?>" class="orders-brand"><i class="bi bi-box-seam me-2"></i>Order Management</a>
    <span class="d-none d-lg-block small text-secondary"><?= $navigationTitles[$activePage] ?></span>
    <div class="ms-auto">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                <span class="bi bi-person-circle"></span>
                <span><?= htmlspecialchars($currentUser['name'] ?? 'Tài khoản', ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2 text-secondary"></i>Hồ sơ cá nhân</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
            </ul>
        </div>
    </div>
</header>

<div class="orders-layout">
    <aside class="offcanvas-lg offcanvas-start orders-sidebar" tabindex="-1" id="orders-sidebar" aria-labelledby="orders-sidebar-title">
        <div class="offcanvas-header">
            <h2 class="offcanvas-title h6" id="orders-sidebar-title">Điều hướng</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#orders-sidebar" aria-label="Đóng"></button>
        </div>
        <nav class="orders-nav" aria-label="Điều hướng quản lý">
            <a href="<?= canManageOrders() ? 'index.php' : 'customer.php' ?>" <?= $activePage === 'orders' ? 'class="orders-nav-current" aria-current="page"' : '' ?>><i class="bi bi-cart3"></i>Đơn hàng</a>
            <?php if (canManageOrders()): ?>
            <a href="customers.php" <?= $activePage === 'customers' ? 'class="orders-nav-current" aria-current="page"' : '' ?>><i class="bi bi-people"></i>Khách hàng</a>
            <a href="products.php" <?= $activePage === 'products' ? 'class="orders-nav-current" aria-current="page"' : '' ?>><i class="bi bi-box-seam"></i>Sản phẩm</a>
            <a href="voucher-create.php" <?= $activePage === 'promotions' ? 'class="orders-nav-current" aria-current="page"' : '' ?>><i class="bi bi-ticket-perforated"></i>Khuyến mãi</a>
            <a href="reports.php" <?= $activePage === 'reports' ? 'class="orders-nav-current" aria-current="page"' : '' ?>><i class="bi bi-bar-chart-line"></i>Báo cáo</a>
            <?php endif; ?>
            <a href="profile.php" <?= $activePage === 'profile' ? 'class="orders-nav-current" aria-current="page"' : '' ?>><i class="bi bi-person-circle"></i>Hồ sơ cá nhân</a>
        </nav>
    </aside>

