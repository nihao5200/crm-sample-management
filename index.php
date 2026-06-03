<?php
/**
 * 后台首页 - 数据统计仪表盘（优化版）
 */
$pageTitle = '数据概览';
require_once __DIR__ . '/includes/header.php';

// ==================== 时间范围选择 ====================
$timeRange = input('range', 'month', 'get'); // day/week/month/year
$startDate = '';
$endDate = '';

switch ($timeRange) {
    case 'day':
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $endDate = date('Y-m-d');
        break;
    case 'year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        break;
    case 'month':
    default:
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t'); // 本月最后一天
        $timeRange = 'month';
        break;
}

// 上一个同期时间段（用于同比计算）
$prevStartDate = '';
$prevEndDate = '';
$daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
$prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
$prevStartDate = date('Y-m-d', strtotime($prevEndDate . ' -' . $daysDiff . ' days'));

// ==================== 基础统计数据（当前期）====================
$customerCount = fetchCount("SELECT COUNT(*) FROM customer WHERE create_time <= ?", [$endDate . ' 23:59:59']);
$orderCount = fetchCount("SELECT COUNT(*) FROM order_main WHERE order_date <= ?", [$endDate]);
$sampleCount = fetchCount("SELECT COUNT(*) FROM sample_record WHERE send_date <= ?", [$endDate]);

