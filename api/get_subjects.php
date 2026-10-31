<?php
// api/get_subjects.php
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
    $stmt = $pdo->prepare("SELECT s.id, s.code, s.name, s.type 
                           FROM subjects s
                           JOIN class_subjects cs ON s.id = cs.subject_id
                           WHERE cs.class_id = ?
                           ORDER BY s.name ASC");
    $stmt->execute([$classId]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($subjects);
} catch (Exception $e) {
    echo json_encode([]);
}
