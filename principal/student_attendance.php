<?php
// principal/student_attendance.php - Take & Manage Daily Student Attendance
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['principal', 'admin']);

$pageTitle = 'Take Student Attendance';
$pdo = get_db_connection();
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$selectedClassId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 10));

$secStmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? ORDER BY name ASC");
$secStmt->execute([$selectedClassId]);
$sections = $secStmt->fetchAll();
$selectedSectionId = (int)($_GET['section_id'] ?? ($sections[0]['id'] ?? 1));

$date = $_GET['date'] ?? date('Y-m-d');

// Handle Save Attendance
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Session expired. Please try again.');
    } else {
        $studentStatuses = $_POST['status'] ?? [];
        $remarks = $_POST['remarks'] ?? [];
        $attDate = $_POST['attendance_date'] ?? $date;
        $classId = (int)($_POST['class_id'] ?? $selectedClassId);
        $sectionId = (int)($_POST['section_id'] ?? $selectedSectionId);
        $currentUserId = (int)$_SESSION['user']['id'];

        if (!empty($studentStatuses) && $classId > 0 && $sectionId > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO daily_attendance (student_id, class_id, section_id, academic_year_id, attendance_date, status, marked_by, remarks)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), remarks = VALUES(remarks)");

                foreach ($studentStatuses as $studentId => $status) {
                    $validStatus = in_array($status, ['present', 'absent', 'late', 'half_day'], true) ? $status : 'present';
                    $remark = trim($remarks[$studentId] ?? '');
                    $stmt->execute([(int)$studentId, $classId, $sectionId, $yearId, $attDate, $validStatus, $currentUserId, $remark]);
                }

                $pdo->commit();
                log_audit('DAILY_ATTENDANCE_SAVED', "Principal recorded attendance for Class {$classId}, Section {$sectionId} on {$attDate}.");
                flash_message('success', "Daily attendance for " . count($studentStatuses) . " students saved successfully.");
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_message('danger', "Error saving attendance: " . $e->getMessage());
            }
        }
    }
    header("Location: " . BASE_URL . "/principal/student_attendance.php?class_id={$selectedClassId}&section_id={$selectedSectionId}&date={$date}");
    exit;
}

// Fetch Students in this Class and Section
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
    <h1 class="page-title"><i class="fas fa-calendar-check me-2 text-primary"></i> Daily Student Attendance</h1>
    <p class="page-subtitle">Select class, section, and date to mark or review attendance records.</p>
  </div>
</div>

<!-- Class / Section Filter -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label small fw-bold mb-1">Class / Standard</label>
        <select name="class_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($selectedClassId === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-bold mb-1">Section</label>
        <select name="section_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($selectedSectionId === (int)$s['id']) ? 'selected' : '' ?>>Section <?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Attendance Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()">
      </div>

      <div class="col-md-1 d-flex align-items-end">
        <button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-search"></i></button>
      </div>
    </form>
  </div>
</div>

<!-- Attendance Form -->
<form method="POST" action="">
  <?= csrf_field() ?>
  <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
  <input type="hidden" name="section_id" value="<?= $selectedSectionId ?>">
  <input type="hidden" name="attendance_date" value="<?= e($date) ?>">

  <div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h5 class="card-title mb-0">Class Roster (<?= count($students) ?> Students Enrolled)</h5>
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
              <th class="text-center" style="width: 320px;">Attendance Status</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($students)): ?>
              <?php foreach ($students as $stu): ?>
                <?php $currentStatus = $stu['recorded_status'] ?? 'present'; ?>
                <tr>
                  <td><strong class="fs-6">#<?= $stu['roll_no'] ?></strong></td>
                  <td>
                    <div class="fw-bold text-dark"><?= e($stu['full_name']) ?></div>
                    <small class="text-muted">Adm: <?= e($stu['admission_no']) ?></small>
                  </td>
                  <td><code><?= e($stu['student_id']) ?></code></td>
                  <td class="text-center">
                    <div class="attendance-toggle-group">
                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="pres_<?= $stu['id'] ?>" value="present" <?= ($currentStatus === 'present') ? 'checked' : '' ?>>
                      <label for="pres_<?= $stu['id'] ?>"><i class="fas fa-check me-1"></i>Present</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="abs_<?= $stu['id'] ?>" value="absent" <?= ($currentStatus === 'absent') ? 'checked' : '' ?>>
                      <label for="abs_<?= $stu['id'] ?>"><i class="fas fa-times me-1"></i>Absent</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="late_<?= $stu['id'] ?>" value="late" <?= ($currentStatus === 'late') ? 'checked' : '' ?>>
                      <label for="late_<?= $stu['id'] ?>"><i class="fas fa-clock me-1"></i>Late</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="half_<?= $stu['id'] ?>" value="half_day" <?= ($currentStatus === 'half_day') ? 'checked' : '' ?>>
                      <label for="half_<?= $stu['id'] ?>"><i class="fas fa-adjust me-1"></i>Half Day</label>
                    </div>
                  </td>
                  <td>
                    <input type="text" name="remarks[<?= $stu['id'] ?>]" class="form-control form-control-sm" placeholder="Optional remark..." value="<?= e($stu['recorded_remarks'] ?? '') ?>">
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center py-4 text-muted">No students enrolled in this class and section.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (!empty($students)): ?>
      <div class="card-footer bg-white p-3 text-end">
        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
          <i class="fas fa-save me-1"></i> Save Attendance Records
        </button>
      </div>
    <?php endif; ?>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
