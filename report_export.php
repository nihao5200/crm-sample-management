<?php
/**
 * 数据报表导出Excel
 */
require_once __DIR__ . '/config/functions.php';

// 时间范围
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

// 获取统计数据
$totalOrderCount = fetchCount(
    "SELECT COUNT(*) FROM order_main WHERE order_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

$totalOrderAmount = fetchCount(
    "SELECT COALESCE(SUM(order_total_amount), 0) FROM order_main WHERE order_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

$totalCustomerCount = fetchCount(
    "SELECT COUNT(DISTINCT customer_id) FROM order_main WHERE order_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

// 客户TOP10
$customerTop10 = fetchAll(
    "SELECT c.customer_name, 
            COUNT(DISTINCT om.id) as order_count,
            COALESCE(SUM(om.order_total_amount), 0) as total_amount
     FROM customer c
     INNER JOIN order_main om ON c.id = om.customer_id
     WHERE om.order_date BETWEEN ? AND ?
     GROUP BY c.id, c.customer_name
     HAVING total_amount > 0
     ORDER BY total_amount DESC
     LIMIT 10",
    [$startDate, $endDate]
);

// 产品TOP10
$productTop10 = fetchAll(
    "SELECT od.product_model,
            SUM(COALESCE(od.quantity, 0)) as total_qty,
            SUM(COALESCE(od.amount, 0)) as total_amount
     FROM order_detail od
     INNER JOIN order_main om ON od.order_id = om.id
     WHERE om.order_date BETWEEN ? AND ?
     GROUP BY od.product_model
     ORDER BY total_qty DESC
     LIMIT 10",
    [$startDate, $endDate]
);

// 日统计明细
$dailyStats = fetchAll(
    "SELECT order_date,
            COUNT(*) as order_count,
            COALESCE(SUM(order_total_amount), 0) as total_amount
     FROM order_main
     WHERE order_date BETWEEN ? AND ?
     GROUP BY order_date
     ORDER BY order_date DESC",
    [$startDate, $endDate]
);

// 导出CSV
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment;filename="数据报表_' . $startDate . '_' . $endDate . '.csv"');
header('Cache-Control: max-age=0');

// 输出BOM头，解决Excel中文乱码
echo "\xEF\xBB\xBF";

// 汇总数据
echo "数据报表\n";
echo "统计期间," . $startDate . " 至 " . $endDate . "\n\n";

echo "汇总统计\n";
echo "订单数量," . $totalOrderCount . "\n";
echo "订单总额," . $totalOrderAmount . "\n";
echo "成交客户," . $totalCustomerCount . "\n";
echo "平均客单价," . ($totalOrderCount > 0 ? round($totalOrderAmount / $totalOrderCount, 2) : 0) . "\n\n";

// 客户TOP10
echo "客户订单金额TOP10\n";
echo "排名,客户名称,订单数量,订单金额\n";
$rank = 1;
foreach ($customerTop10 as $row) {
    echo $rank . ',' . $row['customer_name'] . ',' . $row['order_count'] . ',' . $row['total_amount'] . "\n";
    $rank++;
}
echo "\n";

// 产品TOP10
echo "产品销量TOP10\n";
echo "排名,产品型号,销售数量,销售金额\n";
$rank = 1;
foreach ($productTop10 as $row) {
    echo $rank . ',' . $row['product_model'] . ',' . $row['total_qty'] . ',' . $row['total_amount'] . "\n";
    $rank++;
}
echo "\n";

// 日统计明细
echo "日统计明细\n";
echo "日期,订单数量,订单金额\n";
foreach ($dailyStats as $row) {
    echo $row['order_date'] . ',' . $row['order_count'] . ',' . $row['total_amount'] . "\n";
}

exit;
