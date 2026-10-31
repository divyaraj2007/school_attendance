<?php
// includes/auth.php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

function is_logged_in(): bool {
    if (!empty($_SESSION['user']['id'])) {
        return true;
    }
    // Check remember me cookie
    if (!empty($_COOKIE['school_att_remember'])) {
        $token = $_COOKIE['school_att_remember'];
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            login_user($user, false);
            return true;
        }
    }
    return false;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function current_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function require_role(array|string $allowedRoles): void {
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    if (!is_logged_in()) {
        flash_message('danger', 'Please log in to access this page.');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    $role = current_role();
    if (!in_array($role, $allowedRoles, true)) {
        flash_message('danger', 'Access denied: You do not have permission to view that page.');
        redirect_by_role($role);
        exit;
    }
}

function redirect_by_role(?string $role = null): void {
    $role = $role ?? current_role();
    $target = match ($role) {
        'admin' => BASE_URL . '/admin/index.php',
        'principal' => BASE_URL . '/principal/index.php',
        'teacher' => BASE_URL . '/teacher/index.php',
        'student' => BASE_URL . '/student/index.php',
        default => BASE_URL . '/index.php'
    };
    header("Location: $target");
    exit;
}

function login_user(array $user, bool $remember = false): void {
    $pdo = get_db_connection();
    
    // Load extended profile details based on role
    $profile = [];
    $fullName = $user['user_id'];
    $photo = null;

    if ($user['role'] === 'principal') {
        $stmt = $pdo->prepare("SELECT * FROM principals WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch() ?: [];
        $fullName = $profile['full_name'] ?? 'Principal';
        $photo = $profile['photo'] ?? null;
    } elseif ($user['role'] === 'teacher') {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch() ?: [];
        $fullName = $profile['full_name'] ?? 'Teacher';
        $photo = $profile['photo'] ?? null;
    } elseif ($user['role'] === 'student') {
        $stmt = $pdo->prepare("SELECT s.*, c.name as class_name, sec.name as section_name 
                               FROM students s
                               JOIN classes c ON s.class_id = c.id
                               JOIN sections sec ON s.section_id = sec.id
                               WHERE s.user_id = ?");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch() ?: [];
        $fullName = $profile['full_name'] ?? 'Student';
        $photo = $profile['photo'] ?? null;
    } else {
        $fullName = 'Administrator';
    }

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'user_id' => $user['user_id'],
        'role' => $user['role'],
        'full_name' => $fullName,
        'photo' => $photo,
        'profile' => $profile
    ];

    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Handle remember me
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->execute([$token, $user['id']]);
        setcookie('school_att_remember', $token, time() + (86400 * 30), '/', '', false, true);
    }

    log_audit('LOGIN', "User {$user['user_id']} ({$user['role']}) logged in.", (int)$user['id']);
}

function logout_user(): void {
    if (!empty($_SESSION['user']['id'])) {
        log_audit('LOGOUT', "User {$_SESSION['user']['user_id']} logged out.", (int)$_SESSION['user']['id']);
    }
    if (isset($_COOKIE['school_att_remember'])) {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
        $stmt->execute([$_COOKIE['school_att_remember']]);
        setcookie('school_att_remember', '', time() - 3600, '/');
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
