<?php
/**
 * 客户管理 - 新增/编辑/查看/删除
 */
require_once __DIR__ . '/config/functions.php';

$action = input('action', 'add', 'get');
$id = intval(input('id', 0, 'get'));

// 删除操作
if ($action == 'delete' && $id > 0) {
    requireAdmin();
    
    // 检查客户是否存在
    $customer = fetchOne("SELECT * FROM customer WHERE id = ?", [$id]);
    if (!$customer) {
        setFlashMessage('error', '客户不存在');
        redirect('/customer.php');
    }
    
    // 删除客户（关联的订单和样品会通过外键级联删除）
    try {
        beginTransaction();
        execute("DELETE FROM customer WHERE id = ?", [$id]);
        commit();
        setFlashMessage('success', '客户删除成功');
    } catch (Exception $e) {
        rollback();
        setFlashMessage('error', '删除失败：' . $e->getMessage());
    }
    
    redirect('/customer.php');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireAdmin();
    
    $data = [
        'customer_name' => trim($_POST['customer_name'] ?? ''),
        'contact' => trim($_POST['contact'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'remark' => trim($_POST['remark'] ?? '')
    ];
    
    // 数据验证 - 只有客户名称是必填项
    if (empty($data['customer_name'])) {
        setFlashMessage('error', '客户名称不能为空');
    } else {
        if ($action == 'edit' && $id > 0) {
            // 更新
            $sql = "UPDATE customer SET 
                    customer_name = ?, contact = ?, phone = ?, email = ?, address = ?, remark = ?
                    WHERE id = ?";
            $params = array_values($data);
            $params[] = $id;
            
            try {
                execute($sql, $params);
                setFlashMessage('success', '客户信息更新成功');
                redirect('/customer.php');
            } catch (Exception $e) {
                setFlashMessage('error', '更新失败：' . $e->getMessage());
            }
        } else {
            // 新增
            $sql = "INSERT INTO customer (customer_name, contact, phone, email, address, remark) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            try {
                insert($sql, array_values($data));
                setFlashMessage('success', '客户添加成功');
                redirect('/customer.php');
            } catch (Exception $e) {
                setFlashMessage('error', '添加失败：' . $e->getMessage());
            }
        }
    }
    
    // 如果有错误，保持当前数据
    if ($action == 'edit') {
        redirect('/customer_form.php?id=' . $id . '&action=edit');
    } else {
        redirect('/customer_form.php?action=add');
    }
}

// 获取客户数据（编辑/查看模式）
$customer = null;
if (($action == 'edit' || $action == 'view') && $id > 0) {
    $customer = fetchOne("SELECT * FROM customer WHERE id = ?", [$id]);
    if (!$customer) {
        setFlashMessage('error', '客户不存在');
        redirect('/customer.php');
    }
}

// 页面标题
$pageTitle = $action == 'add' ? '新增客户' : ($action == 'edit' ? '编辑客户' : '客户详情');
require_once __DIR__ . '/includes/header.php';
?>

<!-- 页面标题 -->
<div class="page-header">
    <h2>
        <i class="bi bi-<?php echo $action == 'add' ? 'plus-lg' : ($action == 'edit' ? 'pencil' : 'eye'); ?>"></i> 
        <?php echo $pageTitle; ?>
    </h2>
</div>

<?php showFlashMessage(); ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="customer_name" class="form-label">客户名称 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="customer_name" name="customer_name" 
                           value="<?php echo $customer ? htmlspecialchars_string($customer['customer_name']) : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                    <div class="invalid-feedback">请输入客户名称</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="contact" class="form-label">联系人</label>
                    <input type="text" class="form-control" id="contact" name="contact" 
                           value="<?php echo $customer ? htmlspecialchars_string($customer['contact']) : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">联系电话</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo $customer ? htmlspecialchars_string($customer['phone']) : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">邮箱</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo $customer ? htmlspecialchars_string($customer['email']) : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">地址</label>
                <textarea class="form-control" id="address" name="address" rows="2" 
                          <?php echo $action == 'view' ? 'readonly' : ''; ?>><?php echo $customer ? htmlspecialchars_string($customer['address']) : ''; ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="remark" class="form-label">备注</label>
                <textarea class="form-control" id="remark" name="remark" rows="3" 
                          <?php echo $action == 'view' ? 'readonly' : ''; ?>><?php echo $customer ? htmlspecialchars_string($customer['remark']) : ''; ?></textarea>
            </div>
            
            <?php if ($action == 'view' && $customer): ?>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">创建时间</label>
                    <input type="text" class="form-control" value="<?php echo formatDate($customer['create_time'], 'Y-m-d H:i:s'); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">更新时间</label>
                    <input type="text" class="form-control" value="<?php echo formatDate($customer['update_time'], 'Y-m-d H:i:s'); ?>" readonly>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="d-flex gap-2">
                <?php if ($action != 'view'): ?>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> 保存
                </button>
                <?php endif; ?>
                <a href="/customer.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> 返回列表
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($action == 'view' && $customer): 
    // 获取客户对账数据（使用 order_total_amount 字段）
    $accountSummary = fetchOne(
        "SELECT 
            COALESCE(SUM(om.order_total_amount), 0) as total_order_amount,
            COUNT(DISTINCT om.id) as order_count
         FROM order_main om
         WHERE om.customer_id = ?",
        [$customer['id']]
    );
    
    $totalPaid = fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total_paid
         FROM payment_record
         WHERE customer_id = ?",
        [$customer['id']]
    )['total_paid'];
    
    $unpaidAmount = $accountSummary['total_order_amount'] - $totalPaid;
    
    // 获取收款记录
    $paymentRecords = fetchAll(
        "SELECT * FROM payment_record 
         WHERE customer_id = ? 
         ORDER BY payment_date DESC LIMIT 10",
        [$customer['id']]
    );
