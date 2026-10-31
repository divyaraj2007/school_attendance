<?php
// api/chart_data.php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? 'daily_breakdown';
$pdo = get_db_connection();
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

try {
    if ($type === 'daily_breakdown') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare("SELECT 
                                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
                                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
                                    COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_day
                                FROM daily_attendance 
                                WHERE attendance_date = ?");
        $stmt->execute([$date]);
        $data = $stmt->fetch();
        echo json_encode([
            'labels' => ['Present', 'Absent', 'Late', 'Half Day'],
            'data' => [
                (int)($data['present'] ?? 0),
                (int)($data['absent'] ?? 0),
                (int)($data['late'] ?? 0),
                (int)($data['half_day'] ?? 0)
            ],
            'colors' => ['#10b981', '#ef4444', '#f59e0b', '#f97316']
        ]);
        exit;
    }

    if ($type === 'weekly_trend') {
        $stmt = $pdo->query("SELECT attendance_date,
                                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                                    COUNT(*) as total_count
                             FROM daily_attendance
                             GROUP BY attendance_date
                             ORDER BY attendance_date DESC
                             LIMIT 7");
        $rows = array_reverse($stmt->fetchAll());
        $labels = [];
        $present = [];
        $absent = [];
        foreach ($rows as $r) {
            $labels[] = date('D (M d)', strtotime($r['attendance_date']));
            $present[] = (int)$r['present_count'];
            $absent[] = (int)$r['absent_count'];
        }
        echo json_encode([
            'labels' => $labels,
            'present' => $present,
            'absent' => $absent
        ]);
        exit;
    }

    if ($type === 'class_comparison') {
        $stmt = $pdo->prepare("SELECT c.name as class_name,
                                      COUNT(a.id) as total_attendance,
                                      COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count
                               FROM classes c
                               LEFT JOIN daily_attendance a ON c.id = a.class_id AND a.academic_year_id = ?
                               GROUP BY c.id
                               ORDER BY c.numeric_level ASC
                               LIMIT 10");
        $stmt->execute([$yearId]);
        $rows = $stmt->fetchAll();
        $labels = [];
        $percentages = [];
        foreach ($rows as $r) {
            $labels[] = $r['class_name'];
            $total = (int)$r['total_attendance'];
            $pct = $total > 0 ? round(((int)$r['present_count'] / $total) * 100, 1) : 0;
            $percentages[] = $pct;
        }
        echo json_encode([
            'labels' => $labels,
            'data' => $percentages
        ]);
        exit;
    }

    echo json_encode(['error' => 'Unknown chart type']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
