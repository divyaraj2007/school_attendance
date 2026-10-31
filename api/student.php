<?php
// api/student.php - Student Mobile App Data Endpoint
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

if (!is_logged_in() || current_role() !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized student access.']);
    exit;
}

$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

// Fetch Student Profile
$stmt = $pdo->prepare("SELECT s.*, c.name as class_name, c.type as class_type, sec.name as section_name
                       FROM students s
                       JOIN classes c ON s.class_id = c.id
                       JOIN sections sec ON s.section_id = sec.id
                       WHERE s.user_id = ?");
$stmt->execute([$currentUserId]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Student record not found.']);
    exit;
}

$studentId = (int)$student['id'];
$stats = calculate_student_stats($studentId, $yearId);

// Recent 7 Days Logs
$logStmt = $pdo->prepare("SELECT a.attendance_date, a.status, a.remarks
                          FROM daily_attendance a
                          WHERE a.student_id = ? AND a.academic_year_id = ?
                          ORDER BY a.attendance_date DESC LIMIT 15");
$logStmt->execute([$studentId, $yearId]);
$recentAttendance = $logStmt->fetchAll();

// Subject breakdown
$subStmt = $pdo->prepare("SELECT s.id as subject_id, s.name as subject_name, s.code as subject_code,
                                 COUNT(sa.id) as total_classes,
                                 COUNT(CASE WHEN sa.status = 'present' THEN 1 END) as present_classes,
                                 COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) as absent_classes,
                                 COUNT(CASE WHEN sa.status = 'late' THEN 1 END) as late_classes,
                                 COUNT(CASE WHEN sa.status = 'half_day' THEN 1 END) as half_day_classes
                          FROM subjects s
                          JOIN timetable tt ON tt.subject_id = s.id AND tt.class_id = ? AND tt.section_id = ?
                          LEFT JOIN subject_attendance sa ON sa.subject_id = s.id AND sa.student_id = ? AND sa.academic_year_id = ?
                          GROUP BY s.id, s.name, s.code
                          ORDER BY s.name ASC");
$subStmt->execute([$student['class_id'], $student['section_id'], $studentId, $yearId]);
$subjectBreakdown = $subStmt->fetchAll();

foreach ($subjectBreakdown as &$sub) {
    $total = (int)$sub['total_classes'];
    $eff = (int)$sub['present_classes'] + (int)$sub['late_classes'] + ((int)$sub['half_day_classes'] * 0.5);
    $sub['percentage'] = $total > 0 ? round(($eff / $total) * 100, 1) : 0.0;
}
unset($sub);

// Today's Timetable Schedule
$todayName = date('l');
$timeStmt = $pdo->prepare("SELECT tt.*, s.name as subject_name, s.code as subject_code, t.full_name as teacher_name
                           FROM timetable tt
                           JOIN subjects s ON tt.subject_id = s.id
                           JOIN teachers t ON tt.teacher_id = t.id
                           WHERE tt.class_id = ? AND tt.section_id = ? AND tt.day_of_week = ? AND tt.academic_year_id = ?
                           ORDER BY tt.period_number ASC");
$timeStmt->execute([$student['class_id'], $student['section_id'], $todayName, $yearId]);
$todayTimetable = $timeStmt->fetchAll();

echo json_encode([
    'success' => true,
    'student' => [
        'id' => $student['id'],
        'student_id' => $student['student_id'],
        'full_name' => $student['full_name'],
        'roll_no' => $student['roll_no'],
        'class_name' => $student['class_name'],
        'section_name' => $student['section_name'],
        'photo' => $student['photo']
    ],
    'stats' => $stats,
    'recent_attendance' => $recentAttendance,
    'subjects' => $subjectBreakdown,
    'today_schedule' => $todayTimetable,
    'today_name' => $todayName,
    'today_date' => date('Y-m-d')
]);
