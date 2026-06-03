<?php
/**
 * 订单管理 - 列表页面（统一UI风格）
 */
$pageTitle = '订单管理';
require_once __DIR__ . '/includes/header.php';

// 搜索条件 - 使用原始GET避免htmlspecialchars转义%
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$customerId = intval($_GET['customer_id'] ?? 0);
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $where .= " AND (om.order_no LIKE ? OR c.customer_name LIKE ? OR od.product_model LIKE ?)";
    $likeKeyword = "%{$keyword}%";
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

if (!empty($status)) {
    $where .= " AND om.status = ?";
    $params[] = $status;
}

if ($customerId > 0) {
    $where .= " AND om.customer_id = ?";
    $params[] = $customerId;
}

if (!empty($dateFrom)) {
    $where .= " AND om.order_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where .= " AND om.order_date <= ?";
    $params[] = $dateTo;
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

// 获取订单列表（使用 order_total_amount 字段）
$orders = fetchAll(
    "SELECT om.*, c.customer_name,
            om.order_total_amount as total_amount,
            COUNT(od.id) as item_count
     FROM order_main om 
     LEFT JOIN customer c ON om.customer_id = c.id 
     LEFT JOIN order_detail od ON om.id = od.order_id 
     {$where} 
     GROUP BY om.id
     ORDER BY om.create_time DESC 
     LIMIT {$pagination['offset']}, {$pagination['pageSize']}",
    $params
);

// 获取订单明细（用于列表展示第一个产品）
$orderIds = array_column($orders, 'id');
$orderDetailsMap = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $details = fetchAll(
        "SELECT od.order_id, od.product_model, od.unit_price, od.quantity, od.unit, od.tax_included, od.color 
         FROM order_detail od
         WHERE od.order_id IN ({$placeholders})
         ORDER BY od.id ASC",
        $orderIds
    );
    foreach ($details as $d) {
        if (!isset($orderDetailsMap[$d['order_id']])) {
            $orderDetailsMap[$d['order_id']] = [];
        }
        $orderDetailsMap[$d['order_id']][] = $d;
    }
}

// 订单类型选项
$orderTypeOptions = [
    1 => '普通订单',
    2 => '现金订单'
];

// 构建分页URL
$urlParams = [];
if (!empty($keyword)) $urlParams[] = "keyword=" . urlencode($keyword);
if (!empty($status)) $urlParams[] = "status=" . urlencode($status);
if ($customerId > 0) $urlParams[] = "customer_id=" . $customerId;
if (!empty($dateFrom)) $urlParams[] = "date_from=" . $dateFrom;
if (!empty($dateTo)) $urlParams[] = "date_to=" . $dateTo;
$baseUrl = '/order.php' . (!empty($urlParams) ? '?' . implode('&', $urlParams) : '?');

// 订单状态选项
$statusOptions = [
    'pending' => '待生产',
    'producing' => '生产中',
    'shipped' => '已发货',
    'completed' => '已完成',
    'cancelled' => '已取消'
];
?>

