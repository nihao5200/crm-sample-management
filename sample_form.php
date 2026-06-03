<?php
/**
 * 样品记录管理 - 新增/编辑/查看/删除
 */
require_once __DIR__ . '/config/functions.php';

$action = input('action', 'add', 'get');
$id = intval(input('id', 0, 'get'));

// 删除操作
if ($action == 'delete' && $id > 0) {
    requireAdmin();
    
    $sample = fetchOne("SELECT * FROM sample_record WHERE id = ?", [$id]);
    if (!$sample) {
        setFlashMessage('error', '样品记录不存在');
        redirect('/sample.php');
    }
    
    try {
        execute("DELETE FROM sample_record WHERE id = ?", [$id]);
        setFlashMessage('success', '样品记录删除成功');
    } catch (Exception $e) {
        setFlashMessage('error', '删除失败：' . $e->getMessage());
    }
    
    redirect('/sample.php');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireAdmin();
    
    // 处理跟进记录 - 如果有新记录则追加
    $existingFollowUp = '';
    if ($action == 'edit' && $id > 0) {
        $existingSample = fetchOne("SELECT follow_up FROM sample_record WHERE id = ?", [$id]);
        $existingFollowUp = $existingSample['follow_up'] ?? '';
    }
    
    $newFollowUp = trim($_POST['new_follow_up'] ?? '');
    $followUp = $existingFollowUp;
    if (!empty($newFollowUp)) {
        $dateStr = date('Y-m-d H:i');
        $operator = $_SESSION['admin_username'] ?? 'admin';
        $newRecord = "[{$dateStr}] {$operator}：{$newFollowUp}";
        if (!empty($followUp)) {
            $followUp = $newRecord . "\n" . $followUp;
        } else {
            $followUp = $newRecord;
        }
    }
    
    $data = [
        'sample_no' => trim($_POST['sample_no'] ?? ''),
        'customer_id' => intval($_POST['customer_id'] ?? 0),
        'product_model' => trim($_POST['product_model'] ?? ''),
        'quantity' => intval($_POST['quantity'] ?? 1),
        'unit' => trim($_POST['unit'] ?? '个'),
        'unit_price' => floatval($_POST['unit_price'] ?? 0),
        'color' => trim($_POST['color'] ?? ''),
        'ratio' => trim($_POST['ratio'] ?? ''),
        'tax_included' => isset($_POST['tax_included']) ? 1 : 0,
        'send_date' => $_POST['send_date'] ?? '',
        'sample_status' => intval($_POST['sample_status'] ?? 1),
        'follow_up' => $followUp,
        'remark' => trim($_POST['remark'] ?? '')
    ];
    
    // 数据验证
    $errors = [];
    if (empty($data['sample_no'])) {
        $errors[] = '样品号不能为空';
    }
    if ($data['customer_id'] <= 0) {
        $errors[] = '请选择客户';
    }
    if (empty($data['product_model'])) {
        $errors[] = '产品型号不能为空';
    }
    if (empty($data['send_date'])) {
        $errors[] = '请选择送样日期';
    }
    
    // 验证样品号唯一性
    $checkSql = "SELECT id FROM sample_record WHERE sample_no = ?" . ($action == 'edit' && $id > 0 ? " AND id != ?" : "");
    $checkParams = [$data['sample_no']];
    if ($action == 'edit' && $id > 0) {
        $checkParams[] = $id;
    }
    $existing = fetchOne($checkSql, $checkParams);
    if ($existing) {
        $errors[] = '样品号已存在';
    }
    
    if (empty($errors)) {
        if ($action == 'edit' && $id > 0) {
            // 更新
            $sql = "UPDATE sample_record SET 
                    sample_no = ?, customer_id = ?, product_model = ?, quantity = ?, unit = ?, 
                    unit_price = ?, color = ?, ratio = ?, tax_included = ?, send_date = ?, 
                    sample_status = ?, follow_up = ?, remark = ?
                    WHERE id = ?";
            $params = array_values($data);
            $params[] = $id;
            
            try {
                execute($sql, $params);
                setFlashMessage('success', '样品记录更新成功');
                redirect('/sample.php');
            } catch (Exception $e) {
                setFlashMessage('error', '更新失败：' . $e->getMessage());
            }
        } else {
            // 新增
            $sql = "INSERT INTO sample_record (sample_no, customer_id, product_model, quantity, unit, unit_price, color, ratio, tax_included, send_date, sample_status, follow_up, remark) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            try {
                insert($sql, array_values($data));
                setFlashMessage('success', '样品记录添加成功');
                redirect('/sample.php');
            } catch (Exception $e) {
                setFlashMessage('error', '添加失败：' . $e->getMessage());
            }
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
    
    if ($action == 'edit') {
        redirect('/sample_form.php?id=' . $id . '&action=edit');
    } else {
        redirect('/sample_form.php?action=add');
    }
}

// 获取样品数据（编辑/查看模式）
$sample = null;
if (($action == 'edit' || $action == 'view') && $id > 0) {
    $sample = fetchOne("SELECT * FROM sample_record WHERE id = ?", [$id]);
    if (!$sample) {
        setFlashMessage('error', '样品记录不存在');
        redirect('/sample.php');
    }
}

// 获取客户列表
$customers = fetchAll("SELECT id, customer_name FROM customer ORDER BY customer_name");

// 页面标题
$pageTitle = $action == 'add' ? '新增样品' : ($action == 'edit' ? '编辑样品' : '样品详情');
require_once __DIR__ . '/includes/header.php';

// 生成默认样品号
$defaultSampleNo = '';
if ($action == 'add') {
    $defaultSampleNo = 'SMP' . date('Ymd') . strtoupper(substr(str_shuffle('0123456789'), 0, 4));
}
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
            <!-- 基本信息 -->
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-info-circle"></i> 基本信息</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="sample_no" class="form-label">样品号 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="sample_no" name="sample_no" 
                           value="<?php echo $sample ? $sample['sample_no'] : $defaultSampleNo; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="customer_id" class="form-label">客户 <span class="text-danger">*</span></label>
                    <select class="form-select" id="customer_id" name="customer_id" <?php echo $action == 'view' ? 'disabled' : 'required'; ?>>
                        <option value="">请选择客户</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($sample && $sample['customer_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars_string($c['customer_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="send_date" class="form-label">送样日期 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="send_date" name="send_date" 
                           value="<?php echo $sample ? $sample['send_date'] : date('Y-m-d'); ?>" 
                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="sample_status" class="form-label">样品状态</label>
                    <select class="form-select" id="sample_status" name="sample_status" <?php echo $action == 'view' ? 'disabled' : ''; ?>>
                        <option value="1" <?php echo ($sample && $sample['sample_status'] == 1) ? 'selected' : ''; ?>>待确认</option>
                        <option value="2" <?php echo ($sample && $sample['sample_status'] == 2) ? 'selected' : ''; ?>>已确认</option>
                        <option value="3" <?php echo ($sample && $sample['sample_status'] == 3) ? 'selected' : ''; ?>>已退回</option>
                        <option value="4" <?php echo ($sample && $sample['sample_status'] == 4) ? 'selected' : ''; ?>>已量产</option>
                    </select>
                </div>
            </div>
            
            <!-- 产品明细信息 -->
            <h5 class="border-bottom pb-2 mb-3 mt-4"><i class="bi bi-box-seam"></i> 产品明细</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="product_model" class="form-label">产品型号 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="product_model" name="product_model" 
                           value="<?php echo $sample ? htmlspecialchars_string($sample['product_model']) : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : 'required'; ?>>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="unit" class="form-label">单位</label>
                    <input type="text" class="form-control" id="unit" name="unit" 
                           value="<?php echo $sample ? htmlspecialchars_string($sample['unit'] ?? '个') : '个'; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="unit_price" class="form-label">单价</label>
                    <input type="number" class="form-control" id="unit_price" name="unit_price" 
                           value="<?php echo $sample ? ($sample['unit_price'] ?? 0) : 0; ?>" step="0.01" min="0" 
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="color" class="form-label">颜色</label>
                    <input type="text" class="form-control" id="color" name="color" 
                           value="<?php echo $sample ? htmlspecialchars_string($sample['color'] ?? '') : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="ratio" class="form-label">比例</label>
                    <input type="text" class="form-control" id="ratio" name="ratio" 
                           value="<?php echo $sample ? htmlspecialchars_string($sample['ratio'] ?? '') : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label d-block">含税</label>
                    <div class="form-check form-check-inline mt-2">
                        <input type="checkbox" class="form-check-input" id="tax_included" name="tax_included" value="1" 
                               <?php echo ($sample && ($sample['tax_included'] ?? 0)) ? 'checked' : ''; ?> 
                               <?php echo $action == 'view' ? 'disabled' : ''; ?>>
                        <label class="form-check-label" for="tax_included">含税</label>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="remark" class="form-label">备注</label>
                    <input type="text" class="form-control" id="remark" name="remark" 
                           value="<?php echo $sample ? htmlspecialchars_string($sample['remark']) : ''; ?>" 
                           <?php echo $action == 'view' ? 'readonly' : ''; ?>>
                </div>
            </div>
            
            <!-- 跟进记录 - 留言板样式 -->
            <h5 class="border-bottom pb-2 mb-3 mt-4"><i class="bi bi-chat-dots"></i> 跟进记录</h5>
            
            <?php if ($action != 'view'): ?>
            <!-- 添加新记录 -->
            <div class="mb-3">
                <label for="new_follow_up" class="form-label">添加新记录</label>
                <textarea class="form-control" id="new_follow_up" name="new_follow_up" rows="3" placeholder="请输入新的跟进记录，保存后会自动添加日期和操作人"></textarea>
            </div>
            <?php endif; ?>
            
            <!-- 历史记录显示 -->
            <div class="mb-3">
                <label class="form-label">历史记录</label>
                <div class="follow-up-board border rounded p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                    <?php if ($sample && !empty($sample['follow_up'])): ?>
                        <?php 
                        $records = explode("\n", $sample['follow_up']);
                        $recordIndex = 0;
                        foreach ($records as $record): 
                            $record = trim($record);
                            if (empty($record)) continue;
                            $recordIndex++;
                            // 解析记录格式 [日期时间] 操作人：内容
                            if (preg_match('/^\[(.+?)\]\s*(.+?)：(.+)$/', $record, $matches)):
                                $dateTime = $matches[1];
                                $operator = $matches[2];
                                $content = $matches[3];
                            else:
                                $dateTime = '';
                                $operator = '';
                                $content = $record;
                            endif;
                        ?>
                        <div class="follow-up-item mb-3 pb-3 border-bottom" data-index="<?php echo $recordIndex; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars_string($operator ?: '系统'); ?></span>
                                    <?php if ($action != 'view'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editFollowUp(<?php echo $recordIndex; ?>)" title="修改">
                                        <i class="bi bi-pencil" style="font-size: 12px;"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteFollowUp(<?php echo $recordIndex; ?>)" title="删除">
                                        <i class="bi bi-trash" style="font-size: 12px;"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($dateTime): ?>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars_string($dateTime); ?></small>
                                <?php endif; ?>
                            </div>
                            <!-- 显示模式 -->
                            <div class="follow-up-content text-dark" id="follow-up-display-<?php echo $recordIndex; ?>"><?php echo nl2br(htmlspecialchars_string($content)); ?></div>
                            <!-- 编辑模式（默认隐藏） -->
                            <?php if ($action != 'view'): ?>
                            <div class="follow-up-edit d-none" id="follow-up-edit-<?php echo $recordIndex; ?>">
                                <textarea class="form-control form-control-sm mb-2" rows="2" id="follow-up-text-<?php echo $recordIndex; ?>"><?php echo htmlspecialchars_string($content); ?></textarea>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="saveFollowUp(<?php echo $recordIndex; ?>)">保存</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelEditFollowUp(<?php echo $recordIndex; ?>)">取消</button>
                                </div>
                            </div>
                            <?php endif; ?>
                            <!-- 隐藏字段存储完整记录 -->
                            <input type="hidden" name="follow_up_records[]" value="<?php echo htmlspecialchars_string($record); ?>" id="follow-up-record-<?php echo $recordIndex; ?>">
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4" id="no-follow-up-msg">
                            <i class="bi bi-inbox display-6 d-block mb-2"></i>
                            <small>暂无跟进记录</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($action != 'view'): ?>
            <!-- 用于存储删除和修改后的记录 -->
            <input type="hidden" name="follow_up" id="follow_up_final" value="<?php echo $sample ? htmlspecialchars_string($sample['follow_up']) : ''; ?>">
            <?php endif; ?>
            
            <?php if ($action == 'view' && $sample): ?>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">创建时间</label>
                    <input type="text" class="form-control" value="<?php echo formatDate($sample['create_time'], 'Y-m-d H:i:s'); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">更新时间</label>
                    <input type="text" class="form-control" value="<?php echo formatDate($sample['update_time'], 'Y-m-d H:i:s'); ?>" readonly>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="d-flex gap-2">
                <?php if ($action != 'view'): ?>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> 保存
                </button>
                <?php endif; ?>
                <a href="/sample.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> 返回列表
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($action == 'view' && $sample): ?>
<!-- 关联数据 -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cart3"></i> 该客户的订单记录</h5>
            </div>
            <div class="card-body p-0">
                <?php
                $orders = fetchAll(
                    "SELECT * FROM order_main WHERE customer_id = ? ORDER BY create_time DESC LIMIT 5",
                    [$sample['customer_id']]
                );
                ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>订单号</th>
                                <th>下单日期</th>
                                <th>出货日期</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><a href="/order_form.php?id=<?php echo $order['id']; ?>&action=view"><?php echo $order['order_no']; ?></a></td>
                                <td><?php echo formatDate($order['order_date']); ?></td>
                                <td><?php echo formatDate($order['ship_date']); ?></td>
                                <td><span class="<?php echo getOrderStatusClass($order['status']); ?>"><?php echo getOrderStatusText($order['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($orders)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">该客户暂无订单</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-center">
                    <a href="/order.php?customer_id=<?php echo $sample['customer_id']; ?>" class="btn btn-sm btn-outline-primary">查看全部订单</a>
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
            // 提交前更新follow_up字段
            updateFollowUpFinal();
            form.classList.add('was-validated');
        }, false);
    });
})();

