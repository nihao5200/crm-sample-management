<?php
/**
 * 客户未下单提醒 - 详细列表
 */
$pageTitle = '客户未下单提醒';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/includes/header.php';

// 获取筛选条件
$type = input('type', '', 'get'); // never, 30, 60, 90
$keyword = input('keyword', '', 'get');

// 提醒天数设置（默认30天）
$noOrderDays = 30;

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

// 根据类型筛选
switch ($type) {
    case 'never':
        $where .= " AND NOT EXISTS (SELECT 1 FROM order_main om WHERE om.customer_id = c.id)";
        break;
    case '30':
        $where .= " AND EXISTS (SELECT 1 FROM order_main om WHERE om.customer_id = c.id) 
                   AND DATEDIFF(CURDATE(), (SELECT MAX(order_date) FROM order_main WHERE customer_id = c.id)) BETWEEN 30 AND 59";
        break;
    case '60':
        $where .= " AND EXISTS (SELECT 1 FROM order_main om WHERE om.customer_id = c.id) 
                   AND DATEDIFF(CURDATE(), (SELECT MAX(order_date) FROM order_main WHERE customer_id = c.id)) BETWEEN 60 AND 89";
        break;
    case '90':
        $where .= " AND EXISTS (SELECT 1 FROM order_main om WHERE om.customer_id = c.id) 
                   AND DATEDIFF(CURDATE(), (SELECT MAX(order_date) FROM order_main WHERE customer_id = c.id)) >= 90";
        break;
    default:
        // 默认显示所有超过30天未下单的客户
        $where .= " AND (NOT EXISTS (SELECT 1 FROM order_main om WHERE om.customer_id = c.id) 
                   OR DATEDIFF(CURDATE(), (SELECT MAX(order_date) FROM order_main WHERE customer_id = c.id)) >= ?)";
        $params[] = $noOrderDays;
}

