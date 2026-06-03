<?php
/**
 * 退出登录
 */
require_once __DIR__ . '/config/functions.php';

// 清除所有session数据
session_unset();
session_destroy();

// 跳转到登录页
redirect('login.php');
