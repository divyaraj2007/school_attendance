<?php
// admin/attendance.php - Attendance Overview (Daily, Subject-Wise, Teacher)
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Attendance Overview';
$pdo = get_db_connection();

$tab = $_GET['tab'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');
$classId = (int)($_GET['class_id'] ?? 0);
$sectionId = (int)($_GET['section_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();

// 1. Daily Student Attendance Query
$dailyRecords = [];
if ($tab === 'daily') {
    $where = ["a.attendance_date = ?"];
    $params = [$date];

    if ($classId > 0) {
        $where[] = "a.class_id = ?";
        $params[] = $classId;
    }
    if ($sectionId > 0) {
        $where[] = "a.section_id = ?";
        $params[] = $sectionId;
    }
    $whereClause = implode(' AND ', $where);

    $sql = "SELECT a.*, s.student_id, s.roll_no, s.full_name as student_name, c.name as class_name, sec.name as section_name, u.user_id as marked_by_user
            FROM daily_attendance a
            JOIN students s ON a.student_id = s.id
            JOIN classes c ON a.class_id = c.id
            JOIN sections sec ON a.section_id = sec.id
            JOIN users u ON a.marked_by = u.id
            WHERE {$whereClause}
            ORDER BY c.numeric_level, sec.name, s.roll_no ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dailyRecords = $stmt->fetchAll();
}

// 2. Subject-Wise Attendance Query
$subjectRecords = [];
if ($tab === 'subject') {
    $where = ["sa.attendance_date = ?"];
    $params = [$date];

    if ($classId > 0) {
        $where[] = "sa.class_id = ?";
        $params[] = $classId;
    }
    if ($sectionId > 0) {
        $where[] = "sa.section_id = ?";
        $params[] = $sectionId;
    }
    if ($subjectId > 0) {
        $where[] = "sa.subject_id = ?";
        $params[] = $subjectId;
    }
    $whereClause = implode(' AND ', $where);

    $sql = "SELECT sa.*, s.student_id, s.roll_no, s.full_name as student_name, sub.name as subject_name, sub.code as subject_code, c.name as class_name, sec.name as section_name, u.user_id as marked_by_user
            FROM subject_attendance sa
            JOIN students s ON sa.student_id = s.id
            JOIN subjects sub ON sa.subject_id = sub.id
            JOIN classes c ON sa.class_id = c.id
            JOIN sections sec ON sa.section_id = sec.id
            JOIN users u ON sa.marked_by = u.id
            WHERE {$whereClause}
            ORDER BY c.numeric_level, sec.name, sa.lecture_period, s.roll_no ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subjectRecords = $stmt->fetchAll();
}

// 3. Teacher Attendance Query
$teacherRecords = [];
if ($tab === 'teacher') {
    $sql = "SELECT ta.*, t.teacher_id, t.full_name as teacher_name, t.qualification, u.user_id as marked_by_user
            FROM teacher_attendance ta
            JOIN teachers t ON ta.teacher_id = t.id
            JOIN users u ON ta.marked_by = u.id
            WHERE ta.attendance_date = ?
            ORDER BY t.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date]);
    $teacherRecords = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-clipboard-check me-2 text-primary"></i> Institutional Attendance Overview</h1>
    <p class="page-subtitle">Inspect recorded attendance records across Daily, Subject-wise, and Faculty logs.</p>
  </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-pills mb-4">
  <li class="nav-item">
    <a class="nav-link <?= ($tab === 'daily') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/attendance.php?tab=daily&date=<?= e($date) ?>">
      <i class="fas fa-calendar-day me-1"></i> Daily Student Attendance
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= ($tab === 'subject') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/attendance.php?tab=subject&date=<?= e($date) ?>">
      <i class="fas fa-book-reader me-1"></i> Subject-Wise Attendance
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= ($tab === 'teacher') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/attendance.php?tab=teacher&date=<?= e($date) ?>">
      <i class="fas fa-chalkboard-teacher me-1"></i> Teacher Attendance
    </a>
  </li>
</ul>

<!-- Filter Bar -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">

      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()">
      </div>

      <?php if ($tab !== 'teacher'): ?>
        <div class="col-md-3">
          <label class="form-label small fw-bold mb-1">Class</label>
          <select name="class_id" class="form-select" onchange="this.form.submit()">
            <option value="0">All Classes</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($classId === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($tab === 'subject'): ?>
          <div class="col-md-3">
            <label class="form-label small fw-bold mb-1">Subject</label>
            <select name="subject_id" class="form-select" onchange="this.form.submit()">
              <option value="0">All Subjects</option>
              <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($subjectId === (int)$s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<!-- DATA TABLES -->
<?php if ($tab === 'daily'): ?>
  <div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title">Daily Attendance Logs: <?= format_date($date) ?></h5>
      <span class="badge bg-primary-subtle text-primary"><?= count($dailyRecords) ?> Records</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
          <thead>
            <tr>
              <th>Roll #</th>
              <th>Student Name</th>
              <th>Student ID</th>
              <th>Class & Section</th>
              <th>Status</th>
              <th>Marked By</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($dailyRecords)): ?>
              <?php foreach ($dailyRecords as $r): ?>
                <tr>
                  <td><strong>#<?= $r['roll_no'] ?></strong></td>
                  <td><strong><?= e($r['student_name']) ?></strong></td>
                  <td><code><?= e($r['student_id']) ?></code></td>
                  <td><span class="badge bg-light text-dark border"><?= e($r['class_name']) ?> - <?= e($r['section_name']) ?></span></td>
                  <td><?= status_badge($r['status']) ?></td>
                  <td><small class="text-secondary"><?= e($r['marked_by_user']) ?></small></td>
                  <td><small class="text-muted"><?= format_date($r['created_at'], 'h:i A') ?></small></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No daily attendance submitted for this date.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'subject'): ?>
  <div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title">Subject-Wise Attendance Logs: <?= format_date($date) ?></h5>
      <span class="badge bg-primary-subtle text-primary"><?= count($subjectRecords) ?> Records</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
          <thead>
            <tr>
              <th>Lecture / Period</th>
              <th>Subject</th>
              <th>Student</th>
              <th>Class</th>
              <th>Status</th>
              <th>Marked By</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($subjectRecords)): ?>
              <?php foreach ($subjectRecords as $r): ?>
                <tr>
                  <td><span class="badge bg-secondary">Period <?= $r['lecture_period'] ?></span></td>
                  <td><strong><?= e($r['subject_name']) ?></strong> <small class="text-muted">(<?= e($r['subject_code']) ?>)</small></td>
                  <td><?= e($r['student_name']) ?> <small class="text-muted">(<?= e($r['student_id']) ?>)</small></td>
                  <td><span class="badge bg-light text-dark border"><?= e($r['class_name']) ?> - <?= e($r['section_name']) ?></span></td>
                  <td><?= status_badge($r['status']) ?></td>
                  <td><small class="text-secondary"><?= e($r['marked_by_user']) ?></small></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No subject attendance recorded for this date.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'teacher'): ?>
  <div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title">Teacher Attendance Logs: <?= format_date($date) ?></h5>
      <span class="badge bg-primary-subtle text-primary"><?= count($teacherRecords) ?> Teachers Marked</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
          <thead>
            <tr>
              <th>Teacher ID</th>
              <th>Faculty Name</th>
              <th>Qualification</th>
              <th>Status</th>
              <th>Remarks</th>
              <th>Recorded By</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($teacherRecords)): ?>
              <?php foreach ($teacherRecords as $r): ?>
                <tr>
                  <td><code><?= e($r['teacher_id']) ?></code></td>
                  <td><strong><?= e($r['teacher_name']) ?></strong></td>
                  <td><small class="text-muted"><?= e($r['qualification']) ?></small></td>
                  <td><?= status_badge($r['status']) ?></td>
                  <td><small class="text-muted"><?= e($r['remarks'] ?? '-') ?></small></td>
                  <td><small class="text-secondary"><?= e($r['marked_by_user']) ?></small></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">Teacher attendance has not yet been marked for <?= format_date($date) ?>.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
