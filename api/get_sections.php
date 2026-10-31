<?php
// api/get_sections.php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($classId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, name FROM sections WHERE class_id = ? ORDER BY name ASC");
    $stmt->execute([$classId]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($sections);
} catch (Exception $e) {
    echo json_encode([]);
}