?>
<!-- 客户对账模块 -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-calculator"></i> 客户对账</h5>
                <a href="/payment_report.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download"></i> 导出对账单
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">订单总额</h6>
                                <h4 class="text-primary mb-0">¥<?php echo formatMoney($accountSummary['total_order_amount']); ?></h4>
                                <small class="text-muted"><?php echo $accountSummary['order_count']; ?> 笔订单</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">已收金额</h6>
                                <h4 class="text-success mb-0">¥<?php echo formatMoney($totalPaid); ?></h4>
                                <small class="text-muted">收款进度</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">未收金额</h6>
                                <h4 class="text-danger mb-0">¥<?php echo formatMoney($unpaidAmount); ?></h4>
                                <small class="text-muted">待收款</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">收款比例</h6>
                                <h4 class="text-info mb-0">
                                    <?php echo $accountSummary['total_order_amount'] > 0 ? round($totalPaid / $accountSummary['total_order_amount'] * 100, 1) : 0; ?>%
                                </h4>
                                <small class="text-muted">完成度</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 收款记录时间线 -->
                <h6 class="mt-4 mb-3"><i class="bi bi-clock-history"></i> 最近收款记录</h6>
                <?php if (!empty($paymentRecords)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>收款日期</th>
                                <th>收款方式</th>
                                <th class="text-end">金额</th>
                                <th>备注</th>
                                <th>操作人</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $paymentTypes = [1 => '现金', 2 => '银行转账', 3 => '微信', 4 => '支付宝'];
                            foreach ($paymentRecords as $record): 
                            ?>
                            <tr>
                                <td><?php echo formatDate($record['payment_date']); ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $paymentTypes[$record['payment_type']] ?? '未知'; ?></span>
                                    <?php if ($record['is_cash_order']): ?>
                                    <span class="badge bg-warning">现金订单</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-success fw-bold">¥<?php echo formatMoney($record['amount']); ?></td>
                                <td><small><?php echo htmlspecialchars_string($record['remark'] ?: '-'); ?></small></td>
                                <td><small><?php echo htmlspecialchars_string($record['operator'] ?: '-'); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center">
                    <a href="/payment.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary">查看全部收款记录</a>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>暂无收款记录
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 关联数据 -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cart3"></i> 关联订单</h5>
            </div>
            <div class="card-body p-0">
                <?php
                $orders = fetchAll(
                    "SELECT om.*, 
                            (SELECT COALESCE(SUM(amount), 0) FROM order_detail WHERE order_id = om.id) as total_amount
                     FROM order_main om 
                     WHERE om.customer_id = ? ORDER BY om.create_time DESC LIMIT 5",
                    [$customer['id']]
                );
                ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>订单号</th>
                                <th>金额</th>
                                <th>收款状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                $paymentStatusClass = [1 => 'bg-danger', 2 => 'bg-warning', 3 => 'bg-success'];
                                $paymentStatusText = [1 => '未付款', 2 => '部分付款', 3 => '已结清'];
                            ?>
                            <tr>
                                <td><a href="/order_form.php?id=<?php echo $order['id']; ?>&action=view"><?php echo $order['order_no']; ?></a></td>
                                <td>¥<?php echo formatMoney($order['total_amount']); ?></td>
                                <td>
                                    <span class="badge <?php echo $paymentStatusClass[$order['payment_status'] ?? 1]; ?>">
                                        <?php echo $paymentStatusText[$order['payment_status'] ?? 1]; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($orders)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">暂无订单</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-center">
                    <a href="/order.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary">查看全部订单</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-box"></i> 关联样品</h5>
            </div>
            <div class="card-body p-0">
                <?php
                $samples = fetchAll(
                    "SELECT * FROM sample_record WHERE customer_id = ? ORDER BY create_time DESC LIMIT 5",
                    [$customer['id']]
                );
                ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>样品号</th>
                                <th>产品型号</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($samples as $sample): ?>
                            <tr>
                                <td><a href="/sample_form.php?id=<?php echo $sample['id']; ?>&action=view"><?php echo $sample['sample_no']; ?></a></td>
                                <td><?php echo htmlspecialchars_string($sample['product_model']); ?></td>
                                <td><span class="<?php echo getSampleStatusClass($sample['sample_status']); ?>"><?php echo getSampleStatusText($sample['sample_status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($samples)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">暂无样品</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-center">
                    <a href="/sample.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary">查看全部样品</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// 表单验证
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
