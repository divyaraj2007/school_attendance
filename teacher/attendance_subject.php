<?php
// teacher/attendance_subject.php - Subject-Wise Attendance Module
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'Take Subject Attendance';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacherId = (int)$tStmt->fetchColumn();

// Fetch assigned classes/sections/subjects for this teacher
$assignStmt = $pdo->prepare("SELECT ta.*, c.name as class_name, sec.name as section_name, sub.name as subject_name, sub.code as subject_code
                             FROM teacher_assignments ta
                             JOIN classes c ON ta.class_id = c.id
                             JOIN sections sec ON ta.section_id = sec.id
                             LEFT JOIN subjects sub ON ta.subject_id = sub.id
                             WHERE ta.teacher_id = ? AND ta.subject_id IS NOT NULL");
$assignStmt->execute([$teacherId]);
$assignedSlots = $assignStmt->fetchAll();

if (empty($assignedSlots)) {
    // If no subject assignment, allow general assigned classes
    $assignStmt = $pdo->prepare("SELECT ta.*, c.name as class_name, sec.name as section_name, sub.name as subject_name, sub.code as subject_code
                                 FROM teacher_assignments ta
                                 JOIN classes c ON ta.class_id = c.id
                                 JOIN sections sec ON ta.section_id = sec.id
                                 LEFT JOIN subjects sub ON ta.subject_id = sub.id
                                 WHERE ta.teacher_id = ?");
    $assignStmt->execute([$teacherId]);
    $assignedSlots = $assignStmt->fetchAll();
}

if (empty($assignedSlots)) {
    flash_message('danger', 'You are not assigned to any subject yet.');
    header('Location: ' . BASE_URL . '/teacher/index.php');
    exit;
}

$defaultSlot = $assignedSlots[0];
$selectedSlotId = (int)($_GET['slot_id'] ?? $defaultSlot['id']);

$activeSlot = null;
foreach ($assignedSlots as $slot) {
    if ($slot['id'] == $selectedSlotId) {
        $activeSlot = $slot;
        break;
    }
}
if (!$activeSlot) {
    $activeSlot = $defaultSlot;
    $selectedSlotId = (int)$defaultSlot['id'];
}

$classId = (int)$activeSlot['class_id'];
$sectionId = (int)$activeSlot['section_id'];
$subjectId = (int)($activeSlot['subject_id'] ?? 1);
$period = (int)($_GET['period'] ?? 1);
$date = $_GET['date'] ?? date('Y-m-d');

// Handle Save Subject Attendance
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Security session token expired.');
    } else {
        $studentStatuses = $_POST['status'] ?? [];
        $remarks = $_POST['remarks'] ?? [];
        $attDate = $_POST['attendance_date'] ?? $date;
        $lecturePeriod = (int)($_POST['lecture_period'] ?? $period);

        if (!empty($studentStatuses)) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO subject_attendance (student_id, class_id, section_id, subject_id, academic_year_id, lecture_period, attendance_date, status, marked_by, remarks)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), remarks = VALUES(remarks)");

                foreach ($studentStatuses as $stuId => $status) {
                    $validStatus = in_array($status, ['present', 'absent', 'late', 'half_day'], true) ? $status : 'present';
                    $remark = trim($remarks[$stuId] ?? '');
                    $stmt->execute([(int)$stuId, $classId, $sectionId, $subjectId, $yearId, $lecturePeriod, $attDate, $validStatus, $currentUserId, $remark]);
                }

                $pdo->commit();
                log_audit('TEACHER_SUBJECT_ATTENDANCE_SAVED', "Teacher marked subject attendance for Period {$lecturePeriod} on {$attDate}.", $currentUserId);
                flash_message('success', "Subject attendance for Period {$lecturePeriod} saved.");
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_message('danger', "Failed to save subject attendance: " . $e->getMessage());
            }
        }
    }
    header("Location: " . BASE_URL . "/teacher/attendance_subject.php?slot_id={$selectedSlotId}&period={$period}&date={$date}");
    exit;
}

// Fetch enrolled students, existing status for today's period, and overall subject attendance percentage!
$sql = "SELECT s.*, 
               sa.status as recorded_status, sa.remarks as recorded_remarks,
               COUNT(all_sa.id) as total_subject_lectures,
               COUNT(CASE WHEN all_sa.status = 'present' THEN 1 END) as present_subject_lectures
        FROM students s
        LEFT JOIN subject_attendance sa ON (s.id = sa.student_id AND sa.subject_id = ? AND sa.attendance_date = ? AND sa.lecture_period = ?)
        LEFT JOIN subject_attendance all_sa ON (s.id = all_sa.student_id AND all_sa.subject_id = ?)
        WHERE s.class_id = ? AND s.section_id = ?
        GROUP BY s.id
        ORDER BY s.roll_no ASC";
