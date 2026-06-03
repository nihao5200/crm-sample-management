<?php
/**
 * 客户管理 - 批量导入
 */
$pageTitle = '批量导入客户';
require_once __DIR__ . '/config/functions.php';
requireAdmin();

$importResult = null;
$errors = [];
$successCount = 0;
$failCount = 0;

// 处理导入
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    
    // 检查文件
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = '文件上传失败，错误代码：' . $file['error'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            $errors[] = '请上传 CSV 或 Excel 文件';
        } else {
            // 处理 CSV 文件
            if ($ext === 'csv') {
                $result = processCsvImport($file['tmp_name']);
                $importResult = $result;
                $successCount = $result['success'];
                $failCount = $result['fail'];
                if (!empty($result['errors'])) {
                    $errors = $result['errors'];
                }
            } else {
                $errors[] = 'Excel 文件导入功能需要安装 PHPExcel 扩展，请先使用 CSV 格式导入';
            }
        }
    }
}

/**
 * 处理 CSV 导入
 */
function processCsvImport($filePath) {
    $result = [
        'success' => 0,
        'fail' => 0,
        'errors' => [],
        'details' => []
    ];
    
    // 读取 CSV 文件
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        $result['errors'][] = '无法打开文件';
        return $result;
    }
    
    // 检测并跳过 BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }
    
    // 尝试多种编码读取
    $encodings = ['UTF-8', 'GBK', 'GB2312', 'BIG5'];
    $header = null;
    $usedEncoding = 'UTF-8';
    
    foreach ($encodings as $encoding) {
        rewind($handle);
        // 跳过 BOM
        if ($bom === "\xEF\xBB\xBF") {
            fread($handle, 3);
        }
        
        $rawHeader = fgetcsv($handle);
        if ($rawHeader && count($rawHeader) > 1) {
            // 尝试转换编码
            if ($encoding !== 'UTF-8') {
                $rawHeader = array_map(function($col) use ($encoding) {
                    return mb_convert_encoding($col, 'UTF-8', $encoding);
                }, $rawHeader);
            }
            
            // 检查是否包含中文字符（判断是否解码成功）
            $testStr = implode('', $rawHeader);
            if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $testStr)) {
                $header = $rawHeader;
                $usedEncoding = $encoding;
                break;
            }
        }
    }
    
    if (!$header) {
        $result['errors'][] = '文件为空或格式不正确，请确保是有效的 CSV 文件';
        fclose($handle);
        return $result;
    }
    
    // 标准化表头（更宽松的处理）
    $header = array_map(function($col) {
        // 移除所有空白字符、转换为小写
        $col = preg_replace('/\s+/u', '', $col);
        $col = mb_strtolower($col, 'UTF-8');
        return trim($col);
    }, $header);
    
    // 必需的字段映射（支持更多变体）
    $fieldMap = [
        'customer_name' => ['客户名称', '客户名', '公司名称', '公司', 'name', 'customername', 'customer', '客户', '企业名称', '企业'],
        'contact' => ['联系人', '联系人姓名', 'contact', 'contactname', '联系人姓名', '联系人'],
        'phone' => ['联系电话', '电话', '手机', 'phone', 'tel', 'mobile', '联系方式', '号码'],
        'email' => ['邮箱', '电子邮件', 'email', 'e-mail', 'mail', '电子邮箱'],
        'address' => ['地址', '公司地址', 'address', 'addr', '详细地址', '所在地'],
        'remark' => ['备注', '说明', 'remark', 'note', 'notes', '描述', '其他']
    ];
    
    // 查找字段位置（支持模糊匹配）
    $columnIndex = [];
    foreach ($fieldMap as $field => $possibleNames) {
        foreach ($possibleNames as $name) {
            // 标准化搜索词
            $searchName = preg_replace('/\s+/u', '', mb_strtolower($name, 'UTF-8'));
            
            // 精确匹配
            $index = array_search($searchName, $header);
            if ($index !== false) {
                $columnIndex[$field] = $index;
                break;
            }
            
            // 包含匹配（使用 mb_strpos 支持中文）
            foreach ($header as $i => $h) {
                if (mb_strpos($h, $searchName) !== false || mb_strpos($searchName, $h) !== false) {
                    $columnIndex[$field] = $i;
                    break 2;
                }
            }
        }
    }
    
    // 检查必需字段
    if (!isset($columnIndex['customer_name'])) {
        $result['errors'][] = '未找到"客户名称"列，请检查表头。支持的列名：客户名称、公司名称、客户名等';
        $result['errors'][] = '当前检测到的表头：' . implode('、', array_map('htmlspecialchars', $header));
        $result['errors'][] = '检测到的编码：' . $usedEncoding;
        fclose($handle);
        return $result;
    }
    
    // 读取数据行
    $rowNum = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        
        // 跳过空行
        if (empty(array_filter($row, 'trim'))) {
            continue;
        }
        
        // 转换编码（如果不是 UTF-8）
        if ($usedEncoding !== 'UTF-8') {
            $row = array_map(function($col) use ($usedEncoding) {
                return mb_convert_encoding($col, 'UTF-8', $usedEncoding);
            }, $row);
        }
        
        // 提取数据
        $data = [
            'customer_name' => isset($columnIndex['customer_name']) ? trim($row[$columnIndex['customer_name']] ?? '') : '',
            'contact' => isset($columnIndex['contact']) ? trim($row[$columnIndex['contact']] ?? '') : '',
            'phone' => isset($columnIndex['phone']) ? trim($row[$columnIndex['phone']] ?? '') : '',
            'email' => isset($columnIndex['email']) ? trim($row[$columnIndex['email']] ?? '') : '',
            'address' => isset($columnIndex['address']) ? trim($row[$columnIndex['address']] ?? '') : '',
            'remark' => isset($columnIndex['remark']) ? trim($row[$columnIndex['remark']] ?? '') : ''
        ];
        
        // 验证客户名称
        if (empty($data['customer_name'])) {
            $result['fail']++;
            $result['details'][] = ['row' => $rowNum, 'status' => 'fail', 'msg' => '客户名称不能为空'];
            continue;
        }
        
        // 检查客户是否已存在（自动去重 - 跳过已存在的）
        $existing = fetchOne("SELECT id, contact, phone FROM customer WHERE customer_name = ?", [$data['customer_name']]);
        if ($existing) {
            // 如果已存在，记录为跳过（不算失败）
            $result['details'][] = [
                'row' => $rowNum, 
                'status' => 'skip', 
                'msg' => '客户已存在，自动跳过（联系人：' . ($existing['contact'] ?: '无') . '）'
            ];
            continue;
        }
        
        // 插入数据库
        try {
            $sql = "INSERT INTO customer (customer_name, contact, phone, email, address, remark) VALUES (?, ?, ?, ?, ?, ?)";
            insert($sql, [
                $data['customer_name'],
                $data['contact'],
                $data['phone'],
                $data['email'],
                $data['address'],
                $data['remark']
            ]);
            $result['success']++;
            $result['details'][] = ['row' => $rowNum, 'status' => 'success', 'msg' => '导入成功'];
        } catch (Exception $e) {
            $result['fail']++;
            $result['details'][] = ['row' => $rowNum, 'status' => 'fail', 'msg' => '数据库错误：' . $e->getMessage()];
        }
    }
    
    fclose($handle);
    return $result;
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- 页面标题 -->
<div class="page-header">
    <h2><i class="bi bi-upload"></i> 批量导入客户</h2>
