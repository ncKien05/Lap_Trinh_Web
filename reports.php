<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

requireStaff();
$currentUser = getCurrentUser();
$activePage = 'reports';
$reportError = false;

$statusCounts = [
    'pending'   => 0,
    'shipping'  => 0,
    'delivered' => 0,
    'cancelled' => 0,
    'returned'  => 0,
];
try {
    $pdo = getDBConnection();

    // 1. Thống kê theo trạng thái
    $countStmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status");
    foreach ($countStmt->fetchAll() as $row) {
        $statusCounts[$row['status']] = (int)$row['cnt'];
    }

    // 1.1 Thống kê biểu đồ doanh thu và số đơn theo ngày (10 mốc gần nhất)
    $chartDailyStmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%d/%m/%Y') AS order_date, 
               COUNT(*) AS order_count, 
               SUM(total_amount) AS total_revenue
        FROM orders 
        GROUP BY DATE(created_at) 
        ORDER BY DATE(created_at) ASC 
        LIMIT 10
    ");
    $chartDailyData = $chartDailyStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Lỗi tải báo cáo: ' . $e->getMessage());
    $reportError = true;
}

$chartDates    = json_encode(array_column($chartDailyData ?? [], 'order_date'));
$chartRevenues = json_encode(array_map('floatval', array_column($chartDailyData ?? [], 'total_revenue')));
$chartCounts   = json_encode(array_map('intval', array_column($chartDailyData ?? [], 'order_count')));

$chartStatusLabels = json_encode(['Chờ xử lý', 'Đang giao', 'Đã giao', 'Đã hủy', 'Đã hoàn trả']);
$chartStatusValues = json_encode([
    (int)($statusCounts['pending'] ?? 0),
    (int)($statusCounts['shipping'] ?? 0),
    (int)($statusCounts['delivered'] ?? 0),
    (int)($statusCounts['cancelled'] ?? 0),
    (int)($statusCounts['returned'] ?? 0),
]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo | Order Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/orders-layout.css?v=20261108-teal">
</head>
<body class="orders-report-page">
<?php require __DIR__ . '/includes/orders-navigation.php'; ?>
    <main class="orders-main">
        <div class="orders-page-heading"><h1>Báo cáo</h1></div>
        <?php if ($reportError): ?>
            <div class="alert alert-danger" role="alert">Không thể tải dữ liệu báo cáo.</div>
        <?php else: ?>
    <!-- ── BIỂU ĐỒ THỐNG KÊ ĐƠN HÀNG (Màu basic, nhã nhặn) ── -->
    <div class="card shadow-sm" id="order-report">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-bar-chart-line me-2 text-primary"></i>Biểu đồ Thống kê Đơn hàng
            </h6>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                <!-- Biểu đồ cột/đường: Doanh thu theo ngày -->
                <div class="col-12 col-md-8">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-semibold text-secondary">
                            <i class="bi bi-graph-up me-1"></i>Doanh thu & Số lượng đơn theo ngày
                        </span>
                    </div>
                    <div class="report-chart">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Biểu đồ tròn: Tỷ lệ trạng thái đơn -->
                <div class="col-12 col-md-4">
                    <div class="mb-2">
                        <span class="small fw-semibold text-secondary">
                            <i class="bi bi-pie-chart me-1"></i>Tỷ lệ Trạng thái Đơn hàng
                        </span>
                    </div>
                    <div class="report-chart">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <?php endif; ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const chartDates    = <?= $chartDates ?>;
    const chartRevenues = <?= $chartRevenues ?>;
    const chartCounts   = <?= $chartCounts ?>;
    const statusLabels  = <?= $chartStatusLabels ?>;
    const statusValues  = <?= $chartStatusValues ?>;

    // Biểu đồ Doanh thu & Số lượng đơn theo ngày
    const ctxRevenue = document.getElementById('revenueChart');
    if (ctxRevenue) {
        new Chart(ctxRevenue, {
            type: 'bar',
            data: {
                labels: chartDates.length ? chartDates : ['Chưa có ngày'],
                datasets: [
                    {
                        type: 'bar',
                        label: 'Doanh thu (₫)',
                        data: chartRevenues.length ? chartRevenues : [0],
                        backgroundColor: '#14b8a6',
                        borderRadius: 6,
                        yAxisID: 'y',
                        maxBarThickness: 38
                    },
                    {
                        type: 'line',
                        label: 'Số đơn hàng',
                        data: chartCounts.length ? chartCounts : [0],
                        borderColor: '#164e48',
                        backgroundColor: '#164e48',
                        borderWidth: 2,
                        tension: 0.2,
                        pointRadius: 4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { family: 'Inter', size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.yAxisID === 'y') {
                                    return context.dataset.label + ': ' + Number(context.raw).toLocaleString('vi-VN') + '₫';
                                }
                                return context.dataset.label + ': ' + context.raw + ' đơn';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 } }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { family: 'Inter', size: 11 },
                            callback: function(val) {
                                if (val >= 1000000) return (val / 1000000) + 'Tr';
                                if (val >= 1000) return (val / 1000) + 'k';
                                return val;
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            stepSize: 1,
                            font: { family: 'Inter', size: 11 }
                        }
                    }
                }
            }
        });
    }

    // Biểu đồ tròn Tỷ lệ trạng thái
    const ctxStatus = document.getElementById('statusChart');
    if (ctxStatus) {
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: [
                        '#f59e0b', // Chờ xử lý (Amber)
                        '#0284c7', // Đang giao (Sky)
                        '#10b981', // Đã giao (Emerald)
                        '#ef4444', // Đã hủy
                        '#8b5cf6'  // Đã hoàn trả
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            font: { family: 'Inter', size: 11 },
                            padding: 8
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const val = context.raw || 0;
                                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                return `${context.label}: ${val} đơn (${pct}%)`;
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }
})();
</script>
<script src="assets/app.js" defer></script>
</body>
</html>
