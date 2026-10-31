<?php
// api/auth.php - Mobile App & Web REST API Authentication
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. Check Session Status (GET)
if ($method === 'GET') {
    if (is_logged_in()) {
        $user = current_user();
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'authenticated' => false,
            'user' => null
        ]);
    }
    exit;
}

// 2. Mobile App Login (POST)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $userId = trim((string)($input['user_id'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $role = trim((string)($input['role'] ?? ''));

    if (empty($userId) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'User ID and Password are required.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid User ID or Password.'
        ]);
        exit;
    }

    if (!empty($role) && $user['role'] !== $role) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => "Account is not assigned to the '{$role}' role."
        ]);
        exit;
    }

    // Log the user into PHP session
    login_user($user, true);
    $sessionUser = current_user();

    log_audit('API_LOGIN_SUCCESS', "User '{$userId}' logged in via Mobile App / REST API.", (int)$user['id']);

    echo json_encode([
        'success' => true,
        'message' => 'Authentication successful.',
        'user' => $sessionUser,
        'session_id' => session_id()
    ]);
    exit;
}
