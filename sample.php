<?php
/**
 * 样品管理 - 列表页面（统一UI风格）
 */
$pageTitle = '样品管理';
require_once __DIR__ . '/includes/header.php';

// 搜索条件 - 使用原始GET避免htmlspecialchars转义%
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$customerId = intval($_GET['customer_id'] ?? 0);

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $where .= " AND (sr.sample_no LIKE ? OR c.customer_name LIKE ? OR sr.product_model LIKE ?)";
    $likeKeyword = "%{$keyword}%";
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

if (!empty($status)) {
    $where .= " AND sr.sample_status = ?";
    $params[] = $status;
}

if ($customerId > 0) {
    $where .= " AND sr.customer_id = ?";
    $params[] = $customerId;
}

// 分页
$page = intval(input('page', 1, 'get'));
$pageSize = 10;

$total = fetchCount(
    "SELECT COUNT(*) FROM sample_record sr 
     LEFT JOIN customer c ON sr.customer_id = c.id 
     {$where}",
    $params
);
$pagination = pagination($total, $page, $pageSize);

// 获取样品列表
$samples = fetchAll(
    "SELECT sr.*, c.customer_name
     FROM sample_record sr 
     LEFT JOIN customer c ON sr.customer_id = c.id 
     {$where} 
     ORDER BY sr.create_time DESC 
     LIMIT {$pagination['offset']}, {$pagination['pageSize']}",
    $params
);

// 构建分页URL
$urlParams = [];
if (!empty($keyword)) $urlParams[] = "keyword=" . urlencode($keyword);
if (!empty($status)) $urlParams[] = "status=" . urlencode($status);
if ($customerId > 0) $urlParams[] = "customer_id=" . $customerId;
$baseUrl = '/sample.php' . (!empty($urlParams) ? '?' . implode('&', $urlParams) : '?');

// 样品状态选项（用于下拉框）
$statusOptions = [
    'pending' => '待确认',
    'confirmed' => '已确认',
    'returned' => '已退回',
    'mass_production' => '已量产'
];

// 注意：getSampleStatusClass() 和 getSampleStatusText() 函数已在 functions.php 中定义
?>

<!-- 搜索栏 -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <?php if ($customerId > 0): ?>
            <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
            <?php endif; ?>
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" name="keyword" placeholder="样品号/客户名称/产品型号" value="<?php echo htmlspecialchars_string($keyword); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">全部状态</option>
                    <?php foreach ($statusOptions as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $status == $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>搜索</button>
                <a href="/sample.php<?php echo $customerId > 0 ? '?customer_id=' . $customerId : ''; ?>" class="btn btn-outline-secondary">重置</a>
            </div>
            <div class="col-md-2 text-md-end">
                <?php if (isAdmin()): ?>
                <a href="/sample_form.php?action=add" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>新增样品</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php showFlashMessage(); ?>

<!-- 样品列表 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>样品列表</h5>
        <span class="text-muted">共 <?php echo $total; ?> 条记录</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>样品信息</th>
                        <th>客户</th>
                        <th>产品明细</th>
                        <th>送样日期</th>
                        <th class="text-center">状态</th>
                        <th>跟进记录</th>
                        <th style="width: 150px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($samples as $index => $sample): ?>
                    <tr>
                        <td><?php echo $pagination['offset'] + $index + 1; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-info text-white rounded d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="bi bi-box"></i>
                                </div>
                                <div>
                                    <div class="fw-medium"><?php echo $sample['sample_no']; ?></div>
                                    <small class="text-muted"><i class="bi bi-tag me-1"></i><?php echo htmlspecialchars_string($sample['product_model']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="/customer_form.php?id=<?php echo $sample['customer_id']; ?>&action=view" class="text-decoration-none">
                                <?php echo htmlspecialchars_string($sample['customer_name']); ?>
                            </a>
                        </td>
                        <td>
                            <div class="small">
                                <!-- 第一行：颜色、比例、含税标签 -->
                                <div class="mb-1">
                                    <?php if (!empty($sample['color'])): ?>
                                    <span class="badge bg-light text-dark border me-1"><i class="bi bi-palette me-1"></i><?php echo htmlspecialchars_string($sample['color']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($sample['ratio'])): ?>
                                    <span class="badge bg-light text-dark border me-1"><i class="bi bi-percent me-1"></i><?php echo htmlspecialchars_string($sample['ratio']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($sample['tax_included']): ?>
                                    <span class="badge bg-success" style="font-size: 10px;">含税</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary" style="font-size: 10px;">不含税</span>
                                    <?php endif; ?>
                                </div>
                                <!-- 第二行：单位 -->
                                <div class="mb-1">
                                    <span class="text-muted">单位：</span>
                                    <span class="fw-medium"><?php echo htmlspecialchars_string($sample['unit'] ?? '个'); ?></span>
                                </div>
                                <!-- 第三行：单价 -->
                                <?php if ($sample['unit_price'] > 0): ?>
                                <div>
                                    <span class="text-muted">单价：</span>
                                    <span class="text-primary fw-medium">¥<?php echo number_format($sample['unit_price'], 2); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo formatDate($sample['send_date']); ?></td>
                        <td class="text-center">
                            <span class="badge <?php echo getSampleStatusClass($sample['sample_status']); ?> fs-6">
                                <?php echo getSampleStatusText($sample['sample_status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($sample['follow_up'])): ?>
                            <small class="text-muted" title="<?php echo htmlspecialchars_string($sample['follow_up']); ?>">
                                <?php echo mb_strlen($sample['follow_up']) > 20 ? mb_substr($sample['follow_up'], 0, 20) . '...' : htmlspecialchars_string($sample['follow_up']); ?>
                            </small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="/sample_form.php?id=<?php echo $sample['id']; ?>&action=view" class="btn btn-sm btn-outline-primary" title="查看">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                <a href="/sample_form.php?id=<?php echo $sample['id']; ?>&action=edit" class="btn btn-sm btn-outline-success" title="编辑">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $sample['id']; ?>, '<?php echo $sample['sample_no']; ?>')" title="删除">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($samples)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-4 d-block mb-3"></i>
                            <p>暂无样品数据</p>
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
function confirmDelete(id, sampleNo) {
    if (confirm('确定要删除样品 "' + sampleNo + '" 吗？')) {
        window.location.href = '/sample_form.php?id=' + id + '&action=delete';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
