<?php
// api/teacher.php - Teacher Mobile App Data & Attendance Submission Endpoint
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

if (!is_logged_in() || current_role() !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized teacher access.']);
    exit;
}

$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacher = $tStmt->fetch();

if (!$teacher) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Teacher profile not found.']);
    exit;
}

$teacherId = (int)$teacher['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. Submit Attendance via Mobile App (POST)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $classId = (int)($input['class_id'] ?? 0);
    $sectionId = (int)($input['section_id'] ?? 0);
    $date = trim((string)($input['date'] ?? date('Y-m-d')));
    $attendanceData = $input['attendance'] ?? []; // Map: student_id => status ('present', 'absent', 'late', 'half_day')
    $type = trim((string)($input['type'] ?? 'daily')); // 'daily' or 'subject'
    $subjectId = (int)($input['subject_id'] ?? 0);
    $period = (int)($input['period'] ?? 1);

    if ($classId <= 0 || $sectionId <= 0 || empty($attendanceData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required attendance parameters.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($type === 'daily') {
            $insertStmt = $pdo->prepare("INSERT INTO daily_attendance (student_id, class_id, section_id, academic_year_id, attendance_date, status, marked_by)
                                         VALUES (?, ?, ?, ?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)");
            foreach ($attendanceData as $stuId => $st) {
                $status = in_array($st, ['present', 'absent', 'late', 'half_day']) ? $st : 'present';
                $insertStmt->execute([(int)$stuId, $classId, $sectionId, $yearId, $date, $status, $currentUserId]);
            }
            log_audit('MOBILE_DAILY_ATTENDANCE_MARKED', "Teacher {$teacher['full_name']} marked daily attendance for Class {$classId}-Sec {$sectionId} on {$date} via Mobile App.", $currentUserId);
        } else {
            // Subject attendance
            if ($subjectId <= 0) {
                throw new Exception('Subject ID is required for subject attendance.');
            }
            $insertSub = $pdo->prepare("INSERT INTO subject_attendance (student_id, class_id, section_id, subject_id, teacher_id, academic_year_id, attendance_date, lecture_period, status, marked_by)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)");
            foreach ($attendanceData as $stuId => $st) {
                $status = in_array($st, ['present', 'absent', 'late', 'half_day']) ? $st : 'present';
                $insertSub->execute([(int)$stuId, $classId, $sectionId, $subjectId, $teacherId, $yearId, $date, $period, $status, $currentUserId]);
            }
            log_audit('MOBILE_SUBJECT_ATTENDANCE_MARKED', "Teacher {$teacher['full_name']} marked subject {$subjectId} period {$period} on {$date} via Mobile App.", $currentUserId);
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Attendance successfully submitted from mobile app.',
            'count' => count($attendanceData)
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save attendance: ' . $e->getMessage()]);
        exit;
    }
}

// 2. Fetch Teacher Data for Mobile App (GET)
$action = $_GET['action'] ?? 'overview';

// Action: Fetch Students for a specific class
if ($action === 'students') {
    $classId = (int)($_GET['class_id'] ?? 0);
    $sectionId = (int)($_GET['section_id'] ?? 0);
    $date = trim((string)($_GET['date'] ?? date('Y-m-d')));

    $stuStmt = $pdo->prepare("SELECT s.id, s.student_id, s.roll_no, s.full_name, s.gender,
                                     da.status as today_status
                              FROM students s
                              LEFT JOIN daily_attendance da ON da.student_id = s.id AND da.attendance_date = ?
                              WHERE s.class_id = ? AND s.section_id = ?
                              ORDER BY s.roll_no ASC");
    $stuStmt->execute([$date, $classId, $sectionId]);
    $students = $stuStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'class_id' => $classId,
        'section_id' => $sectionId,
        'date' => $date,
        'students' => $students
    ]);
    exit;
}

// Default: Teacher Overview Dashboard Data
$assignStmt = $pdo->prepare("SELECT ta.*, c.name as class_name, c.type as class_type, sec.name as section_name,
                                    sub.name as subject_name, sub.code as subject_code,
                                    COUNT(DISTINCT s.id) as student_count
                             FROM teacher_assignments ta
                             JOIN classes c ON ta.class_id = c.id
                             JOIN sections sec ON ta.section_id = sec.id
                             LEFT JOIN subjects sub ON ta.subject_id = sub.id
                             LEFT JOIN students s ON (ta.class_id = s.class_id AND ta.section_id = s.section_id)
                             WHERE ta.teacher_id = ? AND ta.academic_year_id = ?
                             GROUP BY ta.id
                             ORDER BY c.numeric_level, sec.name");
$assignStmt->execute([$teacherId, $yearId]);
$assignments = $assignStmt->fetchAll();

// Today's Teaching Schedule
$todayName = date('l');
$timeStmt = $pdo->prepare("SELECT tt.*, c.name as class_name, sec.name as section_name, sub.name as subject_name, sub.code as subject_code
                           FROM timetable tt
                           JOIN classes c ON tt.class_id = c.id
                           JOIN sections sec ON tt.section_id = sec.id
                           JOIN subjects sub ON tt.subject_id = sub.id
                           WHERE tt.teacher_id = ? AND tt.day_of_week = ? AND tt.academic_year_id = ?
                           ORDER BY tt.period_number ASC");
$timeStmt->execute([$teacherId, $todayName, $yearId]);
$todayLectures = $timeStmt->fetchAll();

echo json_encode([
    'success' => true,
    'teacher' => [
        'id' => $teacher['id'],
        'teacher_id' => $teacher['teacher_id'],
        'full_name' => $teacher['full_name'],
        'email' => $teacher['email'],
        'mobile' => $teacher['mobile'],
        'qualification' => $teacher['qualification']
    ],
    'assignments' => $assignments,
    'today_lectures' => $todayLectures,
    'today_name' => $todayName,
    'today_date' => date('Y-m-d')
]);