// ==================== 本期订单统计（使用 order_total_amount）====================
$orderTotalAmount = fetchCount(
    "SELECT COALESCE(SUM(order_total_amount), 0) 
     FROM order_main 
     WHERE order_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

$monthOrderCount = fetchCount(
    "SELECT COUNT(*) FROM order_main WHERE order_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

// ==================== 本期收款统计（仅统计收款记录表）====================
$paymentTotalAmount = fetchCount(
    "SELECT COALESCE(SUM(amount), 0) FROM payment_record WHERE payment_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

$paymentCount = fetchCount(
    "SELECT COUNT(*) FROM payment_record WHERE payment_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

// ==================== 本期未收金额计算 ====================
// 本期所有订单的订单总金额 - 本期内该订单关联的收款金额总和
$unpaidAmount = fetchCount(
    "SELECT COALESCE(SUM(om.order_total_amount - IFNULL(paid.paid_amount, 0)), 0) as unpaid
     FROM order_main om
     LEFT JOIN (
         SELECT opl.order_id, SUM(opl.link_amount) as paid_amount
         FROM order_payment_link opl
         JOIN payment_record pr ON opl.payment_id = pr.id
         WHERE pr.payment_date BETWEEN ? AND ?
         GROUP BY opl.order_id
     ) paid ON om.id = paid.order_id
     WHERE om.order_date BETWEEN ? AND ?",
    [$startDate, $endDate, $startDate, $endDate]
);

// ==================== 上期收款统计（同比）====================
$prevPaymentAmount = fetchCount(
    "SELECT COALESCE(SUM(amount), 0) FROM payment_record WHERE payment_date BETWEEN ? AND ?",
    [$prevStartDate, $prevEndDate]
);

$prevPaymentCount = fetchCount(
    "SELECT COUNT(*) FROM payment_record WHERE payment_date BETWEEN ? AND ?",
    [$prevStartDate, $prevEndDate]
);

// 收款同比
$paymentAmountGrowth = $prevPaymentAmount > 0 ? round(($paymentTotalAmount - $prevPaymentAmount) / $prevPaymentAmount * 100, 1) : 0;
$paymentCountGrowth = $prevPaymentCount > 0 ? round(($paymentCount - $prevPaymentCount) / $prevPaymentCount * 100, 1) : 0;

// ==================== 上期订单统计（同比，使用 order_total_amount）====================
$prevOrderAmount = fetchCount(
    "SELECT COALESCE(SUM(order_total_amount), 0) 
     FROM order_main 
     WHERE order_date BETWEEN ? AND ?",
    [$prevStartDate, $prevEndDate]
);

$prevOrderCount = fetchCount(
    "SELECT COUNT(*) FROM order_main WHERE order_date BETWEEN ? AND ?",
    [$prevStartDate, $prevEndDate]
);

// 计算同比
$amountGrowth = $prevOrderAmount > 0 ? round(($orderTotalAmount - $prevOrderAmount) / $prevOrderAmount * 100, 1) : 0;
$countGrowth = $prevOrderCount > 0 ? round(($monthOrderCount - $prevOrderCount) / $prevOrderCount * 100, 1) : 0;

// ==================== 各状态订单数量 ====================
$pendingOrderCount = fetchCount("SELECT COUNT(*) FROM order_main WHERE status = 1");
$shippedOrderCount = fetchCount("SELECT COUNT(*) FROM order_main WHERE status = 2");
$completedOrderCount = fetchCount("SELECT COUNT(*) FROM order_main WHERE status = 3");

// ==================== 分级预警订单 ====================
$today = date('Y-m-d');
// 3天内出货（紧急）- 包含今天
$urgent3Days = fetchCount(
    "SELECT COUNT(*) FROM order_main 
     WHERE ship_date >= ? AND ship_date <= DATE_ADD(?, INTERVAL 3 DAY) 
     AND status NOT IN (2, 3)",
    [$today, $today]
);
// 7天内出货（警告）
$warning7Days = fetchCount(
    "SELECT COUNT(*) FROM order_main 
     WHERE ship_date > DATE_ADD(?, INTERVAL 3 DAY) AND ship_date <= DATE_ADD(?, INTERVAL 7 DAY) 
     AND status NOT IN (2, 3)",
    [$today, $today]
);
// 15天内出货（提醒）
$notice15Days = fetchCount(
    "SELECT COUNT(*) FROM order_main 
     WHERE ship_date > DATE_ADD(?, INTERVAL 7 DAY) AND ship_date <= DATE_ADD(?, INTERVAL 15 DAY) 
     AND status NOT IN (2, 3)",
    [$today, $today]
);

$totalPendingShipment = $urgent3Days + $warning7Days + $notice15Days;

// ==================== 客户未下单提醒 ====================
// 获取系统设置中的提醒天数（默认30天）
$noOrderDays = 30; // 可以从数据库设置表中读取

// 查询超过N天未下单的客户
$noOrderCustomers = fetchAll(
    "SELECT c.id, c.customer_name, c.contact, c.phone, 
            MAX(om.order_date) as last_order_date,
            DATEDIFF(?, MAX(om.order_date)) as days_since_last_order
     FROM customer c
     LEFT JOIN order_main om ON c.id = om.customer_id
     GROUP BY c.id
     HAVING (last_order_date IS NULL OR DATEDIFF(?, last_order_date) >= ?)
     ORDER BY last_order_date ASC
     LIMIT 10",
    [$today, $today, $noOrderDays]
);

// 分类统计
$noOrderNever = 0; // 从未下单
$noOrder30Days = 0; // 30-60天
$noOrder60Days = 0; // 60-90天
$noOrder90Days = 0; // 超过90天

foreach ($noOrderCustomers as $c) {
    if (empty($c['last_order_date'])) {
        $noOrderNever++;
    } elseif ($c['days_since_last_order'] >= 90) {
        $noOrder90Days++;
    } elseif ($c['days_since_last_order'] >= 60) {
        $noOrder60Days++;
    } else {
        $noOrder30Days++;
    }
}

// ==================== 样品统计 ====================
$pendingSampleCount = fetchCount("SELECT COUNT(*) FROM sample_record WHERE sample_status = 1");
$confirmedSampleCount = fetchCount("SELECT COUNT(*) FROM sample_record WHERE sample_status = 2");
$returnedSampleCount = fetchCount("SELECT COUNT(*) FROM sample_record WHERE sample_status = 3");
$massProductionCount = fetchCount("SELECT COUNT(*) FROM sample_record WHERE sample_status = 4");

// 样品转化率
$conversionRate = $sampleCount > 0 ? round(($confirmedSampleCount + $massProductionCount) / $sampleCount * 100, 1) : 0;

// ==================== 近6个月趋势数据 ====================
$trendMonths = [];
$trendAmounts = [];
$trendCustomers = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $trendMonths[] = date('m月', strtotime("-$i months"));
    
    // 使用 order_total_amount 字段
    $amount = fetchCount(
        "SELECT COALESCE(SUM(order_total_amount), 0) 
         FROM order_main 
         WHERE DATE_FORMAT(order_date, '%Y-%m') = ?",
        [$month]
    );
    $trendAmounts[] = $amount;
    
    $custCount = fetchCount(
        "SELECT COUNT(*) FROM customer WHERE DATE_FORMAT(create_time, '%Y-%m') = ?",
        [$month]
    );
    $trendCustomers[] = $custCount;
}

// ==================== 最近数据 ====================
$recentOrders = fetchAll(
    "SELECT om.*, c.customer_name, om.order_total_amount as total_amount
     FROM order_main om 
     LEFT JOIN customer c ON om.customer_id = c.id 
     ORDER BY om.create_time DESC LIMIT 5"
);

$recentSamples = fetchAll(
    "SELECT sr.*, c.customer_name 
     FROM sample_record sr 
     LEFT JOIN customer c ON sr.customer_id = c.id 
     ORDER BY sr.create_time DESC LIMIT 5"
);

$recentCustomers = fetchAll(
    "SELECT * FROM customer ORDER BY create_time DESC LIMIT 5"
);
?>

<!-- 时间范围选择器 -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="btn-group" role="group">
                <a href="?range=day" class="btn btn-outline-primary <?php echo $timeRange == 'day' ? 'active' : ''; ?>">今日</a>
                <a href="?range=week" class="btn btn-outline-primary <?php echo $timeRange == 'week' ? 'active' : ''; ?>">本周</a>
                <a href="?range=month" class="btn btn-outline-primary <?php echo $timeRange == 'month' ? 'active' : ''; ?>">本月</a>
                <a href="?range=year" class="btn btn-outline-primary <?php echo $timeRange == 'year' ? 'active' : ''; ?>">本年</a>
            </div>
            <div class="text-muted">
                <i class="bi bi-calendar3"></i> 
                <?php echo $startDate; ?> 至 <?php echo $endDate; ?>
                <span class="ms-2">|</span>
                <span class="ms-2">同比上期：<?php echo $prevStartDate; ?> 至 <?php echo $prevEndDate; ?></span>
            </div>
        </div>
    </div>
</div>

<?php showFlashMessage(); ?>

<!-- 第一行：核心指标 -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-info">
                <h4><?php echo $customerCount; ?></h4>
                <p>累计客户</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-cart3"></i>
            </div>
            <div class="stat-info">
                <h4><?php echo $monthOrderCount; ?></h4>
                <p>本期订单</p>
                <?php if ($countGrowth != 0): ?>
                <small class="<?php echo $countGrowth > 0 ? 'text-success' : 'text-danger'; ?>">
                    <i class="bi bi-arrow-<?php echo $countGrowth > 0 ? 'up' : 'down'; ?>"></i> 
                    同比 <?php echo abs($countGrowth); ?>%
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-currency-yen"></i>
            </div>
            <div class="stat-info">
                <h4>¥<?php echo formatMoney($orderTotalAmount); ?></h4>
                <p>本期金额</p>
                <?php if ($amountGrowth != 0): ?>
                <small class="<?php echo $amountGrowth > 0 ? 'text-success' : 'text-danger'; ?>">
                    <i class="bi bi-arrow-<?php echo $amountGrowth > 0 ? 'up' : 'down'; ?>"></i> 
                    同比 <?php echo abs($amountGrowth); ?>%
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-box"></i>
            </div>
            <div class="stat-info">
                <h4><?php echo $sampleCount; ?></h4>
                <p>累计样品</p>
                <small class="text-primary">转化率 <?php echo $conversionRate; ?>%</small>
            </div>
        </div>
    </div>
</div>

<!-- 第二行：收款统计 -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card bg-success text-white h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">本期已收金额</h6>
                        <h3 class="mb-0">¥<?php echo formatMoney($paymentTotalAmount); ?></h3>
                        <?php if ($paymentAmountGrowth != 0): ?>
                        <small class="<?php echo $paymentAmountGrowth > 0 ? 'text-white' : 'text-warning'; ?>">
                            <i class="bi bi-arrow-<?php echo $paymentAmountGrowth > 0 ? 'up' : 'down'; ?>"></i> 
                            同比 <?php echo abs($paymentAmountGrowth); ?>%
                        </small>
                        <?php else: ?>
                        <small>&nbsp;</small>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-cash-stack display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-danger text-white h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">本期未收金额</h6>
                        <h3 class="mb-0">¥<?php echo formatMoney($unpaidAmount); ?></h3>
                        <small><?php echo $paymentCount; ?> 笔收款记录</small>
                    </div>
                    <i class="bi bi-cash-coin display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 第二行：订单状态分布 + 出货预警（统一对称布局） -->
<style>
.stats-card-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    height: 100%;
}
.stats-card-grid.three-col {
    grid-template-columns: repeat(3, 1fr);
}
.stats-card-grid > a,
.stats-card-grid > div {
    display: block;
    height: 100%;
}
.stats-card-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem 0.5rem;
    border-radius: 0.5rem;
    text-align: center;
    height: 100%;
    min-height: 90px;
    transition: transform 0.2s;
}
.stats-card-item:hover {
    transform: translateY(-2px);
}
.stats-card-item .number {
    font-size: 1.75rem;
    font-weight: 600;
    line-height: 1;
    margin-bottom: 0.375rem;
}
.stats-card-item .label {
    font-size: 0.8125rem;
    color: #6c757d;
    margin-bottom: 0.375rem;
}
.stats-card-item .icon {
    font-size: 1.25rem;
}
.section-card {
    height: 100%;
    display: flex;
    flex-direction: column;
}
.section-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(0,0,0,0.125);
}
.section-card .card-header h5 {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 600;
}
.section-card .card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 1rem;
}
</style>

