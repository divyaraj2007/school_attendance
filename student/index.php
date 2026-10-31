<?php
// student/index.php - Student Portal Dashboard
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$pageTitle = 'Student Portal';
$pdo = get_db_connection();
$today = date('Y-m-d');
$currentUserId = (int)$_SESSION['user']['id'];
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

// Fetch Student Profile
$sStmt = $pdo->prepare("SELECT s.*, c.name as class_name, sec.name as section_name
                        FROM students s
                        JOIN classes c ON s.class_id = c.id
                        JOIN sections sec ON s.section_id = sec.id
                        WHERE s.user_id = ?");
$sStmt->execute([$currentUserId]);
$student = $sStmt->fetch();
$studentId = (int)($student['id'] ?? 0);

// Calculate Student Cumulative Statistics
$stats = calculate_student_stats($studentId, $yearId);

// Today's Attendance Status for this student
$todayStmt = $pdo->prepare("SELECT status, remarks FROM daily_attendance WHERE student_id = ? AND attendance_date = ?");
$todayStmt->execute([$studentId, $today]);
$todayRecord = $todayStmt->fetch();

// Recent Daily Attendance Logs (Last 10)
$recentStmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE student_id = ? ORDER BY attendance_date DESC LIMIT 7");
$recentStmt->execute([$studentId]);
$recentAttendance = $recentStmt->fetchAll();

// Recent Announcements for students
$annStmt = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 AND target_role IN ('all', 'student') ORDER BY id DESC LIMIT 3");
$announcements = $annStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-user-graduate me-2 text-primary"></i> Student Attendance Dashboard</h1>
    <p class="page-subtitle">Welcome, <strong><?= e($student['full_name']) ?></strong> &bull; <?= e($student['class_name']) ?> (Section <?= e($student['section_name']) ?>) &bull; Roll #<?= $student['roll_no'] ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/student/calendar.php" class="btn btn-outline-primary btn-sm">
      <i class="fas fa-calendar-alt me-1"></i> Attendance Calendar
    </a>
  </div>
</div>

<!-- Today's Status Banner -->
<div class="card card-custom mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
  <div class="card-body py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
      <div class="p-2 bg-white rounded-circle shadow-sm">
        <i class="fas fa-calendar-check text-primary fa-2x"></i>
      </div>
      <div>
        <div class="small text-secondary fw-semibold">Today's Class Attendance (<?= date('l, F j') ?>)</div>
        <div class="fs-5 fw-bold text-dark">
          <?php if ($todayRecord): ?>
            Status: <?= status_badge($todayRecord['status']) ?>
            <?php if (!empty($todayRecord['remarks'])): ?>
              <small class="text-muted fw-normal ms-2">(<?= e($todayRecord['remarks']) ?>)</small>
            <?php endif; ?>
          <?php else: ?>
            <span class="badge bg-secondary-subtle text-secondary"><i class="fas fa-hourglass-start me-1"></i> Not Yet Marked Today</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div>
      <span class="badge <?= ($stats['percentage'] >= 75) ? 'bg-success' : 'bg-danger' ?> fs-6 px-3 py-2">
        Cumulative: <?= $stats['percentage'] ?>%
      </span>
    </div>
  </div>
</div>

<!-- 5 Stat Cards Required by Prompt -->
<div class="row g-3 mb-4">
  <!-- 1. Attendance Percentage -->
  <div class="col-xl-4 col-md-6">
    <div class="stat-card <?= ($stats['percentage'] >= 75) ? 'stat-green' : 'stat-red' ?>">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Attendance Percentage</div>
          <div class="stat-value <?= ($stats['percentage'] >= 75) ? 'text-success' : 'text-danger' ?>">
            <?= $stats['percentage'] ?>%
          </div>
          <div class="stat-trend text-muted">
            <?php if ($stats['percentage'] >= 75): ?>
              <i class="fas fa-shield-alt text-success me-1"></i> Good Standing (&gt;= 75% required)
            <?php else: ?>
              <i class="fas fa-exclamation-triangle text-danger me-1"></i> Defaulter Warning (&lt; 75%)
            <?php endif; ?>
          </div>
        </div>
        <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
      </div>
    </div>
  </div>

  <!-- 2. Total Working Days -->
  <div class="col-xl-2 col-md-6 col-sm-6">
    <div class="stat-card stat-blue">
      <div class="stat-title">Working Days</div>
      <div class="stat-value"><?= $stats['total_working_days'] ?></div>
      <div class="stat-trend text-muted">Total sessions</div>
    </div>
  </div>

  <!-- 3. Total Present Days -->
  <div class="col-xl-2 col-md-4 col-sm-6">
    <div class="stat-card stat-green">
      <div class="stat-title">Present Days</div>
      <div class="stat-value text-success"><?= $stats['present_days'] ?></div>
      <div class="stat-trend text-success"><i class="fas fa-check me-1"></i> Attended</div>
    </div>
  </div>

  <!-- 4. Total Absent Days -->
  <div class="col-xl-2 col-md-4 col-sm-6">
    <div class="stat-card stat-red">
      <div class="stat-title">Absent Days</div>
      <div class="stat-value text-danger"><?= $stats['absent_days'] ?></div>
      <div class="stat-trend text-danger"><i class="fas fa-times me-1"></i> Missed</div>
    </div>
  </div>

  <!-- 5. Total Late Days -->
  <div class="col-xl-2 col-md-4 col-sm-6">
    <div class="stat-card stat-amber">
      <div class="stat-title">Late Days</div>
      <div class="stat-value text-warning"><?= $stats['late_days'] ?></div>
      <div class="stat-trend text-muted">Late arrivals</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Recent Attendance History -->
  <div class="col-lg-7">
    <div class="card card-custom h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title"><i class="fas fa-history me-2 text-primary"></i> Recent Attendance History</h5>
        <a href="<?= BASE_URL ?>/student/history.php" class="btn btn-sm btn-link p-0 text-decoration-none">Full History</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Attendance Status</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recentAttendance)): ?>
                <?php foreach ($recentAttendance as $r): ?>
                  <tr>
                    <td><strong><?= format_date($r['attendance_date']) ?></strong></td>
                    <td><small class="text-secondary"><?= date('l', strtotime($r['attendance_date'])) ?></small></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><small class="text-muted"><?= e($r['remarks'] ?? '-') ?></small></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="text-center py-4 text-muted">No attendance entries recorded yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Announcements Notice Board -->
  <div class="col-lg-5">
    <div class="card card-custom h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title"><i class="fas fa-bullhorn me-2 text-primary"></i> School Notice Board</h5>
        <a href="<?= BASE_URL ?>/student/announcements.php" class="btn btn-sm btn-link p-0 text-decoration-none">All Notices</a>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($announcements)): ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($announcements as $ann): ?>
              <li class="list-group-item p-3">
                <div class="d-flex justify-content-between align-items-start mb-1">
                  <div class="fw-bold text-dark"><?= e($ann['title']) ?></div>
                  <small class="text-muted flex-shrink-0"><?= format_date($ann['created_at']) ?></small>
                </div>
                <div class="small text-secondary text-truncate"><?= e($ann['content']) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="p-4 text-center text-muted">
            <i class="fas fa-bell-slash text-secondary fa-2x mb-2 d-block"></i>
            No new notices posted on the board.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
