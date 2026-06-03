<?php
/**
 * 收款记录管理 - 新增/编辑/查看/删除
 */
require_once __DIR__ . '/config/functions.php';

$action = input('action', 'add', 'get');
$id = intval(input('id', 0, 'get'));

// 收款方式选项
$paymentTypes = [
    1 => '现金',
    2 => '银行转账',
    3 => '微信',
    4 => '支付宝'
];

// 删除操作
if ($action == 'delete' && $id > 0) {
    requireAdmin();
    
    $payment = fetchOne("SELECT * FROM payment_record WHERE id = ?", [$id]);
    if (!$payment) {
        setFlashMessage('error', '收款记录不存在');
        redirect('/payment.php');
    }
    
    try {
        beginTransaction();
        
        // 获取关联的订单
        $linkedOrders = fetchAll("SELECT order_id, link_amount FROM order_payment_link WHERE payment_id = ?", [$id]);
        
        // 删除关联记录
        execute("DELETE FROM order_payment_link WHERE payment_id = ?", [$id]);
        
        // 更新订单的收款状态和已收金额
        foreach ($linkedOrders as $link) {
            updateOrderPaymentStatus($link['order_id']);
        }
        
        // 删除收款记录
        execute("DELETE FROM payment_record WHERE id = ?", [$id]);
        
        commit();
        setFlashMessage('success', '收款记录删除成功');
    } catch (Exception $e) {
        rollback();
        setFlashMessage('error', '删除失败：' . $e->getMessage());
    }
    
    redirect('/payment.php');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireAdmin();
    
    $paymentData = [
        'customer_id' => intval($_POST['customer_id'] ?? 0),
        'payment_date' => $_POST['payment_date'] ?? '',
        'amount' => floatval($_POST['amount'] ?? 0),
        'payment_type' => intval($_POST['payment_type'] ?? 1),
        'remark' => trim($_POST['remark'] ?? ''),
        'operator' => $_SESSION['admin_username'] ?? 'admin',
        'is_cash_order' => isset($_POST['is_cash_order']) ? 1 : 0
    ];
    
    // 处理关联订单
    $linkedOrders = [];
    if (isset($_POST['order_id']) && is_array($_POST['order_id'])) {
        foreach ($_POST['order_id'] as $index => $orderId) {
            if ($orderId > 0) {
                $linkedOrders[] = [
                    'order_id' => intval($orderId),
                    'amount' => floatval($_POST['order_amount'][$index] ?? 0)
                ];
            }
        }
    }
    
    // 数据验证
    $errors = [];
    if ($paymentData['customer_id'] <= 0) {
        $errors[] = '请选择客户';
    }
    if (empty($paymentData['payment_date'])) {
        $errors[] = '请选择收款日期';
    }
    if ($paymentData['amount'] <= 0) {
        $errors[] = '收款金额必须大于0';
    }
    
    // 验证关联订单金额总和是否等于收款金额
    if (!empty($linkedOrders)) {
        $totalLinkedAmount = array_sum(array_column($linkedOrders, 'amount'));
        if (abs($totalLinkedAmount - $paymentData['amount']) > 0.01) {
            $errors[] = '关联订单金额总和（¥' . formatMoney($totalLinkedAmount) . '）必须等于收款金额（¥' . formatMoney($paymentData['amount']) . '）';
        }
    }
    
    if (empty($errors)) {
        try {
            beginTransaction();
            
            if ($action == 'edit' && $id > 0) {
                // 更新收款记录
                $sql = "UPDATE payment_record SET 
                        customer_id = ?, payment_date = ?, amount = ?, payment_type = ?, 
                        remark = ?, is_cash_order = ?
                        WHERE id = ?";
                execute($sql, [
                    $paymentData['customer_id'],
                    $paymentData['payment_date'],
                    $paymentData['amount'],
                    $paymentData['payment_type'],
                    $paymentData['remark'],
                    $paymentData['is_cash_order'],
                    $id
                ]);
                
                // 删除旧关联
                $oldLinks = fetchAll("SELECT order_id FROM order_payment_link WHERE payment_id = ?", [$id]);
                execute("DELETE FROM order_payment_link WHERE payment_id = ?", [$id]);
                
                // 更新旧订单的收款状态
                foreach ($oldLinks as $oldLink) {
                    updateOrderPaymentStatus($oldLink['order_id']);
                }
                
                $paymentId = $id;
            } else {
                // 新增收款记录
                $sql = "INSERT INTO payment_record 
                        (customer_id, payment_date, amount, payment_type, remark, operator, is_cash_order) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $paymentId = insert($sql, [
                    $paymentData['customer_id'],
                    $paymentData['payment_date'],
                    $paymentData['amount'],
                    $paymentData['payment_type'],
                    $paymentData['remark'],
                    $paymentData['operator'],
                    $paymentData['is_cash_order']
                ]);
            }
            
            // 插入新的订单关联
            foreach ($linkedOrders as $link) {
                execute("INSERT INTO order_payment_link (order_id, payment_id, link_amount) VALUES (?, ?, ?)", [
                    $link['order_id'],
                    $paymentId,
                    $link['amount']
                ]);
                
                // 更新订单收款状态
                updateOrderPaymentStatus($link['order_id']);
            }
            
            commit();
            setFlashMessage('success', $action == 'edit' ? '收款记录更新成功' : '收款记录添加成功');
            redirect('/payment.php');
        } catch (Exception $e) {
            rollback();
            setFlashMessage('error', '保存失败：' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
    
    if ($action == 'edit') {
        redirect('/payment_form.php?id=' . $id . '&action=edit');
    } else {
        redirect('/payment_form.php?action=add');
    }
}

// 获取收款记录数据
$payment = null;
$linkedOrders = [];
if (($action == 'edit' || $action == 'view') && $id > 0) {
    $payment = fetchOne("SELECT * FROM payment_record WHERE id = ?", [$id]);
    if (!$payment) {
        setFlashMessage('error', '收款记录不存在');
        redirect('/payment.php');
    }
    
    // 获取关联的订单
    $linkedOrders = fetchAll(
        "SELECT opl.*, om.order_no, om.customer_id 
         FROM order_payment_link opl 
         LEFT JOIN order_main om ON opl.order_id = om.id 
         WHERE opl.payment_id = ?",
        [$id]
    );
}

// 获取客户列表
$customers = fetchAll("SELECT id, customer_name FROM customer ORDER BY customer_name");

// 获取当前客户的订单列表（用于选择关联）
$customerOrders = [];
$selectedCustomerId = $payment ? $payment['customer_id'] : 0;
if ($selectedCustomerId > 0) {
    $customerOrders = fetchAll(
        "SELECT om.*, 
                (SELECT COALESCE(SUM(amount), 0) FROM order_detail WHERE order_id = om.id) as total_amount
         FROM order_main om 
         WHERE om.customer_id = ? 
         ORDER BY om.order_date DESC",
        [$selectedCustomerId]
    );
}

// 页面标题
$pageTitle = $action == 'add' ? '新增收款' : ($action == 'edit' ? '编辑收款' : '收款详情');
require_once __DIR__ . '/includes/header.php';

// 更新订单收款状态的辅助函数
function updateOrderPaymentStatus($orderId) {
    // 获取订单的 order_total_amount（从订单主表读取，而不是重新计算）
    $order = fetchOne("SELECT order_total_amount FROM order_main WHERE id = ?", [$orderId]);
    $orderTotal = $order ? floatval($order['order_total_amount']) : 0;
    
    // 计算已收金额（关联收款记录的总和）
    $paidAmount = fetchOne(
        "SELECT COALESCE(SUM(link_amount), 0) as paid 
         FROM order_payment_link opl 
         LEFT JOIN payment_record pr ON opl.payment_id = pr.id 
         WHERE opl.order_id = ?",
        [$orderId]
    )['paid'];
    
    // 确定收款状态（完全独立于订单状态）
    // 1: 未付款, 2: 部分付款, 3: 已结清
    $paymentStatus = 1; // 未付款
    if ($paidAmount <= 0) {
        $paymentStatus = 1; // 未付款
    } elseif ($paidAmount >= $orderTotal && $orderTotal > 0) {
        $paymentStatus = 3; // 已结清
    } else {
        $paymentStatus = 2; // 部分付款
    }
    
    // 更新订单的已收金额和收款状态
    execute(
        "UPDATE order_main SET paid_amount = ?, payment_status = ? WHERE id = ?",
        [$paidAmount, $paymentStatus, $orderId]
    );
}
?>

<!-- 页面标题 -->
<div class="page-header">
    <h2>
        <?php 
        $iconClass = 'eye';
        if ($action == 'add') $iconClass = 'plus-lg';
        elseif ($action == 'edit') $iconClass = 'pencil';
        ?>
        <i class="bi bi-<?php echo $iconClass; ?>"></i> 
        <?php echo $pageTitle; ?>
    </h2>
</div>

<?php showFlashMessage(); ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" id="paymentForm" class="needs-validation" novalidate>
            <!-- 基本信息 -->
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-info-circle"></i> 收款信息</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">客户 <span class="text-danger">*</span></label>
                    <select class="form-select" name="customer_id" id="customerSelect" required <?php echo $action == 'view' ? 'disabled' : ''; ?>>
                        <option value="">请选择客户</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($payment && $payment['customer_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars_string($c['customer_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">收款日期 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="payment_date" 
                           value="<?php echo $payment ? $payment['payment_date'] : date('Y-m-d'); ?>" 
                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">收款金额 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">¥</span>
                        <input type="number" class="form-control" name="amount" id="paymentAmount" 
                               value="<?php echo $payment ? $payment['amount'] : ''; ?>" 
                               step="0.01" min="0.01" required
                               <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">收款方式 <span class="text-danger">*</span></label>
                    <select class="form-select" name="payment_type" <?php echo $action == 'view' ? 'disabled' : 'required'; ?>>
                        <?php foreach ($paymentTypes as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($payment && $payment['payment_type'] == $key) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-9 mb-3">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-control" name="remark" 
                           value="<?php echo $payment ? htmlspecialchars_string($payment['remark']) : ''; ?>" 
                           placeholder="如：订单尾款、预付款等"
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">订单类型</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_cash_order" id="isCashOrder" 
                               value="1" <?php echo ($payment && $payment['is_cash_order']) ? 'checked' : ''; ?>
                               <?php echo $action == 'view' ? 'disabled' : ''; ?>>
                        <label class="form-check-label" for="isCashOrder">
                            现金订单
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- 关联订单 -->
            <h5 class="border-bottom pb-2 mb-3 mt-4">
                <i class="bi bi-link-45deg"></i> 关联订单
                <?php if ($action != 'view'): ?>
                <button type="button" class="btn btn-sm btn-success float-end" onclick="addOrderLinkRow()">
                    <i class="bi bi-plus-lg"></i> 添加关联
                </button>
                <?php endif; ?>
            </h5>
            
            <div class="table-responsive">
                <table class="table table-bordered" id="orderLinkTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">订单号</th>
                            <th style="width: 25%;">订单金额</th>
                            <th style="width: 25%;">关联金额 <span class="text-danger">*</span></th>
                            <?php if ($action != 'view'): ?>
                            <th style="width: 10%;">操作</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($linkedOrders)): ?>
                            <?php foreach ($linkedOrders as $link): ?>
                            <tr class="order-link-row">
                                <td>
                                    <select class="form-select order-select" name="order_id[]" <?php echo $action == 'view' ? 'disabled' : 'required'; ?>>
                                        <option value="">请选择订单</option>
                                        <?php foreach ($customerOrders as $order): ?>
                                        <option value="<?php echo $order['id']; ?>" 
                                                data-total="<?php echo $order['total_amount']; ?>"
                                                <?php echo $link['order_id'] == $order['id'] ? 'selected' : ''; ?>>
                                            <?php echo $order['order_no']; ?> (¥<?php echo formatMoney($order['total_amount']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="order-total">¥<?php echo formatMoney($link['total_amount'] ?? 0); ?></td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">¥</span>
                                        <input type="number" class="form-control link-amount" name="order_amount[]" 
                                               value="<?php echo $link['link_amount']; ?>" step="0.01" min="0"
                                               <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                                    </div>
                                </td>
                                <?php if ($action != 'view'): ?>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeOrderLinkRow(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php if ($action == 'add'): ?>
                            <tr class="order-link-row">
                                <td>
                                    <select class="form-select order-select" name="order_id[]" required>
                                        <option value="">请选择订单</option>
                                        <?php foreach ($customerOrders as $order): ?>
                                        <option value="<?php echo $order['id']; ?>" data-total="<?php echo $order['total_amount']; ?>">
                                            <?php echo $order['order_no']; ?> (¥<?php echo formatMoney($order['total_amount']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="order-total">-</td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">¥</span>
                                        <input type="number" class="form-control link-amount" name="order_amount[]" step="0.01" min="0" required>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeOrderLinkRow(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="2" class="text-end"><strong>已关联金额：</strong></td>
                            <td><strong id="linkedAmount" class="text-primary">¥0.00</strong></td>
                            <?php if ($action != 'view'): ?>
                            <td></td>
                            <?php endif; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <?php if ($action != 'view'): ?>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>保存
                </button>
                <a href="/payment.php" class="btn btn-outline-secondary">返回列表</a>
            </div>
            <?php else: ?>
            <div class="d-flex gap-2 mt-4">
                <a href="/payment.php" class="btn btn-outline-secondary">返回列表</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
// 客户选择变更时加载订单列表
document.getElementById('customerSelect')?.addEventListener('change', function() {
    const customerId = this.value;
    if (!customerId) return;
    
    // 使用AJAX加载客户订单
    fetch('/ajax/get_customer_orders.php?customer_id=' + customerId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateOrderOptions(data.orders);
            }
        });
});

// 更新订单选项
function updateOrderOptions(orders) {
    const selects = document.querySelectorAll('.order-select');
    selects.forEach(select => {
        const currentValue = select.value;
        select.innerHTML = '<option value="">请选择订单</option>';
        orders.forEach(order => {
            const option = document.createElement('option');
            option.value = order.id;
            option.dataset.total = order.total_amount;
            option.textContent = order.order_no + ' (¥' + parseFloat(order.total_amount).toFixed(2) + ')';
            select.appendChild(option);
        });
        select.value = currentValue;
    });
}

// 添加关联订单行
function addOrderLinkRow() {
    const customerId = document.getElementById('customerSelect')?.value;
    if (!customerId) {
        alert('请先选择客户');
        return;
    }
    
    const tbody = document.querySelector('#orderLinkTable tbody');
    const newRow = document.createElement('tr');
    newRow.className = 'order-link-row';
    newRow.innerHTML = `
        <td>
            <select class="form-select order-select" name="order_id[]" required>
                <option value="">请选择订单</option>
            </select>
        </td>
        <td class="order-total">-</td>
        <td>
            <div class="input-group">
                <span class="input-group-text">¥</span>
                <input type="number" class="form-control link-amount" name="order_amount[]" step="0.01" min="0" required>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeOrderLinkRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    
    // 复制现有选项
    const firstSelect = document.querySelector('.order-select');
    if (firstSelect) {
        newRow.querySelector('.order-select').innerHTML = firstSelect.innerHTML;
    }
    
    bindOrderSelectEvents(newRow);
}

// 删除关联订单行
function removeOrderLinkRow(btn) {
    const rows = document.querySelectorAll('.order-link-row');
    if (rows.length <= 1) {
        // 清空而不是删除最后一行
        const row = btn.closest('tr');
        row.querySelector('.order-select').value = '';
        row.querySelector('.order-total').textContent = '-';
        row.querySelector('.link-amount').value = '';
    } else {
        btn.closest('tr').remove();
    }
    calculateLinkedAmount();
}

// 绑定订单选择事件
function bindOrderSelectEvents(row) {
    const select = row.querySelector('.order-select');
    const amountInput = row.querySelector('.link-amount');
    
    if (select) {
        select.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const total = option.dataset.total || 0;
            row.querySelector('.order-total').textContent = '¥' + parseFloat(total).toFixed(2);
        });
    }
    
    if (amountInput) {
        amountInput.addEventListener('input', calculateLinkedAmount);
    }
}

// 计算已关联金额
function calculateLinkedAmount() {
    let total = 0;
    document.querySelectorAll('.link-amount').forEach(input => {
        const val = parseFloat(input.value);
        if (!isNaN(val)) {
            total += val;
        }
    });
    document.getElementById('linkedAmount').textContent = '¥' + total.toFixed(2);
}

// 初始化
<?php if ($action != 'view'): ?>
document.querySelectorAll('.order-link-row').forEach(row => {
    bindOrderSelectEvents(row);
});
calculateLinkedAmount();

// 表单验证
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const paymentAmount = parseFloat(document.getElementById('paymentAmount').value) || 0;
    let linkedAmount = 0;
    document.querySelectorAll('.link-amount').forEach(input => {
        const val = parseFloat(input.value);
        if (!isNaN(val)) {
            linkedAmount += val;
        }
    });
    
    // 如果有关联订单，验证金额是否匹配
    const hasLinkedOrders = document.querySelectorAll('.order-select option:checked[value!=""]').length > 0;
    if (hasLinkedOrders && Math.abs(paymentAmount - linkedAmount) > 0.01) {
        e.preventDefault();
        alert('关联订单金额总和必须等于收款金额');
        return;
    }
    
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
