<?php
/**
 * 订单导出 - Excel格式
 */
require_once __DIR__ . '/config/functions.php';
requireLogin();

// 搜索条件
$keyword = input('keyword', '', 'get');
$customerId = intval(input('customer_id', 0, 'get'));
$status = intval(input('status', 0, 'get'));
$dateFrom = input('date_from', '', 'get');
$dateTo = input('date_to', '', 'get');

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $where .= " AND (om.order_no LIKE ? OR od.product_model LIKE ? OR c.customer_name LIKE ?)";
    $likeKeyword = "%{$keyword}%";
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

if ($customerId > 0) {
    $where .= " AND om.customer_id = ?";
    $params[] = $customerId;
}

if ($status > 0) {
    $where .= " AND om.status = ?";
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $where .= " AND om.ship_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where .= " AND om.ship_date <= ?";
    $params[] = $dateTo;
}

// 获取订单数据（不分页，导出全部）
$sql = "SELECT om.*, c.customer_name, c.contact, c.phone,
               (SELECT SUM(amount) FROM order_detail WHERE order_id = om.id) as total_amount,
               (SELECT SUM(quantity) FROM order_detail WHERE order_id = om.id) as total_quantity,
               GROUP_CONCAT(DISTINCT CONCAT(od.product_model, '×', od.quantity) SEPARATOR '; ') as products
        FROM order_main om 
        LEFT JOIN customer c ON om.customer_id = c.id 
        LEFT JOIN order_detail od ON om.id = od.order_id 
        {$where} 
        GROUP BY om.id
        ORDER BY om.create_time DESC";

$orders = fetchAll($sql, $params);

// 设置响应头，输出CSV格式（兼容Excel）
$filename = '订单数据_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 打开输出流
$output = fopen('php://output', 'w');

// 输出BOM头，解决Excel中文乱码
echo "\xEF\xBB\xBF";

// 表头
$headers = ['订单号', '客户名称', '联系人', '联系电话', '下单日期', '出货日期', '产品明细', '总数量', '总金额', '订单状态', '备注'];
fputcsv($output, $headers);

// 数据行
foreach ($orders as $order) {
    $statusText = '';
    switch ($order['status']) {
        case 1: $statusText = '待生产'; break;
        case 2: $statusText = '已发货'; break;
        case 3: $statusText = '已完成'; break;
        default: $statusText = '未知';
    }
    
    $row = [
        $order['order_no'],
        $order['customer_name'],
        $order['contact'],
        $order['phone'],
        $order['order_date'],
        $order['ship_date'],
        $order['products'] ?? '',
        $order['total_quantity'] ?? 0,
        $order['total_amount'] ?? 0,
        $statusText,
        $order['remark'] ?? ''
    ];
    
    fputcsv($output, $row);
}

fclose($output);
exit;
