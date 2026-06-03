<?php
/**
 * 待出货订单 - 列表页面（统一UI风格）
 */
$pageTitle = '待出货订单';
require_once __DIR__ . '/includes/header.php';

$today = date('Y-m-d');

// 搜索条件 - 使用原始GET避免htmlspecialchars转义%
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$urgency = isset($_GET['urgency']) ? trim($_GET['urgency']) : '';

// 构建查询条件 - 待出货：出货日期在未来，且状态不是已完成(3)/已取消(4)
$where = "WHERE om.ship_date >= ? AND om.status NOT IN (2, 3)";
$params = [$today];

if (!empty($keyword)) {
    $where .= " AND (om.order_no LIKE ? OR c.customer_name LIKE ? OR od.product_model LIKE ?)";
    $likeKeyword = "%{$keyword}%";
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

// 紧急程度筛选
if ($urgency === 'urgent') {
    // 3天内（含今天）
    $where .= " AND om.ship_date <= DATE_ADD(?, INTERVAL 3 DAY)";
    $params[] = $today;
} elseif ($urgency === 'warning') {
    // 4-7天
    $where .= " AND om.ship_date > DATE_ADD(?, INTERVAL 3 DAY) AND om.ship_date <= DATE_ADD(?, INTERVAL 7 DAY)";
    $params[] = $today;
    $params[] = $today;
} elseif ($urgency === 'normal') {
    // 8-15天
    $where .= " AND om.ship_date > DATE_ADD(?, INTERVAL 7 DAY) AND om.ship_date <= DATE_ADD(?, INTERVAL 15 DAY)";
    $params[] = $today;
    $params[] = $today;
}

// 分页
$page = intval(input('page', 1, 'get'));
$pageSize = 10;

$total = fetchCount(
    "SELECT COUNT(DISTINCT om.id) FROM order_main om 
     LEFT JOIN customer c ON om.customer_id = c.id 
     LEFT JOIN order_detail od ON om.id = od.order_id 
     {$where}",
    $params
);
$pagination = pagination($total, $page, $pageSize);

// 获取待出货订单列表（使用 order_total_amount 字段）
$orders = fetchAll(
    "SELECT om.*, c.customer_name,
            om.order_total_amount as total_amount,
            COUNT(od.id) as item_count,
            DATEDIFF(om.ship_date, '{$today}') as days_left
     FROM order_main om 
     LEFT JOIN customer c ON om.customer_id = c.id 
     LEFT JOIN order_detail od ON om.id = od.order_id 
     {$where} 
     GROUP BY om.id
     ORDER BY om.ship_date ASC 
     LIMIT {$pagination['offset']}, {$pagination['pageSize']}",
    $params
);

// 构建分页URL
$urlParams = [];
if (!empty($keyword)) $urlParams[] = "keyword=" . urlencode($keyword);
if (!empty($urgency)) $urlParams[] = "urgency=" . urlencode($urgency);
$baseUrl = '/order_pending.php' . (!empty($urlParams) ? '?' . implode('&', $urlParams) : '?');

// 获取各等级数量
$urgentCount = fetchCount("SELECT COUNT(*) FROM order_main WHERE ship_date >= ? AND ship_date <= DATE_ADD(?, INTERVAL 3 DAY) AND status = 1", [$today, $today]);
$warningCount = fetchCount("SELECT COUNT(*) FROM order_main WHERE ship_date > DATE_ADD(?, INTERVAL 3 DAY) AND ship_date <= DATE_ADD(?, INTERVAL 7 DAY) AND status = 1", [$today, $today]);
$normalCount = fetchCount("SELECT COUNT(*) FROM order_main WHERE ship_date > DATE_ADD(?, INTERVAL 7 DAY) AND ship_date <= DATE_ADD(?, INTERVAL 15 DAY) AND status = 1", [$today, $today]);
?>

<!-- 预警统计卡片 -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card border-start border-danger border-4">
            <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="stat-info">
                <h4 class="text-danger"><?php echo $urgentCount; ?></h4>
                <p>3天内出货 (紧急)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card border-start border-warning border-4">
            <div class="stat-icon bg-warning-subtle text-warning">
                <i class="bi bi-clock-fill"></i>
            </div>
            <div class="stat-info">
                <h4 class="text-warning"><?php echo $warningCount; ?></h4>
                <p>4-7天出货 (提醒)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card border-start border-info border-4">
            <div class="stat-icon bg-info-subtle text-info">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h4 class="text-info"><?php echo $normalCount; ?></h4>
                <p>8-15天出货 (正常)</p>
            </div>
        </div>
    </div>
</div>

<!-- 搜索栏 -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" name="keyword" placeholder="订单号/客户/产品" value="<?php echo htmlspecialchars_string($keyword); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="urgency">
                    <option value="">全部紧急程度</option>
                    <option value="urgent" <?php echo $urgency == 'urgent' ? 'selected' : ''; ?>>🚨 3天内 (紧急)</option>
                    <option value="warning" <?php echo $urgency == 'warning' ? 'selected' : ''; ?>>⚠️ 4-7天 (提醒)</option>
                    <option value="normal" <?php echo $urgency == 'normal' ? 'selected' : ''; ?>>📅 8-15天 (正常)</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>搜索</button>
                <a href="/order_pending.php" class="btn btn-outline-secondary">重置</a>
            </div>
            <div class="col-md-2 text-md-end">
                <a href="/order.php" class="btn btn-outline-primary"><i class="bi bi-list me-1"></i>全部订单</a>
            </div>
        </form>
    </div>
</div>

<?php showFlashMessage(); ?>

<!-- 待出货订单列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-truck me-2"></i>待出货订单列表</h5>
        <span class="text-muted">共 <?php echo $total; ?> 条记录</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>订单信息</th>
                        <th>客户</th>
                        <th>出货日期</th>
                        <th class="text-center">剩余天数</th>
                        <th class="text-end">金额</th>
                        <th class="text-center">状态</th>
                        <th style="width: 150px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $index => $order): 
                        $daysLeft = $order['days_left'];
                        if ($daysLeft <= 3) {
                            $urgencyClass = 'table-danger';
                            $badgeClass = 'bg-danger';
                            $urgencyText = '紧急';
                        } elseif ($daysLeft <= 7) {
                            $urgencyClass = 'table-warning';
                            $badgeClass = 'bg-warning text-dark';
                            $urgencyText = '提醒';
                        } else {
                            $urgencyClass = '';
                            $badgeClass = 'bg-info';
                            $urgencyText = '正常';
                        }
                    ?>
                    <tr class="<?php echo $urgencyClass; ?>">
                        <td><?php echo $pagination['offset'] + $index + 1; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-<?php echo $daysLeft <= 3 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'info'); ?> text-white rounded d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="bi bi-truck"></i>
                                </div>
                                <div>
                                    <div class="fw-medium"><?php echo $order['order_no']; ?></div>
                                    <small class="text-muted"><?php echo $order['item_count']; ?> 个明细</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="/customer_form.php?id=<?php echo $order['customer_id']; ?>&action=view" class="text-decoration-none">
                                <?php echo htmlspecialchars_string($order['customer_name']); ?>
                            </a>
                        </td>
                        <td>
                            <span class="fw-medium"><?php echo formatDate($order['ship_date']); ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge <?php echo $badgeClass; ?> fs-6">
                                <?php echo $daysLeft; ?> 天
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="fw-bold text-primary fs-5">¥<?php echo formatMoney($order['total_amount']); ?></div>
                        </td>
                        <td class="text-center">
                            <div class="mb-1">
                                <span class="badge <?php echo getOrderStatusClass($order['status']); ?> fs-6">
                                    <?php echo getOrderStatusText($order['status']); ?>
                                </span>
                            </div>
                            <?php 
                            $paymentStatusClass = [1 => 'bg-danger', 2 => 'bg-warning', 3 => 'bg-success'];
                            $paymentStatusText = [1 => '未收款', 2 => '部分收款', 3 => '已结清'];
                            $paymentStatus = $order['payment_status'] ?? 1;
                            ?>
                            <span class="badge <?php echo $paymentStatusClass[$paymentStatus]; ?>" style="font-size: 0.7rem;">
                                <i class="bi bi-<?php echo $paymentStatus == 3 ? 'check-circle' : ($paymentStatus == 2 ? 'clock' : 'exclamation-circle'); ?>"></i>
                                <?php echo $paymentStatusText[$paymentStatus]; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="/order_form.php?id=<?php echo $order['id']; ?>&action=view" class="btn btn-sm btn-outline-primary" title="查看">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                <a href="/order_form.php?id=<?php echo $order['id']; ?>&action=edit" class="btn btn-sm btn-outline-success" title="编辑">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="/order_form.php?id=<?php echo $order['id']; ?>&action=edit&quick_ship=1" class="btn btn-sm btn-outline-info" title="快速发货">
                                    <i class="bi bi-check-circle"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-check-circle display-4 d-block mb-3 text-success"></i>
                            <p>暂无待出货订单</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['totalPages'] > 1): ?>
    <div class="card-footer bg-white">
        <?php echo paginationHtml($pagination, $baseUrl); ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
