<?php
// teacher/attendance_daily.php - Daily Student Attendance Module
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'Take Daily Attendance';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacherId = (int)$tStmt->fetchColumn();

// Fetch assigned classes/sections
$assignStmt = $pdo->prepare("SELECT DISTINCT ta.class_id, ta.section_id, c.name as class_name, sec.name as section_name
                             FROM teacher_assignments ta
                             JOIN classes c ON ta.class_id = c.id
                             JOIN sections sec ON ta.section_id = sec.id
                             WHERE ta.teacher_id = ?");
$assignStmt->execute([$teacherId]);
$assignedPairs = $assignStmt->fetchAll();

if (empty($assignedPairs)) {
    flash_message('danger', 'You are not assigned to any class yet.');
    header('Location: ' . BASE_URL . '/teacher/index.php');
    exit;
}

$selectedPair = $_GET['class_section'] ?? ($assignedPairs[0]['class_id'] . '_' . $assignedPairs[0]['section_id']);
$parts = explode('_', $selectedPair);
$selectedClassId = (int)($parts[0] ?? $assignedPairs[0]['class_id']);
$selectedSectionId = (int)($parts[1] ?? $assignedPairs[0]['section_id']);
$date = $_GET['date'] ?? date('Y-m-d');

// Authorize selected class and section
$isAuthorized = false;
foreach ($assignedPairs as $p) {
    if ($p['class_id'] == $selectedClassId && $p['section_id'] == $selectedSectionId) {
        $isAuthorized = true;
        break;
    }
}
if (!$isAuthorized) {
    $selectedClassId = (int)$assignedPairs[0]['class_id'];
    $selectedSectionId = (int)$assignedPairs[0]['section_id'];
    $selectedPair = $selectedClassId . '_' . $selectedSectionId;
}

// Handle Attendance Save POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Session token invalid.');
    } else {
        $studentStatuses = $_POST['status'] ?? [];
        $remarks = $_POST['remarks'] ?? [];
        $attDate = $_POST['attendance_date'] ?? $date;

        if (!empty($studentStatuses)) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO daily_attendance (student_id, class_id, section_id, academic_year_id, attendance_date, status, marked_by, remarks)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), remarks = VALUES(remarks)");

                foreach ($studentStatuses as $studentId => $status) {
                    $validStatus = in_array($status, ['present', 'absent', 'late', 'half_day'], true) ? $status : 'present';
                    $remark = trim($remarks[$studentId] ?? '');
                    $stmt->execute([(int)$studentId, $selectedClassId, $selectedSectionId, $yearId, $attDate, $validStatus, $currentUserId, $remark]);
                }

                $pdo->commit();
                log_audit('TEACHER_DAILY_ATTENDANCE_SAVED', "Teacher marked attendance for Class {$selectedClassId}-Sec {$selectedSectionId} on {$attDate}.", $currentUserId);
                flash_message('success', "Daily attendance successfully recorded for " . count($studentStatuses) . " students.");
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_message('danger', "Error saving attendance: " . $e->getMessage());
            }
        }
    }
    header("Location: " . BASE_URL . "/teacher/attendance_daily.php?class_section={$selectedPair}&date={$date}");
    exit;
}

// Fetch Students in this Class and Section with recorded status
$sStmt = $pdo->prepare("SELECT s.*, 
                               a.status as recorded_status, a.remarks as recorded_remarks, a.id as attendance_id
                        FROM students s
                        LEFT JOIN daily_attendance a ON s.id = a.student_id AND a.attendance_date = ?
                        WHERE s.class_id = ? AND s.section_id = ?
                        ORDER BY s.roll_no ASC");
$sStmt->execute([$date, $selectedClassId, $selectedSectionId]);
$students = $sStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-calendar-check me-2 text-primary"></i> Daily Class Attendance</h1>
    <p class="page-subtitle">Mark daily classroom presence for your assigned student division.</p>
  </div>
</div>

<!-- Class & Date Filter Bar -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Select Assigned Class & Section</label>
        <select name="class_section" class="form-select" onchange="this.form.submit()">
          <?php foreach ($assignedPairs as $p): ?>
            <?php $key = $p['class_id'] . '_' . $p['section_id']; ?>
            <option value="<?= $key ?>" <?= ($selectedPair === $key) ? 'selected' : '' ?>>
              <?= e($p['class_name']) ?> - Section <?= e($p['section_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Attendance Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()">
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-sync me-1"></i> Load Roster</button>
      </div>
    </form>
  </div>
</div>

<!-- Attendance Form -->
<form method="POST" action="">
  <?= csrf_field() ?>
  <input type="hidden" name="attendance_date" value="<?= e($date) ?>">

  <div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h5 class="card-title mb-0">Class Roster (<?= count($students) ?> Students)</h5>
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
              <th class="text-center" style="width: 320px;">Status</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($students)): ?>
              <?php foreach ($students as $stu): ?>
                <?php $currentStatus = $stu['recorded_status'] ?? 'present'; ?>
                <tr>
                  <td><strong class="fs-6">#<?= $stu['roll_no'] ?></strong></td>
                  <td><strong><?= e($stu['full_name']) ?></strong></td>
                  <td><code><?= e($stu['student_id']) ?></code></td>
                  <td class="text-center">
                    <div class="attendance-toggle-group">
                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="d_pres_<?= $stu['id'] ?>" value="present" <?= ($currentStatus === 'present') ? 'checked' : '' ?>>
                      <label for="d_pres_<?= $stu['id'] ?>">Present</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="d_abs_<?= $stu['id'] ?>" value="absent" <?= ($currentStatus === 'absent') ? 'checked' : '' ?>>
                      <label for="d_abs_<?= $stu['id'] ?>">Absent</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="d_late_<?= $stu['id'] ?>" value="late" <?= ($currentStatus === 'late') ? 'checked' : '' ?>>
                      <label for="d_late_<?= $stu['id'] ?>">Late</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="d_half_<?= $stu['id'] ?>" value="half_day" <?= ($currentStatus === 'half_day') ? 'checked' : '' ?>>
                      <label for="d_half_<?= $stu['id'] ?>">Half Day</label>
                    </div>
                  </td>
                  <td>
                    <input type="text" name="remarks[<?= $stu['id'] ?>]" class="form-control form-control-sm" placeholder="Notes..." value="<?= e($stu['recorded_remarks'] ?? '') ?>">
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center py-4 text-muted">No students enrolled in this division.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (!empty($students)): ?>
      <div class="card-footer bg-white p-3 text-end">
        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
          <i class="fas fa-save me-1"></i> Save Attendance
        </button>
      </div>
    <?php endif; ?>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