<!-- 搜索栏 -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <?php if ($customerId > 0): ?>
            <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label text-muted small">关键词</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" name="keyword" placeholder="订单号/客户/产品" value="<?php echo htmlspecialchars_string($keyword); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">订单状态</label>
                <select class="form-select" name="status">
                    <option value="">全部状态</option>
                    <?php foreach ($statusOptions as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $status == $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">下单日期从</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">到</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>搜索</button>
            </div>
            <div class="col-md-2">
                <a href="/order.php<?php echo $customerId > 0 ? '?customer_id=' . $customerId : ''; ?>" class="btn btn-outline-secondary w-100">重置</a>
            </div>
        </form>
    </div>
</div>

<?php showFlashMessage(); ?>

<!-- 订单列表 - 统一待出货订单风格 -->
<style>
/* 产品明细样式 - 纵向对齐布局 */
.product-list {
    margin-top: 10px;
}
.product-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 13px;
    color: #666;
    margin-bottom: 8px;
    padding: 6px 0;
    border-bottom: 1px dashed #eee;
}
.product-item:last-child {
    margin-bottom: 0;
    border-bottom: none;
}
/* 第一列：产品型号 */
.product-item .model {
    color: #333;
    font-weight: 500;
    min-width: 100px;
    flex-shrink: 0;
}
/* 第二列：颜色 */
.product-item .color-section {
    min-width: 80px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.product-item .color-box {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 3px;
    border: 1px solid #ddd;
    flex-shrink: 0;
}
.product-item .color-text {
    color: #666;
    font-size: 12px;
}
/* 第三列：单价 */
.product-item .price {
    color: #1976d2;
    font-weight: 500;
    min-width: 80px;
    text-align: right;
}
/* 第四列：数量 */
.product-item .quantity-section {
    min-width: 60px;
    text-align: right;
}
.product-item .quantity-num {
    color: #333;
    font-weight: 500;
}
/* 第五列：单位 */
.product-item .unit-section {
    min-width: 100px;
    color: #666;
    font-size: 12px;
}
.product-item .unit-label {
    color: #999;
    font-size: 11px;
}
/* 第六列：含税标签 */
.product-item .tax-section {
    margin-left: auto;
    min-width: 60px;
    text-align: right;
}
.product-item .tax-tag {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 4px;
    display: inline-block;
}
.tax-tag.inclusive {
    background: #E6F9E9;
    color: #2e7d32;
}
.tax-tag.exclusive {
    background: #F1F1F1;
    color: #666;
}
</style>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-secondary"><i class="bi bi-cart3 me-2"></i>订单列表</h5>
        <div>
            <?php if (isAdmin()): ?>
            <a href="/order_form.php?action=add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新增订单</a>
            <?php endif; ?>
            <a href="/order_export.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-outline-secondary btn-sm ms-1"><i class="bi bi-download me-1"></i>导出</a>
        </div>
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
                        <th class="text-end">金额</th>
                        <th class="text-center">状态</th>
                        <th style="width: 150px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $index => $order): 
                        $isPending = isPendingShipment($order['ship_date'], $order['status']);
                        $detail = $orderDetailsMap[$order['id']] ?? null;
                        $paymentStatus = $order['payment_status'] ?? 1;
                    ?>
                    <?php 
                    // 计算是否临近出货（3天内）
                    $shipDate = strtotime($order['ship_date']);
                    $today = strtotime(date('Y-m-d'));
                    $daysDiff = ($shipDate - $today) / 86400;
                    $isUrgent = $daysDiff >= 0 && $daysDiff <= 3;
                    ?>
                    <tr>
                        <td><?php echo $pagination['offset'] + $index + 1; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-<?php echo $isPending ? 'danger' : 'primary'; ?> text-white rounded d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="bi bi-cart3"></i>
                                </div>
                                <div>
                                    <div class="fw-medium"><?php echo $order['order_no']; ?></div>
                                    <small class="text-muted"><?php echo $order['item_count']; ?> 个明细</small>
                                    <?php if ($isPending): ?>
                                    <span class="badge bg-danger ms-1"><i class="bi bi-clock me-1"></i>待出货</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($detail)): ?>
                            <div class="product-list">
                                <?php foreach ($detail as $item): ?>
                                <div class="product-item">
                                    <!-- 产品型号 -->
                                    <span class="model"><?php echo htmlspecialchars_string($item['product_model']); ?></span>
                                    
                                    <!-- 颜色 -->
                                    <?php if (!empty($item['color'])): ?>
                                    <div class="color-section">
                                        <span class="color-box" style="background:<?php echo htmlspecialchars_string($item['color']); ?>"></span>
                                        <span class="color-text"><?php echo htmlspecialchars_string($item['color']); ?></span>
                                    </div>
                                    <?php else: ?>
                                    <div class="color-section"></div>
                                    <?php endif; ?>
                                    
                                    <!-- 单价 -->
                                    <span class="price">¥<?php echo number_format($item['unit_price'], 2); ?></span>
                                    
                                    <!-- 数量 -->
                                    <div class="quantity-section">
                                        <span class="quantity-num">×<?php echo $item['quantity'] ?? 1; ?></span>
                                    </div>
                                    
                                    <!-- 单位 -->
                                    <div class="unit-section">
                                        <span class="unit-label">单位：</span><?php echo htmlspecialchars_string($item['unit'] ?? '个'); ?>
                                    </div>
                                    
                                    <!-- 含税标签 -->
                                    <div class="tax-section">
                                        <?php if ($item['tax_included']): ?>
                                        <span class="tax-tag inclusive">含税</span>
                                        <?php else: ?>
                                        <span class="tax-tag exclusive">不含税</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/customer_form.php?id=<?php echo $order['customer_id']; ?>&action=view" class="text-decoration-none">
                                <?php echo htmlspecialchars_string($order['customer_name']); ?>
                            </a>
                        </td>
                        <td>
                            <span class="fw-medium <?php echo $isUrgent ? 'text-danger' : ''; ?>"><?php echo formatDate($order['ship_date']); ?></span>
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
                            <?php if (!empty($order['origin'])): ?>
                            <span class="badge bg-info" style="font-size: 0.7rem;">
                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars_string($order['origin']); ?>
                            </span>
                            <?php endif; ?>
                            <?php 
                            $paymentStatusClass = [1 => 'bg-danger', 2 => 'bg-warning', 3 => 'bg-success'];
                            $paymentStatusText = [1 => '未收款', 2 => '部分收款', 3 => '已结清'];
                            ?>
                            <div class="mt-1">
                                <span class="badge <?php echo $paymentStatusClass[$paymentStatus]; ?>" style="font-size: 0.7rem;">
                                    <i class="bi bi-<?php echo $paymentStatus == 3 ? 'check-circle' : ($paymentStatus == 2 ? 'clock' : 'exclamation-circle'); ?>"></i>
                                    <?php echo $paymentStatusText[$paymentStatus]; ?>
                            </span>
                            </div>
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
                                <a href="/order_form.php?id=<?php echo $order['id']; ?>&action=copy" class="btn btn-sm btn-outline-info" title="复制">
                                    <i class="bi bi-files"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $order['id']; ?>, '<?php echo $order['order_no']; ?>')" title="删除">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-4 d-block mb-3 text-secondary"></i>
                            <p>暂无订单数据</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['totalPages'] > 1): ?>
    <div class="card-footer bg-white border-top-0">
        <?php echo paginationHtml($pagination, $baseUrl); ?>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmDelete(id, orderNo) {
    if (confirm('确定要删除订单 "' + orderNo + '" 吗？')) {
        window.location.href = '/order_form.php?id=' + id + '&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
