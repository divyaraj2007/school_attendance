<?php
// student/history.php - Full Attendance History
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$pageTitle = 'Attendance History';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$sStmt = $pdo->prepare("SELECT id, full_name, roll_no, student_id FROM students WHERE user_id = ?");
$sStmt->execute([$currentUserId]);
$student = $sStmt->fetch();
$studentId = (int)($student['id'] ?? 0);

$statusFilter = $_GET['status'] ?? 'all';

$where = ["a.student_id = ?"];
$params = [$studentId];

if ($statusFilter !== 'all' && in_array($statusFilter, ['present', 'absent', 'late', 'half_day'], true)) {
    $where[] = "a.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

$query = "SELECT a.*, c.name as class_name, sec.name as section_name
          FROM daily_attendance a
          JOIN classes c ON a.class_id = c.id
          JOIN sections sec ON a.section_id = sec.id
          WHERE {$whereClause}
          ORDER BY a.attendance_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$history = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-history me-2 text-primary"></i> Attendance History</h1>
    <p class="page-subtitle">Complete chronological record of all attendance sessions for your student account.</p>
  </div>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-print me-1"></i> Print History
    </button>
  </div>
</div>

<!-- Filter Bar -->
<div class="card card-custom mb-4 no-print">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-6">
        <label class="form-label small fw-bold mb-1">Filter by Attendance Status</label>
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="all" <?= ($statusFilter === 'all') ? 'selected' : '' ?>>All Statuses</option>
          <option value="present" <?= ($statusFilter === 'present') ? 'selected' : '' ?>>Present</option>
          <option value="absent" <?= ($statusFilter === 'absent') ? 'selected' : '' ?>>Absent</option>
          <option value="late" <?= ($statusFilter === 'late') ? 'selected' : '' ?>>Late</option>
          <option value="half_day" <?= ($statusFilter === 'half_day') ? 'selected' : '' ?>>Half Day</option>
        </select>
      </div>

      <div class="col-md-6 d-flex align-items-end">
        <a href="<?= BASE_URL ?>/student/history.php" class="btn btn-light btn-sm border text-secondary ms-auto">Reset Filter</a>
      </div>
    </form>
  </div>
</div>

<!-- History Table -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title">Student Record: <?= e($student['full_name']) ?> (<?= e($student['student_id']) ?>)</h5>
    <span class="badge bg-primary-subtle text-primary"><?= count($history) ?> Entries</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Day</th>
            <th>Class</th>
            <th>Attendance Status</th>
            <th>Recorded Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($history)): ?>
            <?php foreach ($history as $h): ?>
              <tr>
                <td><strong><?= format_date($h['attendance_date']) ?></strong></td>
                <td><small class="text-secondary"><?= date('l', strtotime($h['attendance_date'])) ?></small></td>
                <td><span class="badge bg-light text-dark border"><?= e($h['class_name']) ?> - <?= e($h['section_name']) ?></span></td>
                <td><?= status_badge($h['status']) ?></td>
                <td><small class="text-muted"><?= e($h['remarks'] ?? '-') ?></small></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center py-4 text-muted">No attendance logs found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
