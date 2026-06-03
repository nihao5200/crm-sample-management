<?php
/**
 * 收款记录导出Excel
 */
require_once __DIR__ . '/config/functions.php';

// 搜索条件
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$customerId = intval($_GET['customer_id'] ?? 0);
$paymentType = intval($_GET['payment_type'] ?? 0);
$isCashOrder = isset($_GET['is_cash_order']) ? intval($_GET['is_cash_order']) : -1;
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// 收款方式选项
$paymentTypes = [
    1 => '现金',
    2 => '银行转账',
    3 => '微信',
    4 => '支付宝'
];

// 构建查询条件
$where = "WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $where .= " AND (c.customer_name LIKE ? OR pr.remark LIKE ?)";
    $likeKeyword = "%{$keyword}%";
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

if ($customerId > 0) {
    $where .= " AND pr.customer_id = ?";
    $params[] = $customerId;
}

if ($paymentType > 0) {
    $where .= " AND pr.payment_type = ?";
    $params[] = $paymentType;
}

if ($isCashOrder >= 0) {
    $where .= " AND pr.is_cash_order = ?";
    $params[] = $isCashOrder;
}

if (!empty($dateFrom)) {
    $where .= " AND pr.payment_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where .= " AND pr.payment_date <= ?";
    $params[] = $dateTo;
}

// 获取收款记录列表
$payments = fetchAll(
    "SELECT pr.*, c.customer_name,
            GROUP_CONCAT(DISTINCT om.order_no ORDER BY om.order_no SEPARATOR ', ') as related_orders
     FROM payment_record pr 
     LEFT JOIN customer c ON pr.customer_id = c.id 
     LEFT JOIN order_payment_link opl ON pr.id = opl.payment_id
     LEFT JOIN order_main om ON opl.order_id = om.id
     {$where} 
     GROUP BY pr.id
     ORDER BY pr.payment_date DESC, pr.create_time DESC",
    $params
);

// 导出CSV
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment;filename="收款记录_' . date('Ymd') . '.csv"');
header('Cache-Control: max-age=0');

// 输出BOM头，解决Excel中文乱码
echo "\xEF\xBB\xBF";

// 表头
$headers = ['收款日期', '客户名称', '关联订单', '收款金额', '收款方式', '备注', '操作人', '订单类型'];
echo implode(',', $headers) . "\n";

// 数据
foreach ($payments as $payment) {
    $row = [
        $payment['payment_date'],
        $payment['customer_name'],
        $payment['related_orders'] ?: '无关联订单',
        $payment['amount'],
        $paymentTypes[$payment['payment_type']] ?? '未知',
        str_replace(["\r\n", "\n", ',' ], [' ', ' ', '，'], $payment['remark'] ?? ''),
        $payment['operator'],
        $payment['is_cash_order'] ? '现金订单' : '普通订单'
    ];
    echo implode(',', $row) . "\n";
}
exit;
