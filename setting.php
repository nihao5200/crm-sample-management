<?php
/**
 * 系统设置页面
 */
require_once __DIR__ . '/config/functions.php';
requireAdmin(); // 仅管理员可访问

// 处理表单提交（必须在 header.php 之前）
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'change_password') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            setFlashMessage('error', '请填写所有密码字段');
        } elseif ($newPassword != $confirmPassword) {
            setFlashMessage('error', '两次输入的新密码不一致');
        } elseif (strlen($newPassword) < 6) {
            setFlashMessage('error', '新密码长度不能少于6位');
        } else {
            // 验证旧密码
            $user = fetchOne("SELECT * FROM admin WHERE id = ?", [$_SESSION['admin_id']]);
            if ($user && password_verify($oldPassword, $user['password'])) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                execute("UPDATE admin SET password = ? WHERE id = ?", [$newHash, $_SESSION['admin_id']]);
                setFlashMessage('success', '密码修改成功');
            } else {
                setFlashMessage('error', '旧密码错误');
            }
        }
    }
    
    redirect('/setting.php');
}

$pageTitle = '系统设置';
require_once __DIR__ . '/includes/header.php';

// 获取系统统计信息
$stats = [
    'db_size' => '0 MB',
    'total_tables' => 0,
    'total_records' => 0
];

try {
    $dbInfo = fetchAll("SHOW TABLE STATUS");
    $stats['total_tables'] = count($dbInfo);
    $size = 0;
    $records = 0;
    foreach ($dbInfo as $table) {
        $size += $table['Data_length'] + $table['Index_length'];
        $records += $table['Rows'];
    }
    $stats['db_size'] = round($size / 1024 / 1024, 2) . ' MB';
    $stats['total_records'] = $records;
} catch (Exception $e) {
    // 忽略错误
}
?>

<?php showFlashMessage(); ?>

<div class="row">
    <!-- 左侧菜单 -->
    <div class="col-lg-3 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">设置菜单</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="#password" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                    <i class="bi bi-key me-2"></i>修改密码
                </a>
                <a href="#system" class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="bi bi-info-circle me-2"></i>系统信息
                </a>
                <a href="#backup" class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="bi bi-download me-2"></i>数据备份
                </a>
            </div>
        </div>
    </div>
    
    <!-- 右侧内容 -->
    <div class="col-lg-9">
        <div class="tab-content">
            <!-- 修改密码 -->
            <div class="tab-pane fade show active" id="password">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-key me-2"></i>修改密码</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">旧密码</label>
                                <input type="password" class="form-control" name="old_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">新密码</label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                                <div class="form-text">密码长度不能少于6位</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">确认新密码</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>保存修改
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 系统信息 -->
            <div class="tab-pane fade" id="system">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>系统信息</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <td width="30%" class="bg-light">系统名称</td>
                                <td>客户订单样品管理系统</td>
                            </tr>
                            <tr>
                                <td class="bg-light">系统版本</td>
                                <td>v2.0.0</td>
                            </tr>
                            <tr>
                                <td class="bg-light">PHP版本</td>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <td class="bg-light">数据库大小</td>
                                <td><?php echo $stats['db_size']; ?></td>
                            </tr>
                            <tr>
                                <td class="bg-light">数据表数量</td>
                                <td><?php echo $stats['total_tables']; ?> 个</td>
                            </tr>
                            <tr>
                                <td class="bg-light">总记录数</td>
                                <td><?php echo number_format($stats['total_records']); ?> 条</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- 数据备份 -->
            <div class="tab-pane fade" id="backup">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-download me-2"></i>数据备份</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">点击下面的按钮可以导出数据库SQL文件，用于数据备份。</p>
                        <a href="/backup.php" class="btn btn-success">
                            <i class="bi bi-download me-1"></i>立即备份数据库
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 版权信息 -->
        <div class="text-center mt-4 py-3 text-muted" style="font-size: 13px;">
            <span>Copyright © 2026 Yao All Rights Reserved</span>
            <span class="mx-2">|</span>
            <span>客户订单样品管理系统 V2.0.0</span>
            <span class="mx-2">|</span>
            <span>MIT License</span>
            <span class="mx-2">|</span>
            <a href="mailto:sky123@vip.qq.com" class="text-decoration-none">sky123@vip.qq.com</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
