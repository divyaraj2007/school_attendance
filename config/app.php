<?php
// config/app.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Dynamic Base URL detection
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    if (php_sapi_name() === 'cli-server') {
        define('BASE_URL', $protocol . $host);
    } elseif (php_sapi_name() === 'cli') {
        define('BASE_URL', 'http://localhost');
    } else {
        // Apache / XAMPP web server
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $appDir = str_replace('\\', '/', dirname(__DIR__));
        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

        $relative = '';
        if (!empty($docRoot) && strpos(strtolower($appDir), strtolower($docRoot)) === 0) {
            $relative = substr($appDir, strlen($docRoot));
        } else {
            // Find root folder name from script path
            $dirs = explode('/', trim($scriptName, '/'));
            if (!empty($dirs) && in_array(end($dirs), ['index.php', 'users.php', 'reports.php', 'profile.php', 'attendance.php', 'academics.php'])) {
                array_pop($dirs); // remove script file
            }
            if (!empty($dirs) && in_array(end($dirs), ['admin', 'principal', 'teacher', 'student', 'api', 'config', 'includes'])) {
                array_pop($dirs); // remove subfolder
            }
            $relative = !empty($dirs) ? '/' . implode('/', $dirs) : '';
        }
        $relative = rtrim($relative, '/');
        define('BASE_URL', $protocol . $host . $relative);
    }
}

define('APP_NAME', 'Smart School Attendance Management System');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/assets/uploads/profiles/');

date_default_timezone_set('Asia/Kolkata');
