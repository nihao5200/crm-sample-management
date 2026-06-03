<?php
/**
 * 公共函数文件
 */

session_start();

// 引入数据库配置
require_once __DIR__ . '/database.php';

// ============================================
// 安全相关函数
// ============================================

// XSS过滤
function htmlspecialchars_string($string) {
    if (is_array($string)) {
        return array_map('htmlspecialchars_string', $string);
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// 过滤输入
function input($key, $default = '', $method = 'request') {
    $value = $default;
    
    switch ($method) {
        case 'get':
            $value = isset($_GET[$key]) ? $_GET[$key] : $default;
            break;
        case 'post':
            $value = isset($_POST[$key]) ? $_POST[$key] : $default;
            break;
        case 'request':
        default:
            $value = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
            break;
    }
    
    // 如果是字符串，进行XSS过滤
    if (is_string($value)) {
        $value = trim($value);
        $value = htmlspecialchars_string($value);
    }
    
    return $value;
}

// 生成CSRF Token
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 验证CSRF Token
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================
// 用户认证相关函数
// ============================================

// 检查是否登录
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

// 获取当前登录用户信息
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'],
            'role' => $_SESSION['admin_role']
        ];
    }
    return null;
}

// 检查是否是管理员
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 1;
}

// 需要登录才能访问
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

// 需要管理员权限
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlashMessage('error', '您没有权限执行此操作');
        header('Location: /index.php');
        exit;
    }
}

// ============================================
// 消息提示函数
// ============================================

// 设置闪存消息
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

// 获取闪存消息
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// 显示闪存消息HTML
function showFlashMessage() {
    $message = getFlashMessage();
    if ($message) {
        $alertClass = $message['type'] == 'success' ? 'alert-success' : 'alert-danger';
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo $message['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

// ============================================
// 工具函数
// ============================================

// 生成唯一编号
function generateUniqueNo($prefix = '', $length = 6) {
    $date = date('Ymd');
    $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length));
    return $prefix . $date . $random;
}

// 分页函数
function pagination($total, $page, $pageSize = 10) {
    $totalPages = ceil($total / $pageSize);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $pageSize;
    
    return [
        'total' => $total,
        'page' => $page,
        'pageSize' => $pageSize,
        'totalPages' => $totalPages,
        'offset' => $offset
    ];
}

// 生成分页HTML
function paginationHtml($pagination, $url) {
    if ($pagination['totalPages'] <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // 上一页
    $prevClass = $pagination['page'] <= 1 ? 'disabled' : '';
    $prevPage = $pagination['page'] - 1;
    $html .= '<li class="page-item ' . $prevClass . '">';
    $html .= '<a class="page-link" href="' . $url . '&page=' . $prevPage . '">上一页</a></li>';
    
    // 页码
    $startPage = max(1, $pagination['page'] - 2);
    $endPage = min($pagination['totalPages'], $pagination['page'] + 2);
    
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=1">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = $i == $pagination['page'] ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">';
        $html .= '<a class="page-link" href="' . $url . '&page=' . $i . '">' . $i . '</a></li>';
    }
    
    if ($endPage < $pagination['totalPages']) {
        if ($endPage < $pagination['totalPages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=' . $pagination['totalPages'] . '">' . $pagination['totalPages'] . '</a></li>';
    }
    
    // 下一页
    $nextClass = $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '';
    $nextPage = $pagination['page'] + 1;
    $html .= '<li class="page-item ' . $nextClass . '">';
    $html .= '<a class="page-link" href="' . $url . '&page=' . $nextPage . '">下一页</a></li>';
    
    $html .= '</ul></nav>';
    
    return $html;
}

// 订单状态文本
function getOrderStatusText($status) {
    $statusMap = [
        1 => '待生产',
        2 => '已发货',
        3 => '已完成'
    ];
    return isset($statusMap[$status]) ? $statusMap[$status] : '未知';
}

// 订单状态样式
function getOrderStatusClass($status) {
    $classMap = [
        1 => 'badge bg-warning',
        2 => 'badge bg-info',
        3 => 'badge bg-success'
    ];
    return isset($classMap[$status]) ? $classMap[$status] : 'badge bg-secondary';
}

// 样品状态文本
function getSampleStatusText($status) {
    $statusMap = [
        1 => '待确认',
        2 => '已确认',
        3 => '已退回',
        4 => '已量产'
    ];
    return isset($statusMap[$status]) ? $statusMap[$status] : '未知';
}

// 样品状态样式
function getSampleStatusClass($status) {
    $classMap = [
        1 => 'badge bg-warning',
        2 => 'badge bg-success',
        3 => 'badge bg-danger',
        4 => 'badge bg-primary'
    ];
    return isset($classMap[$status]) ? $classMap[$status] : 'badge bg-secondary';
}

// 角色文本
function getRoleText($role) {
    return $role == 1 ? '管理员' : '普通用户';
}

// 格式化日期
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// 格式化金额
function formatMoney($amount) {
    return number_format($amount, 2);
}

// 检查订单是否待出货（出货日期晚于今天，且订单未发货/未完成）
function isPendingShipment($shipDate, $status = null) {
    // 只比较日期部分，忽略时间
    $today = date('Y-m-d');
    $isFutureShipDate = $shipDate >= $today;
    
    // 如果提供了状态，检查状态是否为待生产(1)
    if ($status !== null) {
        return $isFutureShipDate && ($status == 1);
    }
    
    return $isFutureShipDate;
}

// ============================================
// 订单日志相关函数
// ============================================

// 记录订单日志
function logOrderChange($orderId, $action, $fieldName = null, $oldValue = null, $newValue = null, $remark = '') {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $sql = "INSERT INTO order_log (order_id, action, field_name, old_value, new_value, operator_id, operator_name, remark) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    try {
        insert($sql, [$orderId, $action, $fieldName, $oldValue, $newValue, $user['id'], $user['username'], $remark]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// 获取订单日志
function getOrderLogs($orderId) {
    return fetchAll(
        "SELECT * FROM order_log WHERE order_id = ? ORDER BY create_time DESC",
        [$orderId]
    );
}

// 记录操作日志
function logOperation($module, $action, $objectId = null, $objectType = null, $content = '') {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $sql = "INSERT INTO operation_log (user_id, user_name, module, action, object_id, object_type, content, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    try {
        insert($sql, [
            $user['id'], 
            $user['username'], 
            $module, 
            $action, 
            $objectId, 
            $objectType, 
            $content,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// 获取当前页面URL（不含参数）
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
}

// 重定向
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// JSON响应
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// 成功响应
function successResponse($message = '操作成功', $data = []) {
    jsonResponse([
        'code' => 200,
        'message' => $message,
        'data' => $data
    ]);
}

// 错误响应
function errorResponse($message = '操作失败', $code = 400) {
    jsonResponse([
        'code' => $code,
        'message' => $message
    ], $code);
}
