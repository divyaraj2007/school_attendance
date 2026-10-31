<?php
// teacher/index.php - Teacher Dashboard
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'Teacher Dashboard';
$pdo = get_db_connection();
$today = date('Y-m-d');
$currentUserId = (int)$_SESSION['user']['id'];

// Fetch Teacher Profile
$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacher = $tStmt->fetch();
$teacherId = (int)($teacher['id'] ?? 0);

// Fetch Assigned Classes & Sections
$aStmt = $pdo->prepare("SELECT ta.*, c.name as class_name, sec.name as section_name, sub.name as subject_name
                        FROM teacher_assignments ta
                        JOIN classes c ON ta.class_id = c.id
                        JOIN sections sec ON ta.section_id = sec.id
                        LEFT JOIN subjects sub ON ta.subject_id = sub.id
                        WHERE ta.teacher_id = ?");
$aStmt->execute([$teacherId]);
$assignments = $aStmt->fetchAll();

$assignedClassIds = array_unique(array_column($assignments, 'class_id'));
$assignedSectionIds = array_unique(array_column($assignments, 'section_id'));

// Calculate Stats
$assignedClassesCount = count($assignments);

$totalStudents = 0;
if (!empty($assignments)) {
    // Unique students across assigned classes and sections
    $classSecConditions = [];
    $params = [];
    foreach ($assignments as $a) {
        $classSecConditions[] = "(class_id = ? AND section_id = ?)";
        $params[] = $a['class_id'];
        $params[] = $a['section_id'];
    }
    $whereCS = implode(' OR ', $classSecConditions);
    $stuStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE {$whereCS}");
    $stuStmt->execute($params);
    $totalStudents = (int)$stuStmt->fetchColumn();
}

// Today's Attendance in Assigned Classes
$presentCount = 0;
$absentCount = 0;
$todayMarkedCount = 0;

if (!empty($assignments)) {
    $attStmt = $pdo->prepare("SELECT 
                                 COUNT(*) as total_marked,
                                 COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                                 COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count
                              FROM daily_attendance a
                              WHERE a.attendance_date = ? AND ({$whereCS})");
    $attStmt->execute(array_merge([$today], $params));
    $attData = $attStmt->fetch();
    $todayMarkedCount = (int)($attData['total_marked'] ?? 0);
    $presentCount = (int)($attData['present_count'] ?? 0);
    $absentCount = (int)($attData['absent_count'] ?? 0);
}

// Pending Attendance (Classes not yet marked today)
$pendingCount = 0;
foreach ($assignments as $a) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM daily_attendance WHERE class_id = ? AND section_id = ? AND attendance_date = ?");
    $chk->execute([$a['class_id'], $a['section_id'], $today]);
    if ((int)$chk->fetchColumn() === 0) {
        $pendingCount++;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-chalkboard-teacher me-2 text-primary"></i> Faculty Dashboard</h1>
    <p class="page-subtitle">Welcome, <strong><?= e($teacher['full_name'] ?? 'Teacher') ?></strong> &bull; <?= e($teacher['teacher_id'] ?? '') ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/teacher/attendance_daily.php" class="btn btn-primary btn-sm">
      <i class="fas fa-calendar-check me-1"></i> Mark Daily Attendance
    </a>
    <a href="<?= BASE_URL ?>/teacher/attendance_subject.php" class="btn btn-outline-primary btn-sm">
      <i class="fas fa-book-open me-1"></i> Mark Subject Attendance
    </a>
  </div>
</div>

<!-- 6 Required Teacher Dashboard Statistics -->
<div class="row g-3 mb-4">
  <!-- 1. Assigned Classes -->
  <div class="col-xl-2 col-md-4 col-sm-6">
    <div class="stat-card stat-blue">
      <div class="stat-title">Assigned Classes</div>
      <div class="stat-value"><?= $assignedClassesCount ?></div>
      <div class="stat-trend text-muted"><i class="fas fa-chalkboard me-1 text-primary"></i> Class/Subject slots</div>
    </div>
  </div>

  <!-- 2. Total Students -->
  <div class="col-xl-2 col-md-4 col-sm-6">
    <div class="stat-card stat-purple">
      <div class="stat-title">My Students</div>
      <div class="stat-value"><?= $totalStudents ?></div>
      <div class="stat-trend text-muted"><i class="fas fa-user-graduate me-1 text-purple"></i> In assigned sections</div>
    </div>
  </div>

  <!-- 3. Today's Attendance -->
  <div class="col-xl-2 col-md-4 col-sm-6">
    <div class="stat-card stat-indigo">
      <div class="stat-title">Today's Logged</div>
      <div class="stat-value"><?= $todayMarkedCount ?></div>
      <div class="stat-trend text-muted"><?= format_date($today) ?></div>
    </div>
  </div>

  <!-- 4. Present Students -->
  <div class="col-xl-2 col-md-4 col-sm-6">
    <div class="stat-card stat-green">
      <div class="stat-title">Present Today</div>
      <div class="stat-value text-success"><?= $presentCount ?></div>
      <div class="stat-trend text-success"><i class="fas fa-check-circle me-1"></i> Present in class</div>
    </div>
  </div>

  <!-- 5. Absent Students -->
  <div class="col-xl-2 col-md-4 col-sm-6">
    <div class="stat-card stat-red">
      <div class="stat-title">Absent Today</div>
      <div class="stat-value text-danger"><?= $absentCount ?></div>
      <div class="stat-trend text-danger"><i class="fas fa-times-circle me-1"></i> Absentees</div>
    </div>
  </div>

  <!-- 6. Pending Attendance -->
  <div class="col-xl-2 col-md-4 col-sm-6">
    <div class="stat-card stat-amber">
      <div class="stat-title">Pending Classes</div>
      <div class="stat-value text-warning"><?= $pendingCount ?></div>
      <div class="stat-trend text-muted"><?= $pendingCount > 0 ? 'Not marked yet' : 'All classes marked!' ?></div>
    </div>
  </div>
</div>

<!-- Assigned Classes Quick Grid -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card card-custom h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title"><i class="fas fa-school me-2 text-primary"></i> My Assigned Classes & Teaching Roster</h5>
        <a href="<?= BASE_URL ?>/teacher/my_classes.php" class="btn btn-sm btn-link p-0 text-decoration-none">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Class & Section</th>
                <th>Subject Taught</th>
                <th>Role</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($assignments)): ?>
                <?php foreach ($assignments as $as): ?>
                  <tr>
                    <td>
                      <span class="badge bg-light text-dark border fs-6 py-1 px-2">
                        <?= e($as['class_name']) ?> - Section <?= e($as['section_name']) ?>
                      </span>
                    </td>
                    <td><strong><?= e($as['subject_name'] ?? 'Class In-charge') ?></strong></td>
                    <td>
                      <?php if ($as['is_class_teacher'] == 1): ?>
                        <span class="badge bg-success-subtle text-success border border-success"><i class="fas fa-star me-1"></i>Class Teacher</span>
                      <?php else: ?>
                        <span class="badge bg-light text-secondary border">Subject Teacher</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <a href="<?= BASE_URL ?>/teacher/attendance_daily.php?class_id=<?= $as['class_id'] ?>&section_id=<?= $as['section_id'] ?>" class="btn btn-outline-primary" title="Mark Daily Attendance">
                          <i class="fas fa-calendar-check me-1"></i> Daily
                        </a>
                        <a href="<?= BASE_URL ?>/teacher/attendance_subject.php?class_id=<?= $as['class_id'] ?>&section_id=<?= $as['section_id'] ?>&subject_id=<?= (int)$as['subject_id'] ?>" class="btn btn-outline-secondary" title="Mark Subject Attendance">
                          <i class="fas fa-book-reader me-1"></i> Subject
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="text-center py-4 text-muted">No classes have been assigned to your teacher account yet. Contact Administrator or Principal.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Weekly Timetable Preview -->
  <div class="col-lg-4">
    <div class="card card-custom h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title"><i class="fas fa-clock me-2 text-primary"></i> Today's Schedule</h5>
        <a href="<?= BASE_URL ?>/teacher/timetable.php" class="btn btn-sm btn-link p-0 text-decoration-none">Full Timetable</a>
      </div>
      <div class="card-body p-0">
        <?php
          $dayName = date('l');
          $ttStmt = $pdo->prepare("SELECT tt.*, c.name as class_name, sec.name as section_name, sub.name as subject_name
                                   FROM timetable tt
                                   JOIN classes c ON tt.class_id = c.id
                                   JOIN sections sec ON tt.section_id = sec.id
                                   JOIN subjects sub ON tt.subject_id = sub.id
                                   WHERE tt.teacher_id = ? AND tt.day_of_week = ?
                                   ORDER BY tt.period_number ASC");
          $ttStmt->execute([$teacherId, $dayName]);
          $todaySchedule = $ttStmt->fetchAll();
        ?>
        <?php if (!empty($todaySchedule)): ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($todaySchedule as $slot): ?>
              <li class="list-group-item p-3">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="badge bg-primary">Period <?= $slot['period_number'] ?></span>
                  <small class="text-muted"><i class="far fa-clock me-1"></i> <?= format_time($slot['start_time']) ?> - <?= format_time($slot['end_time']) ?></small>
                </div>
                <div class="fw-bold text-dark mt-1"><?= e($slot['subject_name']) ?></div>
                <div class="text-secondary small"><?= e($slot['class_name']) ?> - Sec <?= e($slot['section_name']) ?> &bull; <?= e($slot['room_no'] ?? 'Room') ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="p-4 text-center text-muted">
            <i class="far fa-calendar-check text-success fa-2x mb-2 d-block"></i>
            No scheduled lectures on your timetable for <?= $dayName ?>.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
