<?php
/**
 * 客户管理 - 列表页面（统一UI风格）
 */
$pageTitle = '客户管理';
require_once __DIR__ . '/includes/header.php';

// 搜索条件 - 使用原始GET避免htmlspecialchars转义%
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $where .= " AND (customer_name LIKE ? OR contact LIKE ? OR phone LIKE ?)";
    $likeKeyword = "%{$keyword}%";
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

// 分页
$page = intval(input('page', 1, 'get'));
$pageSize = 10;

$total = fetchCount("SELECT COUNT(*) FROM customer {$where}", $params);
$pagination = pagination($total, $page, $pageSize);

// 获取客户列表
$customers = fetchAll(
    "SELECT c.*, 
            (SELECT COUNT(*) FROM order_main WHERE customer_id = c.id) as order_count,
            (SELECT COUNT(*) FROM sample_record WHERE customer_id = c.id) as sample_count
     FROM customer c 
     {$where} 
     ORDER BY c.create_time DESC 
     LIMIT {$pagination['offset']}, {$pagination['pageSize']}",
    $params
);

// 构建分页URL
$urlParams = [];
if (!empty($keyword)) $urlParams[] = "keyword=" . urlencode($keyword);
$baseUrl = '/customer.php' . (!empty($urlParams) ? '?' . implode('&', $urlParams) : '?');
?>

<!-- 搜索栏 -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" name="keyword" placeholder="客户名称/联系人/电话" value="<?php echo htmlspecialchars_string($keyword); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>搜索</button>
                <a href="/customer.php" class="btn btn-outline-secondary">重置</a>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if (isAdmin()): ?>
                <a href="/customer_import.php" class="btn btn-outline-primary me-2"><i class="bi bi-upload me-1"></i>批量导入</a>
                <a href="/customer_form.php?action=add" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>新增客户</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php showFlashMessage(); ?>

<!-- 客户列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-people me-2"></i>客户列表</h5>
        <span class="text-muted">共 <?php echo $total; ?> 条记录</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>客户信息</th>
                        <th>联系人</th>
                        <th>联系电话</th>
                        <th class="text-center">关联数据</th>
                        <th>创建时间</th>
                        <th style="width: 150px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $index => $customer): ?>
                    <tr>
                        <td><?php echo $pagination['offset'] + $index + 1; ?></td>
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
                            <a href="/order.php?customer_id=<?php echo $customer['id']; ?>" class="badge bg-primary text-decoration-none me-1">
                                <i class="bi bi-cart3 me-1"></i><?php echo $customer['order_count']; ?>
                            </a>
                            <a href="/sample.php?customer_id=<?php echo $customer['id']; ?>" class="badge bg-info text-decoration-none">
                                <i class="bi bi-box me-1"></i><?php echo $customer['sample_count']; ?>
                            </a>
                        </td>
                        <td><?php echo formatDate($customer['create_time']); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="/customer_form.php?id=<?php echo $customer['id']; ?>&action=view" class="btn btn-sm btn-outline-primary" title="查看">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                <a href="/customer_form.php?id=<?php echo $customer['id']; ?>&action=edit" class="btn btn-sm btn-outline-success" title="编辑">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars_string($customer['customer_name']); ?>')" title="删除">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-4 d-block mb-3"></i>
                            <p>暂无客户数据</p>
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
function confirmDelete(id, name) {
    if (confirm('确定要删除客户 "' + name + '" 吗？\n注意：删除客户将同时删除其所有订单和样品记录！')) {
        window.location.href = '/customer_form.php?id=' + id + '&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