$sStmt = $pdo->prepare($sql);
$sStmt->execute([$subjectId, $date, $period, $subjectId, $classId, $sectionId]);
$students = $sStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-book-open me-2 text-primary"></i> Subject Lecture Attendance</h1>
    <p class="page-subtitle">Track lecture attendance with real-time student subject-wise percentages.</p>
  </div>
</div>

<!-- Selection Bar -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Teaching Allocation (Class - Section - Subject)</label>
        <select name="slot_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($assignedSlots as $slot): ?>
            <option value="<?= $slot['id'] ?>" <?= ($selectedSlotId === (int)$slot['id']) ? 'selected' : '' ?>>
              <?= e($slot['class_name']) ?> (Sec <?= e($slot['section_name']) ?>): <?= e($slot['subject_name'] ?? 'General') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Lecture Period</label>
        <select name="period" class="form-select" onchange="this.form.submit()">
          <?php for ($p = 1; $p <= 6; $p++): ?>
            <option value="<?= $p ?>" <?= ($period === $p) ? 'selected' : '' ?>>Period <?= $p ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()">
      </div>

      <div class="col-md-1 d-flex align-items-end">
        <button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-search"></i></button>
      </div>
    </form>
  </div>
</div>

<!-- Subject Attendance Form -->
<form method="POST" action="">
  <?= csrf_field() ?>
  <input type="hidden" name="attendance_date" value="<?= e($date) ?>">
  <input type="hidden" name="lecture_period" value="<?= $period ?>">

  <div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h5 class="card-title mb-0">
          <?= e($activeSlot['subject_name'] ?? 'Subject') ?> &bull; Period <?= $period ?> (<?= count($students) ?> Students)
        </h5>
        <small class="text-muted">Date: <strong><?= format_date($date) ?></strong></small>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-success" id="markAllPresentBtn">
          <i class="fas fa-check-double me-1"></i> Mark All Present
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger" id="markAllAbsentBtn">
          <i class="fas fa-times me-1"></i> Mark All Absent
        </button>
      </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 70px;">Roll #</th>
              <th>Student Name</th>
              <th>Student ID</th>
              <th>Subject Attendance %</th>
              <th class="text-center" style="width: 320px;">Status</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($students)): ?>
              <?php foreach ($students as $stu): ?>
                <?php
                  $currentStatus = $stu['recorded_status'] ?? 'present';
                  $totalLec = (int)$stu['total_subject_lectures'];
                  $presLec = (int)$stu['present_subject_lectures'];
                  $subPct = $totalLec > 0 ? round(($presLec / $totalLec) * 100, 1) : 100.0;
                ?>
                <tr>
                  <td><strong class="fs-6">#<?= $stu['roll_no'] ?></strong></td>
                  <td><strong><?= e($stu['full_name']) ?></strong></td>
                  <td><code><?= e($stu['student_id']) ?></code></td>
                  <td>
                    <span class="badge bg-light text-dark border fw-bold">
                      <?= $subPct ?>%
                    </span>
                    <small class="text-muted ms-1">(<?= $presLec ?>/<?= $totalLec ?> lecs)</small>
                  </td>
                  <td class="text-center">
                    <div class="attendance-toggle-group">
                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="s_pres_<?= $stu['id'] ?>" value="present" <?= ($currentStatus === 'present') ? 'checked' : '' ?>>
                      <label for="s_pres_<?= $stu['id'] ?>">Present</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="s_abs_<?= $stu['id'] ?>" value="absent" <?= ($currentStatus === 'absent') ? 'checked' : '' ?>>
                      <label for="s_abs_<?= $stu['id'] ?>">Absent</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="s_late_<?= $stu['id'] ?>" value="late" <?= ($currentStatus === 'late') ? 'checked' : '' ?>>
                      <label for="s_late_<?= $stu['id'] ?>">Late</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="s_half_<?= $stu['id'] ?>" value="half_day" <?= ($currentStatus === 'half_day') ? 'checked' : '' ?>>
                      <label for="s_half_<?= $stu['id'] ?>">Half Day</label>
                    </div>
                  </td>
                  <td>
                    <input type="text" name="remarks[<?= $stu['id'] ?>]" class="form-control form-control-sm" placeholder="Notes..." value="<?= e($stu['recorded_remarks'] ?? '') ?>">
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No students found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (!empty($students)): ?>
      <div class="card-footer bg-white p-3 text-end">
        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
          <i class="fas fa-save me-1"></i> Save Subject Attendance
        </button>
      </div>
    <?php endif; ?>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
