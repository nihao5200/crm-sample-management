<?php
/**
 * 客户应收款报表
 */
$pageTitle = '客户应收款报表';
require_once __DIR__ . '/includes/header.php';

// 搜索条件
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'unpaid_desc';

// 构建查询
$where = "WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $where .= " AND c.customer_name LIKE ?";
    $params[] = "%{$keyword}%";
}

// 排序
$orderBy = "ORDER BY unpaid_amount DESC";
switch ($sortBy) {
    case 'order_desc':
        $orderBy = "ORDER BY total_order_amount DESC";
        break;
    case 'order_asc':
        $orderBy = "ORDER BY total_order_amount ASC";
        break;
    case 'paid_desc':
        $orderBy = "ORDER BY total_paid DESC";
        break;
    case 'unpaid_desc':
    default:
        $orderBy = "ORDER BY unpaid_amount DESC";
        break;
}

// 获取客户应收款数据（使用 order_total_amount 字段）
$reportData = fetchAll(
    "SELECT 
        c.id,
        c.customer_name,
        c.contact,
        c.phone,
        COALESCE(order_summary.total_amount, 0) as total_order_amount,
        COALESCE(order_summary.order_count, 0) as order_count,
        COALESCE(payment_summary.total_paid, 0) as total_paid,
        COALESCE(order_summary.total_amount, 0) - COALESCE(payment_summary.total_paid, 0) as unpaid_amount
     FROM customer c
     LEFT JOIN (
         SELECT 
             customer_id,
             SUM(order_total_amount) as total_amount,
             COUNT(DISTINCT id) as order_count
         FROM order_main
         GROUP BY customer_id
     ) order_summary ON c.id = order_summary.customer_id
     LEFT JOIN (
         SELECT 
             customer_id,
             SUM(amount) as total_paid
         FROM payment_record
         GROUP BY customer_id
     ) payment_summary ON c.id = payment_summary.customer_id
     {$where}
     HAVING total_order_amount > 0
     {$orderBy}",
    $params
);

// 计算汇总
$totalOrderAmount = array_sum(array_column($reportData, 'total_order_amount'));
$totalPaid = array_sum(array_column($reportData, 'total_paid'));
$totalUnpaid = array_sum(array_column($reportData, 'unpaid_amount'));

// 导出Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="客户应收款报表_' . date('Ymd') . '.csv"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    
    // 表头
    fputcsv($output, ['客户名称', '联系人', '电话', '订单金额', '已收金额', '未收金额', '收款比例']);
    
    // 数据
    foreach ($reportData as $row) {
        $paymentRate = $row['total_order_amount'] > 0 ? round($row['total_paid'] / $row['total_order_amount'] * 100, 1) : 0;
        fputcsv($output, [
            $row['customer_name'],
            $row['contact'],
            $row['phone'],
            $row['total_order_amount'],
            $row['total_paid'],
            $row['unpaid_amount'],
            $paymentRate . '%'
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!-- 搜索栏 -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label text-muted small">客户名称</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" name="keyword" placeholder="搜索客户" value="<?php echo htmlspecialchars_string($keyword); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small">排序方式</label>
                <select class="form-select" name="sort_by">
                    <option value="unpaid_desc" <?php echo $sortBy == 'unpaid_desc' ? 'selected' : ''; ?>>未收金额（高到低）</option>
                    <option value="order_desc" <?php echo $sortBy == 'order_desc' ? 'selected' : ''; ?>>订单金额（高到低）</option>
                    <option value="order_asc" <?php echo $sortBy == 'order_asc' ? 'selected' : ''; ?>>订单金额（低到高）</option>
                    <option value="paid_desc" <?php echo $sortBy == 'paid_desc' ? 'selected' : ''; ?>>已收金额（高到低）</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>查询</button>
            </div>
            <div class="col-md-3 text-end">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success">
                    <i class="bi bi-file-excel me-1"></i>导出Excel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- 汇总卡片 -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">总订单金额</h6>
                        <h3 class="mb-0">¥<?php echo formatMoney($totalOrderAmount); ?></h3>
                    </div>
                    <i class="bi bi-cart3 display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">总已收金额</h6>
                        <h3 class="mb-0">¥<?php echo formatMoney($totalPaid); ?></h3>
                    </div>
                    <i class="bi bi-cash-stack display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">总未收金额</h6>
                        <h3 class="mb-0">¥<?php echo formatMoney($totalUnpaid); ?></h3>
                    </div>
                    <i class="bi bi-exclamation-triangle display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 报表列表 -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>客户应收款明细</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>客户</th>
                        <th>联系人/电话</th>
                        <th class="text-end">订单金额</th>
                        <th class="text-end">已收金额</th>
                        <th class="text-end">未收金额</th>
                        <th class="text-center">收款进度</th>
                        <th style="width: 150px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $row): 
                        $progress = $row['total_order_amount'] > 0 ? round($row['total_paid'] / $row['total_order_amount'] * 100, 1) : 0;
                        $progressClass = $progress >= 100 ? 'bg-success' : ($progress >= 50 ? 'bg-info' : 'bg-warning');
                    ?>
                    <tr>
                        <td>
                            <div class="fw-medium"><?php echo htmlspecialchars_string($row['customer_name']); ?></div>
                            <small class="text-muted"><?php echo $row['order_count']; ?> 笔订单</small>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars_string($row['contact']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars_string($row['phone']); ?></small>
                        </td>
                        <td class="text-end">
                            <span class="fw-bold">¥<?php echo formatMoney($row['total_order_amount']); ?></span>
                        </td>
                        <td class="text-end">
                            <span class="text-success fw-bold">¥<?php echo formatMoney($row['total_paid']); ?></span>
                        </td>
                        <td class="text-end">
                            <span class="text-danger fw-bold">¥<?php echo formatMoney($row['unpaid_amount']); ?></span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                    <div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" style="width: <?php echo min(100, $progress); ?>%"></div>
                                </div>
                                <small class="text-muted" style="width: 45px;"><?php echo $progress; ?>%</small>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="/customer_form.php?id=<?php echo $row['id']; ?>&action=view" class="btn btn-sm btn-outline-primary" title="查看客户">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="/payment.php?customer_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-success" title="查看收款">
                                    <i class="bi bi-cash-coin"></i>
                                </a>
                                <a href="/order.php?customer_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info" title="查看订单">
                                    <i class="bi bi-cart3"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reportData)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-4 d-block mb-3"></i>
                            <p>暂无数据</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
