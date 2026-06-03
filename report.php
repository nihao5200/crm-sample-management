<?php
/**
 * 数据报表统计页面
 */
$pageTitle = '数据报表';
require_once __DIR__ . '/includes/header.php';

// 时间范围 - 使用原始GET避免htmlspecialchars转义
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

// ==================== 汇总数据（先计算）====================
// 订单数量
$totalOrderCount = fetchCount(
    "SELECT COUNT(*) FROM order_main WHERE order_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

// 订单总额 - 直接使用 order_total_amount 字段
$totalOrderAmount = fetchCount(
    "SELECT COALESCE(SUM(order_total_amount), 0) FROM order_main WHERE order_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

// 成交客户数
$totalCustomerCount = fetchCount(
    "SELECT COUNT(DISTINCT customer_id) FROM order_main WHERE order_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

// 平均客单价
$avgOrderAmount = $totalOrderCount > 0 ? round($totalOrderAmount / $totalOrderCount, 2) : 0;

// ==================== 客户TOP10 ====================
$customerTop10 = fetchAll(
    "SELECT c.id, c.customer_name, 
            COUNT(DISTINCT om.id) as order_count,
            COALESCE(SUM(om.order_total_amount), 0) as total_amount
     FROM customer c
     INNER JOIN order_main om ON c.id = om.customer_id
     WHERE om.order_date BETWEEN ? AND ?
     GROUP BY c.id, c.customer_name
     HAVING total_amount > 0
     ORDER BY total_amount DESC
     LIMIT 10",
    [$startDate, $endDate]
);

// ==================== 产品TOP10 ====================
$productTop10 = fetchAll(
    "SELECT od.product_model,
            SUM(COALESCE(od.quantity, 0)) as total_qty,
            SUM(COALESCE(od.amount, 0)) as total_amount
     FROM order_detail od
     INNER JOIN order_main om ON od.order_id = om.id
     WHERE om.order_date BETWEEN ? AND ?
     GROUP BY od.product_model
     ORDER BY total_qty DESC
     LIMIT 10",
    [$startDate, $endDate]
);

// ==================== 日统计明细 ====================
$dailyStats = fetchAll(
    "SELECT order_date,
            COUNT(*) as order_count,
            COALESCE(SUM(order_total_amount), 0) as total_amount
     FROM order_main
     WHERE order_date BETWEEN ? AND ?
     GROUP BY order_date
     ORDER BY order_date DESC",
    [$startDate, $endDate]
);
?>

<!-- 报表筛选 -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">开始日期</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">结束日期</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>查询</button>
                <a href="/report.php" class="btn btn-outline-secondary">重置</a>
            </div>
            <div class="col-md-3 text-md-end">
                <button type="button" class="btn btn-success" onclick="exportReport()">
                    <i class="bi bi-file-excel me-1"></i>导出报表
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 汇总卡片 -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-cart3"></i>
            </div>
            <div class="stat-info">
                <h4><?php echo $totalOrderCount; ?></h4>
                <p>订单数量</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-currency-yen"></i>
            </div>
            <div class="stat-info">
                <h4>¥<?php echo formatMoney($totalOrderAmount); ?></h4>
                <p>订单总额</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-info">
                <h4><?php echo $totalCustomerCount; ?></h4>
                <p>成交客户</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-calculator"></i>
            </div>
            <div class="stat-info">
                <h4>¥<?php echo formatMoney($avgOrderAmount); ?></h4>
                <p>平均客单价</p>
            </div>
        </div>
    </div>
</div>

<!-- 图表区域 -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-trophy"></i> 客户订单金额TOP10</h5>
            </div>
            <div class="card-body">
                <canvas id="customerChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-box-seam"></i> 产品销量TOP10</h5>
            </div>
            <div class="card-body">
                <canvas id="productChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- 数据表格 -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-people"></i> 客户订单排行</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>客户名称</th>
                                <th>订单数</th>
                                <th class="text-end">金额</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerTop10 as $index => $item): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $rankColors = ['warning', 'secondary', 'danger', 'light text-dark', 'light text-dark', 'light text-dark', 'light text-dark', 'light text-dark', 'light text-dark', 'light text-dark'];
                                    ?>
                                    <span class="badge bg-<?php echo $rankColors[$index] ?? 'light text-dark'; ?>"><?php echo $index + 1; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars_string($item['customer_name']); ?></td>
                                <td><?php echo $item['order_count']; ?></td>
                                <td class="text-end fw-bold text-primary">¥<?php echo formatMoney($item['total_amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($customerTop10)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">暂无数据</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-box"></i> 产品销量排行</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>产品型号</th>
                                <th>销量</th>
                                <th class="text-end">金额</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productTop10 as $index => $item): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $rankColors = ['warning', 'secondary', 'danger', 'light text-dark', 'light text-dark', 'light text-dark', 'light text-dark', 'light text-dark', 'light text-dark', 'light text-dark'];
                                    ?>
                                    <span class="badge bg-<?php echo $rankColors[$index] ?? 'light text-dark'; ?>"><?php echo $index + 1; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars_string($item['product_model']); ?></td>
                                <td><?php echo $item['total_qty']; ?></td>
                                <td class="text-end fw-bold text-primary">¥<?php echo formatMoney($item['total_amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($productTop10)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">暂无数据</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 日统计明细 -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-calendar3"></i> 日统计明细</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>日期</th>
                        <th>订单数</th>
                        <th class="text-end">订单金额</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyStats as $item): ?>
                    <tr>
                        <td><?php echo $item['order_date']; ?></td>
                        <td><?php echo $item['order_count']; ?></td>
                        <td class="text-end fw-bold text-primary">¥<?php echo formatMoney($item['total_amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dailyStats)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">暂无数据</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 客户TOP10图表
var customerCtx = document.getElementById('customerChart').getContext('2d');
var customerChart = new Chart(customerCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($customerTop10, 'customer_name')); ?>,
        datasets: [{
            label: '订单金额',
            data: <?php echo json_encode(array_column($customerTop10, 'total_amount')); ?>,
            backgroundColor: 'rgba(78, 115, 223, 0.8)',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '¥' + value.toLocaleString();
                    }
                }
            },
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        }
    }
});

// 产品TOP10图表
var productCtx = document.getElementById('productChart').getContext('2d');
var productChart = new Chart(productCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($productTop10, 'product_model')); ?>,
        datasets: [{
            label: '销量',
            data: <?php echo json_encode(array_column($productTop10, 'total_qty')); ?>,
            backgroundColor: 'rgba(28, 200, 138, 0.8)',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true
            },
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        }
    }
});

// 导出报表
function exportReport() {
    window.location.href = '/report_export.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
