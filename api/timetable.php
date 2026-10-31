<?php
// api/timetable.php - Timetable REST API Endpoint
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$pdo = get_db_connection();
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

$classId = (int)($_GET['class_id'] ?? 0);
$sectionId = (int)($_GET['section_id'] ?? 0);
$teacherId = (int)($_GET['teacher_id'] ?? 0);
$day = trim((string)($_GET['day'] ?? ''));

$where = ["tt.academic_year_id = ?"];
$params = [$yearId];

if ($classId > 0) {
    $where[] = "tt.class_id = ?";
    $params[] = $classId;
}
if ($sectionId > 0) {
    $where[] = "tt.section_id = ?";
    $params[] = $sectionId;
}
if ($teacherId > 0) {
    $where[] = "tt.teacher_id = ?";
    $params[] = $teacherId;
}
if (!empty($day)) {
    $where[] = "tt.day_of_week = ?";
    $params[] = $day;
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT tt.*, c.name as class_name, sec.name as section_name,
                              s.name as subject_name, s.code as subject_code,
                              t.full_name as teacher_name
                       FROM timetable tt
                       JOIN classes c ON tt.class_id = c.id
                       JOIN sections sec ON tt.section_id = sec.id
                       JOIN subjects s ON tt.subject_id = s.id
                       JOIN teachers t ON tt.teacher_id = t.id
                       WHERE {$whereClause}
                       ORDER BY FIELD(tt.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), tt.period_number ASC");
$stmt->execute($params);
$slots = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'count' => count($slots),
    'slots' => $slots
]);
