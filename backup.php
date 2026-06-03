<?php
/**
 * 数据库备份功能
 */
require_once __DIR__ . '/config/functions.php';
requireAdmin(); // 仅管理员可访问

// 设置备份文件名
$backupFileName = 'backup_' . date('Ymd_His') . '.sql';
$backupPath = __DIR__ . '/backups/' . $backupFileName;

// 创建备份目录
if (!is_dir(__DIR__ . '/backups')) {
    mkdir(__DIR__ . '/backups', 0755, true);
}

try {
    // 获取所有表
    $tables = fetchAll("SHOW TABLES");
    $tableList = [];
    foreach ($tables as $table) {
        $tableList[] = array_values($table)[0];
    }
    
    // 开始生成SQL
    $sql = "-- --------------------------------------------------------\n";
    $sql .= "-- CRM系统数据库备份\n";
    $sql .= "-- 备份时间: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- --------------------------------------------------------\n\n";
    
    // 禁用外键检查
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tableList as $table) {
        // 获取表结构
        $createTable = fetchAll("SHOW CREATE TABLE `{$table}`");
        $createSql = $createTable[0]['Create Table'] ?? '';
        
        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "-- 表结构 `{$table}`\n";
        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createSql . ";\n\n";
        
        // 获取表数据
        $rows = fetchAll("SELECT * FROM `{$table}`");
        
        if (!empty($rows)) {
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- 表数据 `{$table}`\n";
            $sql .= "-- --------------------------------------------------------\n";
            
            // 获取列名
            $columns = array_keys($rows[0]);
            $columnStr = '`' . implode('`, `', $columns) . '`';
            
            // 分批插入，每批50条
            $batchSize = 50;
            $totalRows = count($rows);
            
            for ($i = 0; $i < $totalRows; $i += $batchSize) {
                $batch = array_slice($rows, $i, $batchSize);
                $valuesList = [];
                
                foreach ($batch as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $valuesList[] = '(' . implode(', ', $values) . ')';
                }
                
                $sql .= "INSERT INTO `{$table}` ({$columnStr}) VALUES\n";
                $sql .= implode(",\n", $valuesList) . ";\n";
            }
            
            $sql .= "\n";
        }
    }
    
    // 启用外键检查
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    // 保存到文件
    file_put_contents($backupPath, $sql);
    
    // 下载文件
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backupFileName . '"');
    header('Content-Length: ' . filesize($backupPath));
    header('Cache-Control: no-cache, must-revalidate');
    
    readfile($backupPath);
    
    // 删除临时文件
    unlink($backupPath);
    
    exit;
    
} catch (Exception $e) {
    setFlashMessage('error', '备份失败：' . $e->getMessage());
    redirect('/setting.php');
}
