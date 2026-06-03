<?php
/**
 * 页面头部公共模板
 * 包含侧边栏导航和顶部导航栏
 */
require_once __DIR__ . '/../config/functions.php';
requireLogin();

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// 定义页面映射
$pageMap = [
    'index' => 'dashboard',
    'customer' => 'customers',
    'customer_form' => 'customers',
    'order' => 'orders',
    'order_form' => 'orders',
    'order_pending' => 'pending',
    'sample' => 'samples',
    'sample_form' => 'samples',
    'payment' => 'payments',
    'payment_form' => 'payments',
    'report' => 'reports'
];

$page = isset($pageMap[$currentPage]) ? $pageMap[$currentPage] : '';

// 获取待出货订单数量（用于菜单角标）
$pendingShipmentCount = fetchCount(
    "SELECT COUNT(*) FROM order_main WHERE ship_date > CURDATE() AND status != 3"
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>客户订单样品管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    /* 菜单悬浮提示 */
    .nav-link {
        position: relative;
    }
    .nav-link:hover::after {
        content: attr(data-title);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0,0,0,0.8);
        color: #fff;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 10000;
        margin-left: 10px;
    }
    .nav-link:hover::before {
        content: '';
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        border: 5px solid transparent;
        border-right-color: rgba(0,0,0,0.8);
        z-index: 10000;
    }
    /* 菜单角标 */
    .nav-badge {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: #e74a3b;
        color: #fff;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }
    </style>
</head>
<body>
    <!-- 侧边栏 -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-box-seam"></i>
            <h5>CRM管理系统</h5>
        </div>
        <div class="sidebar-nav">
            <div class="nav-item">
                <a class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>" 
                   href="/index.php" 
                   data-title="数据概览">
                    <i class="bi bi-speedometer2"></i>
                    <span>数据概览</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo $page === 'customers' ? 'active' : ''; ?>" 
                   href="/customer.php"
                   data-title="管理客户信息">
                    <i class="bi bi-people"></i>
                    <span>客户管理</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo $page === 'orders' ? 'active' : ''; ?>" 
                   href="/order.php"
                   data-title="管理订单">
                    <i class="bi bi-cart3"></i>
                    <span>订单管理</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?php echo $page === 'samples' ? 'active' : ''; ?>" 
                   href="/sample.php"
                   data-title="管理样品记录">
                    <i class="bi bi-box"></i>
                    <span>样品管理</span>
                </a>
            </div>
            
            <!-- 待出货订单设为一级菜单 -->
            <div class="nav-item">
                <a class="nav-link <?php echo $page === 'pending' ? 'active' : ''; ?>" 
                   href="/order_pending.php"
                   data-title="查看待出货订单">
                    <i class="bi bi-truck"></i>
                    <span>待出货订单</span>
                    <?php if ($pendingShipmentCount > 0): ?>
                    <span class="nav-badge"><?php echo $pendingShipmentCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="sidebar-divider"></div>
            
            <div class="nav-item">
                <a class="nav-link <?php echo $page === 'payments' ? 'active' : ''; ?>" 
                   href="/payment.php"
                   data-title="管理收款记录">
                    <i class="bi bi-cash-coin"></i>
                    <span>收款记录</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a class="nav-link <?php echo $page === 'reports' ? 'active' : ''; ?>" 
                   href="/report.php"
                   data-title="查看统计报表">
                    <i class="bi bi-graph-up"></i>
                    <span>数据报表</span>
                </a>
            </div>
            
            <?php if (isAdmin()): ?>
            <div class="sidebar-divider"></div>
            <div class="nav-item">
                <a class="nav-link" 
                   href="/setting.php"
                   data-title="系统设置">
                    <i class="bi bi-gear"></i>
                    <span>系统设置</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- 遮罩层 -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- 顶部导航栏 -->
    <header class="topbar">
        <button class="topbar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-title"><?php echo isset($pageTitle) ? $pageTitle : '数据概览'; ?></div>
        <div class="topbar-user">
            <div class="dropdown">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="user-avatar"><?php echo mb_substr($currentUser['username'], 0, 1); ?></div>
                    <span><?php echo htmlspecialchars_string($currentUser['username']); ?> <small class="text-muted">(<?php echo $currentUser['role'] == 1 ? '管理员' : '普通用户'; ?>)</small></span>
                    <i class="bi bi-chevron-down small"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>退出登录</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- 主内容区 -->
    <main class="main-content">
        <div class="content-wrapper">