<div class="row g-4 mb-4">
    <!-- 订单状态分布 -->
    <div class="col-lg-6">
        <div class="card section-card">
            <div class="card-header">
                <h5><i class="bi bi-pie-chart me-2"></i>订单状态分布</h5>
                <a href="/order.php" class="btn btn-sm btn-outline-primary">查看全部</a>
            </div>
            <div class="card-body">
                <div class="stats-card-grid">
                    <div class="stats-card-item" style="background: #fff3cd;">
                        <div class="number text-warning"><?php echo $pendingOrderCount; ?></div>
                        <div class="label">待生产</div>
                        <div class="icon"><i class="bi bi-hourglass-split text-warning"></i></div>
                    </div>
                    <div class="stats-card-item" style="background: #d1ecf1;">
                        <div class="number text-info"><?php echo $shippedOrderCount; ?></div>
                        <div class="label">已发货</div>
                        <div class="icon"><i class="bi bi-truck text-info"></i></div>
                    </div>
                    <div class="stats-card-item" style="background: #d4edda;">
                        <div class="number text-success"><?php echo $completedOrderCount; ?></div>
                        <div class="label">已完成</div>
                        <div class="icon"><i class="bi bi-check-circle-fill text-success"></i></div>
                    </div>
                    <a href="/order_pending.php" class="text-decoration-none" style="display: block; height: 100%;">
                        <div class="stats-card-item" style="background: #f8d7da;">
                            <div class="number text-danger"><?php echo $totalPendingShipment; ?></div>
                            <div class="label">待出货</div>
                            <div class="icon"><i class="bi bi-box-seam text-danger"></i></div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 出货预警 -->
    <div class="col-lg-6">
        <div class="card section-card">
            <div class="card-header">
                <h5><i class="bi bi-bell me-2"></i>出货预警</h5>
                <a href="/order_pending.php" class="btn btn-sm btn-outline-danger">查看全部</a>
            </div>
            <div class="card-body">
                <div class="stats-card-grid three-col">
                    <a href="/order_pending.php?level=urgent" class="text-decoration-none" style="display: block; height: 100%;">
                        <div class="stats-card-item" style="background: #fee2e2;">
                            <div class="number text-danger"><?php echo $urgent3Days; ?></div>
                            <div class="label">3天内出货</div>
                            <div class="icon"><i class="bi bi-exclamation-triangle-fill text-danger"></i></div>
                        </div>
                    </a>
                    <a href="/order_pending.php?level=warning" class="text-decoration-none" style="display: block; height: 100%;">
                        <div class="stats-card-item" style="background: #fef3c7;">
                            <div class="number text-warning"><?php echo $warning7Days; ?></div>
                            <div class="label">7天内出货</div>
                            <div class="icon"><i class="bi bi-clock-fill text-warning"></i></div>
                        </div>
                    </a>
                    <a href="/order_pending.php?level=notice" class="text-decoration-none" style="display: block; height: 100%;">
                        <div class="stats-card-item" style="background: #dbeafe;">
                            <div class="number text-primary"><?php echo $notice15Days; ?></div>
                            <div class="label">15天内出货</div>
                            <div class="icon"><i class="bi bi-info-circle-fill text-primary"></i></div>
                        </div>
                    </a>
                </div>
                <?php if ($totalPendingShipment == 0): ?>
                <div class="alert alert-success alert-permanent mt-3 mb-0 text-center">
                    <i class="bi bi-check-circle-fill"></i> 
                    <strong>订单状态：</strong>近期没有待出货订单，一切正常
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 第三行：客户未下单提醒 -->
<div class="row g-4 mb-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-x"></i> 客户未下单提醒</h5>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted">提醒设置：超过 <?php echo $noOrderDays; ?> 天未下单</small>
                    <a href="/customer_no_order.php" class="btn btn-sm btn-outline-primary">查看全部</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-stretch mb-4">
                    <!-- 从未下单 -->
                    <div class="col-md-3">
                        <a href="/customer_no_order.php?type=never" class="text-decoration-none h-100">
                            <div class="d-flex align-items-center p-3 rounded h-100" style="background: #f3f4f6; border-left: 4px solid #6b7280;">
                                <div class="flex-grow-1">
                                    <h4 class="text-secondary mb-1"><?php echo $noOrderNever; ?></h4>
                                    <small class="text-muted">从未下单</small>
                                </div>
                                <i class="bi bi-person-x-fill text-secondary fs-3"></i>
                            </div>
                        </a>
                    </div>
                    <!-- 30-60天 -->
                    <div class="col-md-3">
                        <a href="/customer_no_order.php?type=30" class="text-decoration-none h-100">
                            <div class="d-flex align-items-center p-3 rounded h-100" style="background: #dbeafe; border-left: 4px solid #3b82f6;">
                                <div class="flex-grow-1">
                                    <h4 class="text-primary mb-1"><?php echo $noOrder30Days; ?></h4>
                                    <small class="text-muted">30-60天未下单</small>
                                </div>
                                <i class="bi bi-calendar-x text-primary fs-3"></i>
                            </div>
                        </a>
                    </div>
                    <!-- 60-90天 -->
                    <div class="col-md-3">
                        <a href="/customer_no_order.php?type=60" class="text-decoration-none h-100">
                            <div class="d-flex align-items-center p-3 rounded h-100" style="background: #fef3c7; border-left: 4px solid #f59e0b;">
                                <div class="flex-grow-1">
                                    <h4 class="text-warning mb-1"><?php echo $noOrder60Days; ?></h4>
                                    <small class="text-muted">60-90天未下单</small>
                                </div>
                                <i class="bi bi-calendar2-x text-warning fs-3"></i>
                            </div>
                        </a>
                    </div>
                    <!-- 超过90天 -->
                    <div class="col-md-3">
                        <a href="/customer_no_order.php?type=90" class="text-decoration-none h-100">
                            <div class="d-flex align-items-center p-3 rounded h-100" style="background: #fee2e2; border-left: 4px solid #dc2626;">
                                <div class="flex-grow-1">
                                    <h4 class="text-danger mb-1"><?php echo $noOrder90Days; ?></h4>
                                    <small class="text-muted">超过90天未下单</small>
                                </div>
                                <i class="bi bi-calendar3-x text-danger fs-3"></i>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- 最近未下单客户列表 -->
                <?php if (!empty($noOrderCustomers)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>客户名称</th>
                                <th>联系人</th>
                                <th>联系电话</th>
                                <th>最后下单日期</th>
                                <th>未下单天数</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($noOrderCustomers, 0, 5) as $c): ?>
                            <tr>
                                <td>
                                    <a href="/customer_form.php?id=<?php echo $c['id']; ?>&action=view" class="text-decoration-none fw-medium">
                                        <?php echo htmlspecialchars_string($c['customer_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars_string($c['contact']); ?></td>
                                <td><?php echo htmlspecialchars_string($c['phone']); ?></td>
                                <td>
                                    <?php if ($c['last_order_date']): ?>
                                        <span class="text-muted"><?php echo $c['last_order_date']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">从未下单</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['days_since_last_order']): ?>
                                        <span class="badge <?php echo $c['days_since_last_order'] >= 90 ? 'bg-danger' : ($c['days_since_last_order'] >= 60 ? 'bg-warning' : 'bg-primary'); ?>">
                                            <?php echo $c['days_since_last_order']; ?> 天
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/order_form.php?action=add&customer_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-plus-lg me-1"></i>新建订单
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>好消息：</strong>所有客户近期都有下单记录
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 第四行：趋势图表 -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="bi bi-graph-up"></i> 近6个月订单金额趋势</h5>
            </div>
            <div class="card-body" style="height: 250px;">
                <canvas id="amountTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="bi bi-graph-up"></i> 客户新增趋势</h5>
            </div>
            <div class="card-body" style="height: 250px;">
                <canvas id="customerTrendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- 第四行：最近数据 -->
<div class="row">
<!-- 最近订单 -->
<div class="col-lg-4 mb-4">
    <div class="card h-100">
        <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-cart3 me-2"></i>最近订单</h5>
                <a href="/order.php" class="btn btn-light btn-sm">查看全部</a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($recentOrders)): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($recentOrders as $index => $order): ?>
                <a href="/order_form.php?id=<?php echo $order['id']; ?>&action=view" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?php echo $index % 2 == 0 ? 'bg-light' : ''; ?>">
                    <div>
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge bg-primary me-2"><?php echo $order['order_no']; ?></span>
                        </div>
                        <small class="text-muted"><i class="bi bi-building me-1"></i><?php echo htmlspecialchars_string($order['customer_name']); ?></small>
                        <div class="small text-muted mt-1"><i class="bi bi-calendar3 me-1"></i><?php echo formatDate($order['order_date']); ?></div>
                    </div>
                    <div class="text-end">
                        <span class="badge <?php echo getOrderStatusClass($order['status']); ?> mb-1"><?php echo getOrderStatusText($order['status']); ?></span>
                        <div class="small text-muted">¥<?php echo formatMoney($order['total_amount'] ?? 0); ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="mt-2 text-muted">暂无订单数据</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    
    <!-- 最近样品 -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-box me-2"></i>最近样品</h5>
                    <a href="/sample.php" class="btn btn-light btn-sm">查看全部</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentSamples)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentSamples as $index => $sample): ?>
                    <a href="/sample_form.php?id=<?php echo $sample['id']; ?>&action=view" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?php echo $index % 2 == 0 ? 'bg-light' : ''; ?>">
                        <div>
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-success me-2"><?php echo $sample['sample_no']; ?></span>
                            </div>
                            <small class="text-muted"><i class="bi bi-building me-1"></i><?php echo htmlspecialchars_string($sample['customer_name']); ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge <?php echo getSampleStatusClass($sample['sample_status']); ?> mb-1"><?php echo getSampleStatusText($sample['sample_status']); ?></span>
                            <div class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?php echo formatDate($sample['send_date']); ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted"></i>
                    <p class="mt-2 text-muted">暂无样品数据</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 最近客户 -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>最近客户</h5>
                    <a href="/customer.php" class="btn btn-light btn-sm">查看全部</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentCustomers)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentCustomers as $index => $customer): ?>
                    <a href="/customer_form.php?id=<?php echo $customer['id']; ?>&action=view" class="list-group-item list-group-item-action d-flex align-items-center py-3 <?php echo $index % 2 == 0 ? 'bg-light' : ''; ?>">
                        <div class="flex-shrink-0">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; font-size: 18px;">
                                <?php echo mb_substr($customer['customer_name'], 0, 1); ?>
                            </div>
                        </div>
                        <div class="ms-3 flex-grow-1">
                            <div class="fw-medium"><?php echo htmlspecialchars_string($customer['customer_name']); ?></div>
                            <small class="text-muted">
                                <i class="bi bi-person me-1"></i><?php echo htmlspecialchars_string($customer['contact']); ?> | 
                                <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars_string($customer['phone']); ?>
                            </small>
                        </div>
                        <div class="text-muted small">
                            <?php echo formatDate($customer['create_time']); ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted"></i>
                    <p class="mt-2 text-muted">暂无客户数据</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 引入 Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 订单金额趋势图
var amountCtx = document.getElementById('amountTrendChart').getContext('2d');
var amountChart = new Chart(amountCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($trendMonths); ?>,
        datasets: [{
            label: '订单金额',
            data: <?php echo json_encode($trendAmounts); ?>,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '¥' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// 客户新增趋势图
var customerCtx = document.getElementById('customerTrendChart').getContext('2d');
var customerChart = new Chart(customerCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($trendMonths); ?>,
        datasets: [{
            label: '新增客户',
            data: <?php echo json_encode($trendCustomers); ?>,
            backgroundColor: '#1cc88a',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