// 关键词搜索
if (!empty($keyword)) {
    $where .= " AND (c.customer_name LIKE ? OR c.contact LIKE ? OR c.phone LIKE ?)";
    $likeKeyword = "%{$keyword}%";
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

// 分页
$page = intval(input('page', 1, 'get'));
$pageSize = 20;

$total = fetchCount(
    "SELECT COUNT(*) FROM customer c {$where}",
    $params
);
$pagination = pagination($total, $page, $pageSize);

// 获取客户列表
$customers = fetchAll(
    "SELECT c.*, 
            (SELECT MAX(order_date) FROM order_main WHERE customer_id = c.id) as last_order_date,
            DATEDIFF(CURDATE(), (SELECT MAX(order_date) FROM order_main WHERE customer_id = c.id)) as days_since_last_order,
            (SELECT COUNT(*) FROM order_main WHERE customer_id = c.id) as total_orders,
            (SELECT SUM(order_total_amount) FROM order_main WHERE customer_id = c.id) as total_amount
     FROM customer c 
     {$where}
     ORDER BY last_order_date ASC
     LIMIT {$pagination['offset']}, {$pagination['pageSize']}",
    $params
);

// 统计各类型数量
$stats = [
    'never' => fetchCount("SELECT COUNT(*) FROM customer c WHERE NOT EXISTS (SELECT 1 FROM order_main om WHERE om.customer_id = c.id)"),
    '30' => fetchCount("SELECT COUNT(*) FROM customer c WHERE EXISTS (SELECT 1 FROM order_main om WHERE om.customer_id = c.id) AND DATEDIFF(CURDATE(), (SELECT MAX(order_date) FROM order_main WHERE customer_id = c.id)) BETWEEN 30 AND 59"),
    '60' => fetchCount("SELECT COUNT(*) FROM customer c WHERE EXISTS (SELECT 1 FROM order_main om WHERE om.customer_id = c.id) AND DATEDIFF(CURDATE(), (SELECT MAX(order_date) FROM order_main WHERE customer_id = c.id)) BETWEEN 60 AND 89"),
    '90' => fetchCount("SELECT COUNT(*) FROM customer c WHERE EXISTS (SELECT 1 FROM order_main om WHERE om.customer_id = c.id) AND DATEDIFF(CURDATE(), (SELECT MAX(order_date) FROM order_main WHERE customer_id = c.id)) >= 90"),
];

// 构建分页URL
$urlParams = [];
if (!empty($type)) $urlParams[] = "type=" . urlencode($type);
if (!empty($keyword)) $urlParams[] = "keyword=" . urlencode($keyword);
$baseUrl = '/customer_no_order.php' . (!empty($urlParams) ? '?' . implode('&', $urlParams) : '?');
?>

<!-- 页面标题 -->
<div class="page-header">
    <h2><i class="bi bi-person-x"></i> 客户未下单提醒</h2>
</div>

<?php showFlashMessage(); ?>

<!-- 统计卡片 -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <a href="/customer_no_order.php?type=never" class="text-decoration-none">
            <div class="card h-100 <?php echo $type == 'never' ? 'border-secondary border-2' : ''; ?>">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h4 class="text-secondary mb-1"><?php echo $stats['never']; ?></h4>
                        <small class="text-muted">从未下单</small>
                    </div>
                    <i class="bi bi-person-x-fill text-secondary fs-2"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/customer_no_order.php?type=30" class="text-decoration-none">
            <div class="card h-100 <?php echo $type == '30' ? 'border-primary border-2' : ''; ?>">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h4 class="text-primary mb-1"><?php echo $stats['30']; ?></h4>
                        <small class="text-muted">30-60天未下单</small>
                    </div>
                    <i class="bi bi-calendar-x text-primary fs-2"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/customer_no_order.php?type=60" class="text-decoration-none">
            <div class="card h-100 <?php echo $type == '60' ? 'border-warning border-2' : ''; ?>">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h4 class="text-warning mb-1"><?php echo $stats['60']; ?></h4>
                        <small class="text-muted">60-90天未下单</small>
                    </div>
                    <i class="bi bi-calendar2-x text-warning fs-2"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/customer_no_order.php?type=90" class="text-decoration-none">
            <div class="card h-100 <?php echo $type == '90' ? 'border-danger border-2' : ''; ?>">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h4 class="text-danger mb-1"><?php echo $stats['90']; ?></h4>
                        <small class="text-muted">超过90天未下单</small>
                    </div>
                    <i class="bi bi-calendar3-x text-danger fs-2"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- 搜索栏 -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-center">
            <?php if ($type): ?>
            <input type="hidden" name="type" value="<?php echo $type; ?>">
            <?php endif; ?>
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" name="keyword" placeholder="客户名称/联系人/电话" value="<?php echo htmlspecialchars_string($keyword); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>搜索</button>
                <a href="/customer_no_order.php<?php echo $type ? '?type=' . $type : ''; ?>" class="btn btn-outline-secondary">重置</a>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>返回首页</a>
            </div>
        </form>
    </div>
</div>

<!-- 客户列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>客户列表</h5>
        <span class="text-muted">共 <?php echo $total; ?> 条记录</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>客户信息</th>
                        <th>联系人</th>
                        <th>联系电话</th>
                        <th class="text-center">历史订单</th>
                        <th>最后下单日期</th>
                        <th>未下单天数</th>
                        <th style="width: 200px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-size: 16px;">
                                    <?php echo mb_substr($customer['customer_name'], 0, 1); ?>
                                </div>
                                <div>
                                    <div class="fw-medium"><?php echo htmlspecialchars_string($customer['customer_name']); ?></div>
                                    <?php if (!empty($customer['address'])): ?>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars_string($customer['address']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars_string($customer['contact']); ?></td>
                        <td><?php echo htmlspecialchars_string($customer['phone']); ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?php echo $customer['total_orders'] ?: 0; ?> 笔</span>
                            <?php if ($customer['total_amount'] > 0): ?>
                            <div class="small text-muted mt-1">¥<?php echo formatMoney($customer['total_amount']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($customer['last_order_date']): ?>
                                <span class="text-muted"><?php echo $customer['last_order_date']; ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">从未下单</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($customer['days_since_last_order']): ?>
                                <span class="badge <?php echo $customer['days_since_last_order'] >= 90 ? 'bg-danger' : ($customer['days_since_last_order'] >= 60 ? 'bg-warning text-dark' : 'bg-primary'); ?> fs-6">
                                    <?php echo $customer['days_since_last_order']; ?> 天
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="/customer_form.php?id=<?php echo $customer['id']; ?>&action=view" class="btn btn-sm btn-outline-primary" title="查看客户">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="/order_form.php?action=add&customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-success" title="新建订单">
                                    <i class="bi bi-plus-lg"></i> 订单
                                </a>
                                <a href="/sample_form.php?action=add&customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-info" title="新建样品">
                                    <i class="bi bi-box"></i> 样品
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-check-circle display-4 d-block mb-3 text-success"></i>
                            <p>该分类下没有客户记录</p>
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
