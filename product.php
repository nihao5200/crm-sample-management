<?php
/**
 * 产品型号管理页面
 */
$pageTitle = '产品型号管理';
require_once __DIR__ . '/includes/header.php';

// 搜索条件
$keyword = input('keyword', '', 'get');
$category = input('category', '', 'get');

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $where .= " AND (product_model LIKE ? OR product_name LIKE ?)";
    $likeKeyword = "%{$keyword}%";
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

if (!empty($category)) {
    $where .= " AND category = ?";
    $params[] = $category;
}

// 分页
$page = intval(input('page', 1, 'get'));
$pageSize = 10;

$total = fetchCount("SELECT COUNT(*) FROM product {$where}", $params);
$pagination = pagination($total, $page, $pageSize);

// 获取产品列表
$products = fetchAll(
    "SELECT * FROM product {$where} ORDER BY create_time DESC LIMIT {$pagination['offset']}, {$pagination['pageSize']}",
    $params
);

// 获取分类列表
$categories = fetchAll("SELECT DISTINCT category FROM product WHERE category IS NOT NULL AND category != '' ORDER BY category");

// 构建分页URL
$urlParams = [];
if (!empty($keyword)) $urlParams[] = "keyword=" . urlencode($keyword);
if (!empty($category)) $urlParams[] = "category=" . urlencode($category);
$baseUrl = '/product.php' . (!empty($urlParams) ? '?' . implode('&', $urlParams) : '?');
?>

<!-- 搜索栏 -->
<div class="search-bar">
    <form method="GET" action="" class="row g-3 align-items-center">
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0" name="keyword" placeholder="产品型号/名称" value="<?php echo htmlspecialchars_string($keyword); ?>">
            </div>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="category">
                <option value="">所有分类</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?php echo $c['category']; ?>" <?php echo $category == $c['category'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars_string($c['category']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>搜索</button>
            <a href="/product.php" class="btn btn-outline-secondary">重置</a>
        </div>
        <div class="col-md-3 text-md-end">
            <?php if (isAdmin()): ?>
            <a href="/product_form.php?action=add" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>新增产品</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php showFlashMessage(); ?>

<!-- 产品列表 -->
<div class="card">
    <div class="card-header">
        <h5>产品型号列表</h5>
        <span class="text-muted">共 <?php echo $total; ?> 条记录</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>产品型号</th>
                        <th>产品名称</th>
                        <th>规格参数</th>
                        <th>分类</th>
                        <th>参考单价</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><span class="badge bg-primary"><?php echo htmlspecialchars_string($product['product_model']); ?></span></td>
                        <td><?php echo htmlspecialchars_string($product['product_name']); ?></td>
                        <td><small class="text-muted"><?php echo htmlspecialchars_string($product['specification']); ?></small></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars_string($product['category']); ?></span></td>
                        <td class="text-primary fw-bold">¥<?php echo formatMoney($product['unit_price']); ?></td>
                        <td>
                            <?php if ($product['status'] == 1): ?>
                            <span class="badge bg-success">启用</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">禁用</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="/product_form.php?id=<?php echo $product['id']; ?>&action=view" class="btn btn-sm btn-outline-info btn-action" title="查看">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                <a href="/product_form.php?id=<?php echo $product['id']; ?>&action=edit" class="btn btn-sm btn-outline-primary btn-action" title="编辑">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo $product['product_model']; ?>')" title="删除">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2">暂无产品数据</p>
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
function confirmDelete(id, model) {
    if (confirm('确定要删除产品型号 "' + model + '" 吗？')) {
        window.location.href = '/product_form.php?id=' + id + '&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
