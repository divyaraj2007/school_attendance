<?php
// admin/backup.php - Database Backup Exporter
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db_connection();
log_audit('DB_BACKUP_DOWNLOAD', 'Admin downloaded a full SQL database backup.');

$filename = 'school_attendance_backup_' . date('Y_m_d_His') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo "-- ========================================================\n";
echo "-- Smart School Attendance Management System Database Backup\n";
echo "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
echo "-- Host: " . DB_HOST . " | Database: " . DB_NAME . "\n";
echo "-- ========================================================\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Drop table if exists
    echo "DROP TABLE IF EXISTS `{$table}`;\n";

    // Create table syntax
    $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
    echo $createStmt[1] . ";\n\n";

    // Rows
    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        echo "INSERT INTO `{$table}` VALUES\n";
        $rowSql = [];
        foreach ($rows as $row) {
            $values = array_map(function($val) use ($pdo) {
                if ($val === null) return 'NULL';
                return $pdo->quote((string)$val);
            }, array_values($row));
            $rowSql[] = "(" . implode(', ', $values) . ")";
        }
        echo implode(",\n", $rowSql) . ";\n\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
exit;