// 跟进记录操作函数
function editFollowUp(index) {
    document.getElementById('follow-up-display-' + index).classList.add('d-none');
    document.getElementById('follow-up-edit-' + index).classList.remove('d-none');
}

function cancelEditFollowUp(index) {
    document.getElementById('follow-up-display-' + index).classList.remove('d-none');
    document.getElementById('follow-up-edit-' + index).classList.add('d-none');
    // 恢复原始值
    var recordInput = document.getElementById('follow-up-record-' + index);
    var originalValue = recordInput.value;
    // 解析原始记录获取内容
    var match = originalValue.match(/^\[(.+?)\]\s*(.+?)：(.+)$/);
    if (match) {
        document.getElementById('follow-up-text-' + index).value = match[3];
    } else {
        document.getElementById('follow-up-text-' + index).value = originalValue;
    }
}

function saveFollowUp(index) {
    var newContent = document.getElementById('follow-up-text-' + index).value.trim();
    if (!newContent) {
        alert('跟进记录内容不能为空');
        return;
    }
    
    var recordInput = document.getElementById('follow-up-record-' + index);
    var originalValue = recordInput.value;
    
    // 解析原始记录获取日期和操作人
    var match = originalValue.match(/^\[(.+?)\]\s*(.+?)：(.+)$/);
    if (match) {
        var dateTime = match[1];
        var operator = match[2];
        // 更新记录
        var newRecord = '[' + dateTime + '] ' + operator + '：' + newContent;
        recordInput.value = newRecord;
        // 更新显示
        document.getElementById('follow-up-display-' + index).innerHTML = newContent.replace(/\n/g, '<br>');
    } else {
        recordInput.value = newContent;
        document.getElementById('follow-up-display-' + index).innerHTML = newContent.replace(/\n/g, '<br>');
    }
    
    // 切换回显示模式
    document.getElementById('follow-up-display-' + index).classList.remove('d-none');
    document.getElementById('follow-up-edit-' + index).classList.add('d-none');
    
    // 更新最终字段
    updateFollowUpFinal();
}

function deleteFollowUp(index) {
    if (!confirm('确定要删除这条跟进记录吗？')) {
        return;
    }
    
    // 标记为已删除（清空值）
    var recordInput = document.getElementById('follow-up-record-' + index);
    if (recordInput) {
        recordInput.value = '';
        // 隐藏该记录项
        var item = recordInput.closest('.follow-up-item');
        if (item) {
            item.style.display = 'none';
        }
    }
    
    // 更新最终字段
    updateFollowUpFinal();
}

function updateFollowUpFinal() {
    var records = [];
    var inputs = document.querySelectorAll('input[name="follow_up_records[]"]');
    inputs.forEach(function(input) {
        if (input.value.trim()) {
            records.push(input.value.trim());
        }
    });
    
    var finalInput = document.getElementById('follow_up_final');
    if (finalInput) {
        finalInput.value = records.join('\n');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
