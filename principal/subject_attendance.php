<?php
// principal/subject_attendance.php - Subject-Wise Student Attendance
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['principal', 'admin']);

$pageTitle = 'Subject-Wise Attendance';
$pdo = get_db_connection();
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$selectedClassId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 10));

$secStmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? ORDER BY name ASC");
$secStmt->execute([$selectedClassId]);
$sections = $secStmt->fetchAll();
$selectedSectionId = (int)($_GET['section_id'] ?? ($sections[0]['id'] ?? 1));

// Fetch subjects mapped to this class
$subStmt = $pdo->prepare("SELECT s.* 
                          FROM subjects s
                          JOIN class_subjects cs ON s.id = cs.subject_id
                          WHERE cs.class_id = ?
                          ORDER BY s.name ASC");
$subStmt->execute([$selectedClassId]);
$subjects = $subStmt->fetchAll();
if (empty($subjects)) {
    // Fallback: all subjects
    $subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
}
$selectedSubjectId = (int)($_GET['subject_id'] ?? ($subjects[0]['id'] ?? 1));

$date = $_GET['date'] ?? date('Y-m-d');
$period = (int)($_GET['period'] ?? 1);

// Handle POST to Save Subject Attendance
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Security token invalid.');
    } else {
        $studentStatuses = $_POST['status'] ?? [];
        $remarks = $_POST['remarks'] ?? [];
        $attDate = $_POST['attendance_date'] ?? $date;
        $classId = (int)($_POST['class_id'] ?? $selectedClassId);
        $sectionId = (int)($_POST['section_id'] ?? $selectedSectionId);
        $subjectId = (int)($_POST['subject_id'] ?? $selectedSubjectId);
        $lecturePeriod = (int)($_POST['lecture_period'] ?? $period);
        $currentUserId = (int)$_SESSION['user']['id'];

        if (!empty($studentStatuses) && $classId > 0 && $sectionId > 0 && $subjectId > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO subject_attendance (student_id, class_id, section_id, subject_id, academic_year_id, lecture_period, attendance_date, status, marked_by, remarks)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), remarks = VALUES(remarks)");

                foreach ($studentStatuses as $studentId => $status) {
                    $validStatus = in_array($status, ['present', 'absent', 'late', 'half_day'], true) ? $status : 'present';
                    $remark = trim($remarks[$studentId] ?? '');
                    $stmt->execute([(int)$studentId, $classId, $sectionId, $subjectId, $yearId, $lecturePeriod, $attDate, $validStatus, $currentUserId, $remark]);
                }

                $pdo->commit();
                log_audit('SUBJECT_ATTENDANCE_SAVED', "Principal recorded subject attendance (Subject {$subjectId}, Period {$lecturePeriod}) for Class {$classId}-Sec {$sectionId} on {$attDate}.");
                flash_message('success', "Subject attendance for Period {$lecturePeriod} saved successfully.");
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_message('danger', "Error saving subject attendance: " . $e->getMessage());
            }
        }
    }
    header("Location: " . BASE_URL . "/principal/subject_attendance.php?class_id={$selectedClassId}&section_id={$selectedSectionId}&subject_id={$selectedSubjectId}&period={$period}&date={$date}");
    exit;
}

// Fetch enrolled students & existing attendance for this subject, date and period
$sStmt = $pdo->prepare("SELECT s.*, 
                               sa.status as recorded_status, sa.remarks as recorded_remarks
                        FROM students s
                        LEFT JOIN subject_attendance sa ON s.id = sa.student_id AND sa.subject_id = ? AND sa.attendance_date = ? AND sa.lecture_period = ?
                        WHERE s.class_id = ? AND s.section_id = ?
                        ORDER BY s.roll_no ASC");
$sStmt->execute([$selectedSubjectId, $date, $period, $selectedClassId, $selectedSectionId]);
$students = $sStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-book-reader me-2 text-primary"></i> Subject-Wise Attendance</h1>
    <p class="page-subtitle">Record and inspect granular attendance per lecture period and subject syllabus.</p>
  </div>
</div>

<!-- Filter Bar -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Class</label>
        <select name="class_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($selectedClassId === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-bold mb-1">Section</label>
        <select name="section_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($selectedSectionId === (int)$s['id']) ? 'selected' : '' ?>>Section <?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Subject</label>
        <select name="subject_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($subjects as $sub): ?>
            <option value="<?= $sub['id'] ?>" <?= ($selectedSubjectId === (int)$sub['id']) ? 'selected' : '' ?>><?= e($sub['name']) ?> (<?= e($sub['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-bold mb-1">Lecture Period</label>
        <select name="period" class="form-select" onchange="this.form.submit()">
          <?php for ($p = 1; $p <= 6; $p++): ?>
            <option value="<?= $p ?>" <?= ($period === $p) ? 'selected' : '' ?>>Period <?= $p ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-bold mb-1">Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()">
      </div>
    </form>
  </div>
</div>

<!-- Attendance Form -->
<form method="POST" action="">
  <?= csrf_field() ?>
  <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
  <input type="hidden" name="section_id" value="<?= $selectedSectionId ?>">
  <input type="hidden" name="subject_id" value="<?= $selectedSubjectId ?>">
  <input type="hidden" name="lecture_period" value="<?= $period ?>">
  <input type="hidden" name="attendance_date" value="<?= e($date) ?>">

  <div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h5 class="card-title mb-0">Enrolled Students Roster (Period <?= $period ?>)</h5>
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
                  <td><strong>#<?= $stu['roll_no'] ?></strong></td>
                  <td><strong><?= e($stu['full_name']) ?></strong></td>
                  <td><code><?= e($stu['student_id']) ?></code></td>
                  <td class="text-center">
                    <div class="attendance-toggle-group">
                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="sub_pres_<?= $stu['id'] ?>" value="present" <?= ($currentStatus === 'present') ? 'checked' : '' ?>>
                      <label for="sub_pres_<?= $stu['id'] ?>">Present</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="sub_abs_<?= $stu['id'] ?>" value="absent" <?= ($currentStatus === 'absent') ? 'checked' : '' ?>>
                      <label for="sub_abs_<?= $stu['id'] ?>">Absent</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="sub_late_<?= $stu['id'] ?>" value="late" <?= ($currentStatus === 'late') ? 'checked' : '' ?>>
                      <label for="sub_late_<?= $stu['id'] ?>">Late</label>

                      <input type="radio" name="status[<?= $stu['id'] ?>]" id="sub_half_<?= $stu['id'] ?>" value="half_day" <?= ($currentStatus === 'half_day') ? 'checked' : '' ?>>
                      <label for="sub_half_<?= $stu['id'] ?>">Half Day</label>
                    </div>
                  </td>
                  <td>
                    <input type="text" name="remarks[<?= $stu['id'] ?>]" class="form-control form-control-sm" placeholder="Notes..." value="<?= e($stu['recorded_remarks'] ?? '') ?>">
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center py-4 text-muted">No students found.</td></tr>
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
