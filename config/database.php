<?php
/**
 * 数据库配置文件
 */

// 数据库连接配置
define('DB_HOST', 'localhost');      // 数据库主机
define('DB_NAME', 'crm');     // 数据库名称
define('DB_USER', 'crm');           // 数据库用户名
define('DB_PASS', 'nihao888');           // 数据库密码
define('DB_CHARSET', 'utf8mb4');     // 字符集

// 创建数据库连接
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// 执行SQL查询（带参数绑定，防止SQL注入）
function query($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// 获取单条记录
function fetchOne($sql, $params = []) {
    return query($sql, $params)->fetch();
}

// 获取多条记录
function fetchAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

// 获取记录数
function fetchCount($sql, $params = []) {
    return query($sql, $params)->fetchColumn();
}

// 插入数据，返回自增ID
function insert($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $db->lastInsertId();
}

// 更新或删除数据，返回影响行数
function execute($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->rowCount();
}

// 开始事务
function beginTransaction() {
    return getDB()->beginTransaction();
}

// 提交事务
function commit() {
    return getDB()->commit();
}

// 回滚事务
function rollback() {
    return getDB()->rollBack();
}
