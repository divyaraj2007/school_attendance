<?php
// principal/low_attendance.php - Low Attendance Monitoring & Alerts (< 75%)
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['principal', 'admin']);

$pageTitle = 'Low Attendance Students';
$pdo = get_db_connection();
$threshold = (float)get_setting('attendance_threshold', '75');

$classId = (int)($_GET['class_id'] ?? 0);
$sectionId = (int)($_GET['section_id'] ?? 0);

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$lowStudents = get_low_attendance_students($threshold, ($classId > 0 ? $classId : null), ($sectionId > 0 ? $sectionId : null));

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=low_attendance_defaulters_' . date('Ymd') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Roll No', 'Full Name', 'Class', 'Section', 'Total Working Days', 'Present Days', 'Absent Days', 'Attendance %']);
    foreach ($lowStudents as $s) {
        fputcsv($out, [
            $s['student_id'],
            $s['roll_no'],
            $s['full_name'],
            $s['class_name'],
            $s['section_name'],
            $s['total_days'],
            $s['present_count'],
            $s['absent_count'],
            $s['percentage'] . '%'
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Low Attendance Defaulter List</h1>
    <p class="page-subtitle">Students with cumulative attendance percentage below the institutional threshold of <strong><?= $threshold ?>%</strong>.</p>
  </div>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-print me-1"></i> Print Report
    </button>
    <a href="?class_id=<?= $classId ?>&export=csv" class="btn btn-danger btn-sm">
      <i class="fas fa-file-excel me-1"></i> Export Defaulters (CSV)
    </a>
  </div>
</div>

<!-- Filter Bar -->
<div class="card card-custom mb-4 no-print">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Filter by Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">All Classes & Standards</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($classId === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Threshold Criterion</label>
        <input type="text" class="form-control form-control-sm bg-light" value="&lt; <?= $threshold ?>% Required Compliance" readonly>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <a href="<?= BASE_URL ?>/principal/low_attendance.php" class="btn btn-light btn-sm w-100 border text-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Defaulter Table -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center bg-danger-subtle text-danger border-danger">
    <h5 class="card-title text-danger mb-0">
      <i class="fas fa-user-slash me-2"></i> Flagged Defaulters: <?= count($lowStudents) ?> Students
    </h5>
    <span class="badge bg-danger">Critical Attention Required</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Student Name</th>
            <th>Student ID</th>
            <th>Roll #</th>
            <th>Class & Section</th>
            <th>Total Working Days</th>
            <th>Present</th>
            <th>Absent</th>
            <th>Attendance %</th>
            <th>Compliance Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($lowStudents)): ?>
            <?php foreach ($lowStudents as $s): ?>
              <tr>
                <td><strong><?= e($s['full_name']) ?></strong></td>
                <td><code><?= e($s['student_id']) ?></code></td>
                <td>#<?= $s['roll_no'] ?></td>
                <td><span class="badge bg-light text-dark border"><?= e($s['class_name']) ?> - Sec <?= e($s['section_name']) ?></span></td>
                <td><?= $s['total_days'] ?> days</td>
                <td><span class="text-success fw-bold"><?= $s['present_count'] ?></span></td>
                <td><span class="text-danger fw-bold"><?= $s['absent_count'] ?></span></td>
                <td>
                  <span class="badge bg-danger text-white fs-6 px-3 py-1">
                    <?= $s['percentage'] ?>%
                  </span>
                </td>
                <td>
                  <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1">
                    <i class="fas fa-bell me-1"></i> Defaulter Warning
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="text-center py-5 text-muted">
                <i class="fas fa-check-circle text-success fa-3x mb-2 d-block"></i>
                <h5>Excellent Compliance!</h5>
                <div>No students currently fall below the <?= $threshold ?>% minimum attendance requirement.</div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
