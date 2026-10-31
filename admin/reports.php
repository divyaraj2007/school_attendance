<?php
// admin/reports.php - Comprehensive Reports & Analytics
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'principal']);

$pageTitle = 'Reports & Analytics';
$pdo = get_db_connection();

$reportType = $_GET['type'] ?? 'monthly_class';
$classId = (int)($_GET['class_id'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');
$threshold = (float)get_setting('attendance_threshold', '75');

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_report_' . $reportType . '_' . date('Ymd') . '.csv');
    $out = fopen('php://output', 'w');

    if ($reportType === 'low_attendance') {
        fputcsv($out, ['Student ID', 'Roll No', 'Name', 'Class', 'Section', 'Present Days', 'Total Days', 'Attendance %']);
        $students = get_low_attendance_students($threshold, ($classId > 0 ? $classId : null));
        foreach ($students as $s) {
            fputcsv($out, [$s['student_id'], $s['roll_no'], $s['full_name'], $s['class_name'], $s['section_name'], $s['present_count'], $s['total_days'], $s['percentage'] . '%']);
        }
    } else {
        fputcsv($out, ['Date', 'Class', 'Section', 'Roll No', 'Student Name', 'Status']);
        $sql = "SELECT a.attendance_date, c.name as class_name, sec.name as section_name, s.roll_no, s.full_name, a.status 
                FROM daily_attendance a
                JOIN students s ON a.student_id = s.id
                JOIN classes c ON a.class_id = c.id
                JOIN sections sec ON a.section_id = sec.id
                WHERE a.attendance_date LIKE ? " . ($classId > 0 ? "AND a.class_id = ?" : "") . "
                ORDER BY a.attendance_date DESC, c.numeric_level, s.roll_no";
        $params = [$month . '%'];
        if ($classId > 0) $params[] = $classId;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$r['attendance_date'], $r['class_name'], $r['section_name'], $r['roll_no'], $r['full_name'], ucfirst($r['status'])]);
        }
    }
    fclose($out);
    exit;
}

// Fetch Report Data for View
$reportRows = [];
$lowStudents = [];
if ($reportType === 'low_attendance') {
    $lowStudents = get_low_attendance_students($threshold, ($classId > 0 ? $classId : null));
} else {
    $sql = "SELECT a.attendance_date, c.name as class_name, sec.name as section_name, s.roll_no, s.full_name as student_name, s.student_id, a.status
            FROM daily_attendance a
            JOIN students s ON a.student_id = s.id
            JOIN classes c ON a.class_id = c.id
            JOIN sections sec ON a.section_id = sec.id
            WHERE a.attendance_date LIKE ? " . ($classId > 0 ? "AND a.class_id = ?" : "") . "
            ORDER BY a.attendance_date DESC, c.numeric_level, s.roll_no";
    $params = [$month . '%'];
    if ($classId > 0) $params[] = $classId;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportRows = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-file-invoice me-2 text-primary"></i> Attendance Reports & Analytics</h1>
    <p class="page-subtitle">Generate, filter, print, and export attendance records and compliance analytics.</p>
  </div>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-print me-1"></i> Print / Save PDF
    </button>
    <a href="?type=<?= e($reportType) ?>&month=<?= e($month) ?>&class_id=<?= $classId ?>&export=csv" class="btn btn-success btn-sm">
      <i class="fas fa-file-excel me-1"></i> Export to Excel (CSV)
    </a>
  </div>
</div>

<!-- Report Controls -->
<div class="card card-custom mb-4 no-print">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label small fw-bold mb-1">Report Type</label>
        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="monthly_class" <?= ($reportType === 'monthly_class') ? 'selected' : '' ?>>Monthly Class Attendance</option>
          <option value="low_attendance" <?= ($reportType === 'low_attendance') ? 'selected' : '' ?>>Low Attendance Students (&lt; <?= $threshold ?>%)</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Class Filter</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">All Classes</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($classId === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Month</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?= e($month) ?>" onchange="this.form.submit()">
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <i class="fas fa-filter me-1"></i> Apply
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Printable Header -->
<div class="d-none d-print-block text-center mb-4 pb-3 border-bottom">
  <h2 class="fw-bold mb-1"><?= e(get_setting('school_name')) ?></h2>
  <div class="text-secondary small"><?= e(get_setting('school_address')) ?></div>
  <h4 class="mt-3 fw-bold text-uppercase"><?= ($reportType === 'low_attendance') ? "Low Attendance Report (< {$threshold}%)" : "Monthly Attendance Report ({$month})" ?></h4>
</div>

<!-- Table Output -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title">
      <?= ($reportType === 'low_attendance') ? "Low Attendance Defaulters List" : "Monthly Attendance Register (" . date('F Y', strtotime($month . '-01')) . ")" ?>
    </h5>
    <span class="badge bg-primary-subtle text-primary">
      <?= ($reportType === 'low_attendance') ? count($lowStudents) . ' Defaulters' : count($reportRows) . ' Records' ?>
    </span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <?php if ($reportType === 'low_attendance'): ?>
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Student ID</th>
              <th>Roll #</th>
              <th>Student Name</th>
              <th>Class & Section</th>
              <th>Present / Total</th>
              <th>Attendance %</th>
              <th>Deficit Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($lowStudents)): ?>
              <?php foreach ($lowStudents as $s): ?>
                <tr>
                  <td><code><?= e($s['student_id']) ?></code></td>
                  <td>#<?= $s['roll_no'] ?></td>
                  <td><strong><?= e($s['full_name']) ?></strong></td>
                  <td><?= e($s['class_name']) ?> - Sec <?= e($s['section_name']) ?></td>
                  <td><?= $s['present_count'] ?> / <?= $s['total_days'] ?></td>
                  <td>
                    <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 fw-bold fs-6">
                      <?= $s['percentage'] ?>%
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i>Notice Required</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No students currently have attendance below <?= $threshold ?>%.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      <?php else: ?>
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>Class & Section</th>
              <th>Roll #</th>
              <th>Student Name</th>
              <th>Student ID</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($reportRows)): ?>
              <?php foreach ($reportRows as $r): ?>
                <tr>
                  <td><?= format_date($r['attendance_date']) ?></td>
                  <td><span class="badge bg-light text-dark border"><?= e($r['class_name']) ?> - <?= e($r['section_name']) ?></span></td>
                  <td>#<?= $r['roll_no'] ?></td>
                  <td><strong><?= e($r['student_name']) ?></strong></td>
                  <td><code><?= e($r['student_id']) ?></code></td>
                  <td><?= status_badge($r['status']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No attendance entries recorded for the selected period.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