</div>

<?php showFlashMessage(); ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-arrow-up me-2"></i>上传文件</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>导入过程中出现以下错误：</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars_string($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if ($importResult): 
                    // 计算跳过的数量
                    $skipCount = count(array_filter($importResult['details'], function($d) { return $d['status'] === 'skip'; }));
                    $hasError = $importResult['fail'] > 0;
                    $alertClass = $hasError ? 'alert-warning' : ($skipCount > 0 ? 'alert-info' : 'alert-success');
                ?>
                <div class="alert <?php echo $alertClass; ?>">
                    <h6><i class="bi bi-check-circle me-2"></i>导入完成</h6>
                    <p class="mb-1">成功导入：<strong class="text-success"><?php echo $importResult['success']; ?></strong> 条</p>
                    <?php if ($skipCount > 0): ?>
                    <p class="mb-1">自动跳过（已存在）：<strong class="text-info"><?php echo $skipCount; ?></strong> 条</p>
                    <?php endif; ?>
                    <p class="mb-0">导入失败：<strong class="text-danger"><?php echo $importResult['fail']; ?></strong> 条</p>
                </div>
                
                <?php if (!empty($importResult['details'])): ?>
                <div class="table-responsive mt-3" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">行号</th>
                                <th style="width: 100px;">状态</th>
                                <th>说明</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($importResult['details'] as $detail): ?>
                            <tr>
                                <td>第 <?php echo $detail['row']; ?> 行</td>
                                <td>
                                    <?php if ($detail['status'] == 'success'): ?>
                                    <span class="badge bg-success">成功</span>
                                    <?php elseif ($detail['status'] == 'skip'): ?>
                                    <span class="badge bg-info">跳过</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">失败</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars_string($detail['msg']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <hr>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="import_file" class="form-label">选择 CSV 文件 <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="import_file" name="import_file" accept=".csv" required>
                        <div class="form-text text-muted">
                            支持 CSV 格式文件，文件大小不超过 2MB
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>开始导入
                        </button>
                        <a href="/customer.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>返回列表
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>导入说明</h5>
            </div>
            <div class="card-body">
                <h6 class="fw-bold">文件格式要求：</h6>
                <ul class="small text-muted mb-3">
                    <li>文件格式：CSV（逗号分隔）</li>
                    <li>文件编码：UTF-8（支持中文）</li>
                    <li>第一行为表头</li>
                    <li>每行一条客户记录</li>
                </ul>
                
                <h6 class="fw-bold">必需字段：</h6>
                <ul class="small text-muted mb-3">
                    <li><strong>客户名称</strong> - 必填，不能重复</li>
                </ul>
                
                <h6 class="fw-bold">可选字段：</h6>
                <ul class="small text-muted mb-3">
                    <li>联系人</li>
                    <li>联系电话</li>
                    <li>邮箱</li>
                    <li>地址</li>
                    <li>备注</li>
                </ul>
                
                <h6 class="fw-bold">示例表头：</h6>
                <div class="bg-light p-2 rounded small text-muted mb-3" style="font-family: monospace;">
                    客户名称,联系人,联系电话,邮箱,地址,备注
                </div>
                
                <h6 class="fw-bold">示例数据：</h6>
                <div class="bg-light p-2 rounded small text-muted" style="font-family: monospace;">
                    深圳市科技有限公司,张经理,13800138001,zhang@example.com,深圳市南山区,潜在客户<br>
                    上海贸易有限公司,李主管,13900139002,li@example.com,上海市浦东新区,重要客户
                </div>
                
                <div class="mt-3">
                    <a href="/templates/customer_import_template.csv" class="btn btn-sm btn-outline-primary w-100" download>
                        <i class="bi bi-download me-1"></i>下载导入模板
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

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
