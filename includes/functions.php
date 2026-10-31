<?php
// includes/functions.php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function sanitize(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return is_string($data) ? trim($data) : $data;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function flash_message(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function render_flash_messages(): string {
    $messages = get_flash_messages();
    if (empty($messages)) {
        return '';
    }
    $html = '<div class="flash-messages-container mb-4">';
    foreach ($messages as $msg) {
        $alertClass = match ($msg['type']) {
            'success' => 'alert-success',
            'error', 'danger' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        $icon = match ($msg['type']) {
            'success' => 'fa-check-circle',
            'error', 'danger' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            default => 'fa-info-circle'
        };
        $html .= '<div class="alert ' . $alertClass . ' alert-dismissible fade show shadow-sm" role="alert">';
        $html .= '<i class="fas ' . $icon . ' me-2"></i>' . e($msg['message']);
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function get_setting(string $key, string $default = ''): string {
    static $settingsCache = null;
    $pdo = get_db_connection();
    if ($settingsCache === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM school_settings");
        $settingsCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $settingsCache[$key] ?? $default;
}

function update_setting(string $key, string $value): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

function log_audit(string $action, string $details, ?int $userId = null): void {
    try {
        $pdo = get_db_connection();
        if ($userId === null && !empty($_SESSION['user']['id'])) {
            $userId = (int)$_SESSION['user']['id'];
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        // Silently log or ignore audit failure to not break primary flow
        error_log("Audit log failed: " . $e->getMessage());
    }
}

function get_active_academic_year(): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1");
    $year = $stmt->fetch();
    if (!$year) {
        $stmt = $pdo->query("SELECT * FROM academic_years ORDER BY id DESC LIMIT 1");
        $year = $stmt->fetch();
    }
    return $year ?: null;
}

function format_date(?string $date, string $format = 'M d, Y'): string {
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '-';
}

function format_time(?string $time, string $format = 'h:i A'): string {
    if (empty($time)) return '-';
    $timestamp = strtotime($time);
    return $timestamp ? date($format, $timestamp) : '-';
}

function status_badge(string $status): string {
    $status = strtolower($status);
    return match ($status) {
        'present' => '<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="fas fa-check me-1"></i>Present</span>',
        'absent' => '<span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1"><i class="fas fa-times me-1"></i>Absent</span>',
        'late' => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1"><i class="fas fa-clock me-1"></i>Late</span>',
        'half_day' => '<span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1"><i class="fas fa-adjust me-1"></i>Half Day</span>',
        'on_leave' => '<span class="badge bg-info-subtle text-info-emphasis border border-info px-2 py-1"><i class="fas fa-plane me-1"></i>On Leave</span>',
        'active' => '<span class="badge bg-success text-white px-2 py-1">Active</span>',
        'inactive' => '<span class="badge bg-secondary text-white px-2 py-1">Inactive</span>',
        'pending' => '<span class="badge bg-warning text-dark px-2 py-1"><i class="fas fa-hourglass-half me-1"></i>Pending</span>',
        'approved' => '<span class="badge bg-success text-white px-2 py-1"><i class="fas fa-check me-1"></i>Approved</span>',
        'rejected' => '<span class="badge bg-danger text-white px-2 py-1"><i class="fas fa-ban me-1"></i>Rejected</span>',
        default => '<span class="badge bg-secondary px-2 py-1">' . e(ucfirst($status)) . '</span>'
    };
}

function generate_student_id(): string {
    $pdo = get_db_connection();
    $year = date('Y');
    $stmt = $pdo->query("SELECT student_id FROM students WHERE student_id LIKE 'STU{$year}%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last && preg_match('/STU\d{4}(\d{3,})/', (string)$last, $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $next = 1;
    }
    return sprintf("STU%s%03d", $year, $next);
}

function generate_teacher_id(): string {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT teacher_id FROM teachers WHERE teacher_id LIKE 'TCH%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last && preg_match('/TCH(\d+)/', (string)$last, $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $next = 1001;
    }
    return "TCH" . $next;
}

function generate_principal_id(): string {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT principal_id FROM principals WHERE principal_id LIKE 'PRN%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last && preg_match('/PRN(\d+)/', (string)$last, $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $next = 101;
    }
    return "PRN" . $next;
}

function calculate_student_stats(int $studentId, ?int $academicYearId = null): array {
    $pdo = get_db_connection();
    $params = [$studentId];
    $sql = "SELECT 
                COUNT(*) as total_working_days,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days
            FROM daily_attendance 
            WHERE student_id = ?";
    if ($academicYearId) {
        $sql .= " AND academic_year_id = ?";
        $params[] = $academicYearId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats = $stmt->fetch() ?: [
        'total_working_days' => 0,
        'present_days' => 0,
        'absent_days' => 0,
        'late_days' => 0,
        'half_days' => 0
    ];

    // Standard formula: Present + (Late / 2 or 1) + (Half Day * 0.5)
    // Most educational institutions count: Present + Late as present (or late counts as present with remark), half-day = 0.5
    $effectivePresent = (int)$stats['present_days'] + (int)$stats['late_days'] + ((int)$stats['half_days'] * 0.5);
    $total = (int)$stats['total_working_days'];
    $percentage = $total > 0 ? round(($effectivePresent / $total) * 100, 2) : 0.00;

    $stats['effective_present'] = $effectivePresent;
    $stats['percentage'] = $percentage;
    return $stats;
}

function get_low_attendance_students(float $threshold = 75.0, ?int $classId = null, ?int $sectionId = null): array {
    $pdo = get_db_connection();
    $activeYear = get_active_academic_year();
    $yearId = $activeYear['id'] ?? 1;

    $params = [$yearId];
    $sql = "SELECT s.id, s.student_id, s.roll_no, s.full_name, s.photo,
                   c.name as class_name, c.type as class_type, sec.name as section_name,
                   COUNT(a.id) as total_days,
                   COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                   COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                   COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                   COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) as half_day_count
            FROM students s
            JOIN classes c ON s.class_id = c.id
            JOIN sections sec ON s.section_id = sec.id
            LEFT JOIN daily_attendance a ON s.id = a.student_id AND a.academic_year_id = ?
            WHERE 1=1";

    if ($classId) {
        $sql .= " AND s.class_id = ?";
        $params[] = $classId;
    }
    if ($sectionId) {
        $sql .= " AND s.section_id = ?";
        $params[] = $sectionId;
    }

    $sql .= " GROUP BY s.id ORDER BY c.numeric_level, sec.name, s.roll_no";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allStudents = $stmt->fetchAll();

    $lowAttendance = [];
    foreach ($allStudents as $row) {
        $total = (int)$row['total_days'];
        $effective = (int)$row['present_count'] + (int)$row['late_count'] + ((int)$row['half_day_count'] * 0.5);
        $pct = $total > 0 ? round(($effective / $total) * 100, 2) : 0.0;
        if ($total > 0 && $pct < $threshold) {
            $row['percentage'] = $pct;
            $lowAttendance[] = $row;
        }
    }
    return $lowAttendance;
}

function handle_profile_upload(array $file): ?string {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) {
        return null;
    }
    if ($file['size'] > 3 * 1024 * 1024) { // 3MB limit
        return null;
    }
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'jpg'
    };
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $filename = 'profile_' . bin2hex(random_bytes(10)) . '.' . $ext;
    $targetPath = UPLOAD_DIR . $filename;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'assets/uploads/profiles/' . $filename;
    }
    return null;
}
