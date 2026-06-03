<?php
/**
 * 订单管理 - 新增/编辑/查看/删除
 */
require_once __DIR__ . '/config/functions.php';

$action = input('action', 'add', 'get');
$id = intval(input('id', 0, 'get'));

// 删除操作
if ($action == 'delete' && $id > 0) {
    requireAdmin();
    
    $order = fetchOne("SELECT * FROM order_main WHERE id = ?", [$id]);
    if (!$order) {
        setFlashMessage('error', '订单不存在');
        redirect('/order.php');
    }
    
    try {
        beginTransaction();
        execute("DELETE FROM order_main WHERE id = ?", [$id]);
        commit();
        setFlashMessage('success', '订单删除成功');
    } catch (Exception $e) {
        rollback();
        setFlashMessage('error', '删除失败：' . $e->getMessage());
    }
    
    redirect('/order.php');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireAdmin();
    
    $orderData = [
        'order_no' => trim($_POST['order_no'] ?? ''),
        'customer_id' => intval($_POST['customer_id'] ?? 0),
        'order_date' => $_POST['order_date'] ?? '',
        'ship_date' => $_POST['ship_date'] ?? '',
        'status' => intval($_POST['status'] ?? 1),
        'origin' => trim($_POST['origin'] ?? ''),
        'remark' => trim($_POST['remark'] ?? '')
    ];
    
    // 数据验证
    $errors = [];
    if (empty($orderData['order_no'])) {
        $errors[] = '订单号不能为空';
    }
    if ($orderData['customer_id'] <= 0) {
        $errors[] = '请选择客户';
    }
    if (empty($orderData['order_date'])) {
        $errors[] = '请选择下单日期';
    }
    if (empty($orderData['ship_date'])) {
        $errors[] = '请选择出货日期';
    }
    
    // 验证订单号唯一性
    $checkSql = "SELECT id FROM order_main WHERE order_no = ?" . ($action == 'edit' && $id > 0 ? " AND id != ?" : "");
    $checkParams = [$orderData['order_no']];
    if ($action == 'edit' && $id > 0) {
        $checkParams[] = $id;
    }
    $existing = fetchOne($checkSql, $checkParams);
    if ($existing) {
        $errors[] = '订单号已存在';
    }
    
    // 处理订单明细
    $details = [];
    $orderTotalAmount = 0; // 订单总金额
    if (isset($_POST['product_model']) && is_array($_POST['product_model'])) {
        foreach ($_POST['product_model'] as $index => $productModel) {
            if (!empty($productModel)) {
                $quantity = intval($_POST['quantity'][$index] ?? 0);
                $unitPrice = floatval($_POST['unit_price'][$index] ?? 0);
                $amount = $quantity * $unitPrice; // 后端重新计算金额
                
                // 数据校验：数量和单价必须为正数
                if ($quantity <= 0) {
                    $errors[] = '第' . ($index + 1) . '行：数量必须大于0';
                }
                if ($unitPrice < 0) {
                    $errors[] = '第' . ($index + 1) . '行：单价不能为负数';
                }
                
                $details[] = [
                    'product_model' => trim($productModel),
                    'quantity' => $quantity,
                    'unit' => trim($_POST['unit'][$index] ?? '个'),
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                    'color' => trim($_POST['color'][$index] ?? ''),
                    'ratio' => trim($_POST['ratio'][$index] ?? ''),
                    'tax_included' => isset($_POST['tax_included'][$index]) ? 1 : 0,
                    'express' => trim($_POST['express'][$index] ?? ''),
                    'remark' => trim($_POST['detail_remark'][$index] ?? '')
                ];
                
                $orderTotalAmount += $amount;
            }
        }
    }
    
    if (empty($details)) {
        $errors[] = '请至少添加一条订单明细';
    }
    
    // 校验订单总金额
    if ($orderTotalAmount <= 0) {
        $errors[] = '订单总金额必须大于0';
    }
    
    // 处理收件人信息
    $recipients = [];
    if (isset($_POST['recipient_name']) && is_array($_POST['recipient_name'])) {
        foreach ($_POST['recipient_name'] as $index => $recipientName) {
            if (!empty($recipientName)) {
                $recipients[] = [
                    'recipient_name' => trim($recipientName),
                    'recipient_phone' => trim($_POST['recipient_phone'][$index] ?? ''),
                    'recipient_address' => trim($_POST['recipient_address'][$index] ?? ''),
                    'is_default' => ($_POST['default_recipient'] ?? '') == $index ? 1 : 0
                ];
            }
        }
    }
    
    if (empty($errors)) {
        try {
            beginTransaction();
            
            if ($action == 'edit' && $id > 0) {
                // 编辑模式：检查订单是否已结清，已结清订单不允许修改明细
                $existingOrder = fetchOne("SELECT payment_status FROM order_main WHERE id = ?", [$id]);
                if ($existingOrder && $existingOrder['payment_status'] == 3) {
                    throw new Exception('已结清的订单不允许修改订单明细和金额');
                }
                
                // 更新订单主表（包含订单总金额）
                $sql = "UPDATE order_main SET 
                        order_no = ?, customer_id = ?, order_date = ?, ship_date = ?, status = ?, 
                        origin = ?, order_total_amount = ?, remark = ?
                        WHERE id = ?";
                $params = [
                    $orderData['order_no'],
                    $orderData['customer_id'],
                    $orderData['order_date'],
                    $orderData['ship_date'],
                    $orderData['status'],
                    $orderData['origin'],
                    $orderTotalAmount, // 重新计算的订单总金额
                    $orderData['remark'],
                    $id
                ];
                execute($sql, $params);
                
                // 删除旧明细和收件人
                execute("DELETE FROM order_detail WHERE order_id = ?", [$id]);
                execute("DELETE FROM order_recipient WHERE order_id = ?", [$id]);
                $orderId = $id;
            } else {
                // 新增订单主表（包含订单总金额）
                $sql = "INSERT INTO order_main (order_no, customer_id, order_date, ship_date, status, origin, order_total_amount, remark) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [
                    $orderData['order_no'],
                    $orderData['customer_id'],
                    $orderData['order_date'],
                    $orderData['ship_date'],
                    $orderData['status'],
                    $orderData['origin'],
                    $orderTotalAmount,
                    $orderData['remark']
                ];
                $orderId = insert($sql, $params);
            }
            
            // 插入订单明细
            foreach ($details as $detail) {
                $amount = $detail['quantity'] * $detail['unit_price'];
                // 检查数据库是否有unit字段
                try {
                    $detailSql = "INSERT INTO order_detail (order_id, product_model, quantity, unit, unit_price, amount, color, ratio, tax_included, express, remark) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    insert($detailSql, [
                        $orderId,
                        $detail['product_model'],
                        $detail['quantity'],
                        $detail['unit'],
                        $detail['unit_price'],
                        $amount,
                        $detail['color'],
                        $detail['ratio'],
                        $detail['tax_included'],
                        $detail['express'],
                        $detail['remark']
                    ]);
                } catch (Exception $e) {
                    // 如果unit字段不存在，使用旧版SQL
                    $detailSql = "INSERT INTO order_detail (order_id, product_model, quantity, unit_price, amount, color, ratio, tax_included, express, remark) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    insert($detailSql, [
                        $orderId,
                        $detail['product_model'],
                        $detail['quantity'],
                        $detail['unit_price'],
                        $amount,
                        $detail['color'],
                        $detail['ratio'],
                        $detail['tax_included'],
                        $detail['express'],
                        $detail['remark']
                    ]);
                }
            }
            
            // 插入收件人信息
            foreach ($recipients as $index => $recipient) {
                $recipientSql = "INSERT INTO order_recipient (order_id, recipient_name, recipient_phone, recipient_address, is_default, sort_order) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                insert($recipientSql, [
                    $orderId,
                    $recipient['recipient_name'],
                    $recipient['recipient_phone'],
                    $recipient['recipient_address'],
                    $recipient['is_default'],
                    $index
                ]);
            }
            
            commit();
            setFlashMessage('success', $action == 'edit' ? '订单更新成功' : '订单添加成功');
            redirect('/order.php');
        } catch (Exception $e) {
            rollback();
            setFlashMessage('error', '保存失败：' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
    
    if ($action == 'edit') {
        redirect('/order_form.php?id=' . $id . '&action=edit');
    } else {
        redirect('/order_form.php?action=add');
    }
}

// 获取订单数据（编辑/查看/复制模式）
$order = null;
$orderDetails = [];
$orderRecipients = [];
if (($action == 'edit' || $action == 'view' || $action == 'copy') && $id > 0) {
    $order = fetchOne("SELECT * FROM order_main WHERE id = ?", [$id]);
    if (!$order) {
        setFlashMessage('error', '订单不存在');
        redirect('/order.php');
    }
    $orderDetails = fetchAll("SELECT * FROM order_detail WHERE order_id = ?", [$id]);
    $orderRecipients = fetchAll("SELECT * FROM order_recipient WHERE order_id = ? ORDER BY sort_order", [$id]);
    
    // 复制模式：清空ID，生成新订单号，状态设为待生产
    if ($action == 'copy') {
        $originalOrderNo = $order['order_no']; // 保存原订单号
        $order['id'] = 0;
        $order['order_no'] = 'ORD' . date('Ymd') . strtoupper(substr(str_shuffle('0123456789'), 0, 4));
        $order['order_date'] = date('Y-m-d');
        $order['ship_date'] = '';
        $order['status'] = 1;
        $order['remark'] = '复制自 ' . $originalOrderNo;
    }
}

// 获取客户列表
$customers = fetchAll("SELECT id, customer_name FROM customer ORDER BY customer_name");

// 页面标题
$pageTitle = $action == 'add' ? '新增订单' : ($action == 'edit' ? '编辑订单' : ($action == 'copy' ? '复制订单' : '订单详情'));
require_once __DIR__ . '/includes/header.php';

// 生成默认订单号
$defaultOrderNo = '';
if ($action == 'add') {
    $defaultOrderNo = 'ORD' . date('Ymd') . strtoupper(substr(str_shuffle('0123456789'), 0, 4));
}
?>

<!-- 页面标题 -->
<div class="page-header">
    <h2>
        <?php 
        $iconClass = 'eye';
        if ($action == 'add') $iconClass = 'plus-lg';
        elseif ($action == 'edit') $iconClass = 'pencil';
        elseif ($action == 'copy') $iconClass = 'files';
        ?>
        <i class="bi bi-<?php echo $iconClass; ?>"></i> 
        <?php echo $pageTitle; ?>
    </h2>
</div>

<style>
/* 客户选择器样式 */
.customer-select-wrapper {
    position: relative;
}
.customer-select-wrapper .customer-search-input {
    width: 100%;
    padding-right: 30px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 16px;
}
.customer-select-wrapper .customer-select {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    margin-top: 2px;
    display: none;
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.customer-select-wrapper .customer-select:focus {
    display: block;
}
.customer-select-wrapper .customer-select option {
    padding: 8px 12px;
    cursor: pointer;
}
.customer-select-wrapper .customer-select option:hover {
    background-color: #f8f9fa;
}
</style>

<?php showFlashMessage(); ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" id="orderForm" class="needs-validation" novalidate>
            <!-- 订单基本信息 -->
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-info-circle"></i> 基本信息</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="order_no" class="form-label">订单号 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="order_no" name="order_no" 
                           value="<?php echo $order ? $order['order_no'] : $defaultOrderNo; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="customer_id" class="form-label">客户 <span class="text-danger">*</span></label>
                    <div class="customer-select-wrapper">
                        <input type="text" class="form-control customer-search-input" placeholder="🔍 搜索或选择客户..." autocomplete="off">
                        <select class="form-select customer-select" id="customer_id" name="customer_id" <?php echo $action == 'view' ? 'disabled' : 'required'; ?>>
                            <option value="">请选择客户</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($order && $order['customer_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars_string($c['customer_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="order_date" class="form-label">下单日期 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="order_date" name="order_date" 
                           value="<?php echo $order ? $order['order_date'] : date('Y-m-d'); ?>" 
                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="ship_date" class="form-label">出货日期 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="ship_date" name="ship_date" 
                           value="<?php echo $order ? $order['ship_date'] : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">订单状态</label>
                    <select class="form-select" id="status" name="status" <?php echo $action == 'view' ? 'disabled' : ''; ?>>
                        <option value="1" <?php echo ($order && $order['status'] == 1) ? 'selected' : ''; ?>>待生产</option>
                        <option value="2" <?php echo ($order && $order['status'] == 2) ? 'selected' : ''; ?>>已发货</option>
                        <option value="3" <?php echo ($order && $order['status'] == 3) ? 'selected' : ''; ?>>已完成</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="origin" class="form-label">产地</label>
                    <select class="form-select" id="origin" name="origin" <?php echo $action == 'view' ? 'disabled' : ''; ?>>
                        <option value="">请选择产地</option>
                        <option value="惠州" <?php echo ($order && $order['origin'] == '惠州') ? 'selected' : ''; ?>>惠州</option>
                        <option value="东莞" <?php echo ($order && $order['origin'] == '东莞') ? 'selected' : ''; ?>>东莞</option>
                        <option value="湖南" <?php echo ($order && $order['origin'] == '湖南') ? 'selected' : ''; ?>>湖南</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="remark" class="form-label">订单备注</label>
                    <input type="text" class="form-control" id="remark" name="remark" 
                           value="<?php echo $order ? htmlspecialchars_string($order['remark']) : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
            </div>
            
            <!-- 收件人信息 -->
            <h5 class="border-bottom pb-2 mb-3 mt-4">
                <i class="bi bi-truck"></i> 收件人信息
                <?php if ($action != 'view'): ?>
                <button type="button" class="btn btn-sm btn-success float-end" onclick="addRecipientRow()">
                    <i class="bi bi-plus-lg"></i> 添加收件人
                </button>
                <?php endif; ?>
            </h5>
            
            <div class="table-responsive">
                <table class="table table-bordered" id="recipientTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">默认</th>
                            <th style="width: 20%;">收件人姓名 <span class="text-danger">*</span></th>
                            <th style="width: 15%;">联系电话</th>
                            <th style="width: 50%;">收件地址 <span class="text-danger">*</span></th>
                            <?php if ($action != 'view'): ?>
                            <th style="width: 10%;">操作</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orderRecipients)): ?>
                            <?php foreach ($orderRecipients as $index => $recipient): ?>
                            <tr class="recipient-row">
                                <td class="text-center">
                                    <input type="radio" name="default_recipient" value="<?php echo $index; ?>" 
                                           <?php echo $recipient['is_default'] ? 'checked' : ''; ?> 
                                           <?php echo $action == 'view' ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="recipient_name[]" 
                                           value="<?php echo htmlspecialchars_string($recipient['recipient_name']); ?>" 
                                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="recipient_phone[]" 
                                           value="<?php echo htmlspecialchars_string($recipient['recipient_phone']); ?>" 
                                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="recipient_address[]" 
                                           value="<?php echo htmlspecialchars_string($recipient['recipient_address']); ?>" 
                                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                                </td>
                                <?php if ($action != 'view'): ?>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeRecipientRow(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php if ($action == 'add'): ?>
                            <tr class="recipient-row">
                                <td class="text-center">
                                    <input type="radio" name="default_recipient" value="0" checked>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="recipient_name[]" required>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="recipient_phone[]">
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="recipient_address[]" required>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeRecipientRow(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 订单明细 -->
            <h5 class="border-bottom pb-2 mb-3 mt-4">
                <i class="bi bi-list-ul"></i> 订单明细
                <?php if ($action != 'view'): ?>
                <button type="button" class="btn btn-sm btn-success float-end" onclick="addDetailRow()">
                    <i class="bi bi-plus-lg"></i> 添加明细
                </button>
                <?php endif; ?>
            </h5>
            
            <div class="table-responsive">
                <table class="table table-bordered" id="detailTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 15%;">产品型号 <span class="text-danger">*</span></th>
                            <th style="width: 13%;">数量/单位 <span class="text-danger">*</span></th>
                            <th style="width: 10%;">单价 <span class="text-danger">*</span></th>
                            <th style="width: 10%;">金额</th>
                            <th style="width: 10%;">颜色</th>
                            <th style="width: 8%;">比例</th>
                            <th style="width: 8%;">含税</th>
                            <th style="width: 12%;">快递</th>
                            <th style="width: 12%;">备注</th>
                            <?php if ($action != 'view'): ?>
                            <th style="width: 5%;">操作</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($action == 'view' || $action == 'edit' || $action == 'copy'): ?>
                            <?php foreach ($orderDetails as $index => $detail): ?>
                            <tr class="detail-row">
                                <td>
                                    <input type="text" class="form-control" name="product_model[]" 
                                           value="<?php echo htmlspecialchars_string($detail['product_model']); ?>" 
                                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" class="form-control quantity" name="quantity[]" 
                                               value="<?php echo $detail['quantity']; ?>" min="1" 
                                               <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                                        <input type="text" class="form-control" name="unit[]" 
                                               value="<?php echo htmlspecialchars_string($detail['unit'] ?? '个'); ?>" 
                                               placeholder="单位"
                                               style="width: 45px; padding: 0.375rem 0.5rem;"
                                               <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" class="form-control unit-price" name="unit_price[]" 
                                           value="<?php echo $detail['unit_price']; ?>" step="0.01" min="0" 
                                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                                </td>
                                <td>
                                    <input type="text" class="form-control amount" value="<?php echo number_format($detail['amount'], 2, '.', ''); ?>" readonly>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="color[]" 
                                           value="<?php echo htmlspecialchars_string($detail['color'] ?? ''); ?>" 
                                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="ratio[]" 
                                           value="<?php echo htmlspecialchars_string($detail['ratio'] ?? ''); ?>" 
                                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="tax_included[]" value="1" 
                                           <?php echo ($detail['tax_included'] ?? 0) ? 'checked' : ''; ?> 
                                           <?php echo $action == 'view' ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="express[]" 
                                           value="<?php echo htmlspecialchars_string($detail['express'] ?? ''); ?>" 
                                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="detail_remark[]" 
                                           value="<?php echo htmlspecialchars_string($detail['remark']); ?>" 
                                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                                </td>
                                <?php if ($action != 'view'): ?>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeDetailRow(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if ($action == 'add'): ?>
                        <tr class="detail-row">
                            <td>
                                <input type="text" class="form-control" name="product_model[]" required>
                            </td>
                            <td>
                                <div class="input-group">
                                    <input type="number" class="form-control quantity" name="quantity[]" value="1" min="1" required>
                                    <input type="text" class="form-control" name="unit[]" value="个" placeholder="单位" style="width: 45px; padding: 0.375rem 0.5rem;">
                                </div>
                            </td>
                            <td>
                                <input type="number" class="form-control unit-price" name="unit_price[]" value="0.00" step="0.01" min="0" required>
                            </td>
                            <td>
                                <input type="text" class="form-control amount" value="0.00" readonly>
                            </td>
                            <td>
                                <input type="text" class="form-control" name="color[]">
                            </td>
                            <td>
                                <input type="text" class="form-control" name="ratio[]">
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="tax_included[]" value="1">
                            </td>
                            <td>
                                <input type="text" class="form-control" name="express[]">
                            </td>
                            <td>
                                <input type="text" class="form-control" name="detail_remark[]">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeDetailRow(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="3" class="text-end"><strong>合计：</strong></td>
                            <td><strong id="totalAmount" class="text-primary">¥0.00</strong></td>
                            <td colspan="<?php echo $action == 'view' ? 5 : 6; ?>"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- 收款记录（仅查看模式显示） -->
            <?php if ($action == 'view' && $order): 
                // 获取订单收款记录
                $paymentRecords = fetchAll(
                    "SELECT pr.*, opl.link_amount
                     FROM payment_record pr
                     INNER JOIN order_payment_link opl ON pr.id = opl.payment_id
                     WHERE opl.order_id = ?
                     ORDER BY pr.payment_date DESC",
                    [$order['id']]
                );
                
                // 使用订单主表的 order_total_amount 字段
                $orderTotal = $order['order_total_amount'] ?? 0;
                $paidAmount = $order['paid_amount'] ?? 0;
                $unpaidAmount = $orderTotal - $paidAmount;
                
                // 收款状态样式
                $paymentStatusClass = [
                    1 => 'bg-danger',    // 未付款
                    2 => 'bg-warning',   // 部分付款
                    3 => 'bg-success'    // 已结清
                ];
                $paymentStatusText = [
                    1 => '未付款',
                    2 => '部分付款',
                    3 => '已结清'
                ];
            ?>
            <h5 class="border-bottom pb-2 mb-3 mt-4">
                <i class="bi bi-cash-coin"></i> 收款记录
                <span class="badge <?php echo $paymentStatusClass[$order['payment_status'] ?? 1]; ?> ms-2">
                    <?php echo $paymentStatusText[$order['payment_status'] ?? 1]; ?>
                </span>
            </h5>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">订单金额</h6>
                            <h4 class="text-primary mb-0">¥<?php echo formatMoney($orderTotal); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">已收金额</h6>
                            <h4 class="text-success mb-0">¥<?php echo formatMoney($paidAmount); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">未收金额</h6>
                            <h4 class="text-danger mb-0">¥<?php echo formatMoney($unpaidAmount); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">收款进度</h6>
                            <h4 class="text-info mb-0">
                                <?php echo $orderTotal > 0 ? round($paidAmount / $orderTotal * 100, 1) : 0; ?>%
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($paymentRecords)): ?>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>收款日期</th>
                            <th>收款方式</th>
                            <th class="text-end">本次收款</th>
                            <th>备注</th>
                            <th>操作人</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentRecords as $record): 
                            $paymentTypes = [1 => '现金', 2 => '银行转账', 3 => '微信', 4 => '支付宝'];
                        ?>
                        <tr>
                            <td><?php echo formatDate($record['payment_date']); ?></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $paymentTypes[$record['payment_type']] ?? '未知'; ?></span>
                            </td>
                            <td class="text-end text-success fw-bold">¥<?php echo formatMoney($record['link_amount']); ?></td>
                            <td><small><?php echo htmlspecialchars_string($record['remark'] ?: '-'); ?></small></td>
                            <td><small><?php echo htmlspecialchars_string($record['operator'] ?: '-'); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>暂无收款记录
            </div>
            <?php endif; ?>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">创建时间</label>
                    <input type="text" class="form-control" value="<?php echo formatDate($order['create_time'], 'Y-m-d H:i:s'); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">更新时间</label>
                    <input type="text" class="form-control" value="<?php echo formatDate($order['update_time'], 'Y-m-d H:i:s'); ?>" readonly>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="d-flex gap-2">
                <?php if ($action != 'view'): ?>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> 保存
                </button>
                <?php endif; ?>
                <a href="/order.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> 返回列表
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// 添加收件人行
function addRecipientRow() {
    const tbody = document.querySelector('#recipientTable tbody');
    const rowCount = tbody.querySelectorAll('.recipient-row').length;
    const newRow = document.createElement('tr');
    newRow.className = 'recipient-row';
    newRow.innerHTML = `
        <td class="text-center">
            <input type="radio" name="default_recipient" value="${rowCount}">
        </td>
        <td>
            <input type="text" class="form-control" name="recipient_name[]" required>
        </td>
        <td>
            <input type="text" class="form-control" name="recipient_phone[]">
        </td>
        <td>
            <input type="text" class="form-control" name="recipient_address[]" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeRecipientRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    
    // 如果没有选中的默认收件人，选中第一个
    const checkedRadio = tbody.querySelector('input[name="default_recipient"]:checked');
    if (!checkedRadio) {
        tbody.querySelector('input[name="default_recipient"]').checked = true;
    }
}

// 删除收件人行
function removeRecipientRow(btn) {
    const rows = document.querySelectorAll('.recipient-row');
    if (rows.length <= 1) {
        alert('至少需要保留一个收件人');
        return;
    }
    
    const row = btn.closest('tr');
    const radio = row.querySelector('input[name="default_recipient"]');
    const wasChecked = radio.checked;
    
    row.remove();
    
    // 如果删除的是默认收件人，重新设置默认
    if (wasChecked) {
        const firstRadio = document.querySelector('.recipient-row input[name="default_recipient"]');
        if (firstRadio) {
            firstRadio.checked = true;
        }
    }
    
    // 重新编号
    document.querySelectorAll('.recipient-row').forEach((r, i) => {
        r.querySelector('input[name="default_recipient"]').value = i;
    });
}

// 添加明细行
function addDetailRow() {
    const tbody = document.querySelector('#detailTable tbody');
    const newRow = document.createElement('tr');
    newRow.className = 'detail-row';
    newRow.innerHTML = `
        <td>
            <input type="text" class="form-control" name="product_model[]" required>
        </td>
        <td>
            <div class="input-group">
                <input type="number" class="form-control quantity" name="quantity[]" value="1" min="1" required>
                <input type="text" class="form-control" name="unit[]" value="个" placeholder="单位" style="width: 45px; padding: 0.375rem 0.5rem;">
            </div>
        </td>
        <td>
            <input type="number" class="form-control unit-price" name="unit_price[]" value="0.00" step="0.01" min="0" required>
        </td>
        <td>
            <input type="text" class="form-control amount" value="0.00" readonly>
        </td>
        <td>
            <input type="text" class="form-control" name="color[]">
        </td>
        <td>
            <input type="text" class="form-control" name="ratio[]">
        </td>
        <td class="text-center">
            <input type="checkbox" name="tax_included[]" value="1">
        </td>
        <td>
            <input type="text" class="form-control" name="express[]">
        </td>
        <td>
            <input type="text" class="form-control" name="detail_remark[]">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeDetailRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    bindCalculationEvents(newRow);
    calculateTotal();
}

// 删除明细行
function removeDetailRow(btn) {
    const rows = document.querySelectorAll('.detail-row');
    if (rows.length <= 1) {
        alert('至少需要保留一条明细');
        return;
    }
    btn.closest('tr').remove();
    calculateTotal();
}

// 计算金额
function calculateAmount(row) {
    const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
    const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
    const amount = quantity * unitPrice;
    // 金额显示不带千分位，避免parseFloat解析错误
    row.querySelector('.amount').value = amount.toFixed(2);
    calculateTotal();
}

// 计算总金额
function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.amount').forEach(input => {
        const val = parseFloat(input.value);
        if (!isNaN(val)) {
            total += val;
        }
    });
    // 格式化为千分位
    const formatted = total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('totalAmount').textContent = '¥' + formatted;
}

// 绑定计算事件
function bindCalculationEvents(row) {
    const quantityInput = row.querySelector('.quantity');
    const unitPriceInput = row.querySelector('.unit-price');
    
    if (quantityInput) {
        quantityInput.addEventListener('input', () => calculateAmount(row));
    }
    if (unitPriceInput) {
        unitPriceInput.addEventListener('input', () => calculateAmount(row));
    }
}

// 初始化
<?php if ($action != 'view'): ?>
document.querySelectorAll('.detail-row').forEach(row => {
    bindCalculationEvents(row);
});
calculateTotal();

// 客户搜索功能
const customerSelect = document.getElementById('customer_id');
const searchInput = document.querySelector('.customer-search-input');

if (customerSelect && searchInput) {
    // 保存原始选项
    const originalOptions = Array.from(customerSelect.options);
    
    // 如果有选中值，显示在搜索框
    if (customerSelect.value) {
        const selectedOption = originalOptions.find(opt => opt.value === customerSelect.value);
        if (selectedOption) {
            searchInput.value = selectedOption.text;
        }
    }
    
    // 搜索框获得焦点时展开下拉框
    searchInput.addEventListener('focus', function() {
        customerSelect.size = Math.min(originalOptions.length, 10);
        customerSelect.style.height = 'auto';
        customerSelect.style.display = 'block';
    });
    
    // 搜索功能
    searchInput.addEventListener('input', function() {
        const keyword = this.value.toLowerCase().trim();
        
        // 清空当前选项
        customerSelect.innerHTML = '';
        
        // 添加默认选项
        customerSelect.appendChild(originalOptions[0].cloneNode(true));
        
        // 过滤并添加匹配的选项
        let matchCount = 0;
        for (let i = 1; i < originalOptions.length; i++) {
            const optionText = originalOptions[i].text.toLowerCase();
            if (keyword === '' || optionText.includes(keyword)) {
                customerSelect.appendChild(originalOptions[i].cloneNode(true));
                matchCount++;
            }
        }
        
        // 自动展开下拉框
        if (matchCount > 0) {
            customerSelect.size = Math.min(matchCount + 1, 10);
            customerSelect.style.height = 'auto';
            customerSelect.style.display = 'block';
        }
    });
    
    // 选择客户
    customerSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.value) {
            searchInput.value = selectedOption.text;
        } else {
            searchInput.value = '';
        }
        this.size = 0;
        this.style.display = 'none';
    });
    
    // 点击外部关闭下拉框
    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.customer-select-wrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            customerSelect.size = 0;
            customerSelect.style.display = 'none';
        }
    });
    
    // 点击搜索框时阻止事件冒泡
    searchInput.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

// 表单验证
document.getElementById('orderForm').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
