<?php
// student/attendance_subject.php - Student Subject-Wise Attendance
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$pageTitle = 'Subject-Wise Attendance';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$sStmt = $pdo->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$sStmt->execute([$currentUserId]);
$student = $sStmt->fetch();
$studentId = (int)($student['id'] ?? 0);
$classId = (int)($student['class_id'] ?? 0);

// Fetch subjects for student's class
$subjects = $pdo->prepare("SELECT s.* 
                           FROM subjects s
                           JOIN class_subjects cs ON s.id = cs.subject_id
                           WHERE cs.class_id = ?
                           ORDER BY s.name ASC");
$subjects->execute([$classId]);
$subjectList = $subjects->fetchAll();

// Calculate percentage for each subject
$subjectStats = [];
foreach ($subjectList as $sub) {
    $subStmt = $pdo->prepare("SELECT 
                                 COUNT(*) as total_lectures,
                                 COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                                 COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                                 COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                                 COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_day_count
                              FROM subject_attendance
                              WHERE student_id = ? AND subject_id = ?");
    $subStmt->execute([$studentId, $sub['id']]);
    $data = $subStmt->fetch();

    $tot = (int)$data['total_lectures'];
    $eff = (int)$data['present_count'] + (int)$data['late_count'] + ((int)$data['half_day_count'] * 0.5);
    $pct = $tot > 0 ? round(($eff / $tot) * 100, 1) : 100.0;

    $subjectStats[] = [
        'subject' => $sub,
        'total' => $tot,
        'present' => (int)$data['present_count'],
        'absent' => (int)$data['absent_count'],
        'late' => (int)$data['late_count'],
        'percentage' => $pct
    ];
}

// Recent Subject Lecture Logs
$recentStmt = $pdo->prepare("SELECT sa.*, s.name as subject_name, s.code as subject_code
                             FROM subject_attendance sa
                             JOIN subjects s ON sa.subject_id = s.id
                             WHERE sa.student_id = ?
                             ORDER BY sa.attendance_date DESC, sa.lecture_period DESC
                             LIMIT 50");
$recentStmt->execute([$studentId]);
$recentSubjectLogs = $recentStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-book-open me-2 text-primary"></i> Subject-Wise Attendance Breakdown</h1>
    <p class="page-subtitle">Track lecture attendance requirements and percentages across all academic courses.</p>
  </div>
</div>

<!-- Subject Cards -->
<div class="row g-3 mb-4">
  <?php foreach ($subjectStats as $ss): ?>
    <?php
      $sub = $ss['subject'];
      $pct = $ss['percentage'];
      $isGood = $pct >= 75;
    ?>
    <div class="col-md-6 col-lg-4">
      <div class="card card-custom h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="badge bg-light text-dark border"><?= e($sub['code']) ?></span>
              <h5 class="fw-bold text-dark mt-1 mb-0"><?= e($sub['name']) ?></h5>
            </div>
            <span class="badge <?= $isGood ? 'bg-success' : 'bg-danger' ?> fs-6 px-2 py-1">
              <?= $pct ?>%
            </span>
          </div>

          <div class="progress my-3" style="height: 8px;">
            <div class="progress-bar <?= $isGood ? 'bg-success' : 'bg-danger' ?>" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>

          <div class="d-flex justify-content-between text-muted small">
            <span>Delivered: <strong><?= $ss['total'] ?></strong></span>
            <span>Attended: <strong class="text-success"><?= $ss['present'] ?></strong></span>
            <span>Absent: <strong class="text-danger"><?= $ss['absent'] ?></strong></span>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Detailed Logs -->
<div class="card card-custom">
  <div class="card-header">
    <h5 class="card-title">Subject Lecture Attendance Log</h5>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Lecture / Period</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($recentSubjectLogs)): ?>
            <?php foreach ($recentSubjectLogs as $log): ?>
              <tr>
                <td><strong><?= format_date($log['attendance_date']) ?></strong></td>
                <td><span class="badge bg-secondary">Period <?= $log['lecture_period'] ?></span></td>
                <td><strong><?= e($log['subject_name']) ?></strong> <small class="text-muted">(<?= e($log['subject_code']) ?>)</small></td>
                <td><?= status_badge($log['status']) ?></td>
                <td><small class="text-muted"><?= e($log['remarks'] ?? '-') ?></small></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center py-4 text-muted">No subject attendance logs recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
