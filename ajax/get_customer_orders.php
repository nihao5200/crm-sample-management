<?php
/**
 * AJAX接口：获取客户的订单列表
 */
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

// 验证登录
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$customerId = intval($_GET['customer_id'] ?? 0);

if ($customerId <= 0) {
    echo json_encode(['success' => false, 'message' => '客户ID无效']);
    exit;
}

// 获取客户订单列表
$orders = fetchAll(
    "SELECT om.id, om.order_no, om.order_date, 
            (SELECT COALESCE(SUM(amount), 0) FROM order_detail WHERE order_id = om.id) as total_amount,
            (SELECT COALESCE(SUM(link_amount), 0) FROM order_payment_link WHERE order_id = om.id) as paid_amount
     FROM order_main om 
     WHERE om.customer_id = ? 
     ORDER BY om.order_date DESC",
    [$customerId]
);

echo json_encode(['success' => true, 'orders' => $orders]);
