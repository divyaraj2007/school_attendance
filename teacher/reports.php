<?php
// teacher/reports.php - Teacher Attendance Reports
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'Attendance Reports';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacherId = (int)$tStmt->fetchColumn();

// Fetch assigned classes
$assignStmt = $pdo->prepare("SELECT DISTINCT ta.class_id, ta.section_id, c.name as class_name, sec.name as section_name
                             FROM teacher_assignments ta
                             JOIN classes c ON ta.class_id = c.id
                             JOIN sections sec ON ta.section_id = sec.id
                             WHERE ta.teacher_id = ?");
$assignStmt->execute([$teacherId]);
$assignedPairs = $assignStmt->fetchAll();

$month = $_GET['month'] ?? date('Y-m');
$selectedClassId = (int)($_GET['class_id'] ?? ($assignedPairs[0]['class_id'] ?? 0));

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=teacher_class_report_' . $month . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Roll No', 'Student ID', 'Student Name', 'Total Working Days', 'Present Days', 'Absent Days', 'Attendance %']);

    $sql = "SELECT s.roll_no, s.student_id, s.full_name,
                   COUNT(a.id) as total_days,
                   COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                   COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                   COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                   COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) as half_day_count
            FROM students s
            LEFT JOIN daily_attendance a ON s.id = a.student_id AND a.attendance_date LIKE ?
            WHERE s.class_id = ?
            GROUP BY s.id
            ORDER BY s.roll_no ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$month . '%', $selectedClassId]);
    while ($r = $stmt->fetch()) {
        $tot = (int)$r['total_days'];
        $eff = (int)$r['present_count'] + (int)$r['late_count'] + ((int)$r['half_day_count'] * 0.5);
        $pct = $tot > 0 ? round(($eff / $tot) * 100, 1) : 0;
        fputcsv($out, [$r['roll_no'], $r['student_id'], $r['full_name'], $tot, $r['present_count'], $r['absent_count'], $pct . '%']);
    }
    fclose($out);
    exit;
}

// Student roster & attendance summary
$students = [];
if ($selectedClassId > 0) {
    $sql = "SELECT s.id, s.roll_no, s.student_id, s.full_name,
                   COUNT(a.id) as total_days,
                   COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                   COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                   COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                   COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) as half_day_count
            FROM students s
            LEFT JOIN daily_attendance a ON s.id = a.student_id AND a.attendance_date LIKE ?
            WHERE s.class_id = ?
            GROUP BY s.id
            ORDER BY s.roll_no ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$month . '%', $selectedClassId]);
    $students = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-chart-pie me-2 text-primary"></i> Class Attendance Reports</h1>
    <p class="page-subtitle">Monthly aggregate percentage and attendance registers for your assigned classes.</p>
  </div>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-print me-1"></i> Print
    </button>
    <a href="?class_id=<?= $selectedClassId ?>&month=<?= e($month) ?>&export=csv" class="btn btn-success btn-sm">
      <i class="fas fa-file-excel me-1"></i> Export (CSV)
    </a>
  </div>
</div>

<!-- Filter Bar -->
<div class="card card-custom mb-4 no-print">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Select Assigned Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($assignedPairs as $p): ?>
            <option value="<?= $p['class_id'] ?>" <?= ($selectedClassId === (int)$p['class_id']) ? 'selected' : '' ?>>
              <?= e($p['class_name']) ?> - Section <?= e($p['section_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Month</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?= e($month) ?>" onchange="this.form.submit()">
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary btn-sm w-100">Load Report</button>
      </div>
    </form>
  </div>
</div>

<!-- Report Table -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title">Class Performance: <?= date('F Y', strtotime($month . '-01')) ?></h5>
    <span class="badge bg-primary-subtle text-primary"><?= count($students) ?> Students Listed</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Roll #</th>
            <th>Student Name</th>
            <th>Student ID</th>
            <th>Working Days</th>
            <th>Present</th>
            <th>Absent</th>
            <th>Late</th>
            <th>Attendance %</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($students)): ?>
            <?php foreach ($students as $s): ?>
              <?php
                $tot = (int)$s['total_days'];
                $eff = (int)$s['present_count'] + (int)$s['late_count'] + ((int)$s['half_day_count'] * 0.5);
                $pct = $tot > 0 ? round(($eff / $tot) * 100, 1) : 0;
                $pctBadge = ($pct >= 75) ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger';
              ?>
              <tr>
                <td><strong>#<?= $s['roll_no'] ?></strong></td>
                <td><strong><?= e($s['full_name']) ?></strong></td>
                <td><code><?= e($s['student_id']) ?></code></td>
                <td><?= $tot ?></td>
                <td><span class="text-success fw-bold"><?= $s['present_count'] ?></span></td>
                <td><span class="text-danger fw-bold"><?= $s['absent_count'] ?></span></td>
                <td><span class="text-warning fw-bold"><?= $s['late_count'] ?></span></td>
                <td>
                  <span class="badge <?= $pctBadge ?> px-2 py-1 fw-bold fs-6">
                    <?= $pct ?>%
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">No students found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
