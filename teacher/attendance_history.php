<?php
// teacher/attendance_history.php - Attendance History & Correction Initiation
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'Attendance History';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacherId = (int)$tStmt->fetchColumn();

// Fetch teacher's assigned classes
$assignStmt = $pdo->prepare("SELECT DISTINCT class_id, section_id FROM teacher_assignments WHERE teacher_id = ?");
$assignStmt->execute([$teacherId]);
$pairs = $assignStmt->fetchAll();

$type = $_GET['type'] ?? 'daily';
$dateFilter = $_GET['date'] ?? '';

$records = [];
if (!empty($pairs)) {
    $csWhere = [];
    foreach ($pairs as $p) {
        $csWhere[] = "(a.class_id = {$p['class_id']} AND a.section_id = {$p['section_id']})";
    }
    $whereClause = "(" . implode(' OR ', $csWhere) . ")";
    $params = [];

    if (!empty($dateFilter)) {
        $whereClause .= " AND a.attendance_date = ?";
        $params[] = $dateFilter;
    }

    if ($type === 'daily') {
        $sql = "SELECT a.*, s.student_id, s.roll_no, s.full_name as student_name, c.name as class_name, sec.name as section_name
                FROM daily_attendance a
                JOIN students s ON a.student_id = s.id
                JOIN classes c ON a.class_id = c.id
                JOIN sections sec ON a.section_id = sec.id
                WHERE {$whereClause}
                ORDER BY a.attendance_date DESC, c.numeric_level, s.roll_no ASC
                LIMIT 150";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
    } else {
        $sql = "SELECT a.*, s.student_id, s.roll_no, s.full_name as student_name, sub.name as subject_name, c.name as class_name, sec.name as section_name
                FROM subject_attendance a
                JOIN students s ON a.student_id = s.id
                JOIN subjects sub ON a.subject_id = sub.id
                JOIN classes c ON a.class_id = c.id
                JOIN sections sec ON a.section_id = sec.id
                WHERE {$whereClause}
                ORDER BY a.attendance_date DESC, a.lecture_period, s.roll_no ASC
                LIMIT 150";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-history me-2 text-primary"></i> Attendance History & Logs</h1>
    <p class="page-subtitle">Inspect historical attendance logs and submit correction requests for discrepancies.</p>
  </div>
  <a href="<?= BASE_URL ?>/teacher/correction_request.php" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-spell-check me-1"></i> View Submitted Requests
  </a>
</div>

<!-- Type Switcher & Filter -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label small fw-bold mb-1">Attendance Module</label>
        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="daily" <?= ($type === 'daily') ? 'selected' : '' ?>>Daily Class Attendance</option>
          <option value="subject" <?= ($type === 'subject') ? 'selected' : '' ?>>Subject-Wise Attendance</option>
        </select>
      </div>

      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Filter by Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($dateFilter) ?>" onchange="this.form.submit()">
      </div>

      <div class="col-md-3 d-flex align-items-end">
        <a href="<?= BASE_URL ?>/teacher/attendance_history.php?type=<?= e($type) ?>" class="btn btn-light btn-sm w-100 border text-secondary">Clear Filter</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title">Past Attendance Records (Showing up to 150)</h5>
    <span class="badge bg-secondary-subtle text-secondary"><?= count($records) ?> Records</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Class</th>
            <?php if ($type === 'subject'): ?>
              <th>Subject & Period</th>
            <?php endif; ?>
            <th>Roll #</th>
            <th>Student Name</th>
            <th>Status</th>
            <th>Remarks</th>
            <th class="text-end">Correction</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($records)): ?>
            <?php foreach ($records as $r): ?>
              <tr>
                <td><strong><?= format_date($r['attendance_date']) ?></strong></td>
                <td><span class="badge bg-light text-dark border"><?= e($r['class_name']) ?> - Sec <?= e($r['section_name']) ?></span></td>
                <?php if ($type === 'subject'): ?>
                  <td>
                    <strong><?= e($r['subject_name']) ?></strong>
                    <span class="badge bg-secondary ms-1">Period <?= $r['lecture_period'] ?></span>
                  </td>
                <?php endif; ?>
                <td>#<?= $r['roll_no'] ?></td>
                <td>
                  <strong><?= e($r['student_name']) ?></strong>
                  <small class="text-muted d-block"><?= e($r['student_id']) ?></small>
                </td>
                <td><?= status_badge($r['status']) ?></td>
                <td><small class="text-muted"><?= e($r['remarks'] ?? '-') ?></small></td>
                <td class="text-end">
                  <a href="<?= BASE_URL ?>/teacher/correction_request.php?attendance_type=<?= e($type) ?>&attendance_id=<?= $r['id'] ?>&student_id=<?= $r['student_id'] ?>&original_status=<?= e($r['status']) ?>" class="btn btn-xs btn-outline-warning py-1 px-2 small">
                    <i class="fas fa-edit me-1"></i> Request Fix
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="<?= ($type === 'subject') ? '8' : '7' ?>" class="text-center py-4 text-muted">No attendance logs found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
