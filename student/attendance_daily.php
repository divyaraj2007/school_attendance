<?php
// student/attendance_daily.php - Student Daily Attendance History
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$pageTitle = 'My Daily Attendance';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$sStmt = $pdo->prepare("SELECT id, full_name, roll_no FROM students WHERE user_id = ?");
$sStmt->execute([$currentUserId]);
$student = $sStmt->fetch();
$studentId = (int)($student['id'] ?? 0);

$month = $_GET['month'] ?? date('Y-m');

$attStmt = $pdo->prepare("SELECT a.*, c.name as class_name, sec.name as section_name
                         FROM daily_attendance a
                         JOIN classes c ON a.class_id = c.id
                         JOIN sections sec ON a.section_id = sec.id
                         WHERE a.student_id = ? AND a.attendance_date LIKE ?
                         ORDER BY a.attendance_date DESC");
$attStmt->execute([$studentId, $month . '%']);
$records = $attStmt->fetchAll();

// Month summary
$presentCount = 0;
$absentCount = 0;
$lateCount = 0;
$halfDayCount = 0;
foreach ($records as $r) {
    if ($r['status'] === 'present') $presentCount++;
    elseif ($r['status'] === 'absent') $absentCount++;
    elseif ($r['status'] === 'late') $lateCount++;
    elseif ($r['status'] === 'half_day') $halfDayCount++;
}
$totalRecords = count($records);
$effective = $presentCount + $lateCount + ($halfDayCount * 0.5);
$monthPct = $totalRecords > 0 ? round(($effective / $totalRecords) * 100, 1) : 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-calendar-check me-2 text-primary"></i> Daily Attendance Records</h1>
    <p class="page-subtitle">Monthly day-by-day record of your attendance status.</p>
  </div>
</div>

<!-- Month Filter & Monthly Stats -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-4">
        <form method="GET" action="">
          <label class="form-label small fw-bold mb-1">Select Month</label>
          <div class="input-group">
            <input type="month" name="month" class="form-control" value="<?= e($month) ?>" onchange="this.form.submit()">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
          </div>
        </form>
      </div>

      <div class="col-md-8">
        <div class="d-flex justify-content-md-end gap-3 flex-wrap">
          <div class="text-center">
            <span class="text-muted small d-block">Monthly %</span>
            <strong class="fs-5 <?= ($monthPct >= 75) ? 'text-success' : 'text-danger' ?>"><?= $monthPct ?>%</strong>
          </div>
          <div class="text-center">
            <span class="text-muted small d-block">Present</span>
            <strong class="fs-5 text-success"><?= $presentCount ?></strong>
          </div>
          <div class="text-center">
            <span class="text-muted small d-block">Absent</span>
            <strong class="fs-5 text-danger"><?= $absentCount ?></strong>
          </div>
          <div class="text-center">
            <span class="text-muted small d-block">Late</span>
            <strong class="fs-5 text-warning"><?= $lateCount ?></strong>
          </div>
          <div class="text-center">
            <span class="text-muted small d-block">Half Day</span>
            <strong class="fs-5 text-warning-emphasis"><?= $halfDayCount ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Records Table -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title"><?= date('F Y', strtotime($month . '-01')) ?> Attendance Register</h5>
    <span class="badge bg-primary-subtle text-primary"><?= $totalRecords ?> Sessions</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Day of Week</th>
            <th>Class Division</th>
            <th>Status</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($records)): ?>
            <?php foreach ($records as $r): ?>
              <tr>
                <td><strong><?= format_date($r['attendance_date']) ?></strong></td>
                <td><small class="text-secondary"><?= date('l', strtotime($r['attendance_date'])) ?></small></td>
                <td><span class="badge bg-light text-dark border"><?= e($r['class_name']) ?> - <?= e($r['section_name']) ?></span></td>
                <td><?= status_badge($r['status']) ?></td>
                <td><small class="text-muted"><?= e($r['remarks'] ?? '-') ?></small></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center py-4 text-muted">No attendance marked for this month.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
