<?php
/**
 * 收款记录管理 - 列表页面
 */
$pageTitle = '收款记录';
require_once __DIR__ . '/includes/header.php';

// 搜索条件
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$customerId = intval($_GET['customer_id'] ?? 0);
$paymentType = intval($_GET['payment_type'] ?? 0);
$isCashOrder = isset($_GET['is_cash_order']) ? intval($_GET['is_cash_order']) : -1;
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// 收款方式选项
$paymentTypes = [
    1 => '现金',
    2 => '银行转账',
    3 => '微信',
    4 => '支付宝'
];

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $where .= " AND (c.customer_name LIKE ? OR pr.remark LIKE ?)";
    $likeKeyword = "%{$keyword}%";
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

if ($customerId > 0) {
    $where .= " AND pr.customer_id = ?";
    $params[] = $customerId;
}

if ($paymentType > 0) {
    $where .= " AND pr.payment_type = ?";
    $params[] = $paymentType;
}

if ($isCashOrder >= 0) {
    $where .= " AND pr.is_cash_order = ?";
    $params[] = $isCashOrder;
}

if (!empty($dateFrom)) {
    $where .= " AND pr.payment_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where .= " AND pr.payment_date <= ?";
    $params[] = $dateTo;
}

// 分页
$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 15;

// 获取总数
$total = fetchCount(
    "SELECT COUNT(*) FROM payment_record pr 
     LEFT JOIN customer c ON pr.customer_id = c.id 
     {$where}",
    $params
);
$pagination = pagination($total, $page, $pageSize);

// 获取收款记录列表
$payments = fetchAll(
    "SELECT pr.*, c.customer_name,
            GROUP_CONCAT(DISTINCT om.order_no ORDER BY om.order_no SEPARATOR ', ') as related_orders
     FROM payment_record pr 
     LEFT JOIN customer c ON pr.customer_id = c.id 
     LEFT JOIN order_payment_link opl ON pr.id = opl.payment_id
     LEFT JOIN order_main om ON opl.order_id = om.id
     {$where} 
     GROUP BY pr.id
     ORDER BY pr.payment_date DESC, pr.create_time DESC
     LIMIT {$pagination['offset']}, {$pagination['pageSize']}",
    $params
);

// 获取客户列表（用于筛选下拉框）
$customers = fetchAll("SELECT id, customer_name FROM customer ORDER BY customer_name");

// 计算汇总数据
$summary = fetchOne(
    "SELECT 
        COALESCE(SUM(pr.amount), 0) as total_amount,
        COUNT(*) as total_count
     FROM payment_record pr 
     LEFT JOIN customer c ON pr.customer_id = c.id 
     {$where}",
    $params
);

// 构建分页URL
$urlParams = [];
if (!empty($keyword)) $urlParams[] = "keyword=" . urlencode($keyword);
if ($customerId > 0) $urlParams[] = "customer_id=" . $customerId;
if ($paymentType > 0) $urlParams[] = "payment_type=" . $paymentType;
if ($isCashOrder >= 0) $urlParams[] = "is_cash_order=" . $isCashOrder;
if (!empty($dateFrom)) $urlParams[] = "date_from=" . $dateFrom;
if (!empty($dateTo)) $urlParams[] = "date_to=" . $dateTo;
$baseUrl = '/payment.php' . (!empty($urlParams) ? '?' . implode('&', $urlParams) : '?');

// 获取收款方式样式
function getPaymentTypeClass($type) {
    $classes = [
        1 => 'bg-success',      // 现金
        2 => 'bg-primary',      // 银行转账
        3 => 'bg-info',         // 微信
        4 => 'bg-warning'       // 支付宝
    ];
    return $classes[$type] ?? 'bg-secondary';
}
?>

<!-- 搜索栏 -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label text-muted small">关键词</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" name="keyword" placeholder="客户/备注" value="<?php echo htmlspecialchars_string($keyword); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">客户</label>
                <select class="form-select" name="customer_id">
                    <option value="">全部客户</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $customerId == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars_string($c['customer_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">收款方式</label>
                <select class="form-select" name="payment_type">
                    <option value="">全部方式</option>
                    <?php foreach ($paymentTypes as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $paymentType == $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">订单类型</label>
                <select class="form-select" name="is_cash_order">
                    <option value="-1" <?php echo $isCashOrder == -1 ? 'selected' : ''; ?>>全部</option>
                    <option value="0" <?php echo $isCashOrder == 0 ? 'selected' : ''; ?>>普通订单</option>
                    <option value="1" <?php echo $isCashOrder == 1 ? 'selected' : ''; ?>>现金订单</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">收款日期</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>" placeholder="开始日期">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">到</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>" placeholder="结束日期">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </div>
            </div>
            <div class="col-md-12">
                <a href="/payment.php" class="btn btn-outline-secondary btn-sm">重置</a>
                <?php if (!empty($payments)): ?>
                <a href="/payment_export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-file-excel"></i> 导出Excel
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- 汇总卡片 -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">查询期间收款总额</h6>
                        <h3 class="mb-0">¥<?php echo formatMoney($summary['total_amount']); ?></h3>
                    </div>
                    <i class="bi bi-cash-stack display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">收款笔数</h6>
                        <h3 class="mb-0"><?php echo $summary['total_count']; ?> 笔</h3>
                    </div>
                    <i class="bi bi-receipt display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php showFlashMessage(); ?>

<!-- 收款记录列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>收款记录</h5>
        <?php if (isAdmin()): ?>
        <a href="/payment_form.php?action=add" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg me-1"></i>新增收款
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>收款日期</th>
                        <th>客户</th>
                        <th>关联订单</th>
                        <th class="text-end">金额</th>
                        <th>收款方式</th>
                        <th>备注</th>
                        <th>操作人</th>
                        <th style="width: 120px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $index => $payment): ?>
                    <tr>
                        <td><?php echo $pagination['offset'] + $index + 1; ?></td>
                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                        <td>
                            <a href="/customer_form.php?id=<?php echo $payment['customer_id']; ?>&action=view" class="text-decoration-none">
                                <?php echo htmlspecialchars_string($payment['customer_name']); ?>
                            </a>
                            <?php if ($payment['is_cash_order']): ?>
                            <span class="badge bg-warning ms-1">现金</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($payment['related_orders'])): ?>
                                <small class="text-muted"><?php echo htmlspecialchars_string($payment['related_orders']); ?></small>
                            <?php else: ?>
                                <span class="badge bg-secondary">无关联订单</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="fw-bold text-success fs-5">¥<?php echo formatMoney($payment['amount']); ?></span>
                        </td>
                        <td>
                            <span class="badge <?php echo getPaymentTypeClass($payment['payment_type']); ?>">
                                <?php echo $paymentTypes[$payment['payment_type']] ?? '未知'; ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars_string($payment['remark'] ?: '-'); ?></small>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars_string($payment['operator'] ?: '-'); ?></small>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="/payment_form.php?id=<?php echo $payment['id']; ?>&action=view" class="btn btn-sm btn-outline-primary" title="查看">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                <a href="/payment_form.php?id=<?php echo $payment['id']; ?>&action=edit" class="btn btn-sm btn-outline-success" title="编辑">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $payment['id']; ?>)" title="删除">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-4 d-block mb-3"></i>
                            <p>暂无收款记录</p>
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

<script>
function confirmDelete(id) {
    if (confirm('确定要删除这条收款记录吗？\n注意：删除后将自动解除与订单的关联，并更新订单的收款状态。')) {
        window.location.href = '/payment_form.php?id=' + id + '&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
